<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap p-1">

    <h1>Embeddings Settings</h1>

    <form method="post" action="options.php">

        <?php
            settings_fields('scrywp_search_embeddings_settings');
            do_settings_fields('scrywp-search-embeddings', 'scrywp_search_embeddings_settings');
        ?>
        <?php submit_button(); ?>
    </form>

    <hr>


    <?php
    //get the embeddings method
    $embeddings_method = get_option($this->prefixed('chunking_method'));
    if ($embeddings_method != 'none' && $embeddings_method != '') {
        require_once plugin_dir_path(__FILE__) . 'embeddings_explorer.php';
    } else{
        echo '<p>Select a content embedding method to enable embeddings.</p>';
    }
     ?>
</div>
