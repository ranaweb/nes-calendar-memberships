<?php
/**
 * Dashboard CTA template.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nescm-auto-renew-cta">
	<p><?php echo esc_html( $text ); ?></p>
	<a class="nescm-button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button ); ?></a>
</div>
