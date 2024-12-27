<?php
/**
 * Module Name: Yoast Sitemap Images
 * Description: Adds all attached images to the Yoast SEO sitemap.
 */

add_filter('wpseo_sitemap_urlimages', function ($images, $post_id) {
    $attached_images = get_attached_media('image', $post_id);
    if ($attached_images) {
        foreach ($attached_images as $attached_image) {
            $image_arr = array();
            $image_arr['src'] = $attached_image->guid;
            $images[] = $image_arr;
        }
    }
    return array_unique($images, SORT_REGULAR);
}, 10, 2);

add_filter('wpseo_debug_markers', '__return_false');
