<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Remove_Emoji extends POT_Module {
	protected string $name = 'Remove Emoji';
	protected string $description = 'Remove emoji scripts from the website.';
	protected string $category = 'performance';
	protected bool $default = true;

	public function load(): void {
		// Remove the inline emoji detection script from the frontend head
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );

		// Remove the emoji detection script from the admin area
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

		// Remove emoji CSS styles from the frontend
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		// Remove emoji CSS styles from the admin area
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		// Stop converting emoji in RSS feed content
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );

		// Stop converting emoji in RSS comment text
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

		// Stop converting emoji in outgoing emails
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		// Remove the DNS prefetch for the emoji CDN
		add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
			if ( 'dns-prefetch' === $relation_type ) {
				$urls = array_filter( $urls, function ( $url ) {
					return ! str_contains( $url, 'https://s.w.org/images/core/emoji/' );
				} );
			}

			return $urls;
		}, 10, 2 );

		// Prevent the emoji script from loading via the block editor
		add_filter( 'emoji_svg_url', '__return_false' );
	}
}
