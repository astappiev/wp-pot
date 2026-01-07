<?php

namespace Pot;

defined( '\\ABSPATH' ) || exit;

class Registry {
	private const string OPTION_KEY = 'wp_pot_modules';
	private array $modules = [];
	private array $module_states = [];

	public function __construct() {
		$this->module_states = get_option( self::OPTION_KEY, [] );
	}

	public function save_module_states(): bool {
		return update_option( self::OPTION_KEY, $this->module_states );
	}

	public function register_module( POT_Module|string ...$modules ): void {
		foreach ( $modules as $module ) {
			if ( is_string( $module ) ) {
				if ( ! class_exists( $module ) ) {
					continue;
				}
				$module = new $module();
				if ( ! $module instanceof POT_Module ) {
					continue;
				}
			}

			$slug                   = $module->get_slug();
			$this->modules[ $slug ] = $module;

			// Set default state if not already set
			if ( ! isset( $this->module_states[ $slug ] ) ) {
				$metadata                     = $module->get_metadata();
				$this->module_states[ $slug ] = $metadata['default'];
			}
		}
	}

	public function get_module( string $slug ): ?POT_Module {
		return $this->modules[ $slug ] ?? null;
	}

	public function is_module_enabled( string $slug ): bool {
		return $this->module_states[ $slug ] ?? false;
	}

	public function enable_module( string $slug ): bool {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return false;
		}

		$this->module_states[ $slug ] = true;

		return $this->save_module_states();
	}

	public function disable_module( string $slug ): bool {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return false;
		}

		$this->module_states[ $slug ] = false;

		return $this->save_module_states();
	}

	public function toggle_module( string $slug ): bool {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return false;
		}

		$this->module_states[ $slug ] = ! $this->is_module_enabled( $slug );

		return $this->save_module_states();
	}

	public function load_enabled_modules(): void {
		foreach ( $this->modules as $slug => $module ) {
			if ( $this->is_module_enabled( $slug ) && $module->dependencies_met() ) {
				$module->load();
			}
		}
	}

	public function get_modules_by_category(): array {
		$grouped = [];

		foreach ( $this->modules as $slug => $module ) {
			$metadata = $module->get_metadata();
			$category = $metadata['category'];

			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = [];
			}

			$grouped[ $category ][ $slug ] = [
				'module'           => $module,
				'metadata'         => $metadata,
				'enabled'          => $this->is_module_enabled( $slug ),
				'dependencies_met' => $module->dependencies_met(),
			];
		}

		return $grouped;
	}
}

