<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$searchable_attributes_list = array_values(array_filter((array) $searchable_attributes, 'is_string'));
$searchable_order_index = array_flip($searchable_attributes_list);

$scrywp_searchable_field_sort_key = static function ($field) use ($searchable_order_index) {
    if (!is_array($field) || !isset($field['path'])) {
        return PHP_INT_MAX;
    }
    $keys = array();
    $path = (string) $field['path'];
    if (isset($searchable_order_index[$path])) {
        $keys[] = (int) $searchable_order_index[$path];
    }
    if (isset($field['children']) && is_array($field['children'])) {
        foreach ($field['children'] as $child) {
            if (!is_array($child) || !isset($child['path'])) {
                continue;
            }
            $child_path = (string) $child['path'];
            if (isset($searchable_order_index[$child_path])) {
                $keys[] = (int) $searchable_order_index[$child_path];
            }
        }
    }
    return $keys ? min($keys) : PHP_INT_MAX;
};

$available_fields_list = array();
foreach ((array) $available_fields as $field) {
    if (!is_array($field) || !isset($field['path'], $field['label'])) {
        continue;
    }
    if (isset($field['children']) && is_array($field['children'])) {
        $children = array_values($field['children']);
        usort($children, static function ($a, $b) use ($scrywp_searchable_field_sort_key) {
            return $scrywp_searchable_field_sort_key($a) <=> $scrywp_searchable_field_sort_key($b);
        });
        $field['children'] = $children;
    }
    $available_fields_list[] = $field;
}
usort($available_fields_list, static function ($a, $b) use ($scrywp_searchable_field_sort_key) {
    return $scrywp_searchable_field_sort_key($a) <=> $scrywp_searchable_field_sort_key($b);
});
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
    <p class="description"><?php esc_html_e('Select which fields should be searchable, then drag to reorder. Fields higher in the list are more important for relevancy.', "scry-search"); ?></p>
    <div class="scrywp-searchable-fields-tree" data-index-name="<?php echo esc_attr($index['index_name']); ?>" data-fields-type="searchable">
        <?php foreach ($available_fields_list as $field): ?>
            <?php
            $field_path = (string) $field['path'];
            $field_label = (string) $field['label'];
            $is_checked = in_array($field_path, $searchable_attributes_list, true);
            $is_group = isset($field['type']) && $field['type'] === 'group' && isset($field['children']) && is_array($field['children']);
            $has_checked_child = false;
            if ($is_group) {
                foreach ($field['children'] as $child) {
                    if (is_array($child) && isset($child['path']) && in_array((string) $child['path'], $searchable_attributes_list, true)) {
                        $has_checked_child = true;
                        break;
                    }
                }
            }
            ?>
            <?php if ($is_group): ?>
                <div class="scrywp-searchable-field-group scrywp-searchable-field-draggable" draggable="true" data-field-path="<?php echo esc_attr($field_path); ?>">
                    <div class="scrywp-searchable-field-row scrywp-searchable-field-group-row">
                        <span class="scrywp-searchable-field-handle" aria-label="<?php esc_attr_e('Drag to reorder', "scry-search"); ?>">☰</span>
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
                            <button type="button" class="scrywp-searchable-field-expand" aria-label="<?php esc_attr_e('Expand', "scry-search"); ?>"><?php echo $has_checked_child ? '▼' : '▶'; ?></button>
                        </label>
                    </div>
                    <div class="scrywp-searchable-field-children" style="display: <?php echo $has_checked_child ? 'block' : 'none'; ?>;">
                        <?php foreach ($field['children'] as $child): ?>
                            <?php
                            if (!is_array($child) || !isset($child['path'], $child['label'])) {
                                continue;
                            }
                            $child_path = (string) $child['path'];
                            $child_label = (string) $child['label'];
                            $child_checked = in_array($child_path, $searchable_attributes_list, true);
                            ?>
                            <div class="scrywp-searchable-field-row scrywp-searchable-field-draggable" draggable="true" data-field-path="<?php echo esc_attr($child_path); ?>">
                                <span class="scrywp-searchable-field-handle" aria-label="<?php esc_attr_e('Drag to reorder', "scry-search"); ?>">☰</span>
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
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="scrywp-searchable-field-row scrywp-searchable-field-draggable" draggable="true" data-field-path="<?php echo esc_attr($field_path); ?>">
                    <span class="scrywp-searchable-field-handle" aria-label="<?php esc_attr_e('Drag to reorder', "scry-search"); ?>">☰</span>
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
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
        <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
        <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content"><?php echo esc_html(wp_json_encode($searchable_attributes_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
    </details>
</div>
