<?php
// Verify report isolation and presentation using actual WordPress records.
function bat_dashboard_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
$ids = []; $pages = [];
try {
    foreach (['entry', 'a', 'b', 'thank_you'] as $name) {
        $pages[$name] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Dashboard ' . $name]);
    }
    foreach (['Measured <em>experiment</em>', 'Empty experiment'] as $title) {
        $id = wp_insert_post(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'post_title' => $title]);
        $ids[] = $id;
        update_post_meta($id, '_bat_config', $pages + ['mode' => 'pages', 'goal' => 'page', 'selector' => '', 'weight' => 50, 'active' => false, 'consent' => true, 'revision' => 2]);
    }
    global $wpdb;
    foreach ([[1, 'a', 'exposure'], [1, 'a', 'conversion'], [2, 'a', 'exposure'], [2, 'b', 'exposure'], [2, 'b', 'conversion']] as $i => $event) {
        $wpdb->insert(BAT_Plugin::table(), ['experiment_id' => $ids[0], 'revision' => $event[0], 'visitor_id' => str_pad((string) $i, 32, '0', STR_PAD_LEFT), 'variant' => $event[1], 'event_type' => $event[2], 'created_at' => gmdate('Y-m-d H:i:s')]);
    }
    $reports = BAT_Dashboard::reports(array_map('get_post', $ids));
    bat_dashboard_assert($reports[$ids[0]]['variants']['a'] === ['exposures' => 1, 'conversions' => 0], 'Reports exclude historical revisions');
    bat_dashboard_assert($reports[$ids[0]]['variants']['b'] === ['exposures' => 1, 'conversions' => 1], 'Report keeps each variant independent');
    bat_dashboard_assert($reports[$ids[1]]['variants']['a'] === ['exposures' => 0, 'conversions' => 0], 'Empty experiment has explicit zero counts');
    bat_dashboard_assert(BAT_Dashboard::reports([]) === [], 'No experiments need no aggregate query');
    ob_start(); BAT_Dashboard::render(); $html = ob_get_clean();
    bat_dashboard_assert(strpos($html, 'Measured &lt;em&gt;experiment&lt;/em&gt;') !== false && strpos($html, 'Measured <em>experiment</em>') === false, 'Experiment titles are escaped in dashboard output');
    bat_dashboard_assert(strpos($html, 'A todavía no registra conversiones; no se calcula cambio relativo.') !== false, 'Zero baseline does not produce infinite relative change');
    bat_dashboard_assert(strpos($html, 'Esperando exposiciones en ambas variantes para comparar.') !== false, 'Empty results do not imply a winning variant');
} finally {
    global $wpdb;
    foreach ($ids as $id) { $wpdb->delete(BAT_Plugin::table(), ['experiment_id' => $id], ['%d']); wp_delete_post($id, true); }
    foreach ($pages as $page) { wp_delete_post($page, true); }
}
