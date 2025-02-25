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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('plugins_loaded', function () {
    $base = dirname(__FILE__);
    require_once $base . '/includes/clean-head.php';
    require_once $base . '/includes/cyr2lat.php';
    require_once $base . '/includes/disable-author-archives.php';
    require_once $base . '/includes/disable-pingbacks.php';
    require_once $base . '/includes/fix-pidar-flag.php';
    require_once $base . '/includes/tweak-disable-oembed.php';
    require_once $base . '/includes/tweak-lazyload-iframes.php';
    require_once $base . '/includes/wp-admin-language-switch.php';
//    require_once $base . '/includes/wp-defer-scripts.php';
    require_once $base . '/includes/wp-remove-emoji.php';
    require_once $base . '/includes/yoast-sitemap-images.php';
});

add_filter( 'sanitize_file_name', 'mb_strtolower' );
