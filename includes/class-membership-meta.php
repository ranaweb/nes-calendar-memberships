<?php
/**
 * NES metadata metabox for MemberPress memberships.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Membership_Meta {
	private NESCM_MemberPress_Adapter $adapter;
	private NESCM_Year_Calculator $calculator;

	private array $meta_keys = array(
		'_nescm_enabled',
		'_nescm_family_key',
		'_nescm_public_label',
		'_nescm_membership_year',
		'_nescm_valid_from',
		'_nescm_valid_through',
		'_nescm_sales_window_start',
		'_nescm_sales_window_end',
		'_nescm_renewal_method_type',
		'_nescm_next_year_membership_id',
		'_nescm_auto_renew_membership_id',
		'_nescm_manual_membership_id',
		'_nescm_checkout_context_label',
	);

	public function __construct( NESCM_MemberPress_Adapter $adapter, NESCM_Year_Calculator $calculator ) {
		$this->adapter    = $adapter;
		$this->calculator = $calculator;
	}

	public function hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post_' . $this->adapter->get_membership_post_type(), array( $this, 'save' ), 10, 2 );
	}

	public function add_metabox(): void {
		add_meta_box(
			'nescm_membership_rules',
			__( 'NES Calendar Membership Rules', 'nes-calendar-memberships' ),
			array( $this, 'render' ),
			$this->adapter->get_membership_post_type(),
			'normal',
			'high'
		);
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( 'nescm_save_membership_meta', 'nescm_membership_meta_nonce' );

		$values  = $this->values( $post->ID );
		$families = nescm_get_families();
		$methods  = nescm_get_renewal_method_types();
		?>
		<table class="form-table nescm-metabox" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable NES Calendar Rules', 'nes-calendar-memberships' ); ?></th>
				<td><label><input type="checkbox" name="nescm_meta[_nescm_enabled]" value="yes" <?php checked( $values['_nescm_enabled'], 'yes' ); ?> /> <?php esc_html_e( 'Use calendar-year rules for this membership', 'nes-calendar-memberships' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="nescm_family_key"><?php esc_html_e( 'Membership Family Key', 'nes-calendar-memberships' ); ?></label></th>
				<td>
					<select id="nescm_family_key" name="nescm_meta[_nescm_family_key]">
						<option value=""><?php esc_html_e( 'Select family', 'nes-calendar-memberships' ); ?></option>
						<?php foreach ( $families as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['_nescm_family_key'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nescm_public_label"><?php esc_html_e( 'Public Label', 'nes-calendar-memberships' ); ?></label></th>
				<td><input id="nescm_public_label" class="regular-text" type="text" name="nescm_meta[_nescm_public_label]" value="<?php echo esc_attr( $values['_nescm_public_label'] ); ?>" placeholder="<?php esc_attr_e( 'Resident Membership', 'nes-calendar-memberships' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="nescm_membership_year"><?php esc_html_e( 'Membership Year', 'nes-calendar-memberships' ); ?></label></th>
				<td><input id="nescm_membership_year" class="small-text" type="number" min="2000" max="2100" name="nescm_meta[_nescm_membership_year]" value="<?php echo esc_attr( $values['_nescm_membership_year'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Validity Dates', 'nes-calendar-memberships' ); ?></th>
				<td>
					<label><?php esc_html_e( 'From', 'nes-calendar-memberships' ); ?> <input type="date" name="nescm_meta[_nescm_valid_from]" value="<?php echo esc_attr( $values['_nescm_valid_from'] ); ?>" /></label>
					<label><?php esc_html_e( 'Through', 'nes-calendar-memberships' ); ?> <input type="date" name="nescm_meta[_nescm_valid_through]" value="<?php echo esc_attr( $values['_nescm_valid_through'] ); ?>" /></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sales Window', 'nes-calendar-memberships' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Start', 'nes-calendar-memberships' ); ?> <input type="date" name="nescm_meta[_nescm_sales_window_start]" value="<?php echo esc_attr( $values['_nescm_sales_window_start'] ); ?>" /></label>
					<label><?php esc_html_e( 'End', 'nes-calendar-memberships' ); ?> <input type="date" name="nescm_meta[_nescm_sales_window_end]" value="<?php echo esc_attr( $values['_nescm_sales_window_end'] ); ?>" /></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nescm_method_type"><?php esc_html_e( 'Renewal Method Type', 'nes-calendar-memberships' ); ?></label></th>
				<td>
					<select id="nescm_method_type" name="nescm_meta[_nescm_renewal_method_type]">
						<?php foreach ( $methods as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['_nescm_renewal_method_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Related Membership IDs', 'nes-calendar-memberships' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Next Year', 'nes-calendar-memberships' ); ?> <input class="small-text" type="number" min="0" name="nescm_meta[_nescm_next_year_membership_id]" value="<?php echo esc_attr( $values['_nescm_next_year_membership_id'] ); ?>" /></label>
					<label><?php esc_html_e( 'Auto-Renew', 'nes-calendar-memberships' ); ?> <input class="small-text" type="number" min="0" name="nescm_meta[_nescm_auto_renew_membership_id]" value="<?php echo esc_attr( $values['_nescm_auto_renew_membership_id'] ); ?>" /></label>
					<label><?php esc_html_e( 'Manual', 'nes-calendar-memberships' ); ?> <input class="small-text" type="number" min="0" name="nescm_meta[_nescm_manual_membership_id]" value="<?php echo esc_attr( $values['_nescm_manual_membership_id'] ); ?>" /></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nescm_context_label"><?php esc_html_e( 'Checkout Context Label', 'nes-calendar-memberships' ); ?></label></th>
				<td><input id="nescm_context_label" class="regular-text" type="text" name="nescm_meta[_nescm_checkout_context_label]" value="<?php echo esc_attr( $values['_nescm_checkout_context_label'] ); ?>" placeholder="<?php esc_attr_e( '2027 Resident Membership', 'nes-calendar-memberships' ); ?>" /></td>
			</tr>
		</table>
		<?php
	}

	public function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( ! isset( $_POST['nescm_membership_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nescm_membership_meta_nonce'] ) ), 'nescm_save_membership_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw  = isset( $_POST['nescm_meta'] ) && is_array( $_POST['nescm_meta'] ) ? wp_unslash( $_POST['nescm_meta'] ) : array();
		$data = $this->sanitize_meta( $raw, $post_id );

		foreach ( $this->meta_keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				update_post_meta( $post_id, $key, $data[ $key ] );
			}
		}

		if ( 'yes' === $data['_nescm_enabled'] && ! empty( $data['_nescm_valid_through'] ) ) {
			$this->adapter->set_product_fixed_expiration( $post_id, $data['_nescm_valid_through'] );
		}
	}

	public function values( int $post_id ): array {
		$values = array();

		foreach ( $this->meta_keys as $key ) {
			$values[ $key ] = (string) get_post_meta( $post_id, $key, true );
		}

		$values['_nescm_enabled']             = $values['_nescm_enabled'] ?: 'no';
		$values['_nescm_renewal_method_type'] = $values['_nescm_renewal_method_type'] ?: 'manual';

		return $values;
	}

	private function sanitize_meta( array $raw, int $post_id ): array {
		$data = array_fill_keys( $this->meta_keys, '' );

		$data['_nescm_enabled'] = isset( $raw['_nescm_enabled'] ) && 'yes' === $raw['_nescm_enabled'] ? 'yes' : 'no';

		$family = sanitize_key( (string) ( $raw['_nescm_family_key'] ?? '' ) );
		if ( $family && ! nescm_is_valid_family_key( $family ) ) {
			nescm_add_admin_notice( __( 'Invalid NES family key. The family key was not saved.', 'nes-calendar-memberships' ), 'error' );
			$family = '';
		}
		$data['_nescm_family_key'] = $family;

		$data['_nescm_public_label']           = sanitize_text_field( (string) ( $raw['_nescm_public_label'] ?? '' ) );
		$data['_nescm_checkout_context_label'] = sanitize_text_field( (string) ( $raw['_nescm_checkout_context_label'] ?? '' ) );

		$year = absint( $raw['_nescm_membership_year'] ?? 0 );
		if ( $year > 0 && ( $year < 2000 || $year > 2100 ) ) {
			nescm_add_admin_notice( __( 'Membership year must be a four-digit year between 2000 and 2100.', 'nes-calendar-memberships' ), 'error' );
			$year = 0;
		}
		$data['_nescm_membership_year'] = $year > 0 ? (string) $year : '';

		if ( $year > 0 ) {
			$data['_nescm_valid_from']         = $this->calculator->get_valid_from_for_year( $year )->format( 'Y-m-d' );
			$data['_nescm_valid_through']      = $this->calculator->get_valid_through_for_year( $year )->format( 'Y-m-d' );
			$data['_nescm_sales_window_start'] = $this->calculator->get_sales_window_start_for_year( $year )->format( 'Y-m-d' );
			$data['_nescm_sales_window_end']   = $this->calculator->get_sales_window_end_for_year( $year )->format( 'Y-m-d' );
		}

		foreach ( array( '_nescm_valid_from', '_nescm_valid_through', '_nescm_sales_window_start', '_nescm_sales_window_end' ) as $date_key ) {
			if ( ! empty( $raw[ $date_key ] ) ) {
				$date = sanitize_text_field( (string) $raw[ $date_key ] );
				if ( nescm_parse_date( $date ) ) {
					$data[ $date_key ] = $date;
				} else {
					nescm_add_admin_notice( sprintf( __( 'Invalid date for %s. The invalid date was not saved.', 'nes-calendar-memberships' ), $date_key ), 'error' );
				}
			}
		}

		$method = sanitize_key( (string) ( $raw['_nescm_renewal_method_type'] ?? 'manual' ) );
		$data['_nescm_renewal_method_type'] = array_key_exists( $method, nescm_get_renewal_method_types() ) ? $method : 'manual';

		foreach ( array( '_nescm_next_year_membership_id', '_nescm_auto_renew_membership_id', '_nescm_manual_membership_id' ) as $id_key ) {
			$id = absint( $raw[ $id_key ] ?? 0 );
			if ( $id > 0 && ! $this->adapter->is_membership_post( $id ) ) {
				nescm_add_admin_notice( __( 'A related membership ID was not a valid MemberPress membership and was not saved.', 'nes-calendar-memberships' ), 'error' );
				$id = 0;
			}
			$data[ $id_key ] = $id > 0 ? (string) $id : '';
		}

		if ( 'yes' === $data['_nescm_enabled'] ) {
			if ( '' === $data['_nescm_family_key'] ) {
				nescm_add_admin_notice( __( 'NES-enabled memberships require a family key.', 'nes-calendar-memberships' ), 'error' );
			}
			if ( '' === $data['_nescm_membership_year'] && 'manual' === $data['_nescm_renewal_method_type'] ) {
				nescm_add_admin_notice( __( 'Manual NES memberships require a membership year.', 'nes-calendar-memberships' ), 'error' );
			}
		}

		return $data;
	}
}
