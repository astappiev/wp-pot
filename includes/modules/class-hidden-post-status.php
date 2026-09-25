<?php

namespace Pot\Modules;

use Pot\POT_Module;

defined( '\ABSPATH' ) || exit;

class Hidden_Post_Status extends POT_Module {
	protected string $name        = 'Hidden Post Status';
	protected string $description = 'Adds a "hidden" post status — accessible via direct link but excluded from all listings and search. Known limitation: broken Gutenberg, use save draft instead of Publish after editing a post.';
	protected string $category    = 'content';
	protected bool $default       = true;

	public function load(): void {
		add_action( 'init', [ $this, 'register_post_status' ] );
		add_action( 'post_submitbox_misc_actions', [ $this, 'classic_editor_js' ] );
		add_action( 'admin_footer-edit.php', [ $this, 'quick_edit_js' ] );
		add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );
		add_filter( 'posts_where', [ $this, 'exclude_from_listings' ], 10, 2 );
		add_filter( 'wp_robots', [ $this, 'noindex' ] );
	}

	public function register_post_status(): void {
		register_post_status(
			'hidden',
			[
				'label'                     => _x( 'Hidden', 'post status', 'wp-pot' ),
				'public'                    => true,
				'publicly_queryable'        => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'show_in_rest'              => true,
				'label_count'               => _n_noop(
					'Hidden <span class="count">(%s)</span>',
					'Hidden <span class="count">(%s)</span>',
					'wp-pot'
				),
			]
		);
	}

	/**
	 * Public statuses are included in all non-singular queries by WP_Query, so exclude hidden posts explicitly.
	 * Admin lists, singular views and queries requesting specific statuses are left untouched.
	 */
	public function exclude_from_listings( string $where, \WP_Query $query ): string {
		if ( ( is_admin() && ! wp_doing_ajax() ) || $query->is_singular() || ! empty( $query->get( 'post_status' ) ) ) {
			return $where;
		}

		global $wpdb;

		return $where . " AND {$wpdb->posts}.post_status <> 'hidden'";
	}

	public function noindex( array $robots ): array {
		if ( is_singular() && get_post_status() === 'hidden' ) {
			$robots['noindex'] = true;
		}

		return $robots;
	}

	public function classic_editor_js( $post = null ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$is_hidden = get_post_status( $post ) === 'hidden';
		?>
		<script>
		jQuery( document ).ready( function ( $ ) {
			var $select = $( 'select[name="post_status"]' );
			if ( $select.length && ! $select.find( 'option[value="hidden"]' ).length ) {
				$select.append( '<option value="hidden"<?php echo $is_hidden ? ' selected="selected"' : ''; ?>>Hidden</option>' );
			}
			<?php if ( $is_hidden ) : ?>
			$( '#post-status-display' ).text( 'Hidden' );
			$( '#save-post' ).show();
			<?php endif; ?>
		} );
		</script>
		<?php
	}

	public function quick_edit_js(): void {
		?>
		<script>
		jQuery( document ).ready( function ( $ ) {
			$( 'select[name="_status"]' ).append( '<option value="hidden">Hidden</option>' );
		} );
		</script>
		<?php
	}

	public function display_post_states( array $post_states, \WP_Post $post ): array {
		if ( get_post_status( $post ) === 'hidden' && get_query_var( 'post_status' ) !== 'hidden' ) {
			return [ __( 'Hidden', 'wp-pot' ) ];
		}

		return $post_states;
	}
}
