<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScryWpSearchSettingsFeature extends PluginFeature {
    
    public function add_filters() {
        // Add any filters here if needed
    }
    
    public function add_actions() {
        // Add any actions here if needed
    }
    
    //  \\  //  \\  //  \\  //  \\  //  \\
    /*
    TODO: The core of the plugin is an upgraded, customizable search engine for the site.  It is based on a multinomial equation.
    Different factors about the site content and search query will be considered, and combined with user-defined weights and 
    biases to a final score for each result.  In this feature, we will register the settings that allow the users to customize the 
    coefficients and biases for each factor.  They will be stored in the database, and the scoring engine will use them to score the 
    results.

    Users should be able to enable different factors, such as semantic similarity, recency, keyword matching, category matching, etc.
    using a dropdown and add button.  When they are added, inputs for them should appear on the admin page, where they can customize the weight and bias using
    a slider or a checkbox.  All inputs should be validated and labelled for a good user experience.  When they are removed, then inputs should disapper.

    
    NOTE: Make sure the only user who can access these settings is the admin.  They should NOT be visible to other users.

    It will be located in the WordPress admin area, under the admin page registered by the admin_page feature.

    All settings, classes, and other plugin-specific information should be prefixed using the method appearing throughout the plugin.

    
    */
}