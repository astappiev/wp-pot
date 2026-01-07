<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Media_Replace extends POT_Module {
	protected string $name = 'Media Replace';
	protected string $description = 'Replace media files while maintaining the same attachment ID.';
	protected string $category = 'media';
	protected bool $default = true;

	public function load(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'edit_attachment', [ $this, 'edit_attachment' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields' ], 10, 2 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'calculate_image_srcset' ] );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'get_attachment_image_src' ] );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'prepare_attachment_for_js' ] );
	}

	public function enqueue_scripts(): void {
		wp_enqueue_script( 'wp-pot-media-replace', plugins_url( '../../assets/js/media-replace.js', __FILE__ ), [ 'jquery' ], WP_POT_VERSION, true );
	}

	public function edit_attachment( $postId ): bool {
		if ( empty( $_POST['replaceWith'] ) || ! is_numeric( $_POST['replaceWith'] ) ) {
			return false;
		}

		if ( empty( $_POST['replaceWithNonce'] ) || ! wp_verify_nonce( $_POST['replaceWithNonce'], 'pot_media_replace' ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return false;
		}

		$uploadDir = wp_upload_dir();
		$newFile   = $uploadDir['basedir'] . '/' . get_post_meta( $_POST['replaceWith'], '_wp_attached_file', true );

		if ( ! is_file( $newFile ) ) {
			return false;
		}

		$this->delete_attachment( $postId );

		$oldFile = $uploadDir['basedir'] . '/' . get_post_meta( $postId, '_wp_attached_file', true );
		if ( ! file_exists( dirname( $oldFile ) ) ) {
			wp_mkdir_p( dirname( $oldFile ) );
		}

		global $wp_filesystem;
		if ( WP_Filesystem() && $wp_filesystem->copy( $newFile, $oldFile ) ) {
			$meta = wp_generate_attachment_metadata( $postId, $oldFile );
			wp_update_attachment_metadata( $postId, $meta );

			if ( current_user_can( 'delete_post', $_POST['replaceWith'] ) ) {
				wp_delete_attachment( $_POST['replaceWith'], true );
			}
		}

		return true;
	}

	private function delete_attachment( $post_id ): void {
		$meta         = wp_get_attachment_metadata( $post_id );
		$backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
		$file         = get_attached_file( $post_id );

		if ( is_multisite() ) {
			delete_transient( 'dirsize_cache' );
		}

		$uploadpath = wp_get_upload_dir();

		if ( ! empty( $meta['thumb'] ) ) {
			$thumbfile = str_replace( basename( $file ), $meta['thumb'], $file );
			$thumbfile = apply_filters( 'wp_delete_file', $thumbfile );
			@unlink( path_join( $uploadpath['basedir'], $thumbfile ) );
		}

		if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				$intermediate_file = apply_filters( 'wp_delete_file', $intermediate_file );
				@unlink( path_join( $uploadpath['basedir'], $intermediate_file ) );
			}
		}

		if ( is_array( $backup_sizes ) ) {
			foreach ( $backup_sizes as $size ) {
				$del_file = path_join( dirname( $meta['file'] ), $size['file'] );
				$del_file = apply_filters( 'wp_delete_file', $del_file );
				@unlink( path_join( $uploadpath['basedir'], $del_file ) );
			}
		}

		wp_delete_file( $file );
	}

	public function attachment_fields( $fields, $attachment ): array {
		if ( current_user_can( 'edit_post', $attachment->ID ) ) {
			wp_enqueue_media();
			$fields['pot_image_replace'] = [
				'label' => '',
				'input' => 'html',
				'html'  => '
                    <button type="button" class="button-secondary button-large" onclick="pot_media_replace();">Replace Image</button>
                    <input type="hidden" id="pot_media_replace_with_fld" name="replaceWith" />
                    ' . wp_nonce_field( 'pot_media_replace', 'replaceWithNonce', false, false ) . '
                    <p><strong>Warning:</strong> Replacing this image with another one will permanently delete the current image file, and the replacement image will be moved to overwrite this one.</p>
                '
			];
		}

		return $fields;
	}

	public function calculate_image_srcset( $sources ): array {
		if ( is_admin() ) {
			foreach ( $sources as $size => $source ) {
				$source['url']    .= ( strpos( $source['url'], '?' ) === false ? '?' : '&' ) . '_t=' . time();
				$sources[ $size ] = $source;
			}
		}

		return $sources;
	}

	public function get_attachment_image_src( $attr ): array {
		if ( is_admin() && ! empty( $attr[0] ) ) {
			$attr[0] .= ( strpos( $attr[0], '?' ) === false ? '?' : '&' ) . '_t=' . time();
		}

		return $attr;
	}

	public function prepare_attachment_for_js( $response ): array {
		if ( is_admin() ) {
			if ( strpos( $response['url'], '?' ) !== false ) {
				$response['url'] .= ( strpos( $response['url'], '?' ) === false ? '?' : '&' ) . '_t=' . time();
			}
			if ( isset( $response['sizes'] ) ) {
				foreach ( $response['sizes'] as $sizeName => $size ) {
					$response['sizes'][ $sizeName ]['url'] .= ( strpos( $size['url'], '?' ) === false ? '?' : '&' ) . '_t=' . time();
				}
			}
		}

		return $response;
	}
}
