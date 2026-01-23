<?php

namespace Pot\CLI;

use WP_CLI;
use Exception;
use RuntimeException;

class Sync {

	const DEFAULT_ALIAS = '@production';

	protected string $current_date;
	protected array $alias = [];
	private array $options = [];
	protected int $errors_count = 0;

	public function __construct() {
		$this->current_date = gmdate( 'Ymd\THis' );
	}

	/**
	 * Check and replace site URL from database with the one from wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pot-sync siteurl
	 *     wp pot-sync siteurl --yes
	 *
	 * @when after_wp_load
	 */
	public function siteurl( $args, $assoc_args ): void {
		global $wpdb;

		// Get current URL from database (stored in options table)
		$db_url = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'siteurl' )
		);

		if ( empty( $db_url ) ) {
			WP_CLI::error( 'Could not retrieve siteurl from database.' );
		}

		// Get current URL from wp-config.php (WP_HOME constant)
		$config_url = defined( 'WP_HOME' ) ? WP_HOME : get_option( 'home' );

		if ( empty( $config_url ) ) {
			WP_CLI::error( 'Could not retrieve home URL from configuration.' );
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
			$this->update_polylang_domains( $db_url, $config_url );
		} else {
			if ( ! empty( $result->stderr ) ) {
				WP_CLI::log( $result->stderr );
			}
			WP_CLI::error( 'Search-replace operation failed.' );
		}
	}

	/**
	 * Pull database and uploads from a remote site.
	 *
	 * ## OPTIONS
	 *
	 * [<alias>]
	 * : The WP-CLI alias to pull from. Defaults to @production.
	 *
	 * [--backup_dir=<dir>]
	 * : Directory to store backups. Defaults to 'backup'.
	 *
	 * [--plugins_activate=<plugins>]
	 * : Comma-separated list of plugins to activate after pull.
	 *
	 * [--plugins_deactivate=<plugins>]
	 * : Comma-separated list of plugins to deactivate after pull.
	 *
	 * [--upload_dir=<dir>]
	 * : Upload directory path. Defaults to 'wp-content/uploads'.
	 *
	 * [--exclude_dirs=<dirs>]
	 * : Comma-separated list of directories to exclude from uploads sync.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pot-sync pull
	 *     wp pot-sync pull @production
	 *     wp pot-sync pull --plugins_activate=query-monitor,debug-bar
	 *     wp pot-sync pull --exclude_dirs=cache,tmp
	 *
	 * @when after_wp_load
	 */
	public function pull( $args, $assoc_args ): void {
		$this->parse_alias( $args[0] ?? null );

		$this->options = array_merge(
			[
				'backup_dir'          => 'backup',
				'plugins_activate'    => '',
				'plugins_deactivate'  => '',
				'upload_dir'          => 'wp-content/uploads',
				'exclude_dirs'        => '',
			],
			$assoc_args
		);

		$this->ensure_backup_directory();
		$this->validate_upload_directory();

		WP_CLI::line( WP_CLI::colorize( "%BPulling from {$this->alias['name']}%n" ) );

		try {
			WP_CLI::run_command( [ 'maintenance-mode', 'activate' ], [ 'force' => true ] );

			$target_path = $this->options['backup_dir'] . '/backup_' . $this->current_date . '.sql';
			WP_CLI::log( 'Backing up database' );
			$this->run_local( "db export $target_path --single-transaction" );

			$path = $this->options['backup_dir'] . '/pull_' . $this->current_date . '.sql';
			WP_CLI::log( "Pulling database from {$this->alias['name']}" );
			$db_dump = $this->run_remote( 'db export - --single-transaction' );
			file_put_contents( $path, $db_dump );
			WP_CLI::log( 'Database pulled from remote' );

			WP_CLI::log( 'Resetting local database' );
			$this->run_local( 'db reset --yes' );

			WP_CLI::log( 'Importing database to local site' );
			$this->run_local( "db import $path" );

			WP_CLI::log( 'Replacing site URL' );
			$this->siteurl( [], [ 'yes' => true ] );

			$this->plugins_management();
			$this->sync_uploads();
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		} finally {
			WP_CLI::run_command( [ 'maintenance-mode', 'deactivate' ] );
		}

		if ( $this->errors_count > 0 ) {
			WP_CLI::warning( WP_CLI::colorize( '%BFinished with ' . $this->errors_count . ' errors%n' ) );
		} else {
			WP_CLI::success( WP_CLI::colorize( '%GAll Tasks Finished%n' ) );
		}
	}

	/**
	 * Parse and validate the provided alias.
	 *
	 * @param string|null $args_alias The alias argument.
	 */
	private function parse_alias( $args_alias ): void {
		$aliases = WP_CLI::get_runner()->aliases;
		$alias   = $this->provided_alias_or_default( $aliases, $args_alias, self::DEFAULT_ALIAS );

		if ( empty( $alias ) ) {
			return;
		}

		$alias_data = $aliases[ $alias ];

		if ( ! isset( $alias_data['ssh'] ) ) {
			WP_CLI::error( "Alias $alias does not have `ssh` configuration." );
		}

		if ( ! isset( $alias_data['path'] ) ) {
			WP_CLI::error( "Alias $alias does not have `path` configuration." );
		}

		$alias_data['name']        = $alias;
		$alias_data['append_args'] = [ '--ssh=' . $alias_data['ssh'] . ':' . $alias_data['path'] ];
		$this->alias               = $alias_data;

		$this->check_connection();
	}

	private function ensure_backup_directory(): void {
		if ( ! is_dir( $this->options['backup_dir'] ) ) {
			WP_CLI::log( "Creating backup directory: {$this->options['backup_dir']}" );
			mkdir( $this->options['backup_dir'], 0755, true );
		}
	}

	private function validate_upload_directory(): void {
		if ( is_dir( $this->options['upload_dir'] ) ) {
			return;
		}

		// Try Bedrock structure fallback
		if ( is_dir( 'web/app/uploads' ) ) {
			$this->options['upload_dir'] = 'web/app/uploads';
			WP_CLI::log( 'Using Bedrock uploads directory: web/app/uploads' );
			return;
		}

		WP_CLI::error( 'Uploads directory does not exist. Please provide a valid upload directory.' );
	}

	/**
	 * Get the provided alias or use the default.
	 *
	 * @param array       $aliases  Available aliases.
	 * @param string|null $provided Provided alias.
	 * @param string      $default  Default alias.
	 * @return string
	 */
	private function provided_alias_or_default( $aliases, $provided, $default ): string {
		if ( empty( $aliases ) ) {
			WP_CLI::error( 'No aliases defined. Please add aliases to your wp-cli.yml file.' );
			return '';
		}

		if ( ! empty( $provided ) ) {
			if ( $provided[0] !== '@' ) {
				$provided = '@' . $provided;
			}

			if ( isset( $aliases[ $provided ] ) ) {
				return $provided;
			} else {
				WP_CLI::error( "Alias $provided not found." );
				return '';
			}
		}

		if ( isset( $aliases[ $default ] ) ) {
			return $default;
		}

		WP_CLI::error( 'Please provide an alias as first argument: wp pot-sync pull <alias>' );
		return '';
	}

	private function check_connection(): void {
		$local_url  = $this->run_local( 'option get home' );
		$remote_url = $this->run_remote( 'option get home' );

		if ( $local_url === $remote_url ) {
			WP_CLI::error( 'Remote home URL matches local URL' );
		}
	}

	/**
	 * Run a WP-CLI command locally.
	 *
	 * @param string $command The command to run.
	 * @param bool   $fatal   Whether to throw exception on error.
	 * @return string
	 */
	private function run_local( $command, $fatal = true ): string {
		$cmd = WP_CLI::runcommand(
			$command . ' --quiet',
			[
				'launch'     => false,
				'exit_error' => false,
				'return'     => 'all',
			]
		);

		if ( $cmd->return_code !== 0 ) {
			$error_message = "Error running command `$command`: $cmd->stderr";
			if ( $fatal ) {
				throw new RuntimeException( $error_message );
			} else {
				WP_CLI::error( $error_message, false );
				++$this->errors_count;
			}
		}

		return $cmd->stdout;
	}

	/**
	 * Run a WP-CLI command on remote site.
	 *
	 * @param string $command The command to run.
	 * @param bool   $fatal   Whether to throw exception on error.
	 * @return string
	 */
	private function run_remote( $command, $fatal = true ): string {
		if ( empty( $this->alias ) ) {
			if ( $fatal ) {
				throw new RuntimeException( 'No alias provided' );
			} else {
				WP_CLI::error( "Can't run command `$command`: no alias provided", false );
				return '';
			}
		}

		$cmd = WP_CLI::runcommand(
			$command . ' --quiet',
			[
				'exit_error'    => false,
				'return'        => 'all',
				'command_args'  => $this->alias['append_args'],
			]
		);

		if ( $cmd->return_code !== 0 ) {
			$error_message = "Error running remote command `$command` on `{$this->alias['name']}`: $cmd->stderr";
			if ( $fatal ) {
				throw new RuntimeException( $error_message );
			} else {
				WP_CLI::error( $error_message, false );
				++$this->errors_count;
			}
		}

		return $cmd->stdout;
	}

	private function plugins_management(): void {
		if ( ! empty( $this->options['plugins_activate'] ) ) {
			WP_CLI::log( 'Activating plugins' );
			$plugins_list = preg_replace( '/[ ,]+/', ' ', trim( $this->options['plugins_activate'] ) );
			$this->run_local( 'plugin activate ' . $plugins_list, false );
		}

		if ( ! empty( $this->options['plugins_deactivate'] ) ) {
			WP_CLI::log( 'Deactivating plugins' );
			$plugins_list = preg_replace( '/[ ,]+/', ' ', trim( $this->options['plugins_deactivate'] ) );
			$this->run_local( 'plugin deactivate ' . $plugins_list, false );
		}
	}

	private function update_polylang_domains( string $old_url, string $new_url ): void {
		$polylang = get_option( 'polylang' );

		if ( ! is_array( $polylang ) || ! isset( $polylang['domains'] ) || ! is_array( $polylang['domains'] ) ) {
			return;
		}

		$old_host = parse_url( $old_url, PHP_URL_HOST );
		$new_host = parse_url( $new_url, PHP_URL_HOST );

		if ( ! $old_host || ! $new_host || $old_host === $new_host ) {
			return;
		}

		$changed = false;
		foreach ( $polylang['domains'] as $lang => $domain ) {
			if ( str_contains( $domain, $old_host ) ) {
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

	private function sync_uploads(): void {
		if ( ! $this->check_rsync_availability() ) {
			return;
		}

		$uploads_folder = $this->options['upload_dir'];
		WP_CLI::log( 'Syncing uploads folder' );

		$command = $this->build_rsync_command( $uploads_folder );
		WP_CLI::debug( $command );

		$rsync = WP_CLI::launch( $command, false, true );

		if ( $rsync->return_code !== 0 ) {
			WP_CLI::warning( 'rsync failed. Check the command output above.' );
			++$this->errors_count;
			return;
		}

		WP_CLI::log( 'Uploads folder synced' );
	}

	private function check_rsync_availability(): bool {
		$has_rsync = WP_CLI::launch( 'rsync --version', false, true );

		if ( $has_rsync->return_code !== 0 ) {
			WP_CLI::warning( 'rsync not found. Please install rsync.' );
			++$this->errors_count;
			return false;
		}

		return true;
	}

	private function build_rsync_command( string $uploads_folder ): string {
		$excludes = $this->build_rsync_excludes();
		$remote_path = $this->alias['ssh'] . ':' . $this->alias['path'] . '/' . $uploads_folder . '/';
		$local_path = './' . $uploads_folder . '/';

		return sprintf( 'rsync -avhP %s %s%s', $remote_path, $local_path, $excludes );
	}

	private function build_rsync_excludes(): string {
		if ( empty( $this->options['exclude_dirs'] ) ) {
			return '';
		}

		$exclude_dirs = explode( ',', $this->options['exclude_dirs'] );
		$excludes = '';

		foreach ( $exclude_dirs as $dir ) {
			$excludes .= ' --exclude=' . escapeshellarg( trim( $dir ) );
		}

		return $excludes;
	}
}
