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
const header = (name) => main.match(new RegExp('^\\s*\\* ' + name + ':\\s*(.+)$', 'm'))?.[1].trim();

const checks = [
  ['main version', header('Version') === pkg.version && main.includes("define( 'NESCM_VERSION', '" + pkg.version + "' );")],
  ['readme stable tag', readme.includes('Stable tag: ' + pkg.version)],
  ['plugin site', header('Plugin URI') === 'https://github.com/ranaweb/nes-calendar-memberships'],
  ['author', header('Author') === 'Rana Mustafiz and Cider House'],
  ['requires php', /Requires PHP:\s*8\.1/.test(main) && /Requires PHP:\s*8\.1/.test(readme)],
  ['tested up to', /Tested up to:\s*7\.0/.test(main) && /Tested up to:\s*7\.0/.test(readme)],
  ['plugin name', header('Plugin Name') === 'NES Calendar & Memberships'],
];

for (const [name, ok] of checks) {
  if (!ok) fail(`Release metadata check failed: ${name}`);
}

const requiredFiles = [
  'nes-calendar-memberships.php',
  'includes/class-plugin.php',
  'includes/class-memberpress-adapter.php',
  'includes/class-settings.php',
  'includes/class-year-calculator.php',
  'includes/class-membership-meta.php',
  'includes/class-renewal-router.php',
  'includes/class-checkout-messaging.php',
  'includes/class-transaction-sync.php',
  'includes/class-dashboard-shortcodes.php',
  'includes/class-admin-manual-renewal.php',
  'includes/class-year-generator.php',
  'includes/class-validator.php',
  'calendar/calendar.php',
  'calendar/includes/class-series.php',
  'calendar/includes/class-service.php',
  'calendar/includes/class-engine.php',
  'calendar/assets/series.js',
  'calendar/assets/recurrence.js',
  'README.md',
  'readme.txt',
  'LICENSE',
  'uninstall.php',
];

if (/Plugin Name:/i.test(read(path.join(root, 'calendar/calendar.php')))) fail('The package must have one plugin entry point.');

for (const rel of requiredFiles) {
  if (!fs.existsSync(path.join(root, rel))) {
    fail(`Required file missing: ${rel}`);
  }
}

if (!process.exitCode) {
  console.log('Release metadata checks passed.');
}
