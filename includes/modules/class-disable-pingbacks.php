<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Disable_Pingbacks extends POT_Module {
	protected string $name = 'Disable Pingbacks';
	protected string $description = 'Disable all trackback and pingbacks in WordPress.';
	protected string $category = 'performance';
	protected bool $default = true;

	public function load(): void {
		remove_action( 'do_all_pings', 'do_all_pingbacks' );
		remove_action( 'do_all_pings', 'do_all_trackbacks' );

		if ( isset( $_GET['doing_wp_cron'] ) ) {
			remove_action( 'do_pings', 'do_all_pings' );
			wp_clear_scheduled_hook( 'do_pings' );
		}
	}
}
