<?php
/**
 * Analytics CSV export button (markup only; download handled via admin-ajax).
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="scrywp-analytics-export-bar">
    <button type="button" class="button button-secondary scrywp-export-analytics-csv">
        <?php esc_html_e('Download analytics as CSV', "scry-search"); ?>
    </button>
    <span class="description scrywp-export-analytics-csv-hint">
        <?php esc_html_e('Export all rows from the search analytics table for use in spreadsheets or external tools.', "scry-search"); ?>
    </span>
</div>
