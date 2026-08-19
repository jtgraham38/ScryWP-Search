<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<dialog id="<?php echo esc_attr($index['index_name']); ?>_settings_dialog" class="scrywp-index-dialog scrywp-index-settings-dialog">
    <div class="scrywp-index-dialog-header">
        <h3><?php echo esc_html(sprintf(__('Configure Index: %s', "scry-search"), $index['name'])); ?></h3>
        <button type="button" class="scrywp-index-dialog-close-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').close()" aria-label="<?php esc_attr_e('Close', "scry-search"); ?>">
            ×
        </button>
    </div>

    <div class="scrywp-index-settings-content">
        <div class="scrywp-index-settings-loading">
            <p><?php esc_html_e('Loading settings...', "scry-search"); ?></p>
        </div>
        
        <?php
            //
            //// load the values for the built-in input sections
            //
            $ranking_rules = $indexes_feature->get_default_ranking_rules();
            $searchable_attributes = $indexes_feature->get_searchable_attributes();
            $filterable_attributes = $indexes_feature->get_default_filterable_attributes();
            $available_fields = $indexes_feature->get_available_fields_for_post_type($index['name']);
            if (!is_array($available_fields)) {
                $available_fields = array();
            }
            $available_filterable_fields = $indexes_feature->get_available_filterable_fields_for_post_type($index['name']);
            if (!is_array($available_filterable_fields)) {
                $available_filterable_fields = array();
            }

            try {
                $settings_index = $client->index($index['index_name']);
                $fetched_ranking_rules = $settings_index->getRankingRules();
                if (is_array($fetched_ranking_rules) && !empty($fetched_ranking_rules)) {
                    $ranking_rules = $fetched_ranking_rules;
                }

                $fetched_searchable_attributes = $settings_index->getSearchableAttributes();
                if (is_array($fetched_searchable_attributes) && !empty($fetched_searchable_attributes)) {
                    $searchable_attributes = $fetched_searchable_attributes;
                }

                $fetched_filterable_attributes = $settings_index->getFilterableAttributes();
                if (is_array($fetched_filterable_attributes) && !empty($fetched_filterable_attributes)) {
                    $filterable_attributes = $fetched_filterable_attributes;
                }
            } catch (Exception $e) {
                // Keep defaults so the form remains usable even if settings fetch fails.
            }

            $searchable_attributes = $indexes_feature->resolve_searchable_attributes_for_ui($searchable_attributes, $available_fields);

            $dialog_sections_dir = plugin_dir_path(__FILE__) . 'dialog_sections/';
        ?>
        <form class="scrywp-index-settings-form scrywp-index-settings-loaded" data-index-name="<?php echo esc_attr($index['index_name']); ?>" style="display: none;">
            <nav class="scrywp-index-settings-tabs" role="tablist" aria-label="<?php esc_attr_e('Index settings sections', "scry-search"); ?>"></nav>

            <div class="scrywp-index-settings-panels">
                <?php include $dialog_sections_dir . 'ranking_rules_section.php'; ?>
                <?php include $dialog_sections_dir . 'searchable_fields_section.php'; ?>
                <?php include $dialog_sections_dir . 'synonyms_section.php'; ?>
                <?php include $dialog_sections_dir . 'stop_words_section.php'; ?>
                <?php include $dialog_sections_dir . 'dictionary_section.php'; ?>
                <?php include $dialog_sections_dir . 'typo_tolerance_section.php'; ?>
                <?php include $dialog_sections_dir . 'filterable_fields_section.php'; ?>

                <!-- this is where other plugins can insert their index settings section -->
                <?php do_action($this->config('hook_prefix') . 'index_settings_sections_ui', $index); ?>
            </div>

            <div class="scrywp-index-settings-actions">
                <div class="scrywp-index-settings-save-error" style="display: none;">
                    <p class="scrywp-index-settings-save-error-message"></p>
                </div>
                <div class="scrywp-index-settings-actions-buttons">
                    <button type="button" class="button button-primary scrywp-save-index-settings-button" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                        <?php esc_html_e('Save Settings', "scry-search"); ?>
                      </button>
                      <button type="button" class="button button-secondary scrywp-cancel-index-settings-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').close()">
                        <?php esc_html_e('Cancel', "scry-search"); ?>
                    </button>
                </div>
            </div>
        </form>
        
        <div class="scrywp-index-settings-error" style="display: none;">
            <p class="scrywp-index-settings-error-message"></p>
        </div>
    </div>
</dialog>
