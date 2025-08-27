<?php
/**
 * Plugin Name:	ScryWP Search
 * Plugin URI:	https://scrywp.com
 * Description:	A powerful semantic search plugin with advanced filtering and sorting features.
 * Version:	1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:	JG Web Development
 * Author URI:	https://jacob-t-graham.com
 * License:	GPLv3 or later
 * License URI:	https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:	scry-wp
 */

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Require Composer's autoload file
require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';

// Use statements for namespaced classes
use jtgraham38\jgwordpresskit\Plugin;
use jtgraham38\jgwordpresskit\PluginFeature;

//create a new plugin manager
$plugin = new Plugin("scrywp_", plugin_dir_path( __FILE__ ), plugin_dir_url( __FILE__ ));

//register features with the plugin manager here...
require_once plugin_dir_path(__FILE__) . '/features/admin_page/feature.php';
$feature = new ScryWpAdminPageFeature();
$plugin->register_feature("scrywp_admin_page", $feature);

require_once plugin_dir_path(__FILE__) . '/features/search_settings/feature.php';
$search_settings_feature = new ScryWpSearchSettingsFeature();
$plugin->register_feature("scrywp_search_settings", $search_settings_feature);

require_once plugin_dir_path(__FILE__) . '/features/search_results/feature.php';
$search_results_feature = new ScryWpSearchResultsFeature();
$plugin->register_feature("scrywp_search_results", $search_results_feature);

//init the plugin
$plugin->init();
