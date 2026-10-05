<?php
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'localhost') { throw new RuntimeException('Demo disponible únicamente en localhost.'); }
$result = [];
foreach (['gutenberg', 'elementor', 'atomic'] as $builder) {
    $title = 'Demo elementos: ' . $builder;
    $existing = get_posts(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'title' => $title, 'numberposts' => 1]);
    if ($existing) {
        $cfg = BAT_Plugin::config($existing[0]->ID);
        $result[$builder] = ['experiment' => $existing[0]->ID, 'page' => $cfg['entry'], 'url' => get_permalink($cfg['entry'])];
        continue;
    }
    if ($builder !== 'gutenberg' && !defined('ELEMENTOR_VERSION')) { continue; }
    if ($builder === 'atomic' && !class_exists('Elementor\\Modules\\AtomicWidgets\\Elements\\Div_Block\\Div_Block')) { continue; }
    $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title]);
    $id = wp_insert_post(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'post_title' => $title]);
    update_post_meta($id, '_bat_config', ['mode' => 'elements', 'entry' => $page, 'a' => 0, 'b' => 0, 'thank_you' => 0, 'goal' => 'click', 'selector' => '.ab-cta', 'weight' => 50, 'active' => true, 'consent' => false, 'revision' => 1]);
    if ($builder === 'gutenberg') {
        $content = '';
        foreach (['a' => 'Empieza hoy', 'b' => 'Prueba una experiencia diferente'] as $variant => $headline) {
            $content .= '<!-- wp:builder-ab/variant ' . wp_json_encode(['experimentId' => $id, 'variant' => $variant]) . ' -->';
            $content .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html($headline) . '</h2><!-- /wp:heading -->';
            $content .= '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"ab-cta"} --><div class="wp-block-button ab-cta"><a class="wp-block-button__link wp-element-button" href="#demo-gracias">Me interesa</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
            $content .= '<!-- /wp:builder-ab/variant -->';
        }
        $content .= '<!-- wp:paragraph {"anchor":"demo-gracias"} --><p id="demo-gracias">Gracias por probar la demo.</p><!-- /wp:paragraph -->';
        wp_update_post(['ID' => $page, 'post_content' => $content]);
    } else {
        $data = [];
        foreach (['a' => 'Empieza hoy con Elementor', 'b' => 'Descubre tu siguiente paso con Elementor'] as $variant => $headline) {
            if ($builder === 'atomic') {
                $data[] = ['id' => 'bat' . $variant . '001', 'elType' => 'e-div-block', 'version' => '0.0', 'isInner' => false,
                    'settings' => ['batExperiment' => ['$$type' => 'number', 'value' => $id], 'batVariant' => ['$$type' => 'string', 'value' => $variant]],
                    'elements' => [
                        ['id' => 'bat' . $variant . '002', 'elType' => 'widget', 'widgetType' => 'e-heading', 'version' => '0.0', 'settings' => ['title' => ['$$type' => 'string', 'value' => 'Atomic: ' . $headline]], 'elements' => []],
                        ['id' => 'bat' . $variant . '003', 'elType' => 'widget', 'widgetType' => 'e-button', 'version' => '0.0', 'settings' => ['text' => ['$$type' => 'string', 'value' => 'Me interesa'], 'batGoal' => ['$$type' => 'boolean', 'value' => true]], 'elements' => []],
                    ]];
                continue;
            }
            $data[] = ['id' => 'bat' . $variant . '001', 'elType' => 'container', 'isInner' => false,
                'settings' => ['_bat_experiment' => $id, '_bat_variant' => $variant],
                'elements' => [
                    ['id' => 'bat' . $variant . '002', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => $headline], 'elements' => []],
                    ['id' => 'bat' . $variant . '003', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => ['text' => 'Me interesa', 'link' => ['url' => '#demo-gracias'], '_css_classes' => 'ab-cta'], 'elements' => []],
                ]];
        }
        update_post_meta($page, '_elementor_edit_mode', 'builder');
        update_post_meta($page, '_elementor_template_type', 'wp-page');
        update_post_meta($page, '_elementor_version', ELEMENTOR_VERSION);
        update_post_meta($page, '_elementor_data', wp_slash(wp_json_encode($data)));
        wp_update_post(['ID' => $page, 'post_content' => '<p id="demo-gracias">Demo Elementor</p>']);
    }
    $result[$builder] = ['experiment' => $id, 'page' => $page, 'url' => get_permalink($page)];
}
echo wp_json_encode($result);
