<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div
    class="scrywp-index-settings-section scrywp-hy-hybrid-section"
    data-tab="hybrid"
    data-index-name="<?php echo esc_attr($index['index_name']); ?>"
>
    <div class="scrywp-index-settings-section-header">
        <h4><?php esc_html_e('Hybrid Search', "scry-search"); ?></h4>
        <a
            href="https://www.meilisearch.com/docs/learn/experimental/vector_search"
            target="_blank"
            class="scrywp-index-settings-help-link"
            title="<?php esc_attr_e('Learn more about hybrid search', "scry-search"); ?>"
        >
            <?php esc_html_e('Learn more', "scry-search"); ?>
            <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 4px;"></span>
        </a>
    </div>
    <p class="description">
        <?php esc_html_e('Configure hybrid / semantic search for this index.', "scry-search"); ?>
    </p>
</div>
