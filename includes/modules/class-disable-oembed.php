<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Disable_Oembed extends POT_Module {
	protected string $name = 'Disable wp-embed script';
	protected string $description = 'Remove wp-embed.js script from your website. oEmbed functionality still works but you will not be able to embed other WordPress posts on your pages.';
	protected string $category = 'performance';
	protected bool $default = true;

	public function load(): void {
		add_action( 'wp_footer', [ $this, 'deregister_embed_script' ] );
	}

	public function deregister_embed_script(): void {
		wp_deregister_script( 'wp-embed' );
	}
}
