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
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}
}
