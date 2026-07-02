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
	private NESCM_Year_Calculator $calculator;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings, NESCM_Year_Calculator $calculator ) {
		$this->adapter    = $adapter;
		$this->settings   = $settings;
		$this->calculator = $calculator;
	}

	public function hooks(): void {
		add_shortcode( 'nescm_membership_level', array( $this, 'membership_level' ) );
		add_shortcode( 'nescm_membership_status', array( $this, 'membership_status' ) );
		add_shortcode( 'nescm_membership_expiration', array( $this, 'membership_expiration' ) );
		add_shortcode( 'nescm_renewal_method', array( $this, 'renewal_method' ) );
		add_shortcode( 'nescm_auto_renew_cta', array( $this, 'auto_renew_cta' ) );
		add_shortcode( 'nescm_renew_cta', array( $this, 'renew_cta' ) );
		add_shortcode( 'nescm_membership_summary', array( $this, 'membership_summary' ) );
	}

	public function membership_level(): string {
		return esc_html( $this->summary()['label'] );
	}

	public function membership_status(): string {
		return esc_html( $this->summary()['status_label'] );
	}

	/**
	 * Renewal/expiration output.
	 *
	 * [nescm_membership_expiration]                      → full sentence ("Renews June 25, 2027")
	 * [nescm_membership_expiration part="label"]         → just "Renews" / "Expires" / "Expired on"
	 * [nescm_membership_expiration part="date" fallback="—"] → just the date, with an empty-state fallback
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function membership_expiration( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'part'     => '',
				'fallback' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'nescm_membership_expiration'
		);

		$part     = sanitize_key( (string) $atts['part'] );
		$fallback = sanitize_text_field( (string) $atts['fallback'] );
		$summary  = $this->summary();

		if ( 'label' === $part ) {
			return esc_html( $summary['renewal_label'] ? $summary['renewal_label'] : $fallback );
		}

		if ( 'date' === $part ) {
			return esc_html( $summary['renewal_date'] ? $summary['renewal_date'] : $fallback );
		}

		return esc_html( $summary['expiration_label'] ? $summary['expiration_label'] : $fallback );
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
		$button         = __( 'Set Up Automatic Renewal', 'nes-calendar-memberships' );
		$text           = __( 'Choose automatic renewal so your membership continues each year without interruption.', 'nes-calendar-memberships' );

		if ( 'expired' === $summary['status'] && $summary['family_key'] ) {
			$url    = add_query_arg( 'family', rawurlencode( $summary['family_key'] ), home_url( '/membership-renew/' ) );
			$button = __( 'Renew Membership', 'nes-calendar-memberships' );
			$text   = __( 'Renew your membership to restore member benefits.', 'nes-calendar-memberships' );
		} elseif ( 'auto_renew' === $summary['renewal_method'] ) {
			$button = __( 'Manage Automatic Renewal', 'nes-calendar-memberships' );
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

	/**
	 * Early-renewal call to action for non-recurring (yearly) members.
	 *
	 * Once the next membership year is available to purchase (after the annual cutoff),
	 * or the current membership has expired, this shows a "Renew now for <year>" button
	 * that routes the member to the correct year's checkout. Recurring members renew
	 * automatically, so they never see this.
	 *
	 * @param array|string $atts Shortcode attributes (`href` overrides the destination).
	 */
	public function renew_cta( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'href' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'nescm_renew_cta'
		);

		$summary = $this->summary();

		if ( 'auto_renew' === $summary['renewal_method'] || 'pending' === $summary['status'] || '' === $summary['family_key'] ) {
			return '';
		}

		if ( ! $this->adapter->is_active() ) {
			return '';
		}

		$member_year = (int) $summary['year'];
		$target_year = $this->calculator->get_membership_year_for_date( $this->calculator->today() );

		// Only prompt once a newer year is available to buy, or the membership has expired.
		if ( $target_year <= $member_year && 'expired' !== $summary['status'] ) {
			return '';
		}

		$target_id = $this->adapter->find_membership( $summary['family_key'], $target_year, 'manual' );
		if ( ! $target_id || ! $this->adapter->is_published_membership( $target_id ) ) {
			return '';
		}

		// A custom href (e.g. the renewal chooser page) wins over the direct year checkout route.
		$url           = $atts['href'] ? esc_url_raw( (string) $atts['href'] ) : add_query_arg( 'family', rawurlencode( $summary['family_key'] ), home_url( '/membership-renew/' ) );
		$valid_through = wp_date( 'F j, Y', strtotime( $target_year . '-12-31' ), wp_timezone() );

		/* translators: %d: the membership year now available to renew into. */
		$button = sprintf( __( 'Renew now for %d', 'nes-calendar-memberships' ), $target_year );
		/* translators: %s: the date the renewed membership is valid through. */
		$text = sprintf( __( 'Your membership for the new year is available. Renew now to stay active through %s.', 'nes-calendar-memberships' ), $valid_through );

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
			'status_label'         => __( 'No current membership on file', 'nes-calendar-memberships' ),
			'expiration_label'     => '',
			'renewal_label'        => '',
			'renewal_date'         => '',
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

		$is_expired = $expires_at && '0000-00-00 00:00:00' !== $expires_at && strtotime( $expires_at ) < time();
		$status     = 'pending' === $txn->status ? 'pending' : ( $is_expired ? 'expired' : 'active' );

		$active_recurring = $this->is_active_recurring_transaction( $txn );
		if ( $active_recurring ) {
			$method = 'auto_renew';
		}

		$status_labels = array(
			'active'  => __( 'Active', 'nes-calendar-memberships' ),
			'expired' => __( 'Expired', 'nes-calendar-memberships' ),
			'pending' => __( 'Renewal Pending', 'nes-calendar-memberships' ),
		);

		$method_labels = array(
			'manual'     => __( 'Manual Renewal', 'nes-calendar-memberships' ),
			'auto_renew' => __( 'Automatic Renewal', 'nes-calendar-memberships' ),
			'legacy'     => __( 'Lifetime / Legacy', 'nes-calendar-memberships' ),
			'lifetime'   => __( 'Lifetime / Legacy', 'nes-calendar-memberships' ),
		);

		$expiration_label = '';
		$renewal_label    = '';
		$renewal_date     = '';

		if ( 'pending' === $status ) {
			$expiration_label = __( 'Your membership renewal will be confirmed once NES receives and records your check or Zelle payment.', 'nes-calendar-memberships' );
		} elseif ( $expires_at && '0000-00-00 00:00:00' !== $expires_at ) {
			// For auto-renew members the meaningful date is the subscription's next
			// billing boundary; for yearly members it is the fixed expiration.
			$boundary = $expires_at;
			if ( 'auto_renew' === $method && 'expired' !== $status ) {
				$next_billing = $this->recurring_next_billing( $txn );
				if ( $next_billing ) {
					$boundary = $next_billing;
				}
			}

			$renewal_date = (string) wp_date( 'F j, Y', strtotime( $boundary ), wp_timezone() );

			if ( 'expired' === $status ) {
				$renewal_label = __( 'Expired on', 'nes-calendar-memberships' );
				/* translators: %s: formatted date the membership expired. */
				$expiration_label = sprintf( __( 'Expired on %s', 'nes-calendar-memberships' ), $renewal_date );
			} elseif ( 'auto_renew' === $method ) {
				$renewal_label = __( 'Renews', 'nes-calendar-memberships' );
				/* translators: %s: formatted date the membership renews. */
				$expiration_label = sprintf( __( 'Renews %s', 'nes-calendar-memberships' ), $renewal_date );
			} else {
				$renewal_label = __( 'Expires', 'nes-calendar-memberships' );
				/* translators: %s: formatted date the membership expires. */
				$expiration_label = sprintf( __( 'Expires %s', 'nes-calendar-memberships' ), $renewal_date );
			}
		}

		return array(
			'label'                => $label,
			'status'               => $status,
			'status_label'         => $status_labels[ $status ],
			'expiration_label'     => $expiration_label,
			'renewal_label'        => $renewal_label,
			'renewal_date'         => $renewal_date,
			'renewal_method'       => $method,
			'renewal_method_label' => $method_labels[ $method ] ?? $method_labels['manual'],
			'family_key'           => $family,
			'year'                 => $year,
		);
	}

	/**
	 * The next billing boundary for an active recurring membership, as a MySQL
	 * datetime string. Falls back to '' (caller then uses the txn expires_at).
	 *
	 * @param object $txn Latest NES transaction row for the user.
	 * @return string
	 */
	private function recurring_next_billing( object $txn ): string {
		if ( empty( $txn->subscription_id ) || ! class_exists( 'MeprSubscription' ) ) {
			return '';
		}

		try {
			$sub = new MeprSubscription( (int) $txn->subscription_id );

			if ( isset( $sub->status ) && in_array( (string) $sub->status, array( 'active', 'enabled' ), true ) && method_exists( $sub, 'latest_txn' ) ) {
				$latest = $sub->latest_txn();
				if ( $latest && ! empty( $latest->expires_at ) && '0000-00-00 00:00:00' !== (string) $latest->expires_at ) {
					return (string) $latest->expires_at;
				}
			}
		} catch ( Throwable $e ) {
			$this->adapter->log( 'Next billing lookup failed: ' . $e->getMessage() );
		}

		return '';
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
