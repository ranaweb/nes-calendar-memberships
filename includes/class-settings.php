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
			'cutoff_day'                 => 30,
			'renewal_month'              => 1,
			'renewal_day'                => 1,
			'test_date_override'         => '',
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
		$output['test_date_override'] = '';

		if ( ! checkdate( $output['cutoff_month'], $output['cutoff_day'], 2026 ) ) {
			$output['cutoff_month'] = $defaults['cutoff_month'];
			$output['cutoff_day']   = $defaults['cutoff_day'];
			nescm_add_admin_notice( __( 'Invalid cutoff date. The cutoff was reset to September 30.', 'nes-calendar-memberships' ), 'error' );
		}

		if ( ! checkdate( $output['renewal_month'], $output['renewal_day'], 2026 ) ) {
			$output['renewal_month'] = $defaults['renewal_month'];
			$output['renewal_day']   = $defaults['renewal_day'];
			nescm_add_admin_notice( __( 'Invalid renewal display date. The renewal date was reset to January 1.', 'nes-calendar-memberships' ), 'error' );
		}

		if ( ! empty( $input['test_date_override'] ) ) {
			$test_date = sanitize_text_field( (string) $input['test_date_override'] );
			if ( nescm_parse_date( $test_date ) ) {
				$output['test_date_override'] = $test_date;
			} else {
				nescm_add_admin_notice( __( 'Invalid test date override. The override was not saved.', 'nes-calendar-memberships' ), 'error' );
			}
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
		$this->render_test_date_banner();
		echo '<nav class="nav-tab-wrapper">';

		foreach ( $this->tabs as $slug => $data ) {
			$url   = nescm_admin_page_url( $slug );
			$class = $slug === $tab ? ' nav-tab-active' : '';
			printf( '<a class="nav-tab%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $data['label'] ) );
		}

		echo '</nav>';
		echo '<div class="nescm-tab-panel">';
		if ( ! $this->adapter->is_active() && in_array( $tab, array( 'manual-renewal', 'generate-year' ), true ) ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html__( 'NES Calendar Memberships requires MemberPress to be active. Please activate MemberPress before using NES membership tools.', 'nes-calendar-memberships' )
			);
		} else {
			call_user_func( $this->tabs[ $tab ]['callback'] );
		}
		echo '</div>';
		echo '</div>';
	}

	public function active_test_date(): array {
		if ( defined( 'NES_MEMBERSHIP_TEST_DATE' ) ) {
			$constant_date = (string) NES_MEMBERSHIP_TEST_DATE;
			if ( nescm_parse_date( $constant_date ) ) {
				return array(
					'date'   => $constant_date,
					'source' => 'constant',
				);
			}
		}

		$setting_date = (string) $this->get( 'test_date_override', '' );
		if ( $setting_date && nescm_parse_date( $setting_date ) ) {
			return array(
				'date'   => $setting_date,
				'source' => 'setting',
			);
		}

		return array(
			'date'   => '',
			'source' => '',
		);
	}

	private function render_test_date_banner(): void {
		$active = $this->active_test_date();
		if ( empty( $active['date'] ) ) {
			return;
		}

		$source = 'constant' === $active['source']
			? __( 'NES_MEMBERSHIP_TEST_DATE constant', 'nes-calendar-memberships' )
			: __( 'admin setting', 'nes-calendar-memberships' );

		printf(
			'<div class="notice notice-error nescm-test-date-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Test date override is active.', 'nes-calendar-memberships' ),
			esc_html(
				sprintf(
					/* translators: 1: date, 2: source */
					__( 'Calendar-year calculations are using %1$s from %2$s. Remove this before production use.', 'nes-calendar-memberships' ),
					$active['date'],
					$source
				)
			)
		);
	}

	public function render_settings_tab(): void {
		$settings = $this->all();
		$months   = nescm_months();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nescm_save_settings" />
			<?php wp_nonce_field( 'nescm_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Annual Cutoff Date', 'nes-calendar-memberships' ); ?></th>
					<td>
						<label for="nescm_cutoff_month"><?php esc_html_e( 'Cutoff Month', 'nes-calendar-memberships' ); ?></label>
						<select id="nescm_cutoff_month" name="nescm_settings[cutoff_month]">
							<?php foreach ( $months as $number => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $number ); ?>" <?php selected( (int) $settings['cutoff_month'], $number ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<label for="nescm_cutoff_day"><?php esc_html_e( 'Cutoff Day', 'nes-calendar-memberships' ); ?></label>
						<select id="nescm_cutoff_day" name="nescm_settings[cutoff_day]">
							<?php for ( $day = 1; $day <= 31; $day++ ) : ?>
								<option value="<?php echo esc_attr( (string) $day ); ?>" <?php selected( (int) $settings['cutoff_day'], $day ); ?>><?php echo esc_html( (string) $day ); ?></option>
							<?php endfor; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Renewals after this annual cutoff date are treated as next-year memberships. Default: September 30.', 'nes-calendar-memberships' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Renewal Display Date', 'nes-calendar-memberships' ); ?></th>
					<td>
						<label for="nescm_renewal_month"><?php esc_html_e( 'Renewal Month', 'nes-calendar-memberships' ); ?></label>
						<select id="nescm_renewal_month" name="nescm_settings[renewal_month]">
							<?php foreach ( $months as $number => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $number ); ?>" <?php selected( (int) $settings['renewal_month'], $number ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<label for="nescm_renewal_day"><?php esc_html_e( 'Renewal Day', 'nes-calendar-memberships' ); ?></label>
						<select id="nescm_renewal_day" name="nescm_settings[renewal_day]">
							<?php for ( $day = 1; $day <= 31; $day++ ) : ?>
								<option value="<?php echo esc_attr( (string) $day ); ?>" <?php selected( (int) $settings['renewal_day'], $day ); ?>><?php echo esc_html( (string) $day ); ?></option>
							<?php endfor; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Display/business anchor only in this phase. Default: January 1.', 'nes-calendar-memberships' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nescm_test_date_override"><?php esc_html_e( 'Test Date Override', 'nes-calendar-memberships' ); ?></label></th>
					<td>
						<input id="nescm_test_date_override" type="date" name="nescm_settings[test_date_override]" value="<?php echo esc_attr( $settings['test_date_override'] ); ?>" />
						<p class="description"><?php esc_html_e( 'For staging/testing only. Leave blank on production.', 'nes-calendar-memberships' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Check/Zelle Checkout Message', 'nes-calendar-memberships' ); ?></th>
					<td>
						<select name="nescm_settings[manual_payment_access_mode]">
							<option value="pending_until_complete" <?php selected( $settings['manual_payment_access_mode'], 'pending_until_complete' ); ?>><?php esc_html_e( 'Tell members their renewal completes after NES records payment', 'nes-calendar-memberships' ); ?></option>
							<option value="access_immediately" <?php selected( $settings['manual_payment_access_mode'], 'access_immediately' ); ?>><?php esc_html_e( 'Do not show a pending-payment message', 'nes-calendar-memberships' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Controls the wording members see at checkout only. It does not change when membership access is granted — offline payments are always activated by NES from the Offline Payments tab.', 'nes-calendar-memberships' ); ?></p>
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

	public function render_help_tab(): void {
		echo nescm_render_template( 'admin-howto.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes its own output.
	}
}
