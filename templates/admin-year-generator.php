<?php
/**
 * Year generator admin template.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Generate Year-Specific Memberships', 'nes-calendar-memberships' ); ?></h2>
<p><?php esc_html_e( 'This creates one target-year membership per NES family when a source-year membership exists. Generated products should be reviewed before launch.', 'nes-calendar-memberships' ); ?></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="nescm_generate_year" />
	<?php wp_nonce_field( 'nescm_generate_year' ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="nescm_source_year"><?php esc_html_e( 'Source Year', 'nes-calendar-memberships' ); ?></label></th>
			<td><input id="nescm_source_year" class="small-text" type="number" min="2000" max="2100" name="source_year" value="<?php echo esc_attr( (string) $current_year ); ?>" required /></td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_target_year"><?php esc_html_e( 'Target Year', 'nes-calendar-memberships' ); ?></label></th>
			<td><input id="nescm_target_year" class="small-text" type="number" min="2000" max="2100" name="target_year" value="<?php echo esc_attr( (string) ( $current_year + 1 ) ); ?>" required /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Clone Options', 'nes-calendar-memberships' ); ?></th>
			<td>
				<label><input type="checkbox" name="clone_prices" value="1" checked /> <?php esc_html_e( 'Clone price', 'nes-calendar-memberships' ); ?></label><br />
				<label><input type="checkbox" name="clone_settings" value="1" checked /> <?php esc_html_e( 'Clone MemberPress product settings', 'nes-calendar-memberships' ); ?></label>
				<p class="description"><?php esc_html_e( 'This does not clone separate MemberPress rules/access posts. Review access rules manually after generation.', 'nes-calendar-memberships' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Publish Status', 'nes-calendar-memberships' ); ?></th>
			<td>
				<select name="post_status">
					<option value="draft"><?php esc_html_e( 'Draft first', 'nes-calendar-memberships' ); ?></option>
					<option value="publish"><?php esc_html_e( 'Publish immediately', 'nes-calendar-memberships' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Generate Memberships', 'nes-calendar-memberships' ) ); ?>
</form>
