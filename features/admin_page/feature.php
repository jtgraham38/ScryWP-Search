<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScryWpAdminPageFeature extends PluginFeature {
    
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
     * Register the admin menu page
     */
    public function register_admin_menu() {
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
        // Only load assets on our admin page
        if ($hook !== 'toplevel_page_scrywp-search') {
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

    /*
    * Render an admin page in the base layout
    */
    public function render_admin_page($content) {
        require_once plugin_dir_path(__FILE__) . 'elements/base_layout.php';
    }
}