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
        // Settings and frontend assets will be registered in later steps.
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
