<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$connection_type = get_option($this->prefixed('connection_type'), '');
$url = get_option($this->prefixed('meilisearch_url'), '');
$readonly = ($connection_type === 'scrywp') ? 'readonly' : '';
$required = ($connection_type === 'manual') ? 'required' : '';
?>

<div class="scrywp-manual-config-field">
    <input type="url" name="<?php echo $this->prefixed('meilisearch_url'); ?>" value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="https://your-meilisearch-instance.com" <?php echo $readonly . ' ' . $required; ?>>
    <p class="description"><?php _e('The URL of your Meilisearch instance.', "meilisearch_wp"); ?></p>
</div>

