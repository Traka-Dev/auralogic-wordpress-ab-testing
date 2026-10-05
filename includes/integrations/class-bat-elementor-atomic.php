<?php
if (!defined('ABSPATH')) { exit; }
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
final class BAT_Elementor_Atomic {
    private static $buffers = [];
    public static function boot() {
        add_filter('elementor/atomic-widgets/props-schema', [self::class, 'schema']);
        add_filter('elementor/atomic-widgets/controls', [self::class, 'controls'], 10, 2);
        add_action('elementor/frontend/before_render', [self::class, 'render']);
        add_action('elementor/frontend/after_render', [self::class, 'finish'], 100);
        add_filter('elementor/element/is_dynamic_content', function ($dynamic, $raw_data, $element) {
            return method_exists($element, 'get_atomic_setting') && ($element->get_atomic_setting('batExperiment') || $element->get_atomic_setting('batGoal')) ? true : $dynamic;
        }, 10, 3);
    }
    public static function schema($schema) {
        if (!class_exists(Number_Prop_Type::class)) { return $schema; }
        $schema['batExperiment'] = Number_Prop_Type::make()->default(0);
        $schema['batVariant'] = String_Prop_Type::make()->enum(['a', 'b'])->default('a');
        $schema['batGoal'] = Boolean_Prop_Type::make()->default(false);
        return $schema;
    }
    public static function controls($controls, $element) {
        if (!class_exists(Section::class)) { return $controls; }
        foreach ($controls as $control) { if ($control instanceof Section && $control->get_id() === 'bat') { return $controls; } }
        $controls[] = Section::make()->set_id('bat')->set_label('A/B Testing')
            ->set_description('Asigna dos elementos al mismo experimento, uno como A y otro como B.')
            ->set_items([
                Number_Control::bind_to('batExperiment')->set_label('ID del experimento')->set_min(0)->set_step(1)->set_should_force_int(true),
                Select_Control::bind_to('batVariant')->set_label('Variante')->set_options([['label' => 'A', 'value' => 'a'], ['label' => 'B', 'value' => 'b']]),
                Switch_Control::bind_to('batGoal')->set_label('Contar clics en este elemento')->set_description('Añade ab-cta; usa .ab-cta como selector del objetivo.'),
            ]);
        return $controls;
    }
    public static function render($element) {
        if (!method_exists($element, 'get_atomic_setting')) { return; }
        $data = BAT_Integrations::attributes($element->get_atomic_setting('batExperiment') ?: 0, $element->get_atomic_setting('batVariant') ?: 'a');
        if ($element->get_atomic_setting('batGoal')) { $data['class'] = 'ab-cta'; }
        if (!$data) { return; }
        // Twig elements do not use classic _wrapper attributes. Tag their rendered root without changing saved settings.
        ob_start();
        self::$buffers[spl_object_id($element)] = ['level' => ob_get_level(), 'attributes' => $data];
    }
    public static function finish($element) {
        $id = spl_object_id($element);
        $buffer = self::$buffers[$id] ?? null;
        if (!$buffer || ob_get_level() !== $buffer['level']) { return; }
        unset(self::$buffers[$id]);
        $html = ob_get_clean();
        echo BAT_Integrations::html_attributes($html, $buffer['attributes']); // Already-rendered builder HTML; only escaped attributes are added.
    }
}
