<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\FederationOptions;

class ScrySearch_SearchFeature extends PluginFeature {
    
    public function add_filters() {


    }
    
    public function add_actions() {
        // Add any actions here if needed
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_page'));
    }

    //create a custom table for tracking search analytics
    public function create_search_analytics_table() {
        //todo: create a customer table for tracking search analytics
    }

    //insert a search analytics event into the database
    public function insert_search_analytics_event($event) {
        //todo: insert a search analytics event into the database
    }

    //query the analytics table for search analytics
    public function query_search_analytics($query) {
        //todo: query the analytics table for search analytics
    }

    /**
     * Register WordPress settings
     */
    public function register_settings() {
        // Only allow administrators to access these settings
        if (!current_user_can('manage_options')) {
            return;
        }

        //todo: add settings
    }

    //add an admin page for the search settings
    public function add_admin_page() {
        //todo: add search analytics page
    }
}