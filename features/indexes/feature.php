<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Client;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\ApiException;

class ScryWpIndexesFeature extends PluginFeature {
    
    public function add_filters() {
        // Individual settings are sanitized via register_setting sanitize_callback
    }
    
    public function add_actions() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        //add an admin page for the indexes
        add_action('admin_menu', array($this, 'add_admin_page'));

        //ensure indexes exist in meilisearch for all selected post types
        add_action('init', array($this, 'ensure_post_indexes_exist'));

        //register hooks to keep indexes in step
        add_action('save_post', array($this, 'index_post'));
        add_action('wp_trash_post', array($this, 'trash_post'));
        
        // Register AJAX handlers
        add_action('wp_ajax_' . $this->prefixed('wipe_index'), array($this, 'ajax_wipe_index'));
        add_action('wp_ajax_' . $this->prefixed('index_posts'), array($this, 'ajax_index_posts'));
        add_action('wp_ajax_' . $this->prefixed('search_index'), array($this, 'ajax_search_index'));
        add_action('wp_ajax_' . $this->prefixed('get_index_settings'), array($this, 'ajax_get_index_settings'));
        add_action('wp_ajax_' . $this->prefixed('update_index_settings'), array($this, 'ajax_update_index_settings'));
    }

    //function to index a post when it is created or updated
    public function index_post(int $post_id) {

        //get the post
        $post = get_post($post_id);

        //ensure this post is of a type that should be indexed
        $indexes = $this->get_index_names();

        if (!isset($indexes[$post->post_type])) {
            return;
        }

        //ensure the post is published
        if ($post->post_status !== 'publish') {
            return;
        }

        //prepare the post for indexing
        $post_data = $this->format_post_for_meilisearch($post);

        //get the index name for this post type
        $index_name = $indexes[$post->post_type];

        //provide success and error handling
        try {
            //init a meilisearch client
            $client = new Client(
                get_option($this->prefixed('meilisearch_url')), 
                get_option($this->prefixed('meilisearch_admin_key'))
            );

            //index the post
            $client->index($index_name)->updateDocuments($post_data);
        } catch (Exception $e) {
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo $e->getMessage(); ?></p>
                    <details>
                        <summary>View Details</summary>
                        <pre><?php echo esc_html(print_r($e, true)); ?></pre>
                    </details>
                </div>
                <?php
            });
        }
    }

    //function to delete a post from the index when it is trashed
    public function trash_post(int $post_id) {
        //get the post
        $post = get_post($post_id);

        //ensure this post is of a type that should be indexed
        $indexes = $this->get_index_names();
        if (!isset($indexes[$post->post_type])) {
            return;
        }

        //get the index name for this post type
        $index_name = $indexes[$post->post_type];

        //provide success and error handling
        try {
        //init a meilisearch client
        $client = new Client(
            get_option($this->prefixed('meilisearch_url')), 
            get_option($this->prefixed('meilisearch_admin_key'))
        );

            //delete the post from the index
            $client->index($index_name)->deleteDocument($post_id);
        } catch (Exception $e) {
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo $e->getMessage(); ?></p>
                    <details>
                        <summary>View Details</summary>
                        <pre><?php echo esc_html(print_r($e, true)); ?></pre>
                    </details>
                </div>
                <?php
            });
        }
    }

    //function to ensure indexes exist in meilisearch for all selected post types
    public function ensure_post_indexes_exist() {
        global $wpdb;

        //ensure  that the meilisearch url and admin key are set
        if (empty(get_option($this->prefixed('meilisearch_url'))) || empty(get_option($this->prefixed('meilisearch_admin_key')))) {
            return;
        }

        //first, we will construct all index names from the post
        //types, wpdb prefixed table name, and the post type name
        $index_names = $this->get_index_names();

        //ensure we handle meielisearch errors correctly
        try {
            //create a meilisearch client
            $client = new Client(
                get_option($this->prefixed('meilisearch_url')), 
                get_option($this->prefixed('meilisearch_admin_key'))
            );

            //now, we will check if an index exists, and if not, we will create it
            foreach ($index_names as $post_type => $index_name) {
                $index = $client->index($index_name);
                //determine if the index exists by trying to fetch it
                try {
                    $index->fetchRawInfo();
                } catch (ApiException $e) {
                    // check that the code is 404
                    if ($e->getCode() === 404) {
                        // Index doesn't exist, create it
                        $client->createIndex($index_name, ['primaryKey' => 'ID']);
                        // Configure searchable attributes for the new index
                        $this->configure_index_searchable_attributes($index);
                    } else {
                        //rethrow the exception
                        throw $e;
                    }
                }
            }
        }
        catch (CommunicationException $e) {
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo $e->getMessage(); ?></p>
                    <details>
                        <summary>View Details</summary>
                        <pre><?php echo esc_html(print_r($e, true)); ?></pre>
                    </details>
                </div>
                <?php
            });
        }
        catch (Exception $e) {
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo $e->getMessage(); ?></p>
                    <details>
                        <summary>View Details</summary>
                        <pre><?php echo esc_html(print_r($e, true)); ?></pre>
                    </details>
                </div>
                <?php
            });
        }
    }

    //register settings (select which post types to index)
    public function register_settings() {
        // Only allow administrators to access these settings
        if (!current_user_can('manage_options')) {
            return;
        }


        //register the indexes settings section
        add_settings_section(
            $this->prefixed('indexes_settings_section'),
            'Indexes',
            function() {
                echo '<p>Configure the indexes for ScryWP Search.</p>';
            },
            $this->prefixed('indexes_settings_group')
        );

        //add the settings fields
        add_settings_field(
            $this->prefixed('post_types'),
            'Post Types to Index',
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/settings/post_types_input.php';
            },
            $this->prefixed('indexes_settings_group'),
            $this->prefixed('indexes_settings_section')
        );

        add_settings_field(
            $this->prefixed('index_affix'),
            'Index Affix',
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/settings/index_affix_input.php';
            },
            $this->prefixed('indexes_settings_group'),
            $this->prefixed('indexes_settings_section')
        );

        // Register settings
        register_setting(
            $this->prefixed('indexes_settings_group'),
            $this->prefixed('post_types'),
            array(
                'type' => 'array',
                'description' => 'Post types to index for ScryWP Search.',
                'sanitize_callback' => function($input) {
                    if (!is_array($input)) {
                        return array();
                    }
                    return array_map('sanitize_text_field', $input);
                },
                'default' => array(),
                'show_in_rest' => false,
            )
        );

        register_setting(
            $this->prefixed('indexes_settings_group'),
            $this->prefixed('index_affix'),
            array(
                'type' => 'string',
                'description' => 'Index affix for the indexes.',
                'sanitize_callback' => function($input) {
                    //ensure input ends in an underscore
                    if (substr($input, -1) !== '_') {
                        $input .= '_';
                    }
                    return sanitize_text_field($input);
                },
                'default' => '',
                'show_in_rest' => false,
            )
        );


    }

    //add an admin page for the indexes
    public function add_admin_page() {
        // Register this page with the admin page feature
        $admin_page_feature = $this->get_feature('scry_ms_admin_page');
        if ($admin_page_feature && method_exists($admin_page_feature, 'register_admin_page')) {
            $admin_page_feature->register_admin_page(
                'scrywp-index-settings',
                __('Index Settings', "scry_search_meilisearch"),
                'dashicons-index-card',
                __('Manage post type indexes, configure indexing settings, and view index status.', "scry_search_meilisearch")
            );
        }
        
        add_submenu_page(
            'scrywp-search',
            'Index Settings',
            'Index Settings',
            'manage_options',
            'scrywp-index-settings',
            function() {
                ob_start();
                require_once plugin_dir_path(__FILE__) . 'elements/_inputs.php';
                $content = ob_get_clean();
                $this->get_feature('scry_ms_admin_page')->render_admin_page($content);
            }
        );
    }
    
    /**
     * AJAX handler for wiping (deleting) a Meilisearch index
     */
    public function ajax_wipe_index() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('wipe_index'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry_search_meilisearch")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry_search_meilisearch")));
            return;
        }
        
        // Get index name from POST data
        $index_name = isset($_POST['index_name']) ? sanitize_text_field($_POST['index_name']) : '';
        
        // Validate index name
        if (empty($index_name)) {
            wp_send_json_error(array('message' => __('Please provide an index name', "scry_search_meilisearch")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', "scry_search_meilisearch")));
            return;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry_search_meilisearch")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $meilisearch_admin_key);
            
            // Get the index and delete it
            $index = $client->index($index_name);
            $index->delete();
            
            // Recreate the index immediately with proper configuration
            $client->createIndex($index_name, ['primaryKey' => 'ID']);
            $this->configure_index_searchable_attributes($index);
            
            // Success - the index has been recreated with proper configuration
            wp_send_json_success(array(
                'message' => sprintf(__('Index "%s" has been wiped and recreated successfully with proper configuration.', "scry_search_meilisearch"), $index_name)
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error (404 if index doesn't exist, etc.)
            if ($e->getCode() === 404) {
                wp_send_json_error(array(
                    'message' => __('Index does not exist', "scry_search_meilisearch")
                ));
            } else {
                wp_send_json_error(array(
                    'message' => sprintf(__('API error: %s', "scry_search_meilisearch"), $e->getMessage())
                ));
            }
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        }
    }
    
    /**
     * AJAX handler for indexing all posts of a specific post type
     */
    public function ajax_index_posts() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('index_posts'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry_search_meilisearch")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry_search_meilisearch")));
            return;
        }
        
        // Get post type from POST data
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        
        // Validate post type
        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('Please provide a post type', "scry_search_meilisearch")));
            return;
        }
        
        // Verify the post type is one of the configured post types (security check)
        $index_names = $this->get_index_names();
        if (!isset($index_names[$post_type])) {
            wp_send_json_error(array('message' => __('Invalid post type', "scry_search_meilisearch")));
            return;
        }
        
        $index_name = $index_names[$post_type];
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry_search_meilisearch")));
            return;
        }
        
        try {
            // Get all posts of this post type that are published
            $posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'publish',
            ));
            
            if (empty($posts)) {
                wp_send_json_error(array('message' => sprintf(__('No posts found for post type "%s"', "scry_search_meilisearch"), $post_type)));
                return;
            }
            
            // Format posts for Meilisearch
            $documents = array();
            foreach ($posts as $post) {
                $documents[] = $this->format_post_for_meilisearch($post);
            }
            
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $meilisearch_admin_key);
            
            // Get the index and add/update documents
            $index = $client->index($index_name);
            $task = $index->updateDocuments($documents);
            
            // Success
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Successfully indexed %d post(s) of type "%s".', "scry_search_meilisearch"),
                    count($documents),
                    $post_type
                ),
                'count' => count($documents),
                'task_uid' => isset($task['taskUid']) ? $task['taskUid'] : null
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error
            wp_send_json_error(array(
                'message' => sprintf(__('API error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        }
    }
    
    /**
     * Format a WordPress post for Meilisearch indexing
     */
    private function format_post_for_meilisearch($post) {
        // Get post content (strip HTML tags and shortcodes)
        $content = wp_strip_all_tags($post->post_content);
        $content = do_shortcode($content);
        
        // Get post excerpt
        $excerpt = !empty($post->post_excerpt) ? wp_strip_all_tags($post->post_excerpt) : wp_trim_words($content, 55);
        
        // Format the document
        $document = array(
            'ID' => (int) $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_date' => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
            'post_modified' => $post->post_modified,
            'post_modified_gmt' => $post->post_modified_gmt,
            'post_status' => $post->post_status,
            'post_type' => $post->post_type,
            'post_author' => (int) $post->post_author,
            'post_name' => $post->post_name,
            'permalink' => get_permalink($post->ID),
        );
        
        // Add author name if available
        $author = get_userdata($post->post_author);
        if ($author) {
            $document['author_name'] = $author->display_name;
        }
        
        // Add categories and tags
        $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
        if (!empty($categories)) {
            $document['categories'] = $categories;
        }
        
        $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
        if (!empty($tags)) {
            $document['tags'] = $tags;
        }
        
        // Add featured image URL if available
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $document['featured_image'] = wp_get_attachment_image_url($thumbnail_id, 'full');
        }

        // Add post meta date
        $post_meta = get_post_meta($post->ID);
        if (!empty($post_meta)) {
            $document['post_meta'] = $post_meta;
        }
        
        return $document;
    }
    
    /**
     * AJAX handler for searching a Meilisearch index
     */
    public function ajax_search_index() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('search_index'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry_search_meilisearch")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry_search_meilisearch")));
            return;
        }
        
        // Get search query and index name from POST data
        $search_query = isset($_POST['search_query']) ? sanitize_text_field($_POST['search_query']) : '';
        $index_name = isset($_POST['index_name']) ? sanitize_text_field($_POST['index_name']) : '';
        
        // Validate inputs
        if (empty($search_query)) {
            wp_send_json_error(array('message' => __('Please provide a search query', "scry_search_meilisearch")));
            return;
        }
        
        if (empty($index_name)) {
            wp_send_json_error(array('message' => __('Please provide an index name', "scry_search_meilisearch")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', "scry_search_meilisearch")));
            return;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_search_key = get_option($this->prefixed('meilisearch_search_key'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry_search_meilisearch")));
            return;
        }
        
        // Use search key if available, otherwise fall back to admin key
        $api_key = !empty($meilisearch_search_key) ? $meilisearch_search_key : $meilisearch_admin_key;
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key not configured', "scry_search_meilisearch")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $api_key);

            
            // Search the index
            $index = $client->index($index_name);
            $search_results = $index->search($search_query, array(
                'limit' => 20, // Limit to 20 results
            ));
            
            $hits = $search_results->getHits();

            if (empty($hits)) {
                wp_send_json_success(array(
                    'results' => array(),
                    'message' => __('No results found', "scry_search_meilisearch")
                ));
                return;
            }

            // Extract post IDs from search results
            // Use array_column if all hits have ID, otherwise use foreach for safety
            $post_ids = array();
            foreach ($hits as $hit) {
                if (isset($hit['ID'])) {
                    $post_ids[] = (int) $hit['ID'];
                }
            }
            
            // Ensure we have unique IDs and they're integers
            $post_ids = array_unique(array_map('intval', $post_ids));
            
            if (empty($post_ids)) {
                wp_send_json_success(array(
                    'results' => array(),
                    'message' => __('No valid post IDs found in results', "scry_search_meilisearch")
                ));
                return;
            }
            
            // Fetch posts from database to ensure up-to-date content
            // Use WP_Query directly for better control
            // Get all registered post types to ensure we find the post
            $post_types = get_option($this->prefixed('post_types'));
            
            
            $query = new \WP_Query(array(
                'post__in' => $post_ids,
                'posts_per_page' => count($post_ids),
                'post_status' => 'publish',
                'post_type' => $post_types, // Use array of all post types
                'orderby' => 'post__in', // Preserve Meilisearch order
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
            ));
            
            $posts = $query->posts;
            wp_reset_postdata();

            // Format results with database content
            $results = array();
            foreach ($posts as $post) {
                $results[] = array(
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'excerpt' => !empty($post->post_excerpt) ? wp_strip_all_tags($post->post_excerpt) : wp_trim_words(wp_strip_all_tags($post->post_content), 30),
                    'permalink' => get_permalink($post->ID),
                    'edit_link' => get_edit_post_link($post->ID, 'raw'),
                    'post_type' => $post->post_type,
                    'post_status' => $post->post_status,
                    'post_date' => $post->post_date,
                );
            }
            
            wp_send_json_success(array(
                'results' => $results,
                'count' => count($results),
                'query' => $search_query
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error
            wp_send_json_error(array(
                'message' => sprintf(__('API error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        }
    }
    
    /**
     * Configure searchable attributes for a Meilisearch index
     * Excludes post_status, post_type, author_name, and featured_image from search
     */
    private function configure_index_searchable_attributes($index) {
        try {
            $searchable_attributes = $this->get_searchable_attributes();
            // Update searchable attributes - Meilisearch PHP SDK v1.x uses updateSearchableAttributes
            $index->updateSearchableAttributes($searchable_attributes);
        } catch (Exception $e) {
            // Log error but don't fail the entire operation
            error_log('ScryWP: Failed to configure searchable attributes: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting index settings (ranking rules and searchable attributes)
     */
    public function ajax_get_index_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('get_index_settings'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry_search_meilisearch")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry_search_meilisearch")));
            return;
        }
        
        // Get index name from POST data
        $index_name = isset($_POST['index_name']) ? sanitize_text_field($_POST['index_name']) : '';
        
        // Validate index name
        if (empty($index_name)) {
            wp_send_json_error(array('message' => __('Please provide an index name', "scry_search_meilisearch")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', "scry_search_meilisearch")));
            return;
        }
        
        // Get post type from index name by inverting the array
        $index_to_post_type = array_flip($index_names);
        $post_type = isset($index_to_post_type[$index_name]) ? $index_to_post_type[$index_name] : null;
        
        if (!$post_type) {
            wp_send_json_error(array('message' => __('Could not determine post type for index', "scry_search_meilisearch")));
            return;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry_search_meilisearch")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $meilisearch_admin_key);
            $index = $client->index($index_name);
            
            // Get current ranking rules
            $ranking_rules = $index->getRankingRules();

            // If empty or null, use defaults
            if (empty($ranking_rules)) {
                $ranking_rules = $this->get_default_ranking_rules();
            }
            
            // Get current searchable attributes
            $searchable_attributes = $index->getSearchableAttributes();
            // If empty or null, use defaults
            if (empty($searchable_attributes)) {
                $searchable_attributes = $this->get_searchable_attributes();
            }
            
            // Get available fields for this post type
            $available_fields = $this->get_available_fields_for_post_type($post_type);
            
            wp_send_json_success(array(
                'ranking_rules' => $ranking_rules,
                'searchable_attributes' => $searchable_attributes,
                'available_fields' => $available_fields,
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            if ($e->getCode() === 404) {
                wp_send_json_error(array(
                    'message' => __('Index does not exist', "scry_search_meilisearch")
                ));
            } else {
                wp_send_json_error(array(
                    'message' => sprintf(__('API error: %s', "scry_search_meilisearch"), $e->getMessage())
                ));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        }
    }
    
    /**
     * AJAX handler for updating index settings (ranking rules and searchable attributes)
     */
    public function ajax_update_index_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('update_index_settings'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry_search_meilisearch")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry_search_meilisearch")));
            return;
        }
        
        // Get index name from POST data
        $index_name = isset($_POST['index_name']) ? sanitize_text_field($_POST['index_name']) : '';
        
        // Validate index name
        if (empty($index_name)) {
            wp_send_json_error(array('message' => __('Please provide an index name', "scry_search_meilisearch")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', "scry_search_meilisearch")));
            return;
        }
        
        // Get ranking rules from POST data (should be array from multi-value form inputs)
        $ranking_rules = isset($_POST['ranking_rules']) ? $_POST['ranking_rules'] : array();
        if (!is_array($ranking_rules)) {
            $ranking_rules = array();
        }
        // Sanitize ranking rules
        $ranking_rules = array_map('sanitize_text_field', $ranking_rules);
        
        // Get searchable attributes from POST data (should be array from multi-value form inputs)
        $searchable_attributes = isset($_POST['searchable_attributes']) ? $_POST['searchable_attributes'] : array();
        if (!is_array($searchable_attributes)) {
            $searchable_attributes = array();
        }
        // Sanitize searchable attributes
        $searchable_attributes = array_map('sanitize_text_field', $searchable_attributes);
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry_search_meilisearch")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $meilisearch_admin_key);
            $index = $client->index($index_name);
            
            // Update ranking rules
            if (!empty($ranking_rules)) {
                $index->updateRankingRules($ranking_rules);
            }
            
            // Update searchable attributes
            if (!empty($searchable_attributes)) {
                $index->updateSearchableAttributes($searchable_attributes);
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('Index settings updated successfully for "%s".', "scry_search_meilisearch"), $index_name)
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            if ($e->getCode() === 404) {
                wp_send_json_error(array(
                    'message' => __('Index does not exist', "scry_search_meilisearch")
                ));
            } else {
                wp_send_json_error(array(
                    'message' => sprintf(__('API error: %s', "scry_search_meilisearch"), $e->getMessage())
                ));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry_search_meilisearch"), $e->getMessage())
            ));
        }
    }
    
    /**
     * Get default Meilisearch ranking rules
     */
    private function get_default_ranking_rules() {
        return array(
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
            'exactness',
        );
    }
    
    /**
     * Get available fields for a post type, including meta keys
     */
    private function get_available_fields_for_post_type($post_type) {
        $fields = array();
        
        // Core post fields
        $core_fields = array(
            'ID' => __('Post ID', "scry_search_meilisearch"),
            'post_title' => __('Title', "scry_search_meilisearch"),
            'post_content' => __('Content', "scry_search_meilisearch"),
            'post_excerpt' => __('Excerpt', "scry_search_meilisearch"),
            'post_date' => __('Post Date', "scry_search_meilisearch"),
            'post_date_gmt' => __('Post Date (GMT)', "scry_search_meilisearch"),
            'post_modified' => __('Modified Date', "scry_search_meilisearch"),
            'post_modified_gmt' => __('Modified Date (GMT)', "scry_search_meilisearch"),
            'post_author' => __('Author ID', "scry_search_meilisearch"),
            'post_name' => __('Post Slug', "scry_search_meilisearch"),
            'permalink' => __('Permalink', "scry_search_meilisearch"),
        );
        
        foreach ($core_fields as $field => $label) {
            $fields[$field] = array(
                'label' => $label,
                'type' => 'core',
                'path' => $field,
            );
        }
        
        // Categories
        $fields['categories'] = array(
            'label' => __('Categories', "scry_search_meilisearch"),
            'type' => 'taxonomy',
            'path' => 'categories',
        );
        
        // Tags
        $fields['tags'] = array(
            'label' => __('Tags', "scry_search_meilisearch"),
            'type' => 'taxonomy',
            'path' => 'tags',
        );
        
        // Featured Image
        $fields['featured_image'] = array(
            'label' => __('Featured Image', "scry_search_meilisearch"),
            'type' => 'media',
            'path' => 'featured_image',
        );
        
        // Author Name
        $fields['author_name'] = array(
            'label' => __('Author Name', "scry_search_meilisearch"),
            'type' => 'meta',
            'path' => 'author_name',
        );
        
        // Post Meta - get all unique meta keys for this post type
        $meta_keys = $this->get_post_meta_keys_for_post_type($post_type);
        
        // Also try to get meta keys from Meilisearch index if available
        $index_meta_keys = $this->get_meta_keys_from_index($post_type);
        if (!empty($index_meta_keys)) {
            // Merge and deduplicate
            $meta_keys = array_unique(array_merge($meta_keys, $index_meta_keys));
        }

        
        if (!empty($meta_keys)) {
            $fields['post_meta'] = array(
                'label' => __('Post Meta', "scry_search_meilisearch"),
                'type' => 'group',
                'path' => 'post_meta',
                'children' => array(),
            );
            
            // Sort meta keys alphabetically
            sort($meta_keys);
            
            foreach ($meta_keys as $meta_key) {
                $fields['post_meta']['children']['post_meta.' . $meta_key] = array(
                    'label' => $meta_key,
                    'type' => 'meta',
                    'path' => 'post_meta.' . $meta_key,
                );
            }
        }
        
        return $fields;
    }
    
    /**
     * Get all unique meta keys for a post type
     * Uses a single composite query with JOIN to reduce database trips
     */
    private function get_post_meta_keys_for_post_type($post_type) {
        global $wpdb;
        
        // Single composite query: JOIN posts and postmeta to get unique meta keys
        // First try with published posts
        $meta_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key 
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND pm.meta_key NOT LIKE %s
                ORDER BY pm.meta_key ASC
                LIMIT 200",
                $post_type,
                $wpdb->esc_like('wp_') . '%'
            )
        );
        
        return $meta_keys ? $meta_keys : array();
    }
    
    /**
     * Get meta keys from Meilisearch index documents
     * This helps discover meta keys that might not be in published posts
     */
    private function get_meta_keys_from_index($post_type) {
        $meta_keys = array();
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            return $meta_keys;
        }
        
        try {
            // Get index name
            $index_names = $this->get_index_names();
            if (!isset($index_names[$post_type])) {
                return $meta_keys;
            }
            
            $index_name = $index_names[$post_type];
            
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $meilisearch_admin_key);
            $index = $client->index($index_name);
            
            // Get a few documents to extract meta keys
            // Use search with wildcard to get documents
            try {
                $results = $index->search('*', array('limit' => 20));
                $hits = $results->getHits();
                
                foreach ($hits as $hit) {
                    if (isset($hit['post_meta']) && is_array($hit['post_meta'])) {
                        foreach (array_keys($hit['post_meta']) as $meta_key) {
                            // Exclude private meta keys
                            if (substr($meta_key, 0, 1) !== '_' && substr($meta_key, 0, 3) !== 'wp_') {
                                $meta_keys[] = $meta_key;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Silently fail - this is just a helper method
                error_log('ScryWP: Failed to get meta keys from index via search: ' . $e->getMessage());
            }
            
        } catch (Exception $e) {
            // Silently fail - this is just a helper method
            error_log('ScryWP: Failed to get meta keys from index: ' . $e->getMessage());
        }
        
        return array_unique($meta_keys);
    }
    
    //  \\  //  \\  //  \\  Helpers  //  \\  //  \\  //  \\ 
    public function get_index_names() {
        global $wpdb;
        $index_names = array();
        $post_types_to_index = get_option($this->prefixed('post_types'));
        foreach ($post_types_to_index as $post_type) {
            $index_names[$post_type] = $wpdb->prefix . $this->get_prefix() . get_option($this->prefixed('index_affix')) . $post_type;
        }   
        return $index_names;
    }
    
    /**
     * Get the list of searchable attributes for Meilisearch indexes
     * Excludes: post_status, post_type, author_name, featured_image
     */
    private function get_searchable_attributes() {
        return array(
            'ID',
            'post_title',
            'post_content',
            'post_excerpt',
            'post_date',
            'post_date_gmt',
            'post_modified',
            'post_modified_gmt',
            'post_author',
            'post_name',
            'permalink',
            'categories',
            'tags',
            'post_meta',
        );
    }
}