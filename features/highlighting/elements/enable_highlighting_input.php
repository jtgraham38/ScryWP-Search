<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

$option_name = $this->prefixed('enable_highlighting');
$value = get_option($option_name, '0');
?>

<input
    type="hidden"
    name="<?php $this->pre('enable_highlighting'); ?>"
    value="0"
/>

<label for="<?php $this->pre('enable_highlighting'); ?>">
    <input
        type="checkbox"
        id="<?php $this->pre('enable_highlighting'); ?>"
        name="<?php $this->pre('enable_highlighting'); ?>"
        value="1"
        <?php checked($value, '1'); ?>
    />
    <?php esc_html_e('Highlight matched terms in search results and autosuggest.', "scry-search"); ?>
</label>
