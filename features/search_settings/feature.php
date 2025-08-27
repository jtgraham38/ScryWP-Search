<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;

class ScryWpSearchSettingsFeature extends PluginFeature {
    
    public function add_filters() {
        // Add filters for sanitizing and validating settings
        add_filter('sanitize_option_' . $this->prefixed('search_weights'), array($this, 'sanitize_search_weights'));
    }
    
    public function add_actions() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers for dynamic factor management
        add_action('wp_ajax_' . $this->prefixed('add_search_factor'), array($this, 'ajax_add_search_factor'));
        add_action('wp_ajax_' . $this->prefixed('remove_search_factor'), array($this, 'ajax_remove_search_factor'));
        add_action('wp_ajax_' . $this->prefixed('update_search_weights'), array($this, 'ajax_update_search_weights'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets for search settings
     */
    public function enqueue_admin_assets($hook) {
        // Only load assets on our admin page
        if ($hook !== 'toplevel_page_scrywp-search') {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            $this->prefixed('weights-input-styles'),
            plugin_dir_url(__FILE__) . 'assets/css/weights_input.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            $this->prefixed('weights-input-script'),
            plugin_dir_url(__FILE__) . 'assets/js/weights_input.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            $this->prefixed('weights-input-script'),
            'scrywp_weights_ajax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce($this->prefixed('search_settings_nonce')),
                'add_factor_action' => $this->prefixed('add_search_factor'),
                'remove_factor_action' => $this->prefixed('remove_search_factor'),
                'update_weights_action' => $this->prefixed('update_search_weights')
            )
        );
    }
    
    /**
     * Register WordPress settings
     */
    public function register_settings() {
        // Only allow administrators to access these settings
        if (!current_user_can('manage_options')) {
            return;
        }
        
        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('search_weights'),
            array(
                'type' => 'array',
                'description' => 'Search factor weights for ScryWP Search. Weights do not need to sum to 1.',
                'sanitize_callback' => array($this, 'sanitize_search_weights'),
                'default' => $this->get_default_weights()
            )
        );
    }
    
    /**
     * Get default search factor weights
     */
    private function get_default_weights() {
        return array(
            'semantic_similarity' => 1.0,
            'recency' => 0.2,
            'comment_engagement' => 0.2,
            //'keyword_matching' => 0.2,
            //'category_matching' => 0.05,
            //'tag_matching' => 0.05
        );
    }
    
    /**
     * Sanitize and validate search weights
     */
    public function sanitize_search_weights($input) {
        if (!is_array($input)) {
            return $this->get_default_weights();
        }
        
        $sanitized = array();
        
        foreach ($input as $factor => $weight) {
            // Sanitize factor name
            $factor = sanitize_key($factor);
            
            // Validate weight is numeric and between 0 and 1
            $weight = floatval($weight);
            if ($weight >= 0 && $weight <= 1) {
                $sanitized[$factor] = $weight;
            }
        }
        
        // Ensure semantic similarity is always present with a weight
        if (!isset($sanitized['semantic_similarity'])) {
            $sanitized['semantic_similarity'] = 1.0;
        }
        
        // If no valid weights, use defaults
        if (empty($sanitized)) {
            $sanitized = $this->get_default_weights();
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX handler for adding a new search factor
     */
    public function ajax_add_search_factor() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], $this->prefixed('search_settings_nonce')) || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $factor_name = sanitize_key($_POST['factor_name']);
        if (empty($factor_name)) {
            wp_send_json_error('Invalid factor name');
        }
        
        // Prevent adding semantic similarity if it already exists
        if ($factor_name === 'semantic_similarity') {
            wp_send_json_error('Semantic similarity factor already exists and cannot be added again');
        }
        
        $current_weights = get_option($this->prefixed('search_weights'), $this->get_default_weights());
        
        // Add new factor with default weight
        $current_weights[$factor_name] = 0.1;
        
        update_option($this->prefixed('search_weights'), $current_weights);
        
        wp_send_json_success(array(
            'weights' => $current_weights,
            'message' => 'Factor added successfully'
        ));
    }
    
    /**
     * AJAX handler for removing a search factor
     */
    public function ajax_remove_search_factor() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], $this->prefixed('search_settings_nonce')) || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $factor_name = sanitize_key($_POST['factor_name']);
        if (empty($factor_name)) {
            wp_send_json_error('Invalid factor name');
        }
        
        // Prevent removal of semantic similarity factor
        if ($factor_name === 'semantic_similarity') {
            wp_send_json_error('Semantic similarity factor cannot be removed');
        }
        
        $current_weights = get_option($this->prefixed('search_weights'), $this->get_default_weights());
        
        if (isset($current_weights[$factor_name])) {
            unset($current_weights[$factor_name]);
            
            update_option($this->prefixed('search_weights'), $current_weights);
            
            wp_send_json_success(array(
                'weights' => $current_weights,
                'message' => 'Factor removed successfully'
            ));
        } else {
            wp_send_json_error('Factor not found');
        }
    }
    
    /**
     * AJAX handler for updating search weights
     */
    public function ajax_update_search_weights() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], $this->prefixed('search_settings_nonce')) || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $weights = $_POST['weights'];
        if (!is_array($weights)) {
            wp_send_json_error('Invalid weights data');
        }
        
        // Sanitize and validate weights (no normalization)
        $sanitized_weights = $this->sanitize_search_weights($weights);
        
        update_option($this->prefixed('search_weights'), $sanitized_weights);
        
        wp_send_json_success(array(
            'weights' => $sanitized_weights,
            'message' => 'Weights updated successfully'
        ));
    }
    
    /**
     * Get current search weights
     */
    public function get_search_weights() {
        return get_option($this->prefixed('search_weights'), $this->get_default_weights());
    }
    
    /**
     * Get available search factors
     */
    public function get_available_factors() {
        return array(
            'semantic_similarity' => __('Semantic Similarity', 'scry-wp'),
            'recency' => __('Recency', 'scry-wp'),
            'comment_engagement' => __('Comment Engagement', 'scry-wp'),
            // 'keyword_matching' => __('Keyword Matching', 'scry-wp'),
            // 'category_matching' => __('Category Matching', 'scry-wp'),
            // 'tag_matching' => __('Tag Matching', 'scry-wp'),
            // 'author_authority' => __('Author Authority', 'scry-wp'),
            // 'content_length' => __('Content Length', 'scry-wp'),
            // 'readability' => __('Readability', 'scry-wp'),
            // 'social_shares' => __('Social Shares', 'scry-wp')
        );
    }
}