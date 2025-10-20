<?php

add_action( 'admin_enqueue_scripts', 'pot_media_replace_enqueue_scripts' );
function pot_media_replace_enqueue_scripts() {
	wp_enqueue_script( 'wp-pot-media-replace', plugins_url( '../assets/js/media-replace.js', __FILE__ ), [ 'jquery' ], WP_POT_VERSION, true );
}

add_action( 'edit_attachment', 'pot_media_replace_edit_attachment' );
function pot_media_replace_edit_attachment( $postId ) {
	if ( ! empty( $_POST['replaceWith'] ) && is_numeric( $_POST['replaceWith'] ) && ! empty( $_POST['replaceWithNonce'] ) && wp_verify_nonce( $_POST['replaceWithNonce'], 'pot_media_replace' ) && current_user_can( 'edit_post', $postId ) ) {

		$uploadDir = wp_upload_dir();
		$newFile   = $uploadDir['basedir'] . '/' . get_post_meta( $_POST['replaceWith'], '_wp_attached_file', true );

		// Make sure the new file exists before proceeding
		if ( ! is_file( $newFile ) ) {
			return false;
		}

		// Delete the old attachment's files
		pot_media_replace_delete_attachment( $postId );

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
	}
}

/** Code copied from WordPress wp-includes/post.php and modified **/
function pot_media_replace_delete_attachment( $post_id ) {
	$meta         = wp_get_attachment_metadata( $post_id );
	$backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
	$file         = get_attached_file( $post_id );

	if ( is_multisite() ) {
		delete_transient( 'dirsize_cache' );
	}

	$uploadpath = wp_get_upload_dir();

	if ( ! empty( $meta['thumb'] ) ) {
		$thumbfile = str_replace( basename( $file ), $meta['thumb'], $file );
		/** This filter is documented in wp-includes/functions.php */
		$thumbfile = apply_filters( 'wp_delete_file', $thumbfile );
		@ unlink( path_join( $uploadpath['basedir'], $thumbfile ) );
	}

	// Remove intermediate and backup images if there are any.
	if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size => $sizeinfo ) {
			$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
			/** This filter is documented in wp-includes/functions.php */
			$intermediate_file = apply_filters( 'wp_delete_file', $intermediate_file );
			@ unlink( path_join( $uploadpath['basedir'], $intermediate_file ) );
		}
	}

	if ( is_array( $backup_sizes ) ) {
		foreach ( $backup_sizes as $size ) {
			$del_file = path_join( dirname( $meta['file'] ), $size['file'] );
			/** This filter is documented in wp-includes/functions.php */
			$del_file = apply_filters( 'wp_delete_file', $del_file );
			@ unlink( path_join( $uploadpath['basedir'], $del_file ) );
		}
	}
	wp_delete_file( $file );
}

/** End code copied from WordPress **/

add_filter( 'attachment_fields_to_edit', 'pot_media_replace_attachment_fields', 10, 2 );
function pot_media_replace_attachment_fields( $fields, $attachment ) {
	if ( current_user_can( 'edit_post', $attachment->ID ) ) {
		wp_enqueue_media();
		$fields['pot_image_replace']          = array();
		$fields['pot_image_replace']['label'] = '';
		$fields['pot_image_replace']['input'] = 'html';
		$fields['pot_image_replace']['html']  = '
		<button type="button" class="button-secondary button-large" onclick="pot_media_replace();">Replace Image</button>
		<input type="hidden" id="pot_media_replace_with_fld" name="replaceWith" />
		' . wp_nonce_field( 'pot_media_replace', 'replaceWithNonce', false, false ) . '
		<p><strong>Warning:</strong> Replacing this image with another one will permanently delete the current image file, and the replacement image will be moved to overwrite this one.</p>
	';
	}

	return $fields;
}

// functions below is to help to replace cached images in admin area after replacement

add_filter( 'wp_calculate_image_srcset', 'pot_media_replace_calculate_image_srcset' );
function pot_media_replace_calculate_image_srcset( $sources ) {
	if ( is_admin() ) {
		foreach ( $sources as $size => $source ) {
			$source['url']    .= ( strpos( $source['url'], '?' ) === false ? '?' : '&' ) . '_t=' . time();
			$sources[ $size ] = $source;
		}
	}

	return $sources;
}

add_filter( 'wp_get_attachment_image_src', 'pot_media_replace_get_attachment_image_src' );
function pot_media_replace_get_attachment_image_src( $attr ) {
	if ( is_admin() && ! empty( $attr[0] ) ) {
		$attr[0] .= ( strpos( $attr[0], '?' ) === false ? '?' : '&' ) . '_t=' . time();
	}

	return $attr;
}

add_filter( 'wp_prepare_attachment_for_js', 'pot_media_replace_prepare_attachment_for_js' );
function pot_media_replace_prepare_attachment_for_js( $response ) {
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
