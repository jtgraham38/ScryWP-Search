<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- dictionary section -->
<div class="scrywp-index-settings-section scrywp-dictionary-section" data-tab="dictionary" data-setting-key="dictionary" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
    <div class="scrywp-index-settings-section-header">
        <h4><?php esc_html_e('Dictionary', "scry-search"); ?></h4>
        <a href="https://www.meilisearch.com/docs/reference/api/settings/get-dictionary" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about the dictionary setting', "scry-search"); ?>">
            <?php esc_html_e('Learn more', "scry-search"); ?>
            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
        </a>
    </div>
    <p class="description"><?php esc_html_e('Add dictionary words for this index. Dictionary words are not split during search tokenization (useful for acronyms and multi-word brand names).', "scry-search"); ?></p>

    <div class="scrywp-dictionary-editor" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
        <div class="scrywp-dictionary-chips" aria-live="polite"></div>
        <input type="text" class="regular-text scrywp-dictionary-chip-input" placeholder="<?php esc_attr_e('Type a dictionary word and press Enter', "scry-search"); ?>">
    </div>

    <div class="scrywp-dictionary-hidden-inputs" aria-hidden="true"></div>
    <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
        <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
        <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content">[]</pre>
    </details>
</div>
