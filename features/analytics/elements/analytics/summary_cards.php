<?php
/**
 * Summary Cards component
 *
 * Renders stat cards populated via AJAX (getSummary endpoint).
 *
 * @package scry_ms_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="scry-summary-cards" id="scry-summary-cards">
    <div class="scry-summary-card" id="scry-card-total-searches">
        <div class="scry-summary-card-icon">
            <span class="dashicons dashicons-search"></span>
        </div>
        <div class="scry-summary-card-content">
            <span class="scry-summary-card-label"><?php esc_html_e('Total Searches', 'scry-search'); ?></span>
            <span class="scry-summary-card-value" data-key="total_searches">&mdash;</span>
        </div>
    </div>

    <div class="scry-summary-card" id="scry-card-searches-today">
        <div class="scry-summary-card-icon">
            <span class="dashicons dashicons-calendar-alt"></span>
        </div>
        <div class="scry-summary-card-content">
            <span class="scry-summary-card-label"><?php esc_html_e('Searches Today', 'scry-search'); ?></span>
            <span class="scry-summary-card-value" data-key="searches_today">&mdash;</span>
        </div>
    </div>

    <div class="scry-summary-card" id="scry-card-unique-terms">
        <div class="scry-summary-card-icon">
            <span class="dashicons dashicons-tag"></span>
        </div>
        <div class="scry-summary-card-content">
            <span class="scry-summary-card-label"><?php esc_html_e('Unique Terms', 'scry-search'); ?></span>
            <span class="scry-summary-card-value" data-key="unique_terms">&mdash;</span>
        </div>
    </div>

    <div class="scry-summary-card" id="scry-card-zero-results">
        <div class="scry-summary-card-icon">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <div class="scry-summary-card-content">
            <span class="scry-summary-card-label"><?php esc_html_e('Zero-Result Searches', 'scry-search'); ?></span>
            <span class="scry-summary-card-value" data-key="zero_results">&mdash;</span>
        </div>
    </div>

    <div class="scry-summary-card" id="scry-card-avg-results">
        <div class="scry-summary-card-icon">
            <span class="dashicons dashicons-chart-bar"></span>
        </div>
        <div class="scry-summary-card-content">
            <span class="scry-summary-card-label"><?php esc_html_e('Avg. Results / Search', 'scry-search'); ?></span>
            <span class="scry-summary-card-value" data-key="avg_results">&mdash;</span>
        </div>
    </div>

    <div class="scry-summary-card" id="scry-card-unique-searchers">
        <div class="scry-summary-card-icon">
            <span class="dashicons dashicons-groups"></span>
        </div>
        <div class="scry-summary-card-content">
            <span class="scry-summary-card-label"><?php esc_html_e('Unique Searchers', 'scry-search'); ?></span>
            <span class="scry-summary-card-value" data-key="unique_searchers">&mdash;</span>
        </div>
    </div>
</div>
