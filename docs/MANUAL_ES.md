# Manual de uso — Publicador Galápagos

Manual práctico del sistema que convierte un documento (HTML, Word) en una página
de WordPress optimizada (SEO + schema) en **galapagosislands.travel**.

Está dividido en dos partes:

- **[Parte A — Marketing](#parte-a--marketing-uso-diario)**: cómo subir un documento
  y publicarlo. No necesitas saber de código.
- **[Parte B — Técnico](#parte-b--técnico-mantenimiento)**: cómo está hecho el
  sistema, el repositorio, el motor y el workflow de n8n por dentro.

> **Regla de oro de seguridad:** todo se publica como **borrador** y con el sufijo
> **`-test`** en la URL. Nada sale “en vivo” automáticamente. Un humano revisa el
> borrador en WordPress y decide.

---

# Parte A — Marketing (uso diario)

## 1. Qué hace el sistema (en un párrafo)

Tú subes un documento. El sistema lo lee, detecta **qué tipo de página** es (por su
URL), arma el contenido, lo optimiza para SEO, genera el schema (los datos que
Google usa para los resultados enriquecidos) y crea un **borrador** en WordPress
rellenando los campos ACF. El tema de WordPress (con Elementor) se encarga del
diseño. Tú solo revisas el borrador y lo publicas cuando esté listo.

## 2. Los 3 formatos de documento

El sistema entiende **tres formatos**. Es clave mandar el correcto:

| Formato | Qué es | ¿Se sube? |
|---|---|---|
| **HTML + `schema.json`** | La página en HTML **más** su archivo de schema aparte | ✅ Sí — se suben **los dos** |
| **CMS Stage 8** (`.docx`) | Un Word que **contiene el contenido** (bloques `[AIO BLOCK]`, tablas, FAQs…) | ✅ Sí |
| **Editor Copy** (verde, `.docx`) | Un Word que solo trae **instrucciones para el editor** (resaltadas en verde) | ❌ **No** — no es contenido |

**Reglas simples:**
- Si te llega un **HTML** con su **`.json`** aparte → sube **ambos**.
- Si el HTML ya trae el schema adentro → basta el HTML.
- Si te llega un **CMS Stage 8** (Word con contenido) → sube solo ese `.docx`.
- El **Editor Copy verde** es solo para leer las indicaciones — **nunca se sube**.

## 3. Publicar con n8n (paso a paso)

El equipo publica a través de un **formulario de n8n** que armamos. Hace todo el
circuito: guarda el documento, lo publica como borrador y lo deja en WordPress.

### 3.1 Primera vez: configurar la credencial (una sola vez)

El workflow ya está importado en n8n. Solo necesita **una credencial de GitHub**:

1. n8n → **Credentials → Add credential → Header Auth**.
2. Llena:
   - **Name:** `GitHub Publisher Authorization`
   - **Header Name:** `Authorization`
   - **Header Value:** `Bearer TU_TOKEN`  ← el token va con la palabra `Bearer` y un espacio.
3. El token de GitHub necesita permisos **Contents: Read and write** + **Actions: Read and write** sobre el repo `voyagerscontent/galapagos-wp-publisher-`.
4. Asigna esa credencial a los **3 nodos HTTP** del workflow: *Guardar documento*, *Guardar schema* y *Publicar*.

> ⚠️ El token **nunca** se escribe en el JSON ni se comparte por chat — solo vive
> dentro de la credencial de n8n.

### 3.2 Publicar un documento

1. Abre el workflow **“Publicar página Galápagos (borrador test)”** en n8n.
2. Abre el nodo **“Form: subir documento”** y copia su **URL del formulario**
   (Test URL para pruebas; Production URL cuando el workflow está en *Publish*).
3. Abre esa URL en el navegador:
   - **Documento**: sube el HTML o el `.docx` (CMS Stage 8).
   - **Schema JSON** (opcional): si el HTML trae el schema aparte, sube el `.json`.
4. Envía el formulario.
5. En ~1 minuto el borrador estará en WordPress.

### 3.3 Qué revisar después

- **GitHub → Actions**: verás el proceso `Publish to WordPress` ejecutándose.
- **WordPress**: aparece un borrador nuevo con el sufijo **`-test`** en la URL.
- Revisa el contenido, imágenes y el diseño (lo pinta Elementor).

## 4. Republicar / corregir sin perder imágenes

Si corriges el documento y lo vuelves a subir, el sistema **no crea un duplicado**:
encuentra el borrador `-test` con el mismo slug y lo **actualiza**.

- Las **imágenes e íconos que subiste a mano** en WordPress **se conservan** al
  republicar (el sistema los detecta y los respeta).
- El **texto** sí se reemplaza con la versión nueva del documento.
- En el repositorio queda guardada una copia de cada documento subido (historial).

## 5. Errores comunes y qué hacer

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| El borrador salió sin schema | Subiste solo el HTML y el schema venía aparte | Vuelve a subir el HTML **con** su `.json` |
| Se publicó pero con tipo de página equivocado | La URL del documento no cae en una sección conocida | Avisa al técnico: hay que mapear esa sección de URL |
| Un campo (imagen/texto) no se guarda en WordPress | Problema de configuración del grupo ACF | Avisa al técnico (ver Parte B §10) |
| El formulario da error en un nodo | Falta la credencial o el token no tiene permisos | Revisa §3.1 (credencial en los 3 nodos, permisos Contents + Actions) |

---

# Parte B — Técnico (mantenimiento)

## 6. Mapa del repositorio

```
galapagos-wp-publisher-/
├── src/wp_publisher/     # EL MOTOR (Python): lee doc → arma ACF → publica
│   ├── ingest/           #   lectores de formato: html_reader, cms (Stage 8), docx, markdown, text, gdrive
│   ├── content/          #   composición: compose, richtext, directives
│   ├── acf/              #   config.py (carga acf.yaml) + mapper.py (componentes → payload ACF)
│   ├── parse/            #   pagetype.py (detecta el tipo de página por URL/heurística)
│   ├── seo/ schema/ media/ wordpress/   # SEO, JSON-LD, medios, cliente REST + publisher
│   ├── pipeline.py cli.py config.py
├── config/
│   ├── site.yaml         # config del sitio + ROUTING por URL
│   ├── acf.yaml          # mapeo ACF por defecto (perfil "flat")
│   └── acf/              # perfiles por sección: island.yaml, wildlife_single.yaml, informative.yaml
├── templates/            # perfiles de tipo de página (schema, categorías, parent)
├── content/             # ALMACÉN de documentos (la "memoria"): islands, wildlife, cruises, informative, uploads/
├── wordpress-acf/        # definiciones de los grupos ACF (JSON para importar en WP)
├── wordpress-plugin/     # plugin de Elementor (widgets que pintan los campos ACF)
├── wordpress-theme/      # tema
├── dashboard/            # dashboard alternativo en Streamlit (opción B a n8n)
├── docs/                 # documentación (incluye este manual)
├── samples/ assets/ tests/
└── .github/workflows/publish.yml   # el workflow de GitHub Actions que corre el motor
```

## 7. El motor: flujo y comandos

**Flujo interno:**
```
ingest → Document → detectar tipo de página → SEO → componer → resolver medios
       → schema.org JSON-LD → mapear a ACF → publicar (REST, campo `acf`)
```
Es **determinista** (no usa IA en tiempo de ejecución). La salida son **campos ACF**,
nunca bloques Gutenberg; `post_content` queda vacío.

**Comandos (CLI `wp-publish`):**

| Comando | Qué hace |
|---|---|
| `wp-publish check` | Verifica credenciales de WordPress y detecta el plugin SEO |
| `wp-publish templates` | Lista los tipos de página disponibles |
| `wp-publish preview <doc>` | Arma la página **sin publicar** (prueba local, sin red) |
| `wp-publish publish <doc>` | Publica (por defecto **borrador**) |
| `wp-publish gdrive <id>` | Ingesta desde Google Drive (restringido por cuenta) |

## 8. Ruteo por URL → tipo de página, parent y grupo ACF

El sistema **no** te pide elegir el tipo de página: lo decide por el **primer
segmento de la URL canónica** del documento (`config/site.yaml → routing.url_sections`):

| Sección de URL | Tipo de página | Parent | Grupo ACF |
|---|---|---|---|
| `/planning/…` | `informative` | (sin parent) | Informative Page |
| `/wildlife/…` | `wildlife_single` | Wildlife (9657) | Wildlife Single |
| `/islands/…` | `destination` | Islands (10263) | Island Guide |
| `/cruises/…`, `/galapagos-cruises/…` | `informative` | (sin parent) | Informative Page (temporal) |

Cada tipo lleva su **parent** y su **grupo ACF**. Para agregar una sección nueva,
se añade el segmento aquí y su `templates/<tipo>.yaml` + `config/acf/<perfil>.yaml`.

## 9. El workflow de n8n por dentro

Workflow **“Publicar página Galápagos (borrador test)”** — 6 nodos en cadena:

1. **Form: subir documento** — formulario con 2 campos: *Documento*
   (`.html/.htm/.docx`) y *Schema JSON* opcional (`.json`).
2. **Preparar archivo** (Code) — lee los bytes reales del binario con
   `getBinaryDataBuffer`, los pasa a base64 y arma la ruta
   `content/uploads/<timestamp>-<nombre>`. Si hay HTML + `.json`, los nombra con el
   **mismo nombre base** para que el motor detecte el schema como *sidecar*.
3. **Guardar documento** (HTTP PUT) — commitea el documento al repo (GitHub Contents API).
4. **¿Hay schema?** (IF) — si se adjuntó `.json`, va a guardarlo; si no, salta a publicar.
5. **Guardar schema** (HTTP PUT) — commitea el `.json` (solo si existe).
6. **Publicar** (HTTP POST) — dispara `publish.yml` con
   `mode=publish-draft`, `test_mode=true`, `update_existing=true`.

**Credenciales:** una sola credencial *Header Auth* (`Authorization: Bearer <token>`)
en los 3 nodos HTTP. El token necesita **Contents: write** + **Actions: write**.

**Rama:** los commits y el dispatch van a `claude/wordpress-automation-system-wlnxlp`
(la misma que usa `publish.yml`). Si se mergea a `main`, actualizar `branch`/`ref`
en los nodos HTTP.

### 9.1 El workflow de GitHub Actions (`publish.yml`)

Inputs principales:

| Input | Para qué |
|---|---|
| `file` | Ruta del documento en el repo (o `ALL_ISLANDS` / `ALL_WILDLIFE`) |
| `mode` | `preview` · `publish-draft` · `publish-live` |
| `page_type` | Forzar tipo (vacío = auto por URL) |
| `slug` | Forzar slug (vacío = derivar del doc) |
| `test_mode` | `true` añade `-test` al slug |
| `media` | Estrategia de medios (`placeholder`…) |
| `update_existing` | Sobrescribir un post con el mismo slug (off = rechaza, seguro) |
| `only_fields` | Quirúrgico: escribir SOLO esos campos ACF (nunca crea página) |

## 10. Grupos ACF y cuándo re-importar

- Las definiciones viven en `wordpress-acf/*.json` (uno por tipo de página).
- El motor escribe **por nombre** de campo vía REST; WordPress y Elementor **leen
  por nombre**. El guardado manual en el admin de WordPress usa la **clave** del
  campo (`field_…`).
- **Cada clave de campo ACF debe empezar con `field_`.** Si un grupo tiene claves
  malformadas, el guardado manual de los **repetidores** se rompe (el motor sigue
  publicando porque va por nombre, lo que **oculta** el problema).
- Cuando se corrige o cambia un grupo (`wordpress-acf/<grupo>.json`), hay que
  **re-importarlo** en WordPress: **ACF → Tools → Import Field Groups**. Como la
  clave del grupo no cambia, actualiza el existente. Los datos guardados van por
  nombre, así que **no se pierden**.
- Tras actualizar el plugin de Elementor, correr **“Regenerate CSS & Data”**.

## 11. El plugin de Elementor (widgets)

`wordpress-plugin/island-elementor-widgets.php` — widgets que **pintan** los campos
ACF (el motor no emite HTML de diseño): Hero, Feature Sections (tarjetas zig-zag +
infografía + badge de ícono con recoloreo), FAQs (emite `FAQPage` JSON-LD),
Quick Facts, Visitor Sites, Wildlife, CTA, Related Links, y el widget de Schema
que imprime el campo `seo_schema` por página. Los controles dimensionales tienen
versión responsive.

## 12. Historial de problemas resueltos (troubleshooting técnico)

| Problema | Causa | Solución aplicada |
|---|---|---|
| n8n: `content is not valid Base64` | El binario en modo *filesystem* devolvía `filesystem-v2`, no el base64 | Leer los bytes con `getBinaryDataBuffer` y convertir a base64 |
| n8n: `Required input 'file' not provided` | El dispatch leía `$json.path` (ya no existe tras el commit) | Referenciar `$('Preparar archivo').first().json.mainPath` |
| HTML con schema aparte no aplicaba | El workflow subía un solo archivo | Soporte de 2 archivos con el mismo nombre base (sidecar) |
| Doble grupo ACF en una página | Parent y `gp_page_type` desactualizados atraían 2 grupos | `parent=0` cuando no hay parent + estampar siempre `gp_page_type` |
| Imágenes de Feature Sections se borraban al republicar | El match de filas fallaba con WYSIWYG normalizado por WordPress | Normalizar (quitar tags/entidades) antes de comparar; preservar medios no gestionados |
| Feature Sections no guardaba nada en el admin (islas) | Las 99 claves del grupo de islas no tenían prefijo `field_` | Re-clave a `field_isl_…` + re-importar el grupo |

---

## Referencias

Documentación técnica complementaria en `docs/`: `SETUP.md`, `WORKFLOW.md`,
`PUBLISH_VIA_GITHUB.md`, `AUTHORING_GUIDE.md`, `ACF.md`, `TEMPLATES.md`,
`WILDLIFE_TEMPLATE.md`, `NEW_SITE.md`, `TROUBLESHOOTING.md`.
