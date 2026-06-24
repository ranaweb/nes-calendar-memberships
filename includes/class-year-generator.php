<?php
/**
 * Next-year membership generator.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Year_Generator {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;
	private NESCM_Year_Calculator $calculator;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings, NESCM_Year_Calculator $calculator ) {
		$this->adapter    = $adapter;
		$this->settings   = $settings;
		$this->calculator = $calculator;
	}

	public function hooks(): void {
		add_action( 'admin_post_nescm_generate_year', array( $this, 'handle' ) );
	}

	public function render_tab(): void {
		$current_year = (int) wp_date( 'Y', null, wp_timezone() );
		echo nescm_render_template(
			'admin-year-generator.php',
			array(
				'current_year' => $current_year,
			)
		);
	}

	public function handle(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to generate memberships.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_generate_year' );

		$source_year   = absint( $_POST['source_year'] ?? 0 );
		$target_year   = absint( $_POST['target_year'] ?? 0 );
		$clone_prices  = ! empty( $_POST['clone_prices'] );
		$clone_settings = ! empty( $_POST['clone_settings'] );
		$status        = isset( $_POST['post_status'] ) && 'publish' === $_POST['post_status'] ? 'publish' : 'draft';

		if ( $source_year < 2000 || $target_year < 2000 || $target_year <= $source_year ) {
			nescm_add_admin_notice( __( 'Use valid source and target years. Target year must be after source year.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		$created = 0;
		$skipped = 0;
		$missing = 0;

		foreach ( nescm_get_families() as $family => $label ) {
			$source_id = $this->adapter->find_membership( $family, $source_year, 'manual' );
			if ( ! $source_id ) {
				$missing++;
				continue;
			}

			if ( $this->adapter->find_membership( $family, $target_year, 'manual' ) ) {
				$skipped++;
				continue;
			}

			$new_id = $this->create_from_source( $source_id, $family, $label, $target_year, $status, $clone_prices, $clone_settings );
			if ( $new_id > 0 ) {
				update_post_meta( $source_id, '_nescm_next_year_membership_id', (string) $new_id );
				$created++;
			}
		}

		nescm_add_admin_notice(
			sprintf(
				/* translators: 1: created, 2: skipped, 3: missing */
				__( 'Year generation complete. Created: %1$d. Skipped existing: %2$d. Missing source memberships: %3$d. Review generated drafts before launch.', 'nes-calendar-memberships' ),
				$created,
				$skipped,
				$missing
			),
			$created > 0 ? 'success' : 'warning'
		);
		$this->redirect();
	}

	private function create_from_source( int $source_id, string $family, string $fallback_label, int $target_year, string $status, bool $clone_prices, bool $clone_settings ): int {
		$public_label = (string) get_post_meta( $source_id, '_nescm_public_label', true );
		$public_label = $public_label ?: $fallback_label;

		$source_post = get_post( $source_id );
		if ( ! $source_post ) {
			return 0;
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => $this->adapter->get_membership_post_type(),
				'post_status'  => $status,
				'post_title'   => sprintf( '%1$s - %2$d', $public_label, $target_year ),
				'post_content' => $source_post->post_content,
				'post_excerpt' => $source_post->post_excerpt,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			$this->adapter->log( 'Year generation failed: ' . $new_id->get_error_message() );
			return 0;
		}

		$new_id = (int) $new_id;

		if ( $clone_settings ) {
			$this->clone_post_meta( $source_id, $new_id );
		} elseif ( $clone_prices ) {
			$price = get_post_meta( $source_id, '_mepr_product_price', true );
			update_post_meta( $new_id, '_mepr_product_price', $price );
		}

		$this->clone_taxonomies( $source_id, $new_id );

		$valid_from   = $this->calculator->get_valid_from_for_year( $target_year )->format( 'Y-m-d' );
		$valid_through = $this->calculator->get_valid_through_for_year( $target_year )->format( 'Y-m-d' );

		update_post_meta( $new_id, '_nescm_enabled', 'yes' );
		update_post_meta( $new_id, '_nescm_family_key', $family );
		update_post_meta( $new_id, '_nescm_public_label', $public_label );
		update_post_meta( $new_id, '_nescm_membership_year', (string) $target_year );
		update_post_meta( $new_id, '_nescm_valid_from', $valid_from );
		update_post_meta( $new_id, '_nescm_valid_through', $valid_through );
		update_post_meta( $new_id, '_nescm_sales_window_start', $this->calculator->get_sales_window_start_for_year( $target_year )->format( 'Y-m-d' ) );
		update_post_meta( $new_id, '_nescm_sales_window_end', $this->calculator->get_sales_window_end_for_year( $target_year )->format( 'Y-m-d' ) );
		update_post_meta( $new_id, '_nescm_renewal_method_type', 'manual' );
		update_post_meta( $new_id, '_nescm_checkout_context_label', sprintf( '%1$d %2$s', $target_year, $public_label ) );
		update_post_meta( $new_id, '_nescm_next_year_membership_id', '' );

		$this->adapter->set_product_fixed_expiration( $new_id, $valid_through );

		return $new_id;
	}

	private function clone_post_meta( int $source_id, int $target_id ): void {
		$excluded_prefixes = array(
			'_edit_',
			'_wp_',
			'_nescm_',
			'_mepr_stripe_product_id_',
			'_mepr_stripe_initial_payment_product_id_',
		);
		$excluded_keys = array(
			'_mepr_plan_code',
		);

		$meta = get_post_meta( $source_id );
		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $excluded_keys, true ) ) {
				continue;
			}

			foreach ( $excluded_prefixes as $prefix ) {
				if ( str_starts_with( $key, $prefix ) ) {
					continue 2;
				}
			}

			delete_post_meta( $target_id, $key );
			foreach ( $values as $value ) {
				add_post_meta( $target_id, $key, maybe_unserialize( $value ) );
			}
		}
	}

	private function clone_taxonomies( int $source_id, int $target_id ): void {
		$taxonomies = get_object_taxonomies( $this->adapter->get_membership_post_type() );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $target_id, array_map( 'intval', $terms ), $taxonomy );
			}
		}
	}

	private function redirect(): void {
		wp_safe_redirect( nescm_admin_page_url( 'generate-year' ) );
		exit;
	}
}
