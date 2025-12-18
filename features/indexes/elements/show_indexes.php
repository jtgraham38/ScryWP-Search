<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Meilisearch\Client;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\ApiException;

// Get connection settings
$meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
$meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');

$indexes_data = array();
$error_message = '';

// Try to fetch index information
if (!empty($meilisearch_url) && !empty($meilisearch_admin_key)) {
    try {
        $client = new Client($meilisearch_url, $meilisearch_admin_key);
        $index_names = $this->get_index_names();
        
        foreach ($index_names as $post_type => $index_name) {
            try {
                $index = $client->index($index_name);
                $index_info = $index->fetchRawInfo();
                $stats = $index->stats();
                
                $indexes_data[] = array(
                    'name' => $post_type,
                    'index_name' => $index_name,
                    'uid' => isset($index_info['uid']) ? $index_info['uid'] : $index_name,
                    'primaryKey' => isset($index_info['primaryKey']) ? $index_info['primaryKey'] : null,
                    'createdAt' => isset($index_info['createdAt']) ? $index_info['createdAt'] : null,
                    'updatedAt' => isset($index_info['updatedAt']) ? $index_info['updatedAt'] : null,
                    'numberOfDocuments' => isset($stats['numberOfDocuments']) ? $stats['numberOfDocuments'] : 0,
                    'isIndexing' => isset($stats['isIndexing']) ? $stats['isIndexing'] : false,
                );
            } catch (ApiException $e) {
                // Index doesn't exist, add with error state
                $indexes_data[] = array(
                    'name' => $post_type,
                    'index_name' => $index_name,
                    'error' => $e->getCode() === 404 ? __('Index does not exist', "scry-ms-search") : $e->getMessage(),
                    'exists' => false,
                );
            }
        }
    } catch (CommunicationException $e) {
        $error_message = $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
} else {
    $error_message = __('Connection settings are not configured.', "scry-ms-search");
}
?>
<div class="scrywp-indexes-display">
    <?php if (!empty($error_message)): ?>
        <div class="scrywp-indexes-error">
            <p><strong><?php esc_html_e('Error:', "scry-ms-search"); ?></strong> <?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($indexes_data)): ?>
        <div class="scrywp-indexes-empty">
            <p><?php esc_html_e('No indexes configured. Please select post types to index in the settings.', "scry-ms-search"); ?></p>
        </div>
    <?php else: ?>
        <div class="scrywp-index-all-actions">
            <button type="button" class="button button-primary scrywp-index-all-posts-button">
                <?php esc_html_e('Index All Post Types', "scry-ms-search"); ?>
            </button>
        </div>
        <div class="scrywp-indexes-grid">
            <?php foreach ($indexes_data as $index): ?>
                <div class="scrywp-index-card <?php echo isset($index['error']) ? 'scrywp-index-card-error' : ''; ?>">
                    <div class="scrywp-index-card-header">
                        <div class="scrywp-index-card-title-container">
                            <h3 class="scrywp-index-card-title"><?php echo esc_html($index['name']); ?></h3>
                            <code class="scrywp-index-card-subtitle"><?php echo esc_html($index['index_name']); ?></code>
                        </div>
                        <?php if (isset($index['error'])): ?>
                            <span class="scrywp-index-status scrywp-index-status-error"><?php esc_html_e('Error', "scry-ms-search"); ?></span>
                        <?php elseif ($index['isIndexing']): ?>
                            <span class="scrywp-index-status scrywp-index-status-indexing"><?php esc_html_e('Indexing...', "scry-ms-search"); ?></span>
                        <?php elseif (isset($index['numberOfDocuments']) && $index['numberOfDocuments'] == 0): ?>
                            <span class="scrywp-index-status scrywp-index-status-empty"><?php esc_html_e('Empty', "scry-ms-search"); ?></span>
                        <?php else: ?>
                            <span class="scrywp-index-status scrywp-index-status-ready"><?php esc_html_e('Ready', "scry-ms-search"); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($index['error'])): ?>
                        <div class="scrywp-index-card-content">
                            <p class="scrywp-index-error-message"><?php echo esc_html($index['error']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="scrywp-index-card-content">
                            <div class="scrywp-index-stat">
                                <span class="scrywp-index-stat-label"><?php esc_html_e('Documents:', "scry-ms-search"); ?></span>
                                <span class="scrywp-index-stat-value"><?php echo number_format($index['numberOfDocuments']); ?></span>
                            </div>
                            
                            <?php if (!empty($index['primaryKey'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php esc_html_e('Primary Key:', "scry-ms-search"); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html($index['primaryKey']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($index['createdAt'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php esc_html_e('Created:', "scry-ms-search"); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($index['createdAt']))); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="scrywp-index-actions">
                                <button type="button" class="button button-primary scrywp-index-posts-button" data-post-type="<?php echo esc_attr($index['name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php esc_html_e('Index All Posts', "scry-ms-search"); ?>
                                  </button>
                                  <button type="button" class="button button-secondary scrywp-wipe-index-button" data-index-name="<?php echo esc_attr($index['index_name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php esc_html_e('Wipe Index', "scry-ms-search"); ?>
                                  </button>
                                  <button 
                                      type="button" 
                                      class="button button-secondary scrywp-search-index-button"
                                      data-index-name="<?php echo esc_attr($index['index_name']); ?>" 
                                      data-index-display="<?php echo esc_attr($index['name']); ?>"
                                      onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').showModal()"
                                  >
                                    <?php esc_html_e('Search Index', "scry-ms-search"); ?>
                                  </button>
                                  <button 
                                      type="button" 
                                      class="button button-secondary scrywp-configure-index-button"
                                      data-index-name="<?php echo esc_attr($index['index_name']); ?>" 
                                      data-index-display="<?php echo esc_attr($index['name']); ?>"
                                      onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').showModal()"
                                  >
                                    <?php esc_html_e('Configure Index', "scry-ms-search"); ?>
                                </button>

                                <dialog id="<?php echo esc_attr($index['index_name']); ?>_settings_dialog" class="scrywp-index-dialog scrywp-index-settings-dialog">
                                    <div class="scrywp-index-dialog-header">
                                        <h3><?php echo esc_html(sprintf(__('Configure Index: %s', "scry-ms-search"), $index['name'])); ?></h3>
                                        <button type="button" class="scrywp-index-dialog-close-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').close()" aria-label="<?php esc_attr_e('Close', "scry-ms-search"); ?>">
                                            ×
                                        </button>
                                    </div>
                                
                                    <div class="scrywp-index-settings-content">
                                        <div class="scrywp-index-settings-loading">
                                            <p><?php esc_html_e('Loading settings...', "scry-ms-search"); ?></p>
                                        </div>
                                        
                                        <div class="scrywp-index-settings-loaded" style="display: none;">
                                            <div class="scrywp-index-settings-section">
                                                <div class="scrywp-index-settings-section-header">
                                                    <h4><?php esc_html_e('Ranking Rules', "scry-ms-search"); ?></h4>
                                                    <a href="https://www.meilisearch.com/docs/learn/relevancy/ranking_rules" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about ranking rules', "scry-ms-search"); ?>">
                                                        <?php esc_html_e('Learn more', "scry-ms-search"); ?>
                                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                                                    </a>
                                                </div>
                                                <p class="description"><?php esc_html_e('Drag and drop to reorder the ranking rules. Rules are applied in order from top to bottom.', "scry-ms-search"); ?></p>
                                                <ul class="scrywp-ranking-rules-list" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                                    <!-- Ranking rules will be populated via JavaScript -->
                                                </ul>
                                            </div>
                                            
                                            <div class="scrywp-index-settings-section">
                                                <div class="scrywp-index-settings-section-header">
                                                    <h4><?php esc_html_e('Searchable Fields', "scry-ms-search"); ?></h4>
                                                    <a href="https://www.meilisearch.com/docs/learn/relevancy/displayed_searchable_attributes#the-searchableattributes-list" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about searchable attributes', "scry-ms-search"); ?>">
                                                        <?php esc_html_e('Learn more', "scry-ms-search"); ?>
                                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                                                    </a>
                                                </div>
                                                <p class="description"><?php esc_html_e('Select which fields should be searchable. The order determines relevancy.', "scry-ms-search"); ?></p>
                                                <div class="scrywp-searchable-fields-tree" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                                    <!-- Searchable fields will be populated via JavaScript -->
                                                </div>
                                            </div>
                                            
                                            <div class="scrywp-index-settings-actions">
                                                <div class="scrywp-index-settings-save-error" style="display: none;">
                                                    <p class="scrywp-index-settings-save-error-message"></p>
                                                </div>
                                                <div class="scrywp-index-settings-actions-buttons">
                                                    <button type="button" class="button button-primary scrywp-save-index-settings-button" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                                        <?php esc_html_e('Save Settings', "scry-ms-search"); ?>
                                                      </button>
                                                      <button type="button" class="button button-secondary scrywp-cancel-index-settings-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').close()">
                                                        <?php esc_html_e('Cancel', "scry-ms-search"); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="scrywp-index-settings-error" style="display: none;">
                                            <p class="scrywp-index-settings-error-message"></p>
                                        </div>
                                    </div>
                                </dialog>

                                <dialog id="<?php echo esc_attr($index['index_name']); ?>_search_dialog" class="scrywp-index-dialog">
                                    <div class="scrywp-index-dialog-header">
                                        <h3><?php echo esc_html(sprintf(__('Search Index: %s', "scry-ms-search"), $index['name'])); ?></h3>
                                        <button type="button" class="scrywp-index-dialog-close-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').close()" aria-label="<?php esc_attr_e('Close', "scry-ms-search"); ?>">
                                            ×
                                        </button>
                                    </div>
                                
                                    <form class="scrywp-index-dialog-search-form" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                        <div class="scrywp-index-dialog-search-input-wrapper">
                                            <input 
                                                type="text" 
                                                name="search_query" 
                                                class="scrywp-index-dialog-search-input" 
                                                placeholder="<?php esc_attr_e('Search the index...', "scry-ms-search"); ?>"
                                                autocomplete="off"
                                            >
                                        </div>
                                    </form>
                                    <div class="scrywp-index-dialog-results">
                                        <div class="scrywp-index-dialog-results-message"><?php esc_html_e('Enter a search query above to search the index.', "scry-ms-search"); ?></div>
                                    </div>
                                </dialog>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
