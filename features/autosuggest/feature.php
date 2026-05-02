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

class ScrySearch_AutoSuggestFeature extends PluginFeature {
    
    public function add_filters() {
       

    }
    
    public function add_actions() {
        // Register settings for the autosuggest feature
        add_action('admin_init', array($this, 'register_settings'));

        // Load the autosuggest assets on the frontend if autosuggest is enabled
        add_action('wp_enqueue_scripts', array($this, 'load_assets'));

        // Register the rest api route for autosuggest
        add_action('rest_api_init', array($this, 'register_rest_api_route'));
    }

    /**
     * Load the autosuggest assets on the frontend if autosuggest is enabled, with the scripts localized with the settings
     */
    public function load_assets() {
        // Get the enable autosuggest setting
        $enable_autosuggest = get_option($this->prefixed('enable_autosuggest'), '0');
        if (!$enable_autosuggest) {
            return;
        }

        // Get the class selector setting and the search api key
        //IMPORTANT: do not localize the admin api key here!  It cannot go to the frontend!
        $class_selector = get_option($this->prefixed('class_selector'), '');
        $rest_api_url = rest_url('scry-search/v1/autosuggest');
   

        // Load the assets
        wp_enqueue_style(
            $this->prefixed('autosuggest-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/autosuggest.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            $this->prefixed('autosuggest-script'),
            plugin_dir_url(__FILE__) . 'assets/js/autosuggest.js',
            array($this->prefixed('window-script')),
            '1.0.0',
            true
        );

        // Localize the script with the settings
        wp_localize_script(
            $this->prefixed('autosuggest-script'),
            'localized',
            array(
                'classSelector' => $class_selector,
                'restApiUrl' => $rest_api_url,
            )
        );
    }

    /**
     * Register rest api route for autosuggest
     */
    public function register_rest_api_route() {
        register_rest_route(
            'scry-search/v1',
            '/autosuggest',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_autosuggest_request'),
            )
        );
    }

    /**
     * Handle the autosuggest request
     */
    public function handle_autosuggest_request( $request ) {
        // Search term: JS mirrors form fields (input name="s"); keep "query" for manual/API callers.
        $raw_search = $request->get_param('s');
        if ($raw_search === null || $raw_search === '') {
            $raw_search = $request->get_param('query');
        }
        $query = sanitize_text_field(is_scalar($raw_search) ? (string) $raw_search : '');
        if (is_array($request->get_param('post_type'))){
            $post_type = array_map('sanitize_text_field', $request->get_param('post_type'));
        } else {
            $post_type = sanitize_text_field($request->get_param('post_type'));
        }

        //run a search query to retrieve the 5 most relevant results, reusing the search logic in the search feature
        if ($query === '') {
            return rest_ensure_response(array());
        }

        //get all indexed post types as a fallback default
        $indexed_post_types = $this->get_feature('scry_ms_indexes')->get_index_names();
        $indexed_post_types = array_keys($indexed_post_types);

        $search_query = new WP_Query(array(
            's' => $query,
            'post_type' => ((is_string($post_type) && $post_type !== '') || is_array($post_type)) ? $post_type : $indexed_post_types,
            'posts_per_page' => 5,
            'no_found_rows' => true,
        ));

        //keep the title, url, and excerpt of the results
        $results = array();
        foreach ($search_query->posts as $post) {
            $results[] = array(
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'excerpt' => $post->post_excerpt,
                'post_type' => $post->post_type,
            );
        }

        return rest_ensure_response($results);
    }

    /**
     * Register settings sections/fields (these will be placed ont eh search settings page)
     */
    public function register_settings() {
        
        // Register the autosuggest settings section
        add_settings_section(
            $this->prefixed('autosuggest_settings_section'),
            'Autosuggest Settings',
            function() {
                echo '<p>Configure the autosuggest settings for Scry Search for Meilisearch.</p>';
            },
            $this->prefixed('search_settings_group')
        );

        // Add the autosuggest settings field
        add_settings_field(
            $this->prefixed('enable_autosuggest'),
            'Autosuggest Settings',
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/enable_autosuggest_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('autosuggest_settings_section')
        );

        // class selector to apply autosuggest to
        add_settings_field(
            $this->prefixed('class_selector'),
            'Class Selector',
            function() {
                require_once plugin_dir_path(__FILE__) . 'elements/class_selector_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('autosuggest_settings_section')
        );


        // Register the autosuggest settings setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('enable_autosuggest'),
            array(
                'type' => 'string',
                'description' => 'Enable autosuggest for Scry Search for Meilisearch.',
            )
        );

        // Register the class selector settings setting
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('class_selector'),
            array(
                'type' => 'string',
                'description' => 'The class selector to apply autosuggest to.',
            )
        );
    }

}