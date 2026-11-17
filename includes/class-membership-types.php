<?php
/**
 * Admin-managed membership types (families).
 *
 * Lets NES add new membership families without editing the plugin. Built-in families
 * ship with the plugin and cannot be removed; custom families are stored in an option
 * and surfaced everywhere through nescm_get_families().
 *
 * @package NES_Calendar_Memberships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NESCM_Membership_Types {
	private NESCM_MemberPress_Adapter $adapter;

	public function __construct( NESCM_MemberPress_Adapter $adapter ) {
		$this->adapter = $adapter;
	}

	public function hooks(): void {
		add_action( 'admin_post_nescm_add_membership_type', array( $this, 'handle_add' ) );
		add_action( 'admin_post_nescm_delete_membership_type', array( $this, 'handle_delete' ) );
	}

	/**
	 * Derives a stable family key from a display name (e.g. "Student Membership" => "student_membership").
	 *
	 * @param string $name Human-readable membership type name.
	 * @return string Sanitized family key.
	 */
	public static function key_from_name( string $name ): string {
		return sanitize_key( str_replace( ' ', '_', strtolower( trim( $name ) ) ) );
	}

	public function render_tab(): void {
		$rows = array();
		foreach ( nescm_get_default_families() as $key => $label ) {
			$rows[] = array(
				'key'      => $key,
				'label'    => $label,
				'builtin'  => true,
				'in_use'   => $this->products_using_family( $key ),
			);
		}
		foreach ( nescm_get_custom_families() as $key => $label ) {
			$rows[] = array(
				'key'      => $key,
				'label'    => $label,
				'builtin'  => false,
				'in_use'   => $this->products_using_family( $key ),
			);
		}

		echo nescm_render_template( 'admin-membership-types.php', array( 'rows' => $rows ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes its own output.
	}

	public function handle_add(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage membership types.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_add_membership_type' );

		$name = isset( $_POST['nescm_type_name'] ) ? sanitize_text_field( wp_unslash( $_POST['nescm_type_name'] ) ) : '';
		$key  = isset( $_POST['nescm_type_key'] ) ? self::key_from_name( sanitize_text_field( wp_unslash( $_POST['nescm_type_key'] ) ) ) : '';

		if ( '' === $key ) {
			$key = self::key_from_name( $name );
		}

		if ( '' === $name || '' === $key ) {
			nescm_add_admin_notice( __( 'Enter a membership type name.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		if ( array_key_exists( $key, nescm_get_families() ) ) {
			nescm_add_admin_notice(
				sprintf(
					/* translators: %s: membership family key */
					__( 'A membership type with the key "%s" already exists. Choose a different name.', 'nes-calendar-memberships' ),
					$key
				),
				'error'
			);
			$this->redirect();
		}

		$custom         = nescm_get_custom_families();
		$custom[ $key ] = $name;
		update_option( NESCM_FAMILIES_OPTION, $custom );

		nescm_add_admin_notice(
			sprintf(
				/* translators: 1: membership type name, 2: family key */
				__( 'Added membership type "%1$s" (key: %2$s). Create its year-specific membership products next.', 'nes-calendar-memberships' ),
				$name,
				$key
			),
			'success'
		);
		$this->redirect();
	}

	public function handle_delete(): void {
		if ( ! current_user_can( nescm_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage membership types.', 'nes-calendar-memberships' ) );
		}

		check_admin_referer( 'nescm_delete_membership_type' );

		$key    = isset( $_POST['nescm_type_key'] ) ? sanitize_key( wp_unslash( $_POST['nescm_type_key'] ) ) : '';
		$custom = nescm_get_custom_families();

		if ( '' === $key || ! array_key_exists( $key, $custom ) ) {
			nescm_add_admin_notice( __( 'That membership type cannot be removed.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		if ( $this->products_using_family( $key ) > 0 ) {
			nescm_add_admin_notice( __( 'This membership type still has membership products assigned to it. Reassign or remove those products first.', 'nes-calendar-memberships' ), 'error' );
			$this->redirect();
		}

		unset( $custom[ $key ] );
		update_option( NESCM_FAMILIES_OPTION, $custom );

		nescm_add_admin_notice( __( 'Membership type removed.', 'nes-calendar-memberships' ), 'success' );
		$this->redirect();
	}

	private function products_using_family( string $key ): int {
		$ids = get_posts(
			array(
				'post_type'      => $this->adapter->get_membership_post_type(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_nescm_family_key',
						'value' => $key,
					),
				),
			)
		);

		return is_array( $ids ) ? count( $ids ) : 0;
	}

	private function redirect(): void {
		wp_safe_redirect( nescm_admin_page_url( 'membership-types' ) );
		exit;
	}
}
