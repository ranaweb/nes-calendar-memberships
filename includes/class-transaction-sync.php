<?php
/**
 * Transaction metadata sync.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Transaction_Sync {
	private NESCM_MemberPress_Adapter $adapter;
	private bool $syncing = false;

	public function __construct( NESCM_MemberPress_Adapter $adapter ) {
		$this->adapter = $adapter;
	}

	public function hooks(): void {
		add_action( 'mepr_txn_store', array( $this, 'sync_transaction' ), 20, 2 );
	}

	public function sync_transaction( object $txn, object $old_txn = null ): void {
		if ( $this->syncing || ! $this->adapter->is_active() || ! isset( $txn->id, $txn->product_id ) ) {
			return;
		}

		$product_id = (int) $txn->product_id;
		if ( 'yes' !== get_post_meta( $product_id, '_nescm_enabled', true ) ) {
			return;
		}

		$family        = (string) get_post_meta( $product_id, '_nescm_family_key', true );
		$label         = (string) get_post_meta( $product_id, '_nescm_public_label', true );
		$year          = (string) get_post_meta( $product_id, '_nescm_membership_year', true );
		$valid_through = (string) get_post_meta( $product_id, '_nescm_valid_through', true );
		$method        = (string) get_post_meta( $product_id, '_nescm_renewal_method_type', true );

		try {
			if ( method_exists( $txn, 'update_meta' ) ) {
				$txn->update_meta( '_nescm_family_key', $family );
				$txn->update_meta( '_nescm_public_label', $label );
				$txn->update_meta( '_nescm_membership_year', $year );
				$txn->update_meta( '_nescm_valid_through', $valid_through );
				$txn->update_meta( '_nescm_renewal_method_type', $method ?: 'manual' );
			}

			if ( $valid_through && 'manual' === ( $method ?: 'manual' ) && ! $this->adapter->is_recurring_product( $product_id ) ) {
				$current_expiry_date = ! empty( $txn->expires_at ) ? gmdate( 'Y-m-d', strtotime( (string) $txn->expires_at ) ) : '';

				if ( $current_expiry_date !== $valid_through ) {
					$this->syncing      = true;
					$txn->expires_at    = $valid_through . ' 23:59:59';
					$txn->store();
					$this->syncing = false;
				}
			}
		} catch ( Throwable $e ) {
			$this->syncing = false;
			$this->adapter->log( 'Transaction sync failed: ' . $e->getMessage() );
		}
	}
}
