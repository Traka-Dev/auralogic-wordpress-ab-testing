=== Aura Logic A/B Testing ===
Tags: ab testing, conversion, gutenberg, elementor, experiments
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Experimentos A/B entre páginas o elementos, con exposiciones y conversiones únicas. Por Aura Logic.

== Description ==

Compara dos páginas completas o variantes dentro de una página. Incluye bloque Gutenberg y controles para Elementor clásico y Atomic Elements. Consentimiento configurable, reparto de tráfico y métricas por revisión.

Proyecto en desarrollo temprano. Bricks tiene soporte genérico de páginas completas con exclusión del editor; falta validación con el tema instalado. No incluye controles nativos de Bricks ni Divi. No hay ganador automático ni compras verificadas.

El plugin no requiere un servicio externo. Almacenamiento local de asignaciones por 90 días, eventos en la base de datos del sitio y consentimiento mediante integración manual con el CMP. Revisa README.md para límites y uso.

== Installation ==

1. Sube el ZIP desde Plugins > Añadir plugin > Subir plugin.
2. Activa Aura Logic A/B Testing y abre A/B Testing.
3. Crea las páginas o variantes, configura el objetivo y el consentimiento y activa el experimento.

== Frequently Asked Questions ==

= ¿Dos navegadores pueden recibir la misma variante? =
Sí. La asignación es aleatoria y persiste por navegador; no alterna obligatoriamente A y B.

= ¿Se borran los datos al desinstalar? =
No. Los experimentos y eventos se conservan.

== Changelog ==

= 0.4.0 =
Panel de experimentos con resumen, comparación A/B, búsqueda, filtros y pausa/reactivación.

= 0.4.0 =
Preparación open source bajo Aura Logic, licencia, documentación, CI y empaquetado reproducible.
