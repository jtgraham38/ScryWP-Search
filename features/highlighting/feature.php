<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

use jtgraham38\jgwordpresskit\PluginFeature;
use Meilisearch\Contracts\SearchQuery;

/**
 * Adds matched-term highlighting to Scry Search results.
 */
class ScrySearch_HighlightingFeature extends PluginFeature {

    /**
     * Highlighted fields from the current Meilisearch request, keyed by post ID.
     *
     * @var array<int, array<string, string>>
     */
    private $highlights = array();

    /**
     * Register feature filters.
     */
    public function add_filters() {
        add_filter(
            $this->prefixed('multi_search_query'),
            array($this, 'add_highlight_options')
        );

        add_filter(
            $this->prefixed('multi_search_raw_results'),
            array($this, 'capture_highlights')
        );

        add_filter(
            $this->prefixed('multi_search_final_results'),
            array($this, 'apply_highlights')
        );
    }

    /**
     * Register feature actions.
     */
    public function add_actions() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Add highlighting controls to the existing Search Settings page.
     */
    public function register_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_settings_section(
            $this->prefixed('highlighting_settings_section'),
            __('Matched Term Highlighting', "scry-search"),
            function() {
                echo '<p>' . esc_html__('Customize the appearance of matched terms in search results and autosuggest.', "scry-search") . '</p>';
            },
            $this->prefixed('search_settings_group')
        );

        add_settings_field(
            $this->prefixed('highlighting_css'),
            __('Highlight CSS', "scry-search"),
            function() {
                require plugin_dir_path(__FILE__) . 'elements/highlighting_css_input.php';
            },
            $this->prefixed('search_settings_group'),
            $this->prefixed('highlighting_settings_section')
        );

        register_setting(
            $this->prefixed('search_settings_group'),
            $this->prefixed('highlighting_css'),
            array(
                'type'              => 'string',
                'description'       => 'Custom frontend CSS for matched search terms.',
                'sanitize_callback' => array($this, 'sanitize_custom_css'),
                'default'           => '',
                'show_in_rest'      => false,
            )
        );
    }

    /**
     * Load the base styles and administrator CSS where highlights can appear.
     */
    public function enqueue_styles() {
        $autosuggest_enabled = (bool) get_option($this->prefixed('enable_autosuggest'), '0');

        if (!is_search() && !$autosuggest_enabled) {
            return;
        }

        $style_handle = $this->prefixed('highlighting-styles');

        wp_enqueue_style(
            $style_handle,
            plugin_dir_url(__FILE__) . 'assets/css/highlighting.css',
            array(),
            '1.0.0'
        );

        $custom_css = get_option($this->prefixed('highlighting_css'), '');
        if (is_string($custom_css) && trim($custom_css) !== '') {
            wp_add_inline_style($style_handle, $custom_css);
        }
    }

    /**
     * Remove HTML from saved CSS while preserving multiline CSS syntax.
     */
    public function sanitize_custom_css($css): string {
        if (!is_string($css)) {
            return '';
        }

        return trim(str_replace("\0", '', wp_strip_all_tags($css)));
    }

    /**
     * Ask Meilisearch to return highlighted title and excerpt values.
     */
    public function add_highlight_options(SearchQuery $search_query): SearchQuery {
        return $search_query
            ->setAttributesToHighlight(array('post_title', 'post_excerpt'))
            ->setHighlightPreTag('<mark class="scry-ms-highlight">')
            ->setHighlightPostTag('</mark>');
    }

    /**
     * Preserve Meilisearch's formatted fields before search results become WP_Post objects.
     */
    public function capture_highlights(array $results): array {
        // Each search replaces the previous request-scoped map.
        $this->highlights = array();

        foreach ($results as $result) {
            if (!is_array($result) || !isset($result['ID'], $result['_formatted']) || !is_array($result['_formatted'])) {
                continue;
            }

            $post_id = absint($result['ID']);
            if ($post_id === 0) {
                continue;
            }

            $formatted = $result['_formatted'];
            $highlighted_fields = array();

            if (isset($formatted['post_title']) && is_string($formatted['post_title'])) {
                $highlighted_fields['post_title'] = $this->sanitize_highlighted_text($formatted['post_title']);
            }

            if (isset($formatted['post_excerpt']) && is_string($formatted['post_excerpt'])) {
                $highlighted_fields['post_excerpt'] = $this->sanitize_highlighted_text($formatted['post_excerpt']);
            }

            if (!empty($highlighted_fields)) {
                $this->highlights[$post_id] = $highlighted_fields;
            }
        }

        return $results;
    }

    /**
     * Apply preserved highlights to cloned search-result posts.
     */
    public function apply_highlights(array $posts): array {
        foreach ($posts as $index => $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }

            $post_id = absint($post->ID);
            if (!isset($this->highlights[$post_id])) {
                continue;
            }

            // Avoid modifying the WP_Post instance held in WordPress's object cache.
            $highlighted_post = clone $post;
            $fields = $this->highlights[$post_id];

            if (isset($fields['post_title'])) {
                $highlighted_post->post_title = $fields['post_title'];
            }

            if (isset($fields['post_excerpt'])) {
                $highlighted_post->post_excerpt = $fields['post_excerpt'];
            }

            $posts[$index] = $highlighted_post;
        }

        return $posts;
    }

    /**
     * Remove all HTML except the mark wrapper configured on the search query.
     */
    private function sanitize_highlighted_text(string $text): string {
        return wp_kses(
            $text,
            array(
                'mark' => array(
                    'class' => true,
                ),
            )
        );
    }
}
