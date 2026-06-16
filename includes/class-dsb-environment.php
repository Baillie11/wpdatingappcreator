<?php
/**
 * Environment and database safety helpers.
 *
 * @package DatingSiteBuilder
 */

class DSB_Environment {

	/**
	 * Get the declared application environment.
	 *
	 * Prefer an explicit DSB_ENVIRONMENT value in wp-config.php, then
	 * WordPress's WP_ENVIRONMENT_TYPE constant, then common server
	 * environment variables. Falls back to production because destructive
	 * operations should fail closed.
	 *
	 * @return string
	 */
	public static function get_environment() {
		if ( defined( 'DSB_ENVIRONMENT' ) && DSB_ENVIRONMENT ) {
			return strtolower( (string) DSB_ENVIRONMENT );
		}

		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE ) {
			return strtolower( (string) WP_ENVIRONMENT_TYPE );
		}

		foreach ( array( 'NODE_ENV', 'APP_ENV', 'WP_ENV' ) as $env_var ) {
			$value = getenv( $env_var );
			if ( false !== $value && '' !== $value ) {
				return strtolower( (string) $value );
			}
		}

		return 'production';
	}

	/**
	 * Determine whether this request is running against production/live.
	 *
	 * @return bool
	 */
	public static function is_production() {
		$environment = self::get_environment();

		if ( in_array( $environment, array( 'production', 'prod', 'live' ), true ) ) {
			return true;
		}

		return self::database_name_looks_live();
	}

	/**
	 * Heuristic guard for live database names.
	 *
	 * @return bool
	 */
	public static function database_name_looks_live() {
		if ( ! defined( 'DB_NAME' ) || ! DB_NAME ) {
			return true;
		}

		$db_name = strtolower( (string) DB_NAME );

		return (bool) preg_match( '/(^|[_-])(prod|production|live)([_-]|$)/', $db_name );
	}

	/**
	 * Allow destructive data removal only when explicitly enabled.
	 *
	 * @return bool
	 */
	public static function destructive_data_removal_allowed() {
		if ( ! self::is_production() ) {
			return true;
		}

		return defined( 'DSB_ALLOW_PRODUCTION_DATA_DELETION' )
			&& true === DSB_ALLOW_PRODUCTION_DATA_DELETION;
	}

	/**
	 * Stop a destructive operation in production unless explicitly allowed.
	 *
	 * @param string $operation Human-readable operation name.
	 */
	public static function assert_destructive_data_removal_allowed( $operation ) {
		if ( self::destructive_data_removal_allowed() ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: Operation name. */
			__( 'Dating Site Builder blocked "%s" because this environment appears to be production/live. Take a database backup and set DSB_ALLOW_PRODUCTION_DATA_DELETION to true only if you intentionally want to delete production dating data.', 'dating-site-builder' ),
			$operation
		);

		if ( function_exists( 'wp_die' ) ) {
			wp_die( esc_html( $message ) );
		}

		exit( esc_html( $message ) );
	}
}
