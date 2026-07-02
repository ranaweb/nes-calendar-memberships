<?php
/**
 * Renewal route handler.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Renewal_Router {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;
	private NESCM_Year_Calculator $calculator;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings, NESCM_Year_Calculator $calculator ) {
		$this->adapter    = $adapter;
		$this->settings   = $settings;
		$this->calculator = $calculator;
	}

	public function hooks(): void {
		add_action( 'init', array( $this, 'rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_route' ) );
		add_shortcode( 'nescm_renewal_router', array( $this, 'router_shortcode' ) );
		add_shortcode( 'nescm_renewal_url', array( $this, 'url_shortcode' ) );
		add_shortcode( 'nescm_auto_renew_url', array( $this, 'auto_renew_url_shortcode' ) );
	}

	public function rewrite_rules(): void {
		add_rewrite_rule( '^membership-renew/?$', 'index.php?nescm_renew=1', 'top' );
	}

	public function query_vars( array $vars ): array {
		$vars[] = 'nescm_renew';
		return $vars;
	}

	public function maybe_route(): void {
		if ( '1' !== (string) get_query_var( 'nescm_renew' ) ) {
			return;
		}

		$family = isset( $_GET['family'] ) ? sanitize_key( wp_unslash( $_GET['family'] ) ) : '';
		$this->route_family( $family );
	}

	public function route_family( string $family ): void {
		if ( ! $this->adapter->is_active() ) {
			$this->friendly_error( __( 'MemberPress is not available right now. Please contact NES for help renewing your membership.', 'nes-calendar-memberships' ) );
		}

		if ( ! nescm_is_valid_family_key( $family ) ) {
			$this->friendly_error( __( 'We could not identify the membership family for this renewal link. Please contact NES for help renewing your membership.', 'nes-calendar-memberships' ) );
		}

		$year          = $this->calculator->get_membership_year_for_date( $this->calculator->today() );
		$membership_id = $this->adapter->find_membership( $family, $year, 'manual' );

		if ( ! $membership_id || ! $this->adapter->is_published_membership( $membership_id ) ) {
			$admin_detail = sprintf(
				/* translators: 1: family key, 2: year */
				__( 'Missing published manual membership product for family %1$s and year %2$d.', 'nes-calendar-memberships' ),
				$family,
				$year
			);
			$this->adapter->log( $admin_detail );
			$this->friendly_error(
				__( 'We could not find the correct renewal form for this membership. Please contact NES for help renewing your membership.', 'nes-calendar-memberships' ),
				$admin_detail
			);
		}

		wp_safe_redirect( $this->adapter->checkout_url( $membership_id ) );
		exit;
	}

	public function renewal_url( string $family ): string {
		if ( ! nescm_is_valid_family_key( $family ) ) {
			return '';
		}

		return add_query_arg( 'family', rawurlencode( $family ), home_url( '/membership-renew/' ) );
	}

	public function router_shortcode( array $atts = array() ): string {
		$atts   = shortcode_atts( array( 'family' => '' ), $atts, 'nescm_renewal_router' );
		$family = sanitize_key( (string) $atts['family'] );

		if ( $family ) {
			$url = $this->renewal_url( $family );
			return $url ? esc_url( $url ) : '';
		}

		$links = array();
		foreach ( nescm_get_families() as $key => $label ) {
			$links[] = sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $this->renewal_url( $key ) ), esc_html( $label ) );
		}

		return '<ul class="nescm-renewal-router">' . implode( '', $links ) . '</ul>';
	}

	/**
	 * One-time (yearly) renewal URL. With no family attribute, resolves the
	 * current member's family — so one generic renewal page works for everyone.
	 *
	 * @param array|string $atts Shortcode attributes (`family`).
	 */
	public function url_shortcode( $atts = array() ): string {
		$atts   = shortcode_atts( array( 'family' => '' ), is_array( $atts ) ? $atts : array(), 'nescm_renewal_url' );
		$family = sanitize_key( (string) $atts['family'] );

		if ( '' === $family ) {
			$family = $this->current_user_family();
		}

		return esc_url( $this->renewal_url( $family ) );
	}

	/**
	 * Checkout URL of the recurring (auto-renew) membership for a family. With no
	 * family attribute, resolves the current member's family. Empty when no
	 * published recurring product exists.
	 *
	 * @param array|string $atts Shortcode attributes (`family`).
	 */
	public function auto_renew_url_shortcode( $atts = array() ): string {
		$atts   = shortcode_atts( array( 'family' => '' ), is_array( $atts ) ? $atts : array(), 'nescm_auto_renew_url' );
		$family = sanitize_key( (string) $atts['family'] );

		if ( '' === $family ) {
			$family = $this->current_user_family();
		}

		if ( ! nescm_is_valid_family_key( $family ) ) {
			return '';
		}

		$membership_id = $this->adapter->find_recurring_membership( $family );

		if ( ! $membership_id || ! $this->adapter->is_published_membership( $membership_id ) ) {
			return '';
		}

		return esc_url( $this->adapter->checkout_url( $membership_id ) );
	}

	/**
	 * The logged-in user's membership family, from their latest NES transaction.
	 */
	private function current_user_family(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$txn = $this->adapter->get_latest_nes_transaction_for_user( get_current_user_id() );

		if ( ! $txn || empty( $txn->product_id ) ) {
			return '';
		}

		return (string) get_post_meta( (int) $txn->product_id, '_nescm_family_key', true );
	}

	private function friendly_error( string $message, string $admin_detail = '' ): void {
		status_header( 404 );

		$output = '<p>' . esc_html( $message ) . '</p>';
		if ( $admin_detail && current_user_can( nescm_admin_capability() ) ) {
			$output .= '<p><strong>' . esc_html__( 'Admin detail:', 'nes-calendar-memberships' ) . '</strong> ' . esc_html( $admin_detail ) . '</p>';
		}

		wp_die( wp_kses_post( $output ), esc_html__( 'Renewal unavailable', 'nes-calendar-memberships' ), array( 'response' => 404 ) );
	}
}
