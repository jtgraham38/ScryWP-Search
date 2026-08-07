<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- ranking rules section -->
<div class="scrywp-index-settings-section" data-tab="ranking-rules" data-setting-key="ranking_rules">
    <div class="scrywp-index-settings-section-header">
        <h4><?php esc_html_e('Ranking Rules', "scry-search"); ?></h4>
        <a href="https://www.meilisearch.com/docs/learn/relevancy/ranking_rules" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about ranking rules', "scry-search"); ?>">
            <?php esc_html_e('Learn more', "scry-search"); ?>
            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
        </a>
    </div>
    <p class="description"><?php esc_html_e('Drag and drop to reorder ranking rules. Rules are applied from top to bottom. Add custom rules like attribute:asc or attribute:desc to promote documents by field value.', "scry-search"); ?></p>
    <ul class="scrywp-ranking-rules-list" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
        <?php foreach ($ranking_rules as $ranking_rule): ?>
            <?php if (!is_string($ranking_rule) || $ranking_rule === '') { continue; } ?>
            <?php
            $is_custom_rule = (bool) preg_match('/^.+:(asc|desc)$/i', $ranking_rule);
            ?>
            <li class="scrywp-ranking-rule-item<?php echo $is_custom_rule ? ' scrywp-ranking-rule-item-custom' : ''; ?>" draggable="true" data-rule="<?php echo esc_attr($ranking_rule); ?>" data-custom="<?php echo $is_custom_rule ? '1' : '0'; ?>">
                <span class="scrywp-ranking-rule-handle" aria-label="<?php esc_attr_e('Drag to reorder', "scry-search"); ?>">☰</span>
                <span class="scrywp-ranking-rule-label"><?php echo esc_html($ranking_rule); ?></span>
                <?php if ($is_custom_rule): ?>
                    <button type="button" class="scrywp-ranking-rule-remove" aria-label="<?php esc_attr_e('Remove custom ranking rule', "scry-search"); ?>">×</button>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="scrywp-ranking-rules-add">
        <label class="screen-reader-text" for="<?php echo esc_attr($index['index_name']); ?>_custom_ranking_attribute"><?php esc_html_e('Attribute name', "scry-search"); ?></label>
        <input
            type="text"
            id="<?php echo esc_attr($index['index_name']); ?>_custom_ranking_attribute"
            class="regular-text scrywp-ranking-rule-attribute-input"
            placeholder="<?php esc_attr_e('Attribute name (e.g. post_date_unix)', "scry-search"); ?>"
        >
        <label class="screen-reader-text" for="<?php echo esc_attr($index['index_name']); ?>_custom_ranking_direction"><?php esc_html_e('Sort direction', "scry-search"); ?></label>
        <select id="<?php echo esc_attr($index['index_name']); ?>_custom_ranking_direction" class="scrywp-ranking-rule-direction-select">
            <option value="desc"><?php esc_html_e('Descending (desc)', "scry-search"); ?></option>
            <option value="asc"><?php esc_html_e('Ascending (asc)', "scry-search"); ?></option>
        </select>
        <button type="button" class="button button-secondary scrywp-ranking-rule-add-button">
            <?php esc_html_e('Add custom rule', "scry-search"); ?>
        </button>
    </div>
    <p class="description scrywp-ranking-rules-add-help">
        <?php
        echo wp_kses(
            sprintf(
                /* translators: %s: documentation URL */
                __('Custom rules use the format <code>attribute:asc</code> or <code>attribute:desc</code>. Place them after built-in rules so relevancy is applied first. <a href="%s" target="_blank" rel="noopener noreferrer">Learn more about custom ranking rules</a>.', "scry-search"),
                'https://www.meilisearch.com/docs/capabilities/full_text_search/relevancy/custom_ranking_rules'
            ),
            array(
                'code' => array(),
                'a' => array(
                    'href' => array(),
                    'target' => array(),
                    'rel' => array(),
                ),
            )
        );
        ?>
    </p>
    <p class="scrywp-ranking-rules-add-error" style="display: none;"></p>

    <div class="scrywp-ranking-rules-hidden-inputs" aria-hidden="true"></div>
    <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
        <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
        <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content"><?php echo esc_html(wp_json_encode(array_values(array_filter((array) $ranking_rules, 'is_string')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
    </details>
</div>
