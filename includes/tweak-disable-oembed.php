<?php
/**
 * Module Name: Disable wp-embed script
 * Description: Remove wp-embed.js script from your website. oEmbed functionality still works but you will not be able to embed other WordPress posts on your pages.
 */

add_action('wp_footer', function () {
    wp_deregister_script('wp-embed');
});
