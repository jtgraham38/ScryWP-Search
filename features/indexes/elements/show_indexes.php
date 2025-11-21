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
                    'error' => $e->getCode() === 404 ? __('Index does not exist', "scry_search_meilisearch") : $e->getMessage(),
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
    $error_message = __('Connection settings are not configured.', "scry_search_meilisearch");
}
?>
<div class="scrywp-indexes-display">
    <?php if (!empty($error_message)): ?>
        <div class="scrywp-indexes-error">
            <p><strong><?php _e('Error:', "scry_search_meilisearch"); ?></strong> <?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($indexes_data)): ?>
        <div class="scrywp-indexes-empty">
            <p><?php _e('No indexes configured. Please select post types to index in the settings.', "scry_search_meilisearch"); ?></p>
        </div>
    <?php else: ?>
        <div class="scrywp-index-all-actions">
            <button type="button" class="button button-primary scrywp-index-all-posts-button">
                <?php _e('Index All Post Types', "scry_search_meilisearch"); ?>
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
                            <span class="scrywp-index-status scrywp-index-status-error"><?php _e('Error', "scry_search_meilisearch"); ?></span>
                        <?php elseif ($index['isIndexing']): ?>
                            <span class="scrywp-index-status scrywp-index-status-indexing"><?php _e('Indexing...', "scry_search_meilisearch"); ?></span>
                        <?php elseif (isset($index['numberOfDocuments']) && $index['numberOfDocuments'] == 0): ?>
                            <span class="scrywp-index-status scrywp-index-status-empty"><?php _e('Empty', "scry_search_meilisearch"); ?></span>
                        <?php else: ?>
                            <span class="scrywp-index-status scrywp-index-status-ready"><?php _e('Ready', "scry_search_meilisearch"); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($index['error'])): ?>
                        <div class="scrywp-index-card-content">
                            <p class="scrywp-index-error-message"><?php echo esc_html($index['error']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="scrywp-index-card-content">
                            <div class="scrywp-index-stat">
                                <span class="scrywp-index-stat-label"><?php _e('Documents:', "scry_search_meilisearch"); ?></span>
                                <span class="scrywp-index-stat-value"><?php echo number_format($index['numberOfDocuments']); ?></span>
                            </div>
                            
                            <?php if (!empty($index['primaryKey'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php _e('Primary Key:', "scry_search_meilisearch"); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html($index['primaryKey']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($index['createdAt'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php _e('Created:', "scry_search_meilisearch"); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($index['createdAt']))); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="scrywp-index-actions">
                                <button type="button" class="button button-primary scrywp-index-posts-button" data-post-type="<?php echo esc_attr($index['name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php _e('Index All Posts', "scry_search_meilisearch"); ?>
                                </button>
                                <button type="button" class="button button-secondary scrywp-wipe-index-button" data-index-name="<?php echo esc_attr($index['index_name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php _e('Wipe Index', "scry_search_meilisearch"); ?>
                                </button>
                                <button 
                                    type="button" 
                                    class="button button-secondary scrywp-search-index-button" 
                                    data-index-name="<?php echo esc_attr($index['index_name']); ?>" 
                                    data-index-display="<?php echo esc_attr($index['name']); ?>"
                                    onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').showModal()"
                                >
                                    <?php _e('Search Index', "scry_search_meilisearch"); ?>
                                </button>
                                <button 
                                    type="button" 
                                    class="button button-secondary scrywp-configure-index-button" 
                                    data-index-name="<?php echo esc_attr($index['index_name']); ?>" 
                                    data-index-display="<?php echo esc_attr($index['name']); ?>"
                                    onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').showModal()"
                                >
                                    <?php _e('Configure Index', "scry_search_meilisearch"); ?>
                                </button>

                                <dialog id="<?php echo esc_attr($index['index_name']); ?>_settings_dialog" class="scrywp-index-dialog scrywp-index-settings-dialog">
                                    <div class="scrywp-index-dialog-header">
                                        <h3><?php printf(__('Configure Index: %s', "scry_search_meilisearch"), esc_html($index['name'])); ?></h3>
                                        <button type="button" class="scrywp-index-dialog-close-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').close()" aria-label="<?php esc_attr_e('Close', "scry_search_meilisearch"); ?>">
                                            ×
                                        </button>
                                    </div>
                                
                                    <div class="scrywp-index-settings-content">
                                        <div class="scrywp-index-settings-loading">
                                            <p><?php _e('Loading settings...', "scry_search_meilisearch"); ?></p>
                                        </div>
                                        
                                        <div class="scrywp-index-settings-loaded" style="display: none;">
                                            <div class="scrywp-index-settings-section">
                                                <div class="scrywp-index-settings-section-header">
                                                    <h4><?php _e('Ranking Rules', "scry_search_meilisearch"); ?></h4>
                                                    <a href="https://www.meilisearch.com/docs/learn/relevancy/ranking_rules" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about ranking rules', "scry_search_meilisearch"); ?>">
                                                        <?php _e('Learn more', "scry_search_meilisearch"); ?>
                                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                                                    </a>
                                                </div>
                                                <p class="description"><?php _e('Drag and drop to reorder the ranking rules. Rules are applied in order from top to bottom.', "scry_search_meilisearch"); ?></p>
                                                <ul class="scrywp-ranking-rules-list" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                                    <!-- Ranking rules will be populated via JavaScript -->
                                                </ul>
                                            </div>
                                            
                                            <div class="scrywp-index-settings-section">
                                                <div class="scrywp-index-settings-section-header">
                                                    <h4><?php _e('Searchable Fields', "scry_search_meilisearch"); ?></h4>
                                                    <a href="https://www.meilisearch.com/docs/learn/relevancy/displayed_searchable_attributes#the-searchableattributes-list" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about searchable attributes', "scry_search_meilisearch"); ?>">
                                                        <?php _e('Learn more', "scry_search_meilisearch"); ?>
                                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                                                    </a>
                                                </div>
                                                <p class="description"><?php _e('Select which fields should be searchable. The order determines relevancy.', "scry_search_meilisearch"); ?></p>
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
                                                        <?php _e('Save Settings', "scry_search_meilisearch"); ?>
                                                    </button>
                                                    <button type="button" class="button button-secondary scrywp-cancel-index-settings-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').close()">
                                                        <?php _e('Cancel', "scry_search_meilisearch"); ?>
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
                                        <h3><?php printf(__('Search Index: %s', "scry_search_meilisearch"), esc_html($index['name'])); ?></h3>
                                        <button type="button" class="scrywp-index-dialog-close-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').close()" aria-label="<?php esc_attr_e('Close', "scry_search_meilisearch"); ?>">
                                            ×
                                        </button>
                                    </div>
                                
                                    <form class="scrywp-index-dialog-search-form" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                        <div class="scrywp-index-dialog-search-input-wrapper">
                                            <input 
                                                type="text" 
                                                name="search_query" 
                                                class="scrywp-index-dialog-search-input" 
                                                placeholder="<?php esc_attr_e('Search the index...', "scry_search_meilisearch"); ?>"
                                                autocomplete="off"
                                            >
                                        </div>
                                    </form>
                                    <div class="scrywp-index-dialog-results">
                                        <div class="scrywp-index-dialog-results-message"><?php _e('Enter a search query above to search the index.', "scry_search_meilisearch"); ?></div>
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

