> Traducción al español. Versión original en inglés: [../AUTHORING_GUIDE.md](../AUTHORING_GUIDE.md).

# Guía de autoría — qué poner en tu documento de contenido

Esta es la referencia para las personas que **escriben el contenido**. Cubre las
instrucciones que tu documento debe (y puede) incluir para que se publique
correctamente y —para el **constructor libre (freeform)**— las directivas de
maquetación que te permiten diseñar la página como quieras, independientemente de
las plantillas fijas.

Funciona en **Markdown (`.md`), texto plano (`.txt`) o Word (`.docx`)**, y desde
Google Drive.

> **Salida:** tu contenido se publica poblando **campos ACF** (no
> Gutenberg). Las directivas de abajo se mapean a maquetaciones de flexible-content de ACF; el tema
> las renderiza. Cómo funciona ese mapeo está en [ACF.md](ACF.md).

---

## 1. El encabezado de metadatos (inclúyelo siempre)

Todo documento comienza con un pequeño encabezado de ajustes `key: value`. En Markdown
usa un bloque de frontmatter YAML delimitado por `---`. En Word/texto plano, pon las mismas
líneas `Key: value` justo al inicio y luego una línea en blanco.

```yaml
---
type: freeform            # which builder/template to use (see §2)
post_type: page           # page | post   (default: post)
title: "Why Travel With Us"
slug: "why-travel-with-us"   # optional; auto-generated from the title if omitted
meta_description: "One or two sentences for Google (70–156 chars)."
focus_keyword: "Galapagos expeditions"
featured_image_query: "Galapagos expedition boat"   # used to find/set the hero image
categories: "About, Company"     # comma-separated (posts only)
tags: "Galapagos, expeditions"   # comma-separated (posts only)
status: draft             # draft | pending | publish  (default: draft)
---
```

### Mínimo para publicar correctamente
| Campo | Por qué importa |
| ----- | -------------- |
| `type` | Elige el constructor/plantilla. Usa `freeform` para una maquetación personalizada. |
| `title` | Se convierte en el `<h1>` de la página y en el título de WordPress. |
| `post_type` | `page` para páginas perennes, `post` para blog/noticias. Predeterminado `post`. |
| `meta_description` | Tu fragmento de Google. **Muy recomendado** — de lo contrario se autogenera a partir del primer párrafo. |
| `focus_keyword` | Impulsa las verificaciones de SEO; debería aparecer en el título. |

Todo lo demás es opcional. Los slugs se autogeneran, el estado predeterminado es **draft**,
y las imágenes recurren a un marcador de posición claramente marcado si no se encuentra ninguna.

### Valores de `type`
| `type` | Úsalo para |
| ------ | ------- |
| `freeform` | **Cualquier página/entrada personalizada** — tú controlas la maquetación con directivas (§2). |
| `blog_post` | Artículo / guía estándar |
| `destination` | Resumen de destino |
| `tour` / `cruise` | Itinerarios / cruceros |
| `wildlife_tier1` / `wildlife_tier2` | Páginas de especies (por volumen de búsqueda) |

> Las plantillas fijas (`tour`, `cruise`, `wildlife_*`, …) esperan
> **encabezados de sección** específicos — consulta sus propias guías. El resto de *este* documento
> trata sobre el **constructor libre (freeform)**, donde tú mismo diseñas la maquetación.

---

## 2. Directivas de maquetación freeform (`type: freeform`)

Escribe Markdown normal para el texto. Envuelve cualquier pieza **estructural / diseñada** en una
valla de directiva `:::`:

```
::: name  optional-arguments
   ...content (more Markdown, or nested directives)...
:::
```

Abre una directiva con `::: name`; ciérrala con un `:::` solo. Las directivas pueden
anidarse (p. ej. `column` dentro de `columns`).

### Directivas disponibles

| Directiva | Argumentos | Qué hace |
| --------- | --------- | ------------ |
| `hero` | `image="search terms"` | Banner de ancho completo (degradado, o tu imagen como fondo). Pon dentro un encabezado, una línea de texto y botones. |
| `columns` | una proporción como `60/40` o `1/1/1` | Maquetación lado a lado. Cada hijo debe ser un `column`. |
| `column` | — | Una columna (usada dentro de `columns`). |
| `cards` | `cols=3` | Una cuadrícula responsiva de `card`s. |
| `card` | — | Una tarjeta con borde (usada dentro de `cards`, o sola). |
| `callout` | `note` \| `tip` \| `warning` \| `danger`, `title="…"` | Caja de información destacada. |
| `accordion` | — | FAQ colapsable. Cada `### Question` se convierte en un elemento expandible. |
| `cta` | — | Banner centrado de llamada a la acción (encabezado, texto, botones). |
| `group` (o `section`) | `bg="#f6f9f7"` `color="#111"` `align=center` | Un envoltorio estilizado alrededor de cualquier contenido. |
| `image` | `query="search terms"` o `src="https://…"`, `alt="…"` | Una imagen (leyenda = el texto de adentro). |
| `buttons` | — | Una fila de botones. |
| `spacer` | un número (px) | Espacio vertical. |
| `divider` (o `hr`) | — | Una línea horizontal. |
| `html` | — | Paso directo de HTML crudo (avanzado). |

### Botones
Escribe botones en línea, en cualquier lugar, así:

```
[button:Label|/the-url]   [button:Another|/second-url]
```

Una línea que contenga solo botones se convierte en una fila de botones.

### Imágenes
- `::: image query="Galapagos sea lion"` → el sistema encuentra una imagen coincidente
  de tu **Media Library** (en modo `--media library`) o deja un marcador de posición claramente
  etiquetado para que lo completes.
- `::: image src="https://…/photo.jpg"` → usa esa imagen exacta.
- El texto dentro de la directiva `image` es la leyenda.
- La imagen hero/destacada proviene de `featured_image_query` (encabezado) o del
  `image="…"` de la directiva `hero`.

### Un ejemplo trabajado

```markdown
---
type: freeform
post_type: page
title: "Why Travel With Us"
meta_description: "Small-group Galapagos expeditions led by resident naturalists."
featured_image_query: "Galapagos expedition boat"
---

::: hero image="Galapagos sunset"
# Travel the Galapagos, Differently
Small-group expeditions led by resident naturalists.
[button:Plan Your Trip|/contact] [button:Browse Cruises|/cruises]
:::

## What Makes Us Different
A short intro paragraph here.

::: cards cols=3
::: card
### Expert Naturalists
Certified Galapagos National Park guides on every departure.
:::
::: card
### Small Groups
A maximum of 16 guests per sailing.
:::
::: card
### Carbon-Neutral
Every itinerary is certified carbon-neutral.
:::
:::

::: callout tip title="Did You Know?"
Our guests have logged over **40,000 wildlife sightings**.
:::

::: columns 60/40
::: column
## A Voyage Built Around Wildlife
We time landings to the rhythms of the islands.
:::
::: column
::: image query="Galapagos sea lion snorkeling"
Snorkeling with curious sea lions.
:::
:::
:::

::: accordion
### How big are the groups?
A maximum of 16 guests per sailing.
### Are the trips family friendly?
Yes — we run dedicated family departures.
:::

::: cta
## Ready to Set Sail?
[button:Talk to an Expert|/contact]
:::
```

Consulta `samples/freeform-example.md` para ver el archivo completo.

---

## 3. Formato de texto dentro del contenido

El Markdown estándar funciona dentro de cualquier directiva o sección simple:

- `## Heading` / `### Subheading`
- `**bold**`, `*italic*`, `` `code` ``
- listas `- bullet`, listas `1. numbered`
- `> quote`
- `[link text](https://…)`
- `![alt text](https://…/image.jpg)` para una imagen en línea

> Nota: el `<h1>` principal de la página es tu `title:` del encabezado. Dentro del cuerpo,
> comienza los encabezados en `##` — un `#` inicial se degrada automáticamente para que no
> obtengas dos `<h1>`.

---

## 4. Lista de verificación para publicar

1. El encabezado tiene `type`, `title` y (recomendado) `meta_description` + `post_type`.
2. El cuerpo usa directivas para la maquetación y Markdown para el texto.
3. Guarda como `.md` / `.txt` / `.docx`, o mantenlo en la cuenta aprobada de Google Drive.
4. Previsualiza primero (`mode: preview`), corrige cualquier advertencia, luego `publish-draft`,
   revisa en WordPress y `publish-live`.

Cualquier cosa que el sistema no pueda colocar (una imagen faltante, una directiva vacía) aparece
como una **advertencia** y un marcador de posición etiquetado — nunca una página rota.
