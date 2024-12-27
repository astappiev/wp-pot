<?php
/**
 * Module Name: Language Switch button for WP Admin
 * Description: Adds a language switch button to the WP Admin bar.
 */

function admin_bar_language_switch($admin_bar): void {
    if (function_exists('pll_the_languages') && is_singular()) {
        $languages = pll_the_languages([
            'hide_if_no_translation' => true,
            'hide_current' => true,
            'raw' => true
        ]);

        if (!empty($languages)) {
            foreach ($languages as $language) {
                if ($language['no_translation']) {
                    continue;
                }

                $admin_bar->add_menu([
                    'id' => 'adml_switch-' . $language['slug'],
                    'title' => '<img src="' . $language['flag'] . '"/> ' . $language['name'],
                    'href' => $language['url'],
                ]);
            }
        }
    }
}

add_action('admin_bar_menu', 'admin_bar_language_switch', 1000);
