<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

$option_name = $this->prefixed('highlighting_css');
$value = get_option($option_name, '');
$value = is_string($value) ? $value : '';
?>

<textarea
    id="<?php $this->pre('highlighting_css'); ?>"
    name="<?php $this->pre('highlighting_css'); ?>"
    class="large-text code"
    rows="10"
    placeholder=".scry-ms-highlight {
    background-color: #ffeb3b;
    color: #1d2327;
    font-weight: 600;
}"
><?php echo esc_textarea($value); ?></textarea>

<p class="description">
    <?php esc_html_e('Enter frontend CSS for the .scry-ms-highlight class. Leave blank to use the default style.', "scry-search"); ?>
</p>
