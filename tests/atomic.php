<?php
// Integration with the installed Atomic Elements implementation.
function bat_atomic_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
$page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Atomic elements test']);
$id = 0;
try {
    $id = wp_insert_post(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'post_title' => 'Atomic elements experiment']);
    update_post_meta($id, '_bat_config', ['mode' => 'elements', 'entry' => $page, 'a' => 0, 'b' => 0, 'thank_you' => 0, 'goal' => 'click', 'selector' => '.ab-cta', 'weight' => 50, 'active' => true, 'consent' => false, 'revision' => 1]);
    $GLOBALS['wp_query'] = new WP_Query(['page_id' => $page]);
    $GLOBALS['post'] = get_post($page);
    wp_set_current_user(0);
    // Actual installed Elementor classes, not stand-ins.
    if (!defined('ELEMENTOR_VERSION')) { throw new RuntimeException('Elementor is required for atomic integration tests.'); }
    $atomic = \Elementor\Plugin::$instance->elements_manager->create_element_instance([
        'id' => 'bat_atomic_test', 'elType' => 'e-div-block', 'version' => '0.0', 'elements' => [],
        'settings' => [
            'batExperiment' => ['$$type' => 'number', 'value' => $id],
            'batVariant' => ['$$type' => 'string', 'value' => 'b'],
            'batGoal' => ['$$type' => 'boolean', 'value' => true],
            'classes' => ['$$type' => 'classes', 'value' => ['original']],
        ],
    ]);
    bat_atomic_assert((bool) $atomic && method_exists($atomic, 'get_atomic_setting'), 'Real Atomic Div Block instance created');
    $schema = $atomic::get_props_schema();
    bat_atomic_assert(isset($schema['batExperiment'], $schema['batVariant'], $schema['batGoal']), 'Atomic experiment settings participate in typed schema');
    $controls = $atomic->get_atomic_controls();
    $serialized = wp_json_encode($controls, JSON_UNESCAPED_SLASHES);
    bat_atomic_assert(strpos($serialized, 'A/B Testing') !== false && strpos($serialized, 'batExperiment') !== false, 'Atomic native controls survive schema validation');
    ob_start(); $atomic->print_element(); $output = ob_get_clean();
    bat_atomic_assert(strpos($output, 'data-bat-experiment="' . $id . '"') !== false && strpos($output, 'data-bat-variant="b"') !== false && strpos($output, 'hidden="hidden"') !== false, 'Atomic Twig template renders real A/B attributes');
    bat_atomic_assert(strpos($output, 'original') !== false && strpos($output, 'ab-cta') !== false, 'Atomic goal preserves existing typed classes');
    $saved = $atomic->get_data_for_save();
    bat_atomic_assert($saved['settings']['batExperiment']['value'] === $id && $saved['settings']['batVariant']['value'] === 'b', 'Atomic A/B settings survive native save validation');
} finally {
    if ($id) { wp_delete_post($id, true); }
    wp_delete_post($page, true);
    wp_reset_query(); wp_set_current_user(0);
}
