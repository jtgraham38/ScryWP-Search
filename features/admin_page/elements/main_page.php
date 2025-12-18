<?php
/**
 * Main Settings Overview Page
 * 
 * @package ScryWP_Search
 * @since 1.0.0
 */

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', "scry-search"));
}

// Get registered pages from the admin page feature
// $this refers to ScryWpAdminPageFeature instance in this context
$registered_pages = array();
if (method_exists($this, 'get_registered_pages')) {
    $registered_pages = $this->get_registered_pages();
}

// Separate main page from other pages
$main_page = isset($registered_pages['scrywp-search']) ? $registered_pages['scrywp-search'] : null;
unset($registered_pages['scrywp-search']);
?>

<div class="scrywp-admin-overview">
    <div class="scrywp-admin-overview-header">
        <h2><?php esc_html_e('Welcome to ScryWP Search', "scry-search"); ?></h2>
        <p class="description">
            <?php esc_html_e('Configure your search settings, manage connections, and set up indexes for powerful semantic search capabilities.', "scry-search"); ?>
        </p>
    </div>

    <?php if (!empty($registered_pages)) : ?>
        <div class="scrywp-admin-pages-grid">
            <?php foreach ($registered_pages as $page_slug => $page_data) : ?>
                <div class="scrywp-admin-page-card">
                    <div class="scrywp-admin-page-card-icon">
                        <span class="dashicons <?php echo esc_attr($page_data['icon']); ?>"></span>
                    </div>
                    <div class="scrywp-admin-page-card-content">
                        <h3 class="scrywp-admin-page-card-title">
                            <a href="<?php echo esc_url($page_data['url']); ?>">
                                <?php echo esc_html($page_data['label']); ?>
                            </a>
                        </h3>
                        <?php if (!empty($page_data['description'])) : ?>
                            <p class="scrywp-admin-page-card-description">
                                <?php echo esc_html($page_data['description']); ?>
                            </p>
                        <?php endif; ?>
                        <div class="scrywp-admin-page-card-action">
                            <a href="<?php echo esc_url($page_data['url']); ?>" class="button button-primary">
                                <?php 
                                /* translators: %s: Page title */
                                printf(esc_html__('Configure %s', "scry-search"), esc_html($page_data['label'])); 
                                ?>
                                <span class="dashicons dashicons-arrow-right-alt" style="margin-left: 5px;"></span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('No additional settings pages are available.', "scry-search"); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
.scrywp-admin-overview {
    max-width: 1200px;
    margin-top: 20px;
}

.scrywp-admin-overview-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.scrywp-admin-overview-header h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
    font-weight: 600;
}

.scrywp-admin-overview-header .description {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.scrywp-admin-pages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.scrywp-admin-page-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
    display: flex;
    flex-direction: column;
}

.scrywp-admin-page-card:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.scrywp-admin-page-card-icon {
    margin-bottom: 15px;
}

.scrywp-admin-page-card-icon .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #2271b1;
}

.scrywp-admin-page-card-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.scrywp-admin-page-card-title {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
}

.scrywp-admin-page-card-title a {
    text-decoration: none;
    color: #1d2327;
}

.scrywp-admin-page-card-title a:hover {
    color: #2271b1;
}

.scrywp-admin-page-card-description {
    margin: 0 0 15px 0;
    color: #646970;
    font-size: 14px;
    line-height: 1.6;
    flex: 1;
}

.scrywp-admin-page-card-action {
    margin-top: auto;
}

.scrywp-admin-page-card-action .button {
    display: inline-flex;
    align-items: center;
}

@media (max-width: 782px) {
    .scrywp-admin-pages-grid {
        grid-template-columns: 1fr;
    }
}
</style>
