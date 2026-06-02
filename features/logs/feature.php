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
        add_action('wp_ajax_' . $this->prefixed('load_logs'), array($this, 'ajax_load_logs'));

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

        //exit if not the logs page
        if ($hook !== 'scry-search_page_scry-search-meilisearch-logs') {
            return;
        }

        //enqueue the styles
        wp_enqueue_style(
            $this->prefixed('logs-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/logs.css',
            array(),
            '1.0.0'
        );

        //enqueue the script
        wp_enqueue_script(
            $this->prefixed('logs-script'),
            plugin_dir_url(__FILE__) . 'assets/js/logs.js',
            array(),
            '1.0.0',
            true
        );

        //get the log config
        $logs_config = $this->get_log_config();
        $page_size = isset($logs_config['page_size']) ? absint($logs_config['page_size']) : 100;

        //localize the script with the log config
        wp_localize_script(
            $this->prefixed('logs-script'),
            'scrywpLogs',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => $this->prefixed('load_logs'),
                'nonce' => wp_create_nonce($this->prefixed('load_logs')),
                'pageSize' => $page_size,
                'i18n' => array(
                    'loading' => __('Loading...', "scry-search"),
                    'error' => __('Unable to load logs.', "scry-search"),
                    'noMore' => __('No older log messages to load.', "scry-search"),
                ),
            )
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
        if (!$this->ensure_log_file($level)) {
            throw new RuntimeException('Unable to access log file for level: ' . $level);
        }

        $start = max(0, $start);
        $lines = max(1, $lines);
        $file_path = $this->get_log_file_path($level);
        $log_lines = file($file_path, FILE_IGNORE_NEW_LINES);

        if ($log_lines === false) {
            throw new RuntimeException('Unable to read log file for level: ' . $level);
        }

        $log_lines = array_values(array_filter($log_lines, function($line) {
            return $line !== '';
        }));

        $total_lines = count($log_lines);
        $newest_first = array_reverse($log_lines);
        $selected_lines = array_slice($newest_first, $start, $lines);
        $selected_lines = array_reverse($selected_lines);
        $next_start = $start + count($selected_lines);

        return array(
            'lines' => $selected_lines,
            'start' => $start,
            'next_start' => $next_start,
            'has_more' => $next_start < $total_lines,
            'total' => $total_lines,
        );
    }

    // AJAX handler for loading older log messages
    public function ajax_load_logs() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('load_logs'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        $level = isset($_POST['level']) ? sanitize_text_field(wp_unslash($_POST['level'])) : '';
        $start = isset($_POST['start']) ? absint(wp_unslash($_POST['start'])) : 0;
        $lines = isset($_POST['lines']) ? absint(wp_unslash($_POST['lines'])) : 100;

        try {
            $log_data = $this->read($level, $start, $lines);
            wp_send_json_success($log_data);
        } catch (Throwable $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
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
        $logs_config = $this->get_log_config();

        if (isset($logs_config['levels'][$level])) {
            return $this->get_log_directory_path() . $logs_config['levels'][$level];
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