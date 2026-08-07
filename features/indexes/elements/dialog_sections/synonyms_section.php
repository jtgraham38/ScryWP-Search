<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- synonyms section -->
<div class="scrywp-index-settings-section scrywp-synonyms-section" data-tab="synonyms" data-setting-key="synonyms" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
    <div class="scrywp-index-settings-section-header">
        <h4><?php esc_html_e('Synonyms', "scry-search"); ?></h4>
        <a href="https://www.meilisearch.com/docs/capabilities/full_text_search/relevancy/synonyms" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about synonyms', "scry-search"); ?>">
            <?php esc_html_e('Learn more', "scry-search"); ?>
            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
        </a>
    </div>
    <p class="description"><?php esc_html_e('Add synonyms for this index. Whenever the base term is searched, results with the synonyms will be returned as well.', "scry-search"); ?></p>

    <div class="scrywp-synonyms-editor" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
        <div class="scrywp-synonyms-entries" aria-live="polite"></div>

        <p>
            <button type="button" class="button button-secondary scrywp-synonyms-add-entry">
                <?php esc_html_e('Add base term', "scry-search"); ?>
            </button>
        </p>
    </div>

    <div class="scrywp-synonyms-hidden-inputs" aria-hidden="true"></div>
    <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
        <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
        <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content">{}</pre>
    </details>
</div>
