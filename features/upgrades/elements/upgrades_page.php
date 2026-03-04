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
    // array(
    //     'name' => 'ScryWP Premium Add-on',
    //     'slug' => 'scrywp-premium',
    //     'description' => 'Advanced premium upgrade features.',
    //     'purchase_url' => 'https://scrywp.com',
    //     'plugin_file' => 'scrywp-premium/scrywp-premium.php',
    // ),
);
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
            $plugin_file = isset($upgrade['plugin_file']) ? $upgrade['plugin_file'] : '';
            $is_installed = !empty($plugin_file) && is_plugin_active($plugin_file);
            ?>
            <div class="card" style="max-width: 900px;">
                <h3><?php echo esc_html($upgrade['name']); ?></h3>
                <p><?php echo esc_html($upgrade['description']); ?></p>

                <?php if ($is_installed) : ?>
                    <p>
                        <strong><?php esc_html_e('Installed:', "scry-search"); ?></strong>
                        <?php esc_html_e('This premium upgrade is active and ready to use.', "scry-search"); ?>
                    </p>
                <?php else : ?>
                    <p>
                        <a class="button button-primary"
                           href="<?php echo esc_url($upgrade['purchase_url']); ?>"
                           target="_blank"
                           rel="noopener noreferrer">
                            <?php esc_html_e('Get Premium Upgrade', "scry-search"); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
