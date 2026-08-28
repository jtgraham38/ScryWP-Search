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
    Or should it be via a hidden input field?

    Their schema is:
    - id - auto increment
    - name - unique name for the engine.  It must be a string of lowercase alphanumerics and dashes/underscores.  This is how we will generate classnames to apply to bars.
    - classname - it will be programmatically generated from the name.
    - type - the type of the engine.  For now, this will default to "federated".
    - description - a short description of the engine.  For admin purposes only.
    - settings - a json/serialized object that stores the engine's settings.  This will be used to store meilisearch-specific settings that shoudl be applied to every search that uses this engine.
    - created_at- timestamp when the engine was created.
    - updated_at- timestamp when the engine was last updated.

    It will then be applied by the search feature to the MultiSearchFederation, FederationOptions, and SearchQuery objects
    when the search feature is used to search the index.

    The settings array will have the following structure:
    (TODO: finish designing this schema)
    {
        "search_queries":{
            "<index_1_name>": {
                "resource_type": "search_query",
                "index_uid": "<index_1_name>",
                "filters": {
                    "resource_type": "array",
                    "items": {
                        "<field_name> <operator> <value>",
                        ...
                    }
                },
                "sorts": {
                    "resource_type": "array",
                    "items": [
                        "<field_name>:<direction>",
                        ...
                    ]
                },
                "hybrid": {
                    "resource_type": "hyrbrid_search_options",
                    "embedder": "<embedder_name>",
                    "semantic_ratio": "<semantic_ratio>"
                },
                "federation_options": {
                    "resource_type": "federation_options",
                    "weight": <weight>
                }
            },
            "<index_2_name>": {
                ...
            },
            ...
        }
        "multisearch_federation": {
            "limit": <limit>,
            "offset": <offset>,
            "page": <page>,
            "hits_per_page": <hits_per_page>,
            "facets_by_index": [
                "<index_1_name>": [
                    "<facet_name>",
                    ...
                ],
                "<index_2_name>": [
                    "<facet_name>",
                    ...
                ],
                ...
            ],
            "merge_facets": [
                TODO
            ],
            "distinct": <distinct_string>,
            "performance_details": <performance_details>,
            "personalization": {
                TODO
            }
        }
    }

    We will need an admin page to manage engines using a wp-list-table.  It will support create, edit, and delete operations.

    We can design the controls for the edit and create pages once the schema is finalized.

    The site must always have an engine called "default" that is used when no engine is specified.
    It uses all indexes on the site in a single federated search, and emulates the default behavior of the plugin
    before the engines feature was implemented.
    */
    
}