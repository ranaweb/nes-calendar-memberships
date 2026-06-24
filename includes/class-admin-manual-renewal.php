<?php
/**
 * Manual cheque/Zelle renewal helper.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Admin_Manual_Renewal {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;
	private NESCM_Year_Calculator $calculator;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings, NESCM_Year_Calculator $calculator ) {
		$this->adapter    = $adapter;
		$this->settings   = $settings;
		$this->calculator = $calculator;
	}

	public function hooks(): void {
		add_action( 'admin_post_nescm_create_manual_renewal', array( $this, 'handle' ) );
	}

	public function render_tab(): void {
		$pending_key = 'nescm_manual_renewal_pending_' . get_current_user_id();
		$pending     = get_transient( $pending_key );
		if ( is_array( $pending ) ) {
			delete_transient( $pending_key );
		}

		echo nescm_render_template(
			'admin-manual-renewal.php',
			array(
				'families' => nescm_get_families(),
				'pending'  => is_array( $pending ) ? $pending : array(),
			)
		);
	}

	public function handle(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to create manual renewals.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_create_manual_renewal' );

		$data = $this->sanitize_submission( isset( $_POST['nescm_manual'] ) && is_array( $_POST['nescm_manual'] ) ? wp_unslash( $_POST['nescm_manual'] ) : array() );

		if ( is_wp_error( $data ) ) {
			nescm_add_admin_notice( $data->get_error_message(), 'error' );
			$this->redirect();
		}

		$user = $this->find_user( $data['user'] );
		if ( ! $user ) {
			nescm_add_admin_notice( __( 'Could not find a WordPress user for that login, email, or ID.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		$payment_date = nescm_parse_date( $data['payment_date'] );
		if ( ! $payment_date ) {
			nescm_add_admin_notice( __( 'Payment date must be a valid YYYY-MM-DD date.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		$year          = $this->calculator->get_membership_year_for_date( $payment_date );
		$valid_through = $this->calculator->get_valid_through_for_year( $year )->format( 'Y-m-d' );
		$target_id     = $this->adapter->find_membership( $data['family'], $year, 'manual' );

		if ( ! $target_id || ! $this->adapter->is_published_membership( $target_id ) ) {
			nescm_add_admin_notice(
				sprintf(
					/* translators: 1: family key, 2: year */
					__( 'Missing published manual membership for family %1$s and year %2$d.', 'nes-calendar-memberships' ),
					$data['family'],
					$year
				),
				'error'
			);
			$this->redirect();
		}

		$duplicate = $this->adapter->has_duplicate_transaction( (int) $user->ID, $data['family'], $year );
		if ( $duplicate && empty( $data['confirm_duplicate'] ) ) {
			$data['calculated_year']  = $year;
			$data['valid_through']    = $valid_through;
			$data['target_id']        = $target_id;
			$data['target_title']     = get_the_title( $target_id );
			$data['resolved_user_id'] = (int) $user->ID;
			set_transient( 'nescm_manual_renewal_pending_' . get_current_user_id(), $data, 5 * MINUTE_IN_SECONDS );
			nescm_add_admin_notice( __( 'This member already appears to have a completed transaction for that family and year. Review the duplicate warning before continuing.', 'nes-calendar-memberships' ), 'warning' );
			$this->redirect();
		}

		$label = (string) get_post_meta( $target_id, '_nescm_public_label', true );
		$meta  = array(
			'_nescm_family_key'          => $data['family'],
			'_nescm_public_label'        => $label,
			'_nescm_membership_year'     => (string) $year,
			'_nescm_valid_through'       => $valid_through,
			'_nescm_renewal_method_type' => 'manual',
			'_nescm_payment_method'      => $data['payment_method'],
			'_nescm_payment_reference'   => $data['reference'],
			'_nescm_admin_note'          => $data['note'],
		);

		$result = $this->adapter->create_manual_transaction(
			(int) $user->ID,
			$target_id,
			(float) $data['amount'],
			$data['status'],
			$data['payment_date'],
			$valid_through,
			$meta
		);

		if ( is_wp_error( $result ) ) {
			nescm_add_admin_notice( $result->get_error_message(), 'error' );
			$this->redirect();
		}

		nescm_add_admin_notice(
			sprintf(
				/* translators: 1: transaction ID, 2: year, 3: membership title */
				__( 'Created manual renewal transaction #%1$d for %2$d %3$s.', 'nes-calendar-memberships' ),
				(int) $result,
				$year,
				get_the_title( $target_id )
			),
			'success'
		);
		$this->redirect();
	}

	private function sanitize_submission( array $raw ): array|WP_Error {
		$data = array(
			'user'              => sanitize_text_field( (string) ( $raw['user'] ?? '' ) ),
			'family'            => sanitize_key( (string) ( $raw['family'] ?? '' ) ),
			'payment_date'      => sanitize_text_field( (string) ( $raw['payment_date'] ?? '' ) ),
			'payment_method'    => sanitize_key( (string) ( $raw['payment_method'] ?? '' ) ),
			'amount'            => (float) ( $raw['amount'] ?? 0 ),
			'reference'         => sanitize_text_field( (string) ( $raw['reference'] ?? '' ) ),
			'note'              => sanitize_textarea_field( (string) ( $raw['note'] ?? '' ) ),
			'status'            => sanitize_key( (string) ( $raw['status'] ?? 'pending' ) ),
			'confirm_duplicate' => ! empty( $raw['confirm_duplicate'] ) ? 'yes' : '',
		);

		if ( '' === $data['user'] ) {
			return new WP_Error( 'missing_user', __( 'User is required.', 'nes-calendar-memberships' ) );
		}

		if ( ! nescm_is_valid_family_key( $data['family'] ) ) {
			return new WP_Error( 'invalid_family', __( 'Select a valid membership family.', 'nes-calendar-memberships' ) );
		}

		if ( ! in_array( $data['payment_method'], array( 'cheque', 'zelle', 'other' ), true ) ) {
			return new WP_Error( 'invalid_payment_method', __( 'Select a valid offline payment method.', 'nes-calendar-memberships' ) );
		}

		if ( $data['amount'] < 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Amount must be zero or greater.', 'nes-calendar-memberships' ) );
		}

		if ( ! in_array( $data['status'], array( 'pending', 'complete' ), true ) ) {
			$data['status'] = 'pending';
		}

		return $data;
	}

	private function find_user( string $lookup ): ?WP_User {
		if ( is_numeric( $lookup ) ) {
			$user = get_user_by( 'id', absint( $lookup ) );
			return $user instanceof WP_User ? $user : null;
		}

		$user = is_email( $lookup ) ? get_user_by( 'email', $lookup ) : get_user_by( 'login', $lookup );
		return $user instanceof WP_User ? $user : null;
	}

	private function redirect(): void {
		wp_safe_redirect( nescm_admin_page_url( 'manual-renewal' ) );
		exit;
	}
}
