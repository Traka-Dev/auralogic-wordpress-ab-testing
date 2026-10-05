# Seguridad

El proyecto está en desarrollo temprano. Se mantienen correcciones en la rama `main`; no hay un SLA ni un servicio alojado de medición.

## Reportar una vulnerabilidad

Contacta de forma privada mediante el sitio de [Aura Logic](https://auralogic.dev/). Incluye versión, pasos de reproducción sanitizados e impacto. No publiques tokens, datos de visitantes o detalles de una vulnerabilidad sin corregir en un issue público.

Al publicar el repositorio, los mantenedores deben habilitar los reportes privados de vulnerabilidades de GitHub y añadir aquí el enlace real.

## Límites relevantes

Los eventos enviados por un navegador no prueban una compra. El plugin verifica tokens firmados, revisión, página y exposición previa; no ofrece una defensa completa frente a fraude. El consentimiento requiere conectar el CMP del sitio. Los hashes temporales usados para limitar solicitudes no constituyen un límite distribuido atómico.

Los experimentos y eventos se conservan al desinstalar. El entorno Docker, sus contraseñas conocidas y las demos con consentimiento desactivado son exclusivamente de desarrollo local.
