<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

/**
 * Adds matched-term highlighting to Scry Search results.
 */
class ScrySearch_HighlightingFeature extends PluginFeature {

    /**
     * Register feature filters.
     */
    public function add_filters() {
        // Highlighting filters will be registered in the next implementation step.
    }

    /**
     * Register feature actions.
     */
    public function add_actions() {
        // Settings and frontend assets will be registered in later steps.
    }
}
