<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScryWpConnectionSettingsFeature extends PluginFeature {
    
    public function add_filters() {
        // Add filters for sanitizing and validating settings
        add_filter('sanitize_option_' . $this->prefixed('connection_settings'), array($this, 'sanitize_connection_settings'));
    }
    
    public function add_actions() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Render the connection settings page
        add_action('admin_menu', array($this, 'render_connection_settings_page'));
    }
    
    /**
     * Enqueue admin assets for search settings
     */
    public function enqueue_admin_assets($hook) {
        // Only load assets on our admin page
        if ($hook !== 'toplevel_page_scrywp-search') {
            return;
        }
        
        //TODO: enqueue as needed
    }


    /*
    * Render the connection settings page
    */
    public function render_connection_settings_page() {
        //create a submenu page at 'scrywp-search-settings'
        add_submenu_page(
            'scrywp-search',   //parent slug
            'Connection Settings',       //page title
            'Connection Settings',       //menu title
            'manage_options',            //capability
            'scrywp-search-settings', //menu slug
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/_inputs.php';
            }
        );
    }

    /**
     * Register WordPress settings
     */
    public function register_settings() {
        // Only allow administrators to access these settings
        if (!current_user_can('manage_options')) {
            return;
        }

        //register the connection settings group
        add_settings_section(
            $this->prefixed('connection_settings_section'),
            'Connection Settings',
            function() {
                echo '<p>Configure the connection settings for ScryWP Search.</p>';
            },
            $this->prefixed('search_settings_group')
        );

        //add the connection type field
        add_settings_field(
            $this->prefixed('connection_type'),
            'Connection Type',
            function() {
                $settings = get_option($this->prefixed('meilisearch_connection_settings'), array());
                $connection_type = isset($settings['connection_type']) ? $settings['connection_type'] : '';
                
                echo '<fieldset>';
                echo '<label><input type="radio" name="' . $this->prefixed('meilisearch_connection_settings[connection_type]') . '" value="manual" ' . checked($connection_type, 'manual', false) . '> Manual Configuration</label><br>';
                echo '<label><input type="radio" name="' . $this->prefixed('meilisearch_connection_settings[connection_type]') . '" value="scrywp" ' . checked($connection_type, 'scrywp', false) . '> ScryWP Managed Service</label>';
                echo '</fieldset>';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        //add the URL field
        add_settings_field(
            $this->prefixed('meilisearch_url'),
            'Meilisearch URL',
            function() {
                $settings = get_option($this->prefixed('meilisearch_connection_settings'), array());
                $url = isset($settings['meilisearch_url']) ? $settings['meilisearch_url'] : '';
                
                echo '<input type="url" name="' . $this->prefixed('meilisearch_connection_settings[meilisearch_url]') . '" value="' . esc_attr($url) . '" class="regular-text" placeholder="https://your-meilisearch-instance.com" required>';
                echo '<p class="description">The URL of your Meilisearch instance.</p>';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        //add the search key field
        add_settings_field(
            $this->prefixed('meilisearch_search_key'),
            'Search API Key',
            function() {
                $settings = get_option($this->prefixed('meilisearch_connection_settings'), array());
                $search_key = isset($settings['meilisearch_search_key']) ? $settings['meilisearch_search_key'] : '';
                
                echo '<input type="password" name="' . $this->prefixed('meilisearch_connection_settings[meilisearch_search_key]') . '" value="' . esc_attr($search_key) . '" class="regular-text" placeholder="Your search API key" required>';
                echo '<p class="description">The API key with search permissions for your Meilisearch instance.</p>';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        //add the admin key field
        add_settings_field(
            $this->prefixed('meilisearch_admin_key'),
            'Admin API Key',
            function() {
                $settings = get_option($this->prefixed('meilisearch_connection_settings'), array());
                $admin_key = isset($settings['meilisearch_admin_key']) ? $settings['meilisearch_admin_key'] : '';
                
                echo '<input type="password" name="' . $this->prefixed('meilisearch_connection_settings[meilisearch_admin_key]') . '" value="' . esc_attr($admin_key) . '" class="regular-text" placeholder="Your admin API key" required>';
                echo '<p class="description">The API key with admin permissions for managing indexes and settings.</p>';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        //options are: manual and scrywp
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('meilisearch_connection_settings'),
            array(
                'type' => 'array',
                'description' => 'Meilisearch connection settings for ScryWP Search.',
                'sanitize_callback' => function($input) {

                    //ensure the connection type is either manual or scrywp
                    if ($input['connection_type'] !== 'manual' && $input['connection_type'] !== 'scrywp') {
                        return new WP_Error('invalid_connection_type', 'Invalid connection type');
                    }

                    //ensure the meilisearch search key is set
                    if (empty($input['meilisearch_search_key'])) {
                        return new WP_Error('invalid_meilisearch_search_key', 'Meilisearch search key is required');
                    }

                    //ensure the meilisearch admin key is set
                    if (empty($input['meilisearch_admin_key'])) {
                        return new WP_Error('invalid_meilisearch_admin_key', 'Meilisearch admin key is required');
                    }

                    //ensure the meilisearch url is set
                    if (empty($input['meilisearch_url'])) {
                        return new WP_Error('invalid_meilisearch_url', 'Meilisearch url is required');
                    }

                    return array(
                        'connection_type' => sanitize_text_field($input['connection_type']),
                        'meilisearch_search_key' => sanitize_text_field($input['meilisearch_search_key']),
                        'meilisearch_admin_key' => sanitize_text_field($input['meilisearch_admin_key']),
                        'meilisearch_url' => sanitize_text_field($input['meilisearch_url']),
                    );
                },
                'default' => array(
                    'connection_type' => '',
                    'meilisearch_search_key' => '',
                    'meilisearch_admin_key' => '',
                    'meilisearch_url' => '',
                )
            )
        );
    }
}