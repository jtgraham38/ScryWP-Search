<?php
/**
 * Upgrades page content.
 *
 * @package scry_ms_Search
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html(__('You do not have sufficient permissions to access this page.', "scry-search")));
}

if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Placeholder for future premium upgrade metadata.
$premium_upgrades = array(
    array(
        'name' => 'Scry Search Filters: Filtered Search for WordPress & WooCommerce',
        'slug' => 'scry-search-filters',
        'description' => 'Add support for filtered, facetted search to your WordPress & WooCommerce site.',
        'purchase_url' => 'https://scrywp.com/premium-plugins/scry-search-filters/',
        'active' => false,
    ),
    array(
        'name' => 'Scry Search Hybrid: AI-Powered Search for WordPress & WooCommerce',
        'slug' => 'scry-search-hybrid',
        'description' => 'Add semantic and hybrid search to your WordPress & WooCommerce site with Meilisearch embedders.',
        'purchase_url' => 'https://scrywp.com/premium-plugins/scry-search-hybrid/',
        'active' => false,
    ),
);

// Premium plugins set `active` to true via this filter when installed on the site.
// @HOOK: scry_ms_premium_upgrades_display — args: $premium_upgrades
$premium_upgrades = apply_filters($this->prefixed('premium_upgrades_display'), $premium_upgrades);

?>

<div class="wrap">
    <h2><?php esc_html_e('Premium Upgrades', "scry-search"); ?></h2>
    <p><?php esc_html_e('Unlock additional capabilities for ScryWP Search with premium upgrades.', "scry-search"); ?></p>

    <?php if (empty($premium_upgrades)) : ?>
        <div class="notice notice-info inline">
            <p>
                <strong><?php esc_html_e('Coming Soon:', "scry-search"); ?></strong>
                <?php esc_html_e('Premium upgrades are not available yet. Check back soon for new add-ons.', "scry-search"); ?>
            </p>
        </div>
    <?php else : ?>
        <?php foreach ($premium_upgrades as $upgrade) : ?>
            <?php
            $is_active = !empty($upgrade['active']);
            $purchase_url = isset($upgrade['purchase_url']) ? $upgrade['purchase_url'] : '';
            $upgrade_slug = isset($upgrade['slug']) ? sanitize_key($upgrade['slug']) : '';
            $settings_dialog_id = $upgrade_slug !== '' ? 'scrywp-upgrade-settings-' . $upgrade_slug : '';
            ?>
            <div class="card scrywp-upgrade-card" style="max-width: 900px;">
                <h3><?php echo esc_html($upgrade['name']); ?></h3>
                <p><?php echo esc_html($upgrade['description']); ?></p>

                <p class="scrywp-upgrade-card-actions">
                    <?php if ($is_active) : ?>
                        <span class="button button-primary disabled" aria-disabled="true">
                            <?php esc_html_e('Already Active', "scry-search"); ?>
                        </span>
                    <?php elseif ($purchase_url !== '') : ?>
                        <a class="button button-primary"
                        href="<?php echo esc_url($purchase_url); ?>"
                        target="_blank"
                        rel="noopener noreferrer">
                            <?php esc_html_e('Get Premium Upgrade', "scry-search"); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($is_active && $settings_dialog_id !== '') : ?>
                        <button
                            type="button"
                            class="button scrywp-upgrade-settings-button"
                            onclick="document.getElementById('<?php echo esc_attr($settings_dialog_id); ?>').showModal()"
                            aria-label="<?php echo esc_attr(sprintf(__('Settings for %s', "scry-search"), $upgrade['name'])); ?>"
                        >
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                </p>

                <?php if ($is_active && $settings_dialog_id !== '') : ?>
                    <dialog id="<?php echo esc_attr($settings_dialog_id); ?>" class="scrywp-upgrade-settings-dialog">
                        <div class="scrywp-upgrade-settings-dialog-header">
                            <h3><?php echo esc_html(sprintf(__('Settings: %s', "scry-search"), $upgrade['name'])); ?></h3>
                            <button
                                type="button"
                                class="scrywp-upgrade-settings-dialog-close-button"
                                onclick="document.getElementById('<?php echo esc_attr($settings_dialog_id); ?>').close()"
                                aria-label="<?php esc_attr_e('Close', "scry-search"); ?>"
                            >
                                ×
                            </button>
                        </div>
                        <div class="scrywp-upgrade-settings-dialog-content">
                            <?php
                            // @HOOK: scry_ms_premium_upgrade_settings_ui
                            //buffer the plugin output for the settings
                            ob_start();
                            do_action($this->config('hook_prefix') . 'premium_upgrade_settings_ui', $upgrade);
                            $upgrade_settings_ui = ob_get_clean();

                            //if no content was returned, show a default message
                            if (empty($upgrade_settings_ui)) {
                                $upgrade_settings_ui = '<p>' . esc_html__('No settings are available for this upgrade.', "scry-search") . '</p>';
                            }

                            //output the content
                            echo $upgrade_settings_ui;
                            ?>
                        </div>
                    </dialog>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
