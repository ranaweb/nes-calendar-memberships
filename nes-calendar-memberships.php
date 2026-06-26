<?php
/**
 * Plugin Name: NES Calendar Memberships
 * Plugin URI: https://github.com/ranaweb/nes-calendar-memberships
 * Description: Calendar-year membership routing, checkout messaging, dashboard helpers, and admin safety tools for NES MemberPress memberships.
 * Version: 1.0.3
 * Requires at least: 6.5
 * Tested up to: 7.0
 * Requires PHP: 8.1
 * Author: Cider House
 * Author URI: https://github.com/ranaweb/nes-calendar-memberships
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nes-calendar-memberships
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NESCM_VERSION', '1.0.3' );
define( 'NESCM_FILE', __FILE__ );
define( 'NESCM_PATH', plugin_dir_path( __FILE__ ) );
define( 'NESCM_URL', plugin_dir_url( __FILE__ ) );
define( 'NESCM_OPTION', 'nescm_settings' );
define( 'NESCM_FAMILIES_OPTION', 'nescm_custom_families' );
define( 'NESCM_PRODUCT_META_PREFIX', '_nescm_' );
define( 'NESCM_TXN_META_PREFIX', '_nescm_' );

$nescm_files = array(
	'includes/helpers.php',
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
	'includes/class-plugin.php',
);

foreach ( $nescm_files as $nescm_file ) {
	require_once NESCM_PATH . $nescm_file;
}

register_activation_hook( __FILE__, array( 'NESCM_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NESCM_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'nes-calendar-memberships', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		NESCM_Plugin::instance()->init();
	}
);
