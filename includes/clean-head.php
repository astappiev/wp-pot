<?php
/**
 * Module Name: Clean Head
 * Description: Removes generators and other meta tags from webpage head section.
 */

/**
 * Clean system info from meta
 */
add_action('init', function () {
    // Remove generator tags
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'perflab_render_generator');
    remove_action('wp_head', 'webp_uploads_render_generator');

    // Remove RDS link
    remove_action('wp_head', 'rsd_link');
    // Remove Windows Live Writer manifest
    remove_action('wp_head', 'wlwmanifest_link');

    // Remove the generators from RSS feeds as well
    add_filter('the_generator', '__return_false');
}, PHP_INT_MAX - 1);
