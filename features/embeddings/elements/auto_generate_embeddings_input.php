<?php

if (!defined('ABSPATH')) {
    exit;
}

$value =  get_option($this->prefixed('auto_generate_embeddings'));
?>
<div>
    <input
        id="<?php $this->pre('auto_generate_embeddings') ?>"
        name="<?php $this->pre('auto_generate_embeddings') ?>"
        title="Select whether posts without embeddings should be automatically enqueued for embedding generation.  If this is set, posts without embeddings will be automatically enqueued for embedding generation.  If this is not set, no posts will be automatically enqueued for embedding generation."
        type="checkbox"
        <?php echo esc_attr($value) ? "checked" : "" ?>
    >
</div>