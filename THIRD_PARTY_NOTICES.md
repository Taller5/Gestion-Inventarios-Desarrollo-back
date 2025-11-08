# Avisos sobre software de terceros

Este proyecto integra un firmador XML alternativo para la facturación electrónica de Hacienda de Costa Rica.

- Nombre: hacienda-firmador-php
- Proyecto original (upstream): https://github.com/enzojimenez/hacienda-firmador-php
- Licencia: GNU Affero General Public License v3.0 (AGPL-3.0-only)

Detalles de integración:
- El código del firmador NO se incluye por defecto en este repositorio. Puede estar presente localmente bajo `tools/hacienda-firmador-php/`.
- La aplicación invoca el firmador mediante un puente de línea de comandos (`scripts/sign_bridge.php`) que carga el código del proyecto original en un proceso separado.
- Si distribuyes el código del firmador o si operas un servicio en red que utilice una versión modificada del firmador, debes cumplir la AGPL: conservar los avisos y la licencia, y ofrecer a los usuarios del servicio el Código Fuente Correspondiente de la versión que se esté ejecutando.

Atribución:
- Los derechos de autor del firmador pertenecen a sus autores originales según consta en el repositorio upstream.
- El contenedor/puente (este repositorio) incluye sólo el código mínimo de integración y es compatible con la AGPL. Cualquier cambio que hagas al firmador debe atribuirse y compartirse bajo los mismos términos si se distribuye o se usa a través de la red.

Notas operativas:
- Configura `HACIENDA_ALT_SIGNER_DIR` apuntando a tu clon local del proyecto original.
- Es preferible mantener el firmador como repositorio independiente o submódulo Git para preservar historial y atribución.
