<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Admin_Language_Switch extends POT_Module {
	protected string $name = 'Admin Language Switch';
	protected string $description = 'Adds a language switch button to the WP Admin bar.';
	protected string $category = 'admin';
	protected array $required_plugins = [ 'polylang/polylang.php', 'polylang-pro/polylang.php' ];
	protected bool $default = true;

	public function load(): void {
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_language_switch' ], 1000 );
	}

	public function admin_bar_language_switch( $admin_bar ): void {
		if ( function_exists( 'pll_the_languages' ) && is_singular() ) {
			$languages = pll_the_languages( [
				'hide_if_no_translation' => true,
				'hide_current'           => true,
				'raw'                    => true
			] );

			if ( ! empty( $languages ) ) {
				foreach ( $languages as $language ) {
					if ( $language['no_translation'] ) {
						continue;
					}

					$admin_bar->add_menu( [
						'id'    => 'adml_switch-' . $language['slug'],
						'title' => '<img src="' . $language['flag'] . '"/> ' . $language['name'],
						'href'  => $language['url'],
					] );
				}
			}
		}
	}
}
