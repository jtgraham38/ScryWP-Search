<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScryWpSearchFeature extends PluginFeature {
    
    public function add_filters() {
        // Add any filters here if needed
        add_filter('pre_get_posts', array($this, 'meilisearch_search'));
    }
    
    public function add_actions() {
        // Add any actions here if needed
        add_action('admin_menu', array($this, 'add_admin_page'));
    }

    //coopt the wordpress search feature, replacing it with out meilisearch search
    public function meilisearch_search() {
        //TODO: replace the wordpress search function with our meilisearch search function
        var_dump("todo: implement meilisearch search");
        die;
    }

    //add an admin page for the search settings
    public function add_admin_page() {
        // Register this page with the admin page feature
        $admin_page_feature = $this->get_feature('scrywp_admin_page');
        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scrywp-search-config',
                __('Search Settings', 'scry-wp'),
                'dashicons-search',
                __('Configure the search settings for ScryWP Search.', 'scry-wp')
            );
        }

        add_submenu_page(
            'scrywp-search',
            'Search Settings',
            'Search Settings',
            'manage_options',
            'scrywp-search-config',
            function() {
                ob_start();
                require_once plugin_dir_path(__FILE__) . 'elements/_inputs.php';
                $content = ob_get_clean();
                $this->get_feature('scrywp_admin_page')->render_admin_page($content);
            }
        );
    }
}