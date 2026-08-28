<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\ApiException;

class ScrySearch_IndexesFeature extends PluginFeature {
    
    public function add_filters() {
        // Individual settings are sanitized via register_setting sanitize_callback
    }
    
    public function add_actions() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        //add an admin page for the indexes
        add_action('admin_menu', array($this, 'add_admin_page'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        //ensure indexes exist in meilisearch for all selected post types
        add_action('init', array($this, 'ensure_post_indexes_exist'));

        //register hooks to keep indexes in step
        add_action('save_post', array($this, 'index_post'));
        add_action('wp_trash_post', array($this, 'trash_post'));
        add_action('delete_post', array($this, 'trash_post'));
        add_action('untrash_post', array($this, 'index_post'));
        
        // Register AJAX handlers
        add_action('wp_ajax_' . $this->prefixed('wipe_index'), array($this, 'ajax_wipe_index'));
        add_action('wp_ajax_' . $this->prefixed('index_posts'), array($this, 'ajax_index_posts'));
        add_action('wp_ajax_' . $this->prefixed('search_index'), array($this, 'ajax_search_index'));
        add_action('wp_ajax_' . $this->prefixed('get_index_settings'), array($this, 'ajax_get_index_settings'));
        add_action('wp_ajax_' . $this->prefixed('update_index_settings'), array($this, 'ajax_update_index_settings'));
        // Hybrid embedders: browser POSTs here; PHP talks to Meilisearch (admin key never goes to JS).
        add_action('wp_ajax_' . $this->prefixed('list_embedders'), array($this, 'ajax_list_embedders'));
        add_action('wp_ajax_' . $this->prefixed('save_embedder'), array($this, 'ajax_save_embedder'));
        add_action('wp_ajax_' . $this->prefixed('delete_embedder'), array($this, 'ajax_delete_embedder'));
    }

    //function to index a post when it is created or updated
    public function index_post(int $post_id) {

        //get the post
        $post = get_post($post_id);

        if (!$post) {
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post %d not found. Exiting index_post.', "scry-search"), $post_id));
            return;
        }

        //flag that tells us whether the post should be indexed
        $should_index = true;

        //ensure this post is of a type that should be indexed
        $indexes = $this->get_index_names();

        if (!isset($indexes[$post->post_type])) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post type %s is not indexed. Exiting index_post.', "scry-search"), $post->post_type));
            $should_index = false;
        }

        //ensure the post is published
        if ($post->post_status !== 'publish') {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post %1$d (%2$s) is not published. Exiting index_post.', "scry-search"), $post->ID, $post->post_type));
            $should_index = false;
        }

        //do not index password protected posts
        if (post_password_required($post)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post %1$d (%2$s) is password protected. Exiting index_post.', "scry-search"), $post->ID, $post->post_type));
            $should_index = false;
        }

        //allow other plugins to skip indexing this post
        //@HOOK: scry_ms_should_index
        $should_index = apply_filters($this->config('hook_prefix') . 'should_index', true, $post, 'save');
        if (!$should_index) {
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post %1$d (%2$s) indexing skipped. Exiting index_post.', "scry-search"), $post->ID, $post->post_type));
            return;
        }

        //prepare the post for indexing
        $post_data = $this->format_post_for_meilisearch($post);

        //get the index name for this post type
        $index_name = $indexes[$post->post_type];

        //provide success and error handling
        try {
            //init a meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client();

            //index the post
            $client->index($index_name)->updateDocuments($post_data);

            //@HOOK: scry_ms_after_index_document
            do_action($this->config('hook_prefix') . 'after_index_document', $post->ID, $index_name, $post_data);
        } catch (Exception $e) {
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Error indexing post %1$d (%2$s): %3$s', "scry-search"), $post->ID, $post->post_type, $e->getMessage()));
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($e->getMessage()); ?></p>
                    <details>
                        <summary>View Details</summary>
                        <pre><?php echo esc_html(print_r($e, true)); ?></pre>
                    </details>
                </div>
                <?php
            });
        }
    }

    //function to delete a post from the index when it is trashed or permanently deleted
    public function trash_post(int $post_id) {
        //get the post
        $post = get_post($post_id);

        if (!$post) {
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post %d not found. Exiting trash_post.', "scry-search"), $post_id));
            return;
        }

        //ensure this post is of a type that should be indexed
        $indexes = $this->get_index_names();
        if (!isset($indexes[$post->post_type])) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post type %s is not indexed. Exiting trash_post.', "scry-search"), $post->post_type));
            return;
        }

        //get the index name for this post type
        $index_name = $indexes[$post->post_type];

        //allow other plugins to skip deleting this document
        //@HOOK: scry_ms_should_delete
        $should_delete = apply_filters($this->config('hook_prefix') . 'should_delete', true, $post_id, $index_name);
        if (!$should_delete) {
            $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Post %1$d (%2$s) skipped by should_delete filter. Exiting trash_post.', "scry-search"), $post_id, $post->post_type));
            return;
        }

        //provide success and error handling
        try {
            //init a meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client();

            //delete the post from the index
            $client->index($index_name)->deleteDocument($post_id);

            //@HOOK: scry_ms_after_delete_document
            do_action($this->config('hook_prefix') . 'after_delete_document', $post_id, $index_name);
        } catch (Exception $e) {
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Error deleting post %1$d (%2$s) from index: %3$s', "scry-search"), $post->ID, $post->post_type, $e->getMessage()));
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($e->getMessage()); ?></p>
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
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Meilisearch URL or admin key is not set. Exiting ensure_post_indexes_exist.', "scry-search"));
            return;
        }

        //first, we will construct all index names from the post
        //types, wpdb prefixed table name, and the post type name
        $index_names = $this->get_index_names();

        //ensure we handle meielisearch errors correctly
        try {
            //create a meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client();

            //now, we will check if an index exists, and if not, we will create it
            foreach ($index_names as $post_type => $index_name) {
                $index = $client->index($index_name);
                $created = false;

                //determine if the index exists by trying to fetch it
                try {
                    $index->fetchRawInfo();
                } catch (ApiException $e) {
                    // check that the code is 404
                    if ($e->getCode() === 404) {
                        // Index doesn't exist, create it
                        $client->createIndex($index_name, ['primaryKey' => 'ID']);
                        $created = true;
                        //log a debug message with the logging feature
                        $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Index created: %s', "scry-search"), $index_name));
                    } else {
                        //rethrow the exception
                        throw $e;
                    }
                }

                // only restore settings on a freshly created index. continue skips the rest of
                // this foreach item (other indexes still run). Re-PATCHing embedders on every
                // init would enqueue Meili tasks forever and stick the UI on "Indexing...".
                if (!$created) {
                    continue;
                }

                //if the index was just created, check if we have a backup of the settings, and if so, restore them
                $index_settings_backup_key = $this->prefixed('index_settings_backup_') . $index_name;
                $index_settings_backup = get_option($index_settings_backup_key);

                try{
                    //restore the ranking rules if they are in the backup
                    if (isset($index_settings_backup['ranking_rules'])) {
                        $index->updateRankingRules($index_settings_backup['ranking_rules']);
                    }

                    //restore the searchable attributes if they are in the backup
                    if (isset($index_settings_backup['searchable_attributes'])) {
                        $index->updateSearchableAttributes($index_settings_backup['searchable_attributes']);
                    }

                    //restore synonyms if they are in the backup
                    if (isset($index_settings_backup['synonyms']) && is_array($index_settings_backup['synonyms'])) {
                        $index->updateSynonyms($index_settings_backup['synonyms']);
                    }

                    //restore stop words if they are in the backup
                    if (isset($index_settings_backup['stop_words']) && is_array($index_settings_backup['stop_words'])) {
                        $index->updateStopWords($index_settings_backup['stop_words']);
                    }

                    //restore filterable attributes if they are in the backup
                    if (isset($index_settings_backup['filterable_attributes']) && is_array($index_settings_backup['filterable_attributes'])) {
                        $index->updateFilterableAttributes($index_settings_backup['filterable_attributes']);
                    }

                    //restore dictionary if they are in the backup
                    if (isset($index_settings_backup['dictionary']) && is_array($index_settings_backup['dictionary'])) {
                        $index->updateDictionary($index_settings_backup['dictionary']);
                    }

                    //restore typo tolerance if it is in the backup
                    if (isset($index_settings_backup['typo_tolerance']) && is_array($index_settings_backup['typo_tolerance'])) {
                        $index->updateTypoTolerance($index_settings_backup['typo_tolerance']);
                    }

                    // Embedder defs (source, model, url, apiKey). Only here — $created is true.
                    // continue above skips this on every other page load so Meili is not PATCHed forever.
                    $this->restore_embedders_from_backup($index_name, $index_settings_backup);

                    //hook to allow other plugins to act after the index settings are restored
                    do_action($this->config('hook_prefix') . 'index_settings_restore', $index, $index_settings_backup);

                } catch (Exception $e) {
                    //throw the exception
                    throw $e;
                }

                //hook to allow other plugsin to configure the index immediately after it is created
                do_action($this->config('hook_prefix') . 'after_create_index', $index);
            }
        }
        catch (CommunicationException $e) {
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('ensure_post_indexes_exist connection failed: %s', "scry-search"), $e->getMessage()));
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($e->getMessage()); ?></p>
                    <details>
                        <summary>View Details</summary>
                        <pre><?php echo esc_html(print_r($e, true)); ?></pre>
                    </details>
                </div>
                <?php
            });
        }
        catch (Exception $e) {
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('ensure_post_indexes_exist failed: %s', "scry-search"), $e->getMessage()));
            //report the exception with an admin notice, including a summary/details dropdown with the full stack trace
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($e->getMessage()); ?></p>
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
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('User does not have manage_options permission. Exiting register_settings.', "scry-search"));
            return;
        }


        //register the indexes settings section
        add_settings_section(
            $this->prefixed('indexes_settings_section'),
            'Indexes',
            function() {
                echo '<p>Configure the indexes for Scry Search for Meilisearch.</p>';
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
                'description' => 'Post types to index for Scry Search for Meilisearch.',
                'sanitize_callback' => function($input) {
                    //ensure input is an array
                    if (!is_array($input)) {
                        return array();
                    }

                    //get all post types registered in wordpress
                    $post_types = get_post_types(array(), 'names');

                    //remove any post types that are not registered in wordpress
                    $input = array_intersect($input, $post_types);

                    //sanitize the input
                    $input = array_map('sanitize_text_field', $input);

                    //return the input
                    return $input;
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
                    //ensure input ends in an underscore, if it is not 0 length
                    if (!empty($input) && substr($input, -1) !== '_') {
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
                'scry-search-meilisearch-index-settings',
                __('Index Settings', "scry-search"),
                'dashicons-index-card',
                __('Manage post type indexes, configure indexing settings, and view index status.', "scry-search")
            );
        }
        
        add_submenu_page(
            'scry-search-meilisearch',
            'Index Settings',
            'Index Settings',
            'manage_options',
            'scry-search-meilisearch-index-settings',
            function() {
                $file_path = plugin_dir_path(__FILE__) . 'elements/_inputs.php';
                $this->get_feature('scry_ms_admin_page')->render_admin_page($file_path);
            }
        );
    }

    /**
     * Enqueue admin assets for the indexes page
     */
    public function enqueue_admin_assets($hook) {
        // Only load assets on the indexes settings page
        // Hook format: {parent-slug}_page_{submenu-slug}
        if ($hook !== 'scry-search_page_scry-search-meilisearch-index-settings') {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            $this->prefixed('show-indexes-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/show_indexes.css',
            array(),
            '1.0.0'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            $this->prefixed('show-indexes-script'),
            plugin_dir_url(__FILE__) . 'assets/js/show_indexes.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script with AJAX URL, actions, nonces, and i18n strings
        wp_localize_script(
            $this->prefixed('show-indexes-script'),
            'scrywpIndexes',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'actions' => array(
                    'indexPosts' => $this->prefixed('index_posts'),
                    'wipeIndex' => $this->prefixed('wipe_index'),
                    'searchIndex' => $this->prefixed('search_index'),
                    'getIndexSettings' => $this->prefixed('get_index_settings'),
                    'updateIndexSettings' => $this->prefixed('update_index_settings'),
                ),
                'nonces' => array(
                    'indexPosts' => wp_create_nonce($this->prefixed('index_posts')),
                    'wipeIndex' => wp_create_nonce($this->prefixed('wipe_index')),
                    'searchIndex' => wp_create_nonce($this->prefixed('search_index')),
                    'getIndexSettings' => wp_create_nonce($this->prefixed('get_index_settings')),
                    'updateIndexSettings' => wp_create_nonce($this->prefixed('update_index_settings')),
                ),
                'i18n' => array(
                    'indexing' => __('Indexing...', "scry-search"),
                    'indexingAll' => __('Indexing All...', "scry-search"),
                    'wiping' => __('Wiping...', "scry-search"),
                    'saving' => __('Saving...', "scry-search"),
                    'error' => __('Error:', "scry-search"),
                    'postsIndexedSuccessfully' => __('Posts indexed successfully', "scry-search"),
                    'failedToIndexPosts' => __('Failed to index posts', "scry-search"),
                    'failedToIndex' => __('Failed to index', "scry-search"),
                    'noValidIndexesToIndex' => __('No valid indexes to index.', "scry-search"),
                    'allPostTypesIndexedSuccessfully' => __('All post types have been indexed successfully.', "scry-search"),
                    'indexWipedSuccessfully' => __('Index wiped successfully', "scry-search"),
                    'failedToWipeIndex' => __('Failed to wipe index', "scry-search"),
                    'enterSearchQuery' => __('Enter a search query above to search the index.', "scry-search"),
                    'searching' => __('Searching...', "scry-search"),
                    'noResultsFound' => __('No results found.', "scry-search"),
                    'viewPost' => __('View Post', "scry-search"),
                    'editPost' => __('Edit Post', "scry-search"),
                    'untitled' => __('Untitled', "scry-search"),
                    'viewRawJson' => __('View Raw JSON', "scry-search"),
                    'searchFailed' => __('Search failed', "scry-search"),
                    'errorFailedToSearchIndex' => __('Error: Failed to search index', "scry-search"),
                    'saveSettings' => __('Save Settings', "scry-search"),
                    'failedToLoadSettings' => __('Failed to load settings', "scry-search"),
                    'errorFailedToLoadSettings' => __('Error: Failed to load settings', "scry-search"),
                    'dragToReorder' => __('Drag to reorder', "scry-search"),
                    'expand' => __('Expand', "scry-search"),
                    'removeCustomRankingRule' => __('Remove custom ranking rule', "scry-search"),
                    'customRankingAttributeRequired' => __('Please enter an attribute name.', "scry-search"),
                    'customRankingAttributeInvalid' => __('Attribute names can only contain letters, numbers, underscores, dots, slashes, and hyphens.', "scry-search"),
                    'customRankingRuleExists' => __('That custom ranking rule is already in the list.', "scry-search"),
                    'settingsSavedSuccessfully' => __('Settings saved successfully', "scry-search"),
                    'failedToSaveSettings' => __('Failed to save settings', "scry-search"),
                ),
            )
        );

        // Hybrid CRUD UI on Configure Index (this index only). filemtime busts the browser cache.
        wp_enqueue_style(
            $this->prefixed('hybrid-embedders-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/hybrid_embedders.css',
            array($this->prefixed('show-indexes-styles')),
            (string) filemtime(plugin_dir_path(__FILE__) . 'assets/css/hybrid_embedders.css')
        );

        wp_enqueue_script(
            $this->prefixed('hybrid-embedders-script'),
            plugin_dir_url(__FILE__) . 'assets/js/hybrid_embedders.js',
            array(),
            (string) filemtime(plugin_dir_path(__FILE__) . 'assets/js/hybrid_embedders.js'),
            true
        );

        wp_localize_script(
            $this->prefixed('hybrid-embedders-script'),
            'scrywpHybridEmbedders', // JS global: ajaxUrl, actions, nonces, i18n (translated strings).
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'actions' => array(
                    'list' => $this->prefixed('list_embedders'),
                    'save' => $this->prefixed('save_embedder'),
                    'delete' => $this->prefixed('delete_embedder'),
                ),
                'nonces' => array(
                    'list' => wp_create_nonce($this->prefixed('list_embedders')),
                    'save' => wp_create_nonce($this->prefixed('save_embedder')),
                    'delete' => wp_create_nonce($this->prefixed('delete_embedder')),
                ),
                'i18n' => array(
                    'loading' => __('Loading embedders…', "scry-search"),
                    'none' => __('No embedders on this index yet.', "scry-search"),
                    'loadFailed' => __('Failed to load embedders.', "scry-search"),
                    'hasKey' => __('API key set', "scry-search"),
                    'noKey' => __('No API key', "scry-search"),
                    'selectEmbedder' => __('— Select an embedder —', "scry-search"),
                    'edit' => __('Edit', "scry-search"),
                    'delete' => __('Delete', "scry-search"),
                    'deleting' => __('Deleting…', "scry-search"),
                    'confirmDelete' => __('Delete this embedder from this index? Other indexes are not changed.', "scry-search"),
                    'deleteFailed' => __('Failed to delete embedder.', "scry-search"),
                    'saving' => __('Saving…', "scry-search"),
                    'saveEmbedder' => __('Save embedder', "scry-search"),
                    'saveFailed' => __('Failed to save embedder.', "scry-search"),
                    'nameRequired' => __('Embedder name is required (letters, numbers, _ or -).', "scry-search"),
                    'keepKey' => __('API key set — leave blank to keep it.', "scry-search"),
                    'saveTimeout' => __('Save timed out waiting for Meilisearch. If Meilisearch is in Docker, Ollama often needs host.docker.internal.', "scry-search"),
                ),
            )
        );
    }
    
    /**
     * AJAX handler for wiping (deleting) a Meilisearch index
     */
    public function ajax_wipe_index() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('wipe_index'))) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Security check failed. Exiting ajax_wipe_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Permission denied. Exiting ajax_wipe_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }
        
        // Get index name from POST data
        $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
        
        // Validate index name
        if (empty($index_name)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Index name is empty. Exiting ajax_wipe_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Please provide an index name', "scry-search")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Invalid index name. Exiting ajax_wipe_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Invalid index name', "scry-search")));
            return;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Connection settings are not configured. Exiting ajax_wipe_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry-search")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client();
            
            // Get the index and delete it
            $index = $client->index($index_name);
            $index->delete();
            
            // call index init logic
            $this->ensure_post_indexes_exist();
            
            // Success - the index has been recreated with proper configuration
            wp_send_json_success(array(
                'message' => sprintf(__('Index "%s" has been wiped and recreated successfully with proper configuration.', "scry-search"), $index_name)
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error (404 if index doesn't exist, etc.)
            if ($e->getCode() === 404) {
                //log an error message with the logging feature
                $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Index does not exist: %s', "scry-search"), $e->getMessage()));
                wp_send_json_error(array(
                    'message' => __('Index does not exist', "scry-search")
                ));
            } else {
                //log an error message with the logging feature
                $this->get_feature('scry_ms_logs')->log('error', sprintf(__('API error: %s', "scry-search"), $e->getMessage()));
                wp_send_json_error(array(
                    'message' => sprintf(__('API error: %s', "scry-search"), $e->getMessage())
                ));
            }
        } catch (\Exception $e) {
            // General error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('ajax_wipe_index failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry-search"), $e->getMessage())
            ));
        }
    }
    
    /**
     * AJAX handler for indexing all posts of a specific post type
     */
    public function ajax_index_posts() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('index_posts'))) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Security check failed. Exiting ajax_index_posts.', "scry-search"));
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Permission denied. Exiting ajax_index_posts.', "scry-search"));
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }
        
        // Get post type from POST data
        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
        
        // Validate post type
        if (empty($post_type)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Post type is empty. Exiting ajax_index_posts.', "scry-search"));
            wp_send_json_error(array('message' => __('Please provide a post type', "scry-search")));
            return;
        }
        
        // Verify the post type is one of the configured post types (security check)
        $index_names = $this->get_index_names();
        if (!isset($index_names[$post_type])) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Invalid post type. Exiting ajax_index_posts.', "scry-search"));
            wp_send_json_error(array('message' => __('Invalid post type', "scry-search")));
            return;
        }
        
        $index_name = $index_names[$post_type];
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Connection settings are not configured. Exiting ajax_index_posts.', "scry-search"));
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry-search")));
            return;
        }
        
        try {
            // Process posts in batches to avoid loading everything into memory at once
            $batch_size = 100;
            //@HOOK: scry_ms_bulk_index_batch_size
            $batch_size = (int) apply_filters($this->config('hook_prefix') . 'bulk_index_batch_size', $batch_size, $post_type, $index_name);
            if ($batch_size < 1) {
                $batch_size = 100;
            }

            //query vars
            $offset = 0;
            $total_indexed = 0;
            $found_any_posts = false;
            $task = null;

            //get a meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client();
            $index = $client->index($index_name);

            //begin indexing in batches
            while (true) {
                //build the query args for the batch
                $query_args = array(
                    'post_type' => $post_type,
                    'posts_per_page' => $batch_size,
                    'offset' => $offset,
                    'post_status' => 'publish',
                    'orderby' => 'ID',
                    'order' => 'ASC',
                    'no_found_rows' => true,
                );

                //@HOOK: scry_ms_bulk_index_query_args
                $query_args = apply_filters($this->config('hook_prefix') . 'bulk_index_query_args', $query_args, $post_type, $index_name);

                // Keep batching intact even if a filter changes page size/offset
                $query_args['posts_per_page'] = $batch_size;
                $query_args['offset'] = $offset;

                //get the posts for the batch
                $posts = get_posts($query_args);
                $post_count = count($posts);

                //if no posts found, break the loop
                if ($post_count === 0) {
                    break;
                }

                //set the flag that we found at least one post
                $found_any_posts = true;

                //build the documents array
                $documents = array();
                foreach ($posts as $post) {
                    //test if the post should be indexed
                    //@HOOK: scry_ms_should_index
                    $should_index = apply_filters($this->config('hook_prefix') . 'should_index', true, $post, 'bulk');
                    if (!$should_index) {
                        continue;
                    }
                    //format the post for Meilisearch indexing
                    $documents[] = $this->format_post_for_meilisearch($post);
                }

                //unset the posts array to free up memory
                unset($posts);

                //if there are documents to index, index them
                if (!empty($documents)) {
                    $task = $index->updateDocuments($documents);
                    $total_indexed += count($documents);
                }

                //unset the documents array to free up memory
                unset($documents);

                //if we didn't index all the posts in the batch, break the loop
                if ($post_count < $batch_size) {
                    break;
                }

                //increment the offset
                $offset += $batch_size;
            }

            //if no posts were found, log an error and exit
            if (!$found_any_posts) {
                //log a debug message with the logging feature
                $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('No posts found for post type "%s". Exiting ajax_index_posts.', "scry-search"), $post_type));
                wp_send_json_error(array('message' => sprintf(__('No posts found for post type "%s"', "scry-search"), $post_type)));
                return;
            }

            //if no documents were indexed, log an error and exit
            if ($total_indexed === 0) {
                $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('No documents to index for post type "%s" after should_index filter. Exiting ajax_index_posts.', "scry-search"), $post_type));
                wp_send_json_error(array('message' => sprintf(__('No documents to index for post type "%s"', "scry-search"), $post_type)));
                return;
            }

            //@HOOK: scry_ms_after_bulk_index
            do_action($this->config('hook_prefix') . 'after_bulk_index', $post_type, $index_name, $total_indexed, $task);
            
            // Success
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Successfully indexed %d post(s) of type "%s".', "scry-search"),
                    $total_indexed,
                    $post_type
                ),
                'count' => $total_indexed,
                'task_uid' => (is_array($task) && isset($task['taskUid'])) ? $task['taskUid'] : null
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('API error: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('API error: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('ajax_index_posts failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry-search"), $e->getMessage())
            ));
        }
    }
    
    /**
     * Format a WordPress post for Meilisearch indexing
     */
    private function format_post_for_meilisearch($post) {
        // Get post content (strip HTML tags and shortcodes)
        $content = do_shortcode($post->post_content);
        $content = wp_strip_all_tags($content);
        
        // Get post excerpt
        $excerpt = !empty($post->post_excerpt) ? wp_strip_all_tags($post->post_excerpt) : wp_trim_words($content, 55);

        // Format the document
        $document = array(
            'ID' => (int) $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_date' => $post->post_date,
            'post_date_unix' => strtotime($post->post_date),
            'post_date_gmt' => $post->post_date_gmt,
            'post_modified' => $post->post_modified,
            'post_modified_gmt' => $post->post_modified_gmt,
            'post_modified_unix' => strtotime($post->post_modified),
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
        
        // Add featured image URL if available
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $document['featured_image'] = wp_get_attachment_image_url($thumbnail_id, 'full');
        }

        // Add post meta data
        $post_meta = get_post_meta($post->ID);
        if (!empty($post_meta)) {
            $document['post_meta'] = $post_meta;
        }

        //handle taxonomies here: we want to get all taxonomies the post has, and save them under
        // ['taxonomies']=>[taxonomy_name], with values id, slug, name, and taxonomy set
        $post_id = $post->ID;
        $post_taxonomies = get_object_taxonomies(get_post_type($post_id));
        if (!empty($post_taxonomies)) {
            $terms = wp_get_object_terms($post_id, $post_taxonomies);
            if (!is_wp_error($terms) && !empty($terms)) {
                $excluded_taxonomies = (array) $this->config('excluded_taxonomies');
                foreach ($terms as $term) {
                    if (!isset($term->taxonomy) || in_array($term->taxonomy, $excluded_taxonomies, true)) {
                        continue;
                    }


                    $taxonomy_key = $term->taxonomy;
                    if (!isset($document['taxonomies'][$taxonomy_key])) {
                        $document['taxonomies'][$taxonomy_key] = array();
                    }

                    $document['taxonomies'][$taxonomy_key][] = array(
                        'id' => (int) $term->term_id,
                        'slug' => (string) $term->slug,
                        'name' => (string) $term->name,
                        'taxonomy' => (string) $term->taxonomy,
                        'parent' => (int) $term->parent,
                    );
                }
            }
        }
        
        //let other plugins modify the document before it is indexed
        //@HOOK: scry_ms_index_prepare_document
        $document = apply_filters($this->config('hook_prefix') . 'index_prepare_document', $document, $post);
        
        return $document;
    }
    
    /**
     * AJAX handler for searching a Meilisearch index
     */
    public function ajax_search_index() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('search_index'))) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Security check failed. Exiting ajax_search_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Permission denied. Exiting ajax_search_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }
        
        // Get search query and index name from POST data
        $search_query = isset($_POST['search_query']) ? sanitize_text_field(wp_unslash($_POST['search_query'])) : '';
        $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
        
        // Validate inputs
        if (empty($search_query)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Search query is empty. Exiting ajax_search_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Please provide a search query', "scry-search")));
            return;
        }
        
        if (empty($index_name)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Index name is empty. Exiting ajax_search_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Please provide an index name', "scry-search")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Invalid index name. Exiting ajax_search_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Invalid index name', "scry-search")));
            return;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_search_key = get_option($this->prefixed('meilisearch_search_key'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Connection settings are not configured. Exiting ajax_search_index.', "scry-search"));
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry-search")));
            return;
        }
        
        // Use search key if available, otherwise fall back to admin key
        $api_key = !empty($meilisearch_search_key) ? $meilisearch_search_key : $meilisearch_admin_key;
        
        if (empty($api_key)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('API key not configured. Exiting ajax_search_index.', "scry-search"));
            wp_send_json_error(array('message' => __('API key not configured', "scry-search")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client('search');

            
            // Search the index
            $index = $client->index($index_name);
            $search_results = $index->search($search_query, array(
                'limit' => 20, // Limit to 20 results
            ));
            
            $hits = $search_results->getHits();

            if (empty($hits)) {
                //log a debug message with the logging feature
                $this->get_feature('scry_ms_logs')->log('debug', __('No results found. Exiting ajax_search_index.', "scry-search"));
                wp_send_json_success(array(
                    'results' => array(),
                    'message' => __('No results found', "scry-search")
                ));
                return;
            }

            // Format results directly from Meilisearch hits.
            // Permalink/edit link are still resolved through WordPress using the hit ID.
            $sanitize_hit_value = function ($value) use (&$sanitize_hit_value) {
                if (is_array($value)) {
                    $sanitized = array();
                    foreach ($value as $key => $nested_value) {
                        $sanitized[$key] = $sanitize_hit_value($nested_value);
                    }
                    return $sanitized;
                }
                if (is_int($value) || is_float($value) || is_bool($value) || is_null($value)) {
                    return $value;
                }

                return sanitize_text_field(wp_strip_all_tags((string) $value));
            };

            $results = array();
            foreach ($hits as $hit) {
                if (!is_array($hit)) {
                    continue;
                }

                $post_id = isset($hit['ID']) ? (int) $hit['ID'] : 0;
                $permalink = get_permalink($post_id);
                $edit_link = get_edit_post_link($post_id, 'raw');

                // Return the full Meilisearch document payload (sanitized),
                // then override links from WordPress.
                $result = $sanitize_hit_value($hit);
                $result['permalink'] = is_string($permalink) ? esc_url_raw($permalink) : '';
                $result['edit_link'] = is_string($edit_link) ? esc_url_raw($edit_link) : '';

                $results[] = $result;
            }
            
            wp_send_json_success(array(
                'results' => $results,
                'count' => count($results),
                'query' => $search_query
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('API error: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('API error: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Exception $e) {
            // General error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('ajax_search_index failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry-search"), $e->getMessage())
            ));
        }
    }

    
    /**
     * AJAX handler for getting index settings (ranking rules and searchable attributes)
     */
    public function ajax_get_index_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('get_index_settings'))) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Security check failed. Exiting ajax_get_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Permission denied. Exiting ajax_get_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }
        
        // Get index name from POST data
        $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
        
        // Validate index name
        if (empty($index_name)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Index name is empty. Exiting ajax_get_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Please provide an index name', "scry-search")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Invalid index name. Exiting ajax_get_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Invalid index name', "scry-search")));
            return;
        }
        
        // Get post type from index name by inverting the array
        $index_to_post_type = array_flip($index_names);
        $post_type = isset($index_to_post_type[$index_name]) ? $index_to_post_type[$index_name] : null;
        
        if (!$post_type) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Could not determine post type for index. Exiting ajax_get_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Could not determine post type for index', "scry-search")));
            return;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Connection settings are not configured. Exiting ajax_get_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry-search")));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client();
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

            // Get current synonyms (mapping: word => [synonyms])
            $synonyms = array();
            try {
                $fetched_synonyms = $index->getSynonyms();
                if (is_array($fetched_synonyms)) {
                    $synonyms = $fetched_synonyms;
                }
            } catch (\Exception $e) {
                // If synonyms fetch fails, return empty so UI remains usable.
                //log an debug message with the logging feature
                $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Synonyms fetch failed: %s', "scry-search"), $e->getMessage()));
                $synonyms = array();
            }

            // Get current stop words (flat array)
            $stop_words = array();
            try {
                $fetched_stop_words = $index->getStopWords();
                if (is_array($fetched_stop_words)) {
                    $stop_words = $fetched_stop_words;
                }
            } catch (\Exception $e) {
                // If stop words fetch fails, return empty so UI remains usable.
                //log an debug message with the logging feature
                $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Stop words fetch failed: %s', "scry-search"), $e->getMessage()));
                $stop_words = array();
            }

            // Get current filterable attributes
            $filterable_attributes = array();
            try {
                $fetched_filterable_attributes = $index->getFilterableAttributes();
                if (is_array($fetched_filterable_attributes) && !empty($fetched_filterable_attributes)) {
                    $filterable_attributes = $fetched_filterable_attributes;
                } else {
                    $filterable_attributes = $this->get_default_filterable_attributes();
                }
            } catch (\Exception $e) {
                $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Filterable attributes fetch failed: %s', "scry-search"), $e->getMessage()));
                $filterable_attributes = $this->get_default_filterable_attributes();
            }

            // Get current dictionary (flat array of words)
            $dictionary = array();
            try {
                $fetched_dictionary = $index->getDictionary();
                if (is_array($fetched_dictionary)) {
                    $dictionary = $fetched_dictionary;
                }
            } catch (\Exception $e) {
                $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Dictionary fetch failed: %s', "scry-search"), $e->getMessage()));
                $dictionary = array();
            }

            // Get current typo tolerance settings
            $typo_tolerance = $this->get_default_typo_tolerance();
            try {
                $fetched_typo_tolerance = $index->getTypoTolerance();
                if (is_array($fetched_typo_tolerance) && !empty($fetched_typo_tolerance)) {
                    $typo_tolerance = array_merge($typo_tolerance, $fetched_typo_tolerance);
                    if (isset($fetched_typo_tolerance['minWordSizeForTypos']) && is_array($fetched_typo_tolerance['minWordSizeForTypos'])) {
                        $typo_tolerance['minWordSizeForTypos'] = array_merge(
                            $this->get_default_typo_tolerance()['minWordSizeForTypos'],
                            $fetched_typo_tolerance['minWordSizeForTypos']
                        );
                    }
                }
            } catch (\Exception $e) {
                $this->get_feature('scry_ms_logs')->log('debug', sprintf(__('Typo tolerance fetch failed: %s', "scry-search"), $e->getMessage()));
                $typo_tolerance = $this->get_default_typo_tolerance();
            }

            // Get available fields for this post type
            $available_fields = $this->get_available_fields_for_post_type($post_type);
            $available_filterable_fields = $this->get_available_filterable_fields_for_post_type($post_type);
            $searchable_attributes = $this->resolve_searchable_attributes_for_ui($searchable_attributes, $available_fields);

            //create the return array
            $return_array = array(
                'ranking_rules' => $ranking_rules,
                'searchable_attributes' => $searchable_attributes,
                'available_fields' => $available_fields,
                'filterable_attributes' => $filterable_attributes,
                'available_filterable_fields' => $available_filterable_fields,
                'synonyms' => $synonyms,
                'stop_words' => $stop_words,
                'dictionary' => $dictionary,
                'typo_tolerance' => $typo_tolerance,
            );

            //let other plugins add entries to the return array
            //@HOOK: scry_ms_index_settings_ajax — args: $return_array, $index_name
            $return_array = apply_filters($this->config('hook_prefix') . 'index_settings_ajax', $return_array, $index_name);
            wp_send_json_success($return_array);
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            if ($e->getCode() === 404) {
                //log an error message with the logging feature
                $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Index does not exist: %s', "scry-search"), $e->getMessage()));
                wp_send_json_error(array(
                    'message' => __('Index does not exist', "scry-search")
                ));
            } else {
                //log an error message with the logging feature
                $this->get_feature('scry_ms_logs')->log('error', sprintf(__('API error: %s', "scry-search"), $e->getMessage()));
                wp_send_json_error(array(
                    'message' => sprintf(__('API error: %s', "scry-search"), $e->getMessage())
                ));
            }
        } catch (\Exception $e) {
            // General error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('ajax_get_index_settings failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry-search"), $e->getMessage())
            ));
        }
    }
    
    /**
     * AJAX handler for updating index settings (ranking rules and searchable attributes)
     */
    public function ajax_update_index_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('update_index_settings'))) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Security check failed. Exiting ajax_update_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Permission denied. Exiting ajax_update_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }
        
        // Get index name from POST data
        $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
        
        // Validate index name
        if (empty($index_name)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Index name is empty. Exiting ajax_update_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Please provide an index name', "scry-search")));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            //log a debug message with the logging feature
            $this->get_feature('scry_ms_logs')->log('debug', __('Invalid index name. Exiting ajax_update_index_settings.', "scry-search"));
            wp_send_json_error(array('message' => __('Invalid index name', "scry-search")));
            return;
        }
        
        // Get ranking rules from POST data (should be array from multi-value form inputs)
        $ranking_rules = isset($_POST['ranking_rules']) ? wp_unslash($_POST['ranking_rules']) : array();
        if (!is_array($ranking_rules)) {
            $ranking_rules = array();
        }
        // Sanitize ranking rules
        $ranking_rules = array_map('sanitize_text_field', $ranking_rules);
        
        // Get searchable attributes from POST data (should be array from multi-value form inputs)
        $searchable_attributes = isset($_POST['searchable_attributes']) ? wp_unslash($_POST['searchable_attributes']) : array();
        if (!is_array($searchable_attributes)) {
            $searchable_attributes = array();
        }
        // Sanitize searchable attributes
        $searchable_attributes = array_map('sanitize_text_field', $searchable_attributes);
        $searchable_attributes = array_values(array_unique(array_filter($searchable_attributes, function ($attr) {
            return is_string($attr) && $attr !== '';
        })));

        // Synonyms are submitted as synonyms[base][] = synonym terms (always applied on save; empty clears).
        $synonyms = array();
        $raw_synonyms = isset($_POST['synonyms']) ? wp_unslash($_POST['synonyms']) : array();
        if (is_array($raw_synonyms)) {
            foreach ($raw_synonyms as $base => $values) {
                $base_sanitized = sanitize_text_field((string) $base);
                $base_sanitized = trim($base_sanitized);
                if ($base_sanitized === '') {
                    continue;
                }

                if (!is_array($values)) {
                    $values = array($values);
                }

                $sanitized_values = array();
                foreach ($values as $value) {
                    $term = is_string($value) ? trim(sanitize_text_field($value)) : '';
                    if ($term === '') {
                        continue;
                    }
                    $sanitized_values[] = $term;
                }

                $sanitized_values = array_values(array_unique($sanitized_values));
                if ($sanitized_values !== array()) {
                    $synonyms[$base_sanitized] = $sanitized_values;
                }
            }
        }

        // Stop words are submitted as stop_words[] (always applied on save; empty clears).
        $stop_words = array();
        $raw_stop_words = isset($_POST['stop_words']) ? wp_unslash($_POST['stop_words']) : array();
        //ensure the stop words are an array
        if (!is_array($raw_stop_words)) {
            $raw_stop_words = array($raw_stop_words);
        }
        //sanitize the stop words
        foreach ($raw_stop_words as $word) {
            $term = is_string($word) ? trim(sanitize_text_field($word)) : '';
            if ($term === '') {
                continue;
            }
            $stop_words[] = $term;
        }
        //ensure the stop words are unique
        $stop_words = array_values(array_unique($stop_words));

        // Filterable attributes are submitted as filterable_attributes[] (always applied on save; empty clears).
        $filterable_attributes = isset($_POST['filterable_attributes']) ? wp_unslash($_POST['filterable_attributes']) : array();
        if (!is_array($filterable_attributes)) {
            $filterable_attributes = array();
        }
        $filterable_attributes = array_map('sanitize_text_field', $filterable_attributes);
        $filterable_attributes = array_values(array_unique(array_filter($filterable_attributes, function ($attr) {
            return is_string($attr) && $attr !== '' && $attr !== 'post_taxonomies';
        })));

        // Dictionary words are submitted as dictionary[] (always applied on save; empty clears).
        $dictionary = array();
        $raw_dictionary = isset($_POST['dictionary']) ? wp_unslash($_POST['dictionary']) : array();
        if (!is_array($raw_dictionary)) {
            $raw_dictionary = array($raw_dictionary);
        }
        foreach ($raw_dictionary as $word) {
            $term = is_string($word) ? trim(sanitize_text_field($word)) : '';
            if ($term === '') {
                continue;
            }
            $dictionary[] = $term;
        }
        $dictionary = array_values(array_unique($dictionary));

        // Typo tolerance settings are submitted as typo_tolerance[...] (always applied on save).
        $typo_tolerance = $this->get_default_typo_tolerance();
        $raw_typo_tolerance = isset($_POST['typo_tolerance']) ? wp_unslash($_POST['typo_tolerance']) : array();
        if (!is_array($raw_typo_tolerance)) {
            $raw_typo_tolerance = array();
        }
        $typo_tolerance['enabled'] = !empty($raw_typo_tolerance['enabled']);
        $typo_tolerance['disableOnNumbers'] = !empty($raw_typo_tolerance['disableOnNumbers']);

        $one_typo = isset($raw_typo_tolerance['minWordSizeForTypos']['oneTypo'])
            ? absint($raw_typo_tolerance['minWordSizeForTypos']['oneTypo'])
            : $typo_tolerance['minWordSizeForTypos']['oneTypo'];
        $two_typos = isset($raw_typo_tolerance['minWordSizeForTypos']['twoTypos'])
            ? absint($raw_typo_tolerance['minWordSizeForTypos']['twoTypos'])
            : $typo_tolerance['minWordSizeForTypos']['twoTypos'];
        if ($two_typos < $one_typo) {
            $two_typos = $one_typo;
        }
        $typo_tolerance['minWordSizeForTypos'] = array(
            'oneTypo' => $one_typo,
            'twoTypos' => $two_typos,
        );

        $disable_on_words = isset($raw_typo_tolerance['disableOnWords']) ? $raw_typo_tolerance['disableOnWords'] : array();
        if (!is_array($disable_on_words)) {
            $disable_on_words = array($disable_on_words);
        }
        $typo_tolerance['disableOnWords'] = array_values(array_unique(array_filter(array_map(function ($word) {
            $term = is_string($word) ? trim(sanitize_text_field($word)) : '';
            return $term !== '' ? $term : null;
        }, $disable_on_words))));

        $disable_on_attributes = isset($raw_typo_tolerance['disableOnAttributes']) ? $raw_typo_tolerance['disableOnAttributes'] : array();
        if (!is_array($disable_on_attributes)) {
            $disable_on_attributes = array($disable_on_attributes);
        }
        $typo_tolerance['disableOnAttributes'] = array_values(array_unique(array_filter(array_map(function ($attr) {
            $term = is_string($attr) ? trim(sanitize_text_field($attr)) : '';
            return $term !== '' ? $term : null;
        }, $disable_on_attributes))));

        //backup the settings to the database
        $index_settings_backup_key = $this->prefixed('index_settings_backup_') . $index_name;
        $hybrid = $this->get_hybrid_settings_from_post();
        // Save Settings POSTs prefs only. Keep embedder defs already stored from save/delete.
        $existing_backup = get_option($index_settings_backup_key, array());
        if (
            is_array($existing_backup)
            && isset($existing_backup['hybrid']['embedders'])
            && is_array($existing_backup['hybrid']['embedders'])
        ) {
            $hybrid['embedders'] = $existing_backup['hybrid']['embedders'];
        }
        $index_settings_backup = array(
            'ranking_rules' => $ranking_rules,
            'searchable_attributes' => $searchable_attributes,
            'synonyms' => $synonyms,
            'stop_words' => $stop_words,
            'filterable_attributes' => $filterable_attributes,
            'dictionary' => $dictionary,
            'typo_tolerance' => $typo_tolerance,
            'hybrid' => $hybrid,
        );

        //hook to allow other plugins to modify the index settings backup
        //@HOOK: scry_ms_index_settings_backup — args: $index_settings_backup, $index_name
        $index_settings_backup = apply_filters($this->config('hook_prefix') . 'index_settings_backup', $index_settings_backup, $index_name);

        //update the settings backup in the database
        update_option($index_settings_backup_key, $index_settings_backup);

        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', "scry-search")));
            return;
        }

        //let other plugins modify the index settings before they are updated
        //@HOOK: scry_ms_index_ranking_rules_before_update — args: $ranking_rules, $index_name
        $ranking_rules = apply_filters($this->config('hook_prefix') . 'index_ranking_rules_before_update', $ranking_rules, $index_name);
        //@HOOK: scry_ms_index_searchable_attributes_before_update — args: $searchable_attributes, $index_name
        $searchable_attributes = apply_filters($this->config('hook_prefix') . 'index_searchable_attributes_before_update', $searchable_attributes, $index_name);
        //@HOOK: scry_ms_index_synonyms_before_update — args: $synonyms, $index_name
        $synonyms = apply_filters($this->config('hook_prefix') . 'index_synonyms_before_update', $synonyms, $index_name);
        //@HOOK: scry_ms_index_stop_words_before_update — args: $stop_words, $index_name
        $stop_words = apply_filters($this->config('hook_prefix') . 'index_stop_words_before_update', $stop_words, $index_name);
        //@HOOK: scry_ms_index_filterable_attributes_before_update — args: $filterable_attributes, $index_name
        $filterable_attributes = apply_filters($this->config('hook_prefix') . 'index_filterable_attributes_before_update', $filterable_attributes, $index_name);
        if (!is_array($filterable_attributes)) {
            $filterable_attributes = array();
        }
        //@HOOK: scry_ms_index_dictionary_before_update — args: $dictionary, $index_name
        $dictionary = apply_filters($this->config('hook_prefix') . 'index_dictionary_before_update', $dictionary, $index_name);
        if (!is_array($dictionary)) {
            $dictionary = array();
        }
        //@HOOK: scry_ms_index_typo_tolerance_before_update — args: $typo_tolerance, $index_name
        $typo_tolerance = apply_filters($this->config('hook_prefix') . 'index_typo_tolerance_before_update', $typo_tolerance, $index_name);
        if (!is_array($typo_tolerance)) {
            $typo_tolerance = $this->get_default_typo_tolerance();
        }

        try {
            // Create Meilisearch client
            $client = $this->get_feature('scry_ms_client')->get_client();
            $index = $client->index($index_name);
            
            // Update ranking rules
            if (!empty($ranking_rules)) {
                $index->updateRankingRules($ranking_rules);
            }
            
            // Update searchable attributes
            if (!empty($searchable_attributes)) {
                $index->updateSearchableAttributes($searchable_attributes);
            }

            // Synonyms: always sync from POST (empty array clears Meilisearch synonyms for this index).
            $index->updateSynonyms($synonyms);

            // Stop words: always sync from POST (empty array clears Meilisearch stop words for this index).
            $index->updateStopWords($stop_words);

            // Filterable attributes: always sync from POST (empty array clears Meilisearch filterable attributes for this index).
            $index->updateFilterableAttributes($filterable_attributes);

            // Dictionary: always sync from POST (empty array clears Meilisearch dictionary for this index).
            $index->updateDictionary($dictionary);

            // Typo tolerance: always sync from POST.
            $index->updateTypoTolerance($typo_tolerance);
            
            //let other plugins take action using the index and the settings
            do_action($this->config('hook_prefix') . 'index_update_settings', $index);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Index settings updated successfully for "%s".', "scry-search"), $index_name)
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', "scry-search"), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            if ($e->getCode() === 404) {
                //log an error message with the logging feature
                $this->get_feature('scry_ms_logs')->log('error', sprintf(__('Index does not exist: %s', "scry-search"), $e->getMessage()));
                wp_send_json_error(array(
                    'message' => __('Index does not exist', "scry-search")
                ));
            } else {
                //log an error message with the logging feature
                $this->get_feature('scry_ms_logs')->log('error', sprintf(__('API error: %s', "scry-search"), $e->getMessage()));
                wp_send_json_error(array(
                    'message' => sprintf(__('API error: %s', "scry-search"), $e->getMessage())
                ));
            }
        } catch (\Exception $e) {
            // General error
            //log an error message with the logging feature
            $this->get_feature('scry_ms_logs')->log('error', sprintf(__('ajax_update_index_settings failed: %s', "scry-search"), $e->getMessage()));
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', "scry-search"), $e->getMessage())
            ));
        }
    }
    
    /**
     * Get default Meilisearch ranking rules
     */
    public function get_default_ranking_rules() {
        $ranking_rules = array(
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
            'exactness',
        );
        
        //let other plugins modify the ranking rules
        //@HOOK: scry_ms_index_ranking_rules
        $ranking_rules = apply_filters($this->config('hook_prefix') . 'index_ranking_rules', $ranking_rules);
        
        return $ranking_rules;
    }

    /**
     * Get available fields for a post type, including meta keys
     */
    public function get_available_fields_for_post_type($post_type) {
        $fields = array();
        
        // Core post fields
        $core_fields = array(
            'post_title' => __('Title', "scry-search"),
            'post_excerpt' => __('Excerpt', "scry-search"),
            'post_content' => __('Content', "scry-search"),
            'ID' => __('Post ID', "scry-search"),
            'post_date' => __('Post Date', "scry-search"),
            'post_date_gmt' => __('Post Date (GMT)', "scry-search"),
            'post_modified' => __('Modified Date', "scry-search"),
            'post_modified_gmt' => __('Modified Date (GMT)', "scry-search"),
            'post_author' => __('Author ID', "scry-search"),
            'post_name' => __('Post Slug', "scry-search"),
            'permalink' => __('Permalink', "scry-search"),
        );
        
        foreach ($core_fields as $field => $label) {
            $fields[$field] = array(
                'label' => $label,
                'type' => 'core',
                'path' => $field,
            );
        }
        
        // Featured Image
        $fields['featured_image'] = array(
            'label' => __('Featured Image', "scry-search"),
            'type' => 'media',
            'path' => 'featured_image',
        );
        
        // Author Name
        $fields['author_name'] = array(
            'label' => __('Author Name', "scry-search"),
            'type' => 'meta',
            'path' => 'author_name',
        );
        
        // Post Meta - get all unique meta keys for this post type
        $meta_keys = $this->get_post_meta_keys_for_post_type($post_type);
        
        if (!empty($meta_keys)) {
            $fields['post_meta'] = array(
                'label' => __('Post Meta', "scry-search"),
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

        //let other plugins modify the fields
        //@HOOK: scry_ms_index_fields
        $fields = apply_filters($this->config('hook_prefix') . 'index_fields', $fields, $post_type);
        
        return $fields;
    }

    /**
     * Get available filterable fields for a post type.
     * Same shape as searchable fields, plus a Post Taxonomies group.
     */
    public function get_available_filterable_fields_for_post_type($post_type) {
        $fields = $this->get_available_fields_for_post_type($post_type);
        if (!is_array($fields)) {
            $fields = array();
        }

        // Replace date string fields with a single unix timestamp filter field.
        //delete the other post dates
        unset(
            $fields['post_date'],
            $fields['post_date_gmt'],
            $fields['post_modified'],
            $fields['post_modified_gmt']
        );

        //reorder the fields
        $ordered_fields = array();
        foreach ($fields as $key => $field) {
            if ($key === 'post_author') {
                $ordered_fields['post_date_unix'] = array(
                    'label' => __('Post Date', "scry-search"),
                    'type' => 'core',
                    'path' => 'post_date_unix',
                );
            }
            $ordered_fields[$key] = $field;
        }
        //add the post date unix field if it's not already in the array
        if (!isset($ordered_fields['post_date_unix'])) {
            $ordered_fields['post_date_unix'] = array(
                'label' => __('Post Date', "scry-search"),
                'type' => 'core',
                'path' => 'post_date_unix',
            );
        }
        $fields = $ordered_fields;

        $excluded_taxonomies = $this->config('excluded_taxonomies');
        if (!is_array($excluded_taxonomies)) {
            $excluded_taxonomies = array();
        }

        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $taxonomy_children = array();

        if (is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (!is_object($taxonomy) || !isset($taxonomy->name)) {
                    continue;
                }
                if (in_array($taxonomy->name, $excluded_taxonomies, true)) {
                    continue;
                }

                // Match nested taxonomy paths used when indexing documents.
                $path = 'taxonomies.' . $taxonomy->name . '.id';

                $label = isset($taxonomy->label) ? (string) $taxonomy->label : (string) $taxonomy->name;
                $taxonomy_children[$path] = array(
                    'label' => sprintf('%s (%s)', $label, $taxonomy->name),
                    'type' => 'taxonomy',
                    'path' => $path,
                );
            }
        }

        if (!empty($taxonomy_children)) {
            $fields['post_taxonomies'] = array(
                'label' => __('Post Taxonomies', "scry-search"),
                'type' => 'group',
                'path' => 'post_taxonomies',
                'children' => $taxonomy_children,
            );
        }

        //@HOOK: scry_ms_index_filterable_fields
        $fields = apply_filters($this->config('hook_prefix') . 'index_filterable_fields', $fields, $post_type);

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

        //@HOOK: scry_ms_index_meta_keys
        $meta_keys = apply_filters($this->config('hook_prefix') . 'index_meta_keys', $meta_keys, $post_type);
        
        return $meta_keys ? $meta_keys : array();
    }
    
    //  \\  //  \\  //  \\  Helpers  //  \\  //  \\  //  \\ 

    public function get_index_names() {
        global $wpdb;
        $index_names = array();
        $post_types_to_index = get_option($this->prefixed('post_types'), array());
        foreach ($post_types_to_index as $post_type) {
            $index_names[$post_type] = $wpdb->prefix . $this->get_prefix() . get_option($this->prefixed('index_affix')) . $post_type;
        }

        //let other plugins modify the index names
        //@HOOK: scry_ms_index_names
        $index_names = apply_filters($this->config('hook_prefix') . 'index_names', $index_names);
        
        return $index_names;
    }
    
    /**
     * Get the list of searchable attributes for Meilisearch indexes
     * Excludes: post_status, post_type, author_name, featured_image
     */
    public function get_searchable_attributes() {
        $searchable_attributes = array(
            'post_title',
            'post_excerpt',
            'post_content',
            'ID',
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

        //let other plugins modify the searchable attributes
        //@HOOK: scry_ms_index_searchable_attributes
        $searchable_attributes = apply_filters($this->config('hook_prefix') . 'index_searchable_attributes', $searchable_attributes);

        return $searchable_attributes;
    }

    /**
     * Collect every field path from an available-fields tree (including group children).
     *
     * @param array $fields
     * @return string[]
     */
    public function get_field_paths_from_available_fields($fields) {
        $paths = array();
        foreach ((array) $fields as $field) {
            if (!is_array($field) || !isset($field['path'])) {
                continue;
            }
            $paths[] = (string) $field['path'];
            if (empty($field['children']) || !is_array($field['children'])) {
                continue;
            }
            foreach ($field['children'] as $child) {
                if (!is_array($child) || !isset($child['path'])) {
                    continue;
                }
                $paths[] = (string) $child['path'];
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Normalize searchable attributes for the settings UI.
     * Meilisearch's default ["*"] means every field is searchable.
     *
     * @param mixed $searchable_attributes
     * @param array $available_fields
     * @return string[]
     */
    public function resolve_searchable_attributes_for_ui($searchable_attributes, $available_fields) {
        if (!is_array($searchable_attributes) || empty($searchable_attributes)) {
            return $this->get_searchable_attributes();
        }

        $searchable_attributes = array_values(array_filter($searchable_attributes, 'is_string'));
        if (!in_array('*', $searchable_attributes, true)) {
            return $searchable_attributes;
        }

        $available_paths = $this->get_field_paths_from_available_fields($available_fields);
        $ordered = array();
        foreach ($this->get_searchable_attributes() as $path) {
            if (in_array($path, $available_paths, true)) {
                $ordered[] = $path;
            }
        }
        foreach ($available_paths as $path) {
            if (!in_array($path, $ordered, true)) {
                $ordered[] = $path;
            }
        }

        return $ordered;
    }

    /**
     * Default filterable attributes for new indexes.
     */
    public function get_default_filterable_attributes() {
        $filterable_attributes = array(
            'post_date_unix',
            'post_type',
            'post_status',
            'post_author',
            'taxonomies.category.id',
            'taxonomies.post_tag.id',
        );

        //@HOOK: scry_ms_index_filterable_attributes
        $filterable_attributes = apply_filters($this->config('hook_prefix') . 'index_filterable_attributes', $filterable_attributes);

        return $filterable_attributes;
    }

    /**
     * Default typo tolerance settings for new indexes.
     */
    public function get_default_typo_tolerance() {
        $typo_tolerance = array(
            'enabled' => true,
            'minWordSizeForTypos' => array(
                'oneTypo' => 5,
                'twoTypos' => 9,
            ),
            'disableOnWords' => array(),
            'disableOnAttributes' => array(),
            'disableOnNumbers' => false,
        );

        //@HOOK: scry_ms_index_typo_tolerance
        $typo_tolerance = apply_filters($this->config('hook_prefix') . 'index_typo_tolerance', $typo_tolerance);

        return is_array($typo_tolerance) ? $typo_tolerance : array(
            'enabled' => true,
            'minWordSizeForTypos' => array(
                'oneTypo' => 5,
                'twoTypos' => 9,
            ),
            'disableOnWords' => array(),
            'disableOnAttributes' => array(),
            'disableOnNumbers' => false,
        );
    }

    /**
     * Hybrid prefs stored in the WP index settings backup for this index.
     * enabled / embedder / ratio only — embedder defs are backup['hybrid']['embedders'] (PHP-only).
     * enabled is forced off if embedder is empty.
     *
     * @param string $index_name Meilisearch index uid.
     * @return array{enabled:bool,embedder:string,semantic_ratio:float}
     */
    public function get_hybrid_settings($index_name) {
        $default_ratio = (float) $this->config('default_semantic_ratio');
        if ($default_ratio < 0 || $default_ratio > 1) {
            $default_ratio = 0.5;
        }

        $defaults = array(
            'enabled' => false,
            'embedder' => '',
            'semantic_ratio' => $default_ratio,
        );

        $backup = get_option($this->prefixed('index_settings_backup_') . $index_name, array());
        if (!is_array($backup) || !isset($backup['hybrid']) || !is_array($backup['hybrid'])) {
            return $defaults;
        }

        $hybrid = $backup['hybrid'];
        $ratio = isset($hybrid['semantic_ratio']) ? (float) $hybrid['semantic_ratio'] : $default_ratio;
        $ratio = max(0.0, min(1.0, $ratio));
        $embedder = isset($hybrid['embedder']) ? (string) $hybrid['embedder'] : '';
        $enabled = !empty($hybrid['enabled']) && $embedder !== '';

        return array(
            'enabled' => $enabled,
            'embedder' => $embedder,
            'semantic_ratio' => $ratio,
        );
    }

    /**
     * Hybrid prefs from Configure Index POST (Save Settings).
     * Unchecked checkboxes are omitted from POST — missing hybrid_enabled means off.
     * Empty embedder forces enabled off so hybrid cannot be "on" with nothing selected.
     *
     * @return array{enabled:bool,embedder:string,semantic_ratio:float}
     */
    private function get_hybrid_settings_from_post() {
        $enabled = isset($_POST['hybrid_enabled']) && (string) wp_unslash($_POST['hybrid_enabled']) === '1';

        $embedder = '';
        if (isset($_POST['hybrid_embedder'])) {
            $embedder = sanitize_text_field(wp_unslash($_POST['hybrid_embedder']));
        }

        if ($embedder === '') {
            $enabled = false;
        }

        $default_ratio = (float) $this->config('default_semantic_ratio');
        if ($default_ratio < 0 || $default_ratio > 1) {
            $default_ratio = 0.5;
        }

        $ratio = $default_ratio;
        if (isset($_POST['hybrid_semantic_ratio'])) {
            $ratio = (float) wp_unslash($_POST['hybrid_semantic_ratio']);
        }
        $ratio = max(0.0, min(1.0, $ratio));

        return array(
            'enabled' => (bool) $enabled,
            'embedder' => $embedder,
            'semantic_ratio' => $ratio,
        );
    }

    /**
     * AJAX: GET embedders for one Scry index. Browser never sees apiKey (has_api_key only).
     * Requires a known Scry index_name so callers cannot probe arbitrary Meili uids.
     */
    public function ajax_list_embedders() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('list_embedders'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
        if ($index_name === '') {
            wp_send_json_error(array('message' => __('Please provide an index name', "scry-search")));
            return;
        }

        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', "scry-search")));
            return;
        }

        $embedders = $this->get_embedders_for_index($index_name);
        if (is_wp_error($embedders)) {
            wp_send_json_error(array('message' => $embedders->get_error_message()));
            return;
        }

        // First list after upgrade: copy Meili defs into WP so a later wipe can restore them.
        // isset() is false when the key is missing, true when it is [] (all deleted — do not refill from a stale GET).
        $this->maybe_backfill_embedders_backup($index_name, $embedders);

        wp_send_json_success(array(
            'index_name' => $index_name,
            'embedders' => $this->sanitize_embedders_for_browser($embedders),
        ));
    }

    /**
     * AJAX: PATCH one embedder onto one Scry index. Does not copy to other indexes.
     * Blank api_key on edit keeps the existing Meili key. Does not wait for the async Meili task
     * (Ollama/embeddings can hang that wait).
     */
    public function ajax_save_embedder() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('save_embedder'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
        if ($index_name === '') {
            wp_send_json_error(array('message' => __('Please provide an index name', "scry-search")));
            return;
        }

        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', "scry-search")));
            return;
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        if ($name === '') {
            wp_send_json_error(array('message' => __('Embedder name is required (letters, numbers, _ or -).', "scry-search")));
            return;
        }

        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $allowed_sources = (array) $this->config('embedder_sources');
        if ($allowed_sources === array()) {
            $allowed_sources = array('openAi', 'ollama', 'huggingFace', 'userProvided');
        }
        if (!in_array($source, $allowed_sources, true)) {
            wp_send_json_error(array('message' => __('Invalid embedder source.', "scry-search")));
            return;
        }

        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';
        $api_key = isset($_POST['api_key']) ? (string) wp_unslash($_POST['api_key']) : '';
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $document_template = isset($_POST['document_template']) ? sanitize_textarea_field(wp_unslash($_POST['document_template'])) : '';
        $dimensions = isset($_POST['dimensions']) ? absint($_POST['dimensions']) : 0;

        $config = array(
            'source' => $source,
        );
        if ($model !== '') {
            $config['model'] = $model;
        }
        if ($url !== '') {
            $config['url'] = $url;
        }
        if ($document_template !== '' && $source !== 'userProvided') {
            $config['documentTemplate'] = $document_template;
        }
        if ($source === 'userProvided') {
            if ($dimensions < 1) {
                wp_send_json_error(array('message' => __('Dimensions are required for userProvided embedders.', "scry-search")));
                return;
            }
            $config['dimensions'] = $dimensions;
        } elseif ($dimensions > 0) {
            $config['dimensions'] = $dimensions;
        }

        if ($api_key === '') {
            // Edit with a blank key: reuse the key already stored on this index.
            $existing = $this->get_embedders_for_index($index_name);
            if (!is_wp_error($existing) && isset($existing[$name]['apiKey']) && $existing[$name]['apiKey'] !== '') {
                $config['apiKey'] = $existing[$name]['apiKey'];
            }
        } else {
            $config['apiKey'] = $api_key;
        }

        $response = $this->meilisearch_request(
            'PATCH',
            'indexes/' . rawurlencode($index_name) . '/settings/embedders',
            array($name => $config)
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $this->merge_embedder_into_index_backup($index_name, $name, $config);

        wp_send_json_success(array(
            'message' => __('Sent to Meilisearch. It will apply this embedder in the background. Reindex to build vectors.', "scry-search"),
            'name' => $name,
            'index_name' => $index_name,
        ));
    }

    /**
     * AJAX: remove one embedder from one Scry index (Meili PATCH { name: null }).
     * If this index's WP backup had that name selected, clear it and turn hybrid off.
     */
    public function ajax_delete_embedder() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->prefixed('delete_embedder'))) {
            wp_send_json_error(array('message' => __('Security check failed', "scry-search")));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', "scry-search")));
            return;
        }

        $index_name = isset($_POST['index_name']) ? sanitize_text_field(wp_unslash($_POST['index_name'])) : '';
        if ($index_name === '') {
            wp_send_json_error(array('message' => __('Please provide an index name', "scry-search")));
            return;
        }

        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', "scry-search")));
            return;
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        if ($name === '') {
            wp_send_json_error(array('message' => __('Embedder name is required.', "scry-search")));
            return;
        }

        $response = $this->meilisearch_request(
            'PATCH',
            'indexes/' . rawurlencode($index_name) . '/settings/embedders',
            array($name => null) // Meilisearch delete: JSON null for that key, not HTTP DELETE.
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $cleared_selection = $this->clear_embedder_from_index_backup($index_name, $name);

        wp_send_json_success(array(
            'message' => __('Embedder deleted from this index.', "scry-search"),
            'name' => $name,
            'index_name' => $index_name,
            'cleared_selection' => $cleared_selection,
        ));
    }

    /**
     * If this index's WP backup had that embedder selected, clear it and turn hybrid off.
     * Always drop the def from hybrid.embedders so wipe/recreate does not put a deleted embedder back.
     *
     * @param string $index_name     Meilisearch index uid.
     * @param string $embedder_name  Embedder that was deleted.
     * @return bool True if the backup selection was cleared.
     */
    private function clear_embedder_from_index_backup($index_name, $embedder_name) {
        $embedder_name = (string) $embedder_name;
        if ($index_name === '' || $embedder_name === '') {
            return false;
        }

        $backup_key = $this->prefixed('index_settings_backup_') . $index_name;
        $backup = get_option($backup_key, array());
        if (!is_array($backup)) {
            return false;
        }
        if (!isset($backup['hybrid']) || !is_array($backup['hybrid'])) {
            $backup['hybrid'] = array();
        }

        $cleared_selection = false;
        $current = isset($backup['hybrid']['embedder']) ? (string) $backup['hybrid']['embedder'] : '';
        if ($current === $embedder_name) {
            $backup['hybrid']['embedder'] = '';
            $backup['hybrid']['enabled'] = false;
            $cleared_selection = true;
        }

        if (isset($backup['hybrid']['embedders']) && is_array($backup['hybrid']['embedders'])) {
            unset($backup['hybrid']['embedders'][$embedder_name]);
        }

        update_option($backup_key, $backup);

        return $cleared_selection;
    }

    /**
     * Copy Meili embedder defs into WP once, when this index has never stored them.
     * Does not PATCH Meili. Does not overwrite [] (user deleted every embedder).
     *
     * @param string $index_name Meilisearch index uid.
     * @param array  $embedders  Raw map from GET (includes apiKey).
     */
    private function maybe_backfill_embedders_backup($index_name, $embedders) {
        if ($index_name === '' || !is_array($embedders)) {
            return;
        }

        $backup = get_option($this->prefixed('index_settings_backup_') . $index_name, array());
        if (is_array($backup) && isset($backup['hybrid']['embedders']) && is_array($backup['hybrid']['embedders'])) {
            return;
        }

        $this->save_hybrid_embedders_to_backup($index_name, $embedders);
    }

    /**
     * Merge one embedder def into this index's WP backup (keeps apiKey for restore).
     *
     * @param string $index_name Meilisearch index uid.
     * @param string $name       Embedder name.
     * @param array  $config     Meili embedder body.
     */
    private function merge_embedder_into_index_backup($index_name, $name, $config) {
        if ($index_name === '' || $name === '' || !is_array($config)) {
            return;
        }

        $embedders = $this->get_hybrid_embedders_from_backup($index_name);
        $embedders[$name] = $config;
        $this->save_hybrid_embedders_to_backup($index_name, $embedders);
    }

    /**
     * Read backup['hybrid']['embedders'] for this index. Missing key → empty array.
     *
     * @param string $index_name Meilisearch index uid.
     * @return array<string,array>
     */
    private function get_hybrid_embedders_from_backup($index_name) {
        $backup = get_option($this->prefixed('index_settings_backup_') . $index_name, array());
        if (!is_array($backup) || !isset($backup['hybrid']['embedders']) || !is_array($backup['hybrid']['embedders'])) {
            return array();
        }

        return $backup['hybrid']['embedders'];
    }

    /**
     * Write backup['hybrid']['embedders'] without wiping enabled / embedder / ratio.
     * get_option reads wp_options; update_option writes it. apiKey stays in PHP — never JSON to the browser.
     *
     * @param string $index_name Meilisearch index uid.
     * @param array  $embedders  Name => config map.
     */
    private function save_hybrid_embedders_to_backup($index_name, $embedders) {
        if ($index_name === '' || !is_array($embedders)) {
            return;
        }

        $backup_key = $this->prefixed('index_settings_backup_') . $index_name;
        $backup = get_option($backup_key, array());
        if (!is_array($backup)) {
            $backup = array();
        }
        if (!isset($backup['hybrid']) || !is_array($backup['hybrid'])) {
            $backup['hybrid'] = array();
        }
        $backup['hybrid']['embedders'] = $embedders;
        update_option($backup_key, $backup);
    }

    /**
     * PATCH stored embedder defs onto a brand-new Meili index. Caller must only run this when $created.
     *
     * @param string $index_name Meilisearch index uid.
     * @param mixed  $backup     Index settings backup option value.
     */
    private function restore_embedders_from_backup($index_name, $backup) {
        if ($index_name === '' || !is_array($backup) || !isset($backup['hybrid']['embedders']) || !is_array($backup['hybrid']['embedders'])) {
            return;
        }

        $to_restore = array();
        foreach ($backup['hybrid']['embedders'] as $name => $config) {
            // continue skips this loop item only (not the whole function).
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $name);
            if ($name === '' || !is_array($config)) {
                continue;
            }
            $to_restore[$name] = $config;
        }

        if ($to_restore === array()) {
            return;
        }

        $response = $this->meilisearch_request(
            'PATCH',
            'indexes/' . rawurlencode($index_name) . '/settings/embedders',
            $to_restore
        );

        if (is_wp_error($response)) {
            $this->get_feature('scry_ms_logs')->log(
                'error',
                sprintf(
                    __('Failed to restore embedders for %1$s: %2$s', "scry-search"),
                    $index_name,
                    $response->get_error_message()
                )
            );
        }
    }

    /**
     * Raw embedder map from Meilisearch for one index (includes apiKey).
     * PHP-only — always run through sanitize_embedders_for_browser before wp_send_json_*.
     *
     * @param string $index_name Meilisearch index uid.
     * @return array|WP_Error
     */
    private function get_embedders_for_index($index_name) {
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');

        if ($meilisearch_url === '' || $meilisearch_admin_key === '' || $index_name === '') {
            return new WP_Error(
                'scry_ms_no_connection',
                __('Connection settings are not configured', "scry-search")
            );
        }

        $endpoint = trailingslashit($meilisearch_url) . 'indexes/' . rawurlencode($index_name) . '/settings/embedders';
        $response = wp_remote_get($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $meilisearch_admin_key,
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);
            $message = is_array($decoded) && isset($decoded['message'])
                ? (string) $decoded['message']
                : sprintf(__('Meilisearch returned HTTP %d', "scry-search"), $code);
            return new WP_Error('scry_ms_embedders_http', $message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return array();
        }

        return $body;
    }

    /**
     * Strip apiKey; expose has_api_key so the admin UI can say a key exists without sending it.
     *
     * @param array $embedders Meilisearch embedder map.
     * @return array<string,array>
     */
    private function sanitize_embedders_for_browser($embedders) {
        $safe = array();
        if (!is_array($embedders)) {
            return $safe;
        }

        foreach ($embedders as $name => $config) {
            if (!is_string($name) || $name === '' || !is_array($config)) {
                continue;
            }
            $has_key = !empty($config['apiKey']);
            unset($config['apiKey']);
            $config['has_api_key'] = $has_key;
            $safe[$name] = $config;
        }

        return $safe;
    }

    /**
     * Low-level Meilisearch HTTP helper (WP HTTP API).
     * Used for PATCH embedders; GET list still uses wp_remote_get in get_embedders_for_index().
     *
     * @param string     $method GET|PATCH|POST|DELETE
     * @param string     $path   Path after host.
     * @param array|null $body   JSON body for PATCH/POST.
     * @return array|WP_Error
     */
    private function meilisearch_request($method, $path, $body = null) {
        $url = get_option($this->prefixed('meilisearch_url'), '');
        $key = get_option($this->prefixed('meilisearch_admin_key'), '');

        if ($url === '' || $key === '') {
            return new WP_Error(
                'scry_ms_no_connection',
                __('Connection settings are not configured', "scry-search")
            );
        }

        $endpoint = trailingslashit($url) . ltrim($path, '/');
        $args = array(
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ),
        );

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($endpoint, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $message = __('Meilisearch request failed.', "scry-search");
            if (is_array($decoded) && isset($decoded['message'])) {
                $message = (string) $decoded['message'];
            } elseif ($raw !== '') {
                $message = $raw;
            }
            return new WP_Error('scry_ms_meili_http', $message, array('status' => $code));
        }

        return is_array($decoded) ? $decoded : array();
    }
}