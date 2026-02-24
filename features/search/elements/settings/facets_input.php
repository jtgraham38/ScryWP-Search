<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*
get current facet settings
it is an array of the following format:
array(
    'taxonomies' => array(
        'taxonomy_name' => [
            'taxonomy_term_1',
            'taxonomy_term_2',
            'taxonomy_term_3',
            ...
        ],
    ),
    'meta' => array(
        (tbd)
    ),
);
*/
$current_facet_settings = get_option($this->prefixed('search_facets'), array( 'taxonomies' => array(), 'meta' => array() ));

//get all taxonomies
$all_taxonomies = get_taxonomies( [], 'names' );
$excluded_taxonomies = array( 'post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area',  );  //taxonomies to exclude

//remove excluded taxonomies from all taxonomies
$all_taxonomies = array_diff($all_taxonomies, $excluded_taxonomies);

//for each taxonomy, get its metadata, and all terms for that taxonomy, keeping only taxonomies with terms
$all_taxonomies_metadata = array();
foreach ($all_taxonomies as $taxonomy) {
    $metadata = get_taxonomy($taxonomy);
    $terms = [];
    if ($metadata) {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]);
    }

    //only include taxonomies with metadata and terms
    if ($metadata && $terms) {
        $all_taxonomies_metadata[$taxonomy] = array(
            'metadata' => $metadata,
            'terms' => $terms,
        );
    }
}

?>
<div class="<?php echo esc_attr($this->prefixed('search_facets_input')); ?>">
    <ul class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_list')); ?>">
        <?php 
        foreach ($all_taxonomies_metadata as $taxonomy => $data):
            $metadata = $data['metadata'];
            $terms = $data['terms'];
        ?>
            <li class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_item')); ?>">
                <details>
                    <summary>
                        <input 
                            class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_checkbox')); ?> <?php echo esc_attr($this->prefixed('search_facet_enabled_taxonomies_input_checkbox')); ?>"
                            type="checkbox" 
                            name="<?php echo esc_attr($this->prefixed('search_facets') . '[taxonomies][' . $taxonomy . ']'); ?>" 
                            value="1" 
                            <?php checked(isset($current_facet_settings['taxonomies'][$taxonomy]), true); ?>
                        >
                        <span class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_label')); ?>"><?php echo esc_html($metadata->label); ?> (<?php echo esc_html($taxonomy); ?>)</span>
                    </summary>
                    <ul class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_terms_list')); ?>">
                        <?php foreach ($terms as $term): ?>
                            <li>
                                <input 
                                    class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_checkbox')); ?>"
                                    type="checkbox" 
                                    name="<?php echo esc_attr($this->prefixed('search_facets') . '[taxonomies][' . $taxonomy . '][' . $term->term_id . ']'); ?>" 
                                    value="1" 
                                    <?php checked(isset($current_facet_settings['taxonomies'][$taxonomy][$term->term_id]), true); ?>
                                >
                                <span class="<?php echo esc_attr($this->prefixed('search_facets_taxonomies_input_label')); ?>">
                                    <?php echo esc_html($term->name); ?>
                                    <span class="<?php echo esc_attr($this->prefixed('search_facet_term_count')); ?>">(<?php echo esc_html($term->count); ?>)</span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<script>

//apply logic
document.addEventListener('DOMContentLoaded', function() {
    const facetsInput = document.querySelector('.' + '<?php echo esc_attr($this->prefixed('search_facets_input')); ?>');
    

    //get all the taxonomy groups
    const taxonomyGroups = facetsInput.querySelectorAll('.scry_ms_search_facets_taxonomies_input_item');
    taxonomyGroups.forEach(group => {
        taxonomyGroup(group);
    });
});

//define how each group of checkboxes should work
function taxonomyGroup(scry_ms_search_facets_input_item){
    //get the master checkbox for the taxonomy, which determines whether this taxonomy is enabled or disabled
    const enabledCheckbox = scry_ms_search_facets_input_item.querySelector('.scry_ms_search_facet_enabled_taxonomies_input_checkbox');

    //get the list of terms
    const termsList = scry_ms_search_facets_input_item.querySelector('.scry_ms_search_facets_taxonomies_input_terms_list');
    if (termsList) {
        const terms = termsList.querySelectorAll('.scry_ms_search_facets_taxonomies_input_checkbox');
        terms.forEach(term => {
            term.disabled = !enabledCheckbox.checked;
        });
    }

    //get each of the term checkboxes within the terms list
    const termCheckboxes = termsList.querySelectorAll('.scry_ms_search_facets_taxonomies_input_checkbox');

    //add an event listener for changes to the enabled checkbox
    //when it is checked, enable all the term checkboxes
    //when it is unchecked, disable and uncheck all the term checkboxes
    enabledCheckbox.addEventListener('change', function() {
        if (enabledCheckbox.checked) {
            termCheckboxes.forEach(checkbox => {
                checkbox.disabled = false;
            });
        } else {
            termCheckboxes.forEach(checkbox => {
                checkbox.disabled = true;
                checkbox.checked = false;
            });
        }
    });

}

</script>

<style>
    /* main container */
    .scry_ms_search_facets_taxonomies_input {

    }

    /* list of taxonomies */
    .scry_ms_search_facets_taxonomies_input_list {
    }

    /* each taxonomy */
    .scry_ms_search_facets_taxonomies_input_item {
        margin-bottom: 0.5rem;
    }

    /* contents of summary for each taxonomy dropdown */
    .scry_ms_search_facets_taxonomies_input_item summary {
        padding: 0.5rem;
    }

    /* list of terms for each taxonomy */
    .scry_ms_search_facets_taxonomies_input_terms_list {
        padding-left: 2.5rem;
    }

    /* checkbox for each term */
    .scry_ms_search_facets_taxonomies_input_checkbox {
        margin-right: 0.5rem;
    }

    /* label for each term */
    .scry_ms_search_facets_taxonomies_input_label {
        font-weight: bold;
    }
</style>