<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>


<div class="wrap p-1">

    <h1>ScryWP Search Settings</h1>

    <form method="post" action="options.php">
        <?php
            settings_fields('scry_ms_search_plugin_settings');
            do_settings_fields('scrywp-search-settings', 'scry_ms_search_plugin_settings');
        ?>
        <br>
        <?php submit_button(); ?>
    </form>
</div>

