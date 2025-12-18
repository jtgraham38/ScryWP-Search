<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div>
    <input type="text" name="<?php echo esc_attr($this->prefixed('index_affix')); ?>" value="<?php echo esc_attr(get_option($this->prefixed('index_affix'))); ?>">
    <small style="display: block; margin-top: 5px;">
        A string added to the index name to help identify the index, useful in multi-tenant setups.
        For most websites, you can simply leave this blank.  
        If you change this, all of your existing indexes will remain unchanged, but will no longer be used.
    </small>
</div>