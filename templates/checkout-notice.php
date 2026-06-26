<?php
/**
 * Checkout notice template.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nescm-checkout-notice" role="note">
	<p>
		<?php
		if ( $immediate ) {
			printf(
				/* translators: 1: membership year, 2: membership name, 3: valid-through date. */
				esc_html__( 'You are purchasing your %1$d %2$s. Your membership is active immediately and valid through %3$s.', 'nes-calendar-memberships' ),
				(int) $year,
				esc_html( $label ),
				esc_html( $valid_through_display )
			);
		} else {
			printf(
				/* translators: 1: membership year, 2: membership name, 3: valid-through date. */
				esc_html__( 'You are purchasing your %1$d %2$s. This membership is valid through %3$s.', 'nes-calendar-memberships' ),
				(int) $year,
				esc_html( $label ),
				esc_html( $valid_through_display )
			);
		}
		?>
	</p>
	<?php if ( $manual_method && $pending_offline ) : ?>
		<p><?php esc_html_e( 'If you choose Check/Zelle payment, your renewal will be completed after NES receives and records your payment.', 'nes-calendar-memberships' ); ?></p>
	<?php endif; ?>
</div>
