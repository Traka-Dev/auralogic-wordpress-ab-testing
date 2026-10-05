# Aura Logic A/B Testing — 0.4.0

Por [Aura Logic](https://auralogic.dev/). Open source bajo [GPL-2.0-or-later](LICENSE). Proyecto en desarrollo temprano, disponible en [GitHub](https://github.com/Traka-Dev/auralogic-wordpress-ab-testing); todavía no publicado en WordPress.org.

[Contribuir](CONTRIBUTING.md) · [Seguridad](SECURITY.md) · [Cambios](CHANGELOG.md) · [Crear releases](docs/RELEASING.md)

Plugin de WordPress para probar dos páginas completas o dos variantes de contenido dentro de una página. Incluye medición de visitantes expuestos y conversiones únicas, un bloque nativo de Gutenberg y adaptadores de Elementor clásico y Atomic Elements. Es un único plugin: todos los adaptadores comparten experimentos, asignaciones y métricas.

[![CI](https://github.com/Traka-Dev/auralogic-wordpress-ab-testing/actions/workflows/ci.yml/badge.svg)](https://github.com/Traka-Dev/auralogic-wordpress-ab-testing/actions/workflows/ci.yml)

## Panel de administración

El panel es el centro del producto: resume las métricas de las revisiones actuales y presenta cada experimento con sus variantes A/B, reparto, exposiciones, conversiones y tasas. Puedes buscar por nombre, filtrar por estado, abrir su página y pausar o reactivar sin reiniciar sus resultados. Si sus páginas dejan de estar publicadas, indica que necesitan revisión.

La diferencia relativa de B respecto de A es descriptiva; no declara un ganador ni calcula significación estadística. Cuando faltan exposiciones o la tasa de A es cero, explica por qué no calcula esa comparación.

La configuración se organiza en tres pasos: definir la prueba, elegir qué medir y preparar el lanzamiento. Incluye una guía para publicar variantes y probar como visitante. Las métricas se consultan en lote, sin una consulta de eventos por tarjeta.

![Panel de Aura Logic A/B Testing](https://raw.githubusercontent.com/Traka-Dev/auralogic-wordpress-ab-testing/main/docs/screenshots/admin-dashboard.png)

Captura del entorno local con métricas de muestra generadas por las pruebas.

[Guía práctica: instalar, configurar y leer tu primer experimento](docs/ADMIN_GUIDE.md).

## Instalación

Instala `dist/builder-ab-testing.zip` desde **Plugins → Añadir plugin → Subir plugin**, actívalo y abre **A/B Testing**. Requiere WordPress 6.5+ y PHP 8.0+. Los experimentos y datos de la versión 0.1.0 se conservan como pruebas entre páginas; no necesitan migración de tablas.

## Probar dos páginas completas

1. Crea páginas publicadas de entrada, A, B y agradecimiento. Diseña las variantes con tu builder.
2. Crea un experimento y elige **Dos páginas completas**. Selecciona las páginas y el porcentaje de tráfico para A; el resto irá a B.
3. Selecciona objetivo por visita a agradecimiento o clic mediante selector CSS (por ejemplo, `.ab-cta`).
4. Configura consentimiento, activa el experimento y guarda.
5. Purga caché/CDN de las páginas afectadas y dirige el tráfico a la entrada. Puedes configurarla como página de inicio desde **Ajustes → Lectura**.

El script solicita una asignación persistente y navega a la URL de A o B. Cada variante conserva su propio caché. La entrada mantiene contenido útil si falla JavaScript o si el consentimiento sigue pendiente. Las visitas directas a A/B sin asignación no se cuentan. Se preservan parámetros de campañas `utm_*`, `gclid`, `fbclid` y `msclkid`.

## Probar contenido con Gutenberg

1. Crea una página y un experimento de modalidad **Elementos dentro de una página**. Selecciona esa página, el objetivo y el reparto. Guarda y copia el **ID para el builder**.
2. En el editor de la página, inserta un bloque **Variante A/B**. Configura el ID del experimento y variante A en su barra lateral. Añade dentro títulos, botones u otros bloques.
3. Inserta otro bloque **Variante A/B**, con el mismo ID y variante B. Añade su contenido.
4. Para medir botones usa la clase `ab-cta` en los botones y `.ab-cta` como selector del experimento. El interruptor del bloque «Contar clics en este contenido» añade esa clase al contenedor y cuenta cualquier clic dentro de él; úsalo solo si esa es la métrica que quieres.
5. Publica y prueba como visitante, sin sesión de editor.

El editor muestra ambos bloques con su etiqueta A/B. En el sitio se muestra la variante asignada, sin redirección. No anides un bloque Variante A/B dentro de otro.

## Probar contenido con Elementor

1. Crea un experimento de **Elementos dentro de una página**, selecciona la página hecha con Elementor y copia su ID.
2. Diseña dos contenedores, secciones o widgets clásicos con las alternativas A y B.
3. En **Avanzado → A/B Testing**, establece el ID y la variante en cada uno. Usa el mismo ID en ambas alternativas.
4. Configura el objetivo por clic o agradecimiento. Para botones, añade `ab-cta` en sus clases CSS y usa `.ab-cta` en el objetivo. El interruptor del panel añade esa clase al propio elemento; en un botón dentro de una variante puedes activarlo dejando su ID en 0. Si lo activas en un contenedor, medirá cualquier clic dentro del contenedor.
5. Guarda/publica y purga el caché de Elementor y del sitio.

Los elementos se renderizan normalmente por Elementor, con sus scripts y estilos. El motor alterna su visibilidad sin reemplazar el HTML. Los elementos marcados evitan el caché interno de elementos de Elementor para conservar los atributos del experimento. El editor y las vistas previas muestran ambas variantes y quedan excluidos de la medición.

En elementos clásicos el panel está en Avanzado. En **Atomic Elements** está en **General → A/B Testing** y utiliza sus propiedades tipadas; los controles y el renderizado se comprobaron con Elementor 4.3.4. Widgets interactivos de terceros pueden necesitar ajustes específicos al aparecer; el motor emite `bat:variant-ready` y un evento `resize`. No anides un elemento marcado dentro de otro marcado para el mismo experimento. No dividas un formulario entre variantes.

## Pruebas de páginas completas con Bricks

El motor de páginas completas utiliza páginas publicadas de WordPress y no necesita controles dentro del builder. Diseña la entrada, A, B y el agradecimiento con Bricks y configúralos desde A/B Testing. Para clics, añade la clase `ab-cta` al botón y elige `.ab-cta` como selector del objetivo.

El runtime queda excluido cuando la URL lleva `bricks=run` o cuando la función disponible `bricks_is_frontend()` indica que no es el frontend. Esto evita asignaciones, redirecciones y mediciones en el editor. Estas señales proceden de la [documentación del contexto de renderizado](https://academy.bricksbuilder.io/developer/hooks/filters/filter-bricks-element-render_attributes/) y de las [URLs del builder](https://academy.bricksbuilder.io/builder/setup/known-issues/).

**Validación pendiente:** Bricks no está instalado en el entorno local. Se comprueba el motor genérico para ambas variantes, persistencia, parámetros de campaña, exposición, deduplicación, agradecimiento y exclusión de la URL del editor. Esas pruebas no validan el renderizado, estilos, formularios o caché de Bricks. Necesitamos su ZIP oficial para completar esa comprobación. No se incluyen controles nativos de variantes dentro de Bricks.

## Consentimiento

Por defecto se espera consentimiento antes de almacenar, asignar o medir. Para páginas completas permanece visible la entrada; para elementos se muestra A como alternativa sin medirla. Conecta el gestor de consentimiento a este contrato:

```js
// Después de aprobar la categoría correspondiente:
window.BAT_CONSENT = true;
window.dispatchEvent(new Event('bat:consent'));

// Al revocar: deja de medir y elimina la asignación local.
window.BAT_CONSENT = false;
window.dispatchEvent(new Event('bat:consent'));
```

Si ya estaba concedido al cargar, define la variable antes del script o emite el evento después. Al revocar un experimento de elementos vuelve a mostrarse A, se desconecta su observador y se retiran sus listeners. Los datos históricos y las solicitudes en vuelo se conservan. No hay conexión automática con un CMP concreto. Puedes desactivar la espera en la configuración si corresponde a tu sitio.

## Estado de compatibilidad

| Builder | Páginas completas | Contenido dentro de una página |
| --- | --- | --- |
| Gutenberg | Probado | Bloque Variante A/B implementado y probado |
| Elementor 4.3.4 | Motor genérico | Clásico y Atomic: controles, renderizado y medición probados |
| Bricks | Motor genérico probado; tema real pendiente | Sin adaptador nativo |
| Otros que generan páginas WP | Motor genérico; validar cada builder | Adaptador pendiente |

El núcleo no depende de un builder para asignar visitantes ni registrar eventos.

## Medición y límites

- Una asignación por navegador/experimento/revisión durante 90 días en `localStorage`. No se comparte entre dispositivos.
- Páginas completas: exposición al cargar la variante con el documento visible.
- Elementos: exposición cuando la variante seleccionada entra en el viewport con el documento visible. Solo se inicia el reparto si hay variantes A y B válidas en la página.
- Conversión por clic en el selector dentro de la variante seleccionada o por visitar agradecimiento. Requiere exposición guardada. Un clic equivale a interés, no a envío exitoso de formulario.
- Una exposición y una conversión por visitante/experimento/revisión; índices únicos evitan duplicados concurrentes.
- Cambiar modalidad, páginas, objetivo, reparto o consentimiento crea una revisión nueva. El panel muestra la revisión actual; los datos anteriores permanecen en la tabla. Pausar/reactivar conserva la revisión.
- Editar el contenido desde el builder no cambia automáticamente la revisión. Para una nueva prueba de contenido crea otro experimento y pausa el anterior.
- Se permite un experimento activo por página de prueba. Un agradecimiento puede compartirse entre experimentos de páginas diferentes.
- La variante A sirve como alternativa cuando faltan consentimiento o JavaScript. Puede mostrarse brevemente antes de cambiar a B. Las dos variantes se envían al navegador y no deben contener contenido privado.
- La API usa tokens firmados, verifica revisión y vencimiento y valida la página de cada evento. No guarda IP ni datos de formularios en la tabla de eventos.
- Límite básico de 300 solicitudes/minuto por dirección de conexión mediante hashes temporales. No es un límite distribuido atómico; con CDN/proxy conviene configurarlo en el edge.
- Bots detectados por User-Agent, administradores/editores y vistas previas estándar no participan. Bloqueadores, errores de red o almacenamiento deshabilitado pueden reducir la medición. Los clics son de mejor esfuerzo y no bloquean navegación.
- No hay ganador automático, intervalos estadísticos, compras verificadas en servidor, exportación ni limpieza programada. Los eventos de navegador no demuestran una compra real.
- Al desinstalar se conservan experimentos y datos. Activación de red multisite no disponible; activa por sitio.

## Arquitectura

- `includes/class-bat-plugin.php`: configuración, esquema, firma y carga del motor.
- `includes/class-bat-admin.php`: edición, validación, revisiones y reportes.
- `includes/class-bat-rest.php`: asignaciones y eventos de ambos modos.
- `includes/class-bat-integrations.php`: contrato común de atributos, vistas previas y estado de builders.
- `includes/integrations/`: adaptadores Gutenberg, Elementor clásico, Atomic Elements.
- `assets/tracking.js`: consentimiento, persistencia, redirección, visibilidad y medición.
- `assets/gutenberg.js`: bloque y controles de Gutenberg.

El contrato de renderizado usa `data-bat-experiment` y `data-bat-variant`. Los adaptadores reutilizan `BAT_Integrations::attributes()` y el evento `bat:variant-ready`. El hook `bat_register_integrations` permite arrancar extensiones externas. Las integraciones se apoyan en la [API de bloques de WordPress](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/) y los [hooks de controles de Elementor](https://developers.elementor.com/docs/hooks/injecting-controls).

## Desarrollo local

Requiere Docker Compose, Python 3 y Node.js 22 o 24. Puedes preparar el entorno con `npm run dev:setup`; ese comando instala WordPress si hace falta, activa el plugin y crea las demos. Se sirve únicamente en `127.0.0.1:8098`, con credenciales de desarrollo.

```bash
docker compose up -d wordpress
docker compose run --rm cli wp core install --url=http://localhost:8098 --title='A/B Testing local' --admin_user=batadmin --admin_password=bat-local-development --admin_email=dev@example.test --skip-email
docker compose run --rm cli wp plugin activate builder-ab-testing
docker compose run --rm cli wp plugin install elementor --activate
docker compose run --rm cli wp eval-file wp-content/plugins/builder-ab-testing/scripts/demo.php
docker compose run --rm cli wp eval-file wp-content/plugins/builder-ab-testing/scripts/builders-demo.php
```

El primer script establece la entrada «Inicia tu experiencia» como home. El segundo crea demos Gutenberg, Elementor clásico y Atomic Elements y devuelve sus URLs. Las demos locales tienen consentimiento desactivado para probarlas sin un CMP. Abre las URLs en incógnito; el administrador está excluido.

Administración: `http://localhost:8098/wp-admin/`, usuario `batadmin`, contraseña `bat-local-development`. No uses estas credenciales fuera del entorno local. `docker compose down` detiene el entorno y conserva sus volúmenes.

## Pruebas y paquete

```bash
npm ci
npm test
docker compose run --rm cli wp eval-file wp-content/plugins/builder-ab-testing/tests/integration.php
docker compose run --rm cli wp eval-file wp-content/plugins/builder-ab-testing/tests/builders.php
docker compose run --rm cli wp eval-file wp-content/plugins/builder-ab-testing/tests/atomic.php
python3 tests/http-smoke.py

# Pruebas de navegador (requieren las demos locales):
npx playwright install chromium
npm run test:browser

# ZIP instalable:
python3 scripts/build.py
```

Las suites PHP crean registros temporales y los eliminan. La prueba HTTP y las pruebas de navegador dejan conversiones de muestra en las demos. Se prueban persistencia, firmas, vencimiento, revisión, pausa, deduplicación, renderizado de Gutenberg y Elementor clásico/Atomic, caché de Elementor y controles en sus editores. La suite `atomic.php` comprueba propiedades tipadas, controles, renderizado y datos de guardado con las clases reales de Elementor instalado. Entorno: WordPress 6.8/PHP 8.2, Elementor 4.3.4; sintaxis también verificada con PHP 8.0.

## Siguiente etapa

Añadir conversiones de formulario enviado con éxito, compras de WooCommerce verificadas desde servidor y reportes con incertidumbre estadística antes de automatizar decisiones.

## Git y publicación

El proyecto usa la rama `main`. Los artefactos, dependencias, copias locales de builders y archivos de entorno se excluyen de Git. Los workflows de GitHub ejecutan sintaxis PHP, pruebas JavaScript, integración WordPress/Elementor y pruebas de navegador; generan un ZIP validado para revisión.

Repositorio público: [https://github.com/Traka-Dev/auralogic-wordpress-ab-testing](https://github.com/Traka-Dev/auralogic-wordpress-ab-testing).

```bash
git clone https://github.com/Traka-Dev/auralogic-wordpress-ab-testing.git
cd auralogic-wordpress-ab-testing
npm ci
npm run dev:setup
```

Consulta docs/RELEASING.md para publicar una versión.
