<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Media_Meta extends POT_Module {
	protected string $name = 'Media Extra Info';
	protected string $description = 'Adds file size and dimensions columns to the Media library.';
	protected string $category = 'media';
	protected bool $default = true;

	public function load(): void {
		add_filter( 'manage_media_columns', [ $this, 'add_columns' ] );
		add_action( 'manage_media_custom_column', [ $this, 'display_columns' ], 10, 2 );
		add_action( 'admin_print_styles-upload.php', [ $this, 'column_styles' ] );
	}

	public function add_columns( $posts_columns ): array {
		$posts_columns['filesize']   = __( 'File Size', 'wp-pot' );
		$posts_columns['dimensions'] = __( 'Dimensions', 'wp-pot' );

		return $posts_columns;
	}

	public function display_columns( $column_name, $post_id ): void {
		if ( $column_name === 'filesize' ) {
			$bytes = filesize( get_attached_file( $post_id ) );
			echo size_format( $bytes, 2 );
		}

		if ( $column_name === 'dimensions' ) {
			$metadata = wp_get_attachment_metadata( $post_id );

			if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
				echo esc_html( $metadata['width'] . ' × ' . $metadata['height'] );
			} else {
				echo '–';
			}
		}
	}

	public function column_styles(): void {
		echo '<style>
            .fixed .column-filesize {
                width: 10%;
                max-width: 80px;
            }
            .fixed .column-dimensions {
                width: 10%;
                max-width: 80px;
            }
        </style>';
	}
}
