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
                    'error' => $e->getCode() === 404 ? __('Index does not exist', 'scry-wp') : $e->getMessage(),
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
    $error_message = __('Connection settings are not configured.', 'scry-wp');
}
?>
<div class="scrywp-indexes-display">
    <?php if (!empty($error_message)): ?>
        <div class="scrywp-indexes-error">
            <p><strong><?php _e('Error:', 'scry-wp'); ?></strong> <?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($indexes_data)): ?>
        <div class="scrywp-indexes-empty">
            <p><?php _e('No indexes configured. Please select post types to index in the settings.', 'scry-wp'); ?></p>
        </div>
    <?php else: ?>
        <div class="scrywp-indexes-grid">
            <?php foreach ($indexes_data as $index): ?>
                <div class="scrywp-index-card <?php echo isset($index['error']) ? 'scrywp-index-card-error' : ''; ?>">
                    <div class="scrywp-index-card-header">
                        <div class="scrywp-index-card-title-container">
                            <h3 class="scrywp-index-card-title"><?php echo esc_html($index['name']); ?></h3>
                            <code class="scrywp-index-card-subtitle"><?php echo esc_html($index['index_name']); ?></code>
                        </div>
                        <?php if (isset($index['error'])): ?>
                            <span class="scrywp-index-status scrywp-index-status-error"><?php _e('Error', 'scry-wp'); ?></span>
                        <?php elseif ($index['isIndexing']): ?>
                            <span class="scrywp-index-status scrywp-index-status-indexing"><?php _e('Indexing...', 'scry-wp'); ?></span>
                        <?php else: ?>
                            <span class="scrywp-index-status scrywp-index-status-ready"><?php _e('Ready', 'scry-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($index['error'])): ?>
                        <div class="scrywp-index-card-content">
                            <p class="scrywp-index-error-message"><?php echo esc_html($index['error']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="scrywp-index-card-content">
                            <div class="scrywp-index-stat">
                                <span class="scrywp-index-stat-label"><?php _e('Documents:', 'scry-wp'); ?></span>
                                <span class="scrywp-index-stat-value"><?php echo number_format($index['numberOfDocuments']); ?></span>
                            </div>
                            
                            <?php if (!empty($index['primaryKey'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php _e('Primary Key:', 'scry-wp'); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html($index['primaryKey']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($index['createdAt'])): ?>
                                <div class="scrywp-index-stat">
                                    <span class="scrywp-index-stat-label"><?php _e('Created:', 'scry-wp'); ?></span>
                                    <span class="scrywp-index-stat-value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($index['createdAt']))); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="scrywp-index-actions">
                                <button type="button" class="button button-primary scrywp-index-posts-button" data-post-type="<?php echo esc_attr($index['name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php _e('Index All Posts', 'scry-wp'); ?>
                                </button>
                                <button type="button" class="button button-secondary scrywp-wipe-index-button" data-index-name="<?php echo esc_attr($index['index_name']); ?>" data-index-display="<?php echo esc_attr($index['name']); ?>">
                                    <?php _e('Wipe Index', 'scry-wp'); ?>
                                </button>
                                <button 
                                    type="button" 
                                    class="button button-secondary scrywp-search-index-button" 
                                    data-index-name="<?php echo esc_attr($index['index_name']); ?>" 
                                    data-index-display="<?php echo esc_attr($index['name']); ?>"
                                    onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').showModal()"
                                >
                                    <?php _e('Search Index', 'scry-wp'); ?>
                                </button>

                                <dialog id="<?php echo esc_attr($index['index_name']); ?>_search_dialog" class="scrywp-index-dialog">
                                    <div class="scrywp-index-dialog-header">
                                        <h3><?php printf(__('Search Index: %s', 'scry-wp'), esc_html($index['name'])); ?></h3>
                                        <button type="button" class="scrywp-index-dialog-close-button" onclick="document.getElementById('<?php echo esc_attr($index['index_name']); ?>_search_dialog').close()" aria-label="<?php esc_attr_e('Close', 'scry-wp'); ?>">
                                            ×
                                        </button>
                                    </div>
                                
                                    <form class="scrywp-index-dialog-search-form" data-index-name="<?php echo esc_attr($index['index_name']); ?>">
                                        <div class="scrywp-index-dialog-search-input-wrapper">
                                            <input 
                                                type="text" 
                                                name="search_query" 
                                                class="scrywp-index-dialog-search-input" 
                                                placeholder="<?php esc_attr_e('Search the index...', 'scry-wp'); ?>"
                                                autocomplete="off"
                                            >
                                        </div>
                                    </form>
                                    <div class="scrywp-index-dialog-results">
                                        <div class="scrywp-index-dialog-results-message"><?php _e('Enter a search query above to search the index.', 'scry-wp'); ?></div>
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
            button.textContent = '<?php _e('Indexing...', 'scry-wp'); ?>';
            
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
                    alert(data.data.message || '<?php echo esc_js(__('Posts indexed successfully', 'scry-wp')); ?>');
                    // Reload page to refresh the index list
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    // Show error message
                    alert('<?php echo esc_js(__('Error:', 'scry-wp')); ?> ' + (data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Failed to index posts', 'scry-wp')); ?>'));
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(function(error) {
                // Show error message
                alert('<?php echo esc_js(__('Error:', 'scry-wp')); ?> <?php echo esc_js(__('Failed to index posts', 'scry-wp')); ?>');
                button.disabled = false;
                button.textContent = originalText;
            });
        });
    });
    
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
            button.textContent = '<?php _e('Wiping...', 'scry-wp'); ?>';
            
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
                    alert(data.data.message || '<?php echo esc_js(__('Index wiped successfully', 'scry-wp')); ?>');
                    
                    // Reload page to refresh the index list
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    // Show error message
                    alert('<?php echo esc_js(__('Error:', 'scry-wp')); ?> ' + (data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Failed to wipe index', 'scry-wp')); ?>'));
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(function(error) {
                // Show error message
                alert('<?php echo esc_js(__('Error:', 'scry-wp')); ?> <?php echo esc_js(__('Failed to wipe index', 'scry-wp')); ?>');
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
                resultsContainer.innerHTML = '<div class="scrywp-index-dialog-results-message"><?php _e('Enter a search query above to search the index.', 'scry-wp'); ?></div>';
                return;
            }
            
            // Show loading state
            resultsContainer.innerHTML = '<div class="scrywp-index-dialog-loading"><?php _e('Searching...', 'scry-wp'); ?></div>';
            
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
                        resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results"><?php echo esc_js(__('No results found.', 'scry-wp')); ?></div>';
                        return;
                    }
                    
                    // Build results HTML
                    var resultsHTML = '';
                    var viewPostLabel = '<?php echo esc_js(__('View Post', 'scry-wp')); ?>';
                    var editPostLabel = '<?php echo esc_js(__('Edit Post', 'scry-wp')); ?>';
                    var untitledLabel = '<?php echo esc_js(__('Untitled', 'scry-wp')); ?>';
                    
                    results.forEach(function(result) {
                        resultsHTML += '<div class="scrywp-index-dialog-result">';
                        resultsHTML += '<h4 class="scrywp-index-dialog-result-title">' + (result.title || untitledLabel) + '</h4>';
                        
                        if (result.excerpt) {
                            resultsHTML += '<p class="scrywp-index-dialog-result-excerpt">' + result.excerpt + '</p>';
                        }
                        
                        resultsHTML += '<div class="scrywp-index-dialog-result-meta">';
                        resultsHTML += '<span>' + result.ID + '</span>';
                        resultsHTML += '<span>' + result.post_type + '</span>';
                        resultsHTML += '<span>' + result.post_status + '</span>';
                        if (result.post_date) {
                            resultsHTML += '<span>' + result.post_date + '</span>';
                        }
                        resultsHTML += '</div>';
                        
                        resultsHTML += '<div class="scrywp-index-dialog-result-actions">';
                        if (result.permalink) {
                            resultsHTML += '<a href="' + result.permalink + '" target="_blank" class="scrywp-index-dialog-result-link" aria-label="' + viewPostLabel + '">' + viewPostLabel + '</a>';
                        }
                        if (result.edit_link) {
                            resultsHTML += '<a href="' + result.edit_link + '" target="_blank" class="scrywp-index-dialog-result-edit-link" aria-label="' + editPostLabel + '">' + editPostLabel + '</a>';
                        }
                        resultsHTML += '</div>';
                        
                        resultsHTML += '</div>';
                    });
                    
                    resultsContainer.innerHTML = resultsHTML;
                } else {
                    var errorMessage = data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Search failed', 'scry-wp')); ?>';
                    resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results"><?php echo esc_js(__('Error:', 'scry-wp')); ?> ' + errorMessage + '</div>';
                }
            })
            .catch(function(error) {
                resultsContainer.innerHTML = '<div class="scrywp-index-dialog-no-results"><?php echo esc_js(__('Error: Failed to search index', 'scry-wp')); ?></div>';
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
});
</script>
