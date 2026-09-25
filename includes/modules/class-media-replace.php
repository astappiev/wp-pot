<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Media_Replace extends POT_Module {
	private const string VERSION_META_KEY = '_pot_media_replaced';

	protected string $name = 'Media Replace';
	protected string $description = 'Replace media files while maintaining the same attachment ID.';
	protected string $category = 'media';
	protected bool $default = true;

	public function load(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'edit_attachment', [ $this, 'edit_attachment' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields' ], 10, 2 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'calculate_image_srcset' ], 10, 5 );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'get_attachment_image_src' ], 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'prepare_attachment_for_js' ] );
	}

	public function enqueue_scripts(): void {
		wp_enqueue_script( 'wp-pot-media-replace', plugins_url( '../../assets/js/media-replace.js', __FILE__ ), [ 'jquery' ], WP_POT_VERSION, true );
	}

	public function edit_attachment( $post_id ): bool {
		$replace_id = absint( $_POST['replaceWith'] ?? 0 );
		if ( ! $replace_id || $replace_id === $post_id ) {
			return false;
		}

		if ( empty( $_POST['replaceWithNonce'] ) || ! wp_verify_nonce( $_POST['replaceWithNonce'], 'pot_media_replace' ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $replace_id ) !== 'attachment' ) {
			return false;
		}

		// Use unscaled originals, so WordPress can regenerate "-scaled" and all sub-sizes.
		$new_file = wp_get_original_image_path( $replace_id ) ?: get_attached_file( $replace_id );
		$old_file = wp_get_original_image_path( $post_id ) ?: get_attached_file( $post_id );

		if ( ! $new_file || ! is_file( $new_file ) || ! $old_file ) {
			return false;
		}

		// Copy first, so the current files are only deleted when the replacement is in place.
		$target_dir = dirname( $old_file );
		$tmp_file   = $old_file . '.pot-replace.tmp';
		if ( ! @copy( $new_file, $tmp_file ) ) {
			return false;
		}

		$this->delete_attachment_files( $post_id );

		// Keep the old file name, but take the extension of the new file, so the content always matches the extension.
		$new_ext = strtolower( pathinfo( $new_file, PATHINFO_EXTENSION ) );
		$target  = $old_file;
		if ( $new_ext !== strtolower( pathinfo( $old_file, PATHINFO_EXTENSION ) ) ) {
			$target = $target_dir . '/' . wp_unique_filename( $target_dir, pathinfo( $old_file, PATHINFO_FILENAME ) . '.' . $new_ext );
		}

		if ( ! @rename( $tmp_file, $target ) ) {
			@unlink( $tmp_file );

			return false;
		}

		update_attached_file( $post_id, $target );
		delete_post_meta( $post_id, '_wp_attachment_backup_sizes' );

		$mime = wp_check_filetype( $target )['type'];
		if ( $mime && $mime !== get_post_mime_type( $post_id ) ) {
			global $wpdb;
			// Direct update, as wp_update_post() would trigger this hook again.
			$wpdb->update( $wpdb->posts, [ 'post_mime_type' => $mime ], [ 'ID' => $post_id ] );
			clean_post_cache( $post_id );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $target ) );
		update_post_meta( $post_id, self::VERSION_META_KEY, time() );

		if ( current_user_can( 'delete_post', $replace_id ) ) {
			wp_delete_attachment( $replace_id, true );
		}

		return true;
	}

	private function delete_attachment_files( int $post_id ): void {
		$meta         = wp_get_attachment_metadata( $post_id );
		$backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
		$file         = get_attached_file( $post_id );
		$dir          = dirname( $file );

		// Additional formats (e.g. WebP from webp-uploads) are stored as "sources" and not removed by core.
		$sources = array_merge( [ $meta['sources'] ?? [] ], array_column( $meta['sizes'] ?? [], 'sources' ) );
		foreach ( $sources as $source ) {
			foreach ( (array) $source as $properties ) {
				if ( ! empty( $properties['file'] ) ) {
					wp_delete_file_from_directory( path_join( $dir, $properties['file'] ), $dir );
				}
			}
		}

		// Removes the main file, the original of scaled images, sub-sizes and edit backups.
		wp_delete_attachment_files( $post_id, $meta, $backup_sizes, $file );
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

	public function calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ): array {
		foreach ( $sources as $size => $source ) {
			$sources[ $size ]['url'] = $this->versioned_url( $source['url'], $attachment_id );
		}

		return $sources;
	}

	public function get_attachment_image_src( $image, $attachment_id ): array|false {
		if ( ! empty( $image[0] ) ) {
			$image[0] = $this->versioned_url( $image[0], $attachment_id );
		}

		return $image;
	}

	public function prepare_attachment_for_js( $response ): array {
		if ( ! empty( $response['url'] ) ) {
			$response['url'] = $this->versioned_url( $response['url'], $response['id'] );
		}

		foreach ( $response['sizes'] ?? [] as $size_name => $size ) {
			$response['sizes'][ $size_name ]['url'] = $this->versioned_url( $size['url'], $response['id'] );
		}

		return $response;
	}

	/**
	 * Bust browser and CDN caches for replaced files, as they keep the same URL.
	 */
	private function versioned_url( string $url, int $attachment_id ): string {
		$version = get_post_meta( $attachment_id, self::VERSION_META_KEY, true );

		return $version ? add_query_arg( 'v', $version, $url ) : $url;
	}
}
