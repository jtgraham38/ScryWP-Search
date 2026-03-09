<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current connection settings and default to managed service.
$connection_type = get_option($this->prefixed('connection_type'), 'scrywp');
if (!in_array($connection_type, array('manual', 'scrywp'), true)) {
    $connection_type = 'scrywp';
}

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
            <h3><?php esc_html_e('Connection Type', "scry-search"); ?></h3>
            <div class="scrywp-connection-type-cards">
                
                <!-- Scry Search Managed Service - Prominent card (coming soon) -->
                <label class="scrywp-connection-card scrywp-connection-card-prominent">
                    <input type="radio" name="<?php echo esc_attr($this->prefixed('connection_type')); ?>" value="scrywp" <?php checked($connection_type, 'scrywp'); ?>>
                    <div class="scrywp-card-content">
                        <div class="scrywp-card-title">ScryWP Managed Service</div>
                        <img src="<?php echo esc_url($coai_dark_url); ?>" alt="ScryWP Managed Service" class="scrywp-connection-card-image">
                        <div class="scrywp-card-description">ScryWP will manage your Meilisearch instance for you.</div>
                        <p>
                            <a href="https://scrywp.com" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('Get started at scrywp.com', "scry-search"); ?>
                            </a>
                        </p>
                    </div>
                </label>
                
                <!-- Manual Configuration - Muted card -->
                <label class="scrywp-connection-card scrywp-connection-card-muted">
                    <input type="radio" name="<?php echo esc_attr($this->prefixed('connection_type')); ?>" value="manual" <?php checked($connection_type, 'manual'); ?>>
                    <div class="scrywp-card-content">
                        <div class="scrywp-card-title">Manual Configuration</div>
                        <!-- <img src="<?php echo esc_url($manual_url); ?>" alt="Manual Configuration" class="scrywp-connection-card-image"> -->
                        <div class="scrywp-card-description">Configure your own Meilisearch instance</div>
                    </div>
                </label>
                
            </div>
        </div>
        <div class="scrywp-managed-get-connection-info<?php echo ($connection_type === 'scrywp') ? ' scrywp-section-visible' : ''; ?>">
            <h3><?php esc_html_e('Connect to ScryWP Deployment', "scry-search"); ?></h3>
            <p class="description">
                <?php esc_html_e('Connect your site to a managed ScryWP deployment.', "scry-search"); ?>
            </p>
            <ol>
                <li>Log in to <a href="https://scrywp.com" target="_blank">scrywp.com</a></li>
                <li>Go to your <a href="https://scrywp.com/deployments" target="_blank">deployments</a> page</li>
                <li>Click the button to view the deployment you would like to use, or create a new one.</li>
                <li>Copy the deployment URL and paste it into the field below.</li>
                <li>Scroll down to the "Api Keys" section, copy the "Search API Key", and paste it into the field below.</li>
                <li>Scroll down to the "Api Keys" section, copy the "Admin API Key", and paste it into the field below.</li>
                <li>Click the "Save Changes" button to save your changes.</li>
            </ol>
        </div>
        
        
        <div class="scrywp-connection-test-section">
            <h3><?php esc_html_e('Test Connection', "scry-search"); ?></h3>
            <p class="description">
                <?php esc_html_e('Test your connection settings before saving.', "scry-search"); ?>
            </p>
            <button type="button" id="scrywp-test-connection" class="button button-secondary">
                <?php esc_html_e('Test Connection', "scry-search"); ?>
            </button>
            <div id="scrywp-connection-test-result" class="scrywp-test-result"></div>
        </div>
    </form>
</div>
