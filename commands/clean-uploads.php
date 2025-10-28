<?php
/**
 * Module Name: Clean Uploads Directory
 * Description: Scan the uploads directory and identify files that are not registered in the WordPress media library. Can optionally delete unregistered files.
 */

add_action( 'cli_init', function () {

	$args = [
		'shortdesc' => 'Clean uploads directory by removing unregistered files',
		'synopsis'  => [
			[
				'type'        => 'flag',
				'name'        => 'delete',
				'description' => 'Delete files immediately without confirmation',
				'optional'    => true,
			],
		],
	];

	WP_CLI::add_command( 'media clean-unregistered', function ( $args, $assoc_args ) {
		$skip_confirmation = isset( $assoc_args['delete'] );

		if ( $skip_confirmation ) {
			WP_CLI::warning( 'Running with --delete flag - files will be deleted immediately without confirmation!' );
		}

		// Get WordPress uploads directory path
		$upload_dir  = wp_upload_dir();
		$uploads_dir = $upload_dir['basedir'];

		if ( empty( $uploads_dir ) || ! is_dir( $uploads_dir ) ) {
			WP_CLI::error( 'Could not find uploads directory.' );

			return;
		}

		WP_CLI::log( "Scanning uploads directory: {$uploads_dir}" );

		// Get all registered attachment files from WordPress database
		$registered_files = get_registered_files();
		WP_CLI::log( sprintf( 'Found %d registered files in database', count( $registered_files ) ) );

		// Scan uploads directory
		$all_files = scan_directory( $uploads_dir );
		WP_CLI::log( sprintf( 'Found %d total files in uploads directory', count( $all_files ) ) );

		// Find unregistered files
		$unregistered_files = [];
		foreach ( $all_files as $file_path ) {
			$relative_path = str_replace( $uploads_dir . '/', '', $file_path );

			// Check if this file or any of its variations are registered
			if ( ! is_file_registered( $relative_path, $registered_files ) ) {
				$unregistered_files[] = $file_path;
			}
		}

		$registered_count = count( $all_files ) - count( $unregistered_files );
		WP_CLI::log( sprintf( 'Registered files: %d', $registered_count ) );

		if ( empty( $unregistered_files ) ) {
			WP_CLI::success( 'No unregistered files found. Uploads directory is clean!' );

			return;
		}

		WP_CLI::warning( sprintf( 'Found %d unregistered files', count( $unregistered_files ) ) );

		// Display unregistered files with their sizes
		$total_size = 0;
		foreach ( $unregistered_files as $file ) {
			$size       = filesize( $file );
			$total_size += $size;
			$relative   = str_replace( $uploads_dir . '/', '', $file );
			WP_CLI::log( sprintf( '  %s (%s)', $relative, format_bytes( $size ) ) );
		}

		WP_CLI::log( sprintf( 'Total size to be freed: %s', format_bytes( $total_size ) ) );

		// Ask for confirmation if not in skip-confirmation mode
		if ( ! $skip_confirmation ) {
			WP_CLI::confirm( sprintf( 'Do you want to permanently delete %d unregistered files?', count( $unregistered_files ) ) );
		}

		// Delete files
		$deleted_count = 0;
		$failed_count  = 0;

		foreach ( $unregistered_files as $file ) {
			if ( unlink( $file ) ) {
				$deleted_count ++;
			} else {
				$failed_count ++;
				WP_CLI::warning( 'Failed to delete: ' . str_replace( $uploads_dir . '/', '', $file ) );
			}
		}

		if ( $failed_count > 0 ) {
			WP_CLI::warning( sprintf( 'Deleted %d files, failed to delete %d files', $deleted_count, $failed_count ) );
		} else {
			WP_CLI::success( sprintf( 'Deleted %d files', $deleted_count ) );
		}

		// Clean up empty directories
		cleanup_empty_directories( $uploads_dir );

		WP_CLI::success( 'Cleanup complete!' );
	}, $args );

} );

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get all registered file paths from WordPress database
 */
function get_registered_files() {
	global $wpdb;

	$attachments = $wpdb->get_results(
		"SELECT ID, guid, meta_value
		FROM {$wpdb->posts}
		LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
		AND {$wpdb->postmeta}.meta_key = '_wp_attached_file'
		WHERE post_type = 'attachment'"
	);

	$files = [];
	foreach ( $attachments as $attachment ) {
		if ( ! empty( $attachment->meta_value ) ) {
			$files[] = $attachment->meta_value;

			// Get all generated image sizes
			$metadata = wp_get_attachment_metadata( $attachment->ID );
			if ( ! empty( $metadata['sizes'] ) ) {
				$file_path = dirname( $attachment->meta_value );

				foreach ( $metadata['sizes'] as $size => $size_data ) {
					if ( ! empty( $size_data['file'] ) ) {
						$files[] = $file_path . '/' . $size_data['file'];
					}
				}
			}

			// Get original file if it exists (for scaled images)
			if ( ! empty( $metadata['original_image'] ) ) {
				$file_path = dirname( $attachment->meta_value );
				$files[]   = $file_path . '/' . $metadata['original_image'];
			}
		}
	}

	return array_unique( $files );
}

/**
 * Recursively scan directory and return all file paths
 */
function scan_directory( $dir ) {
	$files    = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isFile() ) {
			// Skip .htaccess and index.php files
			$filename = $item->getFilename();
			if ( $filename !== '.htaccess' && $filename !== 'index.php' ) {
				$files[] = $item->getPathname();
			}
		}
	}

	return $files;
}

/**
 * Check if a file is registered in the database
 */
function is_file_registered( $relative_path, $registered_files ) {
	// Normalize path separators
	$relative_path = str_replace( '\\', '/', $relative_path );

	// Direct match
	if ( in_array( $relative_path, $registered_files, true ) ) {
		return true;
	}

	// Check for common WordPress generated file patterns
	// Example: image-150x150.jpg, image-300x200.jpg, image-scaled.jpg
	$path_info = pathinfo( $relative_path );
	$dir       = ! empty( $path_info['dirname'] ) && $path_info['dirname'] !== '.' ? $path_info['dirname'] . '/' : '';
	$filename  = $path_info['filename'];
	$extension = ! empty( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';

	// Remove size suffixes (e.g., -150x150, -300x200, -scaled, -1, -2, etc.)
	$base_filename = preg_replace( '/-\d+x\d+$/', '', $filename );  // Remove -150x150
	$base_filename = preg_replace( '/-scaled$/', '', $base_filename ); // Remove -scaled
	$base_filename = preg_replace( '/-\d+$/', '', $base_filename );    // Remove -1, -2, etc.

	// Check if the base file is registered
	$base_path = $dir . $base_filename . $extension;
	if ( in_array( $base_path, $registered_files, true ) ) {
		return true;
	}

	// Check if any registered file starts with this base name
	// This catches generated thumbnails that might have different patterns
	foreach ( $registered_files as $registered ) {
		$registered_info     = pathinfo( $registered );
		$registered_dir      = ! empty( $registered_info['dirname'] ) && $registered_info['dirname'] !== '.' ? $registered_info['dirname'] . '/' : '';
		$registered_filename = $registered_info['filename'];
		$registered_base     = preg_replace( '/-\d+x\d+$/', '', $registered_filename );
		$registered_base     = preg_replace( '/-scaled$/', '', $registered_base );

		// If same directory and same base name, consider it registered
		if ( $dir === $registered_dir && strpos( $filename, $registered_base ) === 0 ) {
			return true;
		}
	}

	return false;
}

/**
 * Format bytes to human readable format
 */
function format_bytes( $bytes, $precision = 2 ) {
	$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

	for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i ++ ) {
		$bytes /= 1024;
	}

	return round( $bytes, $precision ) . ' ' . $units[ $i ];
}

/**
 * Remove empty directories recursively
 */
function cleanup_empty_directories( $dir ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	$removed = 0;
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			$path = $item->getPathname();
			// Check if directory is empty
			$files = scandir( $path );
			if ( count( $files ) <= 2 ) { // Only . and ..
				if ( rmdir( $path ) ) {
					$removed ++;
				}
			}
		}
	}

	if ( $removed > 0 ) {
		WP_CLI::success( sprintf( 'Removed %d empty directories', $removed ) );
	}
}

