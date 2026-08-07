<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- stop words section -->
<div class="scrywp-index-settings-section scrywp-stopwords-section" data-tab="stop-words" data-setting-key="stop_words" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
    <div class="scrywp-index-settings-section-header">
        <h4><?php esc_html_e('Stop Words', "scry-search"); ?></h4>
        <a href="https://specs.meilisearch.dev/specifications/text/0123-stop-words-setting-api.html" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about stop words', "scry-search"); ?>">
            <?php esc_html_e('Learn more', "scry-search"); ?>
            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
        </a>
    </div>
    <p class="description"><?php esc_html_e('Add stop words for this index. Stop words are ignored in search queries.', "scry-search"); ?></p>

    <div class="scrywp-stopwords-editor" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
        <div class="scrywp-stopwords-chips" aria-live="polite"></div>
        <input type="text" class="regular-text scrywp-stopwords-chip-input" placeholder="<?php esc_attr_e('Type a stop word and press Enter', "scry-search"); ?>">
    </div>

    <div class="scrywp-stopwords-hidden-inputs" aria-hidden="true"></div>
    <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
        <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
        <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content">[]</pre>
    </details>
</div>
