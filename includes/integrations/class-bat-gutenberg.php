<?php
if (!defined('ABSPATH')) { exit; }
final class BAT_Gutenberg {
    public static function boot() {
        add_action('init', function () {
            wp_register_script('bat-gutenberg', plugins_url('assets/gutenberg.js', BAT_FILE), ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element'], BAT_VERSION, true);
            wp_register_style('bat-gutenberg-editor', plugins_url('assets/gutenberg-editor.css', BAT_FILE), [], BAT_VERSION);
            register_block_type('builder-ab/variant', [
                'api_version' => 3,
                'title' => 'Variante A/B', 'category' => 'design', 'icon' => 'randomize',
                'attributes' => [
                    'experimentId' => ['type' => 'number', 'default' => 0],
                    'variant' => ['type' => 'string', 'default' => 'a', 'enum' => ['a', 'b']],
                    'isGoal' => ['type' => 'boolean', 'default' => false],
                ],
                'editor_script' => 'bat-gutenberg', 'editor_style' => 'bat-gutenberg-editor',
                'render_callback' => [self::class, 'render'],
                'supports' => ['html' => false, 'multiple' => true],
            ]);
        });
    }
    public static function render($attributes, $content) {
        $data = BAT_Integrations::attributes($attributes['experimentId'] ?? 0, $attributes['variant'] ?? 'a', !empty($attributes['isGoal']));
        return '<div ' . get_block_wrapper_attributes($data) . '>' . $content . '</div>';
    }
}
