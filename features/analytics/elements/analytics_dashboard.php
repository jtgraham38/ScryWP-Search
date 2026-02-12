<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

?>
<h1><?php esc_html_e('Search Analytics', "scry-search"); ?></h1>
<?php

require_once plugin_dir_path(__FILE__) . 'analytics/summary_cards.php';

require_once plugin_dir_path(__FILE__) . 'analytics/charts.php';

require_once plugin_dir_path(__FILE__) . 'analytics/recent_searches.php';

require_once plugin_dir_path(__FILE__) . '_inputs.php';