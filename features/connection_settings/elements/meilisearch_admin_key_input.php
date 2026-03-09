<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
?>

<div class="scrywp-manual-config-field">
    <input type="password" name="<?php echo esc_attr($this->prefixed('meilisearch_admin_key')); ?>" value="<?php echo esc_attr($admin_key); ?>" class="regular-text" placeholder="<?php esc_attr_e('Your admin API key', "scry-search"); ?>">
    <p class="description"><?php esc_html_e('The API key with admin permissions for managing indexes and settings.', "scry-search"); ?></p>
</div>

