<?php
/**
 * Module Name: Defer Scripts
 * Description: Defer loading of JS
 */

add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (is_admin()) return $tag; // don't break WP Admin
    if (!str_contains($src, '.js')) return $tag;

    if ($handle === 'wp-hooks' || $handle === 'wp-i18n') return $tag;
    return str_replace('<script src', '<script defer src', $tag);
}, 10, 3);
