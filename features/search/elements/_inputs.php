<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap p-1">
    <h1><?php esc_html_e('Search Settings', "scry-search"); ?></h1>

    <form method="post" action="options.php">
        <?php
            settings_fields($this->prefixed('search_settings_group'));
            do_settings_sections($this->prefixed('search_settings_group'));
        ?>
        <br>
        <?php submit_button(); ?>
    </form>
</div>