> Traducción al español. Versión original en inglés: [../NEW_SITE.md](../NEW_SITE.md).

# Levantar un sitio nuevo

El motor de publicación (`src/wp_publisher/`) es **agnóstico al sitio**. Un sitio es simplemente
un repositorio que lleva su propia configuración, plantillas y secretos. Este
repo es el primer sitio de ese tipo → **https://www.galapagosislands.travel**.

## Qué es específico del sitio vs. compartido

| Específico del sitio (cambia por repo) | Motor compartido (copiado tal cual) |
| -------------------------------- | ---------------------------- |
| `config/site.yaml`               | `src/wp_publisher/**`         |
| `templates/*.yaml`               | dependencias de `pyproject.toml`         |
| `.env` (nunca se commitea)         | `tests/` (pruebas del motor)       |
| `samples/` (ejemplos opcionales)   | la CLI `wp-publish`            |
| descripción de `.claude/skills/` |                              |

## Pasos para crear otro sitio (p. ej. un sitio de Perú)

1. **Crea el repo** a partir de este (plantilla/fork o copia el árbol). Mantén
   `src/wp_publisher/` sin cambios.

2. **Edita `config/site.yaml`** — nombre/URL/logo/redes sociales de la organización, `title_suffix`
   de SEO, locale (`currency`, `country`) y la estrategia de medios
   predeterminada.

3. **Apunta `.env` al nuevo sitio de WordPress:**
   ```ini
   WP_BASE_URL=https://www.example.travel
   WP_USERNAME=...
   WP_APP_PASSWORD=...
   GDRIVE_ALLOWED_ACCOUNT=...      # optional Drive lock
   ```
   Consulta [SETUP.md](SETUP.md) para los pasos del App Password.

4. **Adapta `templates/`** a las secciones de ese sitio. Elimina las plantillas que no
   necesites, agrega nuevas (p. ej. `expedition.yaml`, `lodge.yaml`). Las plantillas son YAML
   puro — consulta [TEMPLATES.md](TEMPLATES.md).

5. **(Opcional) Reemplaza `samples/`** con documentos de ejemplo para ese sitio y actualiza
   la descripción de la skill en `.claude/skills/publish-to-wordpress/SKILL.md`.

6. **Verifica:**
   ```bash
   pip install -e ".[dev]"
   wp-publish templates
   wp-publish preview samples/<a-doc>.md     # no network
   wp-publish check                          # confirms the new WP site
   ```

## Mantener el motor sincronizado entre sitios

Por ahora el motor está **vendorizado** (copiado en cada repo de sitio). Cuando varios
sitios estén activos y quieras una única fuente de verdad, promueve `src/wp_publisher/` a
un paquete independiente (su propio repo / índice privado) y haz que cada sitio dependa de
él vía `pyproject.toml` en lugar de vendorizarlo. El código ya está estructurado para
esto: el motor nunca importa nada específico del sitio — solo lee
`config/site.yaml` y el entorno.
