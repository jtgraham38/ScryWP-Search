<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Client;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\ApiException;

class ScryWpAdminPageFeature extends PluginFeature {
    
    /**
     * Registry of admin pages
     * 
     * @var array
     */
    private static $registered_pages = array();
    
    public function add_filters() {
        // Add any filters here if needed
    }
    
    public function add_actions() {
        // Register admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Register admin page scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Register AJAX handlers
        add_action('wp_ajax_' . $this->prefixed('get_tasks'), array($this, 'ajax_get_tasks'));
    }
    
    /**
     * Register an admin page for the tabbed interface
     * 
     * @param string $page_slug The page slug (e.g., 'scrywp-search-settings')
     * @param string $label The display label
     * @param string $icon Dashicon class (e.g., 'dashicons-admin-generic')
     * @param string $description Optional description for the intro page
     * @return void
     */
    public function register_admin_page($page_slug, $label, $icon = 'dashicons-admin-generic', $description = '') {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        self::$registered_pages[$page_slug] = array(
            'slug' => sanitize_text_field($page_slug),
            'label' => sanitize_text_field($label),
            'icon' => sanitize_text_field($icon),
            'description' => sanitize_textarea_field($description),
            'url' => admin_url('admin.php?page=' . esc_attr($page_slug)),
        );
    }
    
    /**
     * Get all registered admin pages
     * 
     * @return array
     */
    public function get_registered_pages() {
        return self::$registered_pages;
    }
    
    /**
     * Register the admin menu page
     */
    public function register_admin_menu() {
        // Register the main page
        $this->register_admin_page(
            'scrywp-search',
            __('Overview', 'scry-wp'),
            'dashicons-search',
            __('Welcome to ScryWP Search. Configure your search settings and manage indexes.', 'scry-wp')
        );
        
        add_menu_page(
            'ScryWP Search', // Page title
            'ScryWP Search', // Menu title
            'manage_options', // Capability required
            'scrywp-search', // Menu slug
            function() {
                ob_start();
                require_once plugin_dir_path(__FILE__) . 'elements/main_page.php';
                $content = ob_get_clean();
                $this->render_admin_page($content);
            }, // Callback function
            'dashicons-search', // Icon
            30 // Position
        );
    }
    
    /**
     * Enqueue admin page assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load assets on our admin pages
        // Main page hook: 'toplevel_page_scrywp-search'
        // Submenu page hook format: 'scrywp-search_page_{submenu-slug}'
        $allowed_hooks = array('toplevel_page_scrywp-search');
        
        // Add submenu pages dynamically
        $registered_pages = $this->get_registered_pages();
        foreach ($registered_pages as $page_slug => $page_data) {
            if ($page_slug !== 'scrywp-search') {
                $allowed_hooks[] = 'scrywp-search_page_' . str_replace('scrywp-search-', '', $page_slug);
            }
        }
        
        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }
        
        wp_enqueue_style(
            $this->prefixed('admin-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            $this->prefixed('admin-script'),
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array(),
            '1.0.0',
            true
        );
    }

    /**
     * Render an admin page in the base layout
     * 
     * @param string $content The page content to render
     */
    public function render_admin_page($content) {
        require_once plugin_dir_path(__FILE__) . 'elements/base_layout.php';
    }
    
    /**
     * AJAX handler for fetching tasks from Meilisearch
     */
    public function ajax_get_tasks() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('get_tasks'))) {
            wp_send_json_error(array('message' => __('Security check failed', 'scry-wp')));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'scry-wp')));
            return;
        }
        
        // Get pagination parameters
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $from = isset($_POST['from']) ? absint($_POST['from']) : 0;
        
        // Validate limit (max 100 per Meilisearch API)
        if ($limit > 100) {
            $limit = 100;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', 'scry-wp')));
            return;
        }
        
        try {
            // Build tasks API URL
            $tasks_url = rtrim($meilisearch_url, '/') . '/tasks';
            $query_params = array(
                'limit' => $limit,
            );
            
            // Add 'from' parameter if provided (Meilisearch uses task UID for 'from')
            if ($from > 0) {
                $query_params['from'] = $from;
            }
            
            $tasks_url .= '?' . http_build_query($query_params);
            
            // Make HTTP request to Meilisearch tasks endpoint
            $response = wp_remote_get($tasks_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $meilisearch_admin_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 30,
            ));
            
            // Check for errors
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $response_body = wp_remote_retrieve_body($response);
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['message']) ? $error_data['message'] : __('HTTP error: ', 'scry-wp') . $response_code;
                throw new \Exception($error_message);
            }
            
            // Parse response
            $response_body = wp_remote_retrieve_body($response);
            $tasks_response = json_decode($response_body, true);
            
            if (!is_array($tasks_response)) {
                throw new \Exception(__('Invalid response from Meilisearch server', 'scry-wp'));
            }
            
            // Format response data
            $tasks = isset($tasks_response['results']) ? $tasks_response['results'] : array();
            $total = isset($tasks_response['total']) ? (int) $tasks_response['total'] : 0;
            $from_value = isset($tasks_response['from']) ? (int) $tasks_response['from'] : $from;
            $limit_value = isset($tasks_response['limit']) ? (int) $tasks_response['limit'] : $limit;
            
            // Format tasks for display
            $formatted_tasks = array();
            foreach ($tasks as $task) {
                $formatted_tasks[] = array(
                    'uid' => isset($task['uid']) ? (int) $task['uid'] : null,
                    'indexUid' => isset($task['indexUid']) ? esc_html($task['indexUid']) : '',
                    'status' => isset($task['status']) ? esc_html($task['status']) : '',
                    'type' => isset($task['type']) ? esc_html($task['type']) : '',
                    'details' => isset($task['details']) ? $task['details'] : array(),
                    'error' => isset($task['error']) ? $task['error'] : null,
                    'duration' => isset($task['duration']) ? esc_html($task['duration']) : null,
                    'enqueuedAt' => isset($task['enqueuedAt']) ? esc_html($task['enqueuedAt']) : '',
                    'startedAt' => isset($task['startedAt']) ? esc_html($task['startedAt']) : null,
                    'finishedAt' => isset($task['finishedAt']) ? esc_html($task['finishedAt']) : null,
                );
            }
            
            wp_send_json_success(array(
                'tasks' => $formatted_tasks,
                'total' => $total,
                'from' => $from_value,
                'limit' => $limit_value,
                'hasMore' => ($from_value + $limit_value) < $total,
            ));
            
        } catch (CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', 'scry-wp'), $e->getMessage())
            ));
        } catch (ApiException $e) {
            // API error
            wp_send_json_error(array(
                'message' => sprintf(__('API error: %s', 'scry-wp'), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', 'scry-wp'), $e->getMessage())
            ));
        }
    }
}