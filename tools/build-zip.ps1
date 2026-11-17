param(
  [string]$Version
)

$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
if (-not $Version) { $Version = (Get-Content -Raw -LiteralPath (Join-Path $Root "package.json") | ConvertFrom-Json).version }
$PluginSlug = "nes-calendar-memberships"
$Dist = Join-Path $Root "dist"

New-Item -ItemType Directory -Force -Path $Dist | Out-Null

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$PlainZip = Join-Path $Dist "$PluginSlug.zip"
$VersionZip = Join-Path $Dist "$PluginSlug-$Version.zip"

function New-ZipWithForwardSlashes {
  param(
    [string]$SourceDirectory,
    [string]$DestinationZip
  )

  if (Test-Path $DestinationZip) {
    Remove-Item -LiteralPath $DestinationZip -Force
  }

  $Archive = [System.IO.Compression.ZipFile]::Open($DestinationZip, [System.IO.Compression.ZipArchiveMode]::Create)
  try {
    $Base = (Get-Item -LiteralPath $SourceDirectory).FullName.TrimEnd("\", "/")
    $Files = foreach ($Top in @('nes-calendar-memberships.php','uninstall.php','readme.txt','README.md','CHANGELOG.md','LICENSE','assets','includes','templates','calendar')) {
      $Item = Get-Item -LiteralPath (Join-Path $Base $Top)
      if ($Item.PSIsContainer) { Get-ChildItem -LiteralPath $Item.FullName -File -Recurse } else { $Item }
    }
    $Files | Sort-Object FullName | ForEach-Object {
      $FullName = $_.FullName
      $Relative = $FullName.Substring($Base.Length + 1).Replace("\", "/")
      [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($Archive, $FullName, "$PluginSlug/$Relative") | Out-Null
    }
  }
  finally {
    $Archive.Dispose()
  }
}

New-ZipWithForwardSlashes -SourceDirectory $Root -DestinationZip $PlainZip
Copy-Item -LiteralPath $PlainZip -Destination $VersionZip -Force

$Zip = [System.IO.Compression.ZipFile]::OpenRead($PlainZip)
$RequiredPath = "$PluginSlug/$PluginSlug.php"
$HasMain = $false
foreach ($Entry in $Zip.Entries) {
  if ($Entry.FullName -eq $RequiredPath) {
    $HasMain = $true
    break
  }
}
$Zip.Dispose()

if (-not $HasMain) {
  throw "ZIP verification failed. Missing $RequiredPath"
}

Write-Host "Built $PlainZip"
Write-Host "Built $VersionZip"
