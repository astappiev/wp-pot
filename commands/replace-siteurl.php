<?php
/**
 * Module Name: Replace Site URL
 * Description: Check the site URL used in database and in wp-config.php and replace if needed. Useful when migrating a site to a new domain (e.g. from staging to production).
 */

add_action( 'cli_init', function () {

	$args = [
		'shortdesc' => 'Check and replace site URL from database with the one from wp-config.php',
		'synopsis'  => [
			[
				'type'        => 'flag',
				'name'        => 'yes',
				'description' => 'Answer yes to the confirmation message.',
				'optional'    => true,
			],
		],
	];

	WP_CLI::add_command( 'replace-siteurl', function ( $args, $assoc_args ) {
		global $wpdb;

		// Get current URL from database (stored in options table)
		$db_url = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'siteurl' )
		);

		if ( empty( $db_url ) ) {
			WP_CLI::error( 'Could not retrieve siteurl from database.' );

			return;
		}

		// Get current URL from wp-config.php (WP_HOME constant)
		$config_url = defined( 'WP_HOME' ) ? WP_HOME : get_option( 'home' );

		if ( empty( $config_url ) ) {
			WP_CLI::error( 'Could not retrieve home URL from configuration.' );

			return;
		}

		WP_CLI::log( "Database URL: {$db_url}" );
		WP_CLI::log( "Config URL:   {$config_url}" );

		if ( $db_url === $config_url ) {
			WP_CLI::success( 'Site URLs match. No replacement needed.' );

			return;
		}

		WP_CLI::warning( "URLs don't match!" );
		WP_CLI::confirm( "Do you want to replace '{$db_url}' with '{$config_url}' in all database tables?", $assoc_args );

		WP_CLI::log( "Replacing '{$db_url}' with '{$config_url}'..." );
		$result = WP_CLI::runcommand(
			sprintf( "search-replace '%s' '%s' --all-tables --precise --recurse-objects", $db_url, $config_url ),
			[
				'return'     => 'all',
				'launch'     => false,
				'exit_error' => false,
			]
		);

		if ( ! empty( $result->stdout ) ) {
			WP_CLI::log( $result->stdout );
		}

		if ( $result->return_code === 0 ) {
			WP_CLI::success( "Successfully replaced '{$db_url}' with '{$config_url}' in all tables." );

			// Check and update Polylang domains
			$polylang = get_option( 'polylang' );
			if ( is_array( $polylang ) && isset( $polylang['domains'] ) && is_array( $polylang['domains'] ) ) {
				$old_host = parse_url( $db_url, PHP_URL_HOST );
				$new_host = parse_url( $config_url, PHP_URL_HOST );

				if ( $old_host && $new_host ) {
					$changed = false;
					foreach ( $polylang['domains'] as $lang => $domain ) {
						if ( strpos( $domain, $old_host ) !== false ) {
							$new_domain = str_replace( $old_host, $new_host, $domain );
							if ( $domain !== $new_domain ) {
								$polylang['domains'][ $lang ] = $new_domain;
								$changed                      = true;
								WP_CLI::log( "Updated Polylang domain for {$lang}: {$domain} -> {$new_domain}" );
							}
						}
					}

					if ( $changed ) {
						update_option( 'polylang', $polylang );
						WP_CLI::success( 'Polylang domains updated.' );
					}
				}
			}
		} else {
			if ( ! empty( $result->stderr ) ) {
				WP_CLI::log( $result->stderr );
			}
			WP_CLI::error( 'Search-replace operation failed.' );
		}
	}, $args );

} );
