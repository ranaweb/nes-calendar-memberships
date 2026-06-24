<?php
/**
 * Manual renewal admin template.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$values = wp_parse_args(
	$pending,
	array(
		'user'              => '',
		'family'            => '',
		'payment_date'      => wp_date( 'Y-m-d', null, wp_timezone() ),
		'payment_method'    => 'cheque',
		'amount'            => '',
		'reference'         => '',
		'note'              => '',
		'status'            => 'pending',
		'confirm_duplicate' => '',
	)
);
?>
<h2><?php esc_html_e( 'Manual Cheque/Zelle Renewal', 'nes-calendar-memberships' ); ?></h2>

<?php if ( ! empty( $pending['target_id'] ) ) : ?>
	<div class="notice notice-warning inline">
		<p><strong><?php esc_html_e( 'Duplicate warning', 'nes-calendar-memberships' ); ?></strong></p>
		<p>
			<?php
			printf(
				esc_html__( 'This member already appears to have a completed %1$d transaction for %2$s. Continue only if this is intentional.', 'nes-calendar-memberships' ),
				(int) $pending['calculated_year'],
				esc_html( $pending['target_title'] )
			);
			?>
		</p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="nescm_create_manual_renewal" />
	<?php wp_nonce_field( 'nescm_create_manual_renewal' ); ?>
	<?php if ( ! empty( $pending['target_id'] ) ) : ?>
		<input type="hidden" name="nescm_manual[confirm_duplicate]" value="yes" />
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="nescm_manual_user"><?php esc_html_e( 'Member', 'nes-calendar-memberships' ); ?></label></th>
			<td>
				<input id="nescm_manual_user" class="regular-text" type="text" name="nescm_manual[user]" value="<?php echo esc_attr( $values['user'] ); ?>" required />
				<p class="description"><?php esc_html_e( 'Enter user ID, login, or email.', 'nes-calendar-memberships' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_manual_family"><?php esc_html_e( 'Membership Family', 'nes-calendar-memberships' ); ?></label></th>
			<td>
				<select id="nescm_manual_family" name="nescm_manual[family]" required>
					<option value=""><?php esc_html_e( 'Select family', 'nes-calendar-memberships' ); ?></option>
					<?php foreach ( $families as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['family'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_payment_date"><?php esc_html_e( 'Payment Date', 'nes-calendar-memberships' ); ?></label></th>
			<td><input id="nescm_payment_date" type="date" name="nescm_manual[payment_date]" value="<?php echo esc_attr( $values['payment_date'] ); ?>" required /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Payment Method', 'nes-calendar-memberships' ); ?></th>
			<td>
				<select name="nescm_manual[payment_method]">
					<option value="cheque" <?php selected( $values['payment_method'], 'cheque' ); ?>><?php esc_html_e( 'Cheque', 'nes-calendar-memberships' ); ?></option>
					<option value="zelle" <?php selected( $values['payment_method'], 'zelle' ); ?>><?php esc_html_e( 'Zelle', 'nes-calendar-memberships' ); ?></option>
					<option value="other" <?php selected( $values['payment_method'], 'other' ); ?>><?php esc_html_e( 'Other offline', 'nes-calendar-memberships' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_amount"><?php esc_html_e( 'Amount', 'nes-calendar-memberships' ); ?></label></th>
			<td><input id="nescm_amount" type="number" min="0" step="0.01" name="nescm_manual[amount]" value="<?php echo esc_attr( (string) $values['amount'] ); ?>" required /></td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_reference"><?php esc_html_e( 'Reference', 'nes-calendar-memberships' ); ?></label></th>
			<td><input id="nescm_reference" class="regular-text" type="text" name="nescm_manual[reference]" value="<?php echo esc_attr( $values['reference'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_note"><?php esc_html_e( 'Admin Note', 'nes-calendar-memberships' ); ?></label></th>
			<td><textarea id="nescm_note" class="large-text" rows="3" name="nescm_manual[note]"><?php echo esc_textarea( $values['note'] ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Create As', 'nes-calendar-memberships' ); ?></th>
			<td>
				<select name="nescm_manual[status]">
					<option value="pending" <?php selected( $values['status'], 'pending' ); ?>><?php esc_html_e( 'Pending Manual Renewal', 'nes-calendar-memberships' ); ?></option>
					<option value="complete" <?php selected( $values['status'], 'complete' ); ?>><?php esc_html_e( 'Completed Manual Renewal', 'nes-calendar-memberships' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<?php submit_button( empty( $pending['target_id'] ) ? __( 'Create Pending Manual Renewal', 'nes-calendar-memberships' ) : __( 'Create Duplicate Manual Renewal', 'nes-calendar-memberships' ) ); ?>
</form>
