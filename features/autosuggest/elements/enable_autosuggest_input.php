<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$option_name = $this->prefixed('enable_autosuggest');
$value = get_option($option_name, '0');
?>
<label for="<?php $this->pre('enable_autosuggest'); ?>">
    <input type="checkbox" id="<?php $this->pre('enable_autosuggest'); ?>" name="<?php $this->pre('enable_autosuggest'); ?>" value="1" <?php checked($value, '1'); ?> />
    <?php esc_html_e('Enable autosuggest', "scry-search"); ?>
</label>