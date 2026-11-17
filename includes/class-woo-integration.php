<?php
/**
 * WooCommerce integration.
 *
 * Two member-aware conveniences, active only when WooCommerce is installed:
 *  1. A "Member Dashboard" item in the WooCommerce account navigation, shown
 *     only to members (any NES membership history).
 *  2. Address sync: the MemberPress member address and the WooCommerce billing
 *     address are kept in step — updating either one updates the other.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Woo_Integration {

	/**
	 * MemberPress address user-meta keys mapped to WooCommerce billing keys.
	 */
	private const ADDRESS_MAP = array(
		'mepr-address-one'     => 'billing_address_1',
		'mepr-address-two'     => 'billing_address_2',
		'mepr-address-city'    => 'billing_city',
		'mepr-address-state'   => 'billing_state',
		'mepr-address-zip'     => 'billing_postcode',
		'mepr-address-country' => 'billing_country',
	);

	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;
	private NESCM_Member_Access $access;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings, NESCM_Member_Access $access ) {
		$this->adapter  = $adapter;
		$this->settings = $settings;
		$this->access   = $access;
	}

	public function hooks(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
		add_filter( 'woocommerce_get_endpoint_url', array( $this, 'endpoint_url' ), 10, 2 );
		add_action( 'mepr_account_nav', array( $this, 'memberpress_nav_orders' ) );

		add_action( 'updated_user_meta', array( $this, 'sync_address_meta' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'sync_address_meta' ), 10, 4 );
	}

	/**
	 * Whether the user has any WooCommerce order history.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	private function user_is_customer( int $user_id ): bool {
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 1,
				'return'      => 'ids',
			)
		);

		return ! empty( $orders );
	}

	/**
	 * Adds an "My Orders" item to the MemberPress account navigation for users
	 * with WooCommerce order history.
	 */
	public function memberpress_nav_orders(): void {
		if ( ! is_user_logged_in() || ! $this->user_is_customer( get_current_user_id() ) ) {
			return;
		}

		$url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : '';
		if ( ! $url ) {
			return;
		}

		printf(
			'<li class="mepr-nav-item nes-orders-nav-item"><a id="nes-account-orders" href="%1$s">%2$s</a></li>',
			esc_url( $url ),
			esc_html__( 'My Orders', 'nes-calendar-memberships' )
		);
	}

	/**
	 * Adds a members-only "Member Dashboard" item at the top of the Woo account menu.
	 *
	 * @param array $items Woo account menu items (endpoint => label).
	 * @return array
	 */
	public function account_menu_items( array $items ): array {
		if ( ! is_user_logged_in() || ! $this->access->is_member( get_current_user_id() ) ) {
			return $items;
		}

		$member_items = array();

		if ( $this->access->dashboard_url() ) {
			$member_items['nes-member-dashboard'] = __( 'Member Dashboard', 'nes-calendar-memberships' );
		}

		if ( $this->adapter->account_url() ) {
			$member_items['nes-member-profile'] = __( 'Membership Profile', 'nes-calendar-memberships' );
		}

		return $member_items + $items;
	}

	/**
	 * Resolves the custom menu item to the dashboard page URL.
	 *
	 * @param string $url      Endpoint URL computed by WooCommerce.
	 * @param string $endpoint Endpoint key.
	 * @return string
	 */
	public function endpoint_url( $url, $endpoint ) {
		if ( 'nes-member-dashboard' === $endpoint ) {
			$dashboard = $this->access->dashboard_url();
			if ( $dashboard ) {
				return $dashboard;
			}
		}

		if ( 'nes-member-profile' === $endpoint ) {
			$account = $this->adapter->account_url();
			if ( $account ) {
				return $account;
			}
		}

		return $url;
	}

	/**
	 * Mirrors address changes between MemberPress and WooCommerce billing meta.
	 *
	 * Hooked to updated_user_meta/added_user_meta, so every save path is covered:
	 * MemberPress signup and account edits, Woo checkout, the Woo edit-address
	 * endpoint, and admin profile edits. Writing the mirrored value does not
	 * re-fire for identical values, and a guard prevents recursion.
	 *
	 * @param int    $meta_id    Meta row ID (unused).
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Meta key that changed.
	 * @param mixed  $meta_value New value.
	 */
	public function sync_address_meta( $meta_id, $user_id, $meta_key, $meta_value ): void {
		static $syncing = false;

		if ( $syncing || ! is_string( $meta_key ) ) {
			return;
		}

		$map = self::ADDRESS_MAP;

		if ( isset( $map[ $meta_key ] ) ) {
			$target = $map[ $meta_key ];
		} else {
			$flipped = array_flip( $map );
			if ( ! isset( $flipped[ $meta_key ] ) ) {
				return;
			}
			$target = $flipped[ $meta_key ];
		}

		if ( 'yes' !== $this->settings->get( 'sync_billing_address', 'yes' ) ) {
			return;
		}

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		$syncing = true;
		update_user_meta( $user_id, $target, is_scalar( $meta_value ) ? (string) $meta_value : '' );
		$syncing = false;
	}
}
