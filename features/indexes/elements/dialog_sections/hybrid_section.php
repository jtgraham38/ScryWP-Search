<?php
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$index_name = isset($index['index_name']) ? (string) $index['index_name'] : '';
$id_prefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $index_name);
$hybrid = $this->get_hybrid_settings($index_name);

$embedder_names = array();
if ($hybrid['embedder'] !== '') {
    $embedder_names[] = $hybrid['embedder'];
}
?>
<div
    class="scrywp-index-settings-section scrywp-hy-hybrid-section"
    data-tab="hybrid"
    data-index-name="<?php echo esc_attr($index_name); ?>"
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
        <?php esc_html_e(
            'Choose which embedder this index should use for hybrid/semantic search, and how strongly semantic ranking should influence results. Hybrid stays off unless an embedder is selected.',
            "scry-search"
        ); ?>
    </p>

    <label class="scrywp-hy-hybrid-enabled">
        <input
            type="checkbox"
            name="hybrid_enabled"
            value="1"
            <?php checked($hybrid['enabled']); ?>
        >
        <span><?php esc_html_e('Enable hybrid / semantic search for this index', "scry-search"); ?></span>
    </label>

    <div class="scrywp-hy-hybrid-fields">
        <p>
            <label for="<?php echo esc_attr($id_prefix); ?>_hybrid_embedder">
                <?php esc_html_e('Embedder', "scry-search"); ?>
            </label><br>
            <select
                id="<?php echo esc_attr($id_prefix); ?>_hybrid_embedder"
                name="hybrid_embedder"
                class="regular-text scrywp-hy-hybrid-embedder-select"
            >
                <option value=""><?php esc_html_e('— Select an embedder —', "scry-search"); ?></option>
                <?php foreach ($embedder_names as $name) { ?>
                    <option value="<?php echo esc_attr($name); ?>" <?php selected($hybrid['embedder'], $name); ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php } ?>
            </select>
        </p>

        <?php if (empty($embedder_names)) { ?>
            <p class="description scrywp-hy-hybrid-empty-hint">
                <?php esc_html_e('No embedders on this index yet. You will add them in a later step.', "scry-search"); ?>
            </p>
        <?php } ?>

        <p>
            <label for="<?php echo esc_attr($id_prefix); ?>_hybrid_semantic_ratio">
                <?php esc_html_e('Semantic ratio', "scry-search"); ?>
            </label><br>
            <input
                type="number"
                id="<?php echo esc_attr($id_prefix); ?>_hybrid_semantic_ratio"
                name="hybrid_semantic_ratio"
                class="small-text"
                min="0"
                max="1"
                step="0.05"
                value="<?php echo esc_attr((string) $hybrid['semantic_ratio']); ?>"
            >
            <span class="description">
                <?php esc_html_e('0 = keyword only, 1 = fully semantic. Typical starting point: 0.5.', "scry-search"); ?>
            </span>
        </p>
    </div>
</div>
