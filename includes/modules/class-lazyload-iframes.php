<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Lazyload_Iframes extends POT_Module {
	protected string $name = 'Lazy Load Iframes';
	protected string $description = 'Defer loading offscreen iframes by adding loading=lazy attribute.';
	protected string $category = 'performance';
	protected bool $default = true;

	public function load(): void {
		add_filter( 'no_texturize_tags', [ $this, 'no_texturize_iframe' ] );
		add_filter( 'embed_oembed_html', [ $this, 'lazy_load_oembed' ], 10, 3 );
	}

	public function no_texturize_iframe( $tags ): array {
		$tags[] = 'iframe';

		return $tags;
	}

	public function lazy_load_oembed( $html, $url, $attr ): string {
		$html = str_replace( '<iframe ', '<iframe loading="lazy" ', $html );

		$video_data = $this->parse_oembed_uri( $url );
		if ( $video_data ) {
			$provider_id = $video_data['type'];
			$content_id  = $video_data['id'];

			if ( $provider_id == 'youtube' ) {
				$preview_url = "https://img.youtube.com/vi/$content_id/maxresdefault.jpg";
				if ( ! empty( $attr['preview'] ) ) {
					$preview_url = wp_get_attachment_url( $attr['preview'] );
				}

				$srcdoc = "<style>*{padding:0;margin:0;overflow:hidden}html,body{height:100%}img,span{position:absolute;width:100%;top:0;bottom:0;margin:auto}span{height:1.5em;text-align:center;font:48px/1.5 sans-serif;color:white;text-shadow:0 0 0.5em black}</style><a href='\${1}&autoplay=1'><img src='{$preview_url}' alt='Embedded YouTube video preview'><span>&#x25BA;</span></a>";
				$html   = str_replace( 'frameborder="0"', '', $html );
				$html   = preg_replace( '/src="(.*?)"/', 'srcdoc="' . $srcdoc . '"', $html );
			}

			if ( $provider_id == 'soundcloud' ) {
				$html = preg_replace( '/ width="\d+?"/', ' width="100%"', $html );
			}
		}

		return $html;
	}

	/**
	 * Parse the video uri/url to determine the video type/source and the video id
	 */
	private function parse_oembed_uri( $url ): ?array {
		$parse = parse_url( $url );

		$type = '';
		$id   = '';

		if ( $parse['host'] == 'soundcloud.com' ) {
			$type = 'soundcloud';
			$id   = ltrim( $parse['path'], '/' );
		}

		if ( $parse['host'] == 'youtu.be' ) {
			$type = 'youtube';
			$id   = ltrim( $parse['path'], '/' );
		}

		if ( ( $parse['host'] == 'youtube.com' ) || ( $parse['host'] == 'www.youtube.com' ) ) {
			$type = 'youtube';
			parse_str( $parse['query'], $vars );
			$id = $vars['v'];
			if ( ! empty( $vars['feature'] ) ) {
				$id = substr( $parse['query'], strrpos( $parse['query'], 'v=' ) + 2 );
			}
			if ( strpos( $parse['path'], 'embed' ) == 1 ) {
				$id = substr( $parse['path'], strrpos( $parse['path'], '/' ) + 1 );
			}
		}

		if ( ( $parse['host'] == 'vimeo.com' ) || ( $parse['host'] == 'www.vimeo.com' ) ) {
			$type = 'vimeo';
			$id   = ltrim( $parse['path'], '/' );
		}

		return empty( $type ) ? null : [ 'type' => $type, 'id' => $id ];
	}
}
