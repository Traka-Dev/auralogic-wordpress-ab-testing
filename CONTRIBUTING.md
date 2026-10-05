# Contribuir a Aura Logic A/B Testing

Gracias por contribuir. El proyecto está en desarrollo temprano; los límites y builders comprobados están en README.md.

## Entorno

Usa Git, Docker Compose, Python 3 y Node.js 22 o 24. Clona el repositorio, ejecuta `npm ci`, `npm run dev:setup` y `npx playwright install chromium`. La instalación local usa localhost:8098; las credenciales publicadas son exclusivamente de prueba.

## Cambios

1. Crea una rama desde `main`, por ejemplo `fix/variant-visibility`.
2. Implementa un cambio acotado y actualiza la documentación si cambia el comportamiento.
3. Ejecuta `npm test`, `npm run test:integration` y `npm run test:browser` cuando el cambio afecte al frontend, REST o builders. Para el guardado del administrador ejecuta también `npm run test:http`.
4. Genera el paquete con `npm run build` y valida con `npm run check:package`.
5. Abre un pull request explicando el problema, el resultado y las pruebas realizadas. Un bug corregido debe tener una comprobación que reproduzca el comportamiento relevante.

No incluyas ZIP de builders comerciales, datos de visitantes, volúmenes de WordPress, credenciales reales ni archivos de `.local/`. Usa las APIs públicas de los builders. Identifica claramente qué pruebas usan el builder real y cuáles simulan contratos.

## Convenciones

Mantén los prefijos `BAT_`, `bat_` y `builder-ab-testing` para conservar compatibilidad. No cambies revisiones, asignaciones o el esquema de datos sin documentar el efecto en experimentos existentes. Los comentarios y documentos pueden escribirse en español o inglés.

Tus aportaciones se distribuyen bajo GPL-2.0-or-later, igual que el proyecto. No se exige un CLA adicional. Para vulnerabilidades consulta SECURITY.md.
