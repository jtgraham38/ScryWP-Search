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

    <form method="post" action="options.php" class="scrywp-connection-form">
        <?php
        settings_fields($this->prefixed('connection_settings_group'));
        ?>
        
        <div class="scrywp-connection-type-section">
            <h3><?php esc_html_e('Connection Type', "scry-ms-search"); ?></h3>
            <div class="scrywp-connection-type-cards">
                
                <!-- Scry Search Managed Service - Prominent card -->
                <label class="scrywp-connection-card scrywp-connection-card-prominent">
                    <input type="radio" name="<?php echo esc_attr($this->prefixed('connection_type')); ?>" value="scrywp" <?php checked($connection_type, 'scrywp'); ?>>
                    <div class="scrywp-card-content">
                        <div class="scrywp-card-title">ScryWP Managed Service</div>
                        <img src="<?php echo esc_url($coai_dark_url); ?>" alt="ScryWP Managed Service" class="scrywp-connection-card-image">
                        <div class="scrywp-card-description">Recommended: Let ScryWP manage your Meilisearch instance</div>
                    </div>
                </label>
                
                <!-- Manual Configuration - Muted card -->
                <label class="scrywp-connection-card scrywp-connection-card-muted">
                    <input type="radio" name="<?php echo esc_attr($this->prefixed('connection_type')); ?>" value="manual" <?php checked($connection_type, 'manual'); ?>>
                    <div class="scrywp-card-content">
                        <div class="scrywp-card-title">Manual Configuration</div>
                        <!-- <img src="<?php echo $manual_url; ?>" alt="Manual Configuration" class="scrywp-connection-card-image"> -->
                        <div class="scrywp-card-description">Configure your own Meilisearch instance</div>
                    </div>
                </label>
                
            </div>
        </div>
        <div class="scrywp-managed-get-connection-info<?php echo ($connection_type === 'scrywp') ? ' scrywp-section-visible' : ''; ?>">
            <h3><?php esc_html_e('Get Connection Info', "scry-ms-search"); ?></h3>
            <p class="description">
                <?php esc_html_e('Get your connection info from ScryWP.', "scry-ms-search"); ?>
            </p>
            <button type="button" id="scrywp-get-connection-info" class="button button-secondary">
                <?php esc_html_e('Get Connection Info', "scry-ms-search"); ?>
            </button>
            <div id="scrywp-connection-info" class="scrywp-connection-info"></div>
            <small>
                todo: implement this
            </small>
        </div>
        
        
        <div class="scrywp-connection-test-section">
            <h3><?php esc_html_e('Test Connection', "scry-ms-search"); ?></h3>
            <p class="description">
                <?php esc_html_e('Test your connection settings before saving.', "scry-ms-search"); ?>
            </p>
            <button type="button" id="scrywp-test-connection" class="button button-secondary">
                <?php esc_html_e('Test Connection', "scry-ms-search"); ?>
            </button>
            <div id="scrywp-connection-test-result" class="scrywp-test-result"></div>
        </div>
    </form>
</div>
