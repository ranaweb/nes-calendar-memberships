<?php
/**
 * Configuration validator.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Validator {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Settings $settings;
	private NESCM_Year_Calculator $calculator;

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Settings $settings, NESCM_Year_Calculator $calculator ) {
		$this->adapter    = $adapter;
		$this->settings   = $settings;
		$this->calculator = $calculator;
	}

	public function render_tab(): void {
		echo nescm_render_template(
			'admin-validator.php',
			array(
				'items'   => $this->items(),
				'version' => $this->adapter->version(),
				'edition' => $this->adapter->edition(),
			)
		);
	}

	public function items(): array {
		$items = array();

		if ( ! $this->adapter->is_active() ) {
			$items[] = $this->item( 'error', __( 'MemberPress', 'nes-calendar-memberships' ), __( 'MemberPress is not active or required classes are unavailable.', 'nes-calendar-memberships' ), __( 'Activate MemberPress before testing renewal routing or transactions.', 'nes-calendar-memberships' ) );
			return $items;
		}

		if ( defined( 'NESCM_TEST_DATE' ) ) {
			$items[] = $this->item( 'warning', __( 'Test Date Override', 'nes-calendar-memberships' ), __( 'NESCM_TEST_DATE is active.', 'nes-calendar-memberships' ), __( 'Remove the constant before production launch.', 'nes-calendar-memberships' ) );
		}

		$settings = $this->settings->all();
		if ( ! checkdate( (int) $settings['cutoff_month'], (int) $settings['cutoff_day'], 2026 ) ) {
			$items[] = $this->item( 'error', __( 'Settings', 'nes-calendar-memberships' ), __( 'Invalid next-year renewal start date.', 'nes-calendar-memberships' ), __( 'Review the NES Calendar Memberships settings.', 'nes-calendar-memberships' ) );
		}

		if ( 'yes' === $settings['enable_auto_renew_cta'] && empty( $settings['auto_renew_cta_url'] ) ) {
			$items[] = $this->item( 'warning', __( 'Auto-Renew CTA', 'nes-calendar-memberships' ), __( 'CTA is enabled but no URL is configured.', 'nes-calendar-memberships' ), __( 'Add a URL or disable the CTA.', 'nes-calendar-memberships' ) );
		}

		$memberships = $this->adapter->query_nes_memberships();
		$seen        = array();

		foreach ( $memberships as $membership ) {
			$post_id = (int) $membership->ID;
			$title   = get_the_title( $post_id );
			$family  = (string) get_post_meta( $post_id, '_nescm_family_key', true );
			$year    = (string) get_post_meta( $post_id, '_nescm_membership_year', true );
			$through = (string) get_post_meta( $post_id, '_nescm_valid_through', true );
			$method  = (string) get_post_meta( $post_id, '_nescm_renewal_method_type', true );
			$method  = $method ?: 'manual';

			if ( ! nescm_is_valid_family_key( $family ) ) {
				$items[] = $this->item( 'error', $title, __( 'Missing or invalid family key.', 'nes-calendar-memberships' ), __( 'Edit the membership and choose one of the NES family keys.', 'nes-calendar-memberships' ) );
			}

			if ( 'manual' === $method && ! preg_match( '/^\d{4}$/', $year ) ) {
				$items[] = $this->item( 'error', $title, __( 'Manual membership is missing a four-digit membership year.', 'nes-calendar-memberships' ), __( 'Set the membership year in the NES metabox.', 'nes-calendar-memberships' ) );
			}

			if ( ! $through || ! nescm_parse_date( $through ) ) {
				$items[] = $this->item( 'error', $title, __( 'Missing or invalid valid-through date.', 'nes-calendar-memberships' ), __( 'Set valid through to December 31 of the membership year.', 'nes-calendar-memberships' ) );
			}

			if ( 'manual' === $method && $family && $year ) {
				$key = $family . ':' . $year;
				if ( isset( $seen[ $key ] ) ) {
					$items[] = $this->item( 'error', $title, sprintf( __( 'Duplicate manual membership for %s.', 'nes-calendar-memberships' ), $key ), __( 'Keep only one manual membership per family/year.', 'nes-calendar-memberships' ) );
				}
				$seen[ $key ] = true;
			}

			if ( 'manual' === $method && $this->adapter->is_recurring_product( $post_id ) ) {
				$items[] = $this->item( 'warning', $title, __( 'Membership is marked manual in NES but appears recurring in MemberPress.', 'nes-calendar-memberships' ), __( 'Use one-time fixed expiration products for manual calendar-year memberships.', 'nes-calendar-memberships' ) );
			}

			$fixed = $this->adapter->product_fixed_expiration( $post_id );
			if ( 'manual' === $method && $through && $fixed && $fixed !== $through ) {
				$items[] = $this->item( 'warning', $title, sprintf( __( 'NES valid-through date (%1$s) does not match MemberPress fixed expiration (%2$s).', 'nes-calendar-memberships' ), $through, $fixed ), __( 'Save the membership again or update the MemberPress expiration date.', 'nes-calendar-memberships' ) );
			}
		}

		$current_year = $this->calculator->get_membership_year_for_date( $this->calculator->today() );
		foreach ( nescm_get_families() as $family => $label ) {
			foreach ( array( $current_year, $current_year + 1 ) as $year ) {
				$target_id = $this->adapter->find_membership( $family, $year, 'manual' );
				if ( ! $target_id ) {
					$items[] = $this->item( 'error', $label . ' ' . $year, __( 'Missing manual membership product.', 'nes-calendar-memberships' ), __( 'Create or generate the year-specific MemberPress membership.', 'nes-calendar-memberships' ) );
				} elseif ( ! $this->adapter->is_published_membership( $target_id ) ) {
					$items[] = $this->item( 'warning', get_the_title( $target_id ), __( 'Target membership exists but is not published.', 'nes-calendar-memberships' ), __( 'Publish it before renewal links should route members there.', 'nes-calendar-memberships' ) );
				}
			}
		}

		if ( ! has_action( 'mepr_above_checkout_form' ) ) {
			$items[] = $this->item( 'warning', __( 'Checkout Hook', 'nes-calendar-memberships' ), __( 'No callback is currently registered on mepr_above_checkout_form.', 'nes-calendar-memberships' ), __( 'Reload plugins and confirm checkout notices display in MemberPress checkout.', 'nes-calendar-memberships' ) );
		}

		if ( empty( $items ) ) {
			$items[] = $this->item( 'notice', __( 'Checkup', 'nes-calendar-memberships' ), __( 'No issues found in the current NES Calendar Memberships checks.', 'nes-calendar-memberships' ), __( 'Run manual checkout and dashboard tests before launch.', 'nes-calendar-memberships' ) );
		}

		return $items;
	}

	private function item( string $severity, string $item, string $problem, string $fix ): array {
		return compact( 'severity', 'item', 'problem', 'fix' );
	}
}
