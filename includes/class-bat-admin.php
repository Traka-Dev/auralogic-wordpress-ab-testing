<?php
if (!defined('ABSPATH')) { exit; }
final class BAT_Admin {
    public static function boot() {
        add_action('admin_menu', function () { add_menu_page('A/B Testing', 'A/B Testing', 'manage_options', 'builder-ab', [self::class, 'screen'], 'dashicons-chart-bar'); });
        add_action('admin_post_bat_save', [self::class, 'save']);
        add_action('admin_enqueue_scripts', function ($hook) {
            if ($hook === 'toplevel_page_builder-ab') { wp_enqueue_script('bat-admin', plugins_url('assets/admin.js', BAT_FILE), [], BAT_VERSION, true); }
        });
    }
    private static function fail($message) { wp_die(esc_html($message), 'A/B Testing', ['response' => 400]); }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('Acceso denegado.', '', ['response' => 403]); }
        check_admin_referer('bat_save');
        $id = absint($_POST['experiment_id'] ?? 0);
        if ($id && (!get_post($id) || get_post_type($id) !== 'bat_experiment')) { self::fail('Experimento inválido.'); }
        $old = $id ? BAT_Plugin::config($id) : null;
        $config = [];
        $config['mode'] = sanitize_key($_POST['mode'] ?? 'pages');
        if (!in_array($config['mode'], ['pages', 'elements'], true)) { self::fail('Modalidad inválida.'); }
        foreach (['entry', 'a', 'b', 'thank_you'] as $field) { $config[$field] = absint($_POST[$field] ?? 0); }
        if ($config['mode'] === 'elements') { $config['a'] = 0; $config['b'] = 0; }
        $config['goal'] = sanitize_key($_POST['goal'] ?? 'page');
        $config['selector'] = trim(sanitize_text_field(wp_unslash($_POST['selector'] ?? '')));
        if ($config['goal'] === 'click') { $config['thank_you'] = 0; }
        if ($config['goal'] === 'page') { $config['selector'] = ''; }
        $config['weight'] = absint($_POST['weight'] ?? 50);
        $config['active'] = isset($_POST['active']);
        $config['consent'] = isset($_POST['consent']);
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        if (!$title || !in_array($config['goal'], ['page', 'click'], true) || $config['weight'] < 1 || $config['weight'] > 99) { self::fail('Revisa el nombre, objetivo y porcentaje de reparto.'); }
        $required = $config['mode'] === 'elements' ? ['entry'] : ['entry', 'a', 'b'];
        if ($config['goal'] === 'page') { $required[] = 'thank_you'; }
        foreach ($required as $field) {
            if (get_post_type($config[$field]) !== 'page' || get_post_status($config[$field]) !== 'publish') { self::fail('Selecciona páginas publicadas.'); }
        }
        $pages = array_map(function ($field) use ($config) { return $config[$field]; }, $required);
        if (count($pages) !== count(array_unique($pages))) { self::fail('La entrada, variantes y página de agradecimiento deben ser diferentes.'); }
        if ($config['goal'] === 'click' && (!$config['selector'] || strlen($config['selector']) > 200)) { self::fail('Introduce un selector CSS de hasta 200 caracteres para el clic.'); }
        foreach (BAT_Plugin::active() as $other_id => $other) {
            if ($other_id === $id || !$config['active']) { continue; }
            $own_pages = array_filter([$config['entry'], $config['a'], $config['b']]);
            $other_pages = array_filter([$other['entry'], $other['a'], $other['b']]);
            if (array_intersect($own_pages, $other_pages) || ($config['thank_you'] && in_array($config['thank_you'], $other_pages, true)) || ($other['thank_you'] && in_array($other['thank_you'], $own_pages, true))) { self::fail('Estas páginas ya participan en otro experimento activo.'); }
        }
        $config['revision'] = $old['revision'] ?? 1;
        if ($old) {
            foreach (['mode', 'entry', 'a', 'b', 'thank_you', 'goal', 'selector', 'weight', 'consent'] as $field) {
                if ($old[$field] !== $config[$field]) { $config['revision']++; break; }
            }
        }
        $saved = wp_insert_post(['ID' => $id, 'post_type' => 'bat_experiment', 'post_status' => 'publish', 'post_title' => $title], true);
        if (is_wp_error($saved)) { self::fail($saved->get_error_message()); }
        update_post_meta($saved, '_bat_config', $config);
        wp_safe_redirect(add_query_arg(['page' => 'builder-ab', 'edit' => $saved, 'saved' => 1], admin_url('admin.php')));
        exit;
    }
    private static function page_select($name, $selected, $label) {
        echo '<tr><th><label for="bat-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        wp_dropdown_pages(['name' => $name, 'id' => 'bat-' . $name, 'selected' => $selected, 'show_option_none' => 'Selecciona una página', 'option_none_value' => '0', 'post_status' => 'publish']);
        echo '</td></tr>';
    }
    public static function screen() {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $id = absint($_GET['edit'] ?? 0);
        $config = $id ? BAT_Plugin::config($id) : null;
        if ($id && !$config) { self::fail('Experimento inválido.'); }
        $config = $config ?: ['mode' => 'pages', 'entry' => 0, 'a' => 0, 'b' => 0, 'thank_you' => 0, 'goal' => 'page', 'selector' => '', 'weight' => 50, 'active' => false, 'consent' => true, 'revision' => 1];
        echo '<div class="wrap"><h1>A/B Testing</h1>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Experimento guardado. Purga el caché de las páginas involucradas si tu sitio utiliza caché.</p></div>'; }
        echo '<p>Prueba páginas completas o elementos dentro de una página. En pruebas entre páginas, dirige el tráfico a la entrada.</p>';
        BAT_Integrations::status();
        echo '<h2>Experimentos</h2><table class="widefat striped"><thead><tr><th>Nombre</th><th>Estado</th><th>Revisión</th><th>Variante</th><th>Expuestos</th><th>Conversiones</th><th>Tasa</th></tr></thead><tbody>';
        $posts = get_posts(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'numberposts' => -1]);
        foreach ($posts as $post) {
            $item = BAT_Plugin::config($post->ID);
            if (!$item) { continue; }
            $table = BAT_Plugin::table();
            $rows = $wpdb->get_results($wpdb->prepare("SELECT variant, SUM(event_type='exposure') exposures, SUM(event_type='conversion') conversions FROM $table WHERE experiment_id=%d AND revision=%d GROUP BY variant", $post->ID, $item['revision']), OBJECT_K);
            foreach (['a', 'b'] as $variant) {
                $exposures = isset($rows[$variant]) ? (int) $rows[$variant]->exposures : 0;
                $conversions = isset($rows[$variant]) ? (int) $rows[$variant]->conversions : 0;
                echo '<tr><td><a href="' . esc_url(add_query_arg(['page' => 'builder-ab', 'edit' => $post->ID], admin_url('admin.php'))) . '">' . esc_html($post->post_title) . '</a></td><td>' . ($item['active'] ? 'Activo' : 'Pausado') . '</td><td>' . (int) $item['revision'] . '</td><td>' . esc_html(strtoupper($variant)) . '</td><td>' . $exposures . '</td><td>' . $conversions . '</td><td>' . ($exposures ? esc_html(number_format_i18n(100 * $conversions / $exposures, 2)) . '%' : '—') . '</td></tr>';
            }
        }
        if (!$posts) { echo '<tr><td colspan="7">Todavía no hay experimentos.</td></tr>'; }
        echo '</tbody></table><p>Resultados descriptivos de la revisión actual. No se declara un ganador automáticamente. Al cambiar páginas, objetivo, reparto o consentimiento se inicia otra revisión.</p>';
        echo '<h2>' . ($id ? 'Editar experimento' : 'Nuevo experimento') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bat_save');
        echo '<input type="hidden" name="action" value="bat_save"><input type="hidden" name="experiment_id" value="' . $id . '"><table class="form-table">';
        echo '<tr><th><label for="bat-title">Nombre</label></th><td><input id="bat-title" name="title" class="regular-text" required value="' . esc_attr($id ? get_the_title($id) : '') . '"></td></tr>';
        echo '<tr><th><label for="bat-mode">Modalidad</label></th><td><select id="bat-mode" name="mode"><option value="pages" ' . selected($config['mode'], 'pages', false) . '>Dos páginas completas</option><option value="elements" ' . selected($config['mode'], 'elements', false) . '>Elementos dentro de una página</option></select><p class="description">Para elementos, elige la página de entrada y configura dos variantes en Gutenberg o Elementor. Los campos de página A/B se ignoran.</p></td></tr>';
        if ($id) { echo '<tr><th>ID para el builder</th><td><strong>' . $id . '</strong> — usa este ID en las variantes A y B.</td></tr>'; }
        self::page_select('entry', $config['entry'], 'Página de entrada');
        self::page_select('a', $config['a'], 'Variante A');
        self::page_select('b', $config['b'], 'Variante B');
        echo '<tr><th><label for="bat-weight">Tráfico hacia A (%)</label></th><td><input id="bat-weight" type="number" min="1" max="99" name="weight" value="' . (int) $config['weight'] . '" required> El resto irá a B.</td></tr>';
        echo '<tr><th><label for="bat-goal">Objetivo</label></th><td><select id="bat-goal" name="goal"><option value="page" ' . selected($config['goal'], 'page', false) . '>Visita a página de agradecimiento</option><option value="click" ' . selected($config['goal'], 'click', false) . '>Clic en un elemento</option></select></td></tr>';
        self::page_select('thank_you', $config['thank_you'], 'Página de agradecimiento');
        echo '<tr><th><label for="bat-selector">Selector para clic</label></th><td><input id="bat-selector" name="selector" class="regular-text" maxlength="200" placeholder=".mi-boton" value="' . esc_attr($config['selector']) . '"><p class="description">Añade la misma clase al botón de ambas variantes. Solo se usa para el objetivo de clic.</p></td></tr>';
        echo '<tr><th>Consentimiento</th><td><label><input type="checkbox" name="consent" ' . checked($config['consent'], true, false) . '> Esperar consentimiento antes de asignar y medir</label><p class="description">Tu gestor de consentimiento debe activar window.BAT_CONSENT y emitir el evento bat:consent. Consulta el README para integrarlo.</p></td></tr>';
        echo '<tr><th>Estado</th><td><label><input type="checkbox" name="active" ' . checked($config['active'], true, false) . '> Activar experimento</label></td></tr></table>';
        submit_button('Guardar experimento');
        echo '</form><a href="' . esc_url(admin_url('admin.php?page=builder-ab')) . '">Crear otro experimento</a></div>';
    }
}
