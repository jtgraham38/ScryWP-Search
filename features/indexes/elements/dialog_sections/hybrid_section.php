<?php
/**
 * Configure Index → Hybrid Search tab.
 *
 * Storage:
 * - Enable / selected embedder name / ratio → WordPress backup (name= fields, Save Settings).
 * - Embedder definitions (model, URL, key) → Meilisearch for THIS index only (JS + AJAX).
 *
 * This file is included from index_settings_dialog.php, which is itself included from
 * the admin page. $this is the admin-page feature, not indexes — use $indexes_feature.
 *
 * The add/update block is a <div>, not a <form>: we are already inside the Configure Index
 * <form>. CRUD inputs have no name= so Save Settings will not POST them. Buttons are
 * type="button" so they do not submit that parent form. IDs use $id_prefix so post/page
 * dialogs do not share the same id.
 */
//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$index_name = isset($index['index_name']) ? (string) $index['index_name'] : '';
$id_prefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $index_name);
$hybrid = $indexes_feature->get_hybrid_settings($index_name);
$embedder_sources = (array) $indexes_feature->config('embedder_sources');
$default_document_template = (string) $indexes_feature->config('default_document_template');

// JS replaces this list from Meilisearch; keep the saved name so the <select> is not empty before AJAX.
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
                data-selected="<?php echo esc_attr($hybrid['embedder']); ?>"
            >
                <option value=""><?php esc_html_e('— Select an embedder —', "scry-search"); ?></option>
                <?php foreach ($embedder_names as $name) { ?>
                    <option value="<?php echo esc_attr($name); ?>" <?php selected($hybrid['embedder'], $name); ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php } ?>
            </select>
        </p>

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

    <?php // Horizontal rule: prefs above, Meilisearch embedder CRUD below. ?>
    <hr class="scrywp-hy-hybrid-separator">

    <div class="scrywp-hy-embedders">
        <h5 class="scrywp-hy-embedders-heading"><?php esc_html_e('Embedders on this index', "scry-search"); ?></h5>
        <p class="description">
            <?php esc_html_e('Definitions live in Meilisearch for this index only. Saving an embedder does not copy it to other indexes.', "scry-search"); ?>
        </p>
        <div class="scrywp-hy-embedders-status" aria-live="polite"></div>
        <div class="scrywp-hy-embedders-list">
            <p class="description scrywp-hy-embedders-loading">
                <?php esc_html_e('Open this dialog to load embedders…', "scry-search"); ?>
            </p>
        </div>

        <h5 class="scrywp-hy-embedders-heading"><?php esc_html_e('Add / update embedder', "scry-search"); ?></h5>
        <?php // Not a <form>: nested forms inside Configure Index are invalid and steal Save Settings. ?>
        <div class="scrywp-hy-embedder-form" autocomplete="off">
            <p>
                <label for="<?php echo esc_attr($id_prefix); ?>_hy_embedder_name">
                    <?php esc_html_e('Name', "scry-search"); ?>
                </label><br>
                <input
                    type="text"
                    id="<?php echo esc_attr($id_prefix); ?>_hy_embedder_name"
                    class="regular-text scrywp-hy-embedder-name"
                    placeholder="default"
                    pattern="[A-Za-z0-9_-]+"
                >
                <span class="description"><?php esc_html_e('Letters, numbers, underscore, hyphen. This is what the dropdown above selects.', "scry-search"); ?></span>
            </p>

            <p>
                <label for="<?php echo esc_attr($id_prefix); ?>_hy_embedder_source">
                    <?php esc_html_e('Source', "scry-search"); ?>
                </label><br>
                <select
                    id="<?php echo esc_attr($id_prefix); ?>_hy_embedder_source"
                    class="regular-text scrywp-hy-embedder-source"
                >
                    <?php foreach ($embedder_sources as $source) { ?>
                        <option value="<?php echo esc_attr($source); ?>" <?php selected($source, 'openAi'); ?>>
                            <?php echo esc_html($source); ?>
                        </option>
                    <?php } ?>
                </select>
            </p>

            <p class="scrywp-hy-field-model">
                <label for="<?php echo esc_attr($id_prefix); ?>_hy_embedder_model">
                    <?php esc_html_e('Model', "scry-search"); ?>
                </label><br>
                <input
                    type="text"
                    id="<?php echo esc_attr($id_prefix); ?>_hy_embedder_model"
                    class="regular-text scrywp-hy-embedder-model"
                    placeholder="text-embedding-3-small"
                >
            </p>

            <p class="scrywp-hy-field-api-key">
                <label for="<?php echo esc_attr($id_prefix); ?>_hy_embedder_api_key">
                    <?php esc_html_e('API key', "scry-search"); ?>
                </label><br>
                <input
                    type="password"
                    id="<?php echo esc_attr($id_prefix); ?>_hy_embedder_api_key"
                    class="regular-text scrywp-hy-embedder-api-key"
                    value=""
                    autocomplete="new-password"
                >
                <span class="description"><?php esc_html_e('Leave blank when editing to keep the existing key.', "scry-search"); ?></span>
            </p>

            <p class="scrywp-hy-field-url" hidden>
                <?php // hidden until JS sees source=ollama. localhost inside Docker is the Meili container, not the laptop. ?>
                <label for="<?php echo esc_attr($id_prefix); ?>_hy_embedder_url">
                    <?php esc_html_e('URL', "scry-search"); ?>
                </label><br>
                <input
                    type="url"
                    id="<?php echo esc_attr($id_prefix); ?>_hy_embedder_url"
                    class="regular-text scrywp-hy-embedder-url"
                    placeholder="http://localhost:11434/api/embeddings"
                >
                <span class="description"><?php esc_html_e('Used for ollama. Docker Meilisearch often needs http://host.docker.internal:11434/api/embeddings.', "scry-search"); ?></span>
            </p>

            <p class="scrywp-hy-field-dimensions" hidden>
                <label for="<?php echo esc_attr($id_prefix); ?>_hy_embedder_dimensions">
                    <?php esc_html_e('Dimensions', "scry-search"); ?>
                </label><br>
                <input
                    type="number"
                    id="<?php echo esc_attr($id_prefix); ?>_hy_embedder_dimensions"
                    class="small-text scrywp-hy-embedder-dimensions"
                    min="1"
                    step="1"
                    value=""
                >
                <span class="description"><?php esc_html_e('Required for userProvided.', "scry-search"); ?></span>
            </p>

            <p class="scrywp-hy-field-template">
                <label for="<?php echo esc_attr($id_prefix); ?>_hy_embedder_template">
                    <?php esc_html_e('Document template', "scry-search"); ?>
                </label><br>
                <textarea
                    id="<?php echo esc_attr($id_prefix); ?>_hy_embedder_template"
                    class="large-text code scrywp-hy-embedder-template"
                    rows="3"
                ><?php echo esc_textarea($default_document_template); ?></textarea>
                <span class="description"><?php esc_html_e('Liquid-style template over indexed fields, e.g. {{doc.post_title}}.', "scry-search"); ?></span>
            </p>

            <p class="scrywp-hy-embedder-form-actions">
                <?php // type="button": Enter in these fields must not submit Configure Index Save Settings. ?>
                <button type="button" class="button button-primary scrywp-hy-embedder-save">
                    <?php esc_html_e('Save embedder', "scry-search"); ?>
                </button>
                <button type="button" class="button button-secondary scrywp-hy-embedder-reset">
                    <?php esc_html_e('Clear form', "scry-search"); ?>
                </button>
            </p>
        </div>
    </div>
</div>
