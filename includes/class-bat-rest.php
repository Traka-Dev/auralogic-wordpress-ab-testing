<?php
if (!defined('ABSPATH')) { exit; }
final class BAT_REST {
    public static function boot() {
        add_action('rest_api_init', function () {
            foreach (['assign', 'event'] as $route) {
                register_rest_route('builder-ab/v1', '/' . $route, [
                    'methods' => 'POST', 'callback' => [self::class, $route],
                    'permission_callback' => [self::class, 'permission'],
                    'args' => [
                        'id' => ['required' => true, 'validate_callback' => function ($value) { return is_numeric($value) && (int) $value > 0; }],
                        'token' => ['default' => '', 'validate_callback' => function ($value) { return is_string($value) && strlen($value) <= 1024; }],
                    ],
                ]);
            }
        });
    }
    public static function permission($request) {
        // JSON-only endpoints prevent simple cross-site form submissions. Never use an admin nonce for anonymous tracking.
        if (stripos($request->get_header('content-type'), 'application/json') !== 0) { return new WP_Error('bat_content_type', 'Se requiere JSON.', ['status' => 415]); }
        $origin = $request->get_header('origin');
        if ($origin && rtrim($origin, '/') !== self::origin(home_url())) { return new WP_Error('bat_origin', 'Origen no permitido.', ['status' => 403]); }
        // Basic abuse control. Only the server connection address is trusted, never forwarded headers.
        $bucket = 'bat_rate_' . hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . gmdate('YmdHi'), wp_salt('auth'));
        $count = (int) get_transient($bucket);
        if ($count >= 300) { return new WP_Error('bat_rate', 'Demasiadas solicitudes. Inténtalo más tarde.', ['status' => 429]); }
        set_transient($bucket, $count + 1, 2 * MINUTE_IN_SECONDS);
        return true;
    }
    private static function origin($url) {
        $parts = wp_parse_url($url);
        return ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
    private static function response($data) {
        $response = new WP_REST_Response($data);
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }
    public static function assign($request) {
        $id = (int) $request['id'];
        $config = BAT_Plugin::config($id);
        if (!$config || !BAT_Plugin::runnable($config)) { return new WP_Error('bat_inactive', 'Experimento inactivo o páginas no publicadas.', ['status' => 404]); }
        $data = BAT_Plugin::verify($request['token'], $id, $config);
        if (!$data) {
            $data = ['id' => $id, 'revision' => (int) $config['revision'], 'visitor' => bin2hex(random_bytes(16)),
                'variant' => random_int(1, 100) <= $config['weight'] ? 'a' : 'b', 'expires' => time() + 90 * DAY_IN_SECONDS];
        }
        $destination = $config['mode'] === 'elements' ? $config['entry'] : $config[$data['variant']];
        return self::response(['token' => BAT_Plugin::sign($data), 'variant' => $data['variant'], 'expires' => $data['expires'], 'url' => get_permalink($destination)]);
    }
    public static function event($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $config = BAT_Plugin::config($id);
        $type = $request->get_param('type');
        if (!$config || !BAT_Plugin::runnable($config)) { return new WP_Error('bat_inactive', 'Experimento inactivo o páginas no publicadas.', ['status' => 404]); }
        $data = BAT_Plugin::verify($request['token'], $id, $config);
        if (!$data || !in_array($type, ['exposure', 'conversion'], true)) { return new WP_Error('bat_event', 'Evento inválido.', ['status' => 400]); }
        $page = $request->get_param('page');
        $expected = $config['mode'] === 'elements' ? $config['entry'] : $config[$data['variant']];
        if ($type === 'conversion' && $config['goal'] === 'page') { $expected = $config['thank_you']; }
        // URL assertion prevents accidental attribution; browser events are not proof of a purchase.
        if (!is_string($page) || self::page_key($page) !== self::page_key(get_permalink($expected))) { return new WP_Error('bat_page', 'Página inválida.', ['status' => 400]); }
        $table = BAT_Plugin::table();
        if ($type === 'conversion') {
            $exposed = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE experiment_id=%d AND revision=%d AND visitor_id=%s AND event_type='exposure'", $id, $data['revision'], $data['visitor']));
            if (!$exposed) { return new WP_Error('bat_not_exposed', 'No existe exposición previa.', ['status' => 409]); }
        }
        $result = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (experiment_id,revision,visitor_id,variant,event_type,created_at) VALUES (%d,%d,%s,%s,%s,%s)", $id, $data['revision'], $data['visitor'], $data['variant'], $type, gmdate('Y-m-d H:i:s')));
        if ($result === false) { return new WP_Error('bat_storage', 'No se pudo guardar el evento.', ['status' => 500]); }
        return self::response(['recorded' => $result === 1]);
    }
    private static function page_key($url) {
        $parts = wp_parse_url($url);
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        // Plain permalinks identify pages by query; marketing parameters do not.
        $identity = array_intersect_key($query, array_flip(['page_id', 'p', 'pagename']));
        ksort($identity);
        return self::origin($url) . rtrim($parts['path'] ?? '/', '/') . '?' . http_build_query($identity);
    }
}
