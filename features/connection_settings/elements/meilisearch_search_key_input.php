<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$search_key = get_option($this->prefixed('meilisearch_search_key'), '');
?>

<div class="scrywp-manual-config-field">
    <input type="password" name="<?php echo esc_attr($this->prefixed('meilisearch_search_key')); ?>" value="<?php echo esc_attr($search_key); ?>" class="regular-text" placeholder="<?php esc_attr_e('Your search API key', "scry-search"); ?>">
    <p class="description"><?php esc_html_e('The API key with search permissions for your Meilisearch instance.', "scry-search"); ?></p>
</div>

