<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$option_name = $this->prefixed('logs_retention_period');
$value = get_option($option_name, '0');
$value = is_string($value) ? $value : '0';
$value_int = absint($value);
?>
<label for="<?php $this->pre('logs_retention_period'); ?>">
    <input
        type="number"
        min="0"
        step="1"
        class="small-text"
        id="<?php $this->pre('logs_retention_period'); ?>"
        name="<?php $this->pre('logs_retention_period'); ?>"
        value="<?php echo esc_attr((string) $value_int); ?>"
    />
    <?php esc_html_e('Days', "scry-search"); ?>
</label>
<button type="button" class="button button-secondary scrywp-delete-old-logs" style="margin-left: 8px;">
    <?php esc_html_e('Delete old logs now', "scry-search"); ?>
</button>
<span class="scrywp-delete-old-logs-result" style="margin-left: 8px;"></span>
<p class="description">
    <?php esc_html_e('Set to 0 to keep logs indefinitely.', "scry-search"); ?>
</p>
