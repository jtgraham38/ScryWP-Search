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
        ?>
        <form class="scrywp-index-settings-form scrywp-index-settings-loaded" data-index-name="<?php echo esc_attr($index['index_name']); ?>" style="display: none;">
            <nav class="scrywp-index-settings-tabs" role="tablist" aria-label="<?php esc_attr_e('Index settings sections', "scry-search"); ?>"></nav>

            <div class="scrywp-index-settings-panels">
            <!-- ranking rules section -->
            <div class="scrywp-index-settings-section" data-tab="ranking-rules" data-setting-key="ranking_rules">
                <div class="scrywp-index-settings-section-header">
                    <h4><?php esc_html_e('Ranking Rules', "scry-search"); ?></h4>
                    <a href="https://www.meilisearch.com/docs/learn/relevancy/ranking_rules" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about ranking rules', "scry-search"); ?>">
                        <?php esc_html_e('Learn more', "scry-search"); ?>
                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                    </a>
                </div>
                <p class="description"><?php esc_html_e('Drag and drop to reorder the ranking rules. Rules are applied in order from top to bottom.', "scry-search"); ?></p>
                <ul class="scrywp-ranking-rules-list" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                    <?php foreach ($ranking_rules as $ranking_rule): ?>
                        <?php if (!is_string($ranking_rule) || $ranking_rule === '') { continue; } ?>
                        <li class="scrywp-ranking-rule-item" draggable="true" data-rule="<?php echo esc_attr($ranking_rule); ?>">
                            <span class="scrywp-ranking-rule-handle" aria-label="<?php esc_attr_e('Drag to reorder', "scry-search"); ?>">☰</span>
                            <span class="scrywp-ranking-rule-label"><?php echo esc_html($ranking_rule); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="scrywp-ranking-rules-hidden-inputs" aria-hidden="true"></div>
                <details class="scrywp-index-dialog-result-json scrywp-index-settings-raw-json">
                    <summary class="scrywp-index-dialog-result-json-toggle"><?php esc_html_e('View Raw JSON', "scry-search"); ?></summary>
                    <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content"><?php echo esc_html(wp_json_encode(array_values(array_filter((array) $ranking_rules, 'is_string')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                </details>
            </div>

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

            <!-- filterable fields section -->
            <div class="scrywp-index-settings-section" data-tab="filterable-fields" data-setting-key="filterable_attributes">
                <div class="scrywp-index-settings-section-header">
                    <h4><?php esc_html_e('Filterable Fields', "scry-search"); ?></h4>
                    <a href="https://www.meilisearch.com/docs/learn/filtering_and_sorting" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about filterable attributes', "scry-search"); ?>">
                        <?php esc_html_e('Learn more', "scry-search"); ?>
                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                    </a>
                </div>
                <p class="description"><?php esc_html_e('Select which fields should be filterable. Filterable fields can be used for facets and advanced filtering.', "scry-search"); ?></p>
                <div class="scrywp-searchable-fields-tree" data-index-name="<?php echo esc_attr($index['index_name']); ?>" data-fields-type="filterable">
                    <?php foreach ($available_filterable_fields as $field): ?>
                        <?php
                        if (!is_array($field) || !isset($field['path'], $field['label'])) {
                            continue;
                        }
                        $field_path = (string) $field['path'];
                        $field_label = (string) $field['label'];
                        $is_checked = in_array($field_path, $filterable_attributes, true);
                        $is_group = isset($field['type']) && $field['type'] === 'group' && isset($field['children']) && is_array($field['children']);
                        ?>
                        <?php if ($is_group): ?>
                            <div class="scrywp-searchable-field-group">
                                <label class="scrywp-searchable-field-group-label">
                                    <input
                                        type="checkbox"
                                        class="scrywp-searchable-field-checkbox"
                                        name="filterable_attributes[]"
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
                                        $child_checked = in_array($child_path, $filterable_attributes, true);
                                        ?>
                                        <label class="scrywp-searchable-field-item">
                                            <input
                                                type="checkbox"
                                                class="scrywp-searchable-field-checkbox"
                                                name="filterable_attributes[]"
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
                                    name="filterable_attributes[]"
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
                    <pre class="scrywp-index-dialog-result-json-content scrywp-index-settings-raw-json-content"><?php echo esc_html(wp_json_encode(array_values((array) $filterable_attributes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                </details>
            </div>

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
