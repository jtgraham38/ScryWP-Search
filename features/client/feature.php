<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use Meilisearch\Client as MeilisearchClient;

class ScrySearch_Client_Feature {

	public function add_filters() {
	}

	public function add_actions() {
	}

    //  \\  //  \\  //  \\  //  \\

    public function get_client() {

        //get the host and api key from the options
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        //if the host or api key is not set, log an error and return
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Connection settings are not configured. Exiting ajax_get_tasks.', "scry-search"));
            throw new Exception(__('Connection settings are not configured', "scry-search"));
        }

        //get the host and api key from the options, and make a new client
        $client = new MeilisearchClient(
            $meilisearch_url,
            $meilisearch_admin_key
        );

        //let the client modify the client before returning it
        //@HOOK: scry_ms_client — args: $client
        $client = apply_filters($this->prefixed('client'), $client);

        //return the client
        return $client;
    }
}