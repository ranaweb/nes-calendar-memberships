<?php
/**
 * Uninstall handler.
 *
 * NES membership metadata and settings are intentionally preserved so uninstalling
 * the plugin cannot silently damage membership configuration or renewal history.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
