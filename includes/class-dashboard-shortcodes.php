<?php
/**
 * Dashboard shortcodes.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Dashboard_Shortcodes {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings ) {
		$this->adapter  = $adapter;
		$this->settings = $settings;
	}

	public function hooks(): void {
		add_shortcode( 'nescm_membership_level', array( $this, 'membership_level' ) );
		add_shortcode( 'nescm_membership_status', array( $this, 'membership_status' ) );
		add_shortcode( 'nescm_membership_expiration', array( $this, 'membership_expiration' ) );
		add_shortcode( 'nescm_renewal_method', array( $this, 'renewal_method' ) );
		add_shortcode( 'nescm_auto_renew_cta', array( $this, 'auto_renew_cta' ) );
		add_shortcode( 'nescm_membership_summary', array( $this, 'membership_summary' ) );
	}

	public function membership_level(): string {
		return esc_html( $this->summary()['label'] );
	}

	public function membership_status(): string {
		return esc_html( $this->summary()['status_label'] );
	}

	public function membership_expiration(): string {
		return esc_html( $this->summary()['expiration_label'] );
	}

	public function renewal_method(): string {
		return esc_html( $this->summary()['renewal_method_label'] );
	}

	public function auto_renew_cta(): string {
		$summary = $this->summary();

		if ( 'yes' !== $this->settings->get( 'enable_auto_renew_cta' ) ) {
			return '';
		}

		$configured_url = (string) $this->settings->get( 'auto_renew_cta_url', '' );
		$url            = $configured_url;
		$button         = __( 'Add Card for Automatic Renewal', 'nes-calendar-memberships' );
		$text           = __( 'Add a card to renew automatically each year and avoid missed renewals.', 'nes-calendar-memberships' );

		if ( 'expired' === $summary['status'] && $summary['family_key'] ) {
			$url    = add_query_arg( 'family', rawurlencode( $summary['family_key'] ), home_url( '/membership-renew/' ) );
			$button = __( 'Renew Membership', 'nes-calendar-memberships' );
			$text   = __( 'Renew your membership to restore member benefits.', 'nes-calendar-memberships' );
		} elseif ( 'auto_renew' === $summary['renewal_method'] ) {
			$button = __( 'Manage Payment Method', 'nes-calendar-memberships' );
			$text   = __( 'Your membership is set to renew automatically.', 'nes-calendar-memberships' );
		}

		if ( ! $url ) {
			return '';
		}

		return nescm_render_template(
			'dashboard-auto-renew-cta.php',
			array(
				'url'    => $url,
				'button' => $button,
				'text'   => $text,
			)
		);
	}

	public function membership_summary(): string {
		return nescm_render_template( 'dashboard-status.php', array( 'summary' => $this->summary() ) );
	}

	private function summary(): array {
		$empty = array(
			'label'                => '',
			'status'               => 'none',
			'status_label'         => __( 'No active membership found', 'nes-calendar-memberships' ),
			'expiration_label'     => '',
			'renewal_method'       => 'manual',
			'renewal_method_label' => '',
			'family_key'           => '',
			'year'                 => '',
		);

		if ( ! is_user_logged_in() || ! $this->adapter->is_active() ) {
			return $empty;
		}

		$txn = $this->adapter->get_latest_nes_transaction_for_user( get_current_user_id() );
		if ( ! $txn ) {
			return $empty;
		}

		$product_id = (int) $txn->product_id;
		$family     = (string) get_post_meta( $product_id, '_nescm_family_key', true );
		$label      = (string) get_post_meta( $product_id, '_nescm_public_label', true );
		$year       = (string) get_post_meta( $product_id, '_nescm_membership_year', true );
		$method     = (string) get_post_meta( $product_id, '_nescm_renewal_method_type', true );
		$method     = $method ?: 'manual';
		$expires_at = isset( $txn->expires_at ) ? (string) $txn->expires_at : '';

		if ( ! $label ) {
			$label = nescm_get_families()[ $family ] ?? get_the_title( $product_id );
		}

		$is_expired = $expires_at && '0000-00-00 00:00:00' !== $expires_at && strtotime( $expires_at ) < current_time( 'timestamp', true );
		$status     = 'pending' === $txn->status ? 'pending' : ( $is_expired ? 'expired' : 'active' );

		$active_recurring = $this->is_active_recurring_transaction( $txn );
		if ( $active_recurring ) {
			$method = 'auto_renew';
		}

		$status_labels = array(
			'active'  => __( 'Active', 'nes-calendar-memberships' ),
			'expired' => __( 'Expired', 'nes-calendar-memberships' ),
			'pending' => __( 'Pending Payment', 'nes-calendar-memberships' ),
		);

		$method_labels = array(
			'manual'     => __( 'Manual Renewal', 'nes-calendar-memberships' ),
			'auto_renew' => __( 'Automatic Renewal', 'nes-calendar-memberships' ),
			'legacy'     => __( 'Lifetime / Legacy', 'nes-calendar-memberships' ),
			'lifetime'   => __( 'Lifetime / Legacy', 'nes-calendar-memberships' ),
		);

		$expiration_label = '';
		if ( 'pending' === $status ) {
			$expiration_label = __( 'Your renewal is pending until NES receives and records your cheque/Zelle payment.', 'nes-calendar-memberships' );
		} elseif ( $expires_at && '0000-00-00 00:00:00' !== $expires_at ) {
			$date = wp_date( 'F j, Y', strtotime( $expires_at ), wp_timezone() );
			if ( 'auto_renew' === $method ) {
				$expiration_label = sprintf( __( 'Membership active through %s', 'nes-calendar-memberships' ), $date );
			} else {
				$expiration_label = sprintf( __( 'Expires %s', 'nes-calendar-memberships' ), $date );
			}
		}

		return array(
			'label'                => $label,
			'status'               => $status,
			'status_label'         => $status_labels[ $status ],
			'expiration_label'     => $expiration_label,
			'renewal_method'       => $method,
			'renewal_method_label' => $method_labels[ $method ] ?? $method_labels['manual'],
			'family_key'           => $family,
			'year'                 => $year,
		);
	}

	private function is_active_recurring_transaction( object $txn ): bool {
		if ( empty( $txn->subscription_id ) || ! class_exists( 'MeprSubscription' ) ) {
			return false;
		}

		try {
			$sub = new MeprSubscription( (int) $txn->subscription_id );
			return isset( $sub->status ) && in_array( (string) $sub->status, array( 'active', 'enabled' ), true );
		} catch ( Throwable $e ) {
			$this->adapter->log( 'Recurring status check failed: ' . $e->getMessage() );
			return false;
		}
	}
}
