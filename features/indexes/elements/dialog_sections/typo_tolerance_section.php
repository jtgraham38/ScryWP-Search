<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- typo tolerance section -->
<div class="scrywp-index-settings-section scrywp-typo-tolerance-section" data-tab="typo-tolerance" data-setting-key="typo_tolerance" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
    <div class="scrywp-index-settings-section-header">
        <h4><?php esc_html_e('Typo Tolerance', "scry-search"); ?></h4>
        <a href="https://www.meilisearch.com/docs/reference/api/settings/get-typotolerance" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about typo tolerance', "scry-search"); ?>">
            <?php esc_html_e('Learn more', "scry-search"); ?>
            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
        </a>
    </div>
    <p class="description"><?php esc_html_e('Configure how Meilisearch handles typos in search queries for this index.', "scry-search"); ?></p>

    <div class="scrywp-typo-tolerance-editor" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
        <label class="scrywp-typo-tolerance-toggle">
            <input type="checkbox" name="typo_tolerance[enabled]" value="1" class="scrywp-typo-tolerance-enabled" checked>
            <span><?php esc_html_e('Enable typo tolerance', "scry-search"); ?></span>
        </label>

        <div class="scrywp-typo-tolerance-row">
            <label>
                <span class="scrywp-typo-tolerance-label"><?php esc_html_e('Min word size for one typo', "scry-search"); ?></span>
                <input type="number" min="0" class="small-text scrywp-typo-tolerance-one-typo" name="typo_tolerance[minWordSizeForTypos][oneTypo]" value="5">
            </label>
            <label>
                <span class="scrywp-typo-tolerance-label"><?php esc_html_e('Min word size for two typos', "scry-search"); ?></span>
                <input type="number" min="0" class="small-text scrywp-typo-tolerance-two-typos" name="typo_tolerance[minWordSizeForTypos][twoTypos]" value="9">
            </label>
        </div>

        <label class="scrywp-typo-tolerance-toggle">
            <input type="checkbox" name="typo_tolerance[disableOnNumbers]" value="1" class="scrywp-typo-tolerance-disable-numbers">
            <span><?php esc_html_e('Disable typo tolerance on numbers', "scry-search"); ?></span>
        </label>

        <div class="scrywp-typo-tolerance-field">
            <span class="scrywp-typo-tolerance-label"><?php esc_html_e('Disable on words', "scry-search"); ?></span>
            <p class="description"><?php esc_html_e('Words that must match exactly (for example brand names).', "scry-search"); ?></p>
            <div class="scrywp-typo-chips-editor scrywp-typo-disable-words-editor">
                <div class="scrywp-typo-disable-words-chips" aria-live="polite"></div>
                <input type="text" class="regular-text scrywp-typo-disable-words-chip-input" placeholder="<?php esc_attr_e('Type a word and press Enter', "scry-search"); ?>">
            </div>
            <div class="scrywp-typo-disable-words-hidden-inputs" aria-hidden="true"></div>
        </div>

        <div class="scrywp-typo-tolerance-field">
            <span class="scrywp-typo-tolerance-label"><?php esc_html_e('Disable on attributes', "scry-search"); ?></span>
            <p class="description"><?php esc_html_e('Attributes that only return exact matches (for example IDs or codes).', "scry-search"); ?></p>
            <div class="scrywp-typo-chips-editor scrywp-typo-disable-attributes-editor">
                <div class="scrywp-typo-disable-attributes-chips" aria-live="polite"></div>
                <input type="text" class="regular-text scrywp-typo-disable-attributes-chip-input" placeholder="<?php esc_attr_e('Type an attribute path and press Enter', "scry-search"); ?>">
            </div>
            <div class="scrywp-typo-disable-attributes-hidden-inputs" aria-hidden="true"></div>
        </div>
    </div>

    <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
        <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
        <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content">{}</pre>
    </details>
</div>
