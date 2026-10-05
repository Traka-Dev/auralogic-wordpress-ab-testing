# Crear tu primer experimento

Aura Logic A/B Testing 0.4.0 compara páginas completas o contenido dentro de una página. El panel está en **WordPress → A/B Testing**. Los resultados describen lo observado; no declaran un ganador automático.

## Instalar desde el repositorio

Hasta que haya una release con ZIP, puedes generar el instalador desde el código. Necesitas Git y Python 3; el plugin instalado requiere WordPress 6.5+ y PHP 8.0+.

```bash
git clone https://github.com/Traka-Dev/auralogic-wordpress-ab-testing.git
cd auralogic-wordpress-ab-testing
python3 scripts/build.py
```

Sube `dist/builder-ab-testing.zip` desde **Plugins → Añadir plugin → Subir plugin** y actívalo. Para desarrollar y ejecutar las pruebas consulta CONTRIBUTING.md.

## Ejemplo: comparar dos propuestas de una landing

1. Publica una página de entrada, dos alternativas A/B y un agradecimiento. En A presenta una propuesta; en B cambia esa propuesta, manteniendo el mismo objetivo.
2. Abre **Nuevo experimento**, ponle un nombre reconocible y elige **Dos páginas completas**.
3. Selecciona entrada, A y B. Con reparto 50/50, cada navegador nuevo tiene la misma probabilidad de recibir cualquiera de las variantes.
4. Elige **Visita a página de agradecimiento** y selecciona esa página. El botón de ambas variantes debe dirigir hacia ella.
5. Conecta el consentimiento del sitio según el contrato de README.md, activa la prueba y guarda. Purga cachés si corresponde.
6. Comparte la URL de entrada. Comprueba el flujo en un navegador sin sesión de administrador: entrada → variante asignada → agradecimiento.

Dos navegadores pueden recibir la misma variante. La asignación persiste por navegador; refrescar no obliga a alternar A y B.

## Variantes en Gutenberg y Elementor

Elige **Elementos dentro de una página**, selecciona esa página y guarda para obtener el ID. Marca dos contenedores con el mismo ID y variantes A/B: bloque **Variante A/B** en Gutenberg, **Avanzado → A/B Testing** en Elementor clásico o **General → A/B Testing** en Atomic Elements.

Para medir un botón, usa `ab-cta` como clase y `.ab-cta` como selector del objetivo por clic. Si marcas un contenedor entero como objetivo, todos los clics dentro de ese contenedor pueden contar. No anides variantes del mismo experimento ni dividas un formulario entre A y B.

## Leer el panel

- **Expuestos:** visitantes que vieron una variante. En elementos, la variante debe entrar en el área visible de la pantalla.
- **Conversiones:** visitantes expuestos que realizaron el objetivo configurado. Se cuenta una conversión por visitante, experimento y revisión.
- **Tasa:** conversiones divididas por exposiciones de esa variante.
- **Cambio relativo observado:** diferencia de tasa de B frente a A, dividida por la tasa de A. No es significación estadística ni una probabilidad de ganar. Si A tiene tasa cero o faltan exposiciones, el panel explica que no puede calcularlo.

Los totales agrupan las revisiones actuales, y un visitante puede participar en varios experimentos. Busca por nombre o filtra por estado para encontrar una prueba.

## Pausar y editar

**Pausar** detiene nuevas asignaciones y eventos. **Activar** conserva la revisión y los resultados; verifica que las páginas estén publicadas y que no coincidan con otra prueba activa. Una prueba activa con páginas sin publicar muestra **Revisar páginas**.

Cambiar modalidad, páginas, objetivo, reparto o consentimiento inicia otra revisión. Cambiar contenido directamente en el builder no lo hace: para una prueba de contenido nueva crea otro experimento y pausa el anterior. Los registros históricos se conservan, aunque el panel muestra la revisión actual.

## Compatibilidad y alcance

Gutenberg y Elementor clásico/Atomic están probados con WordPress 6.8 y Elementor 4.3.4. Bricks puede utilizar el motor genérico de páginas completas y su editor queda excluido de medición; falta validar el tema instalado y no hay controles nativos de Bricks. Divi no tiene adaptador nativo.

Un clic no demuestra un formulario enviado con éxito ni una compra. No hay ganador automático, intervalos estadísticos ni compras verificadas en servidor. Consulta README.md y SECURITY.md para el alcance completo.
