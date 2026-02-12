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
use Meilisearch\Contracts\TasksQuery;

class ScrySearch_AdminPageFeature extends PluginFeature {
    
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
     * @param string $page_slug The page slug (e.g., 'scry-search-meilisearch-settings')
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
            'scry-search-meilisearch',
            __('Overview', "scry-search"),
            'dashicons-search',
            __('Welcome to Scry Search for Meilisearch. Configure your search settings and manage indexes.', "scry-search")
        );
        
        add_menu_page(
            'Scry Search', // Page title
            'Scry Search', // Menu title
            'manage_options', // Capability required
            'scry-search-meilisearch', // Menu slug
            function() {
                $file_path = plugin_dir_path(__FILE__) . 'elements/main_page.php';
                $this->render_admin_page($file_path);
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
        // Main page hook: 'toplevel_page_scry-search-meilisearch'
        // Submenu page hook format: 'scry-search-meilisearch_page_{submenu-slug}'
        // WordPress uses the full submenu slug in the hook
        
        // Check if this is the main page
        if ($hook === 'toplevel_page_scry-search-meilisearch') {
            $this->enqueue_assets();
            // Enqueue main page specific styles
            wp_enqueue_style(
                $this->prefixed('admin-page-styles'),
                plugin_dir_url(__FILE__) . 'assets/css/page.css',
                array(),
                '1.0.0'
            );
            return;
        }


        
        // Check if this is any submenu page under scry-search-meilisearch
        // WordPress formats submenu hooks as: {parent-slug}_page_{submenu-slug}
        if (strpos($hook, 'scry-search_page_') === 0) {
            // Verify this page is registered using the registration system
            $registered_pages = $this->get_registered_pages();
            $page_slug = str_replace('scry-search_page_', '', $hook);
            
            // Only enqueue if page is registered
            if (isset($registered_pages[$page_slug])) {
                $this->enqueue_assets();
            }
            return;
        }
    }
    
    /**
     * Enqueue the actual CSS and JS assets for all admin pages
     */
    private function enqueue_assets() {
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
    public function render_admin_page($file_path) {
        require_once plugin_dir_path(__FILE__) . 'elements/base_layout.php';
    }
    
    /**
     * AJAX handler for fetching tasks from Meilisearch
     */
    public function ajax_get_tasks() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('get_tasks'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }
        
        // Get pagination parameters
        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 20;
        $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
        
        // Validate limit (max 100 per Meilisearch API)
        if ($limit > 100) {
            $limit = 100;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry-search")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $meilisearch_admin_key);
            
            // First, get total count to calculate reverse pagination
            $count_query = (new TasksQuery())->setLimit(1);
            $count_response = $client->getTasks($count_query);
            
            // Handle response - could be array or object with getTotal() method
            if (is_array($count_response)) {
                $total = isset($count_response['total']) ? (int) $count_response['total'] : 0;
            } else {
                $total = method_exists($count_response, 'getTotal') ? $count_response->getTotal() : 0;
            }
            
            // Calculate pagination
            $current_page = $page > 0 ? $page : 1;
            $total_pages = $total > 0 ? (int) ceil($total / $limit) : 1;
            
            // Build tasks query
            // Meilisearch tasks API: `from` is a task UID (not an offset).
            // Tasks are returned in descending UID order starting from `from`.
            // If `from` is not set, it starts from the newest task (highest UID).
            //
            // Page 1: don't set `from` → returns newest tasks
            // Page N: set `from` to skip the first (N-1)*limit newest tasks
            //   Approximation: from_uid = total - 1 - ((N-1) * limit)
            $tasks_query = (new TasksQuery())->setLimit($limit);

            if ($current_page > 1) {
                $from_uid = max(0, $total - 1 - (($current_page - 1) * $limit));
                $tasks_query->setFrom($from_uid);
            }
            
            $tasks_response = $client->getTasks($tasks_query);
            
            // Format response data - handle both array and object responses
            if (is_array($tasks_response)) {
                $tasks = isset($tasks_response['results']) ? $tasks_response['results'] : array();
            } else {
                $tasks = method_exists($tasks_response, 'getResults') ? $tasks_response->getResults() : array();
            }
            
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
                'limit' => $limit,
                'currentPage' => $current_page,
                'totalPages' => $total_pages,
                'hasMore' => $current_page < $total_pages,
            ));
            
        } catch (CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage())
            ));
        } catch (ApiException $e) {
            // API error
            wp_send_json_error(array(
                'message' => sprintf(__('API error: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry-search"), $e->getMessage())
            ));
        }
    }
}