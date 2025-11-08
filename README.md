# Gestion-Inventarios-Producci-n
Repositorio de producción del proyecto de gestión de inventarios del curso de taller multimedia

## Firmador de Hacienda como submódulo

Para cumplir con la licencia AGPL del firmador alternativo y facilitar actualizaciones, este proyecto puede utilizar el repositorio `hacienda-firmador-php` como submódulo bajo `tools/hacienda-firmador-php`.

### Inicialización del submódulo (una sola vez)

1. Agregar el submódulo apuntando al upstream (o a tu fork):
	 - Upstream recomendado:
		 - URL: `https://github.com/enzojimenez/hacienda-firmador-php`
		 - Ruta: `tools/hacienda-firmador-php`
2. Inicializar y actualizar los submódulos en tu clon:
	 - `git submodule update --init --recursive`

### Si ya tienes una copia modificada local (fork necesario)

1. Crea un repositorio en tu cuenta (fork personal) p. ej. `tuusuario/hacienda-firmador-php`.
2. Desde tu copia local actual (`tools/hacienda-firmador-php`):
	- Inicializa repo si no lo es: `git init` (si ya es repo puedes saltar este paso)
	- Agrega remoto de tu fork: `git remote add origin https://github.com/tuusuario/hacienda-firmador-php`
	- Crea un commit con tus cambios: `git add . && git commit -m "Aplicar cambios locales necesarios"`
	- Publica: `git branch -M main && git push -u origin main`
3. Elimina la carpeta del proyecto del árbol de este repo (la volverás a traer como submódulo):
	- `git rm -r --cached tools/hacienda-firmador-php`
	- Elimina físicamente la carpeta (o muévela fuera temporalmente).
4. Añade el submódulo apuntando a tu fork:
	- `git submodule add https://github.com/tuusuario/hacienda-firmador-php tools/hacienda-firmador-php`
	- `git submodule update --init --recursive`
5. Haz commit en el repo principal para registrar el puntero del submódulo.

### Actualizar a la última versión del submódulo

- Dentro de `tools/hacienda-firmador-php` haz checkout del tag/commit deseado y luego `git add` y `git commit` en el repo principal para registrar el puntero.

### Variables de entorno relevantes

- `HACIENDA_USE_ALT_SIGNER=true`
- `HACIENDA_ALT_SIGNER_DIR` debe apuntar a la ruta absoluta del submódulo en tiempo de ejecución.
	- Ejemplo contenedor: `/app/tools/hacienda-firmador-php`
	- Ejemplo local Windows: `C:\\laragon\\www\\Gestion-Inventarios-Desarrollo-back\\tools\\hacienda-firmador-php`
- Certificado PKCS#12 en base64 (recomendado para despliegues):
	- `HACIENDA_USE_P12_BASE64=true`
	- `HACIENDA_CERT_P12_BASE64=<contenido base64>`
	- `HACIENDA_CERT_PASSWORD=<pin>`

### Notas de licencia (AGPL)

- El firmador `hacienda-firmador-php` está licenciado bajo AGPL-3.0. Mantén los avisos y licencia al distribuirlo o al operar el servicio en red.
- Si realizas modificaciones al firmador, publícalas en tu fork y enlázalo en `THIRD_PARTY_NOTICES.md`.
- La aplicación integra el firmador como proceso separado mediante `scripts/sign_bridge.php`.
