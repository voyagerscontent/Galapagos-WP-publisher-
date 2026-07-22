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

## Variables de entorno (secretos)

| Variable | Para qué |
|---|---|
| `WP_BASE_URL` | `https://www.galapagosislands.travel` |
| `WP_USERNAME` | usuario de WordPress (rol Editor/Admin) |
| `WP_APP_PASSWORD` | Application Password de WP (Usuarios → Perfil → Application Passwords) |
| `DASHBOARD_PASSWORD` | contraseña compartida para entrar al dashboard |

> Sin `WP_*` el dashboard arranca en **modo vista previa** (detecta y valida,
> pero no publica). Sin `DASHBOARD_PASSWORD` queda **abierto** (solo para local).

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
