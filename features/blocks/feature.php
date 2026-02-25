<?php
/**
 * Blocks Feature
 * 
 * @package scry_ms_Search
 * @since 1.0.0
 */

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

//create a new feature for the blocks
class ScrySearch_BlocksFeature extends PluginFeature {
    public function add_filters() {
        // No filters needed
    }

    public function add_actions() {
        //register the blocks
        add_action('init', array($this, 'register_blocks'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    //register the blocks
    public function register_blocks() {
        if (!function_exists('register_block_type')) {
            return;
        }

        $build_dir = __DIR__ . '/build';
        if (!is_dir($build_dir)) {
            return;
        }

        // Prefer metadata collection API when available (WP 6.7+),
        // but keep a folder-scan fallback for compatibility.
        if (
            function_exists('wp_register_block_types_from_metadata_collection')
            && file_exists($build_dir . '/blocks-manifest.php')
        ) {
            wp_register_block_types_from_metadata_collection(
                $build_dir,
                $build_dir . '/blocks-manifest.php'
            );
            return;
        }

        // Fallback: register blocks nested one folder deep in /build.
        $block_json_files = glob($build_dir . '/*/block.json');
        if (!$block_json_files) {
            return;
        }

        foreach ($block_json_files as $block_json_file) {
            register_block_type(dirname($block_json_file));
        }
    }

    /**
     * Register REST routes for block editor data needs.
     */
    public function register_rest_routes() {
        register_rest_route(
            'scry-search/v1',
            '/blocks/facets/search-facets',
            array(
                'methods' => 'GET',
                'callback' => function(){

                    //get the excluded taxonomies from the settings
                    $excluded_taxonomies = array(
                        'post_format',
                        'nav_menu',
                        'link_category',
                        'wp_theme',
                        'wp_template_part_area',
                    );

                    //get all the taxonomies
                    $all_taxonomies = get_taxonomies(array(), 'objects');
                    $taxonomies_payload = array();

                    //loop through all the taxonomies
                    foreach ($all_taxonomies as $slug => $taxonomy) {

                        //skip the excluded taxonomies
                        if (in_array($slug, $excluded_taxonomies, true)) {
                            continue;
                        }

                        //get the terms for the taxonomy
                        $terms = get_terms(array(
                            'taxonomy' => $slug,
                            'hide_empty' => false,
                        ));

                        //if there is an error, set the terms to an empty array
                        if (is_wp_error($terms)) {
                            $terms = array();
                        }

                        //loop through all the terms
                        $term_payload = array();
                        foreach ($terms as $term) {
                            $term_link = get_term_link($term);
                            //add the term to the payload
                            $term_payload[] = array(
                                'id' => (int) $term->term_id,
                                'name' => (string) $term->name,
                                'slug' => (string) $term->slug,
                                'taxonomy' => (string) $term->taxonomy,
                                'count' => (int) $term->count,
                                'description' => (string) $term->description,
                                'link' => is_wp_error($term_link) ? '' : (string) $term_link,
                            );
                        }

                        //add the taxonomy to the payload
                        $taxonomies_payload[$slug] = array(
                            'slug' => $slug,
                            'label' => $taxonomy->label,
                            'terms' => $term_payload,
                        );
                    }

                    //return the payload
                    return rest_ensure_response(array(
                        'taxonomies' => $taxonomies_payload,
                        'meta' => array()
                    ));
                },
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            )
        );
    }
}