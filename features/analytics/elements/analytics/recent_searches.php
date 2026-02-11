<?php
/**
 * Recent Searches dashboard component
 *
 * Renders a WP_List_Table of recent search analytics events.
 * $this refers to ScrySearch_AdminPageFeature (via render_admin_page),
 * so we fetch the analytics feature via get_feature().
 *
 * @package scry_ms_Search
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__DIR__) . '../includes/class-recent-searches-table.php';

$analytics_feature = $this->get_feature('scry_ms_analytics');
$table = new ScrySearch_Recent_Searches_Table($analytics_feature);
$table->prepare_items();
?>

<div class="scry-recent-searches">
    <h2><?php esc_html_e('Recent Searches', 'scry-search'); ?></h2>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : ''); ?>" />
        <?php
            $table->search_box(__('Search Terms', 'scry-search'), 'scry-search-term');
            $table->display();
        ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.scry-toggle-results').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) return;
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            target.style.display = expanded ? 'none' : 'block';
            btn.setAttribute('aria-expanded', !expanded);
            var icon = btn.querySelector('.dashicons');
            if (icon) {
                icon.className = expanded
                    ? 'dashicons dashicons-arrow-right-alt2'
                    : 'dashicons dashicons-arrow-down-alt2';
                icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;';
            }
            var textNode = btn.lastChild;
            if (textNode && textNode.nodeType === Node.TEXT_NODE) {
                textNode.textContent = expanded
                    ? ' <?php echo esc_js(__('Show Results', 'scry-search')); ?>'
                    : ' <?php echo esc_js(__('Hide Results', 'scry-search')); ?>';
            }
        });
    });
});
</script>
