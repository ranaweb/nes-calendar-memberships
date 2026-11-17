<?php
/**
 * Offline payment approval and manual renewal helper.
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
		add_action( 'admin_post_nescm_create_manual_renewal', array( $this, 'handle_create' ) );
		add_action( 'admin_post_nescm_complete_offline_payment', array( $this, 'handle_complete_offline_payment' ) );
		add_action( 'admin_post_nescm_void_offline_payment', array( $this, 'handle_void_offline_payment' ) );
	}

	public function render_tab(): void {
		$pending_key = 'nescm_manual_renewal_pending_' . get_current_user_id();
		$pending     = get_transient( $pending_key );
		if ( is_array( $pending ) ) {
			delete_transient( $pending_key );
		}

		$review_id   = isset( $_GET['review_txn'] ) ? absint( wp_unslash( $_GET['review_txn'] ) ) : 0;
		$review_date = isset( $_GET['payment_received_date'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_received_date'] ) ) : '';
		$review      = $review_id > 0 ? $this->review_data( $review_id, $review_date ) : array();

		echo nescm_render_template(
			'admin-manual-renewal.php',
			array(
				'families'             => nescm_get_families(),
				'pending'              => is_array( $pending ) ? $pending : array(),
				'pending_transactions' => $this->pending_transactions(),
				'review'               => $review,
				'product_map'          => $this->product_map(),
				'settings'             => $this->settings->all(),
			)
		);
	}

	public function handle_create(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to create manual renewals.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_create_manual_renewal' );

		$data = $this->sanitize_manual_submission( isset( $_POST['nescm_manual'] ) && is_array( $_POST['nescm_manual'] ) ? wp_unslash( $_POST['nescm_manual'] ) : array() );

		if ( is_wp_error( $data ) ) {
			nescm_add_admin_notice( $data->get_error_message(), 'error' );
			$this->redirect();
		}

		$user = $this->find_user( $data['user'] );
		if ( ! $user ) {
			nescm_add_admin_notice( __( 'Could not find a WordPress user for that login, email, or ID.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		$preview = $this->calculate_preview( $data['family'], $data['payment_date'] );
		if ( empty( $preview['target_id'] ) || ! $this->adapter->is_published_membership( (int) $preview['target_id'] ) ) {
			nescm_add_admin_notice( __( 'No published target membership could be calculated for that family and payment date.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		$duplicate = $this->adapter->has_duplicate_transaction( (int) $user->ID, $data['family'], (int) $preview['year'], (int) $preview['target_id'] );
		if ( $duplicate && empty( $data['confirm_duplicate'] ) ) {
			$data['preview']          = $preview;
			$data['resolved_user_id'] = (int) $user->ID;
			set_transient( 'nescm_manual_renewal_pending_' . get_current_user_id(), $data, 5 * MINUTE_IN_SECONDS );
			nescm_add_admin_notice( __( 'This member already appears to have a completed transaction for that family and year. Review the duplicate warning before continuing.', 'nes-calendar-memberships' ), 'warning' );
			$this->redirect();
		}

		$meta = $this->transaction_meta_from_data( $data, $preview );

		$result = $this->adapter->create_manual_transaction(
			(int) $user->ID,
			(int) $preview['target_id'],
			(float) $data['amount'],
			$data['status'],
			$data['payment_date'],
			$preview['valid_through'],
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
				(int) $preview['year'],
				get_the_title( (int) $preview['target_id'] )
			),
			'success'
		);
		$this->redirect();
	}

	public function handle_complete_offline_payment(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to complete offline payments.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_complete_offline_payment' );

		$transaction_id = absint( $_POST['transaction_id'] ?? 0 );
		$payment_date   = sanitize_text_field( (string) ( $_POST['payment_received_date'] ?? '' ) );
		$reference      = sanitize_text_field( (string) ( $_POST['reference'] ?? '' ) );
		$note           = sanitize_textarea_field( (string) ( $_POST['admin_note'] ?? '' ) );
		$override       = ! empty( $_POST['confirm_duplicate'] );

		$review = $this->review_data( $transaction_id, $payment_date );
		if ( empty( $review ) || empty( $review['preview']['target_id'] ) ) {
			nescm_add_admin_notice( __( 'Could not calculate a valid target membership for that pending transaction.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		if ( ! $this->adapter->is_published_membership( (int) $review['preview']['target_id'] ) ) {
			nescm_add_admin_notice( __( 'The calculated target membership is not published. Publish it before completing this payment.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect_review( $transaction_id );
		}

		if ( $review['duplicate'] && ! $override ) {
			nescm_add_admin_notice( __( 'Duplicate completed renewal detected. Check the confirmation box before completing anyway.', 'nes-calendar-memberships' ), 'warning' );
			$this->redirect_review( $transaction_id, $payment_date );
		}

		$meta = $this->transaction_meta_from_data(
			array(
				'family'         => $review['family_key'],
				'payment_method' => $review['payment_method'],
				'reference'      => $reference,
				'note'           => $note,
			),
			$review['preview']
		);

		$result = $this->adapter->complete_pending_transaction(
			$transaction_id,
			(int) $review['preview']['target_id'],
			$payment_date,
			$review['preview']['valid_through'],
			$meta
		);

		if ( is_wp_error( $result ) ) {
			nescm_add_admin_notice( $result->get_error_message(), 'error' );
			$this->redirect_review( $transaction_id );
		}

		nescm_add_admin_notice(
			sprintf(
				/* translators: 1: transaction ID */
				__( 'Offline payment transaction #%1$d was completed and activated.', 'nes-calendar-memberships' ),
				(int) $result
			),
			'success'
		);
		$this->redirect();
	}

	public function handle_void_offline_payment(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to void offline payments.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_void_offline_payment' );

		$transaction_id = absint( $_POST['transaction_id'] ?? 0 );
		$note           = sanitize_textarea_field( (string) ( $_POST['admin_note'] ?? '' ) );
		$review         = $this->review_data( $transaction_id );

		if ( empty( $review ) ) {
			nescm_add_admin_notice( __( 'Pending transaction could not be found or is not an NES membership payment.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		$result = $this->adapter->void_pending_transaction( $transaction_id, $note );
		if ( is_wp_error( $result ) ) {
			nescm_add_admin_notice( $result->get_error_message(), 'error' );
			$this->redirect_review( $transaction_id );
		}

		nescm_add_admin_notice( __( 'Pending offline payment was voided.', 'nes-calendar-memberships' ), 'success' );
		$this->redirect();
	}

	public function calculate_preview( string $family, string $payment_date ): array {
		$date = nescm_parse_date( $payment_date );
		if ( ! $date || ! nescm_is_valid_family_key( $family ) ) {
			return array();
		}

		$year          = $this->calculator->get_membership_year_for_date( $date );
		$cutoff        = $this->calculator->get_cutoff_date( (int) $date->format( 'Y' ) );
		$valid_through = $this->calculator->get_valid_through_for_year( $year )->format( 'Y-m-d' );
		$target_id     = $this->adapter->find_membership( $family, $year, 'manual' );

		return array(
			'payment_date'          => $date->format( 'Y-m-d' ),
			'payment_date_display'  => wp_date( 'F j, Y', $date->getTimestamp(), wp_timezone() ),
			'cutoff_date'           => $cutoff->format( 'Y-m-d' ),
			'cutoff_date_display'   => wp_date( 'F j, Y', $cutoff->getTimestamp(), wp_timezone() ),
			'after_cutoff'          => $this->calculator->is_after_cutoff( $date ),
			'result_label'          => $this->calculator->is_after_cutoff( $date ) ? __( 'After cutoff', 'nes-calendar-memberships' ) : __( 'On or before cutoff', 'nes-calendar-memberships' ),
			'family'                => $family,
			'family_label'          => nescm_get_families()[ $family ],
			'year'                  => $year,
			'valid_through'         => $valid_through,
			'valid_through_display' => nescm_format_display_date( $valid_through ),
			'target_id'             => $target_id ?: 0,
			'target_title'          => $target_id ? get_the_title( $target_id ) : __( 'Missing target membership', 'nes-calendar-memberships' ),
			'target_status'         => $target_id ? (string) get_post_status( $target_id ) : '',
		);
	}

	private function pending_transactions(): array {
		$rows = array();
		foreach ( $this->adapter->pending_offline_transactions() as $row ) {
			$rows[] = $this->pending_row_data( $row );
		}

		return $rows;
	}

	private function pending_row_data( object $row ): array {
		$user    = get_user_by( 'id', (int) $row->user_id );
		$product = get_post( (int) $row->product_id );
		$family  = (string) get_post_meta( (int) $row->product_id, '_nescm_family_key', true );
		$date    = $this->transaction_date_for_display( (string) $row->created_at );
		$preview = $family ? $this->calculate_preview( $family, $date ) : array();

		return array(
			'id'             => (int) $row->id,
			'user_id'        => (int) $row->user_id,
			'member'         => $user instanceof WP_User ? $user->display_name : __( 'Unknown user', 'nes-calendar-memberships' ),
			'email'          => $user instanceof WP_User ? $user->user_email : '',
			'product_id'     => (int) $row->product_id,
			'product_title'  => $product ? $product->post_title : __( 'Unknown membership', 'nes-calendar-memberships' ),
			'family_key'     => $family,
			'family_label'   => nescm_get_families()[ $family ] ?? $family,
			'payment_method' => (string) $row->gateway,
			'amount'         => (float) $row->total,
			'created_date'   => $date,
			'status'         => (string) $row->status,
			'preview'        => $preview,
			'review_url'     => wp_nonce_url(
				add_query_arg(
					array(
						'page'       => 'nes-calendar-memberships',
						'tab'        => 'manual-renewal',
						'review_txn' => (int) $row->id,
					),
					nescm_admin_page_url( 'manual-renewal' )
				),
				'nescm_review_offline_payment_' . (int) $row->id
			),
		);
	}

	private function review_data( int $transaction_id, string $payment_date_override = '' ): array {
		$row = $this->adapter->get_transaction_row( $transaction_id );
		if ( ! $row || 'pending' !== (string) $row->status || 'yes' !== get_post_meta( (int) $row->product_id, '_nescm_enabled', true ) ) {
			return array();
		}

		$data = $this->pending_row_data( $row );
		$date = $payment_date_override && nescm_parse_date( $payment_date_override ) ? $payment_date_override : $data['created_date'];
		$data['payment_received_date'] = $date;
		$data['preview'] = $data['family_key'] ? $this->calculate_preview( $data['family_key'], $date ) : array();
		$data['reference'] = $this->adapter->transaction_meta_value( $transaction_id, '_nescm_payment_reference' );
		$data['admin_note'] = $this->adapter->transaction_meta_value( $transaction_id, '_nescm_admin_note' );
		$data['existing_status'] = $this->existing_membership_status( (int) $row->user_id );
		$data['duplicate'] = ! empty( $data['preview']['year'] ) && $this->adapter->has_duplicate_transaction(
			(int) $row->user_id,
			$data['family_key'],
			(int) $data['preview']['year'],
			(int) $data['preview']['target_id'],
			$transaction_id
		);

		return $data;
	}

	private function existing_membership_status( int $user_id ): string {
		$txn = $this->adapter->get_latest_nes_transaction_for_user( $user_id );
		if ( ! $txn ) {
			return __( 'No existing NES membership transaction found.', 'nes-calendar-memberships' );
		}

		$product_title = get_the_title( (int) $txn->product_id );
		$status        = isset( $txn->status ) ? (string) $txn->status : __( 'unknown', 'nes-calendar-memberships' );
		$expires       = isset( $txn->expires_at ) && '0000-00-00 00:00:00' !== $txn->expires_at
			? wp_date( 'F j, Y', strtotime( (string) $txn->expires_at ), wp_timezone() )
			: __( 'lifetime/no expiration', 'nes-calendar-memberships' );

		return sprintf(
			/* translators: 1: product title, 2: status, 3: expiration */
			__( '%1$s - %2$s, expires %3$s', 'nes-calendar-memberships' ),
			$product_title,
			$status,
			$expires
		);
	}

	private function transaction_date_for_display( string $created_at ): string {
		$timestamp = strtotime( $created_at );
		if ( ! $timestamp ) {
			return wp_date( 'Y-m-d', null, wp_timezone() );
		}

		return wp_date( 'Y-m-d', $timestamp, wp_timezone() );
	}

	private function product_map(): array {
		$map = array();
		foreach ( $this->adapter->query_nes_memberships() as $membership ) {
			$post_id = (int) $membership->ID;
			$family  = (string) get_post_meta( $post_id, '_nescm_family_key', true );
			$year    = (string) get_post_meta( $post_id, '_nescm_membership_year', true );
			$method  = (string) get_post_meta( $post_id, '_nescm_renewal_method_type', true );
			if ( ! $family || ! $year || 'manual' !== ( $method ?: 'manual' ) ) {
				continue;
			}

			if ( ! isset( $map[ $family ] ) ) {
				$map[ $family ] = array();
			}

			$map[ $family ][ $year ] = array(
				'id'      => $post_id,
				'title'   => get_the_title( $post_id ),
				'status'  => get_post_status( $post_id ),
				'through' => (string) get_post_meta( $post_id, '_nescm_valid_through', true ),
			);
		}

		return $map;
	}

	private function sanitize_manual_submission( array $raw ): array|WP_Error {
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

		if ( ! nescm_parse_date( $data['payment_date'] ) ) {
			return new WP_Error( 'invalid_payment_date', __( 'Payment received date must be a valid date.', 'nes-calendar-memberships' ) );
		}

		if ( ! in_array( $data['payment_method'], array( 'check', 'zelle', 'other' ), true ) ) {
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

	private function transaction_meta_from_data( array $data, array $preview ): array {
		$target_id = (int) ( $preview['target_id'] ?? 0 );

		return array(
			'_nescm_family_key'          => (string) ( $data['family'] ?? '' ),
			'_nescm_public_label'        => $target_id ? (string) get_post_meta( $target_id, '_nescm_public_label', true ) : '',
			'_nescm_membership_year'     => (string) ( $preview['year'] ?? '' ),
			'_nescm_valid_through'       => (string) ( $preview['valid_through'] ?? '' ),
			'_nescm_renewal_method_type' => 'manual',
			'_nescm_payment_method'      => (string) ( $data['payment_method'] ?? '' ),
			'_nescm_payment_reference'   => (string) ( $data['reference'] ?? '' ),
			'_nescm_admin_note'          => (string) ( $data['note'] ?? '' ),
		);
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

	private function redirect_review( int $transaction_id, string $payment_date = '' ): void {
		$args = array(
			'review_txn' => $transaction_id,
		);

		if ( $payment_date && nescm_parse_date( $payment_date ) ) {
			$args['payment_received_date'] = $payment_date;
		}

		wp_safe_redirect(
			add_query_arg(
				$args,
				nescm_admin_page_url( 'manual-renewal' )
			)
		);
		exit;
	}
}
