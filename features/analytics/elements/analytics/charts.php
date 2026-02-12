<?php
/**
 * Charts component
 *
 * Renders bar chart (top terms) and line chart (search volume trend).
 * Charts are populated via AJAX + Chart.js in analytics.js.
 *
 * @package scry_ms_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="scry-charts-row">
    <!-- Top Search Terms Bar Chart -->
    <div class="scry-chart-container">
        <div class="scry-chart-header">
            <h3><?php esc_html_e('Top Search Terms', 'scry-search'); ?></h3>
            <div class="scry-chart-filters">
                <label for="scry-top-terms-from"><?php esc_html_e('From:', 'scry-search'); ?></label>
                <input type="date" id="scry-top-terms-from" value="<?php echo esc_attr(gmdate('Y-m-d', strtotime('-30 days'))); ?>" />
                <label for="scry-top-terms-to"><?php esc_html_e('To:', 'scry-search'); ?></label>
                <input type="date" id="scry-top-terms-to" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>" />
            </div>
        </div>
        <div class="scry-chart-canvas-wrap">
            <canvas id="scry-top-terms-chart"></canvas>
        </div>
        <p class="scry-chart-empty" id="scry-top-terms-empty" style="display:none;">
            <?php esc_html_e('No search data available for this period.', 'scry-search'); ?>
        </p>
    </div>

    <!-- Search Volume Line Chart -->
    <div class="scry-chart-container">
        <div class="scry-chart-header">
            <h3><?php esc_html_e('Search Volume Over Time', 'scry-search'); ?></h3>
            <div class="scry-chart-filters">
                <label for="scry-trend-term-filter"><?php esc_html_e('Term:', 'scry-search'); ?></label>
                <select id="scry-trend-term-filter">
                    <option value=""><?php esc_html_e('All search terms', 'scry-search'); ?></option>
                </select>
            </div>
        </div>
        <div class="scry-chart-canvas-wrap">
            <canvas id="scry-trend-chart"></canvas>
        </div>
        <p class="scry-chart-empty" id="scry-trend-empty" style="display:none;">
            <?php esc_html_e('No search data available for this period.', 'scry-search'); ?>
        </p>
    </div>
</div>
