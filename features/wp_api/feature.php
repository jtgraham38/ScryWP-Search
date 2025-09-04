<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'ScryWpApiConnection.php';
//require_once plugin_dir_path(__FILE__) . '../embeddings/chunk_getters.php';
require_once plugin_dir_path(__FILE__) . 'ResponseException.php';
require_once plugin_dir_path(__FILE__) . 'WPAPIErrorResponse.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use jtgraham38\wpvectordb\VectorTable;
use jtgraham38\wpvectordb\VectorTableQueue;
use jtgraham38\wpvectordb\query\QueryBuilder;

class ScryWpApiFeature extends PluginFeature{
    use ScryWpChunkingMixin;

    public function add_filters(){
    }

    public function add_actions(){
        add_action('rest_api_init', array($this, 'register_healthcheck_rest_route'));
        add_action('rest_api_init', array($this, 'register_bulk_generate_embeddings_route'));
    }

    //  \\  //  \\  //  \\  //  \\  //  \\  //  \\  //  \\  //  \\

    //register the bulk generate embeddings route
    public function register_bulk_generate_embeddings_route(){
        register_rest_route('scrywp/search/v1', '/content-embed', array(
            'methods' => 'POST',
            'permission_callback' => function($request){
                // Verify the nonce
                $nonce = $request->get_header('X-WP-Nonce');
                if (!wp_verify_nonce($nonce, 'wp_rest')) {
                    return new WP_Error('rest_invalid_nonce', 'Invalid nonce', array('status' => 403));
                }
                
                // Check user capabilities
                return current_user_can('edit_posts');
            },
            'callback' => array($this, 'bulk_generate_embeddings'),
            'args' => array(
                'for' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key){
                        //check if it is in "all", "not_embedded", or a single post id
                        return in_array($param, ['all', 'not_embedded']) || is_numeric($param);
                    },
                    'sanitize_callback' => function($param, $request, $key){
                        if (in_array($param, ['all', 'not_embedded'])){
                            return sanitize_text_field($param);
                        }
                        else{
                            return intval($param);
                        }
                    }
                )
            )
        ));
    }

    //bulk generate embeddings
    public function bulk_generate_embeddings($request){
        global $wpdb;

        $for = $request->get_param('for');

        //create a queue
        $queue = new VectorTableQueue($this->get_prefix());

        //get the posts based on the parameter
        $posts = [];

        $post_types = get_option($this->prefixed('post_types'));
        $post_types_str = "'" . implode("','", $post_types) . "'";
        switch ($for){
            case 'all':
                //get the ids of all posts of the correct post type that are published
                //and where the body has no chunks
                //and are not in the embed queue
                //with a prepared statement
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} 
                        WHERE post_type IN ($post_types_str) 
                        AND post_status = 'publish'
                        AND ID NOT IN (SELECT post_id FROM {$queue->get_table_name()})"
                    ),
                    OBJECT
                );

                break;
            case 'not_embedded':

                //get ids of posts that have embeddings
                $VT = new VectorTable($this->get_prefix());
                $vecs = $VT->get_all();
                $embedded_ids = array_map(function($vec){
                    return intval($vec->post_id);
                }, $vecs);
                $embedded_ids[] = -1;

                //get posts ids of posts of the correct type that have no embeddings
                //include a where not in clause if there are embedded ids, otherwise exclude it
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} 
                        WHERE post_type IN ($post_types_str) 
                        AND post_status = 'publish'
                        AND ID NOT IN (" . implode(',', $embedded_ids) . ")
                        AND ID NOT IN (SELECT post_id FROM {$queue->get_table_name()})"
                    ),
                    OBJECT
                );

                break;
            case is_numeric($for):
                //TODO: possibly check to make sure the post exists
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} 
                        WHERE ID = %d",
                        $for
                    ),
                    OBJECT
                );
                break;
        }

        //read the post ids off of the objects
        $post_ids = array_map(function($result){
            return $result->ID;
        }, $results);
        

        //enqueue the posts for embedding generation
        try{
            $queue->add_posts($post_ids);
        }
        catch (Exception $e){
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $e->getMessage()
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => "Posts enqueued for embedding generation."
        ), 200);
    }

    //get the ip address of the client
    function get_client_ip(){
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            // Check for IP from shared internet
            $ip = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Check for IP passed from proxy
            $ip = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = filter_var($_SERVER['HTTP_X_FORWARDED'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $ip = filter_var($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = filter_var($_SERVER['HTTP_FORWARDED_FOR'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = filter_var($_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP);
        } else {
            // Default fallback to REMOTE_ADDR
            $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        }

        // Handle multiple IPs (e.g., "client IP, proxy IP")
        if (strpos($ip, ',') !== false)
            $ip = explode(',', $ip)[0];

        // Sanitize IP address
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'UNKNOWN';
    }

    //token:256 embedding search
    function token256_content_search($message){

        //begin by embedding the user's message
        $api = new ScryWpApiConnection(
            $this->get_prefix(), 
            $this->get_base_url(), 
            $this->get_base_dir(), 
            $this->get_client_ip(),
            $this->config('chat_timeout'),
            $this->config('embed_timeout')
        );

        //get the embedding from the ai
        $response = $api->query_vector($message);

        if (!isset($response['embeddings'][0]['embedding'])){
            throw new ScryWpSearch_ResponseException(
                'No embeddings returned from the AI',
                $response,
                'coai'
            );
        }

        $embedding = $response['embeddings'][0]['embedding'];

        //load the filters from the database into a query builder object
        //NOTE: the below line causes a silent error, fix it.

        //I have other var dump dies further down the line in vectortable.
        //I have made changes throughout wpvectordb that need to be committed to the main repo, copy/paste would be best.
        $query_params = new QueryBuilder();

        $filters_option = get_option($this->prefixed('filters'), array());
        foreach ($filters_option as $i=>$group){
            $query_params->add_filter_group('scrywp_search_semsearch_filter_group_' . $i);
            foreach ($group as $filter){
                //parse the compare value as the correct type
                $compare_value = $filter['compare_value'];
                switch ($filter['compare_type']){
                    case 'number':
                        $compare_value = floatval($compare_value);
                        break;
                    case 'date':
                        $compare_value = new DateTime($compare_value);
                        break;
                    // text is default
                }

                //set the compare value to the correct type
                $filter['compare_value'] = $compare_value;

                //add the filter to the query builder
                $query_params->add_filter('scrywp_search_semsearch_filter_group_' . $i, $filter);
            }
        }

        //add filters to the query builder for post type and status
        //add a filter for post types and status and empty content
        $post_types = get_option($this->prefixed('post_types'));
        if (!$post_types) $post_types = array('post', 'page');

        $query_params->add_filter_group('_semsearch_post_types');
        $query_params->add_filter('_semsearch_post_types', [
            'field_name' => 'post_type',
            'operator' => 'IN',
            'compare_value' => $post_types
        ]);

        $query_params->add_filter_group('_semsearch_post_status');
        $query_params->add_filter('_semsearch_post_status', [
            'field_name' => 'post_status',
            'operator' => '=',
            'compare_value' => 'publish'
        ]);

        $query_params->add_filter_group('_semsearch_post_content');
        $query_params->add_filter('_semsearch_post_content', [
            'field_name' => 'post_content',
            'operator' => '!=',
            'compare_value' => ''
        ]);
        //load the sorts from the database into a query builder object
        $sorts_option = get_option($this->prefixed('sorts'), array());
        foreach ($sorts_option as $i=>$sort){
            $query_params->add_sort($sort);
        }

        //then, find the most similar vectors in the database table
        $vt = new VectorTable( $this->get_prefix() );

        $ordered_vec_ids = $vt->search( $embedding, 20, $query_params );
        //then, get the posts and sections each vector corresponds to
        $vecs = $vt->ids( $ordered_vec_ids );
        

        //create an array of the content embedding data
        $content_embedding_datas = [];
        foreach ($vecs as &$vec){
            $content_embedding_datas[] = [
                'id' => $vec->id,
                'post_id' => $vec->post_id,
                'sequence_no' => $vec->sequence_no,
            ];
        }

        //use the sequence numbers and post metas to retrieve the correct portions of the posts
        $chunks = [];
        foreach ($content_embedding_datas as $data){
            $post_id = $data['post_id'];
            $sequence_no = $data['sequence_no'];
            
            //get the post
            $post = get_post($post_id);

            //get the post content for the sequence number
            $chunk = $this->get_feature('scrywp_embeddings')->scrywp_search_token256_get_chunk($post->post_content, $sequence_no);

            //add the chunk to the chunks array
            $chunks[] = [
                'id' => $post_id,
                'title' => esc_html($post->post_title),
                'url' => esc_url(get_the_permalink($post_id)),
                'body' => esc_html($chunk),
                'type' => esc_html(get_post_type($post_id)),
                'image' => esc_url(get_the_post_thumbnail_url($post_id)),
            ];
        }

        //return the post chunks
        return $chunks;
    }

    //get post meta configured by the user for each chunk
    function add_meta_attributes($chunks){
        /*
        Chunks is an array of arrays like this: 
        Array
        (
            [id] => 542
            [title] => Title
            [url] => http://url.com/123
            [body] => lorem ipsum
            [type] => post_type
            [image] => http://image.com/123.jpg
        )
        */
        //get post types
        $post_types = get_option($this->prefixed('post_types'));
        if (!$post_types) $post_types = array('post', 'page');

        //loop over each chunk
        foreach ($chunks as &$chunk){
            //skip posts of types that should not be used
            if (!in_array($chunk['type'], $post_types)) continue;

            //get the post meta for the post
            $meta = get_post_meta($chunk['id']);

            //get the meta keys for that post type configured by the user
            $keys = get_option($this->prefixed( $chunk['type'] . '_prompt_meta_keys' ), []);

            //filter out the meta that is not configured by the user
            $meta = array_filter($meta, function($key) use ($keys){
                return in_array($key, $keys);
            }, ARRAY_FILTER_USE_KEY);

            //add the meta to the chunk
            $chunk['meta'] = $meta;
        }

        //return the chunks with the meta added
        return $chunks;
    }

    //register a scrywp-search healthcheck route
    function register_healthcheck_rest_route(){
        register_rest_route('scrywp/search/v1', '/healthcheck', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true', // this line was added to allow any site visitor to make an ai healthcheck request
            'callback' => function(){
                return new WP_REST_Response(array(
                    'status' => 'ok'
                ));
            }
        ));
    }

    //placeholder uninstall method to identify this feature
    public function uninstall(){
    }
}