<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScrySearch_LogsFeature extends PluginFeature {

    /**
     * Current database schema version
     */
    private $db_version = '1.0';
    
    public function add_filters() {
        // No filters needed
    }
    
    public function add_actions() {

        // admin_menu is where WordPress lets plugins add wp-admin menu pages.
        add_action('admin_menu', array($this, 'add_admin_page'));

        // admin_enqueue_scripts is where wp-admin CSS/JS files should be loaded.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Ensure the logs database table exists before admins use the logs screen.
        add_action('admin_init', array($this, 'maybe_create_table'));

        // WordPress AJAX maps POST action=scry_ms_load_logs to this PHP callback.
        add_action('wp_ajax_' . $this->prefixed('load_logs'), array($this, 'ajax_load_logs'));

    }

    // =========================================================================
    // Database Table
    // =========================================================================

    /**
     * Get the full logs table name
     */
    public function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . $this->prefixed('logs');
    }

    /**
     * Check if the logs table needs to be created or updated
     */
    public function maybe_create_table() {
        $installed_version = get_option($this->prefixed('logs_db_version'), '0');
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->create_logs_table();
            update_option($this->prefixed('logs_db_version'), $this->db_version);
        }
    }

    /**
     * Create the custom table for plugin logs
     */
    public function create_logs_table() {
        global $wpdb;

        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL,
            message longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_type (type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Admin Page Method
    public function add_admin_page() {

        // Register this page with the plugin's shared tab/navigation layout.
        $admin_page_feature = $this->get_feature('scry_ms_admin_page');

        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scry-search-meilisearch-logs',
                __('Logs', "scry-search"),
                'dashicons-admin-generic',
                __('View plugin debug and error logs.', "scry-search")
            );
        }

        // add_submenu_page creates the actual WordPress admin page URL/menu item.
        add_submenu_page(
            'scry-search-meilisearch',
            __('Logs', "scry-search"),
            __('Logs', "scry-search"),
            'manage_options',
            'scry-search-meilisearch-logs',
            function() {
                // Render this feature's page inside the shared Scry Search admin wrapper.
                $file_path = plugin_dir_path(__FILE__) . 'elements/logs_page.php';
                $this->get_feature('scry_ms_admin_page')->render_admin_page($file_path);
            }
        );
    }

    // Enqueue assets for the logs admin page
    public function enqueue_admin_assets($hook) {

        // WordPress passes the current admin page id as $hook; only load assets here.
        if ($hook !== 'scry-search_page_scry-search-meilisearch-logs') {
            return;
        }

        // wp_enqueue_style tells WordPress to include this CSS file on the page.
        wp_enqueue_style(
            $this->prefixed('logs-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/logs.css',
            array(),
            '1.0.0'
        );

        // wp_enqueue_script tells WordPress to include this JS file on the page.
        wp_enqueue_script(
            $this->prefixed('logs-script'),
            plugin_dir_url(__FILE__) . 'assets/js/logs.js',
            array(),
            '1.0.0',
            true
        );

        // Pass the configured page size into JavaScript for AJAX pagination.
        $logs_config = $this->get_log_config();
        $page_size = isset($logs_config['page_size']) ? absint($logs_config['page_size']) : 100;

        // wp_localize_script exposes PHP values to logs.js as window.scrywpLogs.
        wp_localize_script(
            $this->prefixed('logs-script'),
            'scrywpLogs',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => $this->prefixed('load_logs'),
                'nonce' => wp_create_nonce($this->prefixed('load_logs')),  // Nonces help prove the AJAX request came from this admin page.
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
        global $wpdb;

        $logs_config = $this->get_log_config();

        if (!isset($logs_config['levels'][$level])) {
            return false;
        }

        $message = $this->sanitize_log_message($message);

        $result = $wpdb->insert(
            $this->get_table_name(),
            array(
                'type' => sanitize_text_field($level),
                'message' => $message,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );

        return $result !== false;
    }


    // Reading logs method for feature
    public function read(string $level, int $start, int $lines) {
        global $wpdb;

        $logs_config = $this->get_log_config();

        // read() is allowed to throw because callers need to know when reading fails.
        if (!isset($logs_config['levels'][$level])) {
            throw new RuntimeException('Invalid log level: ' . $level);
        }

        // Normalize pagination inputs so negative/zero values do not break queries.
        $start = max(0, $start);
        $lines = max(1, $lines);

        $table_name = $this->get_table_name();

        // Count all rows for this type so the UI knows whether "Load more" is needed.
        $total_lines = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE type = %s",
                $level
            )
        );

        // Pagination starts from the newest rows, using $start as the offset.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT type, message, created_at FROM $table_name WHERE type = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $level,
                $lines,
                $start
            ),
            ARRAY_A
        );

        if ($rows === null) {
            throw new RuntimeException('Unable to read logs from the database: ' . $wpdb->last_error);
        }

        // The query returns newest-first, but the viewer reads naturally oldest-to-newest.
        $rows = array_reverse($rows);
        $selected_lines = array_map(function($row) {
            return sprintf(
                '[%s] %s: %s',
                $row['created_at'],
                strtoupper($row['type']),
                $row['message']
            );
        }, $rows);
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
        // Verify the nonce that was created in wp_localize_script().
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('load_logs'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        // Only administrators should be able to read plugin log files.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        // Values arrive from logs.js as POST data, so sanitize before using them.
        $level = isset($_POST['level']) ? sanitize_text_field(wp_unslash($_POST['level'])) : '';
        $start = isset($_POST['start']) ? absint(wp_unslash($_POST['start'])) : 0;
        $lines = isset($_POST['lines']) ? absint(wp_unslash($_POST['lines'])) : 100;
        $logs_config = $this->get_log_config();
        $page_size = isset($logs_config['page_size']) ? absint($logs_config['page_size']) : 100;
        $lines = max(1, min($lines, $page_size));

        try {
            $log_data = $this->read($level, $start, $lines);
            // Sends JSON back to fetch() in logs.js.
            wp_send_json_success($log_data);
        } catch (Throwable $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    // Method to get the logs config
    private function get_log_config() {
        // Pulls the 'logs' array from the plugin config in scry_search.php.
        return $this->config('logs');
    }

    // Method to keep log messages single-line and remove common secret formats
    private function sanitize_log_message(string $message): string {
        // Collapse newlines/tabs/spaces so one log call cannot forge multiple entries.
        $message = preg_replace('/\s+/', ' ', trim($message));

        // Redact common Authorization bearer token formats from exception dumps.
        $message = preg_replace('/Authorization:\s*Bearer\s+[^\s\]]+/i', 'Authorization: Bearer [REDACTED]', $message);
        $message = preg_replace('/Bearer\s+[A-Za-z0-9+\/=_-]+/i', 'Bearer [REDACTED]', $message);

        return $message;
    }

}