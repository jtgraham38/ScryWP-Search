<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap p-1">
    <h1>Indexes</h1>

    <?php require_once plugin_dir_path(__FILE__) . 'show_indexes.php'; ?>

    <form method="post" action="options.php">
        <?php
            settings_fields($this->prefixed('indexes_settings_group'));
            do_settings_sections($this->prefixed('indexes_settings_group'));
        ?>
        <br>
        <?php submit_button(); ?>
    </form>
</div>