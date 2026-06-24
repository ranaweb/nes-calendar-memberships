<?php
/**
 * Dashboard summary template.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nescm-membership-summary">
	<div class="nescm-summary-row">
		<span><?php esc_html_e( 'Level', 'nes-calendar-memberships' ); ?></span>
		<strong><?php echo esc_html( $summary['label'] ); ?></strong>
	</div>
	<div class="nescm-summary-row">
		<span><?php esc_html_e( 'Status', 'nes-calendar-memberships' ); ?></span>
		<strong><?php echo esc_html( $summary['status_label'] ); ?></strong>
	</div>
	<?php if ( ! empty( $summary['expiration_label'] ) ) : ?>
		<div class="nescm-summary-row">
			<span><?php esc_html_e( 'Expiration', 'nes-calendar-memberships' ); ?></span>
			<strong><?php echo esc_html( $summary['expiration_label'] ); ?></strong>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $summary['renewal_method_label'] ) ) : ?>
		<div class="nescm-summary-row">
			<span><?php esc_html_e( 'Renewal Method', 'nes-calendar-memberships' ); ?></span>
			<strong><?php echo esc_html( $summary['renewal_method_label'] ); ?></strong>
		</div>
	<?php endif; ?>
</div>
