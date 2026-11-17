<?php
/**
 * Membership Types admin template.
 *
 * @package NES_Calendar_Memberships
 *
 * @var array $rows List of family rows: key, label, builtin, in_use.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Membership Types', 'nes-calendar-memberships' ); ?></h2>
<p><?php esc_html_e( 'These are the NES membership families. Add a new type here, then create its year-specific MemberPress membership products and tag them with this family in the NES Calendar Membership Rules box.', 'nes-calendar-memberships' ); ?></p>

<table class="widefat striped nescm-types-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Membership Type', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Family Key', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Source', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Products', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'nes-calendar-memberships' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
				<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
				<td><?php echo $row['builtin'] ? esc_html__( 'Built-in', 'nes-calendar-memberships' ) : esc_html__( 'Custom', 'nes-calendar-memberships' ); ?></td>
				<td><?php echo esc_html( (string) $row['in_use'] ); ?></td>
				<td>
					<?php if ( $row['builtin'] ) : ?>
						<span class="description"><?php esc_html_e( 'Permanent', 'nes-calendar-memberships' ); ?></span>
					<?php elseif ( $row['in_use'] > 0 ) : ?>
						<span class="description"><?php esc_html_e( 'In use — reassign products to remove', 'nes-calendar-memberships' ); ?></span>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nescm-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this membership type? Renewal links using its key will stop working.', 'nes-calendar-memberships' ) ); ?>');">
							<input type="hidden" name="action" value="nescm_delete_membership_type" />
							<input type="hidden" name="nescm_type_key" value="<?php echo esc_attr( $row['key'] ); ?>" />
							<?php wp_nonce_field( 'nescm_delete_membership_type' ); ?>
							<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Remove', 'nes-calendar-memberships' ); ?></button>
						</form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<hr />

<h3><?php esc_html_e( 'Add a Membership Type', 'nes-calendar-memberships' ); ?></h3>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nescm-add-type-form">
	<input type="hidden" name="action" value="nescm_add_membership_type" />
	<?php wp_nonce_field( 'nescm_add_membership_type' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="nescm_type_name"><?php esc_html_e( 'Name', 'nes-calendar-memberships' ); ?></label></th>
			<td><input id="nescm_type_name" class="regular-text" type="text" name="nescm_type_name" required placeholder="<?php esc_attr_e( 'Student Membership', 'nes-calendar-memberships' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_type_key"><?php esc_html_e( 'Family Key', 'nes-calendar-memberships' ); ?></label></th>
			<td>
				<input id="nescm_type_key" class="regular-text" type="text" name="nescm_type_key" placeholder="student_membership" />
				<p class="description"><?php esc_html_e( 'Auto-filled from the name. Used in renewal links (/membership-renew/?family=KEY), so it is permanent once saved. Adjust it now if needed.', 'nes-calendar-memberships' ); ?></p>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Add Membership Type', 'nes-calendar-memberships' ) ); ?>
</form>

<script>
(function () {
	var name = document.getElementById('nescm_type_name');
	var key = document.getElementById('nescm_type_key');
	if (!name || !key) { return; }
	var edited = false;
	key.addEventListener('input', function () { edited = true; });
	name.addEventListener('input', function () {
		if (edited && key.value) { return; }
		key.value = name.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
	});
}());
</script>
