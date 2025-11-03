<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

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
            array('jquery'),
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
}