<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$connection_type = get_option($this->prefixed('connection_type'), '');
$search_key = get_option($this->prefixed('meilisearch_search_key'), '');
$readonly = ($connection_type === 'scrywp') ? 'readonly' : '';
$required = ($connection_type === 'manual') ? 'required' : '';
?>

<div class="scrywp-manual-config-field">
    <input type="password" name="<?php echo $this->prefixed('meilisearch_search_key'); ?>" value="<?php echo esc_attr($search_key); ?>" class="regular-text" placeholder="<?php esc_attr_e('Your search API key', "meilisearch_wp"); ?>" <?php echo $readonly . ' ' . $required; ?>>
    <p class="description"><?php _e('The API key with search permissions for your Meilisearch instance.', "meilisearch_wp"); ?></p>
</div>

