<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$url = get_option($this->prefixed('meilisearch_url'), '');
?>

<div class="scrywp-manual-config-field">
    <input type="url" name="<?php echo esc_attr($this->prefixed('meilisearch_url')); ?>" value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="https://your-meilisearch-instance.com">
    <p class="description"><?php esc_html_e('The URL of your Meilisearch instance.', "scry-search"); ?></p>
</div>

