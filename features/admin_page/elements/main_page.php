<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get the search settings feature instance
$search_settings = $this->get_feature('scrywp_search_settings');

if ($search_settings) {
    require_once plugin_dir_path(__FILE__) . '../../search_settings/elements/weights_input.php';
} else {
    echo '<div class="notice notice-error"><p>Search settings feature not found.</p></div>';
}
?>
