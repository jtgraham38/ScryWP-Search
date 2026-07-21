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
     * Register feature filters.
     */
    public function add_filters() {
        add_filter(
            $this->prefixed('multi_search_query'),
            array($this, 'add_highlight_options')
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
}
