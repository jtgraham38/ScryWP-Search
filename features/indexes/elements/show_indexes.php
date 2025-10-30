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
        
        foreach ($index_names as $index_name) {
            try {
                $index = $client->index($index_name);
                $index_info = $index->fetchRawInfo();
                $stats = $index->stats();
                
                $indexes_data[] = array(
                    'name' => $index_name,
                    'uid' => isset($index_info['uid']) ? $index_info['uid'] : $index_name,
                    'primaryKey' => isset($index_info['primaryKey']) ? $index_info['primaryKey'] : null,
                    'createdAt' => isset($index_info['createdAt']) ? $index_info['createdAt'] : null,
                    'updatedAt' => isset($index_info['updatedAt']) ? $index_info['updatedAt'] : null_detail,
                    'numberOfDocuments' => isset($stats['numberOfDocuments']) ? $stats['numberOfDocuments'] : 0,
                    'isIndexing' => isset($stats['isIndexing']) ? $stats['isIndexing'] : false,
                );
            } catch (ApiException $e) {
                // Index doesn't exist, add with error state
                $indexes_data[] = array(
                    'name' => $index_name,
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
<h1>TODO: add a wipe button to delete an index from teh search to each card</h1>
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
                        <h3 class="scrywp-index-card-title"><?php echo esc_html($index['name']); ?></h3>
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

.scrywp-index-card-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #23282d;
    word-break: break-word;
    flex: 1;
    margin-right: 10px;
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
    background: #f8d7da hocolor: #721c24;
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
</style>
