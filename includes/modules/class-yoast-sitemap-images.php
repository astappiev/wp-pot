<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Yoast_Sitemap_Images extends POT_Module {
	protected string $name = 'Yoast Sitemap Images';
	protected string $description = 'Adds all attached images to the Yoast SEO sitemap.';
	protected string $category = 'seo';
	protected array $required_plugins = [ 'wordpress-seo/wp-seo.php' ];
	protected bool $default = true;

	public function load(): void {
		add_filter( 'wpseo_sitemap_urlimages', [ $this, 'add_attached_images' ], 10, 2 );
		add_filter( 'wpseo_debug_markers', '__return_false' );
	}

	public function add_attached_images( $images, $post_id ): array {
		$attached_images = get_attached_media( 'image', $post_id );
		if ( $attached_images ) {
			foreach ( $attached_images as $attached_image ) {
				$image_arr        = [];
				$image_arr['src'] = $attached_image->guid;
				$images[]         = $image_arr;
			}
		}

		return array_unique( $images, SORT_REGULAR );
	}
}
