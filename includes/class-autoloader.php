<?php

namespace Pot;

defined( '\\ABSPATH' ) || exit;

class Autoloader {
	/**
	 * An associative array where the key is a namespace prefix and the value
	 * is an array of base directories for classes in that namespace.
	 *
	 * @var array
	 */
	private array $prefixes = [];

	/**
	 * Prefix to the class files to adhere to WordPress coding guidelines.
	 *
	 * @var string
	 */
	private string $class_file_prefix = 'class-';

	/**
	 * Register loader with SPL autoloader stack.
	 *
	 * @return void
	 */
	public function register(): void {
		spl_autoload_register( [ $this, 'loadClass' ] );
	}

	/**
	 * Adds a base directory for a namespace prefix.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $base_dir A base directory for class files in the namespace.
	 * @param bool $prepend If true, prepend the base directory to the stack instead of appending it;
	 *   this causes it to be searched first rather than last.
	 *
	 * @return void
	 */
	public function addNamespace( $prefix, $base_dir, $prepend = false ): void {
		$prefix   = trim( $prefix, '\\' ) . '\\';
		$base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . '/';
		if ( isset( $this->prefixes[ $prefix ] ) === false ) {
			$this->prefixes[ $prefix ] = [];
		}

		if ( $prepend ) {
			array_unshift( $this->prefixes[ $prefix ], $base_dir );
		} else {
			array_push( $this->prefixes[ $prefix ], $base_dir );
		}
	}

	/**
	 * Loads the class file for a given class name.
	 *
	 * @param string $class The fully-qualified class name.
	 *
	 * @return mixed The mapped file name on success, or boolean false on
	 * failure.
	 */
	public function loadClass( $class ): mixed {
		$prefix = $class;

		while ( false !== ( $pos = strrpos( $prefix, '\\' ) ) ) {
			$prefix         = substr( $class, 0, $pos + 1 );
			$relative_class = substr( $class, $pos + 1 );
			$mapped_file    = $this->loadMappedFile( $prefix, $relative_class );
			if ( $mapped_file ) {
				return $mapped_file;
			}

			$prefix = rtrim( $prefix, '\\' );
		}

		return false;
	}

	/**
	 * Load the mapped file for a namespace prefix and relative class.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $relative_class The relative class name.
	 *
	 * @return mixed Boolean false if no mapped file can be loaded, or the
	 * name of the mapped file that was loaded.
	 */
	protected function loadMappedFile( string $prefix, string $relative_class ): mixed {
		if ( isset( $this->prefixes[ $prefix ] ) === false ) {
			return false;
		}

		foreach ( $this->prefixes[ $prefix ] as $base_dir ) {
			$relative_class = strtolower( $relative_class );
			$relative_class = strtr( $relative_class, '_', '-' );

			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
			if ( $this->class_file_prefix ) {
				$pos      = strrpos( $file, '/' );
				$filename = $this->class_file_prefix . substr( $file, $pos + 1 );
				$file     = substr_replace( $file, $filename, $pos + 1 );
			}

			if ( $this->requireFile( $file ) ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * If a file exists, require it from the file system.
	 *
	 * @param string $file The file to require.
	 *
	 * @return bool True if the file exists, false if not.
	 */
	protected function requireFile( string $file ): bool {
		if ( file_exists( $file ) ) {
			require $file;

			return true;
		}

		return false;
	}
}
