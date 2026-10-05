<?php
if (!defined('ABSPATH')) { exit; }
final class BAT_Integrations {
    public static function boot() {
        require_once __DIR__ . '/integrations/class-bat-gutenberg.php';
        require_once __DIR__ . '/integrations/class-bat-elementor.php';
        require_once __DIR__ . '/integrations/class-bat-elementor-atomic.php';
        BAT_Gutenberg::boot();
        BAT_Elementor::boot();
        BAT_Elementor_Atomic::boot();
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_style('bat-variants', plugins_url('assets/variants.css', BAT_FILE), [], BAT_VERSION);
        });
    }
    public static function preview() {
        return current_user_can('edit_posts') || is_preview() || is_customize_preview() || isset($_GET['elementor-preview'])
            || (isset($_GET['bricks']) && $_GET['bricks'] === 'run')
            || (function_exists('bricks_is_frontend') && !bricks_is_frontend());
    }
    public static function attributes($id, $variant, $goal = false) {
        $id = absint($id);
        if (!$id || !in_array($variant, ['a', 'b'], true)) { return []; }
        $config = BAT_Plugin::config($id);
        $page_id = get_queried_object_id() ?: get_the_ID();
        if (!$config || $config['mode'] !== 'elements' || (int) $config['entry'] !== (int) $page_id) { return []; }
        $attributes = ['data-bat-experiment' => (string) $id, 'data-bat-variant' => $variant];
        if ($goal) { $attributes['class'] = 'ab-cta'; }
        if ($variant === 'b' && !self::preview()) { $attributes['hidden'] = 'hidden'; }
        return $attributes;
    }
    public static function html_attributes($html, array $attributes) {
        if (!$attributes || !is_string($html)) { return $html; }
        $processor = new WP_HTML_Tag_Processor($html);
        while ($processor->next_tag()) {
            if (in_array($processor->get_tag(), ['STYLE', 'SCRIPT', 'LINK', 'META'], true)) { continue; }
            foreach ($attributes as $name => $value) {
                if ($name === 'class') { $processor->add_class($value); }
                else { $processor->set_attribute($name, $value); }
            }
            return $processor->get_updated_html();
        }
        return $html;
    }
    public static function status() {
        $elementor = defined('ELEMENTOR_VERSION');
        echo '<details><summary>Integraciones de builders</summary><ul>';
        echo '<li>Gutenberg: bloque «Variante A/B» disponible.</li>';
        echo '<li>Elementor: ' . ($elementor ? 'controles clásicos y Atomic Elements incluidos (versión ' . esc_html(ELEMENTOR_VERSION) . ').' : 'adaptadores clásicos y Atomic incluidos; activa Elementor para usarlos.') . '</li>';
        echo '</ul></details>';
    }
}
