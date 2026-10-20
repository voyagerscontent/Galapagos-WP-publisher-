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

### Related Links ("Explore More")

Este widget **a propósito no** se puede estilizar por página: la tipografía, los
colores, la línea bajo cada título de grupo, los chevrones y el número de
columnas están fijos en el CSS del widget para que todas las instancias se vean
igual en todo el sitio. Sólo queda ajustable el contenedor exterior (fondo,
padding, ancho máximo). Las columnas siguen al contenido — una por grupo de
enlaces, más la tarjeta de contacto cuando se muestra.

Sus enlaces salen de uno de dos sitios, que se elige en **"Where the links come
from"**:

- **Automatic** (por defecto) — lee el campo ACF `related_links` de la página y
  cae a la lista **Manual links** del propio widget cuando la página no tiene
  ninguno. Es lo que necesitan las páginas padre hechas a mano (que no llevan
  grupo ACF).
- **ACF field only** / **Manual list only** — fuerzan un origen concreto.

Una fila sin grupo se pinta como columna sin título; nunca se inventa un
encabezado, así que los nombres de grupo siempre se eligen a mano.

**Cuidado con `{{WRAPPER}}`.** Elementor resuelve ese token sólo dentro de los
`selectors` de un control. En el marcado que un widget *imprime* es texto y ya,
así que un bloque `<style>` emitido con `{{WRAPPER}} .foo{…}` es CSS inválido y
el navegador lo descarta — la página parece estilizada sólo porque los valores
por defecto de los controles generan CSS real por detrás, y pierde el estilo en
cuanto se quitan esos controles. Por eso este widget imprime su hoja de estilos
una vez por petición, apoyada en sus propias clases `.irl-*` y sin token. La
mayoría de los demás widgets de este archivo siguen emitiendo bloques con
`{{WRAPPER}}` que nunca aplican; su aspecto sale de los valores por defecto de
los controles.

Cuando solo hay UNA columna (por ejemplo solo el bloque de contacto “Need help?”,
porque la página no tiene enlaces relacionados), ese bloque se centra en la página y
su texto y botón también quedan centrados.

### Expert Pages (las páginas que escribió un experto)

Pon **Island Expert Pages** en la ficha de un experto y lista, como tarjetas, todas
las páginas publicadas que nombran a esa persona en su byline ACF `author`. No hay
nada que mantener a mano: una página aparece en cuanto se publica con el byline.

- **A quién busca.** El nombre sale del propio título de la ficha (se puede
  sobrescribir en el widget si el título lleva algo más que el nombre). Sólo se
  compara la parte del byline anterior a la primera coma, sin distinguir
  mayúsculas, acentos ni un "By" inicial; así `Juan Magallanes, Naturalist Expert
  Contributor` cuenta para *Juan Magallanes*, pero *María Magallanes* o *Juan
  Magallanes Jr* no.
- **Qué muestra cada tarjeta.** La imagen destacada de la página (si no, su
  `hero_image`), el título y hasta **tres líneas** de descripción. La descripción es
  la primera disponible de: el extracto, la meta descripción de Yoast / Rank Math,
  `geo_answer`, `hero_subtitle`, `intro`.
- **Páginas solo de Elementor.** Una página sin campos ACF también se encuentra si tiene un
  **Author Box** de Elementor (Source: Custom) con el **Name** escrito a mano (p. ej. “By Juan
  Magallanes, Galapagos Travel Advisor”): se compara la parte antes de la coma, igual que con el
  byline ACF. Un Author Box cuyo Name es una etiqueta dinámica se ignora. Los resultados se
  guardan unas horas y se refrescan cuando se guarda cualquier página.
- **Límites.** Lee el campo `author`, que sólo llevan los grupos Informative, Island
  y Wildlife. Un tipo de página sin ese campo (cruceros, itinerarios) no aparecerá
  hasta que se agregue el campo a su grupo. La propia ficha nunca se lista a sí
  misma.
- **Diseño.** Fijo, como Related Links: sólo el contenedor (fondo, padding, ancho
  máximo) es ajustable. Los controles cubren el encabezado (`{name}` se sustituye),
  en qué tipos de contenido buscar, el orden y un máximo.

Las tarjetas salen en un **carrusel**: tres a la vez en escritorio, dos en tablet,
una en móvil. Si hay más páginas de las que caben, aparecen flechas fuera de las
tarjetas, centradas en el widget (y se desliza con el dedo en el celular). Las tarjetas pueden mostrar una fecha pequeña abajo (interruptor **Show date on cards**, **apagado** por defecto) — “Updated 12 oct 2026”, o la fecha
de publicación (**Date**); usa el formato de fecha del sitio.
El **título** de la tarjeta es el `hero_title` ACF de la página (si está vacío,
el título de la página). El **texto** es la Respuesta Rápida (`geo_answer`) sin
etiquetas, cortada a 3 líneas con “…”; solo si está vacía usa: Extracto →
`hero_subtitle` → `intro` → descripción de Yoast → descripción de Rank Math.

### Texto largo: barra “Read more” (todos los tamaños de pantalla)

Las bandas/filas de secciones, los ítems de "dónde ver" y las fichas de vida
silvestre recortan el texto largo y muestran una barra **Read more ↓** (línea
fina, texto a la izquierda y flecha a la derecha). Al hacer clic se abre el texto
completo (pasa a **Read less ↑**); al cerrarlo, la página vuelve al inicio de esa
sección si ya te habías desplazado más abajo. El texto corto no lleva barra — ni el que apenas supera el recorte (la barra aparece
solo si se ocultarían MÁS de 6 líneas). Ya no
hay hover para expandir: hacía saltar la página cuando una sección se cerraba bajo
el mouse. El interruptor se llama “Clamp long text (Read more button)” y el ajuste
de “altura al abrir” ya no se usa (el texto siempre se abre completo). Los enlaces dentro de ese texto toman el color del texto
de la sección (crema en una banda café) con subrayado, así nunca desaparecen sobre
fondo oscuro.

### Tablas dentro de las secciones (se ajustan, sin scroll lateral)

Las tablas en filas/bandas de secciones ahora **ajustan el texto y siempre caben
en la sección**: sin barra de scroll horizontal. El grupo Table tiene el
interruptor **“Wrap table text (no horizontal scroll)”** (activado por defecto);
apágalo para volver al comportamiento anterior (ancho mínimo, scroll lateral). Una
**tabla de una sola columna** (cada celda = línea de título y luego un párrafo) se
ve con el título en negrita y el texto normal; la celda del encabezado usa el
mismo patrón con el color del encabezado (línea de título en negrita, del mismo tamaño que el texto). Su texto es de 15px con interlineado 1.7
(igual que el texto del cuerpo de la sección) y, en las filas de secciones, sigue la
tipografía “Body”. Si una tabla de una columna no tiene fila de encabezado
(`<thead>`), su primera fila se muestra automáticamente como encabezado café (los
colores siguen los controles “Header background / text”).

### Botón de volver arriba

Las páginas largas muestran un botón redondo gris de **volver arriba** (flecha
blanca, abajo a la derecha). Solo aparece cuando la página mide más de unas 3
pantallas y el visitante ya bajó un poco; un clic sube suavemente al inicio
(instantáneo si el visitante prefiere menos movimiento). No se muestra en el
editor/vista previa de Elementor ni al imprimir. Para desarrolladores: se apaga con
`add_filter('island_back_to_top', '__return_false');` o se cambia el largo mínimo
(en pantallas, por defecto 3) con el filtro `island_back_to_top_screens`.

### Estilo de tabla para cualquier widget: clase `island-table`

Las tablas fuera del widget de Feature Sections (por ejemplo dentro de un Text Editor) no tienen
diseño propio. Agrega la clase CSS **`island-table`** a ese widget o contenedor (Advanced → CSS
Classes), o escribe `<table class="island-table">`, y todas las tablas dentro toman el diseño del
sitio: encabezado café (un `<thead>` o una primera fila de `<th>`), filas alternas, primera
columna en negrita café, esquinas redondeadas y texto que se ajusta (sin scroll lateral). Una tabla de una columna (cada
celda: línea de título y luego un párrafo) muestra el título en negrita y, si no tiene `<thead>`, su
primera fila como encabezado café — igual que las tablas de “Ages”. Las
tablas dentro de Feature Sections ya tienen este aspecto y no necesitan la clase.

#### Sobre una sección café: clase `table-in-brown`

**`table-in-brown`** es el mismo diseño que `island-table` (cuerpo crema, filas alternas, esquinas
redondeadas) para usarlo sobre una **sección café**, con dos diferencias: un **encabezado arena** con texto
café (uno café se perdería en el fondo café) y un borde fino crema. Las tres clases de tabla también fuerzan
fuente normal, tamaño 15px e interlineado cómodo en encabezado y celdas, para que los estilos de título del
tema no las aplasten. Se pone igual:
Advanced → CSS Classes, o `<table class="table-in-brown">`.

### Degradado inferior de la lista con scroll

Los widgets que hacen scroll de su lista de features dentro de una altura fija (Feature Sections
con “Match neighbour column”, y Wildlife · At a Glance + Features) muestran un degradado suave en el
borde inferior como pista de “hay más”. Apaga **Show bottom fade** (junto al Fade color) para no
tener degradado. El degradado del texto “Read more” dentro de cada tarjeta es aparte y se queda.

#### Sobre una sección crema: clase `table-in-cream`

Para una **página o sección crema** usa **`table-in-cream`**: una **tabla blanca** (más contraste contra el
crema) con el encabezado café, rayas crema suaves y una sombra ligera. Se usa igual que las otras dos:
Advanced → CSS Classes, o `<table class="table-in-cream">`. Dentro del marco de tabla de Feature Sections (la tarjeta blanca / caja redondeada que el widget ya
dibuja) las clases **no agregan un segundo borde, margen ni sombra**. Resumen — `island-table`: la normal;
`table-in-brown`: el mismo diseño para secciones cafés; `table-in-cream`: tabla blanca para secciones crema.

### Island Author Box (la caja de autor del hero como plantilla)

Un solo widget para la caja bajo el título de la página — foto redonda, “By Juan Magallanes, Galapagos
Travel Advisor” y la nota de revisión — para ponerlo una vez en una plantilla y que funcione en todas las
páginas. Se llena solo con los datos de cada página:

- **Nombre y cargo** — el campo ACF **Author / Byline** (`author`) de la página. Lo anterior a la coma es el nombre.
- **Foto y enlace** — la **ficha del experto** que coincide (la página de tipo “Experts” cuyo título es ese
  nombre; si ninguna está marcada “Experts”, se usa cualquier página publicada con exactamente ese título): su **about image**, o si no su imagen destacada; el enlace va a esa ficha. Si el ACF **Author URL**
  (`author_url`) está lleno, ese manda como enlace.
- **Nota de revisión** — el campo ACF **Review Note** (`review_note`, un campo de texto nuevo que agregas a
  mano junto a Author / Byline). Si la página no tiene, se usa la **Default review note** del widget.
- Sin byline en la página → el widget no imprime nada (en el editor sale un aviso punteado). Las páginas
  solo de Elementor siguen con el Author Box de Elementor; este widget es para páginas con los campos ACF.

Controles de estilo: colores y tipografía del nombre y la nota, tamaño y aro de la foto, separación,
posición de la caja (izquierda / centro / derecha), ancho máximo (la caja se ajusta a su contenido, 760px por
defecto), fondo y relleno. Para que quede abajo del hero, pon el widget al final del contenedor del hero y
usa Justify Content = “Space Between” en ese contenedor. El widget también tiene reemplazos manuales opcionales (byline, nota, enlace, foto).

#### Seguir con el Author Box de Elementor: las etiquetas dinámicas “Island Author”

Si prefieres el **Author Box** de Elementor (Source: Custom), no cambia nada en las páginas que ya lo usan con
valores escritos a mano. Para una **plantilla**, llena sus campos con estas etiquetas dinámicas (clic en el
icono de discos del campo → grupo **Island Author**):

| Campo del Author Box | Etiqueta | Valor |
|---|---|---|
| Name | **Author name (By Name, Role)** | “By ” + el ACF `author` de la página (prefijo editable) |
| Profile Picture | **Author photo (expert profile)** | la about image / imagen destacada de la ficha del experto |
| Link | **Author link (Author URL / expert profile)** | ACF `author_url`, o si no la ficha del experto |
| Biography | **Review note** | ACF `review_note`, o si no el texto por defecto que escribes en la etiqueta |

Requiere Elementor Pro (etiquetas dinámicas). Una página con el campo `author` vacío muestra la caja vacía,
así que usa las etiquetas solo en una plantilla de páginas que tengan ese campo. **Island Author Box** (arriba)
sigue disponible como alternativa.

### Fuentes dentro del pie “Explore More”

En **Island Related Links** (Content → Links) enciende **Also show the sources list (ACF “sources”)**: la misma
lista colapsable “Sources & Citations (n)” aparece dentro del pie, con sus colores cafés, **después** o **antes** de
las columnas de enlaces (**Sources position**), con su propio título y **Start open**. Un solo widget = todo el pie.
Las etiquetas de las fuentes se limpian igual que en Island Sources & Links.

### Fuentes (Island Sources & Links)

Imprime la lista ACF **`sources`** (etiqueta + URL) como una lista ordenada: cada fuente es **su etiqueta, enlazada a
su URL** (una fila sin URL queda como texto). Ya no imprime el bloque “Explore More”: para eso usa **Island Related
Links**. Si una etiqueta llega de un documento con basura pegada (“…Image: foto.jpg (400×300px…)Alt: …”, un enlace
Markdown, un “[” suelto), el widget muestra solo la etiqueta legible y toma la URL de la fila o del enlace Markdown.
La lista es **colapsable** por defecto (interruptor **Collapsible list**): una barra fina con el título, el número
de fuentes y una flecha; clic para abrir. **Start open** la deja abierta. Controles de estilo: colores del título,
de los enlaces y del texto; columnas (1–3, siempre 1 en celular).

### Enlace del autor (el nombre enlaza a su ficha)

Pon **Island Byline** (categoría “Galápagos Site Widgets”) donde va la línea “By
Juan Magallanes, Naturalist Expert Contributor”. Lee el campo ACF `author` de la
página (o el cuadro **Text** si lo llenas) y convierte la parte **“By Nombre”** (todo lo anterior a la coma) en un
enlace a la ficha de ese experto: la página publicada cuyo *Galápagos Page Type*
es **Experts** y cuyo título es el mismo nombre (sin importar mayúsculas ni
acentos). El cargo / nota de revisión después de la coma queda como texto. Pon el enlace en el campo
ACF **Author URL** (`author_url`, junto a Author / Byline); si está lleno manda ese; si no,
el nombre enlaza a la ficha del experto como se describe arriba. El widget también tiene una
caja “Link” para ponerlo a mano.

¿Ya tienes el byline en otro widget (un Heading, Text Editor…)? No hace falta reemplazarlo:
y no hay nada que configurar: cualquier widget de la página que imprima el byline (por
ejemplo un Heading con el campo ACF `author` después de “By ”) convierte automáticamente
“By Juan Magallanes” en el enlace (Author URL, o la ficha del experto que coincida); el cargo
después de la coma queda como texto. El enlace conserva la fuente, el tamaño, el color
y la cursiva del texto: visualmente solo cambia con un subrayado al pasar el mouse. Si un widget muestra solo el nombre, ponle la clase CSS
`island-byline` (Advanced → CSS Classes). Si no
hay ficha con ese nombre, o ya estás en esa ficha → texto simple, sin enlace.
Fuera de Elementor está el shortcode `[island_byline]`.

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
- Se reconoce por el banner **`CMS Stage N`** + un marcador AIO, en dos variantes:
  **`[AIO BLOCK …]`** (con corchetes) o **`AIO SUMMARY BLOCK (…): <respuesta>`** (en línea).
- Soporta el header grande **"PUBLISHER HEADER BLOCK"** (JSON-LD, enlaces internos,
  reglas de entidad) — se salta todo eso hasta el título real (H1).
- Los **títulos de sección van en negrita** (sin estilos de encabezado); se detectan
  por la negrita del `.docx`, así también entran los títulos largos.
- `[AIO BLOCK 1 — speakable]` o `AIO SUMMARY BLOCK (…):` → `geo_answer`.
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
| Un CMS Stage 8 no se segmentó (todo cayó en el hero/intro) | Variante con `AIO SUMMARY BLOCK` (sin corchetes) + títulos en negrita → no entraba al adapter Stage 8 | Detección tolerante (acepta `AIO SUMMARY BLOCK`), header "PUBLISHER HEADER BLOCK" saltado, y títulos por negrita |

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
