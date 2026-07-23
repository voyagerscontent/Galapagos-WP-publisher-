> Traducción al español. Versión original en inglés: [../PUBLISH_VIA_GITHUB.md](../PUBLISH_VIA_GITHUB.md).

# Publicar desde GitHub.com (sin máquina local)

Puedes ejecutar todo el pipeline desde tu navegador usando **GitHub Actions** —
sin terminal, sin instalación local. Subes un documento al repo y luego haces
clic en un botón para publicarlo. Las credenciales se almacenan una sola vez
como secretos de repositorio cifrados.

> **Publica en 3 pasos (una vez configurado):**
> 1. **Add file → Upload files** dentro de la carpeta `content/` → Commit.
> 2. **Actions → Publish to WordPress → Run workflow** → establece `file` en
>    `content/your-doc.md`, elige un `mode`.
> 3. Usa `preview` → `publish-draft` → `publish-live`. Revisa el borrador en
>    `wp-admin` entre los pasos.
>
> Si una ejecución falla en la autenticación, consulta
> [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## Configuración única: agrega tus secretos de WordPress

1. En el `wp-admin` de **galapagosislands.travel**, crea una Application
   Password: **Users → Profile → Application Passwords** → nómbrala
   `wp-publisher` → **Add New** → copia el valor (se ve como
   `abcd EFGH 1234 wxyz 5678 90ab`).

2. En **este repo de GitHub**, ve a
   **Settings → Secrets and variables → Actions → New repository secret** y
   agrega tres secretos:

   | Nombre            | Valor                                   |
   | ----------------- | --------------------------------------- |
   | `WP_BASE_URL`     | `https://www.galapagosislands.travel`   |
   | `WP_USERNAME`     | tu nombre de usuario de WordPress        |
   | `WP_APP_PASSWORD` | la Application Password del paso 1        |

   Estos están cifrados y nunca son visibles en los logs.

## Publicar un documento

1. **Agrega el documento al repo.** Abre la carpeta `content/` → **Add file →
   Upload files** → suelta tu `.docx`, `.md` o `.txt` → **Commit changes**.
   (Agrega un encabezado de metadatos corto — consulta `content/README.md`.)

2. **Ejecuta el workflow.** Ve a la pestaña **Actions** → **Publish to
   WordPress** → **Run workflow**, y completa:

   - **file** — p. ej. `content/my-cruise.md`
   - **mode** — comienza con `preview`, luego `publish-draft`, y finalmente
     `publish-live`
   - **page_type** — déjalo en blanco para autodetectar, o fuerza `cruise` /
     `tour` / `destination` / `blog_post`
   - **media** — `placeholder` (marcadores de imagen requerida) o `library`
     (reutiliza imágenes ya existentes en tu Media Library)

3. **Revisa el resultado.** Abre el job en ejecución:
   - El modo `preview` produce un artefacto descargable **preview-output**
     (el HTML generado + schema) e imprime un resumen — no se publica nada.
   - `publish-draft` / `publish-live` imprimen la URL de la entrada y un enlace
     de **edición** de WordPress en el log.

## Flujo recomendado

```
upload doc  →  run "preview"  →  read warnings, fix the doc if needed
            →  run "publish-draft"  →  review the draft in wp-admin
            →  run "publish-live"   →  it's live
```

Volver a ejecutar sobre el mismo archivo **actualiza** la entrada existente
(emparejada por slug) en lugar de crear un duplicado, por lo que es seguro
iterar.

## Alternativa: GitHub Codespaces

Si prefieres tener una terminal real (basada en navegador), abre el repo en un
**Codespace** (botón verde **Code** → **Codespaces** → **Create codespace**).
Luego sigue los pasos locales en [SETUP.md](SETUP.md): `cp .env.example .env`,
complétalo, `pip install -e ".[dev]"`, y usa `wp-publish` directamente. Agrega
los mismos tres valores como **Codespaces secrets** para que estén disponibles
automáticamente.
