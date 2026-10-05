<?php
// Local demo only; consent is disabled to let developers test without a CMP.
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'localhost') { throw new RuntimeException('Demo disponible únicamente en localhost.'); }
$existing = get_posts(['post_type' => 'bat_experiment', 'title' => 'Demo: llamada a la acción', 'post_status' => 'publish', 'numberposts' => 1]);
if ($existing) {
    $config = BAT_Plugin::config($existing[0]->ID);
    update_option('show_on_front', 'page');
    update_option('page_on_front', $config['entry']);
    echo wp_json_encode(['experiment' => $existing[0]->ID, 'entry' => get_permalink($config['entry']), 'pages' => array_intersect_key($config, array_flip(['entry', 'a', 'b', 'thank_you']))]);
    return;
}
$pages = [];
foreach (['entry' => 'Demo entrada', 'a' => 'Demo variante A', 'b' => 'Demo variante B', 'thank_you' => 'Demo gracias'] as $key => $title) {
    $pages[$key] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title]);
}
wp_update_post(['ID' => $pages['entry'], 'post_title' => 'Inicio', 'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Inicia tu experiencia</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Bienvenido. Preparando tu experiencia…</p><!-- /wp:paragraph -->']);
foreach (['a' => 'Conoce nuestro servicio', 'b' => 'Descubre cómo podemos ayudarte'] as $key => $headline) {
    wp_update_post(['ID' => $pages[$key], 'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html($headline) . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p><a class="ab-cta" href="' . esc_url(get_permalink($pages['thank_you'])) . '">Me interesa</a></p><!-- /wp:paragraph -->']);
}
wp_update_post(['ID' => $pages['thank_you'], 'post_content' => '<!-- wp:paragraph --><p>¡Gracias! Has completado el objetivo de la demo.</p><!-- /wp:paragraph -->']);
$id = wp_insert_post(['post_type' => 'bat_experiment', 'post_status' => 'publish', 'post_title' => 'Demo: llamada a la acción']);
update_post_meta($id, '_bat_config', $pages + ['goal' => 'page', 'selector' => '', 'weight' => 50, 'active' => true, 'consent' => false, 'revision' => 1]);
update_option('show_on_front', 'page');
update_option('page_on_front', $pages['entry']);
echo wp_json_encode(['experiment' => $id, 'entry' => get_permalink($pages['entry']), 'pages' => $pages]);
