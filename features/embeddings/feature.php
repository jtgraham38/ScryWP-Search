<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use jtgraham38\wpvectordb\VectorTable;
use jtgraham38\wpvectordb\VectorTableQueue;
use \NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;

require_once plugin_dir_path(__FILE__) . '../wp_api/ScryWpApiConnection.php';
require_once plugin_dir_path(__FILE__) . '../wp_api/util.php';

class ScryWpEmbeddingsFeature extends PluginFeature{
    use ScryWpChunkingMixin;

    private int $CHUNK_SIZE = 256;

    public function add_filters(){
        //add filter to generate embeddings for a post (this is triggered when the embedding explorer is used)
        //add_action('wp_insert_post', array($this, 'generate_embeddings_for_post'), 10, 3);
    }
    
    public function add_actions(){
        //add submenu page
        add_action('admin_menu', array($this, 'add_menu'));
        
        //register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        //register styles
        add_action('admin_enqueue_scripts', array($this, 'register_styles'));

        //register scripts
        add_action('admin_enqueue_scripts', array($this, 'register_scripts'));
        
        //add meta box
        add_action('add_meta_boxes', array($this, 'add_embedding_meta_box'));

        //show a notice to generate embeddings
        add_action('admin_notices', array($this, 'show_generate_embeddings_notice'));


        //delete embeddings when a post is deleted
        add_action('delete_post', array($this, 'delete_embeddings_for_post'));


        //queue posts for embedding generation from the editor, based on the type of post
        add_action('save_post', array($this, 'enqueue_embedding_from_editor'), 10, 3);

        //    \\    //    CREATE NEW SYSTEM FOR EMBEDDINGS    //    \\
        //schedule the cron job
        add_action('init', array($this, 'schedule_cron_jobs'));

        //hook into the cron job, to consume a batch of posts from the queue
        add_action($this->prefixed('embed_batch_cron_hook'), array($this, 'consume_batch_from_queue'));

        //hook into the cron job, to clean the queue
        add_action($this->prefixed('clean_queue_cron_hook'), array($this, 'clean_queue'));

        //hook into the cron job, to enqueue posts for embedding generation if they are not already embedded
        add_action($this->prefixed('auto_enqueue_embeddings_cron_hook'), array($this, 'auto_enqueue_embeddings'));
    }

    //  \\  //  \\  //  \\  //  \\  //  \\  //  \\  //  \\  //  \\

    //  \\  //  \\  //  \\  //  \\ MANAGE QUEUE FOR POSTS REQUIRING EMBEDDINGS  //  \\  //  \\  //  \\  // \\
    //schedule the cron job
    public function schedule_cron_jobs(){

        //if the chunking method is not set, do not consume the batch from the queue
        $chunking_method = get_option($this->prefixed('chunking_method'));
        if ($chunking_method == 'none' || $chunking_method == '') {
            return;
        }
        
        //schedule the cron job to consume a batch of posts from the queue every 15 seconds
        if (!wp_next_scheduled($this->prefixed('embed_batch_cron_hook'))) {
            wp_schedule_event(time(), 'every_minute', $this->prefixed('embed_batch_cron_hook'));
        }

        //schedule a daily cron job to remove posts that have been completed for more than 3 days
        if (!wp_next_scheduled($this->prefixed('clean_queue_cron_hook'))) {
            wp_schedule_event(time(), 'daily', $this->prefixed('clean_queue_cron_hook'));
        }

        //schedule a weekly cron job to enqueue posts for embedding generation if they are not already embedded
        if (!wp_next_scheduled($this->prefixed('auto_enqueue_embeddings_cron_hook'))) {
            wp_schedule_event(time(), 'weekly', $this->prefixed('auto_enqueue_embeddings_cron_hook'));
        }
    }

    //get a batch of posts from the queue, and send it to the embedding service
    public function consume_batch_from_queue(){
        //if the chunking method is not set, do not consume the batch from the queue
        $chunking_method = get_option($this->prefixed('chunking_method'));
        if ($chunking_method == 'none' || $chunking_method == '') {
            return;
        }
        
        global $wpdb;

        //get a batch of posts from the queue
        $queue = new VectorTableQueue($this->get_prefix());
        $post_ids = $queue->get_next_batch();

        //get all posts with the indicated ids
        $post_types_str = '"' . implode('","', get_option($this->prefixed('post_types'), [])) . '"';
        $post_ids_str = implode(',', $post_ids);

        //return if there are no post types or post ids
        if ($post_types_str == '' || $post_ids_str == ''){
            return;
        }

        //get the posts (no limit needed here because $queue->get_next_batch() already limits the number of posts)
        $posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE ID IN ($post_ids_str) AND post_status = 'publish' AND post_type IN ($post_types_str)");

        //return if there are no posts
        if (empty($posts)) {
            return;
        }

        //return if there are no chunks in any of the posts
        $chunks_exist = false;
        $filtered_posts = [];
        foreach ($posts as $post) {
            $chunks = $this->chunk_post($post);
            if (!empty($chunks)) {
                $chunks_exist = true;
                $filtered_posts[] = $post;
            } else {
                $queue->delete_post($post->ID);
            }
        }
        if (!$chunks_exist) {
            return;
        }

        //at this point we only have posts that have chunks

        //send the posts to the embedding service
        try{

            $api = new ContentOracleApiConnection(
                $this->get_prefix(), 
                $this->get_base_url(), 
                $this->get_base_dir(), 
                $this->get_client_ip(),
                $this->config('chat_timeout'),
                $this->config('embed_timeout')
            );
            $result = $api->bulk_generate_embeddings($filtered_posts);
        } catch (Exception $e){
            //log the error
            error_log($e->getMessage());

            //mark each post in the batch as failed
            $queue->update_status($post_ids, 'failed', $e->getMessage());
            return;
        }


        //mark each post in the batch as completed
        $queue->update_status($post_ids, 'completed');
    }

    //clean the queue
    public function clean_queue(){
        //if the chunking method is not set, do not clean the queue
        $chunking_method = get_option($this->prefixed('chunking_method'));
        if ($chunking_method == 'none' || $chunking_method == '') {
            return;
        }

        //get the queue
        $queue = new VectorTableQueue($this->get_prefix());
        $queue->cleanup();
    }

    //automoatically enqueue posts for embedding generation if they are not already embedded
    public function auto_enqueue_embeddings(){
        //if the chunking method is not set, do not enqueue posts for embedding generation
        $chunking_method = get_option($this->prefixed('chunking_method'));
        if ($chunking_method == 'none' || $chunking_method == '') {
            return;
        }

        //get all posts that are not already embedded
        if (get_option($this->prefixed('auto_generate_embeddings'))){
            $this->enqueue_all_posts_that_are_not_already_embedded();
        }
    }


    //  \\  //  \\  //  \\  //  \\ ENQUEUE POSTS AT DIFFERENT TIMES  //  \\  //  \\  //  \\  // \\

    //callback that flags a post for embedding generation when the checkbox is checked
    public function enqueue_embedding_from_editor($post_ID, $post, $update){
        // check if this is an autosave. If it is, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // check the user's permissions.
        if (!current_user_can('edit_post', $post_ID)) {
            return;
        }
        
        //nonce verification
        if (!isset($_POST[$this->prefixed('generate_embeddings_nonce')]) 
        || 
        !wp_verify_nonce($_POST[$this->prefixed('generate_embeddings_nonce')], $this->prefixed('save_generate_embeddings'))
        ) {
            return;
        }
        
        //check if the post is of the correct post type
        if (!in_array($post->post_type, get_option($this->prefixed('post_types'), []))) {
            return;
        }

        //check if the checkbox is checked
        if (!isset($_POST[$this->prefixed('generate_embeddings')])) {
            return;
        }
        
        //update flag post meta for embedding generation if the checkbox is checked
        try{
            $queue = new VectorTableQueue($this->get_prefix());
            $queue->add_post($post_ID);
        } catch (Exception $e){
            error_log($e->getMessage());
        }
    }

    //enqueue all posts that are not already embedded
    public function enqueue_all_posts_that_are_not_already_embedded(){
        global $wpdb;
        //get all posts that are:
        // 1. not already embedded
        // 2. of the correct post type
        // 3. status is publish
        // 4. are not in the embed queue

        //get post types
        $post_types = get_option($this->prefixed('post_types'));
        $post_types_str = "'" . implode("','", $post_types) . "'";

        

        //get ids of posts that have embeddings
        $VT = new VectorTable($this->get_prefix());
        $queue = new VectorTableQueue($this->get_prefix());
        
        $vecs = $VT->get_all();
        $embedded_ids = array_map(function($vec){
            return $vec->post_id;
        }, $vecs);
        $embedded_ids = array_map('intval', $embedded_ids);
        $embedded_ids[] = -1;

        //get posts in post types, that are published, that are not already embedded, and that are not in the embed queue
        //ordered by the most recently published post
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->posts} 
                WHERE post_type IN ($post_types_str) 
                AND post_status = 'publish'
                AND ID NOT IN (" . implode(',', $embedded_ids) . ")
                AND ID NOT IN (SELECT post_id FROM {$queue->get_table_name()})
                ORDER BY post_date DESC
                LIMIT 500"
            ),
            OBJECT
        );

        
        //remove posts that have no chunks
        $posts = array_filter($posts, function($post){
            $chunked_post = $this->chunk_post($post);
            return !empty($chunked_post->chunks);
        });
        
        //return if there are no posts
        if (empty($posts)) {
            return;
        }

        //get post ids
        $post_ids = array_map(function($post){
            return $post->ID;
        }, $posts);

        //enqueue the posts
        $queue->add_posts($post_ids);
    }

    //enqueue all posts
    //function here for future use, not currently used!
    public function enqueue_all_posts(){
        global $wpdb;

        //create a queue
        $queue = new VectorTableQueue($this->get_prefix());

        //get all posts where
        // 1. of the correct post type
        // 2. status is publish
        // 3. are not in the embed queue
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->posts} 
                WHERE post_type IN ($post_types_str) 
                AND post_status = 'publish'
                AND ID NOT IN (SELECT post_id FROM {$queue->get_table_name()})"
            ),
            OBJECT
        );


        //remove posts that have no chunks
        $posts = array_filter($posts, function($post){
            $chunked_post = $this->chunk_post($post);
            return !empty($chunked_post->chunks);
        });

        //return if there are no posts
        if (empty($posts)) {
            return;
        }

        //get post ids
        $post_ids = array_map(function($post){
            return $post->ID;
        }, $posts);

        //enqueue the posts
        $queue->add_posts($post_ids);
    }



    //  \\  //  \\  //  \\  //  \\ EMBEDDING MAINTENANCE  //  \\  //  \\  //  \\  // \\

    //delete embeddings when a post is deleted
    public function delete_embeddings_for_post($post_id){
        //create vector table
        $vt = new VectorTable($this->get_prefix());

        //get ids of all vectors for the post
        $vectors = $vt->get_all_for_post($post_id);
        
        //delete the vectors
        foreach ($vectors as $vector) {
            $vt->delete($vector->id);
        }

        //delete the queue item
        $queue = new VectorTableQueue($this->get_prefix());
        $queue->delete_post($post_id);
    }

    //  \\  //  \\  //  \\  //  \\ HELPERS  //  \\  //  \\  //  \\  // \\
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
    //    \\    SETTINGS PAGE \\    //
    public function render_page(){
        ob_start();
        require_once plugin_dir_path(__FILE__) . 'elements/_inputs.php';
        $content = ob_get_clean();
        
        $this->get_feature('scrywp_admin_page')->render_admin_page($content);
    }
    
    public function add_menu(){
            add_submenu_page(
                'scrywp-search-hidden', // Parent menu slug (this page does not appear in the sidebar menu)
                'Embeddings', // page title
                'Embeddings', // menu title
                'manage_options', // capability
                'scrywp-search-embeddings', // menu slug
                array($this, 'render_page') // callback function
            );
        }

        public function register_settings(){
            global $wpdb;

            add_settings_section(
                'scrywp_search_embeddings_settings', // id
                '', // title
                function(){ // callback
                    echo 'Manage your AI search settings here.';
                },
                'scrywp-search-embeddings'  // page (matches menu slug)
            );

            // create the settings fields
            add_settings_field(
                $this->prefixed('chunking_method'),    // id of the field
                'Embedding Method',   // title
                function(){ // callback
                    require_once plugin_dir_path(__FILE__) . 'elements/chunking_method_input.php';
                },
                'scrywp-search-embeddings', // page (matches menu slug)
                'scrywp_search_embeddings_settings',  // section
                array(
                'label_for' => $this->prefixed('chunking_method')
                )
            );

            add_settings_field(
                $this->prefixed('auto_generate_embeddings'),    // id of the field
                'Auto-generate Text Embeddings Weekly',   // title
                function(){ // callback
                    require_once plugin_dir_path(__FILE__) . 'elements/auto_generate_embeddings_input.php';
                },
                'scrywp-search-embeddings', // page (matches menu slug)
                'scrywp_search_embeddings_settings',  // section
                array(
                'label_for' => $this->prefixed('auto_generate_embeddings')
                )
            );

            // create the settings themselves
            register_setting(
                'scrywp_search_embeddings_settings', // option group
                $this->prefixed('chunking_method'),    // option name
                array(  // args
                    'type' => 'string',
                    'default' => 'token:256',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            );

            register_setting(
                'scrywp_search_embeddings_settings', // option group
                $this->prefixed('auto_generate_embeddings'),    // option name
                array(  // args
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize_callback' => function($value){
                        return $value ? true : false;
                    }
                )
            );

            //check if each setting is in the db, if not, add it
            $settings = array(
                ['option_name' => $this->prefixed('chunking_method'), 'default' => 'token:256'],
                ['option_name' => $this->prefixed('auto_generate_embeddings'), 'default' => true],
            );

            foreach ($settings as $setting){
                $exists = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name = '{$setting['option_name']}'")[0]->option_name;
                if (!$exists){
                    add_option($setting['option_name'], $setting['default']);
                }
            }
        }
    
        public function register_scripts(){
        //if we are on the embeddings page
        if (strpos(get_current_screen()->base, 'scrywp-search-embeddings') === false) {
            return;
        }

        //enqueue the scripts
        wp_enqueue_script('scrywp-search-embeddings-api', plugin_dir_url(__FILE__) . 'assets/js/api.js', []);
        wp_enqueue_script('scrywp-search-embeddings-page', plugin_dir_url(__FILE__) . 'assets/js/embedding_explorer.js', ['scrywp-search-embeddings-api']);

        //localize the api script with the base url
        wp_localize_script('scrywp-search-embeddings-page', 'contentoracle_ai_chat_embeddings', array(
            'api_base_url' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest')   //TODO: apply this to other rest api calls
        ));
    }

    public function register_styles(){
        if (strpos(get_current_screen()->base, 'contentoracle-ai-chat-embeddings') === false) {
            return;
        }
        wp_enqueue_style('contentoracle-ai-chat-embeddings', plugin_dir_url(__FILE__) . 'assets/css/explorer.css');
    }

    //    \\    add meta box to post editor    //    \\
    public function add_embedding_meta_box(){
        //only add the meta box if the post type is in the list of post types that are indexed by the AI
        if (!in_array(get_post_type(), get_option($this->prefixed('post_types'), []))) {
            return;
        }

        add_meta_box(
            'contentoracle-ai-chat-embeddings',
            'ContentOracle AI Embeddings',
            function(){
                require_once plugin_dir_path(__FILE__) . 'elements/_meta_box.php';
            },
            get_option($this->prefixed('post_types')) ?? [],
            'side',
            'high'
        );
    }

    //  \\  //  \\  //  \\  //  SHOW NOTICES TO PROMPT USER TO GENERATE EMBEDDINGS  //  \\  //  \\  //  \\  //  \\
    public function show_generate_embeddings_notice(){
        //check if the user has the capability to edit posts
        if (!current_user_can('edit_posts')) {
            return;
        }

        //check if we are on a scrywp admin page
        if ( strpos(get_current_screen()->base, 'scrywp-search' ) === false) {
            return;
        }

        //show an admin notice to generate embeddings if the chunking method is set
        if (get_option($this->prefixed('chunking_method')) != 'none') {

            //check if embeddings have been generated
            $vt = new VectorTable($this->get_prefix());
            $embedding_count = $vt->get_vector_count();
            if ($embedding_count > 0) {
                return;
            }

            $generate_embeddings_url = admin_url('admin.php?page=contentoracle-ai-chat-embeddings');
            echo '<div class="notice notice-info is-dismissible">';
            echo '<h2>You must generate embeddings for ContentOracle AI!</h2>';
            echo '<p>Visit <a href="' . esc_url($generate_embeddings_url) . '" >this page </a> to generate embeddings for your posts, or switch to keyword search.</p>';
            echo '</div>';
        }
    }

    public function scrywp_search_token256_get_chunk($content, $embedding_number){
        $chunk_size = 256;
        //strip tags and tokenize content
        $tokenizer = new WhitespaceAndPunctuationTokenizer();
        $tokens = $tokenizer->tokenize(strip_tags($content));

        //get start and end indices of section
        $start = $embedding_number * $chunk_size;
        $end = ($embedding_number + 1) * $chunk_size;

        //get the section from the post content
        $section = array_slice($tokens, $start, $end - $start);
        $section = implode(' ', $section);

        //return the section
        return $section;
    }

    //placeholder uninstall method to identify this feature
    public function uninstall(){
        
    }

}

class ScryWp_ChunksForPost{
    public int $post_id;
    public array $chunks;

    public function __construct(int $post_id, array $chunks){
        $this->post_id = $post_id;
        $this->chunks = $chunks;
    }
}

