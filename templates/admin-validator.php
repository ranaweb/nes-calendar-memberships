<?php
/**
 * Validator admin template.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Configuration Checkup', 'nes-calendar-memberships' ); ?></h2>
<p>
	<?php
	printf(
		esc_html__( 'MemberPress detected: %1$s %2$s', 'nes-calendar-memberships' ),
		esc_html( $edition ?: __( 'unknown edition', 'nes-calendar-memberships' ) ),
		esc_html( $version ?: __( 'unknown version', 'nes-calendar-memberships' ) )
	);
	?>
</p>

<table class="widefat striped nescm-checkup-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Severity', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Item', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Problem', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Suggested Fix', 'nes-calendar-memberships' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $items as $item ) : ?>
			<tr class="nescm-severity-<?php echo esc_attr( $item['severity'] ); ?>">
				<td><strong><?php echo esc_html( ucfirst( $item['severity'] ) ); ?></strong></td>
				<td><?php echo esc_html( $item['item'] ); ?></td>
				<td><?php echo esc_html( $item['problem'] ); ?></td>
				<td><?php echo esc_html( $item['fix'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
