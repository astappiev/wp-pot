<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Disable_Author_Archives extends POT_Module {
	protected string $name = 'Disable Author Archives';
	protected string $description = 'Disable the author archives pages and returns a 404 error.';
	protected string $category = 'security';
	protected bool $default = true;

	public function load(): void {
		remove_filter( 'template_redirect', 'redirect_canonical' );
		add_action( 'template_redirect', [ $this, 'disable_author_archives' ] );
	}

	public function disable_author_archives(): void {
		if ( is_author() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		} else {
			redirect_canonical();
		}
	}
}
