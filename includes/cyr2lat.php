<?php
/**
 * Module Name: Stupidly simple Cyrillic to Latin
 * Description: Converts cyrillic characters in post slugs to latin characters.
 */

function translit(string $title): string {
    // based on ISO 9
    $dictionary = [
        'А' => 'A', 'а' => 'a',
        'Б' => 'B', 'б' => 'b',
        'В' => 'V', 'в' => 'v',
        'Г' => 'G', 'г' => 'g',
        'Д' => 'D', 'д' => 'd',
        'Е' => 'E', 'е' => 'e',
        'Ё' => 'YO', 'ё' => 'yo',
        'Ж' => 'ZH', 'ж' => 'zh',
        'З' => 'Z', 'з' => 'z',
        'И' => 'I', 'и' => 'i',
        'Й' => 'J', 'й' => 'j',
        'К' => 'K', 'к' => 'k',
        'Л' => 'L', 'л' => 'l',
        'М' => 'M', 'м' => 'm',
        'Н' => 'N', 'н' => 'n',
        'О' => 'O', 'о' => 'o',
        'П' => 'P', 'п' => 'p',
        'Р' => 'R', 'р' => 'r',
        'С' => 'S', 'с' => 's',
        'Т' => 'T', 'т' => 't',
        'У' => 'U', 'у' => 'u',
        'Ф' => 'F', 'ф' => 'f',
        'Х' => 'H', 'х' => 'h',
        'Ц' => 'CZ', 'ц' => 'cz',
        'Ч' => 'CH', 'ч' => 'ch',
        'Ш' => 'SH', 'ш' => 'sh',
        'Щ' => 'SHH', 'щ' => 'shh',
        'Ъ' => '', 'ъ' => '',
        'Ы' => 'Y', 'ы' => 'y',
        'Ь' => '', 'ь' => '',
        'Э' => 'E', 'э' => 'e',
        'Ю' => 'YU', 'ю' => 'yu',
        'Я' => 'YA', 'я' => 'ya',

        // Macedonian letters
        'Ѓ' => 'G', 'ѓ' => 'g',
        'Ѕ' => 'Z', 'ѕ' => 'z',
        'Ј' => 'J', 'ј' => 'j',
        'Љ' => 'L', 'љ' => 'l',
        'Њ' => 'N', 'њ' => 'n',
        'Ќ' => 'K', 'ќ' => 'k',
        'Џ' => 'DH', 'џ' => 'dh',

        // Ukrainian letters
        'І' => 'I', 'і' => 'i',
        'Ґ' => 'G', 'ґ' => 'g',
        'Є' => 'YE', 'є' => 'ye',
        'Ї' => 'YI', 'ї' => 'yi',

        // Belarusian letters
        'Ў' => 'U', 'ў' => 'u',

        // Bulgarian letters
        'Ѣ' => 'YE', 'ѣ' => 'ye',
        'Ѫ' => 'О', 'ѫ' => 'о',
        'Ѳ' => 'FH', 'ѳ' => 'fh',
        'Ѵ' => 'YH', 'ѵ' => 'yh',
    ];

    $locale = get_locale();
    switch ($locale) {
        case 'uk':
        case 'uk_ua':
        case 'uk_UA':
            $dictionary['И'] = 'Y';
            $dictionary['и'] = 'y';
            break;
        case 'bg':
        case 'bg_bg':
        case 'bg_BG':
            $dictionary['Щ'] = 'SHT';
            $dictionary['щ'] = 'sht';
            $dictionary['Ъ'] = 'A';
            $dictionary['ъ'] = 'a';
            break;
    }

    $title = strtr($title, apply_filters('ctl_table', $dictionary));
    if (function_exists('iconv')) {
        $title = iconv('UTF-8', 'UTF-8//TRANSLIT//IGNORE', $title);
    }
    $title = preg_replace("/[^A-Za-z0-9'_\-.]/", '-', $title);
    $title = preg_replace('/-+/', '-', $title);
    return trim($title, "-");
}

add_filter('sanitize_title', 'translit', 9);
add_filter('sanitize_file_name', 'translit');
