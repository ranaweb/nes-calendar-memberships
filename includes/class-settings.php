<?php
/**
 * Settings and admin shell.
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Settings {
	private NESCM_MemberPress_Adapter $adapter;
	private array $tabs = array();

	public function __construct( NESCM_MemberPress_Adapter $adapter ) {
		$this->adapter = $adapter;
	}

	public static function defaults(): array {
		return array(
			'cutoff_month'               => 9,
			'cutoff_day'                 => 1,
			'renewal_month'              => 1,
			'renewal_day'                => 1,
			'manual_payment_access_mode' => 'pending_until_complete',
			'enable_auto_renew_cta'      => 'no',
			'auto_renew_cta_url'         => '',
			'default_dashboard_page_id'  => 0,
			'debug_logging'              => 'no',
		);
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99 );
		add_action( 'admin_post_nescm_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_tab( string $slug, string $label, callable $callback ): void {
		$this->tabs[ $slug ] = array(
			'label'    => $label,
			'callback' => $callback,
		);
	}

	public function all(): array {
		$options = get_option( NESCM_OPTION, array() );
		return wp_parse_args( is_array( $options ) ? $options : array(), self::defaults() );
	}

	public function get( string $key, mixed $default = null ): mixed {
		$options = $this->all();
		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	public function add_menu(): void {
		$parent = $this->adapter->is_menu_available() ? 'memberpress' : 'options-general.php';

		add_submenu_page(
			$parent,
			__( 'NES Calendar Memberships', 'nes-calendar-memberships' ),
			__( 'NES Calendar Memberships', 'nes-calendar-memberships' ),
			nescm_admin_capability(),
			'nes-calendar-memberships',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'nes-calendar-memberships' ) ) {
			return;
		}

		wp_enqueue_style( 'nescm-admin', NESCM_URL . 'assets/css/admin.css', array(), NESCM_VERSION );
	}

	public function sanitize( array $input ): array {
		$defaults = self::defaults();
		$output   = $defaults;

		$output['cutoff_month']  = max( 1, min( 12, absint( $input['cutoff_month'] ?? $defaults['cutoff_month'] ) ) );
		$output['cutoff_day']    = max( 1, min( 31, absint( $input['cutoff_day'] ?? $defaults['cutoff_day'] ) ) );
		$output['renewal_month'] = max( 1, min( 12, absint( $input['renewal_month'] ?? $defaults['renewal_month'] ) ) );
		$output['renewal_day']   = max( 1, min( 31, absint( $input['renewal_day'] ?? $defaults['renewal_day'] ) ) );

		if ( ! checkdate( $output['cutoff_month'], $output['cutoff_day'], 2026 ) ) {
			$output['cutoff_month'] = $defaults['cutoff_month'];
			$output['cutoff_day']   = $defaults['cutoff_day'];
			nescm_add_admin_notice( __( 'Invalid cutoff date. The cutoff was reset to September 1.', 'nes-calendar-memberships' ), 'error' );
		}

		if ( ! checkdate( $output['renewal_month'], $output['renewal_day'], 2026 ) ) {
			$output['renewal_month'] = $defaults['renewal_month'];
			$output['renewal_day']   = $defaults['renewal_day'];
			nescm_add_admin_notice( __( 'Invalid renewal display date. The renewal date was reset to January 1.', 'nes-calendar-memberships' ), 'error' );
		}

		$access_mode = sanitize_key( (string) ( $input['manual_payment_access_mode'] ?? $defaults['manual_payment_access_mode'] ) );
		$output['manual_payment_access_mode'] = in_array( $access_mode, array( 'pending_until_complete', 'access_immediately' ), true ) ? $access_mode : $defaults['manual_payment_access_mode'];

		$output['enable_auto_renew_cta']     = isset( $input['enable_auto_renew_cta'] ) && 'yes' === $input['enable_auto_renew_cta'] ? 'yes' : 'no';
		$output['auto_renew_cta_url']        = isset( $input['auto_renew_cta_url'] ) ? esc_url_raw( (string) $input['auto_renew_cta_url'] ) : '';
		$output['default_dashboard_page_id'] = absint( $input['default_dashboard_page_id'] ?? 0 );
		$output['debug_logging']             = isset( $input['debug_logging'] ) && 'yes' === $input['debug_logging'] ? 'yes' : 'no';

		return $output;
	}

	public function save_settings(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage NES Calendar Memberships settings.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_save_settings' );

		$settings = isset( $_POST['nescm_settings'] ) && is_array( $_POST['nescm_settings'] )
			? wp_unslash( $_POST['nescm_settings'] )
			: array();

		update_option( NESCM_OPTION, $this->sanitize( $settings ) );
		nescm_add_admin_notice( __( 'NES Calendar Memberships settings saved.', 'nes-calendar-memberships' ), 'success' );

		wp_safe_redirect( nescm_admin_page_url( 'settings' ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage NES Calendar Memberships.', 'nes-calendar-memberships' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		if ( ! isset( $this->tabs[ $tab ] ) ) {
			$tab = 'settings';
		}

		echo '<div class="wrap nescm-admin">';
		echo '<h1>' . esc_html__( 'NES Calendar Memberships', 'nes-calendar-memberships' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';

		foreach ( $this->tabs as $slug => $data ) {
			$url   = nescm_admin_page_url( $slug );
			$class = $slug === $tab ? ' nav-tab-active' : '';
			printf( '<a class="nav-tab%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $data['label'] ) );
		}

		echo '</nav>';
		echo '<div class="nescm-tab-panel">';
		call_user_func( $this->tabs[ $tab ]['callback'] );
		echo '</div>';
		echo '</div>';
	}

	public function render_settings_tab(): void {
		$settings = $this->all();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nescm_save_settings" />
			<?php wp_nonce_field( 'nescm_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="nescm_cutoff_month"><?php esc_html_e( 'Next-Year Renewal Start', 'nes-calendar-memberships' ); ?></label></th>
					<td>
						<input id="nescm_cutoff_month" class="small-text" type="number" min="1" max="12" name="nescm_settings[cutoff_month]" value="<?php echo esc_attr( (string) $settings['cutoff_month'] ); ?>" />
						<input class="small-text" type="number" min="1" max="31" name="nescm_settings[cutoff_day]" value="<?php echo esc_attr( (string) $settings['cutoff_day'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Month and day when renewals begin routing to the next membership year. Default: September 1.', 'nes-calendar-memberships' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nescm_renewal_month"><?php esc_html_e( 'Renewal Display Date', 'nes-calendar-memberships' ); ?></label></th>
					<td>
						<input id="nescm_renewal_month" class="small-text" type="number" min="1" max="12" name="nescm_settings[renewal_month]" value="<?php echo esc_attr( (string) $settings['renewal_month'] ); ?>" />
						<input class="small-text" type="number" min="1" max="31" name="nescm_settings[renewal_day]" value="<?php echo esc_attr( (string) $settings['renewal_day'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Display/business anchor only in this phase. Default: January 1.', 'nes-calendar-memberships' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Manual Payment Access', 'nes-calendar-memberships' ); ?></th>
					<td>
						<select name="nescm_settings[manual_payment_access_mode]">
							<option value="pending_until_complete" <?php selected( $settings['manual_payment_access_mode'], 'pending_until_complete' ); ?>><?php esc_html_e( 'Pending until complete', 'nes-calendar-memberships' ); ?></option>
							<option value="access_immediately" <?php selected( $settings['manual_payment_access_mode'], 'access_immediately' ); ?>><?php esc_html_e( 'Access immediately', 'nes-calendar-memberships' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Renew CTA', 'nes-calendar-memberships' ); ?></th>
					<td>
						<label><input type="checkbox" name="nescm_settings[enable_auto_renew_cta]" value="yes" <?php checked( $settings['enable_auto_renew_cta'], 'yes' ); ?> /> <?php esc_html_e( 'Enable dashboard CTA', 'nes-calendar-memberships' ); ?></label>
						<p><input class="regular-text" type="url" name="nescm_settings[auto_renew_cta_url]" value="<?php echo esc_attr( $settings['auto_renew_cta_url'] ); ?>" placeholder="https://example.com/automatic-renewal" /></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nescm_dashboard_page"><?php esc_html_e( 'Dashboard Page ID', 'nes-calendar-memberships' ); ?></label></th>
					<td><input id="nescm_dashboard_page" class="small-text" type="number" min="0" name="nescm_settings[default_dashboard_page_id]" value="<?php echo esc_attr( (string) $settings['default_dashboard_page_id'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug Logging', 'nes-calendar-memberships' ); ?></th>
					<td><label><input type="checkbox" name="nescm_settings[debug_logging]" value="yes" <?php checked( $settings['debug_logging'], 'yes' ); ?> /> <?php esc_html_e( 'Log non-sensitive diagnostic data', 'nes-calendar-memberships' ); ?></label></td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'nes-calendar-memberships' ) ); ?>
		</form>
		<?php
	}
}
