<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Client as MeilisearchClient;

class ScryWpConnectionSettingsFeature extends PluginFeature {
    
    public function add_filters() {
        // Individual settings are sanitized via register_setting sanitize_callback
    }
    
    public function add_actions() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Render the connection settings page
        add_action('admin_menu', array($this, 'render_connection_settings_page'));
        
        // Register AJAX handlers
        add_action('wp_ajax_scrywp_test_connection', array($this, 'ajax_test_connection'));
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
        // Register this page with the admin page feature
        $admin_page_feature = $this->get_feature('scrywp_admin_page');
        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scrywp-search-settings',
                __('Connection Settings', 'scry-wp'),
                'dashicons-admin-generic',
                __('Configure the connection settings for ScryWP Search, including connection type and server credentials.', 'scry-wp')
            );
        }
        
        //create a submenu page at 'scrywp-search-settings'
        add_submenu_page(
            'scrywp-search',   //parent slug
            'Connection Settings',       //page title
            'Connection Settings',       //menu title
            'manage_options',            //capability
            'scrywp-search-settings', //menu slug
            function() {
                ob_start();
                require_once plugin_dir_path(__FILE__) . 'elements/_inputs.php';
                $content = ob_get_clean();
                $this->get_feature('scrywp_admin_page')->render_admin_page($content);
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
                require_once plugin_dir_path(__FILE__) . 'elements/connection_type_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        //add the URL field
        add_settings_field(
            $this->prefixed('meilisearch_url'),
            'Meilisearch URL',
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/meilisearch_url_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        //add the search key field
        add_settings_field(
            $this->prefixed('meilisearch_search_key'),
            'Search API Key',
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/meilisearch_search_key_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        //add the admin key field 
        add_settings_field(
            $this->prefixed('meilisearch_admin_key'),
            'Admin API Key',
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/meilisearch_admin_key_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_settings_section')
        );

        // Register connection type setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('connection_type'),
            array(
                'type' => 'string',
                'description' => 'Connection type for ScryWP Search (manual or scrywp).',
                'sanitize_callback' => function($input) {
                    // Ensure the connection type is either manual or scrywp
                    $allowed_values = array('manual', 'scrywp');
                    $sanitized = sanitize_text_field($input);
                    if (!in_array($sanitized, $allowed_values, true)) {
                        return '';
                    }
                    return $sanitized;
                },
                'default' => '',
                'show_in_rest' => false,
            )
        );

        // Register Meilisearch URL setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('meilisearch_url'),
            array(
                'type' => 'string',
                'description' => 'Meilisearch instance URL for ScryWP Search.',
                'sanitize_callback' => function($input) {
                    // Sanitize URL
                    $sanitized = esc_url_raw(trim($input));
                    // Validate URL format
                    if (!empty($sanitized) && !filter_var($sanitized, FILTER_VALIDATE_URL)) {
                        return '';
                    }
                    return $sanitized;
                },
                'default' => '',
                'show_in_rest' => false,
            )
        );

        // Register Meilisearch search key setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('meilisearch_search_key'),
            array(
                'type' => 'string',
                'description' => 'Meilisearch search API key for ScryWP Search.',
                'sanitize_callback' => function($input) {
                    // Sanitize as text field but preserve the key
                    return sanitize_text_field(trim($input));
                },
                'default' => '',
                'show_in_rest' => false,
            )
        );

        // Register Meilisearch admin key setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('meilisearch_admin_key'),
            array(
                'type' => 'string',
                'description' => 'Meilisearch admin API key for ScryWP Search.',
                'sanitize_callback' => function($input) {
                    // Sanitize as text field but preserve the key
                    return sanitize_text_field(trim($input));
                },
                'default' => '',
                'show_in_rest' => false,
            )
        );
    }
    
    /**
     * AJAX handler for testing Meilisearch connection
     */
    public function ajax_test_connection() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scrywp_test_connection')) {
            wp_send_json_error(array('message' => __('Security check failed', 'scry-wp')));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'scry-wp')));
            return;
        }
        
        // Get connection settings from POST data
        $url = isset($_POST['meilisearch_url']) ? sanitize_text_field($_POST['meilisearch_url']) : '';
        $admin_key = isset($_POST['meilisearch_admin_key']) ? sanitize_text_field($_POST['meilisearch_admin_key']) : '';
        $search_key = isset($_POST['meilisearch_search_key']) ? sanitize_text_field($_POST['meilisearch_search_key']) : '';
        $connection_type = isset($_POST['connection_type']) ? sanitize_text_field($_POST['connection_type']) : '';
        
        // If no URL provided, try to get from saved settings
        if (empty($url)) {
            $url = get_option($this->prefixed('meilisearch_url'), '');
        }
        
        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => __('Please provide a valid Meilisearch URL', 'scry-wp')));
            return;
        }
        
        // Use admin key for testing, or fall back to search key
        $api_key = !empty($admin_key) ? $admin_key : $search_key;
        
        // If no key provided in POST, try to get from saved settings
        if (empty($api_key)) {
            $api_key = get_option($this->prefixed('meilisearch_admin_key'), '');
            if (empty($api_key)) {
                $api_key = get_option($this->prefixed('meilisearch_search_key'), '');
            }
        }
        
        try {
            // Create Meilisearch client
            $client = new MeilisearchClient($url, $api_key);
            
            // Test connection by getting health status
            $health = $client->health();
            
            // If we get here, the connection is successful
            $message = __('Connection successful!', 'scry-wp');
            
            wp_send_json_success(array('message' => $message));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', 'scry-wp'), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error (auth, etc.)
            wp_send_json_error(array(
                'message' => sprintf(__('Authentication failed: %s', 'scry-wp'), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', 'scry-wp'), $e->getMessage())
            ));
        }
    }
}