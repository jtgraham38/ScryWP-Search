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
        settings_fields($this->prefixed('search_settings_group'));
        ?>
        
        <div class="scrywp-connection-type-section">
            <h3><?php _e('Connection Type', "scry_search_meilisearch"); ?></h3>
            <div class="scrywp-connection-type-cards">
                
                <!-- ScryWP Managed Service - Prominent card -->
                <label class="scrywp-connection-card scrywp-connection-card-prominent">
                    <input type="radio" name="<?php echo $this->prefixed('connection_type'); ?>" value="scrywp" <?php checked($connection_type, 'scrywp'); ?>>
                    <div class="scrywp-card-content">
                        <div class="scrywp-card-title">ScryWP Managed Service</div>
                        <img src="<?php echo $coai_dark_url; ?>" alt="ScryWP Managed Service" class="scrywp-connection-card-image">
                        <div class="scrywp-card-description">Recommended: Let ScryWP manage your Meilisearch instance</div>
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
                <?php _e('Get your connection info from ScryWP.', "scry_search_meilisearch"); ?>
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
    </form>
</div>

<style>
.scrywp-connection-settings {
    max-width: 800px;
}

.scrywp-managed-get-connection-info,
.scrywp-connection-test-section,
.scrywp-save-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    transition: opacity 0.3s ease, max-height 0.3s ease, margin 0.3s ease, padding 0.3s ease;
    overflow: hidden;
}

.scrywp-managed-get-connection-info {
    max-height: 0;
    opacity: 0;
    margin: 0;
    padding-top: 0;
    padding-bottom: 0;
    border-width: 0;
}

.scrywp-managed-get-connection-info.scrywp-section-visible {
    max-height: 500px;
    opacity: 1;
    margin-bottom: 20px;
    padding: 20px;
    border-width: 1px;
}

.scrywp-managed-get-connection-info h3,
.scrywp-connection-test-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #23282d;
}

.scrywp-connection-info {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
    display: none;
}

.scrywp-connection-info.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.scrywp-connection-info.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.scrywp-test-result {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
    display: none;
}

.scrywp-test-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.scrywp-test-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.scrywp-save-section {
    text-align: left;
}

.scrywp-save-section .button-primary {
    margin-right: 10px;
}

.scrywp-connection-type-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.scrywp-connection-type-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #23282d;
}

.scrywp-connection-type-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.scrywp-connection-card {
    display: block;
    border: 2px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    background: #fff;
}

.scrywp-connection-card:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.scrywp-connection-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.scrywp-connection-card-image {
    width: 64px;
    height: 64px;
    object-fit: contain;
    display: block;
    margin: 20px auto;
    transition: all 0.3s ease;
}

.scrywp-connection-card:hover .scrywp-connection-card-image {
    transform: scale(1.05);
}

/* Images become muted when card is not selected */
.scrywp-connection-card:not(:has(input[type="radio"]:checked)) .scrywp-connection-card-image {
    opacity: 0.4;
    filter: grayscale(60%);
}

/* Prominent card image when selected */
.scrywp-connection-card-prominent:has(input[type="radio"]:checked) .scrywp-connection-card-image {
    opacity: 1;
    filter: drop-shadow(0 2px 4px rgba(34, 113, 177, 0.2)) grayscale(0%);
}

/* Prominent card image when not selected */
.scrywp-connection-card-prominent:not(:has(input[type="radio"]:checked)) .scrywp-connection-card-image {
    opacity: 0.3;
    filter: grayscale(70%);
}

/* Muted card image when selected */
.scrywp-connection-card-muted:has(input[type="radio"]:checked) .scrywp-connection-card-image {
    opacity: 1;
    filter: grayscale(0%);
}

/* Muted card image when not selected - already muted but make it more muted */
.scrywp-connection-card-muted:not(:has(input[type="radio"]:checked)) .scrywp-connection-card-image {
    opacity: 0.25;
    filter: grayscale(80%);
}

/* Hover effect - restore some visibility on hover even when not selected */
.scrywp-connection-card:not(:has(input[type="radio"]:checked)):hover .scrywp-connection-card-image {
    opacity: 0.6;
    filter: grayscale(40%);
}

.scrywp-connection-card-prominent {
    border-color: #2271b1;
    background: linear-gradient(135deg, #f6f9fc 0%, #ffffff 100%);
    box-shadow: 0 4px 12px rgba(34, 113, 177, 0.15);
}

.scrywp-connection-card-prominent:hover {
    border-color: #135e96;
    box-shadow: 0 6px 16px rgba(34, 113, 177, 0.25);
}

.scrywp-connection-card-prominent input[type="radio"]:checked ~ .scrywp-card-content::before {
    content: '✓';
    position: absolute;
    top: -10px;
    right: -10px;
    background: #2271b1;
    color: #fff;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.scrywp-connection-card-prominent:has(input[type="radio"]:checked) {
    border-color: #2271b1;
    background: linear-gradient(135deg, #e6f2ff 0%, #f6f9fc 100%);
    box-shadow: 0 4px 16px rgba(34, 113, 177, 0.3);
}

.scrywp-connection-card-muted {
    border-color: #dcdcde;
    background: #f9f9f9;
    opacity: 0.8;
}

.scrywp-connection-card-muted:hover {
    border-color: #8c8f94;
    opacity: 1;
    background: #fff;
}

.scrywp-connection-card-muted input[type="radio"]:checked ~ .scrywp-card-content::before {
    content: '✓';
    position: absolute;
    top: -10px;
    right: -10px;
    background: #646970;
    color: #fff;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.scrywp-connection-card-muted:has(input[type="radio"]:checked) {
    border-color: #646970;
    background: #f0f0f1;
    opacity: 1;
}

.scrywp-card-content {
    position: relative;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.scrywp-card-title {
    font-size: 18px;
    font-weight: 600;
    color: #23282d;
    margin-bottom: 0;
}

.scrywp-connection-card-prominent .scrywp-card-title {
    color: #2271b1;
}

.scrywp-connection-card-muted .scrywp-card-title {
    color: #646970;
}

.scrywp-card-description {
    font-size: 14px;
    color: #646970;
    line-height: 1.5;
    margin-top: 0;
}

.scrywp-connection-card-prominent .scrywp-card-description {
    color: #50575e;
}

.scrywp-connection-card-muted .scrywp-card-description {
    color: #8c8f94;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Helper function to query selector
    function $(selector) {
        return document.querySelector(selector);
    }
    
    // Helper function to query all
    function $$(selector) {
        return document.querySelectorAll(selector);
    }
    
    // Show/hide sections based on connection type
    function toggleSectionsBasedOnConnectionType() {
        var connectionTypeInput = $('input[name="<?php echo $this->prefixed('connection_type'); ?>"]:checked');
        var connectionType = connectionTypeInput ? connectionTypeInput.value : '';
        
        // Toggle "Get Connection Info" section (show for scrywp) with smooth animation
        var getConnectionInfoSection = $('.scrywp-managed-get-connection-info');
        if (getConnectionInfoSection) {
            if (connectionType === 'scrywp') {
                // Show section with animation
                getConnectionInfoSection.classList.add('scrywp-section-visible');
            } else {
                // Hide section with animation
                getConnectionInfoSection.classList.remove('scrywp-section-visible');
            }
        }
        
        // Toggle manual config fields (enable for manual, readonly for scrywp)
        var manualConfigFields = $$('.scrywp-manual-config-field');
        manualConfigFields.forEach(function(field) {
            var inputs = field.querySelectorAll('input');
            inputs.forEach(function(input) {
                if (connectionType === 'manual') {
                    // Enable fields and make required for manual config
                    input.removeAttribute('readonly');
                    input.setAttribute('required', 'required');
                } else {
                    // Make fields readonly (uneditable but still submits) and remove required for scrywp managed service
                    input.setAttribute('readonly', 'readonly');
                    input.removeAttribute('required');
                }
            });
        });
    }
    
    // Initial check
    toggleSectionsBasedOnConnectionType();
    
    // Watch for changes to connection type
    var connectionTypeInputs = $$('input[name="<?php echo $this->prefixed('connection_type'); ?>"]');
    connectionTypeInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            toggleSectionsBasedOnConnectionType();
        });
    });
    
    // Test connection functionality
    var testButton = $('#scrywp-test-connection');
    if (testButton) {
        testButton.addEventListener('click', function() {
            var button = this;
            var result = $('#scrywp-connection-test-result');
            
            button.disabled = true;
            button.textContent = '<?php _e('Testing...', "scry_search_meilisearch"); ?>';
            if (result) {
                result.style.display = 'none';
            }
            
            // Get form data using WordPress Settings API field names
            var connectionTypeInput = $('input[name="<?php echo $this->prefixed('connection_type'); ?>"]:checked');
            var urlInput = $('input[name="<?php echo $this->prefixed('meilisearch_url'); ?>"]');
            var searchKeyInput = $('input[name="<?php echo $this->prefixed('meilisearch_search_key'); ?>"]');
            var adminKeyInput = $('input[name="<?php echo $this->prefixed('meilisearch_admin_key'); ?>"]');
            
            var formData = new FormData();
            formData.append('action', 'scry_ms_test_connection');
            formData.append('nonce', '<?php echo wp_create_nonce('scry_ms_test_connection'); ?>');
            formData.append('connection_type', connectionTypeInput ? connectionTypeInput.value : '');
            formData.append('meilisearch_url', urlInput ? urlInput.value : '');
            formData.append('meilisearch_search_key', searchKeyInput ? searchKeyInput.value : '');
            formData.append('meilisearch_admin_key', adminKeyInput ? adminKeyInput.value : '');
            
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
                if (result) {
                    result.classList.remove('success', 'error');
                    
                    if (data.success) {
                        result.classList.add('success');
                        result.innerHTML = '<strong><?php _e('Success!', "scry_search_meilisearch"); ?></strong> ' + (data.data && data.data.message ? data.data.message : '');
                    } else {
                        result.classList.add('error');
                        var errorMessage = (data.data && data.data.message) ? data.data.message : '<?php _e('Connection test failed', "scry_search_meilisearch"); ?>';
                        result.innerHTML = '<strong><?php _e('Error:', "scry_search_meilisearch"); ?></strong> ' + errorMessage;
                    }
                    result.style.display = 'block';
                }
            })
            .catch(function(error) {
                if (result) {
                    result.classList.remove('success', 'error');
                    result.classList.add('error');
                    result.innerHTML = '<strong><?php _e('Error:', "scry_search_meilisearch"); ?></strong> <?php _e('Failed to test connection', "scry_search_meilisearch"); ?>';
                    result.style.display = 'block';
                }
            })
            .finally(function() {
                button.disabled = false;
                button.textContent = '<?php _e('Test Connection', "scry_search_meilisearch"); ?>';
            });
        });
    }
    
    // Form validation
    var form = $('form.scrywp-connection-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            var connectionTypeInput = $('input[name="<?php echo $this->prefixed('connection_type'); ?>"]:checked');
            var connectionType = connectionTypeInput ? connectionTypeInput.value : '';
            
            if (!connectionType) {
                alert('<?php _e('Please select a connection type', "scry_search_meilisearch"); ?>');
                e.preventDefault();
                return false;
            }
            
            // Only validate manual config fields when manual is selected
            if (connectionType === 'manual') {
                var urlInput = $('input[name="<?php echo $this->prefixed('meilisearch_url'); ?>"]');
                var searchKeyInput = $('input[name="<?php echo $this->prefixed('meilisearch_search_key'); ?>"]');
                var adminKeyInput = $('input[name="<?php echo $this->prefixed('meilisearch_admin_key'); ?>"]');
                
                var url = urlInput ? urlInput.value : '';
                var searchKey = searchKeyInput ? searchKeyInput.value : '';
                var adminKey = adminKeyInput ? adminKeyInput.value : '';
                
                if (!url || !searchKey || !adminKey) {
                    alert('<?php _e('Please fill in all required fields', "scry_search_meilisearch"); ?>');
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
});
</script>