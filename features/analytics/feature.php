<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScrySearch_AnalyticsFeature extends PluginFeature {

    /**
     * Current database schema version
     */
    private $db_version = '1.0';
    
    public function add_filters() {
        // No filters needed
    }
    
    public function add_actions() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'maybe_create_table'));
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_' . $this->prefixed('get_analytics_searches'), array($this, 'ajax_get_analytics_searches'));
        add_action('wp_ajax_' . $this->prefixed('get_analytics_summary'), array($this, 'ajax_get_analytics_summary'));
        add_action('wp_ajax_' . $this->prefixed('get_analytics_top_terms'), array($this, 'ajax_get_analytics_top_terms'));
        add_action('wp_ajax_' . $this->prefixed('get_analytics_term_trend'), array($this, 'ajax_get_analytics_term_trend'));
    }

    // =========================================================================
    // Database Table
    // =========================================================================

    /**
     * Get the full table name
     */
    public function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . $this->prefixed('search_analytics');
    }

    /**
     * Check if the table needs to be created or updated
     */
    public function maybe_create_table() {
        $installed_version = get_option($this->prefixed('analytics_db_version'), '0');
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->create_search_analytics_table();
            update_option($this->prefixed('analytics_db_version'), $this->db_version);
        }
    }

    /**
     * Create the custom table for tracking search analytics
     */
    public function create_search_analytics_table() {
        global $wpdb;

        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            search_term varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            user_ip varchar(45) NOT NULL DEFAULT '',
            user_agent text DEFAULT '',
            referrer varchar(2048) DEFAULT '',
            result_count int(11) NOT NULL DEFAULT 0,
            result_ids longtext DEFAULT '',
            result_titles longtext DEFAULT '',
            post_types_searched text DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_search_term (search_term),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_result_count (result_count)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // =========================================================================
    // Insert Analytics Event
    // =========================================================================

    /**
     * Insert a search analytics event into the database
     *
     * @param array $event Event data with keys: search_term, result_count, result_ids, result_titles, post_types_searched
     * @return bool True on success
     */
    public function insert_search_analytics_event(array $event): bool {
        global $wpdb;

        $table_name = $this->get_table_name();

        // Capture user data
        $user_id = get_current_user_id();
        $user_ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $referrer = wp_get_referer();
        if (!$referrer && isset($_SERVER['HTTP_REFERER'])) {
            $referrer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }
        if (!$referrer) {
            $referrer = '';
        }

        // Check GDPR anonymization setting
        $anonymize = get_option($this->prefixed('anonymize_analytics'), '0');
        if ($anonymize === '1') {
            $user_id = 0;
            $user_ip = wp_hash($user_ip);
            $user_agent = '';
            $referrer = '';
        }

        $search_term = isset($event['search_term']) ? sanitize_text_field($event['search_term']) : '';
        if (empty($search_term)) {
            return false;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'search_term'        => $search_term,
                'user_id'            => absint($user_id),
                'user_ip'            => sanitize_text_field($user_ip),
                'user_agent'         => $user_agent,
                'referrer'           => esc_url_raw($referrer),
                'result_count'       => isset($event['result_count']) ? absint($event['result_count']) : 0,
                'result_ids'         => wp_json_encode(isset($event['result_ids']) ? array_map('absint', $event['result_ids']) : array()),
                'result_titles'      => wp_json_encode(isset($event['result_titles']) ? array_map('sanitize_text_field', $event['result_titles']) : array()),
                'post_types_searched' => wp_json_encode(isset($event['post_types_searched']) ? array_map('sanitize_text_field', $event['post_types_searched']) : array()),
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        return $result !== false;
    }

    /**
     * Get the client IP address with proxy support
     */
    private function get_client_ip(): string {
        $ip = '';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REAL_IP']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    // =========================================================================
    // Query Analytics
    // =========================================================================

    /**
     * Query the analytics table with flexible filtering, sorting, and pagination
     *
     * @param array $args Query arguments
     * @return array Results with pagination info
     */
    public function query_search_analytics(array $args = array()): array {
        global $wpdb;

        $table_name = $this->get_table_name();

        // Defaults
        $defaults = array(
            'search_term'      => '',
            'search_term_like' => '',
            'user_id'          => null,
            'user_ip'          => '',
            'result_count'     => null,
            'result_count_min' => null,
            'result_count_max' => null,
            'has_results'      => null,
            'date_from'        => '',
            'date_to'          => '',
            'orderby'          => 'created_at',
            'order'            => 'DESC',
            'per_page'         => 20,
            'page'             => 1,
            'count_only'       => false,
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clauses
        $where = array();
        $values = array();

        if (!empty($args['search_term'])) {
            $where[] = 'search_term = %s';
            $values[] = $args['search_term'];
        }

        if (!empty($args['search_term_like'])) {
            $where[] = 'search_term LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['search_term_like']) . '%';
        }

        if ($args['user_id'] !== null) {
            $where[] = 'user_id = %d';
            $values[] = absint($args['user_id']);
        }

        if (!empty($args['user_ip'])) {
            $where[] = 'user_ip = %s';
            $values[] = $args['user_ip'];
        }

        if ($args['result_count'] !== null) {
            $where[] = 'result_count = %d';
            $values[] = absint($args['result_count']);
        }

        if ($args['result_count_min'] !== null) {
            $where[] = 'result_count >= %d';
            $values[] = absint($args['result_count_min']);
        }

        if ($args['result_count_max'] !== null) {
            $where[] = 'result_count <= %d';
            $values[] = absint($args['result_count_max']);
        }

        if ($args['has_results'] !== null) {
            if ($args['has_results']) {
                $where[] = 'result_count > 0';
            } else {
                $where[] = 'result_count = 0';
            }
        }

        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = sanitize_text_field($args['date_from']) . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = sanitize_text_field($args['date_to']) . ' 23:59:59';
        }

        $where_sql = '';
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
        }

        // Count query
        $count_sql = "SELECT COUNT(*) FROM $table_name $where_sql";
        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        if ($args['count_only']) {
            return array(
                'results'  => array(),
                'total'    => $total,
                'page'     => (int) $args['page'],
                'per_page' => (int) $args['per_page'],
                'pages'    => 0,
            );
        }

        // Validate orderby to prevent SQL injection
        $allowed_orderby = array('id', 'search_term', 'user_id', 'user_ip', 'result_count', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Pagination
        $per_page = max(1, (int) $args['per_page']);
        $page = max(1, (int) $args['page']);
        $offset = ($page - 1) * $per_page;
        $pages = (int) ceil($total / $per_page);

        // Data query
        $data_sql = "SELECT * FROM $table_name $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $data_values = array_merge($values, array($per_page, $offset));
        $results = $wpdb->get_results($wpdb->prepare($data_sql, $data_values), ARRAY_A);

        return array(
            'results'  => $results ? $results : array(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => $pages,
        );
    }

    // =========================================================================
    // Settings
    // =========================================================================

    /**
     * Register WordPress settings
     */
    public function register_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Register the GDPR settings section
        add_settings_section(
            $this->prefixed('analytics_gdpr_section'),
            __('Privacy Settings', "scry-search"),
            function() {
                echo '<p>' . esc_html__('Configure privacy and data collection settings for search analytics.', "scry-search") . '</p>';
            },
            $this->prefixed('analytics_settings_group')
        );

        // Add the anonymize checkbox field
        add_settings_field(
            $this->prefixed('anonymize_analytics'),
            __('GDPR Anonymization', "scry-search"),
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/settings/anonymize_input.php';
            },
            $this->prefixed('analytics_settings_group'),
            $this->prefixed('analytics_gdpr_section')
        );

        // Register the anonymize setting
        register_setting(
            $this->prefixed('analytics_settings_group'),
            $this->prefixed('anonymize_analytics'),
            array(
                'type'              => 'string',
                'description'       => 'Enable GDPR anonymization for search analytics data.',
                'sanitize_callback' => function($input) {
                    return $input === '1' ? '1' : '0';
                },
                'default'           => '0',
                'show_in_rest'      => false,
            )
        );
    }

    // =========================================================================
    // Admin Page
    // =========================================================================

    /**
     * Add the analytics admin page
     */
    public function add_admin_page() {
        // Register this page with the admin page feature for tab navigation
        $admin_page_feature = $this->get_feature('scry_ms_admin_page');
        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scry-search-meilisearch-analytics',
                __('Search Analytics', "scry-search"),
                'dashicons-chart-area',
                __('View search analytics, trends, and insights.', "scry-search")
            );
        }

        add_submenu_page(
            'scry-search-meilisearch',
            __('Search Analytics', "scry-search"),
            __('Search Analytics', "scry-search"),
            'manage_options',
            'scry-search-meilisearch-analytics',
            function() {
                $file_path = plugin_dir_path(__FILE__) . 'elements/analytics_dashboard.php';
                $this->get_feature('scry_ms_admin_page')->render_admin_page($file_path);
            }
        );
    }

    /**
     * Enqueue admin assets for the analytics page
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our analytics page
        if ($hook !== 'scry-search_page_scry-search-meilisearch-analytics') {
            return;
        }

        // Chart.js from CDN
        wp_enqueue_script(
            $this->prefixed('chartjs'),
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js',
            array(),
            '4.4.7',
            true
        );

        // Analytics CSS
        wp_enqueue_style(
            $this->prefixed('analytics-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/analytics.css',
            array(),
            '1.0.0'
        );

        // Analytics JS
        wp_enqueue_script(
            $this->prefixed('analytics-script'),
            plugin_dir_url(__FILE__) . 'assets/js/analytics.js',
            array('jquery', $this->prefixed('chartjs')),
            '1.0.0',
            true
        );

        wp_localize_script(
            $this->prefixed('analytics-script'),
            'scrywpAnalytics',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'actions' => array(
                    'getSearches'  => $this->prefixed('get_analytics_searches'),
                    'getSummary'   => $this->prefixed('get_analytics_summary'),
                    'getTopTerms'  => $this->prefixed('get_analytics_top_terms'),
                    'getTermTrend' => $this->prefixed('get_analytics_term_trend'),
                ),
                'nonces' => array(
                    'getSearches'  => wp_create_nonce($this->prefixed('get_analytics_searches')),
                    'getSummary'   => wp_create_nonce($this->prefixed('get_analytics_summary')),
                    'getTopTerms'  => wp_create_nonce($this->prefixed('get_analytics_top_terms')),
                    'getTermTrend' => wp_create_nonce($this->prefixed('get_analytics_term_trend')),
                ),
                'i18n' => array(
                    'loading'       => __('Loading...', "scry-search"),
                    'noData'        => __('No analytics data available yet.', "scry-search"),
                    'error'         => __('An error occurred while loading data.', "scry-search"),
                    'anonymous'     => __('Anonymous', "scry-search"),
                    'searches'      => __('Searches', "scry-search"),
                    'showResults'   => __('Show Results', "scry-search"),
                    'hideResults'   => __('Hide Results', "scry-search"),
                    'previous'      => __('Previous', "scry-search"),
                    'next'          => __('Next', "scry-search"),
                    'pageOf'        => __('Page %1$s of %2$s', "scry-search"),
                    'allTerms'      => __('All search terms', "scry-search"),
                    'searchVolume'  => __('Search Volume', "scry-search"),
                    'topTerms'      => __('Top Search Terms', "scry-search"),
                ),
            )
        );
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    /**
     * AJAX: Get paginated recent searches
     */
    public function ajax_get_analytics_searches() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('get_analytics_searches'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        $args = array(
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'page'     => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'orderby'  => isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'created_at',
            'order'    => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'DESC',
        );

        if (!empty($_POST['search_term_like'])) {
            $args['search_term_like'] = sanitize_text_field(wp_unslash($_POST['search_term_like']));
        }

        if (!empty($_POST['date_from'])) {
            $args['date_from'] = sanitize_text_field(wp_unslash($_POST['date_from']));
        }

        if (!empty($_POST['date_to'])) {
            $args['date_to'] = sanitize_text_field(wp_unslash($_POST['date_to']));
        }

        if (isset($_POST['has_results']) && $_POST['has_results'] !== '') {
            $args['has_results'] = sanitize_text_field(wp_unslash($_POST['has_results'])) === '0' ? false : true;
        }

        $data = $this->query_search_analytics($args);

        // Enrich results with user display names
        foreach ($data['results'] as &$row) {
            $row['result_ids'] = json_decode($row['result_ids'], true);
            $row['result_titles'] = json_decode($row['result_titles'], true);
            $row['post_types_searched'] = json_decode($row['post_types_searched'], true);

            if (!empty($row['user_id'])) {
                $user = get_userdata((int) $row['user_id']);
                $row['user_display_name'] = $user ? esc_html($user->display_name) : __('Unknown User', "scry-search");
            } else {
                $row['user_display_name'] = __('Anonymous', "scry-search");
            }

            $row['created_at_formatted'] = date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($row['created_at'])
            );
        }
        unset($row);

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get analytics summary stats
     */
    public function ajax_get_analytics_summary() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('get_analytics_summary'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        global $wpdb;
        $table_name = $this->get_table_name();

        $total_searches = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $searches_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        $unique_terms = (int) $wpdb->get_var("SELECT COUNT(DISTINCT search_term) FROM $table_name");
        $zero_results = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE result_count = 0");
        $avg_results = (float) $wpdb->get_var("SELECT AVG(result_count) FROM $table_name");

        // Unique searchers: count distinct combinations of user_id and user_ip
        $unique_searchers = (int) $wpdb->get_var("SELECT COUNT(DISTINCT CONCAT(user_id, '|', user_ip)) FROM $table_name");

        wp_send_json_success(array(
            'total_searches'   => $total_searches,
            'searches_today'   => $searches_today,
            'unique_terms'     => $unique_terms,
            'zero_results'     => $zero_results,
            'avg_results'      => round($avg_results, 1),
            'unique_searchers' => $unique_searchers,
        ));
    }

    /**
     * AJAX: Get top search terms for the bar chart
     */
    public function ajax_get_analytics_top_terms() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('get_analytics_top_terms'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        global $wpdb;
        $table_name = $this->get_table_name();

        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 15;
        if ($limit < 1 || $limit > 50) {
            $limit = 15;
        }

        $where = '';
        $values = array();

        if (!empty($_POST['date_from'])) {
            $where .= ' AND created_at >= %s';
            $values[] = sanitize_text_field(wp_unslash($_POST['date_from'])) . ' 00:00:00';
        }
        if (!empty($_POST['date_to'])) {
            $where .= ' AND created_at <= %s';
            $values[] = sanitize_text_field(wp_unslash($_POST['date_to'])) . ' 23:59:59';
        }

        $sql = "SELECT search_term, COUNT(*) as count FROM $table_name WHERE 1=1 $where GROUP BY search_term ORDER BY count DESC LIMIT %d";
        $values[] = $limit;

        $results = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        wp_send_json_success(array(
            'terms' => $results ? $results : array(),
        ));
    }

    /**
     * AJAX: Get search term trend data for the line chart
     */
    public function ajax_get_analytics_term_trend() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('get_analytics_term_trend'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        global $wpdb;
        $table_name = $this->get_table_name();

        $days = isset($_POST['days']) ? absint(wp_unslash($_POST['days'])) : 30;
        if ($days < 1 || $days > 365) {
            $days = 30;
        }

        $where = '';
        $values = array();

        // Date range: last N days
        $where .= ' AND created_at >= %s';
        $values[] = gmdate('Y-m-d', strtotime("-{$days} days")) . ' 00:00:00';

        // Optional: filter by a specific search term
        if (!empty($_POST['term'])) {
            $where .= ' AND search_term = %s';
            $values[] = sanitize_text_field(wp_unslash($_POST['term']));
        }

        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM $table_name WHERE 1=1 $where GROUP BY DATE(created_at) ORDER BY date ASC";

        if (!empty($values)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        } else {
            $results = $wpdb->get_results($sql, ARRAY_A);
        }

        // Fill in missing dates with zero counts
        $filled = array();
        $start = new DateTime(gmdate('Y-m-d', strtotime("-{$days} days")));
        $end = new DateTime(gmdate('Y-m-d'));
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);

        $results_by_date = array();
        if ($results) {
            foreach ($results as $row) {
                $results_by_date[$row['date']] = (int) $row['count'];
            }
        }

        foreach ($period as $date) {
            $d = $date->format('Y-m-d');
            $filled[] = array(
                'date'  => $d,
                'count' => isset($results_by_date[$d]) ? $results_by_date[$d] : 0,
            );
        }

        wp_send_json_success(array(
            'trend' => $filled,
        ));
    }
}
