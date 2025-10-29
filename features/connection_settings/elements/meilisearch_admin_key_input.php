<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$connection_type = get_option($this->prefixed('connection_type'), '');
$admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
$readonly = ($connection_type === 'scrywp') ? 'readonly' : '';
$required = ($connection_type === 'manual') ? 'required' : '';
?>

<div class="scrywp-manual-config-field">
    <input type="password" name="<?php echo $this->prefixed('meilisearch_admin_key'); ?>" value="<?php echo esc_attr($admin_key); ?>" class="regular-text" placeholder="<?php esc_attr_e('Your admin API key', 'scry-wp'); ?>" <?php echo $readonly . ' ' . $required; ?>>
    <p class="description"><?php _e('The API key with admin permissions for managing indexes and settings.', 'scry-wp'); ?></p>
</div>

