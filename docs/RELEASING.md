# Publicar una versión

1. Revisa las limitaciones y la matriz de builders en README.md. No anuncies compatibilidad con versiones que no se probaron.
2. Actualiza versión en el encabezado PHP, `BAT_VERSION`, `package.json`, `package-lock.json`, `readme.txt`, README.md y CHANGELOG.md.
3. Ejecuta las comprobaciones de CONTRIBUTING.md. Revisa el contenido de `dist/builder-ab-testing.zip` con `npm run check:package`.
4. Crea un commit de versión y, una vez integrado en `main`, un tag anotado `vX.Y.Z`.
5. Publica en GitHub una release desde ese tag y adjunta el ZIP validado y notas con cambios y limitaciones.

CI genera un artefacto ZIP de prueba; no publica releases automáticamente. Crear este repositorio no equivale a aprobar o publicar el plugin en WordPress.org. El archivo readme.txt sirve como base para una futura revisión del directorio, y sus metadatos deben revisarse antes de enviarlo.
