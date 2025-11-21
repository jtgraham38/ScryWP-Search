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
            __('Overview', "scry_search_meilisearch"),
            'dashicons-search',
            __('Welcome to Scry Search for Meilisearch. Configure your search settings and manage indexes.', "scry_search_meilisearch")
        );
        
        add_menu_page(
            'Scry Search', // Page title
            'Scry Search', // Menu title
            'manage_options', // Capability required
            'scry-search-meilisearch', // Menu slug
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
        // Main page hook: 'toplevel_page_scry-search-meilisearch'
        // Submenu page hook format: 'scry-search-meilisearch_page_{submenu-slug}'
        // WordPress uses the full submenu slug in the hook
        
        // Check if this is the main page
        if ($hook === 'toplevel_page_scry-search-meilisearch') {
            $this->enqueue_assets();
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
     * Enqueue the actual CSS and JS assets
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
    public function render_admin_page($content) {
        require_once plugin_dir_path(__FILE__) . 'elements/base_layout.php';
    }
    
    /**
     * AJAX handler for fetching tasks from Meilisearch
     */
    public function ajax_get_tasks() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('get_tasks'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry_search_meilisearch")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry_search_meilisearch")));
            return;
        }
        
        // Get pagination parameters
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        
        // Validate limit (max 100 per Meilisearch API)
        if ($limit > 100) {
            $limit = 100;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry_search_meilisearch")));
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
            $total_pages = $total > 0 ? ceil($total / $limit) : 1;
            
            // Calculate reverse offset for pagination
            // Meilisearch orders tasks oldest first by default (lowest UID to highest UID)
            // So: offset 0 = oldest tasks (UID 0), offset (total - limit) = newest tasks (highest UID)
            // To get newest on page 1 and oldest on last page, we reverse the offset:
            // - Page 1: offset = (total_pages - 1) * limit = newest tasks (highest UIDs)
            // - Page N: offset = (total_pages - N) * limit = older tasks
            // - Last page: offset = 0 = oldest tasks (UID 0)
            // Example: If total = 1913, limit = 20, total_pages = 96:
            // - Page 1: offset = (96 - 1) * 20 = 1900 = newest tasks (UIDs 1912-1893)
            // - Page 96: offset = (96 - 96) * 20 = 0 = oldest tasks (UIDs 0-19)
            $offset = ($total_pages - $current_page) * $limit;
            
            // Ensure offset doesn't exceed total - limit (maximum valid offset)
            $max_offset = max(0, $total - $limit);
            if ($offset > $max_offset) {
                $offset = $max_offset;
            }
            
            // Ensure offset is at least 0 (since UIDs start at 0)
            if ($offset < 0) {
                $offset = 0;
            }
            
            // Get tasks using Meilisearch PHP client with TasksQuery
            // Meilisearch returns tasks ordered newest first by default
            // So page 1 will show newest tasks (highest UIDs) and last page will show oldest (lowest UIDs)
            $tasks_query = (new TasksQuery())
                ->setLimit($limit)
                ->setFrom($offset);
            
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
                'from' => $offset,
                'limit' => $limit,
                'currentPage' => $current_page,
                'totalPages' => $total_pages,
                'hasMore' => $current_page < $total_pages,
            ));
            
        } catch (CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (ApiException $e) {
            // API error
            wp_send_json_error(array(
                'message' => sprintf(__('API error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        }
    }
}