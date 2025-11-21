<?php
/**
 * Main Settings Overview Page
 * 
 * @package scry_ms_Search
 * @since 1.0.0
 */

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', "scry_search_meilisearch"));
}

// Get registered pages from the admin page feature
// $this refers to ScryWpAdminPageFeature instance in this context
$registered_pages = array();
if (method_exists($this, 'get_registered_pages')) {
    $registered_pages = $this->get_registered_pages();
}

// Separate main page from other pages
$main_page = isset($registered_pages['scry-search-meilisearch']) ? $registered_pages['scry-search-meilisearch'] : null;
unset($registered_pages['scry-search-meilisearch']);
?>

<div class="scrywp-admin-overview">
    <div class="scrywp-admin-overview-header">
        <h2><?php esc_html_e('Welcome to Scry Search for Meilisearch', "scry_search_meilisearch"); ?></h2>
        <p class="description">
            <?php esc_html_e('Configure your search settings, manage connections, and set up indexes for powerful semantic search capabilities.', "scry_search_meilisearch"); ?>
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
                                printf(esc_html__('Configure %s', "scry_search_meilisearch"), esc_html($page_data['label'])); 
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
            <p><?php esc_html_e('No additional settings pages are available.', "scry_search_meilisearch"); ?></p>
        </div>
    <?php endif; ?>
</div>
