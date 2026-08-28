<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin deactivation and uninstall cleanup.
 *
 * Deactivation clears scheduled cron events so they do not keep firing while inactive.
 * Uninstall removes custom tables, all scry_ms_* options, and cron events.
 */
class ScrySearch_Lifecycle {

	public const OPTION_PREFIX = 'scry_ms_';

	/**
	 * Daily cron hooks registered by the plugin.
	 *
	 * @var string[]
	 */
	private const CRON_HOOKS = array(
		'scry_ms_cleanup_analytics_events',
		'scry_ms_cleanup_logs',
	);

	/**
	 * Custom database table suffixes (without the WordPress table prefix).
	 *
	 * @var string[]
	 */
	private const TABLE_SUFFIXES = array(
		'scry_ms_search_analytics',
		'scry_ms_logs',
	);

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		self::clear_scheduled_events();
	}

	/**
	 * Run on plugin uninstall (delete).
	 */
	public static function uninstall(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::clear_scheduled_events();
		self::drop_tables();
		self::delete_options();
	}

	/**
	 * Clear all plugin cron events.
	 */
	public static function clear_scheduled_events(): void {
		foreach ( self::CRON_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Drop plugin-owned custom tables.
	 */
	private static function drop_tables(): void {
		global $wpdb;

		foreach ( self::TABLE_SUFFIXES as $suffix ) {
			$table_name = $wpdb->prefix . $suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
		}
	}

	/**
	 * Delete all plugin options, including dynamic index backup keys.
	 */
	private static function delete_options(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%'
			)
		);
	}
}
