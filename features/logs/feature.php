<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScrySearch_LogsFeature extends PluginFeature {
    
    public function add_filters() {
        // No filters needed
    }
    
    public function add_actions() {

        // Register actions for admin page and assets
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

    }

    // Admin Page Method
    public function add_admin_page() {

        $admin_page_feature = $this->get_feature('scry_ms_admin_page');

        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scry-search-meilisearch-logs',
                __('Logs', "scry-search"),
                'dashicons-admin-generic',
                __('View plugin debug and error logs.', "scry-search")
            );
        }

        add_submenu_page(
            'scry-search-meilisearch',
            __('Logs', "scry-search"),
            __('Logs', "scry-search"),
            'manage_options',
            'scry-search-meilisearch-logs',
            function() {
                $file_path = plugin_dir_path(__FILE__) . 'elements/logs_page.php';
                $this->get_feature('scry_ms_admin_page')->render_admin_page($file_path);
            }
        );
    }

    // Enqueue assets for the logs admin page
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'scry-search_page_scry-search-meilisearch-logs') {
            return;
        }

        wp_enqueue_style(
            $this->prefixed('logs-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/logs.css',
            array(),
            '1.0.0'
        );
    }

    // logging method for feature
    public function log(string $level, string $message) {
        if (!$this->ensure_log_file($level)) {
            return false;
        }
    
        $file_path = $this->get_log_file_path($level);
        $line = sprintf(
            "%s %s %s - %s\n",
            $level,
            current_time('Y-m-d'),
            current_time('H:i:s'),
            $message
        );
    
        return error_log($line, 3, $file_path);
    }


    // Reading logs method for feature
    public function read(string $level, int $start, int $lines) {
        
        
    }

    // Zipping files method for feature
    public function rotate(string $level) {

        
    }

    // Method to get the logs config
    private function get_log_config() {
        return $this->config('logs');
    }

    // Method to get the logs files directory path
    private function get_log_directory_path() {
        return trailingslashit($this->get_base_dir()) . 'logs/';
    }

    // Method to get the log file path
    private function get_log_file_path(string $level) {
        if ($level === 'debug') {
            return $this->get_log_directory_path() . 'debug.log';
        }
    
        if ($level === 'error') {
            return $this->get_log_directory_path() . 'error.log';
        }
    
        return false;
    }

    // Method to ensure the log file exists and is writable (returns false otherwise)
    private function ensure_log_file(string $level): bool {
        $directory = $this->get_log_directory_path();
        $file_path = $this->get_log_file_path($level);
    
        if (!$file_path) {
            return false;
        }
    
        if (!file_exists($directory) && !wp_mkdir_p($directory)) {
            return false;
        }
    
        if (!file_exists($file_path) && !touch($file_path)) {
            return false;
        }
    
        return is_writable($file_path);
    }

}