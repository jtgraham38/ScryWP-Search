<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\FederationOptions;

class ScrySearch_SearchFeature extends PluginFeature {
    
    public function add_filters() {
        // Add any filters here if needed
        add_filter('posts_pre_query', array($this, 'meilisearch_search'), 10, 2);

    }
    
    public function add_actions() {
        // Register settings on init so they are available to REST requests.
        add_action('admin_init', array($this, 'register_settings'));
        // Custom REST endpoint for block editor facet settings lookup.
        add_action('admin_menu', array($this, 'add_admin_page'));
    }

    //coopt the wordpress search feature, replacing it with out meilisearch search
    public function meilisearch_search($posts, $query = null) {
        
        // If query is not provided or not a WP_Query instance, return early
        if (!$query || !($query instanceof WP_Query)) {
            return $posts;
        }
        //ensure this is a search query (frontend, url contains "?s=...")
        if (!$query->is_search) {
            return $posts; // Return null or existing posts to let WordPress handle it normally
        }
        if (!$query->get('s')){
            return $posts; // Return null or existing posts to let WordPress handle it normally
        }

        //get all the post types we are searching, that overlap with
        //the indexed post types
        $indexed_post_types = get_option($this->prefixed('post_types'));
        if (!is_array($indexed_post_types)) {
            $indexed_post_types = array();
        }
        $post_types_values = $query->get('post_type');
        if (empty($post_types_values)) {
            return $posts;
        }
        if (!is_array($post_types_values)) {
            $post_types_values = array($post_types_values);
        }
        $post_types_to_search = array_intersect($post_types_values, $indexed_post_types);
        //if there are no post types to search, search all indexed post types
        if (empty($post_types_to_search)) {
            if (empty($indexed_post_types)) {
                return $posts;
            }
            $post_types_to_search = $indexed_post_types;
        }
        
        //now, get the index names for the post types we are searching
        $index_feature = $this->get_feature('scry_ms_indexes');
        $index_names = $index_feature->get_index_names();
        $index_names_to_search = array_intersect_key($index_names, array_flip($post_types_to_search));

        //get the search query, and all other query params that should be passed to the meilisearch search
        $query_params = array();

        if ($query->get('s')) $query_params['q'] = $query->get('s');
        
        // Handle pagination
        $posts_per_page = $query->get('posts_per_page');
        if ($posts_per_page && $posts_per_page > 0) {
            $query_params['limit'] = $posts_per_page;
        }
        
        $paged = $query->get('paged');
        if ($paged && $paged > 1 && isset($query_params['limit'])) {
            $query_params['offset'] = ($paged - 1) * $query_params['limit'];
        }
        
        if ($query->get('offset')) $query_params['offset'] = $query->get('offset');
        if ($query->get('limit') && !isset($query_params['limit'])) $query_params['limit'] = $query->get('limit');
        //skip hitsPerPage for now
        //skip page for now
        //THE ABOVE ARE VITAL, ADD SUPPORT FOR THE REST IN DOCUMENTATION ORDER!.

        //add support for facets
        if (isset($_GET['scry-search']['facets']['taxonomies'])) {
            $taxonomy_facets_to_search = $_GET['scry-search']['facets']['taxonomies'];
            //
            //
            ////TODO: use these ids in the search query
            //
            //
        }

        //ensure we gracefully fall back to the wordpress search if the meilisearch search fails
        try {
            //create a meilisearch client
            $client = new Client(
                get_option($this->prefixed('meilisearch_url')), 
                get_option($this->prefixed('meilisearch_search_key'))
            );

            //get the search weights
            $search_weights = get_option($this->prefixed('search_weights'));

            //create search queries
            $search_queries = array();
            foreach ($index_names_to_search as $post_type => $index_name) {

                //get the search weight for the index, or default to 1.0 if not set
                $search_weight = isset($search_weights[$post_type]) ? $search_weights[$post_type] : 1.0;

                //create a new search query
                $search_queries[] = (new SearchQuery())
                    ->setIndexUid($index_name)
                    ->setFederationOptions((new FederationOptions())->setWeight(floatval($search_weight)))
                    ->setQuery($query_params['q']);
            }

            // Set pagination on MultiSearchFederation (handles federated search pagination)
            $federation = new MultiSearchFederation();
            if (isset($query_params['limit'])) {
                $federation->setLimit($query_params['limit']);
            }
            if (isset($query_params['offset'])) {
                $federation->setOffset($query_params['offset']);
            }
            
            //use federated multi search to search the indexes
            $search_results = $client->multiSearch($search_queries, $federation);

        } catch (Exception $e) {
            //fall back to the wordpress search
            return $posts;
        }
        
        //multiSearch returns an array with 'results' key containing array of search results
        $all_results = array();
        $total_hits = 0;
        
        if (isset($search_results['hits']) && is_array($search_results['hits'])) {
            $all_results = $search_results['hits'];
            $total_hits = $search_results['estimatedTotalHits'];
        }

        // Track search analytics
        try {
            $analytics_feature = $this->get_feature('scry_ms_analytics');
            if ($analytics_feature) {
                $analytics_feature->insert_search_analytics_event(array(
                    'search_term'         => $query_params['q'],
                    'result_count'        => $total_hits,
                    'result_ids'          => array_column($all_results, 'ID'),
                    'result_titles'       => array_column($all_results, 'post_title'),
                    'post_types_searched' => array_values($post_types_to_search),
                ));
            }
        } catch (Exception $e) {
            // Silently fail - analytics should not break search
        }
        
        //get the post ids from the search results
        $post_ids = array_column($all_results, 'ID');

        //fetch the actual WP_Post objects
        if (!empty($post_ids)) {
            // Get posts in the order returned by Meilisearch
            // Use the actual limit from query params, or count if not set
            $limit = isset($query_params['limit']) ? $query_params['limit'] : count($post_ids);
            $posts_array = get_posts(array(
                'post__in' => $post_ids,
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'post_type' => $post_types_to_search,
                'orderby' => 'post__in',
                'order' => 'ASC',
            ));
            
            // Set the found posts count for pagination
            $query->found_posts = $total_hits ?: count($post_ids);
            $query->max_num_pages = ceil($query->found_posts / ($posts_per_page ?: 10));
        
            
            return $posts_array;
        } else {
            // No results found - return empty array but keep search term for display
            $query->found_posts = 0;
            $query->max_num_pages = 0;

            return array();
        }
        
    }

    /**
     * Register settings sections/fields for the admin page UI.
     */
    public function register_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Register the search weights section
        add_settings_section(
            $this->prefixed('search_weights_section'),
            __('Search Weights', "scry-search"),
            function() {
                echo '<p>' . esc_html__('Configure search weights for each post type. Higher weights will prioritize results from that post type in federated searches.', "scry-search") . '</p>';
            },
            $this->prefixed('search_settings_group')
        );

        // Add the search weights field
        add_settings_field(
            $this->prefixed('search_weights'),
            __('Post Type Weights', "scry-search"),
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/settings/search_weights_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('search_weights_section')
        );

        // Register search weights setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('search_weights'),
            array(
                'type' => 'array',
                'description' => 'Search weights mapping post types to numeric weight values for Scry Search for Meilisearch.',
                'sanitize_callback' => function($input) {
                    if (!is_array($input)) {
                        return array();
                    }
                    
                    // Get valid post types
                    $valid_post_types = get_post_types(array(), 'names');
                    $sanitized = array();
                    
                    foreach ($input as $post_type => $weight) {
                        // Validate post type key exists
                        if (!in_array($post_type, $valid_post_types, true)) {
                            continue;
                        }
                        
                        // Sanitize weight as float
                        $weight_value = filter_var($weight, FILTER_VALIDATE_FLOAT);
                        if ($weight_value === false) {
                            // If invalid, default to 1.0
                            $weight_value = 1.0;
                        }
                        
                        // Ensure weight is positive
                        if ($weight_value < 0) {
                            $weight_value = 0;
                        }
                        
                        $sanitized[sanitize_text_field($post_type)] = floatval($weight_value);
                    }
                    
                    return $sanitized;
                },
                'default' => array(
                    'taxonomies' => array(),
                    'meta' => array(),
                ),
                'show_in_rest' => false,
            )
        );

        // Register facets section
        add_settings_section(
            $this->prefixed('search_facets_section'),
            __('Filterable Facets', "scry-search"),
            function() {
                echo '<p>' . esc_html__('Select which taxonomies can be used as facets in search filtering.', "scry-search") . '</p>';
            },
            $this->prefixed('search_settings_group')
        );

        // Add facets field
        add_settings_field(
            $this->prefixed('search_facets'),
            __('Taxonomies', "scry-search"),
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/settings/facets_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('search_facets_section')
        );

        // Register facets setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('search_facets'),
            array(
                'type' => 'array',
                'description' => 'Selected taxonomy slugs that are filterable facets in Scry Search for Meilisearch.',
                'sanitize_callback' => function($input) {
                    if (!is_array($input)) {
                        return array(
                            'taxonomies' => array(),
                            'meta' => array(),
                        );
                    }

                    $raw_taxonomies = array();
                    if (isset($input['taxonomies'])) {
                        $raw_taxonomies = is_array($input['taxonomies'])
                            ? $input['taxonomies']
                            : array($input['taxonomies']);
                    }

                    // Keep only valid taxonomy slugs.
                    $valid_taxonomies = get_taxonomies(array(), 'names');
                    $excluded_taxonomies = array('post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area');
                    $valid_taxonomies = array_values(array_diff($valid_taxonomies, $excluded_taxonomies));

                    $sanitized_taxonomies = array();
                    foreach ($raw_taxonomies as $taxonomy_slug) {
                        if (!is_string($taxonomy_slug)) {
                            continue;
                        }
                        $taxonomy_slug = sanitize_key($taxonomy_slug);
                        if ($taxonomy_slug === '') {
                            continue;
                        }
                        if (!in_array($taxonomy_slug, $valid_taxonomies, true)) {
                            continue;
                        }
                        $sanitized_taxonomies[] = $taxonomy_slug;
                    }

                    $sanitized_facets = array(
                        'taxonomies' => array_values(array_unique($sanitized_taxonomies)),
                        'meta' => array(),
                    );

                    // Keep index filterable attributes in sync with selected taxonomy facets.
                    $index_feature = $this->get_feature('scry_ms_indexes');
                    if ($index_feature && method_exists($index_feature, 'update_filterable_attributes_for_taxonomies')) {
                        $index_feature->update_filterable_attributes_for_taxonomies($sanitized_facets['taxonomies']);
                    }

                    return $sanitized_facets;
                },
                'default' => array(
                    'taxonomies' => array(),
                    'meta' => array(),
                ),
                'show_in_rest' => false,
            )
        );
    }

    //add an admin page for the search settings
    public function add_admin_page() {
        // Register this page with the admin page feature
        $admin_page_feature = $this->get_feature('scry_ms_admin_page');
        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scry-search-meilisearch-search-config',
                __('Search Settings', "scry-search"),
                'dashicons-search',
                __('Configure the search settings for ScryWP Search.', "scry-search")
            );
        }

        add_submenu_page(
            'scry-search-meilisearch',
            'Search Settings',
            'Search Settings',
            'manage_options',
            'scry-search-meilisearch-search-config',
            function() {
                $file_path = plugin_dir_path(__FILE__) . 'elements/_inputs.php';
                $this->get_feature('scry_ms_admin_page')->render_admin_page($file_path);
            }
        );
    }
}