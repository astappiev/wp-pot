<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Defer_Scripts extends POT_Module {
	protected string $name = 'Defer Scripts';
	protected string $description = 'Defer loading of JS files (may cause compatibility issues with some plugins).';
	protected string $category = 'performance';
	protected bool $default = false;

	public function load(): void {
		add_filter( 'script_loader_tag', [ $this, 'defer_scripts' ], 10, 3 );
	}

	public function defer_scripts( $tag, $handle, $src ): string {
		if ( is_admin() ) {
			return $tag;
		}

		if ( ! str_contains( $src, '.js' ) ) {
			return $tag;
		}

		if ( $handle === 'wp-hooks' || $handle === 'wp-i18n' ) {
			return $tag;
		}

		return str_replace( '<script src', '<script defer src', $tag );
	}
}
