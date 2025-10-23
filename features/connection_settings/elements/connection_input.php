<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current connection settings for testing
$connection_settings = get_option($this->prefixed('meilisearch_connection_settings'), array(
    'connection_type' => '',
    'meilisearch_search_key' => '',
    'meilisearch_admin_key' => '',
    'meilisearch_url' => '',
));
?>

<div class="scrywp-connection-settings">
    <div class="scrywp-settings-header">
        <h2><?php _e('Meilisearch Connection Settings', 'scry-wp'); ?></h2>
        <p class="description">
            <?php _e('Configure your Meilisearch instance connection settings. Choose between manual configuration or ScryWP managed service.', 'scry-wp'); ?>
        </p>
    </div>

    <form method="post" action="options.php" class="scrywp-connection-form">
        <?php
        settings_fields($this->prefixed('search_settings_group'));
        do_settings_sections($this->prefixed('search_settings_group'));
        ?>

        <div class="scrywp-connection-test-section">
            <h3><?php _e('Test Connection', 'scry-wp'); ?></h3>
            <p class="description">
                <?php _e('Test your connection settings before saving.', 'scry-wp'); ?>
            </p>
            <button type="button" id="scrywp-test-connection" class="button button-secondary">
                <?php _e('Test Connection', 'scry-wp'); ?>
            </button>
            <div id="scrywp-connection-test-result" class="scrywp-test-result"></div>
        </div>

        <div class="scrywp-save-section">
            <?php submit_button(__('Save Connection Settings', 'scry-wp'), 'primary', 'submit', false); ?>
            <span id="scrywp-save-status"></span>
        </div>
    </form>
</div>

<style>
.scrywp-connection-settings {
    max-width: 800px;
}

.scrywp-settings-header {
    margin-bottom: 30px;
}

.scrywp-connection-test-section,
.scrywp-save-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.scrywp-connection-test-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #23282d;
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
</style>

<script>
jQuery(document).ready(function($) {
    // Test connection functionality
    $('#scrywp-test-connection').on('click', function() {
        var $button = $(this);
        var $result = $('#scrywp-connection-test-result');
        
        $button.prop('disabled', true).text('<?php _e('Testing...', 'scry-wp'); ?>');
        $result.hide();
        
        // Get form data using WordPress Settings API field names
        var formData = {
            action: 'scrywp_test_connection',
            nonce: '<?php echo wp_create_nonce('scrywp_test_connection'); ?>',
            connection_type: $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[connection_type]'); ?>"]:checked').val(),
            meilisearch_url: $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[meilisearch_url]'); ?>"]').val(),
            meilisearch_search_key: $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[meilisearch_search_key]'); ?>"]').val(),
            meilisearch_admin_key: $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[meilisearch_admin_key]'); ?>"]').val()
        };
        
        $.post(ajaxurl, formData, function(response) {
            $result.removeClass('success error');
            
            if (response.success) {
                $result.addClass('success').html('<strong><?php _e('Success!', 'scry-wp'); ?></strong> ' + response.data.message).show();
            } else {
                $result.addClass('error').html('<strong><?php _e('Error:', 'scry-wp'); ?></strong> ' + (response.data.message || '<?php _e('Connection test failed', 'scry-wp'); ?>')).show();
            }
        }).fail(function() {
            $result.removeClass('success error').addClass('error')
                .html('<strong><?php _e('Error:', 'scry-wp'); ?></strong> <?php _e('Failed to test connection', 'scry-wp'); ?>').show();
        }).always(function() {
            $button.prop('disabled', false).text('<?php _e('Test Connection', 'scry-wp'); ?>');
        });
    });
    
    // Form validation
    $('form.scrywp-connection-form').on('submit', function(e) {
        var connectionType = $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[connection_type]'); ?>"]:checked').val();
        var url = $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[meilisearch_url]'); ?>"]').val();
        var searchKey = $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[meilisearch_search_key]'); ?>"]').val();
        var adminKey = $('input[name="<?php echo $this->prefixed('meilisearch_connection_settings[meilisearch_admin_key]'); ?>"]').val();
        
        if (!connectionType) {
            alert('<?php _e('Please select a connection type', 'scry-wp'); ?>');
            e.preventDefault();
            return false;
        }
        
        if (!url || !searchKey || !adminKey) {
            alert('<?php _e('Please fill in all required fields', 'scry-wp'); ?>');
            e.preventDefault();
            return false;
        }
    });
});
</script>