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
        // Make sure /logs/ and the selected .log file exist before writing.
        if (!$this->ensure_log_file($level)) {
            return false;
        }
    
        $file_path = $this->get_log_file_path($level);

        // Rotate before writing so the active file stays under the configured limit.
        if ($this->should_rotate($file_path) && !$this->rotate($level)) {
            return false;
        }

        $message = $this->sanitize_log_message($message);

        // sprintf fills the %s placeholders with level, date, time, and message.
        $line = sprintf(
            "%s %s %s - %s\n",
            $level,
            current_time('Y-m-d'),
            current_time('H:i:s'),
            $message
        );
    
        // error_log with type 3 appends the message to the destination file.
        return error_log($line, 3, $file_path);
    }


    // Reading logs method for feature
    public function read(string $level, int $start, int $lines) {
        // read() is allowed to throw because callers need to know when reading fails.
        if (!$this->ensure_log_file($level)) {
            throw new RuntimeException('Unable to access log file for level: ' . $level);
        }

        // Normalize pagination inputs so negative/zero values do not break slicing.
        $start = max(0, $start);
        $lines = max(1, $lines);
        $file_path = $this->get_log_file_path($level);

        // file() reads the log into an array where each item is one line.
        $log_lines = file($file_path, FILE_IGNORE_NEW_LINES);

        if ($log_lines === false) {
            throw new RuntimeException('Unable to read log file for level: ' . $level);
        }

        // Remove blank lines and reset array keys to 0, 1, 2...
        $log_lines = array_values(array_filter($log_lines, function($line) {
            return $line !== '';
        }));

        $total_lines = count($log_lines);

        // The file is oldest-first, but pagination starts from the newest entry.
        $newest_first = array_reverse($log_lines);
        $selected_lines = array_slice($newest_first, $start, $lines);

        // Display the selected chunk oldest-to-newest so the log reads naturally.
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

    // Zipping files method for feature
    public function rotate(string $level) {
        $file_path = $this->get_log_file_path($level);

        // If there is no active log file yet, rotation is already "done."
        if (!$file_path || !file_exists($file_path)) {
            return true;
        }

        $zip_path = $file_path . '.zip';

        // The spec only keeps one zip per level, so remove the previous archive.
        if (file_exists($zip_path) && !unlink($zip_path)) {
            return false;
        }

        // ZipArchive is PHP's built-in class for creating zip files.
        $zip = new ZipArchive();

        // CREATE opens an existing zip or creates a new one at $zip_path.
        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            return false;
        }

        // Store only "error.log" or "debug.log" inside the zip, not the full path.
        if (!$zip->addFile($file_path, basename($file_path))) {
            $zip->close();
            return false;
        }

        // close() finalizes the zip file on disk.
        if (!$zip->close()) {
            return false;
        }

        // Empty the active log file so new logs continue writing to the same path.
        return file_put_contents($file_path, '') !== false;
    }

    // Method to get the logs config
    private function get_log_config() {
        // Pulls the 'logs' array from the plugin config in scry_search.php.
        return $this->config('logs');
    }

    // Method to get the logs files directory path
    private function get_log_directory_path() {
        $logs_config = $this->get_log_config();
        $directory = isset($logs_config['directory']) ? $logs_config['directory'] : 'logs';

        // get_base_dir() is the plugin root; trailingslashit makes path joining safe.
        return trailingslashit($this->get_base_dir()) . trailingslashit($directory);
    }

    // Method to get the log file path
    private function get_log_file_path(string $level) {
        $logs_config = $this->get_log_config();

        // Config maps each valid level to its log filename.
        if (isset($logs_config['levels'][$level])) {
            return $this->get_log_directory_path() . $logs_config['levels'][$level];
        }
    
        return false;
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

    // Method to ensure the log file exists and is writable (returns false otherwise)
    private function ensure_log_file(string $level): bool {
        $directory = $this->get_log_directory_path();
        $file_path = $this->get_log_file_path($level);
    
        // Invalid levels do not have a file path.
        if (!$file_path) {
            return false;
        }
    
        // wp_mkdir_p creates the directory and any missing parent directories.
        if (!file_exists($directory) && !wp_mkdir_p($directory)) {
            return false;
        }
    
        // touch creates the file if it does not exist.
        if (!file_exists($file_path) && !touch($file_path)) {
            return false;
        }
    
        // Final guard: PHP must be able to write to the file.
        return is_writable($file_path);
    }

    // Method to determine whether a log file is beyond the configured size limit
    private function should_rotate(string $file_path): bool {
        $logs_config = $this->get_log_config();
        $max_file_size = isset($logs_config['max_file_size']) ? absint($logs_config['max_file_size']) : 0;

        // A missing or zero max size disables rotation.
        if ($max_file_size <= 0 || !file_exists($file_path)) {
            return false;
        }
        
        // check if the file size is greater than the max file size
        return filesize($file_path) >= $max_file_size;
    }

}