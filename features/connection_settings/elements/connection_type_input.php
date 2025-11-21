<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current connection settings for testing
$connection_type = get_option($this->prefixed('connection_type'), '');

//get the urls of images for cards
$coai_dark_url = $this->get_base_url() . 'assets/images/coai_dark.png';
$manual_url = $this->get_base_url() . 'assets/images/manual.png';

?>

<div class="scrywp-connection-settings">
        
        <div class="scrywp-connection-type-section">
            <h3><?php _e('Connection Type', "scry_search_meilisearch"); ?></h3>
            <div class="scrywp-connection-type-cards">
                
                <!-- Scry Search Managed Service - Prominent card -->
                <label class="scrywp-connection-card scrywp-connection-card-prominent">
                    <input type="radio" name="<?php echo $this->prefixed('connection_type'); ?>" value="scrywp" <?php checked($connection_type, 'scrywp'); ?>>
                    <div class="scrywp-card-content">
                        <div class="scrywp-card-title">Scry Search Managed Service</div>
                        <img src="<?php echo $coai_dark_url; ?>" alt="Scry Search for Meilisearch Managed Service" class="scrywp-connection-card-image">
                        <div class="scrywp-card-description">Recommended: Let Scry Search for Meilisearch manage your Meilisearch instance</div>
                        <div class="scrywp-card-description">Coming soon...</div>
                    </div>
                </label>
                
                <!-- Manual Configuration - Muted card -->
                <label class="scrywp-connection-card scrywp-connection-card-muted">
                    <input type="radio" name="<?php echo $this->prefixed('connection_type'); ?>" value="manual" <?php checked($connection_type, 'manual'); ?>>
                    <div class="scrywp-card-content">
                        <div class="scrywp-card-title">Manual Configuration</div>
                        <!-- <img src="<?php echo $manual_url; ?>" alt="Manual Configuration" class="scrywp-connection-card-image"> -->
                        <div class="scrywp-card-description">Configure your own Meilisearch instance</div>
                    </div>
                </label>
                
            </div>
        </div>
        <div class="scrywp-managed-get-connection-info<?php echo ($connection_type === 'scrywp') ? ' scrywp-section-visible' : ''; ?>">
            <h3><?php _e('Get Connection Info', "scry_search_meilisearch"); ?></h3>
            <p class="description">
                <?php _e('Get your connection info from Scry Search for Meilisearch.', "scry_search_meilisearch"); ?>
            </p>
            <button type="button" id="scrywp-get-connection-info" class="button button-secondary">
                <?php _e('Get Connection Info', "scry_search_meilisearch"); ?>
            </button>
            <div id="scrywp-connection-info" class="scrywp-connection-info"></div>
            <small>
                todo: implement this
            </small>
        </div>
        
        
        <div class="scrywp-connection-test-section">
            <h3><?php _e('Test Connection', "scry_search_meilisearch"); ?></h3>
            <p class="description">
                <?php _e('Test your connection settings before saving.', "scry_search_meilisearch"); ?>
            </p>
            <button type="button" id="scrywp-test-connection" class="button button-secondary">
                <?php _e('Test Connection', "scry_search_meilisearch"); ?>
            </button>
            <div id="scrywp-connection-test-result" class="scrywp-test-result"></div>
        </div>
</div>