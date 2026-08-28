<?php

/**
 * Uninstall handler for Scry Search for Meilisearch.
 *
 * @package ScrySearch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-lifecycle.php';

ScrySearch_Lifecycle::uninstall();
