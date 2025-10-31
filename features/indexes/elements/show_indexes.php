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
});
</script>
