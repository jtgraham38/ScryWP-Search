<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$option_name = $this->prefixed('anonymize_analytics');
$value = get_option($option_name, '0');
?>
<label for="<?php $this->pre('anonymize_analytics'); ?>">
    <input
        type="checkbox"
        id="<?php $this->pre('anonymize_analytics'); ?>"
        name="<?php $this->pre('anonymize_analytics'); ?>"
        value="1"
        <?php checked($value, '1'); ?>
    />
    <?php esc_html_e('Anonymize analytics data', "scry-search"); ?>
</label>
<p class="description">
    <?php esc_html_e('When enabled, user IP addresses are hashed, and user IDs, user agents, and referrer URLs are not stored. This cannot retroactively anonymize previously collected data.', "scry-search"); ?>
</p>
