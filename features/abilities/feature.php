<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;


//add support for common tasks to the abilities api of wordpress
class ScrySearch_AbilitiesFeature extends PluginFeature {
    
    public function add_filters() {

    }
    
    public function add_actions() {

    }

    //add support to the abilities api for:
    //- running a search
    //- creating updating and deleting indexes
    //- adding and removing posts to/from indexes
    //- reading tasks
    //- reading error/debug logs
    //- reading/updating plugin settings (not including api keys)
    //- reading analytics events

    
}