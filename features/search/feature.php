<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Client;

class ScryWpSearchFeature extends PluginFeature {
    
    public function add_filters() {
        // Add any filters here if needed
        add_filter('posts_pre_query', array($this, 'meilisearch_search'), 10, 2);

    }
    
    public function add_actions() {
        // Add any actions here if needed
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

        //first, get the index to search
        $post_type = $query->get('post_type');
        $index_feature = $this->get_feature('scrywp_indexes');
        $index_names = $index_feature->get_index_names();
        if (!isset($index_names[$post_type])) {
            return $posts; // Return null or existing posts to let WordPress handle it normally
        }
        $index_name = $index_names[$post_type];

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

        //ensure we gracefully fall back to the wordpress search if the meilisearch search fails
        try {
            //create a meilisearch client
            $client = new Client(
                get_option($this->prefixed('meilisearch_url')), 
                get_option($this->prefixed('meilisearch_search_key'))
            );

            //search the index for the results
            $index = $client->index($index_name);
            $search_results = $index->search(
                $query_params['q'],
                $query_params
            );

        } catch (Exception $e) {
            //fall back to the wordpress search
            return $posts;
        }
        
        //get the results from the search
        $results = $search_results->getHits();
        
        //get the post ids from the search results
        $post_ids = array_column($results, 'ID');
        
        //fetch the actual WP_Post objects
        if (!empty($post_ids)) {
            // Get posts in the order returned by Meilisearch
            $posts_array = get_posts(array(
                'post__in' => $post_ids,
                'posts_per_page' => count($post_ids),
                'post_status' => 'publish',
                'post_type' => $post_type,
                'orderby' => 'post__in',
                'order' => 'ASC',
            ));
            
            // Set the found posts count for pagination
            $query->found_posts = $search_results->getEstimatedTotalHits();
            $query->max_num_pages = ceil($query->found_posts / ($posts_per_page ?: 10));
        
            
            return $posts_array;
        } else {
            // No results found - return empty array but keep search term for display
            $query->found_posts = 0;
            $query->max_num_pages = 0;

            return array();
        }
        
    }

    //add an admin page for the search settings
    public function add_admin_page() {
        // Register this page with the admin page feature
        $admin_page_feature = $this->get_feature('scrywp_admin_page');
        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scrywp-search-config',
                __('Search Settings', 'scry-wp'),
                'dashicons-search',
                __('Configure the search settings for ScryWP Search.', 'scry-wp')
            );
        }

        add_submenu_page(
            'scrywp-search',
            'Search Settings',
            'Search Settings',
            'manage_options',
            'scrywp-search-config',
            function() {
                ob_start();
                require_once plugin_dir_path(__FILE__) . 'elements/_inputs.php';
                $content = ob_get_clean();
                $this->get_feature('scrywp_admin_page')->render_admin_page($content);
            }
        );
    }
}