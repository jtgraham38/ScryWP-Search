<?php
/**
 * Recent Searches WP_List_Table
 *
 * @package scry_ms_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ScrySearch_Recent_Searches_Table extends WP_List_Table {

    /**
     * @var ScrySearch_AnalyticsFeature
     */
    private $analytics_feature;

    /**
     * Constructor
     *
     * @param ScrySearch_AnalyticsFeature $analytics_feature
     */
    public function __construct($analytics_feature) {
        $this->analytics_feature = $analytics_feature;

        parent::__construct(array(
            'singular' => 'search',
            'plural'   => 'searches',
            'ajax'     => false,
        ));
    }

    /**
     * Define table columns
     */
    public function get_columns() {
        return array(
            'search_term'  => __('Search Term', 'scry-search'),
            'user'         => __('User', 'scry-search'),
            'user_ip'      => __('IP Address', 'scry-search'),
            'result_count' => __('Results', 'scry-search'),
            'created_at'   => __('Date', 'scry-search'),
        );
    }

    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'search_term'  => array('search_term', false),
            'result_count' => array('result_count', false),
            'created_at'   => array('created_at', true), // default sort desc
        );
    }

    /**
     * Prepare table items
     */
    public function prepare_items() {
        $per_page = $this->get_items_per_page('scry_searches_per_page', 20);
        $current_page = $this->get_pagenum();

        // Build query args from request
        $args = array(
            'per_page' => $per_page,
            'page'     => $current_page,
            'orderby'  => isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'created_at',
            'order'    => isset($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'DESC',
        );

        // Search filter
        if (!empty($_REQUEST['s'])) {
            $args['search_term_like'] = sanitize_text_field(wp_unslash($_REQUEST['s']));
        }

        // Date filters (default: last 30 days)
        $default_from = gmdate('Y-m-d', strtotime('-30 days'));
        $default_to   = gmdate('Y-m-d');

        $args['date_from'] = !empty($_REQUEST['date_from'])
            ? sanitize_text_field(wp_unslash($_REQUEST['date_from']))
            : $default_from;
        $args['date_to'] = !empty($_REQUEST['date_to'])
            ? sanitize_text_field(wp_unslash($_REQUEST['date_to']))
            : $default_to;

        // Zero results filter
        if (isset($_REQUEST['zero_results']) && $_REQUEST['zero_results'] === '1') {
            $args['has_results'] = false;
        }

        $data = $this->analytics_feature->query_search_analytics($args);

        // Enrich rows
        foreach ($data['results'] as &$row) {
            $row['result_ids'] = json_decode($row['result_ids'], true);
            $row['result_titles'] = json_decode($row['result_titles'], true);
            $row['post_types_searched'] = json_decode($row['post_types_searched'], true);

            if (!empty($row['user_id'])) {
                $user = get_userdata((int) $row['user_id']);
                $row['user_display_name'] = $user ? $user->display_name : __('Unknown User', 'scry-search');
            } else {
                $row['user_display_name'] = __('Anonymous', 'scry-search');
            }
        }
        unset($row);

        $this->items = $data['results'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page'    => $per_page,
            'total_pages' => $data['pages'],
        ));

        $this->_column_headers = array(
            $this->get_columns(),
            array(), // hidden columns
            $this->get_sortable_columns(),
        );
    }

    /**
     * Default column renderer
     */
    public function column_default($item, $column_name) {
        return esc_html($item[$column_name] ?? '');
    }

    /**
     * Search term column -- includes expandable results detail
     */
    public function column_search_term($item) {
        $term = esc_html($item['search_term']);
        return '<strong>' . $term . '</strong>';
    }

    /**
     * User column
     */
    public function column_user($item) {
        $display = esc_html($item['user_display_name']);
        if (!empty($item['user_id'])) {
            $display .= ' <span class="description">(ID: ' . absint($item['user_id']) . ')</span>';
        }
        return $display;
    }

    /**
     * IP column
     */
    public function column_user_ip($item) {

        //check if the ip is (does not meet the ipv4 or ipv6 format)
        if (!filter_var($item['user_ip'], FILTER_VALIDATE_IP)) {
            return '<span class="description">' . esc_html__('Anonymized', 'scry-search') . '</span>';
        }

        $ip = esc_html($item['user_ip']);
        if (empty($ip)) {
            return '<span class="description">' . esc_html__('N/A', 'scry-search') . '</span>';
        }
        return '<code>' . $ip . '</code>';
    }

    /**
     * Result count column with color coding
     */
    public function column_result_count($item) {
        $count = absint($item['result_count']);

        if ($count === 0) {
            return '<span style="color:#d63638;font-weight:600;">0</span>';
        }

        // Build expandable results list
        $result_titles = is_array($item['result_titles']) ? $item['result_titles'] : array();
        $result_ids = is_array($item['result_ids']) ? $item['result_ids'] : array();
        $details_id = 'scry-results-' . esc_attr($item['id']);
        $toggle = '<button type="button" class="button-link scry-toggle-results" data-target="' . $details_id . '" aria-expanded="false">'
            . '<span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> '
            . esc_html__('Show Results', 'scry-search')
            . '</button>';

        $list = '<div id="' . $details_id . '" class="scry-results-detail" style="display:none;margin-top:8px;">';
        $list .= '<ol style="margin:0 0 0 1.5em;">';
        foreach ($result_titles as $i => $title) {
            $post_id = isset($result_ids[$i]) ? (int) $result_ids[$i] : 0;
            $safe_title = esc_html($title);
            if ($post_id) {
                $permalink = get_permalink($post_id);
                $edit_link = get_edit_post_link($post_id);
                $safe_title = '<a href="' . esc_url($permalink) . '" target="_blank">' . $safe_title . '</a>';
                if ($edit_link) {
                    $safe_title .= ' <a href="' . esc_url($edit_link) . '" class="scry-edit-link" title="' . esc_attr__('Edit', 'scry-search') . '"><span class="dashicons dashicons-edit" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span></a>';
                }
            }
            $list .= '<li>' . $safe_title . '</li>';
        }
        $list .= '</ol></div>';
        
        return '<span style="color:#00a32a;font-weight:600;">' . number_format_i18n($count) . '</span><br>' . $toggle . $list;
    }

    /**
     * Date column
     */
    public function column_created_at($item) {
        $timestamp = strtotime($item['created_at']);
        $formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
        $human = human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('ago', 'scry-search');

        return esc_html($formatted) . '<br><span class="description">' . esc_html($human) . '</span>';
    }

    /**
     * Message when no items found
     */
    public function no_items() {
        esc_html_e('No search events recorded yet.', 'scry-search');
    }

    /**
     * Extra table nav -- date filters and zero-results toggle
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $date_from = !empty($_REQUEST['date_from']) ? sanitize_text_field(wp_unslash($_REQUEST['date_from'])) : gmdate('Y-m-d', strtotime('-30 days'));
        $date_to = !empty($_REQUEST['date_to']) ? sanitize_text_field(wp_unslash($_REQUEST['date_to'])) : gmdate('Y-m-d');
        $zero_results = isset($_REQUEST['zero_results']) && $_REQUEST['zero_results'] === '1';
        ?>
        <div class="alignleft actions">
            <label for="scry-date-from"><?php esc_html_e('From:', 'scry-search'); ?></label>
            <input type="date" id="scry-date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>" />

            <label for="scry-date-to"><?php esc_html_e('To:', 'scry-search'); ?></label>
            <input type="date" id="scry-date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>" />

            <label for="scry-zero-results">
                <input type="checkbox" id="scry-zero-results" name="zero_results" value="1" <?php checked($zero_results); ?> />
                <?php esc_html_e('Zero results only', 'scry-search'); ?>
            </label>

            <?php submit_button(__('Filter', 'scry-search'), '', 'filter_action', false); ?>
        </div>
        <?php
    }
}
