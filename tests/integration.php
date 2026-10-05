<?php
// Run with wp eval-file after activating the plugin in a disposable WordPress installation.
function bat_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function bat_request($route, $data) {
    $request = new WP_REST_Request('POST', '/builder-ab/v1/' . $route);
    $request->set_header('content-type', 'application/json');
    $request->set_header('origin', 'http://localhost:8098');
    $request->set_body(wp_json_encode($data));
    return rest_do_request($request);
}
$pages = [];
$id = 0;
try {
    foreach (['entry', 'a', 'b', 'thank_you'] as $name) {
        $pages[$name] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'BAT test ' . $name]);
    }
    $id = wp_insert_post(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'post_title' => 'Integration test']);
    $config = $pages + ['goal' => 'page', 'selector' => '', 'weight' => 50, 'active' => true, 'consent' => false, 'revision' => 1];
    update_post_meta($id, '_bat_config', $config);
    $response = bat_request('assign', ['id' => $id]);
    bat_assert($response->get_status() === 200, 'Assignment endpoint works in WordPress');
    $assignment = $response->get_data();
    bat_assert(in_array($assignment['variant'], ['a', 'b'], true), 'Assignment picks a valid variant');
    $repeat = bat_request('assign', ['id' => $id, 'token' => $assignment['token']])->get_data();
    bat_assert($repeat['token'] === $assignment['token'], 'Repeat visits keep the same visitor and variant');
    $variant_page = get_permalink($pages[$assignment['variant']]);
    $conversion = ['id' => $id, 'token' => $assignment['token'], 'type' => 'conversion', 'page' => get_permalink($pages['thank_you'])];
    bat_assert(bat_request('event', $conversion)->get_status() === 409, 'Conversion requires prior exposure');
    $exposure = ['id' => $id, 'token' => $assignment['token'], 'type' => 'exposure', 'page' => $variant_page];
    bat_assert(bat_request('event', $exposure)->get_data()['recorded'] === true, 'Exposure recorded');
    bat_assert(bat_request('event', $exposure)->get_data()['recorded'] === false, 'Reloads do not duplicate exposure');
    bat_assert(bat_request('event', $conversion)->get_data()['recorded'] === true, 'Thank-you conversion recorded');
    bat_assert(bat_request('event', $conversion)->get_data()['recorded'] === false, 'Conversion is deduplicated');
    bat_assert(bat_request('event', array_merge($exposure, ['page' => get_permalink($pages['entry'])]))->get_status() === 400, 'Wrong page rejected');
    bat_assert(bat_request('event', array_merge($exposure, ['token' => $assignment['token'] . 'x']))->get_status() === 400, 'Tampered signature rejected');
    $payload = BAT_Plugin::verify($assignment['token'], $id, $config);
    $payload['expires'] = time() - 1;
    bat_assert(bat_request('event', array_merge($exposure, ['token' => BAT_Plugin::sign($payload)]))->get_status() === 400, 'Expired token rejected');
    $config['revision'] = 2;
    update_post_meta($id, '_bat_config', $config);
    bat_assert(bat_request('event', $exposure)->get_status() === 400, 'Old revisions cannot contaminate current results');
    wp_update_post(['ID' => $pages['a'], 'post_status' => 'draft']);
    bat_assert(bat_request('assign', ['id' => $id])->get_status() === 404, 'Unpublished variants stop assignment');
    wp_update_post(['ID' => $pages['a'], 'post_status' => 'publish']);
    $config['active'] = false;
    update_post_meta($id, '_bat_config', $config);
    bat_assert(bat_request('assign', ['id' => $id])->get_status() === 404, 'Paused experiments stop assignment');
    $request = new WP_REST_Request('POST', '/builder-ab/v1/assign');
    $request->set_header('content-type', 'application/json');
    $request->set_header('origin', 'https://external.example');
    $request->set_body(wp_json_encode(['id' => $id]));
    bat_assert(rest_do_request($request)->get_status() === 403, 'Cross-origin requests rejected');
    $request->set_header('origin', 'http://localhost:8098');
    $request->set_header('content-type', 'application/x-www-form-urlencoded');
    $request->set_body_params(['id' => $id]);
    bat_assert(rest_do_request($request)->get_status() === 415, 'Form submissions rejected');
} finally {
    global $wpdb;
    if ($id) {
        $wpdb->delete(BAT_Plugin::table(), ['experiment_id' => $id], ['%d']);
        wp_delete_post($id, true);
    }
    foreach ($pages as $page) { wp_delete_post($page, true); }
}
