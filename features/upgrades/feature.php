<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScrySearch_UpgradesFeature extends PluginFeature {
    public function add_filters() {
        // No filters are needed yet for upgrades.
    }

    public function add_actions() {
        add_action('admin_menu', array($this, 'add_admin_page'));
    }

    // Add an admin page for premium upgrades.
    public function add_admin_page() {
        $admin_page_feature = $this->get_feature('scry_ms_admin_page');
        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scry-search-meilisearch-upgrades',
                __('Upgrades', "scry-search"),
                'dashicons-superhero',
                __('Explore premium upgrades for ScryWP Search.', "scry-search")
            );
        }

        add_submenu_page(
            'scry-search-meilisearch',
            __('Upgrades', "scry-search"),
            __('Upgrades', "scry-search"),
            'manage_options',
            'scry-search-meilisearch-upgrades',
            function() {
                $file_path = plugin_dir_path(__FILE__) . 'elements/upgrades_page.php';
                $this->get_feature('scry_ms_admin_page')->render_admin_page($file_path);
            }
        );
    }
}