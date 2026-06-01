<?php
// exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$allowed_levels = array('error', 'debug');
$selected_level = isset($_GET['log_level']) ? sanitize_text_field(wp_unslash($_GET['log_level'])) : 'error';

if (!in_array($selected_level, $allowed_levels, true)) {
    $selected_level = 'error';
}

$placeholder_lines = array(
    'error 2026-06-01 10:58:21 - Meilisearch connection failed while loading index settings.',
    'error 2026-06-01 10:59:04 - Index posts request returned an unexpected response.',
    'error 2026-06-01 11:00:32 - Search request failed and WordPress fallback was used.',
);
?>

<div class="scrywp-logs-page">
    <div class="scrywp-logs-header">
        <div>
            <h1 class="scrywp-logs-title"><?php esc_html_e('Logs', 'scry-search'); ?></h1>
            <p class="scrywp-logs-description">
                <?php esc_html_e('Review recent Scry Search error and debug messages for troubleshooting. Error logs are shown by default, with the newest entries at the bottom of the viewer.', 'scry-search'); ?>
            </p>
        </div>

        <form method="get" class="scrywp-logs-toolbar">
            <input type="hidden" name="page" value="scry-search-meilisearch-logs">

            <label for="scrywp-log-level">
                <?php esc_html_e('Log file', 'scry-search'); ?>
            </label>

            <select id="scrywp-log-level" name="log_level">
                <option value="error" <?php selected($selected_level, 'error'); ?>>
                    <?php esc_html_e('Error log', 'scry-search'); ?>
                </option>
                <option value="debug" <?php selected($selected_level, 'debug'); ?>>
                    <?php esc_html_e('Debug log', 'scry-search'); ?>
                </option>
            </select>

            <button type="submit" class="button button-primary">
                <?php esc_html_e('View Log', 'scry-search'); ?>
            </button>
        </form>
    </div>

    <div class="scrywp-logs-card">
        <div class="scrywp-logs-card-header">
            <span class="scrywp-logs-badge">
                <?php echo esc_html($selected_level); ?>.log
            </span>
            <span class="scrywp-logs-meta">
                <?php esc_html_e('Showing the most recent 100 lines', 'scry-search'); ?>
            </span>
        </div>

        <pre class="scrywp-logs-viewer"><code><?php echo esc_html(implode("\n", $placeholder_lines)); ?></code></pre>

        <div class="scrywp-logs-load-more">
            <a href="#" class="button button-secondary" data-log-level="<?php echo esc_attr($selected_level); ?>" data-next-start="100">
                <?php esc_html_e('Load 100 more', 'scry-search'); ?>
            </a>
        </div>
    </div>
</div>