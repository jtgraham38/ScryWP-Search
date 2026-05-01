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

class ScrySearch_WindowFeature extends PluginFeature {
    
    public function add_filters() {
       

    }
    
    public function add_actions() {
        // load teh window object script, for use by all other features and upgrades
        add_action('wp_enqueue_scripts', array($this, 'load_assets'));
    }

    /**
     * Load the autosuggest assets on the frontend if autosuggest is enabled, with the scripts localized with the settings
     */
    public function load_assets() {
        
        
        wp_enqueue_script(
            $this->prefixed('window-script'),
            plugin_dir_url(__FILE__) . 'assets/js/window.js',
            array(),
            '1.0.0',
            true
        );


    }
}