<?php
/**
 * Base Layout for ScryWP Search Settings Page
 * 
 * @package ScryWP_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current page
$current_page = $_GET['page'] ?? '';

// Define available tabs
$tabs = array(
    'scrywp-search' => array(
        'label' => __('Search', 'scry-wp'),
        'icon' => 'dashicons-search',
        'url' => admin_url('admin.php?page=scrywp-search')
    ),
    'scrywp-search-settings' => array(
        'label' => __('Connection Settings', 'scry-wp'),
        'icon' => 'dashicons-admin-generic',
        'url' => admin_url('admin.php?page=scrywp-search-settings')
    ),
    'scrywp-index-settings' => array(
        'label' => __('Index Settings', 'scry-wp'),
        'icon' => 'dashicons-index-card',
        'url' => admin_url('admin.php?page=scrywp-index-settings')
    ),
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-search" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
        <?php _e('ScryWP Search', 'scry-wp'); ?>
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
</div>

<div>
    <?php echo $content; //already escaped ?>
</div>