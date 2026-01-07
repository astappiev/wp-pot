<?php

namespace Pot;

defined( '\\ABSPATH' ) || exit;

class Admin_Page {
	private Registry $registry;

	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wp_pot_toggle_module', [ $this, 'ajax_toggle_module' ] );
	}

	public function add_menu_page(): void {
		add_options_page(
			__( 'P-o-T Modules', 'wp-pot' ),
			__( 'P-o-T', 'wp-pot' ),
			'manage_options',
			'wp-pot-modules',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ): void {
		if ( $hook !== 'settings_page_wp-pot-modules' ) {
			return;
		}

		wp_enqueue_style(
			'wp-pot-admin',
			plugins_url( '../assets/css/admin.css', __FILE__ ),
			[],
			WP_POT_VERSION
		);

		wp_enqueue_script(
			'wp-pot-admin',
			plugins_url( '../assets/js/admin.js', __FILE__ ),
			[ 'jquery' ],
			WP_POT_VERSION,
			true
		);

		wp_localize_script( 'wp-pot-admin', 'wpPotAdmin', [
			'nonce'   => wp_create_nonce( 'wp_pot_toggle_module' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		] );
	}

	public function ajax_toggle_module(): void {
		check_ajax_referer( 'wp_pot_toggle_module', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wp-pot' ) ] );
		}

		$slug = sanitize_key( $_POST['slug'] ?? '' );

		if ( empty( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid module', 'wp-pot' ) ] );
		}

		$module = $this->registry->get_module( $slug );

		if ( ! $module ) {
			wp_send_json_error( [ 'message' => __( 'Module not found', 'wp-pot' ) ] );
		}

		if ( ! $module->dependencies_met() ) {
			wp_send_json_error( [ 'message' => __( 'Module dependencies not met', 'wp-pot' ) ] );
		}

		$this->registry->toggle_module( $slug );
		$enabled = $this->registry->is_module_enabled( $slug );

		wp_send_json_success( [
			'enabled' => $enabled,
			'message' => $enabled
				? __( 'Module enabled', 'wp-pot' )
				: __( 'Module disabled', 'wp-pot' )
		] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$modules_by_category = $this->registry->get_modules_by_category();
		$category_labels     = $this->get_category_labels();

		?>
		<div class="wrap wp-pot-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Enable or disable performance optimization modules. Modules with unmet dependencies are shown but cannot be enabled.', 'wp-pot' ); ?></p>

			<?php foreach ( $modules_by_category as $category => $modules ): ?>
				<div class="wp-pot-category">
					<h2><?php echo esc_html( $category_labels[ $category ] ?? ucfirst( $category ) ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
						<tr>
							<th class="column-name"><?php esc_html_e( 'Module', 'wp-pot' ); ?></th>
							<th class="column-description"><?php esc_html_e( 'Description', 'wp-pot' ); ?></th>
							<th class="column-status"><?php esc_html_e( 'Status', 'wp-pot' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ( $modules as $slug => $data ): ?>
							<?php
							$metadata   = $data['metadata'];
							$enabled    = $data['enabled'];
							$deps_met   = $data['dependencies_met'];
							$can_toggle = $deps_met;
							?>
							<tr data-module="<?php echo esc_attr( $slug ); ?>">
								<td class="column-name">
									<strong><?php echo esc_html( $metadata['name'] ); ?></strong>
									<?php if ( ! empty( $metadata['required_plugins'] ) ): ?>
										<div class="wp-pot-dependencies">
											<small>
												<?php esc_html_e( 'Requires:', 'wp-pot' ); ?>
												<?php echo esc_html( implode( ', ', $metadata['required_plugins'] ) ); ?>
											</small>
										</div>
									<?php endif; ?>
								</td>
								<td class="column-description">
									<?php echo esc_html( $metadata['description'] ); ?>
									<?php if ( ! $deps_met ): ?>
										<div class="wp-pot-warning">
											<small style="color: #d63638;">
												<?php esc_html_e( '⚠ Dependencies not met. Required plugin(s) not active.', 'wp-pot' ); ?>
											</small>
										</div>
									<?php endif; ?>
								</td>
								<td class="column-status">
									<label class="wp-pot-toggle">
										<input
											type="checkbox"
											<?php checked( $enabled && $deps_met ); ?>
											<?php disabled( ! $can_toggle ); ?>
											data-slug="<?php echo esc_attr( $slug ); ?>"
										/>
										<span class="wp-pot-toggle-slider"></span>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function get_category_labels(): array {
		return [
			'performance' => __( 'Performance', 'wp-pot' ),
			'security'    => __( 'Security', 'wp-pot' ),
			'media'       => __( 'Media', 'wp-pot' ),
			'seo'         => __( 'SEO', 'wp-pot' ),
			'admin'       => __( 'Admin', 'wp-pot' ),
			'content'     => __( 'Content', 'wp-pot' ),
			'general'     => __( 'General', 'wp-pot' ),
		];
	}
}

