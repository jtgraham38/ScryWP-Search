<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get the indexes feature to access indexed post types
$indexes_feature = $this->get_feature('scrywp_indexes');
$indexed_post_types = array();

if ($indexes_feature) {
    // Get indexed post types from the indexes feature
    $indexed_post_types = get_option($indexes_feature->prefixed('post_types'), array());
}

// Get current weights
$current_weights = get_option($this->prefixed('search_weights'), array());

// Get all post type objects for display
$all_post_types = get_post_types(array(), 'objects');

// Filter to only show indexed post types
$post_types_to_display = array();
if (!empty($indexed_post_types) && is_array($indexed_post_types)) {
    foreach ($indexed_post_types as $post_type_slug) {
        if (isset($all_post_types[$post_type_slug])) {
            $post_types_to_display[$post_type_slug] = $all_post_types[$post_type_slug];
        }
    }
}
?>

<div class="scrywp-search-weights">
    <?php if (empty($post_types_to_display)): ?>
        <p class="description">
            <?php esc_html_e('No post types are currently indexed. Please configure indexed post types in the Index Settings page first.', "meilisearch_wp"); ?>
        </p>
    <?php else: ?>
        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ($post_types_to_display as $post_type_slug => $post_type_obj): ?>
                    <?php
                    // Get current weight for this post type, default to 1.0
                    $current_weight = isset($current_weights[$post_type_slug]) 
                        ? floatval($current_weights[$post_type_slug]) 
                        : 1.0;
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($this->prefixed('search_weights_' . $post_type_slug)); ?>">
                                <?php echo esc_html($post_type_obj->label); ?>
                                <span class="description">(<?php echo esc_html($post_type_slug); ?>)</span>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="number" 
                                id="<?php echo esc_attr($this->prefixed('search_weights_' . $post_type_slug)); ?>"
                                name="<?php echo esc_attr($this->prefixed('search_weights') . '[' . $post_type_slug . ']'); ?>"
                                value="<?php echo esc_attr($current_weight); ?>"
                                step="0.1"
                                min="0"
                                class="regular-text"
                            />
                            <p class="description">
                                <?php esc_html_e('Weight for this post type in federated searches. Higher values prioritize results from this post type. Default: 1.0', "meilisearch_wp"); ?>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

