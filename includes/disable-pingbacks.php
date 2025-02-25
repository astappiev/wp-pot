<?php
/**
 * Module Name: Remove Pingbacks
 * Description: Disable all trackback and pingbacks in WordPress
 */

remove_action( 'do_all_pings', 'do_all_pingbacks' );
remove_action( 'do_all_pings', 'do_all_trackbacks' );

if (isset($_GET['doing_wp_cron'])) {
	remove_action('do_pings', 'do_all_pings');
	wp_clear_scheduled_hook('do_pings');
}
