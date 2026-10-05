<?php
if (!defined('ABSPATH')) { exit; }
final class BAT_Dashboard {
    public static function reports(array $posts) {
        global $wpdb;
        $reports = []; $clauses = []; $params = [];
        foreach ($posts as $post) {
            $config = BAT_Plugin::config($post->ID);
            if (!$config) { continue; }
            $reports[$post->ID] = ['post' => $post, 'config' => $config, 'variants' => ['a' => ['exposures' => 0, 'conversions' => 0], 'b' => ['exposures' => 0, 'conversions' => 0]]];
            $clauses[] = '(experiment_id=%d AND revision=%d)';
            $params[] = $post->ID; $params[] = $config['revision'];
        }
        if (!$reports) { return []; }
        $table = BAT_Plugin::table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT experiment_id, variant, SUM(event_type='exposure') exposures, SUM(event_type='conversion') conversions FROM $table WHERE " . implode(' OR ', $clauses) . ' GROUP BY experiment_id, variant', $params));
        foreach ($rows as $row) {
            if (isset($reports[$row->experiment_id]['variants'][$row->variant])) {
                $reports[$row->experiment_id]['variants'][$row->variant] = ['exposures' => (int) $row->exposures, 'conversions' => (int) $row->conversions];
            }
        }
        return $reports;
    }
    private static function rate(array $variant) {
        return $variant['exposures'] ? 100 * $variant['conversions'] / $variant['exposures'] : null;
    }
    private static function card($id, array $report) {
        $config = $report['config'];
        $status = !$config['active'] ? 'paused' : (BAT_Plugin::runnable($config) ? 'active' : 'attention');
        $labels = ['paused' => 'Pausado', 'active' => 'Activo', 'attention' => 'Revisar páginas'];
        $url = add_query_arg(['page' => 'builder-ab', 'edit' => $id], admin_url('admin.php'));
        echo '<article class="bat-experiment" data-bat-status="' . esc_attr($status) . '" data-bat-name="' . esc_attr($report['post']->post_title) . '">';
        echo '<div class="bat-card-top"><span class="bat-badge bat-badge-' . esc_attr($status) . '">' . esc_html($labels[$status]) . '</span><span class="bat-muted">Revisión ' . (int) $config['revision'] . '</span></div>';
        echo '<h3><a href="' . esc_url($url) . '#bat-form">' . esc_html($report['post']->post_title) . '</a></h3>';
        echo '<p class="bat-muted">' . ($config['mode'] === 'elements' ? 'Elementos en una página' : 'Dos páginas completas') . ' · ' . ($config['goal'] === 'click' ? 'Objetivo por clic' : 'Página de agradecimiento') . '</p>';
        echo '<table class="bat-comparison"><caption class="screen-reader-text">Resultados de ' . esc_html($report['post']->post_title) . '</caption><thead><tr><th>Variante</th><th>Expuestos</th><th>Conversiones</th><th>Tasa</th></tr></thead><tbody>';
        foreach ($report['variants'] as $variant => $counts) {
            $rate = self::rate($counts);
            echo '<tr><th scope="row"><span class="bat-variant bat-variant-' . esc_attr($variant) . '">' . esc_html(strtoupper($variant)) . '</span><small>' . (int) ($variant === 'a' ? $config['weight'] : 100 - $config['weight']) . '% tráfico</small></th><td>' . esc_html(number_format_i18n($counts['exposures'])) . '</td><td>' . esc_html(number_format_i18n($counts['conversions'])) . '</td><td>' . ($rate === null ? '—' : esc_html(number_format_i18n($rate, 2)) . '%') . '<span class="bat-rate-bar" aria-hidden="true"><span style="width:' . esc_attr((string) min(100, $rate ?? 0)) . '%"></span></span></td></tr>';
        }
        echo '</tbody></table>';
        $a = self::rate($report['variants']['a']); $b = self::rate($report['variants']['b']);
        if ($a === null || $b === null) { $insight = 'Esperando exposiciones en ambas variantes para comparar.'; }
        elseif ($a == 0) { $insight = 'A todavía no registra conversiones; no se calcula cambio relativo.'; }
        else {
            $lift = 100 * ($b - $a) / $a;
            $insight = 'Cambio relativo observado de B frente a A: ' . ($lift > 0 ? '+' : '') . number_format_i18n($lift, 2) . '%.';
        }
        echo '<p class="bat-insight">' . esc_html($insight) . '</p><p class="bat-muted bat-small">Resultados descriptivos; no determinan un ganador.</p>';
        echo '<div class="bat-card-actions"><a class="button" href="' . esc_url($url) . '#bat-form">Configurar</a>';
        if (get_post_status($config['entry']) === 'publish') { echo '<a href="' . esc_url(get_permalink($config['entry'])) . '" target="_blank" rel="noopener">Abrir página <span aria-hidden="true">↗</span></a>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bat_toggle_' . $id);
        echo '<input type="hidden" name="action" value="bat_toggle"><input type="hidden" name="experiment_id" value="' . (int) $id . '"><button class="button" type="submit" aria-label="' . esc_attr(($config['active'] ? 'Pausar ' : 'Activar ') . $report['post']->post_title) . '">' . ($config['active'] ? 'Pausar' : 'Activar') . '</button></form></div></article>';
    }
    public static function render() {
        $reports = self::reports(get_posts(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'numberposts' => -1]));
        $totals = ['active' => 0, 'paused' => 0, 'exposures' => 0, 'conversions' => 0];
        foreach ($reports as $report) {
            $totals[$report['config']['active'] && BAT_Plugin::runnable($report['config']) ? 'active' : 'paused']++;
            foreach ($report['variants'] as $counts) { $totals['exposures'] += $counts['exposures']; $totals['conversions'] += $counts['conversions']; }
        }
        echo '<header class="bat-header"><div><p class="bat-eyebrow">AURA LOGIC · EXPERIMENTOS</p><h1>A/B Testing</h1><p>De una hipótesis a resultados que puedes comparar.</p></div><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=builder-ab#bat-form')) . '">Nuevo experimento</a></header>';
        echo '<section class="bat-kpis" aria-label="Resumen de experimentos">';
        foreach (['active' => 'Experimentos activos', 'paused' => 'Pausados o por revisar', 'exposures' => 'Exposiciones', 'conversions' => 'Conversiones'] as $key => $label) {
            echo '<div class="bat-kpi"><span>' . esc_html($label) . '</span><strong>' . esc_html(number_format_i18n($totals[$key])) . '</strong></div>';
        }
        echo '</section><p class="bat-muted">Totales de las revisiones actuales. Un visitante puede participar en distintos experimentos.</p>';
        echo '<div class="bat-toolbar"><h2>Tus experimentos</h2><div><label class="screen-reader-text" for="bat-search">Buscar experimentos</label><input id="bat-search" type="search" placeholder="Buscar experimento…"><label class="screen-reader-text" for="bat-filter">Filtrar por estado</label><select id="bat-filter"><option value="all">Todos los estados</option><option value="active">Activos</option><option value="paused">Pausados</option><option value="attention">Revisar páginas</option></select></div></div>';
        echo '<div class="bat-experiments">';
        foreach ($reports as $id => $report) { self::card($id, $report); }
        echo '</div><p id="bat-no-results" class="bat-empty" role="status" ' . ($reports ? 'hidden' : '') . '>' . ($reports ? 'No hay experimentos que coincidan con la búsqueda.' : 'Crea tu primer experimento y empieza a medir una mejora.') . '</p>';
    }
}
