# Publicador Galápagos — Dashboard de Marketing

Un panel web para que marketing suba un documento y publique un **borrador de
test** (`-test`) en WordPress, sin tocar Git ni elegir el tipo de página. Es una
UI de Streamlit sobre el motor `wp_publisher` (misma lógica que
`wp-publish publish --test`).

## Qué hace

1. **Subir** — HTML + `schema.json`, o un Word (CMS Stage 8).
2. **Detectar** — formato + tipo de página por la URL canónica
   (`/wildlife/` → wildlife_single, `/islands/` → destination, `/planning/` y
   `/cruises/` → informative). Muestra parent, grupo ACF y slug.
3. **Validar** — título correcto, nº de secciones/tablas/FAQs, schema presente,
   flags VERIFY.
4. **Publicar / Republicar** — crea el borrador `-test`; si ya existe, lo
   actualiza.
5. **Memoria (repo como almacén)** — al publicar, el documento se guarda en el
   repo (`content/<sección>/<slug>.<ext>` + su `.json`). La pestaña **Documentos
   guardados** lista lo guardado y permite **republicar sin volver a subir**;
   Git conserva cada versión (para comparar). Siempre puedes **resubir** una
   versión corregida desde “Subir documento”.

## Variables de entorno (secretos)

| Variable | Para qué |
|---|---|
| `WP_BASE_URL` | `https://www.galapagosislands.travel` |
| `WP_USERNAME` | usuario de WordPress (rol Editor/Admin) |
| `WP_APP_PASSWORD` | Application Password de WP (Usuarios → Perfil → Application Passwords) |
| `DASHBOARD_PASSWORD` | contraseña compartida para entrar al dashboard |
| `GITHUB_TOKEN` | token con permiso de **Contents: write** al repo (memoria de documentos) |
| `GITHUB_REPO` | `voyagerscontent/galapagos-wp-publisher-` |
| `GITHUB_BRANCH` | rama donde se guardan los documentos (p. ej. `main`) |

> Sin `WP_*` el dashboard arranca en **modo vista previa** (detecta y valida,
> pero no publica). Sin `DASHBOARD_PASSWORD` queda **abierto** (solo local).
> Sin `GITHUB_*` funciona igual, pero **no guarda memoria** (cada republish
> requeriría volver a subir el documento).

### Token de GitHub (memoria)

GitHub → **Settings → Developer settings → Personal access tokens →
Fine-grained tokens → Generate new token**. Da acceso **solo a este repo** con
permiso **Repository permissions → Contents → Read and write**. Cópialo y
guárdalo como `GITHUB_TOKEN` (solo se ve una vez).

## Correr en local

```bash
pip install -r dashboard/requirements.txt
export WP_BASE_URL="https://www.galapagosislands.travel"
export WP_USERNAME="tu_usuario"
export WP_APP_PASSWORD="xxxx xxxx xxxx xxxx"
export DASHBOARD_PASSWORD="clave-del-equipo"
streamlit run dashboard/app.py
```

## Desplegar hoy — Streamlit Community Cloud (gratis, ~5 min)

1. Entra a **share.streamlit.io** con tu cuenta de GitHub.
2. **New app** → elige este repo y la rama, y como *Main file path* pon
   `dashboard/app.py`.
3. **Advanced settings → Secrets**, pega (formato TOML):
   ```toml
   WP_BASE_URL = "https://www.galapagosislands.travel"
   WP_USERNAME = "tu_usuario"
   WP_APP_PASSWORD = "xxxx xxxx xxxx xxxx"
   DASHBOARD_PASSWORD = "clave-del-equipo"
   ```
4. **Deploy**. Te da una URL para compartir con marketing.

## Desplegar en Render / Railway (con Docker)

Usa el `dashboard/Dockerfile`. En el servicio, define las mismas variables de
entorno de la tabla. Puerto: `8501`.
