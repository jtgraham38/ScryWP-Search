<?php
// exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$logs_config = $this->config('logs');
$allowed_levels = isset($logs_config['levels']) ? $logs_config['levels'] : array('error' => 'Error', 'debug' => 'Debug');
$page_size = isset($logs_config['page_size']) ? absint($logs_config['page_size']) : 100;
$selected_level = isset($_GET['log_level']) ? sanitize_text_field(wp_unslash($_GET['log_level'])) : 'error';

if (!array_key_exists($selected_level, $allowed_levels)) {
    $selected_level = 'error';
}

$selected_log_type = $allowed_levels[$selected_level];
$logs_feature = $this->get_feature('scry_ms_logs');
$log_data = array(
    'lines' => array(),
    'start' => 0,
    'next_start' => 0,
    'has_more' => false,
    'total' => 0,
);
$log_read_error = '';

try {
    $log_data = $logs_feature->read($selected_level, 0, $page_size);
} catch (Throwable $e) {
    $log_read_error = $e->getMessage();
}
?>

<div class="scrywp-logs-page">
    <div class="scrywp-logs-header">
        <div>
            <h1 class="scrywp-logs-title"><?php esc_html_e('Logs', 'scry-search'); ?></h1>
            <p class="scrywp-logs-description">
                <?php esc_html_e('Review recent Scry Search error and debug messages stored in the database. Error entries are shown by default, with the newest entries at the bottom of the viewer.', 'scry-search'); ?>
            </p>
        </div>

        <form method="get" class="scrywp-logs-toolbar">
            <input type="hidden" name="page" value="scry-search-meilisearch-logs">

            <label for="scrywp-log-level">
                <?php esc_html_e('Log type', 'scry-search'); ?>
            </label>

            <select id="scrywp-log-level" name="log_level">
                <?php foreach ($allowed_levels as $level => $label): ?>
                    <option value="<?php echo esc_attr($level); ?>" <?php selected($selected_level, $level); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button button-primary">
                <?php esc_html_e('View Entries', 'scry-search'); ?>
            </button>
        </form>
    </div>

    <div class="scrywp-logs-card">
        <div class="scrywp-logs-card-header">
            <span class="scrywp-logs-badge">
                <?php echo esc_html($selected_log_type); ?>
            </span>
            <span class="scrywp-logs-meta">
                <?php echo esc_html(sprintf(__('Showing the most recent %d entries', 'scry-search'), $page_size)); ?>
            </span>
        </div>

        <pre class="scrywp-logs-viewer"><code><?php
        if (!empty($log_read_error)) {
            echo esc_html($log_read_error);
        } elseif (empty($log_data['lines'])) {
            esc_html_e('No log messages found.', 'scry-search');
        } else {
            echo esc_html(implode("\n", $log_data['lines']));
        }
        ?></code></pre>

        <div class="scrywp-logs-load-more">
            <?php if (!empty($log_data['has_more'])): ?>
                <a href="#" class="button button-secondary" data-log-level="<?php echo esc_attr($selected_level); ?>" data-next-start="<?php echo esc_attr($log_data['next_start']); ?>">
                    <?php echo esc_html(sprintf(__('Load %d more', 'scry-search'), $page_size)); ?>
                </a>
            <?php else: ?>
                <span class="scrywp-logs-meta">
                    <?php esc_html_e('No older log messages to load.', 'scry-search'); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>