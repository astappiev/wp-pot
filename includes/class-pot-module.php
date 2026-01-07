<?php

namespace Pot;

defined( '\\ABSPATH' ) || exit;

abstract class POT_Module {
	protected string $name = '';
	protected string $description = '';
	protected string $category = 'general';
	protected bool $default = true;
	/**
	 * @var array List of required plugins in the format 'plugin-folder/plugin-file.php', at least one of which must be active for the module to load.
	 */
	protected array $required_plugins = [];

	/**
	 * Load the module - register hooks, filters, and actions
	 * Must be implemented by each module
	 */
	abstract public function load(): void;

	public function get_metadata(): array {
		return [
			'name'             => $this->name,
			'description'      => $this->description,
			'category'         => $this->category,
			'default'          => $this->default,
			'required_plugins' => $this->required_plugins,
		];
	}

	public function dependencies_met(): bool {
		if ( empty( $this->required_plugins ) ) {
			return true;
		}

		foreach ( $this->required_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return true;
			}
		}

		return false;
	}

	public function get_slug(): string {
		return sanitize_key( get_class( $this ) );
	}
}
