<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScrySearch_LogsFeature extends PluginFeature {
    
    public function add_filters() {
        // No filters needed
    }
    
    public function add_actions() {
        // No actions needed
    }
}