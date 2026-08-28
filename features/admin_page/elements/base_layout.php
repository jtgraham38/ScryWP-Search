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
    wp_die(esc_html(__('You do not have sufficient permissions to access this page.', "scry-search")));
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

<?php
// Setup breadcrumb: show until connection is configured and at least one site index exists in Meilisearch.
$meilisearch_url       = get_option($this->prefixed('meilisearch_url'), '');
$meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
$connection_configured = !empty($meilisearch_url) && !empty($meilisearch_admin_key);

//test if the site indexes exist in Meilisearch
$has_site_indexes = false;
$indexes_feature  = $this->get_feature('scry_ms_indexes');
$index_names      = method_exists($indexes_feature, 'get_index_names') ? $indexes_feature->get_index_names() : array();

//test if the site indexes exist in Meilisearch
if ($connection_configured && !empty($index_names)) {
	try {
		$client = $this->get_feature('scry_ms_client')->get_client();
		foreach ($index_names as $index_uid) {
			try {
				$client->index($index_uid)->fetchRawInfo();
				$has_site_indexes = true;
				break;
			} catch (\Exception $e) {
				// Index missing or unreachable — keep looking.
				continue;
			}
		}
	} catch (\Exception $e) {
		$has_site_indexes = false;
	}
}

//determine if the setup breadcrumb should be shown
$show_setup_breadcrumb = !$connection_configured || !$has_site_indexes;

//determine which step to highlight in the setup breadcrumb
if (!$connection_configured) {
	$setup_current_step = 1;
} elseif (!$has_site_indexes) {
	$setup_current_step = 2;
} else {
	$setup_current_step = 3;
}

//steps to set up the plugin
$setup_steps = array(
	1 => array(
		'label' => __('Configure connection', 'scry-search'),
		'url'   => admin_url('admin.php?page=scry-search-meilisearch-settings'),
	),
	2 => array(
		'label' => __('Index posts', 'scry-search'),
		'url'   => admin_url('admin.php?page=scry-search-meilisearch-index-settings'),
	),
	3 => array(
		'label' => __('Search!', 'scry-search'),
		'url'   => home_url('/'),
	),
);

// Determine if the semantic search notice should be shown.
$show_semantic_search_notice = false;
if (
	!$show_setup_breadcrumb
	&& $current_page !== 'scry-search-meilisearch-index-settings'
	&& !empty($index_names)
	&& $indexes_feature
	&& method_exists($indexes_feature, 'get_hybrid_settings')
) {
	$show_semantic_search_notice = true;
    $all_have_semantic_active = true;
	foreach ($index_names as $index_name) {
		$hybrid = $indexes_feature->get_hybrid_settings((string) $index_name);

        //if the hybrid settings are not empty
		if (!empty($hybrid['enabled'])) {
			$all_have_semantic_active = false;
			break;
		}
	}

    //if all indexes already have semantic active, don't show the notice
    if ($all_have_semantic_active) {
        $show_semantic_search_notice = false;
    }
}

if (
	$show_semantic_search_notice
	&& !empty($_COOKIE[$this->prefixed('dismiss_semantic_search_notice')])
) {
	$show_semantic_search_notice = false;
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

    <?php if ($show_semantic_search_notice) : ?>
        <div class="scry-ms-semantic-search-notice notice notice-info is-dismissible inline">
            <p>
                <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                <a href="<?php echo esc_url(admin_url('admin.php?page=scry-search-meilisearch-index-settings')); ?>"><?php esc_html_e('Get started with semantic search', 'scry-search'); ?></a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($show_setup_breadcrumb) : ?>
        <nav class="scry-ms-setup-breadcrumb" aria-label="<?php esc_attr_e('Setup steps', 'scry-search'); ?>">
            <p class="scry-ms-setup-breadcrumb-intro"><?php esc_html_e('Get started with Scry Search:', 'scry-search'); ?></p>
            <ol class="scry-ms-setup-breadcrumb-steps">
                <?php foreach ($setup_steps as $step_number => $step) :
                    $is_complete = $step_number < $setup_current_step;
                    $is_current  = $step_number === $setup_current_step;
                    $classes     = 'scry-ms-setup-breadcrumb-step';
                    if ($is_complete) {
                        $classes .= ' is-complete';
                    }
                    if ($is_current) {
                        $classes .= ' is-current';
                    }
                    ?>
                    <li class="<?php echo esc_attr($classes); ?>">
                        <?php if ($is_current || !$is_complete) : ?>
                            <a class="scry-ms-setup-breadcrumb-link" href="<?php echo esc_url($step['url']); ?>">
                                <span class="scry-ms-setup-breadcrumb-number"><?php echo esc_html((string) $step_number); ?></span>
                                <span class="scry-ms-setup-breadcrumb-label"><?php echo esc_html($step['label']); ?></span>
                            </a>
                        <?php else : ?>
                            <span class="scry-ms-setup-breadcrumb-link">
                                <span class="scry-ms-setup-breadcrumb-number"><?php echo esc_html((string) $step_number); ?></span>
                                <span class="scry-ms-setup-breadcrumb-label"><?php echo esc_html($step['label']); ?></span>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <!-- Task View -->
    <?php require_once plugin_dir_path(__FILE__) . 'task_view.php'; ?>
    
</div>

<div>
    <?php 
        require_once $file_path;
    ?>
</div>

<footer class="scry-ms-admin-review-footer">
    <p class="scry-ms-admin-review-footer-text">
        <?php
        echo wp_kses(
            sprintf(
                /* translators: 1: opening anchor tag, 2: closing anchor tag */
                __('Enjoying Scry Search? Please leave us a %1$s5-star review%2$s.  It helps a lot!', 'scry-search'),
                '<a class="scry-ms-admin-review-footer-link" href="' . esc_url('https://wordpress.org/support/plugin/scry-search/reviews/#new-post') . '" target="_blank" rel="noopener noreferrer">',
                '</a>'
            ),
            array(
                'a' => array(
                    'href'   => true,
                    'target' => true,
                    'rel'    => true,
                    'class'  => true,
                ),
            )
        );
        ?>
        <span class="scry-ms-admin-review-footer-stars" aria-hidden="true">★★★★★</span>
    </p>
</footer>
