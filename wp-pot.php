<?php
/**
 * Plugin Name: WP Performance Optimization Tactics (POT)
 * Description: My collection of Performance Optimization Tactics (POT) for WordPress
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Version: 1.0.0
 * Author: Oleh Astappiev
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-pot
 * Domain Path: /lang
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_POT_FILE', __FILE__ );
define( 'WP_POT_BASENAME', plugin_basename( WP_POT_FILE ) );
define( 'WP_POT_PLUGIN_DIR', plugin_dir_url( WP_POT_FILE ) );

if ( ! defined( 'WP_POT_PLUGIN_PATH' ) ) {
	define( 'WP_POT_PLUGIN_PATH', __DIR__ );
}

$meta = get_file_data( WP_POT_FILE, [ 'Version' => 'Version' ] );

define( 'WP_POT_VERSION', $meta['Version'] );

require_once WP_POT_PLUGIN_PATH . '/includes/class-autoloader.php';

$autoloader = new Pot\Autoloader();
$autoloader->register();
$autoloader->addNamespace( 'Pot', WP_POT_PLUGIN_PATH . '/includes' );

add_action( 'plugins_loaded', function () {
	$registry = new \Pot\Registry();

	$registry->register_module(
		\Pot\Modules\Admin_Language_Switch::class,
		\Pot\Modules\Clean_Head::class,
		\Pot\Modules\Cyr2lat::class,
		\Pot\Modules\Defer_Scripts::class,
		\Pot\Modules\Disable_Author_Archives::class,
		\Pot\Modules\Disable_Oembed::class,
		\Pot\Modules\Disable_Pingbacks::class,
		\Pot\Modules\Fix_Pidar_Flag::class,
		\Pot\Modules\Lazyload_Iframes::class,
		\Pot\Modules\Local_Avatars::class,
		\Pot\Modules\Media_Meta::class,
		\Pot\Modules\Media_Replace::class,
		\Pot\Modules\Remove_Emoji::class,
		\Pot\Modules\Yoast_Sitemap_Images::class,
	);

	$registry->load_enabled_modules();

	if ( is_admin() ) {
		$admin_page = new \Pot\Admin_Page( $registry );
		$admin_page->init();
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'pot-clean', \Pot\CLI\Clean::class );
		WP_CLI::add_command( 'pot-purge', \Pot\CLI\Purge::class );
		WP_CLI::add_command( 'pot-sync', \Pot\CLI\Sync::class );
	}
} );
