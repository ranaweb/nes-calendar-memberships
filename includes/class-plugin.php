<?php
/**
 * Plugin composition root.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Plugin {
	private static ?NESCM_Plugin $instance = null;

	public NESCM_MemberPress_Adapter $adapter;
	public NESCM_Terminology $terminology;
	public NESCM_Settings $settings;
	public NESCM_Year_Calculator $calculator;
	public NESCM_Membership_Meta $membership_meta;
	public NESCM_Membership_Types $membership_types;
	public NESCM_Renewal_Router $renewal_router;
	public NESCM_Checkout_Messaging $checkout_messaging;
	public NESCM_Transaction_Sync $transaction_sync;
	public NESCM_Dashboard_Shortcodes $dashboard_shortcodes;
	public NESCM_Admin_Manual_Renewal $manual_renewal;
	public NESCM_Year_Generator $year_generator;
	public NESCM_Validator $validator;
	public NESCM_Member_Access $member_access;
	public NESCM_Woo_Integration $woo_integration;

	public static function instance(): NESCM_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate(): void {
		if ( ! get_option( NESCM_OPTION ) ) {
			add_option( NESCM_OPTION, NESCM_Settings::defaults() );
		}

		add_rewrite_rule( '^membership-renew/?$', 'index.php?nescm_renew=1', 'top' );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	private function __construct() {}

	public function init(): void {
		$this->adapter              = new NESCM_MemberPress_Adapter();
		$this->terminology          = new NESCM_Terminology();
		$this->settings             = new NESCM_Settings( $this->adapter );
		$this->calculator           = new NESCM_Year_Calculator( $this->settings );
		$this->membership_meta      = new NESCM_Membership_Meta( $this->adapter, $this->calculator );
		$this->membership_types     = new NESCM_Membership_Types( $this->adapter );
		$this->renewal_router       = new NESCM_Renewal_Router( $this->adapter, $this->settings, $this->calculator );
		$this->checkout_messaging   = new NESCM_Checkout_Messaging( $this->adapter, $this->settings, $this->calculator );
		$this->transaction_sync     = new NESCM_Transaction_Sync( $this->adapter );
		$this->dashboard_shortcodes = new NESCM_Dashboard_Shortcodes( $this->adapter, $this->settings, $this->calculator );
		$this->manual_renewal       = new NESCM_Admin_Manual_Renewal( $this->adapter, $this->settings, $this->calculator );
		$this->year_generator       = new NESCM_Year_Generator( $this->adapter, $this->settings, $this->calculator );
		$this->validator            = new NESCM_Validator( $this->adapter, $this->settings, $this->calculator );
		$this->member_access        = new NESCM_Member_Access( $this->adapter, $this->settings );
		$this->woo_integration      = new NESCM_Woo_Integration( $this->adapter, $this->settings, $this->member_access );

		$this->settings->register_tab( 'settings', __( 'Settings', 'nes-calendar-memberships' ), array( $this->settings, 'render_settings_tab' ) );
		$this->settings->register_tab( 'membership-types', __( 'Membership Types', 'nes-calendar-memberships' ), array( $this->membership_types, 'render_tab' ) );
		$this->settings->register_tab( 'manual-renewal', __( 'Offline Payments / Manual Renewals', 'nes-calendar-memberships' ), array( $this->manual_renewal, 'render_tab' ) );
		$this->settings->register_tab( 'generate-year', __( 'Generate Year', 'nes-calendar-memberships' ), array( $this->year_generator, 'render_tab' ) );
		$this->settings->register_tab( 'checkup', __( 'Checkup', 'nes-calendar-memberships' ), array( $this->validator, 'render_tab' ) );
		$this->settings->register_tab( 'how-to', __( 'How To', 'nes-calendar-memberships' ), array( $this->settings, 'render_help_tab' ) );

		add_action( 'admin_notices', 'nescm_render_admin_notices' );
		add_action( 'admin_notices', array( $this, 'memberpress_missing_notice' ) );

		$this->terminology->hooks();
		$this->settings->hooks();
		$this->membership_meta->hooks();
		$this->membership_types->hooks();
		$this->renewal_router->hooks();
		$this->checkout_messaging->hooks();
		$this->transaction_sync->hooks();
		$this->dashboard_shortcodes->hooks();
		$this->manual_renewal->hooks();
		$this->year_generator->hooks();
		$this->member_access->hooks();
		$this->woo_integration->hooks();
	}

	public function memberpress_missing_notice(): void {
		if ( ! current_user_can( nescm_admin_capability() ) || $this->adapter->is_active() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! is_admin() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'NES Calendar Memberships requires MemberPress to be active. Please activate MemberPress before using NES membership tools.', 'nes-calendar-memberships' )
		);
	}
}
