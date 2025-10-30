<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div>
    <input type="text" name="<?php echo $this->prefixed('index_affix'); ?>" value="<?php echo get_option($this->prefixed('index_affix')); ?>">
    <small style="display: block; margin-top: 5px;">
        A string added to the index name to help identify the index.  For most setups, you can leave this blank.  If you change this, all of your existing indexes remain unchanged, but will no longer be used.
    </small>
</div>