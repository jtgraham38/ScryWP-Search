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
                    'error' => $e->getCode() === 404 ? __('Index does not exist', "scry-search") : $e->getMessage(),
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
    $error_message = __('Connection settings are not configured.', "scry-search");
}
?>
<div class="scrywp-indexes-display">
    <?php if (!empty($error_message)): ?>
        <div class="scrywp-indexes-error">
            <p><strong><?php esc_html_e('Error:', "scry-search"); ?></strong> <?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($indexes_data)): ?>
        <div class="scrywp-indexes-empty">
            <p><?php esc_html_e('No indexes configured. Please select post types to index in the settings.', "scry-search"); ?></p>
        </div>
    <?php else: ?>
        <div class="scrywp-index-all-actions">
            <button type="button" class="button button-primary scrywp-index-all-posts-button">
                <?php esc_html_e('Index All Post Types', "scry-search"); ?>
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
                            <span class="scrywp-index-status scrywp-index-status-error"><?php esc_html_e('Error', "scry-search"); ?></span>
                        <?php elseif ($index['isIndexing']): ?>
                            <span class="scrywp-index-status scrywp-index-status-indexing"><?php esc_html_e('Indexing...', "scry-search"); ?></span>
                        <?php elseif (isset($index['numberOfDocuments']) && $index['numberOfDocuments'] == 0): ?>
                            <span class="scrywp-index-status scrywp-index-status-empty"><?php esc_html_e('Empty', "scry-search"); ?></span>
                        <?php else: ?>
                            <span class="scrywp-index-status scrywp-index-status-ready"><?php esc_html_e('Ready', "scry-search"); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($index['error'])): ?>
                        <div class="scrywp-index-card-content">
                            <p class="scrywp-index-error-message"><?php echo esc_html($index['error']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="scrywp-index-card-content">
                            <div class="scrywp-index-stat">
                                <span class="scrywp-index-stat-label"><?php esc_html_e('Documents:', "scry-search"); ?></span>
                                <span class="scrywp-index-stat-value"><?php echo number_format($index['numberOfDocuments']); ?></span>
                            </div>
                            
                            <?php if (!empty($index['primaryKey'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php esc_html_e('Primary Key:', "scry-search"); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html($index['primaryKey']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($index['createdAt'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php esc_html_e('Created:', "scry-search"); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($index['createdAt']))); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="scrywp-index-actions">
                                <button type="button" class="button button-primary scrywp-index-posts-button" data-post-type="<?php echo esc_attr($index['name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php esc_html_e('Index All Posts', "scry-search"); ?>
                                  </button>
                                  <button type="button" class="button button-secondary scrywp-wipe-index-button" data-index-name="<?php echo esc_attr($index['index_name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php esc_html_e('Wipe Index', "scry-search"); ?>
                                  </button>
                                  <button 
                                      type="button" 
                                      class="button button-secondary scrywp-search-index-button"
                                      data-index-name="<?php echo esc_attr($index['index_name']); ?>" 
                                      data-index-display="<?php echo esc_attr($index['name']); ?>"
                                      onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').showModal()"
                                  >
                                    <?php esc_html_e('Search Index', "scry-search"); ?>
                                  </button>
                                  <button 
                                      type="button" 
                                      class="button button-secondary scrywp-configure-index-button"
                                      data-index-name="<?php echo esc_attr($index['index_name']); ?>" 
                                      data-index-display="<?php echo esc_attr($index['name']); ?>"
                                      onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').showModal()"
                                  >
                                    <?php esc_html_e('Configure Index', "scry-search"); ?>
                                </button>

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
                                        
                                        <div class="scrywp-index-settings-loaded" style="display: none;">
                                            <div class="scrywp-index-settings-section">
                                                <div class="scrywp-index-settings-section-header">
                                                    <h4><?php esc_html_e('Ranking Rules', "scry-search"); ?></h4>
                                                    <a href="https://www.meilisearch.com/docs/learn/relevancy/ranking_rules" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about ranking rules', "scry-search"); ?>">
                                                        <?php esc_html_e('Learn more', "scry-search"); ?>
                                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                                                    </a>
                                                </div>
                                                <p class="description"><?php esc_html_e('Drag and drop to reorder the ranking rules. Rules are applied in order from top to bottom.', "scry-search"); ?></p>
                                                <ul class="scrywp-ranking-rules-list" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                                    <!-- Ranking rules will be populated via JavaScript -->
                                                </ul>
                                            </div>
                                            
                                            <div class="scrywp-index-settings-section">
                                                <div class="scrywp-index-settings-section-header">
                                                    <h4><?php esc_html_e('Searchable Fields', "scry-search"); ?></h4>
                                                    <a href="https://www.meilisearch.com/docs/learn/relevancy/displayed_searchable_attributes#the-searchableattributes-list" target="_blank" class="scrywp-index-settings-help-link" title="<?php esc_attr_e('Learn more about searchable attributes', "scry-search"); ?>">
                                                        <?php esc_html_e('Learn more', "scry-search"); ?>
                                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
                                                    </a>
                                                </div>
                                                <p class="description"><?php esc_html_e('Select which fields should be searchable. The order determines relevancy.', "scry-search"); ?></p>
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
                                                        <?php esc_html_e('Save Settings', "scry-search"); ?>
                                                      </button>
                                                      <button type="button" class="button button-secondary scrywp-cancel-index-settings-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_settings_dialog').close()">
                                                        <?php esc_html_e('Cancel', "scry-search"); ?>
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
                                        <h3><?php echo esc_html(sprintf(__('Search Index: %s', "scry-search"), $index['name'])); ?></h3>
                                        <button type="button" class="scrywp-index-dialog-close-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').close()" aria-label="<?php esc_attr_e('Close', "scry-search"); ?>">
                                            ×
                                        </button>
                                    </div>
                                
                                    <form class="scrywp-index-dialog-search-form" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                        <div class="scrywp-index-dialog-search-input-wrapper">
                                            <input 
                                                type="text" 
                                                name="search_query" 
                                                class="scrywp-index-dialog-search-input" 
                                                placeholder="<?php esc_attr_e('Search the index...', "scry-search"); ?>"
                                                autocomplete="off"
                                            >
                                        </div>
                                    </form>
                                    <div class="scrywp-index-dialog-results">
                                        <div class="scrywp-index-dialog-results-message"><?php esc_html_e('Enter a search query above to search the index.', "scry-search"); ?></div>
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

<style>
.scrywp-indexes-display {
    max-width: 1200px;
    margin: 20px 0;
}

.scrywp-indexes-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.scrywp-indexes-empty {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 30px;
    text-align: center;
    color: #646970;
}

.scrywp-index-all-actions {
    margin: 20px 0;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e5e5;
}

.scrywp-index-all-actions .scrywp-index-all-posts-button {
    font-size: 14px;
    padding: 8px 16px;
    height: auto;
}

.scrywp-index-all-actions .scrywp-index-all-posts-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.scrywp-indexes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.scrywp-index-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.scrywp-index-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.scrywp-index-card-error {
    border-color: #dc3232;
    background: #fef7f7;
}

.scrywp-index-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e5e5;
}

.scrywp-index-card-title-container {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.scrywp-index-card-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #23282d;
    word-break: break-word;
}

.scrywp-index-card-subtitle {
    font-size: 12px;
    color: #646970;
}

.scrywp-index-status {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    white-space: nowrap;
}

.scrywp-index-status-ready {
    background: #d4edda;
    color: #155724;
}

.scrywp-index-status-indexing {
    background: #fff3cd;
    color: #856404;
    animation: pulse 2s ease-in-out infinite;
}

.scrywp-index-status-error {
    background: #f8d7da;
    color: #721c24;
}

.scrywp-index-status-empty {
    background: #ffeaa7;
    color: #856404;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.scrywp-index-card-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.scrywp-index-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.scrywp-index-stat-label {
    color: #646970;
    font-size: 14px;
}

.scrywp-index-stat-value {
    color: #23282d;
    font-size: 14px;
    font-weight: 600;
}

.scrywp-index-error-message {
    color: #721c24;
    margin: 0;
    padding: 10px;
    background: #f8d7da;
    border-radius: 4px;
    border-left: 3px solid #dc3232;
}

@media (max-width: 782px) {
    .scrywp-indexes-grid {
        grid-template-columns: 1fr;
    }
    
    .scrywp-index-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .scrywp-index-card-title {
        margin-right: 0;
    }
}

@media (min-width: 783px) and (max-width: 1024px) {
    .scrywp-indexes-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.scrywp-index-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e5e5e5;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.scrywp-wipe-index-button {
    width: 100%;
    background-color: #dc3232;
    border-color: #dc3232;
    color: #fff;
}

.scrywp-wipe-index-button:hover,
.scrywp-wipe-index-button:focus {
    background-color: #a00;
    border-color: #a00;
    color: #fff;
}

.scrywp-wipe-index-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Search Dialog Styles */
.scrywp-index-dialog {
    width: 90%;
    max-width: 800px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.scrywp-index-dialog::backdrop {
    background: rgba(0, 0, 0, 0.5);
}

.scrywp-index-dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e5e5;
    background: #f9f9f9;
}

.scrywp-index-dialog-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #23282d;
}

.scrywp-index-dialog-close-button {
    background: none;
    border: none;
    font-size: 32px;
    line-height: 1;
    color: #646970;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}

.scrywp-index-dialog-close-button:hover {
    color: #dc3232;
}

.scrywp-index-dialog-search-form {
    padding: 20px;
    border-bottom: 1px solid #e5e5e5;
}

.scrywp-index-dialog-search-input-wrapper {
    display: flex;
    gap: 10px;
}

.scrywp-index-dialog-search-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.scrywp-index-dialog-search-input:focus {
    outline: none;
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

.scrywp-index-dialog-results {
    padding: 20px;
    max-height: 500px;
    overflow-y: auto;
}

.scrywp-index-dialog-results-message {
    color: #646970;
    text-align: center;
    padding: 40px 20px;
    font-style: italic;
}

.scrywp-index-dialog-result {
    padding: 15px;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    margin-bottom: 15px;
    background: #fff;
    transition: all 0.2s ease;
}

.scrywp-index-dialog-result:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.scrywp-index-dialog-result:last-child {
    margin-bottom: 0;
}

.scrywp-index-dialog-result-title {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #23282d;
}

.scrywp-index-dialog-result-excerpt {
    margin: 0 0 12px 0;
    color: #646970;
    font-size: 14px;
    line-height: 1.5;
}

.scrywp-index-dialog-result-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #8c8f94;
    margin-bottom: 12px;
}

.scrywp-index-dialog-result-json {
    margin: 12px 0;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    background: #f9f9f9;
}

.scrywp-index-dialog-result-json-toggle {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #2271b1;
    user-select: none;
    list-style: none;
    transition: background 0.2s ease;
}

.scrywp-index-dialog-result-json-toggle:hover {
    background: #f0f0f0;
}

.scrywp-index-dialog-result-json-toggle::-webkit-details-marker {
    display: none;
}

.scrywp-index-dialog-result-json-toggle::before {
    content: '▶';
    display: inline-block;
    margin-right: 6px;
    font-size: 10px;
    transition: transform 0.2s ease;
}

details[open] .scrywp-index-dialog-result-json-toggle::before {
    transform: rotate(90deg);
}

.scrywp-index-dialog-result-json-content {
    padding: 12px;
    margin: 0;
    background: #fff;
    border-top: 1px solid #e5e5e5;
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    line-height: 1.5;
    overflow-x: auto;
    white-space: pre;
    max-height: 400px;
    overflow-y: auto;
}

.scrywp-index-dialog-result-actions {
    display: flex;
    gap: 10px;
}

.scrywp-index-dialog-result-link {
    display: inline-block;
    padding: 6px 12px;
    background: #2271b1;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    transition: background 0.2s ease;
}

.scrywp-index-dialog-result-link:hover {
    background: #135e96;
    color: #fff;
}

.scrywp-index-dialog-result-edit-link {
    display: inline-block;
    padding: 6px 12px;
    background: #646970;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    transition: background 0.2s ease;
}

.scrywp-index-dialog-result-edit-link:hover {
    background: #50575e;
    color: #fff;
}

.scrywp-index-dialog-loading {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.scrywp-index-dialog-no-results {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
    font-style: italic;
}

/* Index Settings Dialog Styles */
.scrywp-index-settings-dialog {
    max-width: 900px;
}

.scrywp-index-settings-content {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.scrywp-index-settings-loading {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.scrywp-index-settings-error {
    padding: 20px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    color: #721c24;
}

.scrywp-index-settings-error-message {
    margin: 0;
}

.scrywp-index-settings-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e5e5;
}

.scrywp-index-settings-section:last-of-type {
    border-bottom: none;
}

.scrywp-index-settings-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.scrywp-index-settings-section h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #23282d;
}

.scrywp-index-settings-help-link {
    font-size: 13px;
    color: #2271b1;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    transition: color 0.2s ease;
}

.scrywp-index-settings-help-link:hover {
    color: #135e96;
    text-decoration: underline;
}

.scrywp-index-settings-section .description {
    margin: 0 0 15px 0;
    color: #646970;
    font-size: 13px;
    font-style: italic;
}

/* Ranking Rules Styles */
.scrywp-ranking-rules-list {
    list-style: none;
    margin: 0;
    padding: 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    background: #fff;
}

.scrywp-ranking-rule-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #e5e5e5;
    cursor: move;
    background: #fff;
    transition: background 0.2s ease;
}

.scrywp-ranking-rule-item:last-child {
    border-bottom: none;
}

.scrywp-ranking-rule-item:hover {
    background: #f9f9f9;
}

.scrywp-ranking-rule-item.scrywp-ranking-rule-drag-over {
    background: #e7f5ff;
    border-top: 2px solid #2271b1;
}

.scrywp-ranking-rule-handle {
    margin-right: 12px;
    color: #8c8f94;
    font-size: 18px;
    cursor: grab;
    user-select: none;
}

.scrywp-ranking-rule-handle:active {
    cursor: grabbing;
}

.scrywp-ranking-rule-label {
    flex: 1;
    font-size: 14px;
    color: #23282d;
    font-family: 'Courier New', Courier, monospace;
}

/* Searchable Fields Styles */
.scrywp-searchable-fields-tree {
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    background: #fff;
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.scrywp-searchable-field-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    margin: 2px 0;
    cursor: pointer;
    border-radius: 3px;
    transition: background 0.2s ease;
}

.scrywp-searchable-field-item:hover {
    background: #f9f9f9;
}

.scrywp-searchable-field-checkbox {
    margin-right: 10px;
    cursor: pointer;
}

.scrywp-searchable-field-item span {
    font-size: 14px;
    color: #23282d;
    user-select: none;
}

.scrywp-searchable-field-group {
    margin: 5px 0;
}

.scrywp-searchable-field-group-label {
    display: flex;
    align-items: center;
    padding: 10px;
    font-weight: 600;
    background: #f9f9f9;
    border-radius: 3px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.scrywp-searchable-field-group-label:hover {
    background: #f0f0f0;
}

.scrywp-searchable-field-group-label span {
    flex: 1;
    font-size: 14px;
    color: #23282d;
}

.scrywp-searchable-field-expand {
    background: none;
    border: none;
    color: #646970;
    font-size: 12px;
    cursor: pointer;
    padding: 0 8px;
    margin-left: 8px;
    transition: color 0.2s ease;
}

.scrywp-searchable-field-expand:hover {
    color: #2271b1;
}

.scrywp-searchable-field-children {
    margin-left: 30px;
    margin-top: 5px;
    padding-left: 10px;
    border-left: 2px solid #e5e5e5;
}

.scrywp-searchable-field-children .scrywp-searchable-field-item {
    padding-left: 5px;
}

/* Settings Actions */
.scrywp-index-settings-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e5e5;
}

.scrywp-index-settings-actions-buttons {
    display: flex;
    gap: 10px;
}

.scrywp-save-index-settings-button {
    min-width: 120px;
}

.scrywp-cancel-index-settings-button {
    min-width: 120px;
}

.scrywp-index-settings-save-error {
    padding: 10px 12px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    color: #721c24;
}

.scrywp-index-settings-save-error-message {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle index posts button clicks
    var indexPostsButtons = document.querySelectorAll('.scrywp-index-posts-button');
    
    indexPostsButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            var button = this;
            var postType = button.getAttribute('data-post-type');
            var indexDisplay = button.getAttribute('data-index-display');
            
            // Request confirmation
            var confirmed = confirm(
                'Are you sure you want to index all posts of type "' + indexDisplay + '"? This will add or update all posts in the index.'
            );
            
            if (!confirmed) {
                return;
            }
            
            // Disable button and show loading state
            button.disabled = true;
            var originalText = button.textContent;
            button.textContent = '<?php echo esc_js(__('Indexing...', "scry-search")); ?>';
            
            // Prepare AJAX request
            var formData = new FormData();
            formData.append('action', '<?php echo esc_js($this->prefixed('index_posts')); ?>');
            formData.append('nonce', '<?php echo esc_js(wp_create_nonce($this->prefixed('index_posts'))); ?>');
            formData.append('post_type', postType);
            
            // Send AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Show success message
                    alert(data.data.message || '<?php echo esc_js(__('Posts indexed successfully', "scry-search")); ?>');
                    // Reload page to refresh the index list
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    // Show error message
                    alert('<?php echo esc_js(__('Error:', "scry-search")); ?> ' + (data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Failed to index posts', "scry-search")); ?>'));
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(function(error) {
                // Show error message
                alert('<?php echo esc_js(__('Error:', "scry-search")); ?> <?php echo esc_js(__('Failed to index posts', "scry-search")); ?>');
                button.disabled = false;
                button.textContent = originalText;
            });
        });
    });
    
    // Handle index all posts button click
    var indexAllButton = document.querySelector('.scrywp-index-all-posts-button');
    if (indexAllButton) {
        indexAllButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            var button = this;
            
            // Get all index post buttons (excluding ones with errors)
            var allIndexButtons = Array.from(document.querySelectorAll('.scrywp-index-posts-button'));
            var validIndexButtons = allIndexButtons.filter(function(btn) {
                // Check if the button's parent card has an error
                var card = btn.closest('.scrywp-index-card');
                return card && !card.classList.contains('scrywp-index-card-error');
            });
            
            if (validIndexButtons.length === 0) {
                alert('<?php echo esc_js(__('No valid indexes to index.', "scry-search")); ?>');
                return;
            }
            
            // Request confirmation
            var postTypes = validIndexButtons.map(function(btn) {
                return btn.getAttribute('data-index-display');
            }).join(', ');
            
            var confirmed = confirm(
                'Are you sure you want to index all post types? This will index:\n\n' + postTypes + '\n\nThis may take a while.'
            );
            
            if (!confirmed) {
                return;
            }
            
            // Disable button and show loading state
            button.disabled = true;
            var originalText = button.textContent;
            button.textContent = '<?php echo esc_js(__('Indexing All...', "scry-search")); ?>';
            
            // Disable all individual index buttons
            validIndexButtons.forEach(function(btn) {
                btn.disabled = true;
            });
            
            // Process each index sequentially
            var processIndex = function(index) {
                if (index >= validIndexButtons.length) {
                    // All done, reload page
                    alert('<?php echo esc_js(__('All post types have been indexed successfully.', "scry-search")); ?>');
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                    return;
                }
                
                var currentButton = validIndexButtons[index];
                var postType = currentButton.getAttribute('data-post-type');
                var indexDisplay = currentButton.getAttribute('data-index-display');
                
                // Update button text to show current progress
                button.textContent = '<?php echo esc_js(__('Indexing', "scry-search")); ?>: ' + indexDisplay + ' (' + (index + 1) + '/' + validIndexButtons.length + ')';
                
                // Prepare AJAX request
                var formData = new FormData();
                formData.append('action', '<?php echo esc_js($this->prefixed('index_posts')); ?>');
                formData.append('nonce', '<?php echo esc_js(wp_create_nonce($this->prefixed('index_posts'))); ?>');
                formData.append('post_type', postType);
                
                // Send AJAX request
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        // Move to next index
                        processIndex(index + 1);
                    } else {
                        // Show error but continue with next index
                        var errorMsg = data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Failed to index', "scry-search")); ?>';
                        console.error('Failed to index ' + indexDisplay + ': ' + errorMsg);
                        // Continue with next index anyway
                        processIndex(index + 1);
                    }
                })
                .catch(function(error) {
                    // Show error but continue with next index
                    console.error('Error indexing ' + indexDisplay + ':', error);
                    // Continue with next index anyway
                    processIndex(index + 1);
                });
            };
            
            // Start processing
            processIndex(0);
        });
    }
    
    // Handle wipe index button clicks
    var wipeButtons = document.querySelectorAll('.scrywp-wipe-index-button');
    
    wipeButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            var button = this;
            var indexName = button.getAttribute('data-index-name');
            var indexDisplay = button.getAttribute('data-index-display');
            
            // Request confirmation
            var confirmed = confirm(
                'Are you sure you want to wipe the index? All documents will be deleted. The index will be recreated automatically.'
            );
            
            if (!confirmed) {
                return;
            }
            
            // Disable button and show loading state
            button.disabled = true;
            var originalText = button.textContent;
            button.textContent = '<?php echo esc_js(__('Wiping...', "scry-search")); ?>';
            
            // Prepare AJAX request
            var formData = new FormData();
            formData.append('action', '<?php echo esc_js($this->prefixed('wipe_index')); ?>');
            formData.append('nonce', '<?php echo esc_js(wp_create_nonce($this->prefixed('wipe_index'))); ?>');
            formData.append('index_name', indexName);
            
            // Send AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Show success message and reload page after a short delay
                    alert(data.data.message || '<?php echo esc_js(__('Index wiped successfully', "scry-search")); ?>');
                    
                    // Reload page to refresh the index list
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    // Show error message
                    alert('<?php echo esc_js(__('Error:', "scry-search")); ?> ' + (data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Failed to wipe index', "scry-search")); ?>'));
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(function(error) {
                // Show error message
                alert('<?php echo esc_js(__('Error:', "scry-search")); ?> <?php echo esc_js(__('Failed to wipe index', "scry-search")); ?>');
                button.disabled = false;
                button.textContent = originalText;
            });
        });
    });
    
    // Handle search index input changes (instant search)
    var searchInputs = document.querySelectorAll('.scrywp-index-dialog-search-input');
    var searchTimeouts = {};
    console.log("something");
    
    searchInputs.forEach(function(searchInput) {
        var form = searchInput.closest('.scrywp-index-dialog-search-form');
        if (!form) return;
        
        var indexName = form.getAttribute('data-index-name');
        if (!indexName) return;
        
        var dialog = form.closest('.scrywp-index-dialog');
        if (!dialog) return;
        
        var resultsContainer = dialog.querySelector('.scrywp-index-dialog-results');
        if (!resultsContainer) return;
        
        var inputId = indexName; // Use index name as unique ID for timeout
        
        // Search function
        var performSearch = function() {
            var searchQuery = searchInput.value.trim();
            
            // If query is empty, show initial message
            if (!searchQuery) {
                resultsContainer.innerHTML = '<div class="scrywp-index-dialog-results-message"><?php echo esc_js(__('Enter a search query above to search the index.', "scry-search")); ?></div>';
                return;
            }
            
            // Show loading state
            resultsContainer.innerHTML = '<div class="scrywp-index-dialog-loading"><?php echo esc_js(__('Searching...', "scry-search")); ?></div>';
            
            // Prepare AJAX request
            var formData = new FormData();
            formData.append('action', '<?php echo esc_js($this->prefixed('search_index')); ?>');
            formData.append('nonce', '<?php echo esc_js(wp_create_nonce($this->prefixed('search_index'))); ?>');
            formData.append('index_name', indexName);
            formData.append('search_query', searchQuery);
            
            // Send AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    var results = data.data.results || [];
                    
                    if (results.length === 0) {
                        resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results"><?php echo esc_js(__('No results found.', "scry-search")); ?></div>';
                        return;
                    }
                    
                    // Helper function to escape HTML for JSON display
                    function escapeHtml(text) {
                        if (text === null || text === undefined) {
                            return '';
                        }
                        var map = {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#039;'
                        };
                        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
                    }
                    
                    // Helper function to escape URLs for href attributes
                    function escapeUrl(url) {
                        if (!url) return '';
                        // Convert to string and escape HTML entities
                        var escaped = escapeHtml(String(url));
                        // Additional escaping for URL-specific characters in attributes
                        return escaped.replace(/ /g, '%20');
                    }
                    
                    // Build results HTML
                    var resultsHTML = '';
                    var viewPostLabel = '<?php echo esc_js(__('View Post', "scry-search")); ?>';
                    var editPostLabel = '<?php echo esc_js(__('Edit Post', "scry-search")); ?>';
                    var untitledLabel = '<?php echo esc_js(__('Untitled', "scry-search")); ?>';
                    
                    results.forEach(function(result) {
                        resultsHTML += '<div class="scrywp-index-dialog-result">';
                        resultsHTML += '<h4 class="scrywp-index-dialog-result-title">' + escapeHtml(result.title || untitledLabel) + '</h4>';
                        
                        if (result.excerpt) {
                            resultsHTML += '<p class="scrywp-index-dialog-result-excerpt">' + escapeHtml(result.excerpt) + '</p>';
                        }
                        
                        resultsHTML += '<div class="scrywp-index-dialog-result-meta">';
                        resultsHTML += '<span>' + escapeHtml(String(result.ID || '')) + '</span>';
                        resultsHTML += '<span>' + escapeHtml(result.post_type || '') + '</span>';
                        resultsHTML += '<span>' + escapeHtml(result.post_status || '') + '</span>';
                        if (result.post_date) {
                            resultsHTML += '<span>' + escapeHtml(result.post_date) + '</span>';
                        }
                        resultsHTML += '</div>';
                        
                        // Add raw JSON dropdown
                        resultsHTML += '<details class="scrywp-index-dialog-result-json">';
                        resultsHTML += '<summary class="scrywp-index-dialog-result-json-toggle"><?php echo esc_js(__('View Raw JSON', "scry-search")); ?></summary>';
                        resultsHTML += '<pre class="scrywp-index-dialog-result-json-content">' + escapeHtml(JSON.stringify(result, null, 2)) + '</pre>';
                        resultsHTML += '</details>';
                        
                        resultsHTML += '<div class="scrywp-index-dialog-result-actions">';
                        if (result.permalink) {
                            // Escape URL for href attribute
                            var escapedPermalink = escapeUrl(result.permalink);
                            resultsHTML += '<a href="' + escapedPermalink + '" target="_blank" class="scrywp-index-dialog-result-link" aria-label="' + viewPostLabel + '">' + viewPostLabel + '</a>';
                        }
                        if (result.edit_link) {
                            // Escape URL for href attribute
                            var escapedEditLink = escapeUrl(result.edit_link);
                            resultsHTML += '<a href="' + escapedEditLink + '" target="_blank" class="scrywp-index-dialog-result-edit-link" aria-label="' + editPostLabel + '">' + editPostLabel + '</a>';
                        }
                        resultsHTML += '</div>';
                        
                        resultsHTML += '</div>';
                    });
                    
                    resultsContainer.innerHTML = resultsHTML;
                } else {
                    var errorMessage = data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Search failed', "scry-search")); ?>';
                    resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results"><?php echo esc_js(__('Error:', "scry-search")); ?> ' + errorMessage + '</div>';
                }
            })
            .catch(function(error) {
                resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results"><?php echo esc_js(__('Error: Failed to search index', "scry-search")); ?></div>';
            });
        };
        
        // Handle input events with debouncing (300ms delay)
        searchInput.addEventListener('input', function() {
            // Clear existing timeout
            if (searchTimeouts[inputId]) {
                clearTimeout(searchTimeouts[inputId]);
            }
            
            // Set new timeout
            searchTimeouts[inputId] = setTimeout(performSearch, 300);
        });
        
        // Also handle form submission (in case user presses Enter)
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            // Clear timeout and perform search immediately
            if (searchTimeouts[inputId]) {
                clearTimeout(searchTimeouts[inputId]);
            }
            performSearch();
        });
    });
    
    // Handle index settings dialog
    var configureButtons = document.querySelectorAll('.scrywp-configure-index-button');
    
    configureButtons.forEach(function(configureButton) {
        var indexName = configureButton.getAttribute('data-index-name');
        if (!indexName) return;
        
        var dialog = document.getElementById(indexName + '_settings_dialog');
        if (!dialog) return;
        
        var rulesList = dialog.querySelector('.scrywp-ranking-rules-list');
        var fieldsTree = dialog.querySelector('.scrywp-searchable-fields-tree');
        var loadingDiv = dialog.querySelector('.scrywp-index-settings-loading');
        var loadedDiv = dialog.querySelector('.scrywp-index-settings-loaded');
        var errorDiv = dialog.querySelector('.scrywp-index-settings-error');
        var saveButton = dialog.querySelector('.scrywp-save-index-settings-button');
        var saveErrorDiv = dialog.querySelector('.scrywp-index-settings-save-error');
        var saveErrorMessage = dialog.querySelector('.scrywp-index-settings-save-error-message');
        
        var currentRankingRules = [];
        var currentSearchableAttributes = [];
        var availableFields = {};
        
        // Store original button text
        var originalSaveButtonText = saveButton ? saveButton.textContent : '';
        
        // Reset button state function
        function resetSaveButton() {
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.textContent = originalSaveButtonText || '<?php echo esc_js(__('Save Settings', "scry-search")); ?>';
            }
            // Hide save error message
            if (saveErrorDiv) {
                saveErrorDiv.style.display = 'none';
            }
        }
        
        // Show save error function
        function showSaveError(message) {
            console.log('showSaveError called with:', message);
            console.log('saveErrorDiv:', saveErrorDiv);
            console.log('saveErrorMessage:', saveErrorMessage);
            if (saveErrorDiv && saveErrorMessage) {
                saveErrorMessage.textContent = message;
                saveErrorDiv.style.display = 'block';
                console.log('Error div should now be visible');
            } else {
                console.error('Error: saveErrorDiv or saveErrorMessage not found!');
            }
        }
        
        // Load settings when dialog opens
        configureButton.addEventListener('click', function() {
            // Reset button state when dialog opens
            resetSaveButton();
            
            // Small delay to ensure dialog is open
            setTimeout(function() {
                loadIndexSettings(indexName);
            }, 100);
        });
        
        // Function to load index settings
        function loadIndexSettings(indexName) {
            loadingDiv.style.display = 'block';
            loadedDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            
            // Reset button state when loading
            resetSaveButton();
            
            var formData = new FormData();
            formData.append('action', '<?php echo esc_js($this->prefixed('get_index_settings')); ?>');
            formData.append('nonce', '<?php echo esc_js(wp_create_nonce($this->prefixed('get_index_settings'))); ?>');
            formData.append('index_name', indexName);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    currentRankingRules = data.data.ranking_rules || [];
                    currentSearchableAttributes = data.data.searchable_attributes || [];
                    availableFields = data.data.available_fields || {};
                    
                    renderRankingRules();
                    renderSearchableFields();
                    
                    loadingDiv.style.display = 'none';
                    loadedDiv.style.display = 'block';
                    
                    // Reset button state after successful load
                    resetSaveButton();
                } else {
                    showSettingsError(data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Failed to load settings', "scry-search")); ?>');
                }
            })
            .catch(function(error) {
                showSettingsError('<?php echo esc_js(__('Error: Failed to load settings', "scry-search")); ?>');
            });
        }
        
        // Function to show error
        function showSettingsError(message) {
            loadingDiv.style.display = 'none';
            loadedDiv.style.display = 'none';
            errorDiv.style.display = 'block';
            errorDiv.querySelector('.scrywp-index-settings-error-message').textContent = message;
        }
        
        // Function to render ranking rules
        function renderRankingRules() {
            if (!rulesList) return;
            
            rulesList.innerHTML = '';
            
            currentRankingRules.forEach(function(rule, index) {
                var li = document.createElement('li');
                li.className = 'scrywp-ranking-rule-item';
                li.draggable = true;
                li.dataset.rule = rule;
                li.dataset.index = index;
                
                var handle = document.createElement('span');
                handle.className = 'scrywp-ranking-rule-handle';
                handle.textContent = '☰';
                handle.setAttribute('aria-label', '<?php echo esc_js(__('Drag to reorder', "scry-search")); ?>');
                
                var label = document.createElement('span');
                label.className = 'scrywp-ranking-rule-label';
                label.textContent = rule;
                
                li.appendChild(handle);
                li.appendChild(label);
                rulesList.appendChild(li);
            });
            
            // Setup drag and drop
            setupDragAndDrop();
        }
        
        // Function to setup drag and drop
        function setupDragAndDrop() {
            var items = rulesList.querySelectorAll('.scrywp-ranking-rule-item');
            var draggedElement = null;
            
            items.forEach(function(item) {
                item.addEventListener('dragstart', function(e) {
                    draggedElement = this;
                    this.style.opacity = '0.5';
                    e.dataTransfer.effectAllowed = 'move';
                });
                
                item.addEventListener('dragend', function(e) {
                    this.style.opacity = '1';
                    items.forEach(function(it) {
                        it.classList.remove('scrywp-ranking-rule-drag-over');
                    });
                });
                
                item.addEventListener('dragover', function(e) {
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                    e.dataTransfer.dropEffect = 'move';
                    if (this !== draggedElement) {
                        this.classList.add('scrywp-ranking-rule-drag-over');
                    }
                    return false;
                });
                
                item.addEventListener('dragleave', function(e) {
                    this.classList.remove('scrywp-ranking-rule-drag-over');
                });
                
                item.addEventListener('drop', function(e) {
                    if (e.stopPropagation) {
                        e.stopPropagation();
                    }
                    
                    if (draggedElement !== this) {
                        var allItems = Array.from(rulesList.querySelectorAll('.scrywp-ranking-rule-item'));
                        var draggedIndex = allItems.indexOf(draggedElement);
                        var targetIndex = allItems.indexOf(this);
                        
                        // Reorder array
                        var rule = currentRankingRules[draggedIndex];
                        currentRankingRules.splice(draggedIndex, 1);
                        currentRankingRules.splice(targetIndex, 0, rule);
                        
                        // Re-render
                        renderRankingRules();
                    }
                    
                    return false;
                });
            });
        }
        
        // Function to render searchable fields
        function renderSearchableFields() {
            if (!fieldsTree) return;
            
            fieldsTree.innerHTML = '';
            
            // Build field tree
            Object.keys(availableFields).forEach(function(fieldKey) {
                var field = availableFields[fieldKey];
                var isChecked = currentSearchableAttributes.indexOf(field.path) !== -1;
                
                if (field.type === 'group' && field.children) {
                    // Render group with children
                    var groupDiv = document.createElement('div');
                    groupDiv.className = 'scrywp-searchable-field-group';
                    
                    var groupLabel = document.createElement('label');
                    groupLabel.className = 'scrywp-searchable-field-group-label';
                    
                    var groupCheckbox = document.createElement('input');
                    groupCheckbox.type = 'checkbox';
                    groupCheckbox.className = 'scrywp-searchable-field-checkbox';
                    groupCheckbox.dataset.fieldPath = field.path;
                    groupCheckbox.checked = isChecked;
                    
                    var groupSpan = document.createElement('span');
                    groupSpan.textContent = field.label;
                    
                    groupLabel.appendChild(groupCheckbox);
                    groupLabel.appendChild(groupSpan);
                    
                    var expandButton = document.createElement('button');
                    expandButton.type = 'button';
                    expandButton.className = 'scrywp-searchable-field-expand';
                    expandButton.textContent = '▶';
                    expandButton.setAttribute('aria-label', '<?php echo esc_js(__('Expand', "scry-search")); ?>');
                    
                    groupLabel.appendChild(expandButton);
                    groupDiv.appendChild(groupLabel);
                    
                    var childrenDiv = document.createElement('div');
                    childrenDiv.className = 'scrywp-searchable-field-children';
                    childrenDiv.style.display = 'none';
                    
                    Object.keys(field.children).forEach(function(childKey) {
                        var child = field.children[childKey];
                        var childIsChecked = currentSearchableAttributes.indexOf(child.path) !== -1;
                        
                        var childLabel = document.createElement('label');
                        childLabel.className = 'scrywp-searchable-field-item';
                        
                        var childCheckbox = document.createElement('input');
                        childCheckbox.type = 'checkbox';
                        childCheckbox.className = 'scrywp-searchable-field-checkbox';
                        childCheckbox.dataset.fieldPath = child.path;
                        childCheckbox.checked = childIsChecked;
                        
                        var childSpan = document.createElement('span');
                        childSpan.textContent = child.label;
                        
                        childLabel.appendChild(childCheckbox);
                        childLabel.appendChild(childSpan);
                        childrenDiv.appendChild(childLabel);
                    });
                    
                    groupDiv.appendChild(childrenDiv);
                    fieldsTree.appendChild(groupDiv);
                    
                    // Toggle expand/collapse
                    expandButton.addEventListener('click', function() {
                        var isExpanded = childrenDiv.style.display !== 'none';
                        childrenDiv.style.display = isExpanded ? 'none' : 'block';
                        expandButton.textContent = isExpanded ? '▶' : '▼';
                    });
                    
                    // Group checkbox controls children
                    groupCheckbox.addEventListener('change', function() {
                        var children = childrenDiv.querySelectorAll('.scrywp-searchable-field-checkbox');
                        children.forEach(function(child) {
                            child.checked = groupCheckbox.checked;
                        });
                    });
                } else {
                    // Render single field
                    var label = document.createElement('label');
                    label.className = 'scrywp-searchable-field-item';
                    
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'scrywp-searchable-field-checkbox';
                    checkbox.dataset.fieldPath = field.path;
                    checkbox.checked = isChecked;
                    
                    var span = document.createElement('span');
                    span.textContent = field.label;
                    
                    label.appendChild(checkbox);
                    label.appendChild(span);
                    fieldsTree.appendChild(label);
                }
            });
        }
        
        // Save settings
        if (saveButton) {
            saveButton.addEventListener('click', function() {
                var button = this;
                var originalText = button.textContent;
                
                // Hide any previous error
                if (saveErrorDiv) {
                    saveErrorDiv.style.display = 'none';
                }
                
                button.disabled = true;
                button.textContent = '<?php echo esc_js(__('Saving...', "scry-search")); ?>';
                
                // Collect searchable attributes from checkboxes
                var searchableAttributes = [];
                var checkboxes = dialog.querySelectorAll('.scrywp-searchable-field-checkbox:checked');
                checkboxes.forEach(function(checkbox) {
                    searchableAttributes.push(checkbox.dataset.fieldPath);
                });
                
                var formData = new FormData();
                formData.append('action', '<?php echo esc_js($this->prefixed('update_index_settings')); ?>');
                formData.append('nonce', '<?php echo esc_js(wp_create_nonce($this->prefixed('update_index_settings'))); ?>');
                formData.append('index_name', indexName);
                
                // Append ranking rules as multi-value form inputs
                currentRankingRules.forEach(function(rule) {
                    formData.append('ranking_rules[]', rule);
                });
                
                // Append searchable attributes as multi-value form inputs
                searchableAttributes.forEach(function(attribute) {
                    formData.append('searchable_attributes[]', attribute);
                });
                
                // Log FormData contents
                var logData = {
                    action: '<?php echo esc_js($this->prefixed('update_index_settings')); ?>',
                    nonce: '<?php echo esc_js(wp_create_nonce($this->prefixed('update_index_settings'))); ?>',
                    index_name: indexName,
                    ranking_rules: currentRankingRules,
                    searchable_attributes: searchableAttributes
                };
                console.log('Sending FormData:', logData);
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(data) {
                    console.log('Response data:', data);
                    if (data.success) {
                        // Hide any previous error
                        if (saveErrorDiv) {
                            saveErrorDiv.style.display = 'none';
                        }
                        alert(data.data.message || '<?php echo esc_js(__('Settings saved successfully', "scry-search")); ?>');
                        // Reset button state before closing
                        resetSaveButton();
                        dialog.close();
                    } else {
                        console.log('Error response received');
                        var errorMessage = data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Failed to save settings', "scry-search")); ?>';
                        console.log('Error message:', errorMessage);
                        // Reset button state but don't hide error
                        if (saveButton) {
                            saveButton.disabled = false;
                            saveButton.textContent = originalSaveButtonText || '<?php echo esc_js(__('Save Settings', "scry-search")); ?>';
                        }
                        showSaveError('<?php echo esc_js(__('Error:', "scry-search")); ?> ' + errorMessage);
                    }
                })
                .catch(function(error) {
                    var errorMessage = '<?php echo esc_js(__('Error:', "scry-search")); ?> <?php echo esc_js(__('Failed to save settings', "scry-search")); ?>';
                    if (error && error.message) {
                        errorMessage += ' (' + error.message + ')';
                    }
                    // Reset button state but don't hide error
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.textContent = originalSaveButtonText || '<?php echo esc_js(__('Save Settings', "scry-search")); ?>';
                    }
                    showSaveError(errorMessage);
                });
            });
        }
    });
});
</script>
