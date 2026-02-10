<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

//get all post types
$post_types = get_post_types(array(), 'objects');

//exclude certain useless (for purposes of ai generation) post types
$exclude = array(
    'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'acf-field-group', 'acf-field', 'wp_font_family', 'wp_font_face', 'wp_global_styles',
);
foreach ($exclude as $ex){
    unset($post_types[$ex]);
}

//load the current value of the post types setting
$post_types_setting = get_option($this->prefixed('post_types'));
?>

<div>
    <div>
        <div>
            <select
                id="<?php echo esc_attr($this->prefixed('post_types_input')); ?>"
                name="<?php echo esc_attr($this->prefixed('post_types')); ?>[]"
                multiple
                title="Select which post types our AI should use to generate its search response.  It will use the title, contents, links, media, and more to generate a response."
                required
                style="height: 12rem;"
            >
                <?php 
                $selected_post_types = is_array($post_types_setting) ? $post_types_setting : array();
                foreach ($post_types as $label => $post_type): ?>
                    <option value="<?php echo esc_attr($label); ?>" <?php echo in_array($label, $selected_post_types) ? 'selected' : ''; ?>>
                        <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>