<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Client as MeilisearchClient;

/**
 * Shared Meilisearch client factory.
 *
 * Centralizes Client construction so indexing, search, admin tasks, and add-ons
 * all go through one path — and so third parties can wrap or replace the client
 * via scry_ms_meilisearch_client.
 */
class ScrySearch_Client_Feature extends PluginFeature {

	public function add_filters() {
		// No WordPress filters registered by this feature itself.
	}

	public function add_actions() {
		// No WordPress actions registered by this feature itself.
	}

	//  \\  //  \\  //  \\  Client factory  //  \\  //  \\  //  \\

	/**
	 * Build a Meilisearch client for the given key type.
	 *
	 * @param string $key_type 'admin' or 'search'. Search falls back to the admin key when unset.
	 * @return MeilisearchClient
	 * @throws \Exception When connection settings are missing.
	 */
	public function get_client( string $key_type = 'admin' ) {
		// Resolve the Meilisearch host from plugin options.
		$meilisearch_url = get_option( $this->prefixed( 'meilisearch_url' ), '' );

		// Prefer the search key for read paths; fall back to admin when it is not configured.
		if ( 'search' === $key_type ) {
			$api_key = get_option( $this->prefixed( 'meilisearch_search_key' ), '' );
			if ( empty( $api_key ) ) {
				$api_key = get_option( $this->prefixed( 'meilisearch_admin_key' ), '' );
			}
		} else {
			// Normalize anything other than 'search' to 'admin' for the filter payload.
			$key_type = 'admin';
			$api_key  = get_option( $this->prefixed( 'meilisearch_admin_key' ), '' );
		}

		// Bail early when connection settings are incomplete.
		if ( empty( $meilisearch_url ) || empty( $api_key ) ) {
			$this->get_feature( 'scry_ms_logs' )->log(
				'debug',
				__( 'Connection settings are not configured. Exiting get_client.', 'scry-search' )
			);
			throw new \Exception( __( 'Connection settings are not configured', 'scry-search' ) );
		}

		// Construct the official Meilisearch PHP client.
		$client = new MeilisearchClient( $meilisearch_url, $api_key );

		// Let other plugins wrap or replace the client (e.g. mocks, middleware, custom hosts).
		//@HOOK: scry_ms_meilisearch_client
		$client = apply_filters( $this->config( 'hook_prefix' ) . 'meilisearch_client', $client, $key_type );

		return $client;
	}
}
