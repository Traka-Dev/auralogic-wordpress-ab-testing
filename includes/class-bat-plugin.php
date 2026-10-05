<?php
if (!defined('ABSPATH')) { exit; }
final class BAT_Plugin {
    public static function boot() {
        add_action('init', function () {
            register_post_type('bat_experiment', ['public' => false, 'show_ui' => false, 'supports' => ['title']]);
        });
        BAT_Admin::boot();
        BAT_REST::boot();
        BAT_Integrations::boot();
        add_action('wp_enqueue_scripts', [self::class, 'scripts']);
        do_action('bat_register_integrations');
    }
    public static function table() { global $wpdb; return $wpdb->prefix . 'bat_events'; }
    public static function install($network_wide = false) {
        if ($network_wide) { wp_die('Activa A/B Testing individualmente en cada sitio; la activación de red aún no está disponible.'); }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collation = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            experiment_id bigint unsigned NOT NULL,
            revision bigint unsigned NOT NULL,
            visitor_id varchar(32) NOT NULL,
            variant varchar(1) NOT NULL,
            event_type varchar(16) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY visitor_event (experiment_id,revision,visitor_id,event_type),
            KEY report (experiment_id,revision,variant,event_type)
        ) $collation;");
    }
    public static function config($id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'bat_experiment' || $post->post_status !== 'publish') { return null; }
        $config = get_post_meta($id, '_bat_config', true);
        return is_array($config) ? array_merge(['mode' => 'pages'], $config) : null;
    }
    public static function active() {
        $items = [];
        foreach (get_posts(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
            $config = self::config($post->ID);
            if ($config && self::runnable($config)) { $items[$post->ID] = $config; }
        }
        return $items;
    }
    public static function runnable(array $config) {
        if (!$config['active']) { return false; }
        $fields = ($config['mode'] ?? 'pages') === 'elements' ? ['entry'] : ['entry', 'a', 'b'];
        if ($config['goal'] === 'page') { $fields[] = 'thank_you'; }
        foreach ($fields as $field) {
            if (get_post_type($config[$field]) !== 'page' || get_post_status($config[$field]) !== 'publish') { return false; }
        }
        return true;
    }
    public static function scripts() {
        if (BAT_Integrations::preview() || is_feed()) { return; }
        $experiments = [];
        foreach (self::active() as $id => $config) {
            // Only load on the entry, variants and thank-you pages.
            if (!is_page([$config['entry'], $config['a'], $config['b'], $config['thank_you']])) { continue; }
            $experiments[] = [
                'id' => $id, 'revision' => $config['revision'], 'mode' => $config['mode'], 'entry' => get_permalink($config['entry']),
                'a' => $config['a'] ? get_permalink($config['a']) : '', 'b' => $config['b'] ? get_permalink($config['b']) : '',
                'thankYou' => $config['thank_you'] ? get_permalink($config['thank_you']) : '',
                'selector' => $config['selector'], 'goal' => $config['goal'], 'consent' => $config['consent'],
            ];
        }
        if (!$experiments) { return; }
        wp_enqueue_script('bat-tracking', plugins_url('assets/tracking.js', BAT_FILE), [], BAT_VERSION, false);
        wp_add_inline_script('bat-tracking', 'window.BAT_CONFIG=' . wp_json_encode([
            'endpoint' => rest_url('builder-ab/v1/'), 'experiments' => $experiments,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }
    public static function sign(array $payload) {
        $body = rtrim(strtr(base64_encode(wp_json_encode($payload)), '+/', '-_'), '=');
        return $body . '.' . hash_hmac('sha256', $body, wp_salt('auth'));
    }
    public static function verify($token, $id, array $config) {
        if (!is_string($token) || strlen($token) > 1024) { return null; }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], wp_salt('auth')), $parts[1])) { return null; }
        $data = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        if (!is_array($data) || ($data['id'] ?? null) !== (int) $id || ($data['revision'] ?? null) !== (int) $config['revision'] || !is_int($data['expires'] ?? null) || $data['expires'] <= time() || !in_array($data['variant'] ?? '', ['a', 'b'], true) || !preg_match('/^[a-f0-9]{32}$/', $data['visitor'] ?? '')) { return null; }
        return $data;
    }
}
