<?php
/**
 * Module Name: Nginx Cache Flush
 * Description: Purge the Nginx cache (FastCGI, Proxy, uWSGI) from the command line.
 */

add_action( 'cli_init', function () {

	$args = array(
		'shortdesc' => 'Purge the Nginx cache',
	);

	WP_CLI::add_command( 'nginx-cache flush', function () {
		// Trigger the switch_theme action that nginx-cache plugin listens to
		do_action( 'switch_theme' );

		WP_CLI::success( 'Nginx cache purged.' );
	}, $args );

} );

