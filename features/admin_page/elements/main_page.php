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
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', "scry-search"));
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
$all_taxonomies_objects = get_taxonomies( [], 'names' );
$scrywp_hosting_url = 'https://scrywp.com';
$scrywp_logo_url    = $this->get_base_url() . 'assets/images/coai_dark.png';
?>

<div class="scrywp-admin-overview">
    <div class="scrywp-admin-overview-header">
        <h2><?php esc_html_e('Welcome to ScryWP Search', "scry-search"); ?></h2>
        <p class="description">
            <?php esc_html_e('Configure your search settings, manage connections, and set up indexes for powerful semantic search capabilities.', "scry-search"); ?>
        </p>
    </div>

    <a class="scrywp-admin-overview-cta" href="<?php echo esc_url( $scrywp_hosting_url ); ?>" target="_blank" rel="noopener noreferrer">
        <div class="scrywp-admin-overview-cta-inner">
            <img class="scrywp-admin-overview-cta-logo" src="<?php echo esc_url( $scrywp_logo_url ); ?>" width="112" height="112" alt="<?php esc_attr_e( 'ScryWP', "scry-search" ); ?>" />
            <div class="scrywp-admin-overview-cta-copy">
                <span class="scrywp-admin-overview-cta-eyebrow"><?php esc_html_e( 'Managed Meilisearch', "scry-search" ); ?></span>
                <span class="scrywp-admin-overview-cta-title"><?php esc_html_e( 'Ship search that feels instant—not another server to babysit', "scry-search" ); ?></span>
                <span class="scrywp-admin-overview-cta-desc"><?php esc_html_e( 'Production-ready hosting, monitoring, and management. Connect in minutes and get back to building.', "scry-search" ); ?></span>
            </div>
            <span class="button button-primary scrywp-admin-overview-cta-button">
                <?php esc_html_e( 'Start now at scrywp.com', "scry-search" ); ?>
                <span class="dashicons dashicons-external" aria-hidden="true"></span>
            </span>
        </div>
    </a>

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
