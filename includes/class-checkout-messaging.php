<?php
/**
 * Checkout notices.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Checkout_Messaging {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;
	private NESCM_Year_Calculator $calculator;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings, NESCM_Year_Calculator $calculator ) {
		$this->adapter    = $adapter;
		$this->settings   = $settings;
		$this->calculator = $calculator;
	}

	public function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'mepr_above_checkout_form', array( $this, 'render_notice' ) );
		add_shortcode( 'nescm_checkout_notice', array( $this, 'checkout_notice_shortcode' ) );
	}

	public function enqueue(): void {
		wp_enqueue_style( 'nescm-frontend', NESCM_URL . 'assets/css/frontend.css', array(), NESCM_VERSION );
	}

	public function render_notice( int $product_id ): void {
		echo wp_kses_post( $this->notice_markup( $product_id ) );
	}

	public function checkout_notice_shortcode( array $atts = array() ): string {
		$atts       = shortcode_atts( array( 'id' => 0 ), $atts, 'nescm_checkout_notice' );
		$product_id = absint( $atts['id'] );

		if ( ! $product_id && is_singular( $this->adapter->get_membership_post_type() ) ) {
			$product_id = get_the_ID();
		}

		return $this->notice_markup( $product_id );
	}

	private function notice_markup( int $product_id ): string {
		if ( $product_id <= 0 || 'yes' !== get_post_meta( $product_id, '_nescm_enabled', true ) ) {
			return '';
		}

		$year          = (int) get_post_meta( $product_id, '_nescm_membership_year', true );
		$label         = (string) get_post_meta( $product_id, '_nescm_public_label', true );
		$valid_through = (string) get_post_meta( $product_id, '_nescm_valid_through', true );
		$method        = (string) get_post_meta( $product_id, '_nescm_renewal_method_type', true );

		if ( ! $label ) {
			$family = (string) get_post_meta( $product_id, '_nescm_family_key', true );
			$label  = nescm_get_families()[ $family ] ?? get_the_title( $product_id );
		}

		if ( ! $year || ! $valid_through ) {
			return '';
		}

		$today_year = $this->calculator->get_membership_year_for_date( $this->calculator->today() );
		$immediate  = $year > (int) $this->calculator->today()->format( 'Y' ) || $year === $today_year && $this->calculator->is_after_cutoff( $this->calculator->today() );

		return nescm_render_template(
			'checkout-notice.php',
			array(
				'year'                  => $year,
				'label'                 => $label,
				'valid_through_display' => nescm_format_display_date( $valid_through ),
				'immediate'             => $immediate,
				'manual_method'         => 'manual' === $method,
				'pending_offline'       => 'pending_until_complete' === $this->settings->get( 'manual_payment_access_mode' ),
			)
		);
	}
}
