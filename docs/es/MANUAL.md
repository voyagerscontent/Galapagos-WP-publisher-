> Versión en español · English version: [../MANUAL.md](../MANUAL.md).

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
diseño.

El sistema deja un **borrador**, pero **no** lo deja listo para publicar por sí
solo. Antes de publicar, una persona debe **revisar, validar y completar** algunas
cosas a mano — sobre todo el **SEO** y la **desindexación** (`noindex`), que el
sistema **no** pone. Ver el checklist obligatorio en la [§4](#4-antes-de-publicar--checklist-obligatorio).

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

## 3. Publicar con n8n (uso)

El sistema ya está **configurado y activo** — solo tienes que usar el formulario.
Publicar es subir el documento y enviar; el resto es automático.

### Cómo publicar

1. Abre la **URL del formulario** de n8n (te la comparte el equipo técnico; es fija).
2. Llena el formulario:
   - **Documento**: sube el HTML o el `.docx` (CMS Stage 8).
   - **Schema JSON** (opcional): solo si es un HTML cuyo schema viene aparte, sube el `.json`.
3. Presiona **enviar**.
4. En **~1 minuto** el borrador estará en WordPress.

Eso es todo. No hay que activar nada, ni tocar n8n, ni elegir el tipo de página
(se detecta solo por la URL del documento).

### Qué revisar después

- **WordPress**: aparece un borrador nuevo con el sufijo **`-test`** en la URL.
- Revisa el contenido, las imágenes y el diseño (lo pinta Elementor).
- Si algo se ve mal, corrige el documento y **vuelve a subirlo** (ver §5).

## 4. Antes de publicar — checklist obligatorio

El borrador **no está listo tal cual sale**. El sistema arma el contenido y
pre-rellena parte del SEO, pero **tú debes revisar, validar y completar** esto en
WordPress **antes** de publicar:

**1. Desindexar la página (`noindex`) — el sistema NO lo hace.**
- En el plugin SEO (Rank Math / Yoast) de la página, pon **`noindex`** (robots:
  no index, no follow) mientras sea una página de prueba/`-test` o no esté aprobada.
- Así Google no la indexa antes de tiempo. Solo se quita el `noindex` cuando la
  página final se aprueba para salir en vivo.

**2. Validar y completar el SEO — a mano.**
El sistema puede pre-rellenar el **meta title**, la **meta description** y el
**focus keyword**, pero **no confíes en que estén completos ni finales**. Revisa y
completa en el plugin SEO:
- **Meta title** — correcto, con la keyword, dentro del largo recomendado.
- **Meta description** — atractiva y dentro del largo.
- **Focus keyword / keywords** — ponlas/ajústalas tú.
- **Schema** — el sistema genera el JSON-LD; verifica que corresponda a la página.

**3. Validar el contenido y el diseño.**
- Textos, imágenes, tablas, FAQs y el render de Elementor.

**4. Recién ahí, publicar.**
- Cambia el estado a publicado **solo cuando** 1–3 estén hechos y aprobados.

> En resumen: el sistema te ahorra el 80% del trabajo, pero el **SEO final y el
> `noindex` son responsabilidad del revisor**, no del sistema.

## 5. Republicar / corregir sin perder imágenes

Si corriges el documento y lo vuelves a subir, el sistema **no crea un duplicado**:
encuentra el borrador `-test` con el mismo slug y lo **actualiza**.

- Las **imágenes e íconos que subiste a mano** en WordPress **se conservan** al
  republicar (el sistema los detecta y los respeta).
- El **texto** sí se reemplaza con la versión nueva del documento.
- En el repositorio queda guardada una copia de cada documento subido (historial).

## 6. Errores comunes y qué hacer

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| El borrador salió sin schema | Subiste solo el HTML y el schema venía aparte | Vuelve a subir el HTML **con** su `.json` |
| La página aparece indexada / sin SEO | El sistema no pone `noindex` ni finaliza el SEO | Complétalo a mano (ver §4) |
| Se publicó pero con tipo de página equivocado | La URL del documento no cae en una sección conocida | Avisa al técnico: hay que mapear esa sección de URL |
| Un campo (imagen/texto) no se guarda en WordPress | Problema de configuración del grupo ACF | Avisa al técnico (ver Parte B §10) |
| El formulario da error en un nodo | Falta la credencial o el token no tiene permisos | Avisa al técnico (config en Parte B §9.1) |

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

**Rama:** los commits y el dispatch van a `claude/wordpress-automation-system-wlnxlp`
(la misma que usa `publish.yml`). Si se mergea a `main`, actualizar `branch`/`ref`
en los nodos HTTP.

### 9.1 Configuración inicial (una sola vez — técnico)

El workflow (`n8n-galapagos-v3-FINAL.json`) se importa en n8n una vez y se deja
activo. Solo necesita **una credencial de GitHub**:

1. n8n → **Credentials → Add credential → Header Auth**.
2. **Header Name:** `Authorization` · **Header Value:** `Bearer <TOKEN>`.
3. El token de GitHub necesita **Contents: write** + **Actions: write** sobre
   `voyagerscontent/galapagos-wp-publisher-`.
4. Asignar esa credencial a los **3 nodos HTTP**: *Guardar documento*,
   *Guardar schema* y *Publicar*.
5. **Save** → **Publish** (deja el formulario en su Production URL, fija, para
   compartir con marketing).

> ⚠️ El token vive **solo** dentro de la credencial de n8n — nunca en el JSON del
> workflow ni en texto plano.

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

## 12. Los 3 formatos de documento por dentro (contrato técnico)

Cómo lee el motor cada formato. El lector correcto se elige por la extensión y por
la detección de contenido (`looks_like_stage8`).

### 12.1 HTML de producción (+ sidecar `schema.json`)
Lector: `ingest/html_reader.py`. Es el **camino canónico**.
- Segmenta el cuerpo por cada **`<h2>`** (soporta layout con `<section>` o plano).
- Mapeo de bloques a campos:
  - `.answer-box` → `geo_answer` (respuesta GEO/AIO)
  - `.dateline` / `.byline` / autor del JSON-LD → `author`
  - `.conversion-band` / `.cta-primary` / `.cta-close` → CTA (las primarias primero); `.lead-magnet` → CTA secundaria
  - `#related` / `h2#related` → `related_links` (consume solo el `<ul>/<ol>` que le sigue)
  - `footer.sources` / `p.sources` → `sources`
  - `<script type="application/ld+json">` **o** sidecar `<nombre>.schema.json` → `seo_schema`
- Captura `metadata["slug"]` (último segmento de la URL) y `metadata["url_section"]`
  (primer segmento → usado por el ruteo, ver §8).
- El cuerpo de cada sección se conserva **tal cual** como HTML.

### 12.2 CMS Stage 8 (`.docx` con contenido)
Lector: `ingest/cms.py` (adaptador Stage 8) vía `ingest/docx_reader.py`.
- Se reconoce por el banner **`CMS Stage N`** + marcadores **`[AIO BLOCK]`**.
- `[AIO BLOCK 1 — speakable]` → `geo_answer`.
- Descarta los marcadores de producción (`[AIO/PHOTO/INFOGRAPHIC …]`).
- **Tablas** se conservan como HTML.
- **FAQs** → repetidor `faqs`.
- **Sources & Citations** → `sources` (descarta rutas internas `.md`/`.csv`).
- Recupera **Meta Title / Meta Description** del documento.
- Corta la basura final del pipeline (`VERIFY Summary`, `WF5-7`, etc.).
- Captura `url_section` desde la línea `Slug:`.

### 12.3 Editor Copy (verde, `.docx`)
- **NO es contenido** — son instrucciones para el editor (resaltadas en verde).
- **No se ingiere.** Si te llega este archivo, no se sube al sistema.

## 13. Troubleshooting

### 13.1 Operativo (qué revisar cuando algo falla)

| Síntoma | Causa | Qué hacer |
|---|---|---|
| Actions falla con error de autenticación | `WP_USERNAME`/`WP_APP_PASSWORD` mal o caducados | Revisar los secrets del repo; regenerar el Application Password |
| “A page with slug … already exists. Refusing to overwrite” | El slug ya existe y `update_existing` está en off | Publicar con `update_existing: true` (o cambiar el slug) |
| La página se publica sin parent / el grupo ACF no aparece | El parent (slug) no existe o `gp_page_type` no coincide | Crear la página parent con ese slug; verificar el ruteo por URL (§8) |
| n8n: error en un nodo HTTP (401/403) | Falta la credencial o el token sin permisos | Credencial en los 3 nodos; token con Contents + Actions (§9.1) |
| Un repetidor no guarda en el admin | Claves ACF sin prefijo `field_` | Re-importar el grupo corregido (§10) |
| Schema duplicado en la página | El plugin SEO también emite JSON-LD | Apagar el schema del plugin (§14) |

### 13.2 Historial de bugs resueltos

| Problema | Causa | Solución aplicada |
|---|---|---|
| n8n: `content is not valid Base64` | El binario en modo *filesystem* devolvía `filesystem-v2`, no el base64 | Leer los bytes con `getBinaryDataBuffer` y convertir a base64 |
| n8n: `Required input 'file' not provided` | El dispatch leía `$json.path` (ya no existe tras el commit) | Referenciar `$('Preparar archivo').first().json.mainPath` |
| HTML con schema aparte no aplicaba | El workflow subía un solo archivo | Soporte de 2 archivos con el mismo nombre base (sidecar) |
| Doble grupo ACF en una página | Parent y `gp_page_type` desactualizados atraían 2 grupos | `parent=0` cuando no hay parent + estampar siempre `gp_page_type` |
| Imágenes de Feature Sections se borraban al republicar | El match de filas fallaba con WYSIWYG normalizado por WordPress | Normalizar (quitar tags/entidades) antes de comparar; preservar medios no gestionados |
| Feature Sections no guardaba nada en el admin (islas) | Las 99 claves del grupo de islas no tenían prefijo `field_` | Re-clave a `field_isl_…` + re-importar el grupo |

## 14. SEO: plugin y schema

El sistema deja parte del SEO listo, pero **la configuración del plugin SEO y el
`noindex` son responsabilidad del equipo** (ver también §4).

### 14.1 Qué hace el motor (automático)

- Escribe **meta title**, **meta description** y **focus keyword** en las claves
  del plugin configurado en `config/site.yaml` → `seo.seo_plugin` (fijado a
  **`rankmath`**: `rank_math_title`, `rank_math_description`,
  `rank_math_focus_keyword`). Con `auto` detecta el plugin activo (Yoast primero).
- Genera el **schema JSON-LD** de la página (desde el documento) y lo entrega en el
  campo ACF `seo_schema`, que pinta el widget **“Island Schema”**.
- **No** pone `noindex` — es manual.

### 14.2 Plugin recomendado: Rank Math

Se usa **Rank Math** por sus funciones de SEO/GEO en el tier gratuito (varias
keywords, señales AIO, redirecciones). Yoast también funciona, pero se aprovecha
menos.

**Reglas de oro:**

1. **Un solo plugin activo.** Nunca Yoast y Rank Math a la vez: duplican metas y
   schema, y el motor **detecta Yoast primero** (escribiría en el plugin equivocado).
2. **Apaga el schema del plugin.** El sistema ya emite su propio JSON-LD (widget
   “Island Schema”). Si el plugin también lo emite, hay **schema duplicado** — malo
   para GEO/AIO y para los rich results.

### 14.3 Configurar Rank Math (una vez)

1. Deja **solo Rank Math activo** (Yoast desactivado/eliminado tras migrar, §14.4).
2. **Rank Math → Titles & Meta →** por cada tipo de contenido (Posts, Pages y los
   CPT de islas/wildlife) → **Schema Type = None / Off**.
3. Revisa que **Sitemaps** y **Breadcrumbs** no dupliquen lo que ya hace el tema.

### 14.4 Migrar de Yoast a Rank Math

1. **Backup** de la base de datos (o export) antes de empezar.
2. Deja **Yoast activo**; instala y activa **Rank Math** (los dos activos **solo**
   durante la importación — no publiques/republiques en esa ventana).
3. **Rank Math → Status & Tools → Import & Export → Import from Yoast**: importa
   **Titles & Meta**, **focus keywords**, **robots meta (`noindex`)** y
   **redirecciones**.
4. Verifica en 2–3 páginas que llegaron meta title/description, focus keyword y `noindex`.
5. **Desactiva y elimina Yoast.** Queda solo Rank Math activo.
6. Aplica §14.3 (apagar el schema del plugin).

> El motor no necesita cambios: en la siguiente publicación detecta Rank Math y
> escribe las metas en `rank_math_*` automáticamente.

### 14.5 Checklist SEO por página (antes de publicar)

- [ ] `noindex` puesto mientras la página sea `-test` / no aprobada (§4).
- [ ] Meta title, meta description y keyword revisados y completados.
- [ ] **Un solo** bloque de schema en la página (el del sistema, no el del plugin).

---

## Referencias

Documentación técnica complementaria en `docs/` (inglés) y su traducción en
`docs/es/` (español): `SETUP`, `WORKFLOW`, `PUBLISH_VIA_GITHUB`, `AUTHORING_GUIDE`,
`ACF`, `TEMPLATES`, `WILDLIFE_TEMPLATE`, `NEW_SITE`, `TROUBLESHOOTING`.
