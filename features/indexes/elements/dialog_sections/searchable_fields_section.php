<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- searchable fields section -->
<div class="scrywp-index-settings-section" data-tab="searchable-fields" data-setting-key="searchable_attributes">
    <div class="scrywp-index-settings-section-header">
        <h4><?php esc_html_e('Searchable Fields', "scry-search"); ?></h4>
        <a href="https://www.meilisearch.com/docs/learn/relevancy/displayed_searchable_attributes#the-searchableattributes-list" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about searchable attributes', "scry-search"); ?>">
            <?php esc_html_e('Learn more', "scry-search"); ?>
            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
        </a>
    </div>
    <p class="description"><?php esc_html_e('Select which fields should be searchable. The order determines relevancy.', "scry-search"); ?></p>
    <div class="scrywp-searchable-fields-tree" data-index-name="<?php echo esc_attr($index['index_name']); ?>" data-fields-type="searchable">
        <?php foreach ($available_fields as $field): ?>
            <?php
            if (!is_array($field) || !isset($field['path'], $field['label'])) {
                continue;
            }
            $field_path = (string) $field['path'];
            $field_label = (string) $field['label'];
            $is_checked = in_array($field_path, $searchable_attributes, true);
            $is_group = isset($field['type']) && $field['type'] === 'group' && isset($field['children']) && is_array($field['children']);
            ?>
            <?php if ($is_group): ?>
                <div class="scrywp-searchable-field-group">
                    <label class="scrywp-searchable-field-group-label">
                        <input
                            type="checkbox"
                            class="scrywp-searchable-field-checkbox"
                            name="searchable_attributes[]"
                            value="<?php echo esc_attr($field_path); ?>"
                            data-field-path="<?php echo esc_attr($field_path); ?>"
                            <?php checked($is_checked); ?>
                        >
                        <span><?php echo esc_html($field_label); ?></span>
                        <button type="button" class="scrywp-searchable-field-expand" aria-label="<?php esc_attr_e('Expand', "scry-search"); ?>">▶</button>
                    </label>
                    <div class="scrywp-searchable-field-children" style="display: none;">
                        <?php foreach ($field['children'] as $child): ?>
                            <?php
                            if (!is_array($child) || !isset($child['path'], $child['label'])) {
                                continue;
                            }
                            $child_path = (string) $child['path'];
                            $child_label = (string) $child['label'];
                            $child_checked = in_array($child_path, $searchable_attributes, true);
                            ?>
                            <label class="scrywp-searchable-field-item">
                                <input
                                    type="checkbox"
                                    class="scrywp-searchable-field-checkbox"
                                    name="searchable_attributes[]"
                                    value="<?php echo esc_attr($child_path); ?>"
                                    data-field-path="<?php echo esc_attr($child_path); ?>"
                                    <?php checked($child_checked); ?>
                                >
                                <span><?php echo esc_html($child_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <label class="scrywp-searchable-field-item">
                    <input
                        type="checkbox"
                        class="scrywp-searchable-field-checkbox"
                        name="searchable_attributes[]"
                        value="<?php echo esc_attr($field_path); ?>"
                        data-field-path="<?php echo esc_attr($field_path); ?>"
                        <?php checked($is_checked); ?>
                    >
                    <span><?php echo esc_html($field_label); ?></span>
                </label>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
        <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
        <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content"><?php echo esc_html(wp_json_encode(array_values((array) $searchable_attributes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
    </details>
</div>
