<?php

const POT_AVATAR_META_KEY = 'pot_local_avatar';

function pot_local_avatars_enqueue_scripts( $hook_suffix ) {
	$screens = array( 'profile.php', 'user-edit.php', 'options-discussion.php' );
	if ( ! in_array( $hook_suffix, $screens, true ) ) {
		return;
	}

	if ( current_user_can( 'upload_files' ) ) {
		wp_enqueue_media();
	}

	wp_enqueue_script( 'wp-pot-local-avatars', plugins_url( '../assets/js/local-avatars.js', __FILE__ ), [ 'jquery' ], WP_POT_VERSION, true );
}

add_action( 'admin_enqueue_scripts', 'pot_local_avatars_enqueue_scripts' );

function pot_add_avatar_field( $user ) {
	$avatar_id  = get_user_meta( $user->ID, POT_AVATAR_META_KEY, true );
	$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
	?>
	<h2><?php esc_html_e( 'Profile Picture', 'wp-pot' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="pot_local_avatar"><?php esc_html_e( 'Local Avatar', 'wp-pot' ); ?></label></th>
			<td>
				<input type="hidden" id="pot_local_avatar" name="pot_local_avatar" value="<?php echo esc_attr( $avatar_id ); ?>"/>
				<div id="pot_local_avatars_preview" style="margin-bottom: 10px;">
					<?php if ( $avatar_url ): ?>
						<img src="<?php echo esc_url( $avatar_url ); ?>" style="max-width: 150px; height: auto;"/>
					<?php endif; ?>
				</div>
				<button type="button" class="button" id="pot_local_avatars_upload_btn">
					<?php echo $avatar_id ? esc_html__( 'Change Avatar', 'wp-pot' ) : esc_html__( 'Upload Avatar', 'wp-pot' ); ?>
				</button>
				<?php if ( $avatar_id ): ?>
					<button type="button" class="button" id="pot_local_avatars_remove_btn"><?php esc_html_e( 'Remove Avatar', 'wp-pot' ); ?></button>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Select an image from the media library to use as your avatar.', 'wp-pot' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'show_user_profile', 'pot_add_avatar_field' );
add_action( 'edit_user_profile', 'pot_add_avatar_field' );

function pot_save_avatar_field( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}

	if ( isset( $_POST[ POT_AVATAR_META_KEY ] ) ) {
		$avatar_id = absint( $_POST[ POT_AVATAR_META_KEY ] );
		if ( $avatar_id ) {
			update_user_meta( $user_id, POT_AVATAR_META_KEY, $avatar_id );
		} else {
			delete_user_meta( $user_id, POT_AVATAR_META_KEY );
		}
	}
}

add_action( 'personal_options_update', 'pot_save_avatar_field' );
add_action( 'edit_user_profile_update', 'pot_save_avatar_field' );


function pot_local_avatars_url( $url, $id_or_email, $args ) {
	$user = false;

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
	} elseif ( $id_or_email instanceof \WP_User ) {
		$user = $id_or_email;
	} elseif ( $id_or_email instanceof \WP_Post ) {
		$user = get_user_by( 'id', $id_or_email->post_author );
	} elseif ( $id_or_email instanceof \WP_Comment ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			$user = get_user_by( 'id', $id_or_email->user_id );
		}
	}

	if ( ! $user || ! $user->ID ) {
		return $url;
	}

	$avatar_id = get_user_meta( $user->ID, POT_AVATAR_META_KEY, true );
	if ( ! $avatar_id ) {
		return $url;
	}

	$size       = isset( $args['size'] ) ? $args['size'] : 96;
	$avatar_url = wp_get_attachment_image_url( $avatar_id, [ $size, $size ] );

	return $avatar_url ?: $url;
}

add_filter( 'get_avatar_url', 'pot_local_avatars_url', 10, 3 );


function pot_local_avatars_class( $avatar, $id_or_email, $size, $default, $alt ) {
	$user = false;

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
	} elseif ( $id_or_email instanceof \WP_User ) {
		$user = $id_or_email;
	}

	if ( $user && $user->ID ) {
		$avatar_id = get_user_meta( $user->ID, POT_AVATAR_META_KEY, true );
		if ( $avatar_id ) {
			$avatar = str_replace( 'class=\'', 'class=\'local-avatar ', $avatar );
		}
	}

	return $avatar;
}

add_filter( 'get_avatar', 'pot_local_avatars_class', 10, 5 );


/**
 * Assigns reference media IDs for Local Avatars in WP Meta Cleaner.
 */
function wpmc_scan_once_local_avatars(): void {
	global $wpmc;

	$users = get_users( array( 'fields' => array( 'ID' ) ) );
	foreach ( $users as $user ) {
		$data = get_user_meta( $user->ID, POT_AVATAR_META_KEY, true );

		if ( ! empty( $data ) ) {
			$wpmc->add_reference_id( $data, 'Local Avatar (ID)' );
		}
	}
}

add_action( 'wpmc_scan_once', 'wpmc_scan_once_local_avatars', 10, 0 );
