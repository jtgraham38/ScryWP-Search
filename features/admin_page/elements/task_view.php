<?php
/**
 * Task Drawer Component
 * 
 * Displays a pullout drawer from the right with paginated Meilisearch tasks
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
    return;
}

// Get the admin page feature instance for prefixing
// $this refers to ScryWpAdminPageFeature instance in this context (from base_layout.php)
$admin_page_feature = $this;
?>

<!-- Task Drawer Toggle Button -->
<div class="scrywp-task-drawer-toggle-wrapper">
    <button type="button" class="button scrywp-task-drawer-toggle" id="scrywp-task-drawer-toggle">
        <span class="dashicons dashicons-arrow-left-alt"></span>
        <?php esc_html_e('View Tasks', "scry_search_meilisearch"); ?>
    </button>
</div>

<!-- Task Drawer Overlay -->
<div class="scrywp-task-drawer-overlay" id="scrywp-task-drawer-overlay"></div>

<!-- Task Drawer -->
<div class="scrywp-task-drawer" id="scrywp-task-drawer">
    <!-- Drawer Header -->
    <div class="scrywp-task-drawer-header">
        <h2 class="scrywp-task-drawer-title">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e('Meilisearch Tasks', "scry_search_meilisearch"); ?>
        </h2>
        <button type="button" class="scrywp-task-drawer-close" id="scrywp-task-drawer-close" aria-label="<?php esc_attr_e('Close drawer', "scry_search_meilisearch"); ?>">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>

    <!-- Drawer Content -->
    <div class="scrywp-task-drawer-content">
        <!-- Loading State -->
        <div class="scrywp-task-drawer-loading" id="scrywp-task-drawer-loading">
            <span class="spinner is-active" style="float: none; margin: 20px auto;"></span>
            <p><?php esc_html_e('Loading tasks...', "scry_search_meilisearch"); ?></p>
        </div>

        <!-- Empty State -->
        <div class="scrywp-task-drawer-empty" id="scrywp-task-drawer-empty" style="display: none;">
            <p><?php esc_html_e('No tasks found.', "scry_search_meilisearch"); ?></p>
        </div>

        <!-- Error State -->
        <div class="scrywp-task-drawer-error" id="scrywp-task-drawer-error" style="display: none;">
            <p><?php esc_html_e('Error loading tasks.', "scry_search_meilisearch"); ?></p>
            <p class="scrywp-task-drawer-error-message" id="scrywp-task-drawer-error-message"></p>
        </div>

        <!-- Pagination -->
        <div class="scrywp-task-drawer-pagination" id="scrywp-task-drawer-pagination" style="display: none;">
            <button type="button" class="button scrywp-task-drawer-prev" id="scrywp-task-drawer-prev" disabled>
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e('Previous', "scry_search_meilisearch"); ?>
            </button>
            <div class="scrywp-task-drawer-pagination-center">
                <div class="scrywp-task-drawer-page-input-wrapper">
                    <label for="scrywp-task-drawer-page-input" class="scrywp-task-drawer-page-label">
                        <?php esc_html_e('Page:', "scry_search_meilisearch"); ?>
                    </label>
                    <input type="number" 
                           id="scrywp-task-drawer-page-input" 
                           class="scrywp-task-drawer-page-input" 
                           min="1" 
                           value="1"
                           aria-label="<?php esc_attr_e('Page number', "scry_search_meilisearch"); ?>">
                    <span class="scrywp-task-drawer-total-pages" id="scrywp-task-drawer-total-pages"></span>
                </div>
                <span class="scrywp-task-drawer-pagination-info" id="scrywp-task-drawer-pagination-info"></span>
            </div>
            <button type="button" class="button scrywp-task-drawer-next" id="scrywp-task-drawer-next">
                <?php esc_html_e('Next', "scry_search_meilisearch"); ?>
                <span class="dashicons dashicons-arrow-right-alt"></span>
            </button>
        </div>

        <!-- Tasks List -->
        <div class="scrywp-task-drawer-list" id="scrywp-task-drawer-list"></div>

    </div>
</div>
<?php
// Localize script with AJAX URL and nonce
wp_localize_script(
    $admin_page_feature->prefixed('admin-script'),
    'scrywpTasks',
    array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => $admin_page_feature->prefixed('get_tasks'),
        'nonce' => wp_create_nonce($admin_page_feature->prefixed('get_tasks')),
        'i18n' => array(
            'loading' => __('Loading tasks...', "scry_search_meilisearch"),
            'noTasks' => __('No tasks found.', "scry_search_meilisearch"),
            'tasksInfo' => __('Showing %1$d-%2$d of %3$d tasks', "scry_search_meilisearch"),
        ),
    )
);
?>

