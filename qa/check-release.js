const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const mainPath = path.join(root, 'nes-calendar-memberships.php');
const readmePath = path.join(root, 'readme.txt');
const packagePath = path.join(root, 'package.json');

function fail(message) {
  console.error(message);
  process.exitCode = 1;
}

function read(file) {
  return fs.readFileSync(file, 'utf8');
}

const main = read(mainPath);
const readme = read(readmePath);
const pkg = JSON.parse(read(packagePath));

// package.json is the single source of truth for the version.
const version = pkg.version;
const versionPattern = version.replace(/\./g, '\\.');

const checks = [
  ['main header version', new RegExp(`Version:\\s*${versionPattern}\\b`).test(main)],
  ['main NESCM_VERSION constant', new RegExp(`NESCM_VERSION',\\s*'${versionPattern}'`).test(main)],
  ['readme stable tag', new RegExp(`Stable tag:\\s*${versionPattern}\\b`).test(readme)],
  ['author', /Author:\s*Cider House/.test(main)],
  ['requires php', /Requires PHP:\s*8\.1/.test(main) && /Requires PHP:\s*8\.1/.test(readme)],
  ['tested up to', /Tested up to:\s*7\.0/.test(main) && /Tested up to:\s*7\.0/.test(readme)],
  ['plugin name', /Plugin Name:\s*NES Calendar Memberships/.test(main)],
];

for (const [name, ok] of checks) {
  if (!ok) fail(`Release metadata check failed: ${name} (expected version ${version})`);
}

const requiredFiles = [
  'nes-calendar-memberships.php',
  'includes/class-plugin.php',
  'includes/class-memberpress-adapter.php',
  'includes/class-terminology.php',
  'includes/class-settings.php',
  'includes/class-year-calculator.php',
  'includes/class-membership-meta.php',
  'includes/class-membership-types.php',
  'includes/class-renewal-router.php',
  'includes/class-checkout-messaging.php',
  'includes/class-transaction-sync.php',
  'includes/class-dashboard-shortcodes.php',
  'includes/class-admin-manual-renewal.php',
  'includes/class-year-generator.php',
  'includes/class-validator.php',
  'templates/admin-howto.php',
  'templates/admin-membership-types.php',
  'README.md',
  'readme.txt',
  'LICENSE',
  'uninstall.php',
];

for (const rel of requiredFiles) {
  if (!fs.existsSync(path.join(root, rel))) {
    fail(`Required file missing: ${rel}`);
  }
}

if (!process.exitCode) {
  console.log(`Release metadata checks passed for version ${version}.`);
}
