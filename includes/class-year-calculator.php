<?php
/**
 * Calendar-year calculation rules.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Year_Calculator {
	private NESCM_Settings $settings;

	public function __construct( NESCM_Settings $settings ) {
		$this->settings = $settings;
	}

	public function today(): DateTimeImmutable {
		if ( defined( 'NESCM_TEST_DATE' ) && ( current_user_can( nescm_admin_capability() ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) ) {
			$parsed = nescm_parse_date( (string) NESCM_TEST_DATE );
			if ( $parsed ) {
				return $parsed;
			}
		}

		return new DateTimeImmutable( 'now', wp_timezone() );
	}

	public function get_cutoff_date( int $year ): DateTimeImmutable {
		$month = (int) $this->settings->get( 'cutoff_month', 9 );
		$day   = (int) $this->settings->get( 'cutoff_day', 1 );

		return new DateTimeImmutable( sprintf( '%04d-%02d-%02d 00:00:00', $year, $month, $day ), wp_timezone() );
	}

	public function get_membership_year_for_date( DateTimeInterface $date ): int {
		$local  = DateTimeImmutable::createFromInterface( $date )->setTimezone( wp_timezone() );
		$year   = (int) $local->format( 'Y' );
		$cutoff = $this->get_cutoff_date( $year );

		return $local >= $cutoff ? $year + 1 : $year;
	}

	public function is_after_cutoff( DateTimeInterface $date ): bool {
		$local = DateTimeImmutable::createFromInterface( $date )->setTimezone( wp_timezone() );
		return $local >= $this->get_cutoff_date( (int) $local->format( 'Y' ) );
	}

	public function get_valid_from_for_year( int $year ): DateTimeImmutable {
		return new DateTimeImmutable( sprintf( '%04d-01-01 00:00:00', $year ), wp_timezone() );
	}

	public function get_valid_through_for_year( int $year ): DateTimeImmutable {
		return new DateTimeImmutable( sprintf( '%04d-12-31 23:59:59', $year ), wp_timezone() );
	}

	public function get_sales_window_start_for_year( int $membership_year ): DateTimeImmutable {
		return $this->get_cutoff_date( $membership_year - 1 );
	}

	public function get_sales_window_end_for_year( int $membership_year ): DateTimeImmutable {
		return $this->get_cutoff_date( $membership_year )->modify( '-1 day' )->setTime( 23, 59, 59 );
	}
}
