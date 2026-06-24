<?php
/**
 * Shared helper functions.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nescm_get_families(): array {
	return array(
		'resident'           => __( 'Resident Membership', 'nes-calendar-memberships' ),
		'joint_resident'     => __( 'Joint Resident Membership', 'nes-calendar-memberships' ),
		'junior'             => __( 'Junior Membership', 'nes-calendar-memberships' ),
		'nonresident'        => __( 'Nonresident Membership', 'nes-calendar-memberships' ),
		'pilgrim_individual' => __( 'Pilgrim Circle Individual', 'nes-calendar-memberships' ),
		'pilgrim_joint'      => __( 'Pilgrim Circle Joint', 'nes-calendar-memberships' ),
		'firewood_individual'=> __( 'Firewood Circle Individual', 'nes-calendar-memberships' ),
		'firewood_joint'     => __( 'Firewood Circle Joint', 'nes-calendar-memberships' ),
	);
}

function nescm_get_renewal_method_types(): array {
	return array(
		'manual'     => __( 'Manual', 'nes-calendar-memberships' ),
		'auto_renew' => __( 'Automatic Renewal', 'nes-calendar-memberships' ),
		'legacy'     => __( 'Legacy', 'nes-calendar-memberships' ),
		'lifetime'   => __( 'Lifetime', 'nes-calendar-memberships' ),
	);
}

function nescm_is_valid_family_key( string $family_key ): bool {
	return array_key_exists( $family_key, nescm_get_families() );
}

function nescm_parse_date( string $date ): ?DateTimeImmutable {
	$date = trim( $date );

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return null;
	}

	$timezone = wp_timezone();
	$parsed   = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
	$errors   = DateTimeImmutable::getLastErrors();

	if ( ! $parsed || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
		return null;
	}

	return $parsed;
}

function nescm_format_display_date( string $date ): string {
	$parsed = nescm_parse_date( $date );

	if ( ! $parsed ) {
		return '';
	}

	return wp_date( 'F j, Y', $parsed->getTimestamp(), wp_timezone() );
}

function nescm_admin_capability(): string {
	return 'manage_options';
}

function nescm_add_admin_notice( string $message, string $type = 'warning' ): void {
	$user_id = get_current_user_id();

	if ( $user_id <= 0 ) {
		return;
	}

	$key     = 'nescm_admin_notices_' . $user_id;
	$notices = get_transient( $key );
	$notices = is_array( $notices ) ? $notices : array();

	$notices[] = array(
		'message' => wp_strip_all_tags( $message ),
		'type'    => in_array( $type, array( 'success', 'info', 'warning', 'error' ), true ) ? $type : 'warning',
	);

	set_transient( $key, $notices, MINUTE_IN_SECONDS );
}

function nescm_render_admin_notices(): void {
	$user_id = get_current_user_id();

	if ( $user_id <= 0 ) {
		return;
	}

	$key     = 'nescm_admin_notices_' . $user_id;
	$notices = get_transient( $key );

	if ( ! is_array( $notices ) || empty( $notices ) ) {
		return;
	}

	delete_transient( $key );

	foreach ( $notices as $notice ) {
		$type    = isset( $notice['type'] ) ? sanitize_html_class( (string) $notice['type'] ) : 'warning';
		$message = isset( $notice['message'] ) ? (string) $notice['message'] : '';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}

function nescm_render_template( string $template, array $vars = array() ): string {
	$file = NESCM_PATH . 'templates/' . ltrim( $template, '/' );

	if ( ! file_exists( $file ) ) {
		return '';
	}

	ob_start();
	extract( $vars, EXTR_SKIP );
	include $file;
	return (string) ob_get_clean();
}

function nescm_current_request_url(): string {
	$scheme = is_ssl() ? 'https://' : 'http://';
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	return esc_url_raw( $scheme . $host . $uri );
}

function nescm_admin_page_url( string $tab = 'settings' ): string {
	$base = defined( 'MEPR_VERSION' ) ? 'admin.php' : 'options-general.php';

	return add_query_arg(
		array(
			'page' => 'nes-calendar-memberships',
			'tab'  => sanitize_key( $tab ),
		),
		admin_url( $base )
	);
}
