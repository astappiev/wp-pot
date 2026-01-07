<?php

namespace Pot\CLI;

use WP_CLI;

class Purge {

	/**
	 * Purge the Nginx cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pot-purge nginx
	 *
	 * @when after_wp_load
	 */
	public function nginx(): void {
		// Trigger the switch_theme action that nginx-cache plugin listens to
		do_action( 'switch_theme' );

		WP_CLI::success( 'Nginx cache purged.' );
	}
}
