> Traducción al español. Versión original en inglés: [../SETUP.md](../SETUP.md).

# Configuración

## 1. Instalación

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"          # add the gdrive extra if you'll use Drive:
pip install -e ".[dev,gdrive]"
```

Copia la plantilla de entorno y complétala:

```bash
cp .env.example .env
```

## 2. Contraseña de aplicación de WordPress

El publicador se autentica con una **Application Password** — una credencial
revocable que no expone la contraseña real de la cuenta. Funciona en cualquier
WordPress moderno (5.6+) con la REST API habilitada.

1. Inicia sesión en el panel de administración de WordPress de
   **galapagosislands.travel** con el usuario que debe ser propietario del
   contenido.
2. Ve a **Users → Profile** (o **Users → Your Profile**).
3. Desplázate hasta **Application Passwords**.
4. Escribe un nombre como `wp-publisher` y haz clic en **Add New Application Password**.
5. Copia la contraseña generada (se ve como `abcd EFGH 1234 wxyz 5678 90ab`).
   Los espacios están bien — pégala tal cual.
6. Coloca los valores en `.env`:

   ```ini
   WP_BASE_URL=https://www.galapagosislands.travel
   WP_USERNAME=your-wp-username
   WP_APP_PASSWORD=abcd EFGH 1234 wxyz 5678 90ab
   WP_DEFAULT_STATUS=draft
   ```

Verifícalo:

```bash
wp-publish check
```

Deberías ver el usuario autenticado y qué plugin de SEO (Yoast / RankMath /
ninguno) fue detectado.

### Permisos y notas

- El usuario necesita la capacidad `edit_posts` / `publish_posts` (Author o
  superior; se recomienda Editor).
- Algunos hostings o plugins de seguridad bloquean las escrituras REST o Basic
  Auth. Si `check` falla con 401/403, confirma que las Application Passwords
  estén habilitadas y que la REST API no esté detrás de un firewall.
- Las categorías y etiquetas se crean automáticamente si no existen.

## 3. Google Drive (opcional)

Se usa solo si ingieres documentos directamente desde Drive. Configura
`GDRIVE_ALLOWED_ACCOUNT` en `.env` para restringir la ingesta a una sola cuenta
de Google (este repo la fija por defecto en **businessops@latintrails.com** — la
cuenta de contenido compartida); el sistema entonces rechaza cualquier otra
cuenta. Déjala en blanco para permitir cualquier cuenta autorizada.

1. En la [Google Cloud Console](https://console.cloud.google.com/), crea (o
   reutiliza) un proyecto.
2. Habilita la **Google Drive API**.
3. En **APIs & Services → Credentials**, crea un **OAuth client ID** de tipo
   **Desktop app**.
4. Descarga el JSON y guárdalo como `credentials.json` en la raíz del proyecto
   (la ruta es configurable mediante `GDRIVE_CREDENTIALS_FILE`).
5. La primera ejecución abre un navegador para autorizar. Inicia sesión con la
   cuenta configurada en `GDRIVE_ALLOWED_ACCOUNT`. Se almacena en caché un
   `token.json` para la próxima vez.

```bash
wp-publish gdrive "8-Day Galapagos" --preview
```

Si autorizas la cuenta incorrecta, la ejecución se aborta con un error claro —
elimina `token.json` y vuelve a intentarlo con la cuenta correcta.

> `credentials.json`, `token.json` y `.env` están en git‑ignore. Nunca los
> subas al repositorio.

## 4. Valores predeterminados del sitio

Edita `config/site.yaml` para establecer los detalles de tu organización, los
límites de longitud de SEO y la estrategia de medios predeterminada (`library`
vs `placeholder`). Estos alimentan la salida de schema.org y el optimizador de
SEO.
