<?php
/**
 * Base Layout for Scry Search for Meilisearch Settings Page
 * 
 * @package scry_ms_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', "scry-search"));
}

// Get current page
$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

// Get registered pages from the admin page feature
// $this refers to ScryWpAdminPageFeature instance in this context
$tabs = array();

if (method_exists($this, 'get_registered_pages')) {
    $registered_pages = $this->get_registered_pages();
    
    // Sort pages to ensure main page is first
    $main_page = isset($registered_pages['scry-search-meilisearch']) ? $registered_pages['scry-search-meilisearch'] : null;
    unset($registered_pages['scry-search-meilisearch']);
    
    // Build tabs array
    if ($main_page) {
        $tabs['scry-search-meilisearch'] = array(
            'label' => esc_html($main_page['label']),
            'icon' => esc_attr($main_page['icon']),
            'url' => esc_url($main_page['url']),
        );
    }
    
    // Add other pages
    foreach ($registered_pages as $page_slug => $page_data) {
        $tabs[$page_slug] = array(
            'label' => esc_html($page_data['label']),
            'icon' => esc_attr($page_data['icon']),
            'url' => esc_url($page_data['url']),
        );
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-search" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
        <?php esc_html_e('ScryWP Search', "scry-search"); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <?php foreach ($tabs as $tab_id => $tab) : ?>
            <?php 
            $is_current = $current_page === $tab_id;
            ?>
            <a href="<?php echo esc_attr($tab['url']); ?>" 
               class="nav-tab <?php echo $is_current ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr($tab['icon']); ?>" style="margin-right: 5px;"></span>
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Task View -->
    <?php require_once plugin_dir_path(__FILE__) . 'task_view.php'; ?>
    
</div>

<div>
    <?php echo $content; //already escaped ?>
</div>