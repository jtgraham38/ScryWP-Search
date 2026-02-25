<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

$current_facet_settings = get_option(
    $this->prefixed('search_facets'),
    array('taxonomies' => array(), 'meta' => array())
);

// Support both legacy associative format and new flat array format.
$current_taxonomies = array();
if (isset($current_facet_settings['taxonomies']) && is_array($current_facet_settings['taxonomies'])) {
    foreach ($current_facet_settings['taxonomies'] as $taxonomy_key => $taxonomy_value) {
        if (is_string($taxonomy_key) && $taxonomy_key !== '' && !is_numeric($taxonomy_key)) {
            $current_taxonomies[] = sanitize_key($taxonomy_key);
            continue;
        }

        if (is_string($taxonomy_value) && $taxonomy_value !== '') {
            $current_taxonomies[] = sanitize_key($taxonomy_value);
        }
    }
}
$current_taxonomies = array_values(array_unique(array_filter($current_taxonomies)));

// Get all taxonomies, then remove excluded/internal ones.
$all_taxonomies = get_taxonomies(array(), 'names');
$excluded_taxonomies = array('post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area');
$all_taxonomies = array_values(array_diff($all_taxonomies, $excluded_taxonomies));
sort($all_taxonomies);

?>
<div class="<?php echo esc_attr($this->prefixed('search_facets_input')); ?>">
    <div class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_container')); ?>">
        <h3><?php esc_html_e('Filterable taxonomies', 'scry-search'); ?></h3>
        <p><?php esc_html_e('Select one or more taxonomies to make filterable in Meilisearch.', 'scry-search'); ?></p>

        <select
            class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_multiselect')); ?>"
            name="<?php echo esc_attr($this->prefixed('search_facets') . '[taxonomies][]'); ?>"
            multiple
            size="10"
        >
            <?php foreach ($all_taxonomies as $taxonomy_name) : ?>
                <?php
                $taxonomy_object = get_taxonomy($taxonomy_name);
                if (!$taxonomy_object) {
                    continue;
                }
                ?>
                <option
                    value="<?php echo esc_attr($taxonomy_name); ?>"
                    <?php selected(in_array($taxonomy_name, $current_taxonomies, true)); ?>
                >
                    <?php echo esc_html($taxonomy_object->label . ' (' . $taxonomy_name . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<style>
    .scry_ms_search_facets_taxonomies_multiselect {
        width: 100%;
        min-height: 16rem;
    }
</style>