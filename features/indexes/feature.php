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
        
        // Register AJAX handlers
        add_action('wp_ajax_' . $this->prefixed('wipe_index'), array($this, 'ajax_wipe_index'));
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
                //determine if the index exists by trying to fetch it
                try {
                    $client->index($index_name)->fetchRawInfo();
                    // Index exists, continue to next
                    continue;
                } catch (ApiException $e) {
                    // check that the code is 404
                    if ($e->getCode() === 404) {
                        // Index doesn't exist, create it
                        $client->createIndex($index_name, ['primaryKey' => 'ID']);
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
                $this->get_feature('scrywp_admin_page')->render_admin_page($content);
            }
        );
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
     * AJAX handler for wiping (deleting) a Meilisearch index
     */
    public function ajax_wipe_index() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->prefixed('wipe_index'))) {
            wp_send_json_error(array('message' => __('Security check failed', 'scry-wp')));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'scry-wp')));
            return;
        }
        
        // Get index name from POST data
        $index_name = isset($_POST['index_name']) ? sanitize_text_field($_POST['index_name']) : '';
        
        // Validate index name
        if (empty($index_name)) {
            wp_send_json_error(array('message' => __('Please provide an index name', 'scry-wp')));
            return;
        }
        
        // Verify the index name is one of the configured indexes (security check)
        $index_names = $this->get_index_names();
        if (!in_array($index_name, $index_names, true)) {
            wp_send_json_error(array('message' => __('Invalid index name', 'scry-wp')));
            return;
        }
        
        // Get connection settings
        $meilisearch_url = get_option($this->prefixed('meilisearch_url'), '');
        $meilisearch_admin_key = get_option($this->prefixed('meilisearch_admin_key'), '');
        
        if (empty($meilisearch_url) || empty($meilisearch_admin_key)) {
            wp_send_json_error(array('message' => __('Connection settings are not configured', 'scry-wp')));
            return;
        }
        
        try {
            // Create Meilisearch client
            $client = new Client($meilisearch_url, $meilisearch_admin_key);
            
            // Get the index and delete it
            $index = $client->index($index_name);
            $index->delete();
            
            // Success - the index will be recreated automatically by ensure_post_indexes_exist
            wp_send_json_success(array(
                'message' => sprintf(__('Index "%s" has been wiped successfully. It will be recreated automatically.', 'scry-wp'), $index_name)
            ));
            
        } catch (\Meilisearch\Exceptions\CommunicationException $e) {
            // Network/connection error
            wp_send_json_error(array(
                'message' => sprintf(__('Connection failed: %s', 'scry-wp'), $e->getMessage())
            ));
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // API error (404 if index doesn't exist, etc.)
            if ($e->getCode() === 404) {
                wp_send_json_error(array(
                    'message' => __('Index does not exist', 'scry-wp')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => sprintf(__('API error: %s', 'scry-wp'), $e->getMessage())
                ));
            }
        } catch (\Exception $e) {
            // General error
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', 'scry-wp'), $e->getMessage())
            ));
        }
    }
}