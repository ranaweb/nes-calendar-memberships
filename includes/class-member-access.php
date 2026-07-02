<?php
/**
 * Member access: dashboard gate and post-login routing.
 *
 * The member dashboard (an Elementor page, configured via the Dashboard Page ID
 * setting) is for members only — including expired members, who need it to renew.
 * Customer-only and other non-member users are routed to the WooCommerce account
 * page instead. After login, members land on the dashboard and everyone else on
 * the shop account page.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Member_Access {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings ) {
		$this->adapter  = $adapter;
		$this->settings = $settings;
	}

	public function hooks(): void {
		add_action( 'template_redirect', array( $this, 'guard_dashboard' ) );
		add_filter( 'login_redirect', array( $this, 'route_wp_login' ), 20, 3 );
		add_filter( 'mepr-process-login-redirect-url', array( $this, 'route_memberpress_login' ), 20, 2 );
	}

	/**
	 * The configured member dashboard page ID (0 = feature disabled).
	 */
	public function dashboard_page_id(): int {
		return absint( $this->settings->get( 'default_dashboard_page_id', 0 ) );
	}

	public function dashboard_url(): string {
		$page_id = $this->dashboard_page_id();

		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * A member is any user with NES membership history — active, pending, or expired.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public function is_member( int $user_id ): bool {
		return $this->adapter->user_has_nes_transactions( $user_id );
	}

	/**
	 * Members-only gate on the dashboard page.
	 */
	public function guard_dashboard(): void {
		$page_id = $this->dashboard_page_id();

		if ( $page_id <= 0 || ! is_page( $page_id ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( (string) get_permalink( $page_id ) ), $this->adapter->login_url() ) );
			exit;
		}

		// Site staff and members (including expired members) may view the dashboard.
		if ( current_user_can( 'edit_posts' ) || $this->is_member( get_current_user_id() ) ) {
			return;
		}

		wp_safe_redirect( $this->shop_account_url() );
		exit;
	}

	/**
	 * WordPress core login redirect: member → dashboard, everyone else → shop account.
	 *
	 * @param string           $redirect_to           Computed redirect destination.
	 * @param string           $requested_redirect_to Redirect destination explicitly requested.
	 * @param WP_User|WP_Error $user                  Logged-in user, or error on failure.
	 * @return string
	 */
	public function route_wp_login( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! $user instanceof WP_User ) {
			return $redirect_to;
		}

		// An explicitly requested destination wins — but the login form's default
		// hidden redirect (admin_url) is not an explicit request.
		if ( is_string( $requested_redirect_to ) && '' !== $requested_redirect_to && admin_url() !== $requested_redirect_to ) {
			return $redirect_to;
		}

		return $this->route_for_user( $user, (string) $redirect_to );
	}

	/**
	 * MemberPress login redirect: same routing as core.
	 *
	 * @param mixed $url  Destination computed by MemberPress.
	 * @param mixed $user Logged-in user.
	 * @return mixed
	 */
	public function route_memberpress_login( $url, $user ) {
		if ( ! $user instanceof WP_User ) {
			return $url;
		}

		// Only a redirect requested in the URL is explicit — the MemberPress login
		// form always POSTs a hidden redirect_to field, which must not disable routing.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing hint; no state change.
		if ( ! empty( $_GET['redirect_to'] ) ) {
			return $url;
		}

		return $this->route_for_user( $user, (string) $url );
	}

	private function route_for_user( WP_User $user, string $fallback ): string {
		/**
		 * Filters whether NES smart login routing is applied.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'nescm_login_routing_enabled', true ) ) {
			return $fallback;
		}

		// Staff keep the WordPress default (usually wp-admin).
		if ( $user->has_cap( 'edit_posts' ) ) {
			return $fallback;
		}

		if ( $this->is_member( (int) $user->ID ) ) {
			$dashboard = $this->dashboard_url();
			if ( $dashboard ) {
				return $dashboard;
			}
		}

		$shop = $this->shop_account_url();

		return $shop ? $shop : $fallback;
	}

	private function shop_account_url(): string {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = (string) wc_get_page_permalink( 'myaccount' );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}
}
