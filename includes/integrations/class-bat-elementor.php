<?php
if (!defined('ABSPATH')) { exit; }
final class BAT_Elementor {
    public static function boot() {
        add_action('elementor/element/after_section_end', [self::class, 'controls'], 10, 3);
        add_action('elementor/frontend/before_render', [self::class, 'render']);
        // Elements may be hidden by runtime assignment; bypass cached element markup when they carry A/B settings.
        add_filter('elementor/element/is_dynamic_content', function ($dynamic, $raw_data, $element) {
            return $element->get_settings('_bat_experiment') ? true : $dynamic;
        }, 10, 3);
    }
    public static function controls($element, $section_id, $args) {
        if (!in_array($element->get_type(), ['widget', 'section', 'column', 'container'], true) || $section_id === '_bat_section' || $element->get_controls('_bat_section')) { return; }
        $element->start_controls_section('_bat_section', ['label' => 'A/B Testing', 'tab' => \Elementor\Controls_Manager::TAB_ADVANCED]);
        $element->add_control('_bat_experiment', [
            'label' => 'ID del experimento', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 0, 'default' => 0,
            'description' => 'Crea un experimento de elementos en A/B Testing y copia su ID. Usa 0 para desactivar este elemento.',
        ]);
        $element->add_control('_bat_variant', ['label' => 'Variante', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'a', 'options' => ['a' => 'A', 'b' => 'B']]);
        $element->add_control('_bat_goal', ['label' => 'Contar clics en este elemento', 'type' => \Elementor\Controls_Manager::SWITCHER, 'description' => 'Añade la clase ab-cta. Usa .ab-cta como selector del objetivo.']);
        $element->end_controls_section();
    }
    public static function render($element) {
        $data = BAT_Integrations::attributes($element->get_settings('_bat_experiment'), $element->get_settings('_bat_variant') ?: 'a');
        // Goal buttons inside a variant don't need another nested variant wrapper.
        if ($element->get_settings('_bat_goal') === 'yes') { $data['class'] = 'ab-cta'; }
        foreach ($data as $name => $value) { $element->add_render_attribute('_wrapper', $name, $value); }
    }
}
