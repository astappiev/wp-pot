<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Clean_Head extends POT_Module {
	protected string $name = 'Clean Head';
	protected string $description = 'Removes generators and other meta tags from webpage head section.';
	protected string $category = 'performance';
	protected bool $default = true;

	public function load(): void {
		add_action( 'init', [ $this, 'clean_head' ], PHP_INT_MAX - 1 );
	}

	public function clean_head(): void {
		// Remove generator tags
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'perflab_render_generator' );
		remove_action( 'wp_head', 'webp_uploads_render_generator' );

		// Remove RDS link
		remove_action( 'wp_head', 'rsd_link' );
		// Remove Windows Live Writer manifest
		remove_action( 'wp_head', 'wlwmanifest_link' );
		// Disable comments feed
		add_filter( 'feed_links_show_comments_feed', '__return_false' );

		// Remove the generators from RSS feeds as well
		add_filter( 'the_generator', '__return_false' );
	}
}
