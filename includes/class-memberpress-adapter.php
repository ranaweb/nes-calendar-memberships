<?php
/**
 * MemberPress-specific integration boundary.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_MemberPress_Adapter {
	public function is_active(): bool {
		return defined( 'MEPR_VERSION' ) && class_exists( 'MeprProduct' ) && class_exists( 'MeprTransaction' );
	}

	public function version(): string {
		return defined( 'MEPR_VERSION' ) ? (string) MEPR_VERSION : '';
	}

	public function edition(): string {
		return defined( 'MEPR_EDITION' ) ? (string) MEPR_EDITION : '';
	}

	public function is_menu_available(): bool {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return false;
		}

		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && 'memberpress' === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	public function get_membership_post_type(): string {
		if ( class_exists( 'MeprProduct' ) && property_exists( 'MeprProduct', 'cpt' ) ) {
			return (string) MeprProduct::$cpt;
		}

		return 'memberpressproduct';
	}

	public function is_membership_post( int $post_id ): bool {
		return $post_id > 0 && get_post_type( $post_id ) === $this->get_membership_post_type();
	}

	public function is_published_membership( int $post_id ): bool {
		return $this->is_membership_post( $post_id ) && 'publish' === get_post_status( $post_id );
	}

	public function checkout_url( int $membership_id ): string {
		if ( $membership_id <= 0 ) {
			return '';
		}

		if ( class_exists( 'MeprProduct' ) ) {
			try {
				$product = new MeprProduct( $membership_id );
				if ( isset( $product->ID ) && (int) $product->ID === $membership_id && method_exists( $product, 'url' ) ) {
					return (string) $product->url();
				}
			} catch ( Throwable $e ) {
				$this->log( 'Failed to get MemberPress product URL: ' . $e->getMessage() );
			}
		}

		return (string) get_permalink( $membership_id );
	}

	public function product_price( int $membership_id ): float {
		if ( class_exists( 'MeprProduct' ) ) {
			try {
				$product = new MeprProduct( $membership_id );
				return isset( $product->price ) ? (float) $product->price : 0.0;
			} catch ( Throwable $e ) {
				$this->log( 'Failed to read product price: ' . $e->getMessage() );
			}
		}

		return (float) get_post_meta( $membership_id, '_mepr_product_price', true );
	}

	public function is_recurring_product( int $membership_id ): bool {
		if ( class_exists( 'MeprProduct' ) ) {
			try {
				$product = new MeprProduct( $membership_id );
				return isset( $product->period_type ) && in_array( (string) $product->period_type, array( 'weeks', 'months', 'years' ), true );
			} catch ( Throwable $e ) {
				$this->log( 'Failed to read product recurrence: ' . $e->getMessage() );
			}
		}

		return in_array( (string) get_post_meta( $membership_id, '_mepr_product_period_type', true ), array( 'weeks', 'months', 'years' ), true );
	}

	public function product_fixed_expiration( int $membership_id ): string {
		if ( class_exists( 'MeprProduct' ) ) {
			try {
				$product = new MeprProduct( $membership_id );
				return isset( $product->expire_fixed ) ? (string) $product->expire_fixed : '';
			} catch ( Throwable $e ) {
				$this->log( 'Failed to read fixed expiration: ' . $e->getMessage() );
			}
		}

		return (string) get_post_meta( $membership_id, '_mepr_expire_fixed', true );
	}

	public function set_product_fixed_expiration( int $membership_id, string $date ): bool {
		if ( ! $this->is_membership_post( $membership_id ) || ! nescm_parse_date( $date ) ) {
			return false;
		}

		if ( class_exists( 'MeprProduct' ) ) {
			try {
				$product                = new MeprProduct( $membership_id );
				$product->period_type   = 'lifetime';
				$product->expire_type   = 'fixed';
				$product->expire_fixed  = $date;
				$product->allow_renewal = false;
				$product->store_meta();
				return true;
			} catch ( Throwable $e ) {
				$this->log( 'Failed to set MemberPress fixed expiration: ' . $e->getMessage() );
			}
		}

		update_post_meta( $membership_id, '_mepr_product_period_type', 'lifetime' );
		update_post_meta( $membership_id, '_mepr_expire_type', 'fixed' );
		update_post_meta( $membership_id, '_mepr_expire_fixed', $date );
		update_post_meta( $membership_id, '_mepr_allow_renewal', false );
		return true;
	}

	public function find_membership( string $family_key, int $year, string $method_type = 'manual' ): ?int {
		$query = new WP_Query(
			array(
				'post_type'              => $this->get_membership_post_type(),
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_nescm_enabled',
						'value' => 'yes',
					),
					array(
						'key'   => '_nescm_family_key',
						'value' => $family_key,
					),
					array(
						'key'   => '_nescm_membership_year',
						'value' => (string) $year,
					),
					array(
						'key'   => '_nescm_renewal_method_type',
						'value' => $method_type,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : null;
	}

	public function query_nes_memberships( array $args = array() ): array {
		$defaults = array(
			'post_type'      => $this->get_membership_post_type(),
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => '_nescm_enabled',
					'value' => 'yes',
				),
			),
		);

		$query = new WP_Query( wp_parse_args( $args, $defaults ) );
		return $query->posts;
	}

	public function get_latest_nes_transaction_for_user( int $user_id ): ?object {
		global $wpdb;

		if ( ! $this->is_active() || $user_id <= 0 || empty( $wpdb->mepr_transactions ) ) {
			return null;
		}

		$product_ids = get_posts(
			array(
				'post_type'      => $this->get_membership_post_type(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_nescm_enabled',
				'meta_value'     => 'yes',
			)
		);

		if ( empty( $product_ids ) ) {
			return null;
		}

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT * FROM {$wpdb->mepr_transactions}
			WHERE user_id = %d
			AND product_id IN ($placeholders)
			AND txn_type = %s
			AND status IN (%s, %s, %s)
			ORDER BY
				CASE WHEN status = %s AND (expires_at > %s OR expires_at IS NULL OR expires_at = %s) THEN 0 ELSE 1 END,
				created_at DESC,
				id DESC
			LIMIT 1",
			array_merge(
				array(
					$user_id,
				),
				array_map( 'absint', $product_ids ),
				array(
					'payment',
					'complete',
					'confirmed',
					'pending',
					'complete',
					current_time( 'mysql', true ),
					'0000-00-00 00:00:00',
				)
			)
		);

		$row = $wpdb->get_row( $sql );
		return $row ?: null;
	}

	public function create_manual_transaction( int $user_id, int $membership_id, float $amount, string $status, string $created_at, string $expires_at, array $meta = array() ): int|WP_Error {
		if ( ! $this->is_active() ) {
			return new WP_Error( 'memberpress_inactive', __( 'MemberPress is not active.', 'nes-calendar-memberships' ) );
		}

		if ( ! class_exists( 'MeprTransaction' ) || ! class_exists( 'MeprEvent' ) ) {
			return new WP_Error( 'memberpress_transaction_unavailable', __( 'MemberPress transaction classes are unavailable.', 'nes-calendar-memberships' ) );
		}

		try {
			$txn             = new MeprTransaction();
			$txn->trans_num  = 'nescm_' . wp_generate_password( 12, false, false );
			$txn->user_id    = $user_id;
			$txn->product_id = $membership_id;
			$txn->amount     = $amount;
			$txn->tax_amount = 0.0;
			$txn->tax_rate   = 0.0;
			$txn->total      = $amount;
			$txn->status     = 'complete' === $status ? MeprTransaction::$complete_str : MeprTransaction::$pending_str;
			$txn->gateway    = MeprTransaction::$manual_gateway_str;
			$txn->txn_type   = MeprTransaction::$payment_str;
			$txn->created_at = $this->mysql_datetime( $created_at . ' 12:00:00' );
			$txn->expires_at = $this->mysql_datetime( $expires_at . ' 23:59:59' );
			$txn->store();

			foreach ( $meta as $key => $value ) {
				$txn->update_meta( $key, $value );
			}

			if ( MeprTransaction::$complete_str === $txn->status ) {
				MeprEvent::record( 'transaction-completed', $txn );
				MeprEvent::record( 'non-recurring-transaction-completed', $txn );
			}

			do_action( 'mepr_signup', $txn );

			return (int) $txn->id;
		} catch ( Throwable $e ) {
			$this->log( 'Manual transaction creation failed: ' . $e->getMessage() );
			return new WP_Error( 'manual_transaction_failed', $e->getMessage() );
		}
	}

	public function has_duplicate_transaction( int $user_id, string $family_key, int $year ): bool {
		global $wpdb;

		if ( ! $this->is_active() || empty( $wpdb->mepr_transactions ) || $user_id <= 0 ) {
			return false;
		}

		$product_ids = get_posts(
			array(
				'post_type'      => $this->get_membership_post_type(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_nescm_enabled',
						'value' => 'yes',
					),
					array(
						'key'   => '_nescm_family_key',
						'value' => $family_key,
					),
					array(
						'key'   => '_nescm_membership_year',
						'value' => (string) $year,
					),
				),
			)
		);

		if ( empty( $product_ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->mepr_transactions}
			WHERE user_id = %d
			AND product_id IN ($placeholders)
			AND status = %s
			AND txn_type = %s",
			array_merge( array( $user_id ), array_map( 'absint', $product_ids ), array( 'complete', 'payment' ) )
		);

		return (int) $wpdb->get_var( $sql ) > 0;
	}

	public function mysql_datetime( string $datetime ): string {
		$timestamp = strtotime( $datetime );
		if ( ! $timestamp ) {
			$timestamp = time();
		}

		if ( class_exists( 'MeprUtils' ) && method_exists( 'MeprUtils', 'ts_to_mysql_date' ) ) {
			return (string) MeprUtils::ts_to_mysql_date( $timestamp, 'Y-m-d H:i:s' );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	public function log( string $message ): void {
		$options = get_option( NESCM_OPTION, array() );

		if ( is_array( $options ) && isset( $options['debug_logging'] ) && 'yes' === $options['debug_logging'] ) {
			error_log( '[NESCM] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
