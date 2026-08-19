<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;


//add support for common tasks to the abilities api of wordpress
class ScrySearch_EnginesFeature extends PluginFeature {
    
    public function add_filters() {

    }
    
    public function add_actions() {

    }

    /*
    Engines are search presets that can be used to customize specific search experiences, and applied to specific bars via a classname.
    Define their schema here.
    A search engine will be stored in its own custom table, and they will be applied to specific bars via a classname.

    Their schema is:
    - id - auto increment
    - name - unique name for the engine.  It must be a string of lowercase alphanumerics and dashes/underscores.  This is how we will generate classnames to apply to bars.
    - classname - it will be programmatically generated from the name.
    - description - a short description of the engine.  For admin purposes only.
    - settings - a json/serialized object that stores the engine's settings.  This will be used to store meilisearch-specific settings that shoudl be applied to every search that uses this engine.
    - created_at- timestamp when the engine was created.
    - updated_at- timestamp when the engine was last updated.

    It will then be applied by the search feature to the MultiSearchFederation, FederationOptions, and SearchQuery objects
    when the search feature is used to search the index.

    The settings array will have the following structure:
    (TODO: finish designing this schema)
    {
        "indexes": [
            "<index_1_name>": {
                "search_query": {
                    todo
                },
                "federation_options": {
                    todo
                },
                todo
            }
        ],
        "multisearch_federation": {
            todo
        }
    }

    We will need an admin page to manage engines using a wp-list-table.  It will support create, edit, and delete operations.

    We can design the controls for the edit and create pages once the schema is finalized.

    The site must always have an engine called "default" that is used when no engine is specified.
    It uses all indexes on the site in a single federated search, and emulates the default behavior of the plugin
    before the engines feature was implemented.
    */
    
}