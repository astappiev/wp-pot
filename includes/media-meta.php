<?php

/**
 * Filter the Media list table columns to add a File Size column.
 *
 * @param array $posts_columns Existing array of columns displayed in the Media list table.
 * @return array Amended array of columns to be displayed in the Media list table.
 */
function swr_media_columns_filesize( $posts_columns ) {
    $posts_columns['filesize'] = __( 'File Size', 'wp-pot' );
    $posts_columns['dimensions'] = __( 'Dimensions', 'wp-pot' );

    return $posts_columns;
}
add_filter( 'manage_media_columns', 'swr_media_columns_filesize' );

/**
 * Display File Size custom column in the Media list table.
 *
 * @param string $column_name Name of the custom column.
 * @param int    $post_id Current Attachment ID.
 */
function swr_media_custom_column_filesize( $column_name, $post_id ) {
    if ( 'filesize' === $column_name ) {
        $bytes = filesize( get_attached_file( $post_id ) );
        echo size_format( $bytes, 2 );
    }

    if ( 'dimensions' === $column_name ) {
        $metadata = wp_get_attachment_metadata( $post_id );

        if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
            echo esc_html( $metadata['width'] . ' × ' . $metadata['height'] );
        } else {
            echo '–';
        }
    }
}
add_action( 'manage_media_custom_column', 'swr_media_custom_column_filesize', 10, 2 );

/**
 * Adjust File Size column on Media Library page in WP admin
 */
function swr_filesize_column_filesize() {
    echo
    '<style>
        .fixed .column-filesize {
            width: 10%;
			max-width: 80px;
        }
        .fixed .column-dimensions {
            width: 10%;
			max-width: 80px;
        }
    </style>';
}
add_action( 'admin_print_styles-upload.php', 'swr_filesize_column_filesize' );
