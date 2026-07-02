param(
  [string]$Version = "1.0.4"
)

$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$PluginSlug = "nes-calendar-memberships"
$Dist = Join-Path $Root "dist"
$StageRoot = Join-Path $Dist "stage"
$StagePlugin = Join-Path $StageRoot $PluginSlug

if (Test-Path $Dist) {
  Remove-Item -LiteralPath $Dist -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $StagePlugin | Out-Null

$ExcludeTop = @(".git", ".github", "dist", "node_modules", "qa", "tools")
$ExcludeNames = @(".DS_Store", "Thumbs.db", ".gitignore", ".gitattributes", "package.json")

Get-ChildItem -LiteralPath $Root -Force | ForEach-Object {
  if ($ExcludeTop -contains $_.Name) {
    return
  }
  if ($ExcludeNames -contains $_.Name) {
    return
  }
  Copy-Item -LiteralPath $_.FullName -Destination $StagePlugin -Recurse -Force
}

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
    Get-ChildItem -LiteralPath $SourceDirectory -Recurse -File | ForEach-Object {
      $FullName = $_.FullName
      $Relative = $FullName.Substring($Base.Length + 1).Replace("\", "/")
      [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($Archive, $FullName, $Relative) | Out-Null
    }
  }
  finally {
    $Archive.Dispose()
  }
}

New-ZipWithForwardSlashes -SourceDirectory $StageRoot -DestinationZip $PlainZip
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

Remove-Item -LiteralPath $StageRoot -Recurse -Force

Write-Host "Built $PlainZip"
Write-Host "Built $VersionZip"
