<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$option_name = $this->prefixed('class_selector');
$value = get_option($option_name, '');
?>
<input type="text" id="<?php $this->pre('class_selector'); ?>" name="<?php $this->pre('class_selector'); ?>" value="<?php echo esc_attr($value); ?>" placeholder="form.scrywp-search-form" />
<p class="description"><?php esc_html_e('The class selector to apply autosuggest to.  All search form tags with this class will have autosuggest enabled.  Leave this blank to apply autosuggest to all search forms.', "scry-search"); ?></p>