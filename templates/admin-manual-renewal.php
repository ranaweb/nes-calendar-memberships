<?php
/**
 * Offline payment/manual renewal admin template.
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
		'preview'           => array(),
	)
);

$preview_config = array(
	'cutoffMonth' => (int) $settings['cutoff_month'],
	'cutoffDay'   => (int) $settings['cutoff_day'],
	'families'    => $families,
	'products'    => $product_map,
);
?>
<h2><?php esc_html_e( 'Pending Offline Payments', 'nes-calendar-memberships' ); ?></h2>
<p><?php esc_html_e( 'Review pending cheque/Zelle/offline MemberPress transactions for NES memberships, then complete and activate them when payment has been received.', 'nes-calendar-memberships' ); ?></p>

<?php if ( ! empty( $review ) ) : ?>
	<div class="nescm-review-panel">
		<h3><?php esc_html_e( 'Review Pending Offline Payment', 'nes-calendar-memberships' ); ?></h3>
		<div class="nescm-review-grid">
			<p><strong><?php esc_html_e( 'Member', 'nes-calendar-memberships' ); ?></strong><br /><?php echo esc_html( $review['member'] ); ?></p>
			<p><strong><?php esc_html_e( 'Email', 'nes-calendar-memberships' ); ?></strong><br /><?php echo esc_html( $review['email'] ); ?></p>
			<p><strong><?php esc_html_e( 'Pending Transaction ID', 'nes-calendar-memberships' ); ?></strong><br />#<?php echo esc_html( (string) $review['id'] ); ?></p>
			<p><strong><?php esc_html_e( 'Existing Membership Status', 'nes-calendar-memberships' ); ?></strong><br /><?php echo esc_html( $review['existing_status'] ); ?></p>
			<p><strong><?php esc_html_e( 'Membership Type / Family', 'nes-calendar-memberships' ); ?></strong><br /><?php echo esc_html( $review['family_label'] ); ?></p>
			<p><strong><?php esc_html_e( 'Payment Method', 'nes-calendar-memberships' ); ?></strong><br /><?php echo esc_html( $review['payment_method'] ); ?></p>
			<p><strong><?php esc_html_e( 'Amount', 'nes-calendar-memberships' ); ?></strong><br /><?php echo esc_html( number_format_i18n( (float) $review['amount'], 2 ) ); ?></p>
		</div>

		<?php if ( ! empty( $review['duplicate'] ) ) : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Duplicate warning:', 'nes-calendar-memberships' ); ?></strong> <?php esc_html_e( 'This member already appears to have a completed renewal for the same family and membership year.', 'nes-calendar-memberships' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $review['preview'] ) ) : ?>
			<div class="nescm-preview-panel">
				<h4><?php esc_html_e( 'Calculation Preview', 'nes-calendar-memberships' ); ?></h4>
				<ul>
					<li><?php /* translators: %s: payment received date. */ printf( esc_html__( 'Payment received: %s', 'nes-calendar-memberships' ), esc_html( $review['preview']['payment_date_display'] ) ); ?></li>
					<li><?php /* translators: %s: annual cutoff date. */ printf( esc_html__( 'Cutoff: %s', 'nes-calendar-memberships' ), esc_html( $review['preview']['cutoff_date_display'] ) ); ?></li>
					<li><?php /* translators: %s: cutoff result label. */ printf( esc_html__( 'Result: %s', 'nes-calendar-memberships' ), esc_html( $review['preview']['result_label'] ) ); ?></li>
					<li><?php /* translators: %s: membership year. */ printf( esc_html__( 'Membership year paid for: %s', 'nes-calendar-memberships' ), esc_html( (string) $review['preview']['year'] ) ); ?></li>
					<li><?php /* translators: %s: valid-through date. */ printf( esc_html__( 'Valid through: %s', 'nes-calendar-memberships' ), esc_html( $review['preview']['valid_through_display'] ) ); ?></li>
					<li><?php /* translators: %s: target membership product title. */ printf( esc_html__( 'Target MemberPress membership: %s', 'nes-calendar-memberships' ), esc_html( $review['preview']['target_title'] ) ); ?></li>
				</ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nescm-review-form">
			<input type="hidden" name="action" value="nescm_complete_offline_payment" />
			<input type="hidden" name="transaction_id" value="<?php echo esc_attr( (string) $review['id'] ); ?>" />
			<?php wp_nonce_field( 'nescm_complete_offline_payment' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="nescm_review_payment_date"><?php esc_html_e( 'Payment Received Date', 'nes-calendar-memberships' ); ?></label></th>
					<td><input id="nescm_review_payment_date" type="date" name="payment_received_date" value="<?php echo esc_attr( $review['payment_received_date'] ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="nescm_review_reference"><?php esc_html_e( 'Check/Zelle Reference', 'nes-calendar-memberships' ); ?></label></th>
					<td><input id="nescm_review_reference" class="regular-text" type="text" name="reference" value="<?php echo esc_attr( $review['reference'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="nescm_review_note"><?php esc_html_e( 'Admin Note', 'nes-calendar-memberships' ); ?></label></th>
					<td><textarea id="nescm_review_note" class="large-text" rows="3" name="admin_note"><?php echo esc_textarea( $review['admin_note'] ); ?></textarea></td>
				</tr>
				<?php if ( ! empty( $review['duplicate'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Duplicate Override', 'nes-calendar-memberships' ); ?></th>
						<td><label><input type="checkbox" name="confirm_duplicate" value="1" required /> <?php esc_html_e( 'I understand this may create a duplicate completed renewal.', 'nes-calendar-memberships' ); ?></label></td>
					</tr>
				<?php endif; ?>
			</table>

			<?php submit_button( __( 'Complete & Activate', 'nes-calendar-memberships' ) ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nescm-void-form">
			<input type="hidden" name="action" value="nescm_void_offline_payment" />
			<input type="hidden" name="transaction_id" value="<?php echo esc_attr( (string) $review['id'] ); ?>" />
			<input type="hidden" name="admin_note" value="<?php echo esc_attr( $review['admin_note'] ); ?>" />
			<?php wp_nonce_field( 'nescm_void_offline_payment' ); ?>
			<?php submit_button( __( 'Cancel / Void', 'nes-calendar-memberships' ), 'delete', 'submit', false ); ?>
		</form>
	</div>
<?php endif; ?>

<table class="widefat striped nescm-pending-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Member', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Email', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Membership / Family', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Payment Method', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Amount', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Payment Date / Created Date', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Calculated Membership Year', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Valid Through', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Status', 'nes-calendar-memberships' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'nes-calendar-memberships' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $pending_transactions ) ) : ?>
			<tr><td colspan="10"><?php esc_html_e( 'No pending NES offline payments found.', 'nes-calendar-memberships' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $pending_transactions as $transaction ) : ?>
			<tr>
				<td><?php echo esc_html( $transaction['member'] ); ?></td>
				<td><?php echo esc_html( $transaction['email'] ); ?></td>
				<td><?php echo esc_html( $transaction['product_title'] . ' / ' . $transaction['family_label'] ); ?></td>
				<td><?php echo esc_html( $transaction['payment_method'] ); ?></td>
				<td><?php echo esc_html( number_format_i18n( (float) $transaction['amount'], 2 ) ); ?></td>
				<td><?php echo esc_html( $transaction['created_date'] ); ?></td>
				<td><?php echo esc_html( ! empty( $transaction['preview']['year'] ) ? (string) $transaction['preview']['year'] : '-' ); ?></td>
				<td><?php echo esc_html( ! empty( $transaction['preview']['valid_through_display'] ) ? $transaction['preview']['valid_through_display'] : '-' ); ?></td>
				<td><?php echo esc_html( $transaction['status'] ); ?></td>
				<td>
					<a class="button button-small" href="<?php echo esc_url( $transaction['review_url'] ); ?>"><?php esc_html_e( 'Review', 'nes-calendar-memberships' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( $transaction['review_url'] ); ?>#complete"><?php esc_html_e( 'Complete & Activate', 'nes-calendar-memberships' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nescm-inline-form">
						<input type="hidden" name="action" value="nescm_void_offline_payment" />
						<input type="hidden" name="transaction_id" value="<?php echo esc_attr( (string) $transaction['id'] ); ?>" />
						<?php wp_nonce_field( 'nescm_void_offline_payment' ); ?>
						<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Cancel / Void', 'nes-calendar-memberships' ); ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<hr />

<h2><?php esc_html_e( 'Create Manual Renewal Manually', 'nes-calendar-memberships' ); ?></h2>
<p><?php esc_html_e( 'Use this only if the member did not complete an online cheque/Zelle checkout and you need to record a payment received outside the website.', 'nes-calendar-memberships' ); ?></p>

<?php if ( ! empty( $pending['preview'] ) ) : ?>
	<div class="notice notice-warning inline">
		<p><strong><?php esc_html_e( 'Duplicate warning:', 'nes-calendar-memberships' ); ?></strong> <?php esc_html_e( 'This member already appears to have a completed transaction for that family and membership year. Continue only if this is intentional.', 'nes-calendar-memberships' ); ?></p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nescm-manual-renewal-form">
	<input type="hidden" name="action" value="nescm_create_manual_renewal" />
	<?php wp_nonce_field( 'nescm_create_manual_renewal' ); ?>
	<?php if ( ! empty( $pending['preview'] ) ) : ?>
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
			<th scope="row"><label for="nescm_manual_family"><?php esc_html_e( 'Membership Type / Family', 'nes-calendar-memberships' ); ?></label></th>
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
			<th scope="row"><label for="nescm_payment_date"><?php esc_html_e( 'Payment Received Date', 'nes-calendar-memberships' ); ?></label></th>
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
			<th scope="row"><label for="nescm_reference"><?php esc_html_e( 'Check/Zelle Reference', 'nes-calendar-memberships' ); ?></label></th>
			<td><input id="nescm_reference" class="regular-text" type="text" name="nescm_manual[reference]" value="<?php echo esc_attr( $values['reference'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="nescm_note"><?php esc_html_e( 'Admin Note', 'nes-calendar-memberships' ); ?></label></th>
			<td><textarea id="nescm_note" class="large-text" rows="3" name="nescm_manual[note]"><?php echo esc_textarea( $values['note'] ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Create Transaction As', 'nes-calendar-memberships' ); ?></th>
			<td>
				<select id="nescm_manual_status" name="nescm_manual[status]">
					<option value="pending" <?php selected( $values['status'], 'pending' ); ?>><?php esc_html_e( 'Pending Manual Renewal', 'nes-calendar-memberships' ); ?></option>
					<option value="complete" <?php selected( $values['status'], 'complete' ); ?>><?php esc_html_e( 'Completed Manual Renewal', 'nes-calendar-memberships' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<div id="nescm-manual-preview" class="nescm-preview-panel" aria-live="polite"></div>

	<?php submit_button( empty( $pending['preview'] ) ? __( 'Create Manual Renewal', 'nes-calendar-memberships' ) : __( 'Create Duplicate Manual Renewal', 'nes-calendar-memberships' ) ); ?>
</form>

<script>
window.NESCMManualRenewal = <?php echo wp_json_encode( $preview_config ); ?>;
(function () {
	const config = window.NESCMManualRenewal || {};
	const form = document.getElementById('nescm-manual-renewal-form');
	const preview = document.getElementById('nescm-manual-preview');
	if (!form || !preview) {
		return;
	}
	const family = document.getElementById('nescm_manual_family');
	const paymentDate = document.getElementById('nescm_payment_date');
	const status = document.getElementById('nescm_manual_status');
	function formatDate(date) {
		return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
	}
	function parseDate(value) {
		const parts = String(value || '').split('-').map(Number);
		if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) {
			return null;
		}
		return new Date(parts[0], parts[1] - 1, parts[2]);
	}
	function esc(value) {
		return String(value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function renderPreview() {
		const date = parseDate(paymentDate.value);
		const familyKey = family.value;
		if (!date || !familyKey) {
			preview.innerHTML = '<h4><?php echo esc_js( __( 'Calculation Preview', 'nes-calendar-memberships' ) ); ?></h4><p><?php echo esc_js( __( 'Choose a membership family and payment received date to preview the calculated renewal.', 'nes-calendar-memberships' ) ); ?></p>';
			return;
		}
		const cutoff = new Date(date.getFullYear(), Number(config.cutoffMonth || 9) - 1, Number(config.cutoffDay || 30));
		const afterCutoff = date > cutoff;
		const year = afterCutoff ? date.getFullYear() + 1 : date.getFullYear();
		const validThrough = new Date(year, 11, 31);
		const product = (((config.products || {})[familyKey] || {})[String(year)] || null);
		const familyLabel = (config.families || {})[familyKey] || familyKey;
		preview.innerHTML = '<h4><?php echo esc_js( __( 'Calculation Preview', 'nes-calendar-memberships' ) ); ?></h4>' +
			'<ul>' +
			'<li><?php echo esc_js( __( 'Payment received:', 'nes-calendar-memberships' ) ); ?> ' + formatDate(date) + '</li>' +
			'<li><?php echo esc_js( __( 'Cutoff:', 'nes-calendar-memberships' ) ); ?> ' + formatDate(cutoff) + '</li>' +
			'<li><?php echo esc_js( __( 'Result:', 'nes-calendar-memberships' ) ); ?> ' + (afterCutoff ? '<?php echo esc_js( __( 'After cutoff', 'nes-calendar-memberships' ) ); ?>' : '<?php echo esc_js( __( 'On or before cutoff', 'nes-calendar-memberships' ) ); ?>') + '</li>' +
			'<li><?php echo esc_js( __( 'Membership family:', 'nes-calendar-memberships' ) ); ?> ' + esc(familyLabel) + '</li>' +
			'<li><?php echo esc_js( __( 'Membership year paid for:', 'nes-calendar-memberships' ) ); ?> ' + year + '</li>' +
			'<li><?php echo esc_js( __( 'Valid through:', 'nes-calendar-memberships' ) ); ?> ' + formatDate(validThrough) + '</li>' +
			'<li><?php echo esc_js( __( 'Target membership product:', 'nes-calendar-memberships' ) ); ?> ' + (product ? esc(product.title) + ' (#' + esc(product.id) + ', ' + esc(product.status) + ')' : '<?php echo esc_js( __( 'Missing target membership', 'nes-calendar-memberships' ) ); ?>') + '</li>' +
			'<li><?php echo esc_js( __( 'Status that will be created:', 'nes-calendar-memberships' ) ); ?> ' + esc(status.options[status.selectedIndex].text) + '</li>' +
			'</ul>';
	}
	family.addEventListener('change', renderPreview);
	paymentDate.addEventListener('change', renderPreview);
	status.addEventListener('change', renderPreview);
	renderPreview();
}());
</script>
