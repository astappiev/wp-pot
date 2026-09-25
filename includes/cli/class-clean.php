<?php

namespace Pot\CLI;

use WP_CLI;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;

class Clean {
	private ?array $registered_bases = null;

	/**
	 * Clean uploads directory by removing unregistered files.
	 *
	 * By default only files in the uploads root and in year folders (e.g. 2024/05) are scanned,
	 * as other folders usually belong to plugins (woocommerce_uploads, learndash, logs, etc.).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only list unregistered files, don't delete anything.
	 *
	 * [--delete]
	 * : Delete files immediately without confirmation.
	 *
	 * [--all]
	 * : Scan all folders in uploads, not only year folders.
	 *
	 * [--exclude=<paths>]
	 * : Comma-separated list of paths relative to uploads to skip, e.g. 2023/01,2024.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pot-clean unregistered --dry-run
	 *     wp pot-clean unregistered
	 *     wp pot-clean unregistered --delete
	 *     wp pot-clean unregistered --all --exclude=woocommerce_uploads,learndash
	 *
	 * @when after_wp_load
	 */
	public function unregistered( $args, $assoc_args ): void {
		$skip_confirmation = isset( $assoc_args['delete'] );
		$dry_run           = isset( $assoc_args['dry-run'] );
		$scan_all          = isset( $assoc_args['all'] );
		$excludes          = array_filter( array_map( fn( $path ) => trim( $path, " /" ), explode( ',', $assoc_args['exclude'] ?? '' ) ) );

		if ( $skip_confirmation && ! $dry_run ) {
			WP_CLI::warning( 'Running with --delete flag - files will be deleted immediately without confirmation!' );
		}

		// Get WordPress uploads directory path
		$upload_dir  = wp_upload_dir();
		$uploads_dir = $upload_dir['basedir'];

		if ( empty( $uploads_dir ) || ! is_dir( $uploads_dir ) ) {
			WP_CLI::error( 'Could not find uploads directory.' );
		}

		WP_CLI::log( "Scanning uploads directory: $uploads_dir" );

		// Get all registered attachment files from WordPress database
		$registered_files = $this->get_registered_files();
		WP_CLI::log( sprintf( 'Found %d registered files in database', count( $registered_files ) ) );

		// Scan uploads directory
		$all_files = $this->scan_directory( $uploads_dir, $scan_all, $excludes );
		WP_CLI::log( sprintf( 'Found %d total files in uploads directory', count( $all_files ) ) );

		// Find unregistered files
		$unregistered_files = [];
		foreach ( $all_files as $file_path ) {
			$relative_path = str_replace( $uploads_dir . '/', '', $file_path );

			// Check if this file or any of its variations are registered
			if ( ! $this->is_file_registered( $relative_path, $registered_files ) ) {
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
			WP_CLI::log( sprintf( '  %s (%s)', $relative, $this->format_bytes( $size ) ) );
		}

		WP_CLI::log( sprintf( 'Total size to be freed: %s', $this->format_bytes( $total_size ) ) );

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run, no files were deleted.' );

			return;
		}

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
		$this->cleanup_empty_directories( $uploads_dir, $scan_all, $excludes );

		WP_CLI::success( 'Cleanup complete!' );
	}

	/**
	 * Get all registered file paths from WordPress database, as a set (path => true).
	 */
	private function get_registered_files(): array {
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
			if ( empty( $attachment->meta_value ) ) {
				continue;
			}

			$files[ $attachment->meta_value ] = true;
			$file_path = dirname( $attachment->meta_value );
			$metadata  = wp_get_attachment_metadata( $attachment->ID );

			// Get original file if it exists (for scaled images)
			if ( ! empty( $metadata['original_image'] ) ) {
				$files[ $file_path . '/' . $metadata['original_image'] ] = true;
			}

			// Get all generated image sizes, and their additional formats (e.g. WebP "sources" from webp-uploads)
			$items = array_merge( [ $metadata ?: [] ], $metadata['sizes'] ?? [] );
			foreach ( $items as $item ) {
				if ( ! empty( $item['file'] ) ) {
					$files[ $file_path . '/' . wp_basename( $item['file'] ) ] = true;
				}

				foreach ( $item['sources'] ?? [] as $source ) {
					if ( ! empty( $source['file'] ) ) {
						$files[ $file_path . '/' . $source['file'] ] = true;
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Recursively scan directory and return all file paths.
	 */
	private function scan_directory( string $dir, bool $scan_all, array $excludes ): array {
		$files = [];

		foreach ( $this->get_iterator( $dir, $scan_all, $excludes, RecursiveIteratorIterator::LEAVES_ONLY ) as $item ) {
			// Skip .htaccess and index.php files
			$filename = $item->getFilename();
			if ( $item->isFile() && $filename !== '.htaccess' && $filename !== 'index.php' ) {
				$files[] = $item->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Iterate uploads, skipping excluded paths and (unless $scan_all) top-level folders other than years.
	 */
	private function get_iterator( string $dir, bool $scan_all, array $excludes, int $mode ): RecursiveIteratorIterator {
		return new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				function ( $item ) use ( $dir, $scan_all, $excludes ) {
					$relative = ltrim( substr( $item->getPathname(), strlen( $dir ) ), '/' );

					foreach ( $excludes as $exclude ) {
						if ( $relative === $exclude || str_starts_with( $relative, $exclude . '/' ) ) {
							return false;
						}
					}

					// Top-level folders other than years usually belong to plugins
					if ( ! $scan_all && $item->isDir() && ! str_contains( $relative, '/' ) ) {
						return (bool) preg_match( '/^\d{4}$/', $relative );
					}

					return true;
				}
			),
			$mode
		);
	}

	/**
	 * Check if a file is registered in the database.
	 */
	private function is_file_registered( string $relative_path, array $registered_files ): bool {
		// Normalize path separators
		$relative_path = str_replace( '\\', '/', $relative_path );

		// Direct match
		if ( isset( $registered_files[ $relative_path ] ) ) {
			return true;
		}

		// Check for common WordPress generated file patterns
		// Example: image-150x150.jpg, image-300x200.jpg, image-scaled.jpg
		$path_info = pathinfo( $relative_path );
		$dir       = ! empty( $path_info['dirname'] ) && $path_info['dirname'] !== '.' ? $path_info['dirname'] . '/' : '';
		$filename  = $path_info['filename'];
		$extension = ! empty( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';

		// Remove size suffixes (e.g., -150x150, -300x200, -scaled, -1, -2, etc.)
		$base_filename = preg_replace( '/-\d+x\d+$/', '', $filename ); // Remove -150x150
		$base_filename = preg_replace( '/-scaled$/', '', $base_filename ); // Remove -scaled
		$base_filename = preg_replace( '/-\d+$/', '', $base_filename ); // Remove -1, -2, etc.

		// Check if the base file is registered
		if ( isset( $registered_files[ $dir . $base_filename . $extension ] ) ) {
			return true;
		}

		// Check if any registered file in the same directory starts with this base name
		// This catches generated thumbnails that might have different patterns
		foreach ( $this->get_registered_bases( $registered_files )[ $dir ] ?? [] as $registered_base ) {
			if ( str_starts_with( $filename, $registered_base ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registered file names without size suffixes, grouped by directory.
	 */
	private function get_registered_bases( array $registered_files ): array {
		if ( isset( $this->registered_bases ) ) {
			return $this->registered_bases;
		}

		$this->registered_bases = [];
		foreach ( array_keys( $registered_files ) as $registered ) {
			$registered_info = pathinfo( $registered );
			$registered_dir  = ! empty( $registered_info['dirname'] ) && $registered_info['dirname'] !== '.' ? $registered_info['dirname'] . '/' : '';
			$registered_base = preg_replace( '/-\d+x\d+$/', '', $registered_info['filename'] );
			$registered_base = preg_replace( '/-scaled$/', '', $registered_base );

			$this->registered_bases[ $registered_dir ][ $registered_base ] = $registered_base;
		}

		return $this->registered_bases;
	}

	/**
	 * Format bytes to human-readable format.
	 */
	private function format_bytes( int $bytes, int $precision = 2 ): string {
		$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i ++ ) {
			$bytes /= 1024;
		}

		return round( $bytes, $precision ) . ' ' . $units[ $i ];
	}

	/**
	 * Remove empty directories recursively.
	 */
	private function cleanup_empty_directories( string $dir, bool $scan_all, array $excludes ): void {
		$iterator = $this->get_iterator( $dir, $scan_all, $excludes, RecursiveIteratorIterator::CHILD_FIRST );

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
}

