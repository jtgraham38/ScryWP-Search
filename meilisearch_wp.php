<?php
/**
 * Plugin Name:	Scry Search for Meilisearch
 * Plugin URI:	https://scrywp.com
 * Description:	A powerful semantic search plugin, powered by Meilisearch.
 * Version:	1.0.0
 * Requires at least: 5.2
 * Requires PHP:      8.1
 * Author:	JG Web Development
 * Author URI:	https://jacob-t-graham.com
 * License:	GPLv3 or later
 * License URI:	https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:	scry-ms-search
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
$plugin = new Plugin("scry_ms_", plugin_dir_path( __FILE__ ), plugin_dir_url( __FILE__ ));

//register features with the plugin manager here...
require_once plugin_dir_path(__FILE__) . '/features/admin_page/feature.php';
$feature = new ScrySearch_AdminPageFeature();
$plugin->register_feature("scry_ms_admin_page", $feature);

require_once plugin_dir_path(__FILE__) . '/features/search/feature.php';
$search_feature = new ScrySearch_SearchFeature();
$plugin->register_feature("scry_ms_search", $search_feature);

require_once plugin_dir_path(__FILE__) . '/features/indexes/feature.php';
$indexes_feature = new ScrySearch_IndexesFeature();
$plugin->register_feature("scry_ms_indexes", $indexes_feature);

require_once plugin_dir_path(__FILE__) . '/features/connection_settings/feature.php';
$connection_settings_feature = new ScrySearch_ConnectionSettingsFeature();
$plugin->register_feature("scry_ms_connection_settings", $connection_settings_feature);

//init the plugin
$plugin->init();
