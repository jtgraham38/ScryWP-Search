<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div>
    <input type="text" name="<?php echo $this->prefixed('index_affix'); ?>" value="<?php echo get_option($this->prefixed('index_affix')); ?>">
    <small style="display: block; margin-top: 5px;">
        A string added to the index name to help identify the index, useful in multi-tenant setups.
        For most websites, you can simplyleave this blank.  
        If you change this, all of your existing indexes will remain unchanged, but will no longer be used.
    </small>
</div>