<?php
// Real WordPress + Elementor adapter tests. Requires Elementor activated.
function bat_builder_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function bat_builder_request($route, $data) {
    $request = new WP_REST_Request('POST', '/builder-ab/v1/' . $route);
    $request->set_header('content-type', 'application/json');
    $request->set_body(wp_json_encode($data));
    return rest_do_request($request);
}
$id = 0;
$page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Builder adapter test']);
try {
    $id = wp_insert_post(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'post_title' => 'Elements test']);
    $config = ['mode' => 'elements', 'entry' => $page, 'a' => 0, 'b' => 0, 'thank_you' => 0, 'goal' => 'click', 'selector' => '.ab-cta', 'weight' => 50, 'active' => true, 'consent' => false, 'revision' => 1];
    update_post_meta($id, '_bat_config', $config);
    $GLOBALS['wp_query'] = new WP_Query(['page_id' => $page]);
    $GLOBALS['post'] = get_post($page);
    wp_set_current_user(0);
    bat_builder_assert(WP_Block_Type_Registry::get_instance()->is_registered('builder-ab/variant'), 'Native Gutenberg variant block registered');
    $markup = '<!-- wp:builder-ab/variant ' . wp_json_encode(['experimentId' => $id, 'variant' => 'b', 'isGoal' => true]) . ' --><!-- wp:paragraph --><p>Variant B content</p><!-- /wp:paragraph --><!-- /wp:builder-ab/variant -->';
    $rendered = do_blocks($markup);
    bat_builder_assert(strpos($rendered, 'data-bat-experiment="' . $id . '"') !== false && strpos($rendered, 'data-bat-variant="b"') !== false, 'Gutenberg renders experiment and variant markers');
    bat_builder_assert(strpos($rendered, 'hidden="hidden"') !== false && preg_match('/class="[^"]*\bab-cta\b/', $rendered), 'Gutenberg baseline hides B and adds goal marker');
    bat_builder_assert(strpos($rendered, 'Variant B content') !== false, 'Inner Gutenberg content preserved');
    wp_set_current_user(1);
    bat_builder_assert(strpos(do_blocks($markup), 'hidden=') === false, 'Editor preview displays both variants');
    wp_set_current_user(0);
    bat_builder_assert(BAT_Integrations::attributes($id, 'c') === [], 'Unknown variants rejected');
    $response = bat_builder_request('assign', ['id' => $id]);
    bat_builder_assert($response->get_status() === 200 && $response->get_data()['url'] === get_permalink($page), 'Element assignment stays on the same page');
    $assignment = $response->get_data();
    $exposure = ['id' => $id, 'token' => $assignment['token'], 'type' => 'exposure', 'page' => get_permalink($page)];
    bat_builder_assert(bat_builder_request('event', $exposure)->get_data()['recorded'] === true, 'Element exposure recorded on experiment page');
    bat_builder_assert(bat_builder_request('event', array_merge($exposure, ['type' => 'conversion']))->get_data()['recorded'] === true, 'Element click conversion recorded');
    if (!defined('ELEMENTOR_VERSION')) { throw new RuntimeException('Activate Elementor before running this suite.'); }
    $widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance([
        'id' => 'bat_test_widget', 'elType' => 'widget', 'widgetType' => 'heading',
        'settings' => ['title' => 'Elementor B', '_bat_experiment' => $id, '_bat_variant' => 'b', '_bat_goal' => 'yes'],
    ]);
    $controls = $widget->get_controls();
    bat_builder_assert(isset($controls['_bat_section'], $controls['_bat_experiment'], $controls['_bat_variant'], $controls['_bat_goal']), 'Real Elementor widget receives native A/B controls');
    ob_start(); $widget->print_element(); $html = ob_get_clean();
    bat_builder_assert(strpos($html, 'data-bat-experiment="' . $id . '"') !== false && strpos($html, 'data-bat-variant="b"') !== false && strpos($html, 'hidden="hidden"') !== false, 'Real Elementor output includes variant markers and baseline visibility');
    bat_builder_assert(strpos($html, 'ab-cta') !== false && strpos($html, 'Elementor B') !== false, 'Elementor content and goal class preserved');
    bat_builder_assert(apply_filters('elementor/element/is_dynamic_content', false, [], $widget) === true, 'Elementor caching bypasses marked A/B elements');
    $config['active'] = false;
    update_post_meta($id, '_bat_config', $config);
    bat_builder_assert(isset(BAT_Integrations::attributes($id, 'b')['hidden']), 'Paused elements keep the A baseline');
    $GLOBALS['wp_query'] = new WP_Query(['page_id' => 14]);
    bat_builder_assert(BAT_Integrations::attributes($id, 'b') === [], 'Markers cannot target unrelated pages');
} finally {
    global $wpdb;
    if ($id) { $wpdb->delete(BAT_Plugin::table(), ['experiment_id' => $id], ['%d']); wp_delete_post($id, true); }
    wp_delete_post($page, true);
    wp_reset_query();
    wp_set_current_user(0);
}
