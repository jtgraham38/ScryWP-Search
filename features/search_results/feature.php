<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScryWpSearchResultsFeature extends PluginFeature {
    
    public function add_filters() {
        // Add any filters here if needed
    }
    
    public function add_actions() {
        // Add any actions here if needed
        add_action('pre_get_posts', array($this, 'scrywp_search_results'));
    }


    //main unction the intercepts a search query and performs our custom search
    public function scrywp_search_results($query) {
        //ensure this is a search query
        if ($query->is_search()) {

            //get the search phrase
            $search_phrase = $query->query_vars['s'];

            //our search wil hav two phases:
            //candidate generation, where we use general filtering and sorting to reduce the number of posts we need to score
            //and scoring, where we use our weights defined in the admin to score the candidates

            //  \\  //PART 1: CANDIDATE GENERATION \\  //   \\

            //use filters from query that we want applied, to such as post type, post status, etc.
            $post_type = isset($query->query_vars['post_type']) ? $query->query_vars['post_type'] : null;
            $post_status = isset($query->query_vars['post_status']) ? $query->query_vars['post_status'] : null;

            //get the post ids of all posts of the given type and status
            $candidate_post_ids = $this->get_post_ids_by_type_and_status($post_type, $post_status);

            //from those post ids, compute a semantic hamming distance between the search phrase and the post content
            $candidate_post_ids_and_distances = $this->compute_semantic_hamming_distance($search_phrase, $candidate_post_ids);



            //  \\  //PART 2: SCORING \\  //   \\



            
            // echo '<pre>';
            // //dump the parsed search terms
            // var_dump($query->query_vars);
            // echo '</pre>';
            die;
        }
    }
    
    //	\\	// CANDIDATE GENERATION HELPERS \\	//	\\

    //return the ids of all posts of a given type and status
    public function get_post_ids_by_type_and_status($post_type, $post_status) {

        //assemble args
        $args = array(
            'posts_per_page' => -1
        );

        //add post type if provided
        if ($post_type) {
            $args['post_type'] = $post_type;
        }

        //add post status if provided
        if ($post_status) {
            $args['post_status'] = $post_status;
        }

        //execute query
        $query = new WP_Query($args);
        $posts = $query->posts;

        //return the ids of the posts
        return array_map(function($post) {
            return $post->ID;
        }, $posts);
    }

    //compute the semantic hamming distance between a search phrase and a post
    public function compute_semantic_hamming_distance($search_phrase, $post_ids) {
        //TODO: implement this with wpvectordb
        //return the post ids, along with their semantic hamming distance, sorted by distance
    }
    //	\\	// SCORING HELPERS \\	//	\\





/*
Todo: I need text embedding generation in the plugin before I can implement any more of the search results feature.
1) I will need to modify wpvectordb to devise a way to detect an existing vector db from contentoracle ai chat, and if it exists,
use that, if not, create one for this plugin.  Then, COAI chat should use this one.
2) Then, I will need to copy the generate embeddings feature over from coai chat.
3) Then, I'll need to add functions to wpvectordb to compoentize the search process.
Finally, once I have all the vectors generating and storing correctly, I can implement the scoring process.
*/

    
}