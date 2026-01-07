<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\\ABSPATH' ) || exit;

class Local_Avatars extends POT_Module {
	private const AVATAR_META_KEY = 'pot_local_avatar';

	protected string $name = 'Local Avatars';
	protected string $description = 'Allows users to upload custom avatars from the media library.';
	protected string $category = 'media';
	protected bool $default = true;

	public function load(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'show_user_profile', [ $this, 'add_avatar_field' ] );
		add_action( 'edit_user_profile', [ $this, 'add_avatar_field' ] );
		add_action( 'personal_options_update', [ $this, 'save_avatar_field' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_avatar_field' ] );
		add_filter( 'get_avatar_url', [ $this, 'get_avatar_url' ], 10, 3 );
		add_filter( 'get_avatar', [ $this, 'add_avatar_class' ], 10, 5 );
		add_action( 'wpmc_scan_once', [ $this, 'wpmc_scan_references' ], 10, 0 );
	}

	public function enqueue_scripts( $hook_suffix ): void {
		$screens = [ 'profile.php', 'user-edit.php', 'options-discussion.php' ];
		if ( ! in_array( $hook_suffix, $screens, true ) ) {
			return;
		}

		if ( current_user_can( 'upload_files' ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_script( 'wp-pot-local-avatars', plugins_url( '../../assets/js/local-avatars.js', __FILE__ ), [ 'jquery' ], WP_POT_VERSION, true );
	}

	public function add_avatar_field( $user ): void {
		$avatar_id  = get_user_meta( $user->ID, self::AVATAR_META_KEY, true );
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
							<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php esc_attr_e( 'Avatar preview', 'wp-pot' ); ?>" style="max-width: 150px; height: auto;"/>
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

	public function save_avatar_field( $user_id ): bool {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( isset( $_POST['pot_local_avatar'] ) ) {
			$avatar_id = absint( $_POST['pot_local_avatar'] );
			if ( $avatar_id ) {
				update_user_meta( $user_id, self::AVATAR_META_KEY, $avatar_id );
			} else {
				delete_user_meta( $user_id, self::AVATAR_META_KEY );
			}
		}

		return true;
	}

	public function get_avatar_url( $url, $id_or_email, $args ): string {
		$user = $this->get_user_from_id_or_email( $id_or_email );

		if ( ! $user || ! $user->ID ) {
			return $url;
		}

		$avatar_id = get_user_meta( $user->ID, self::AVATAR_META_KEY, true );
		if ( ! $avatar_id ) {
			return $url;
		}

		$size       = isset( $args['size'] ) ? $args['size'] : 96;
		$avatar_url = wp_get_attachment_image_url( $avatar_id, [ $size, $size ] );

		return $avatar_url ?: $url;
	}

	public function add_avatar_class( $avatar, $id_or_email, $size, $default, $alt ): string {
		$user = $this->get_user_from_id_or_email( $id_or_email );

		if ( $user && $user->ID ) {
			$avatar_id = get_user_meta( $user->ID, self::AVATAR_META_KEY, true );
			if ( $avatar_id ) {
				$avatar = str_replace( 'class=\'', 'class=\'local-avatar ', $avatar );
			}
		}

		return $avatar;
	}

	private function get_user_from_id_or_email( $id_or_email ) {
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

		return $user;
	}

	public function wpmc_scan_references(): void {
		global $wpmc;

		if ( ! isset( $wpmc ) ) {
			return;
		}

		$users = get_users( [ 'fields' => [ 'ID' ] ] );
		foreach ( $users as $user ) {
			$data = get_user_meta( $user->ID, self::AVATAR_META_KEY, true );

			if ( ! empty( $data ) ) {
				$wpmc->add_reference_id( $data, 'Local Avatar (ID)' );
			}
		}
	}
}
