> Traducción al español. Versión original en inglés: [../ACF.md](../ACF.md).

# Publicación en campos ACF (flat) para Elementor

El publisher **no** escribe bloques de Gutenberg. Toma tu documento de contenido,
lo compone en componentes estructurados y escribe **campos ACF planos y nombrados** sobre
la API REST. Luego diseñas la página en **Elementor**, enlazando cada campo con
**Dynamic Tags → ACF Field**. Los datos y el diseño quedan claramente separados.

```
content doc → compose → flat ACF fields → POST { "acf": {...} } → WordPress → Elementor (Dynamic Tags)
```

## 1. Crear el field group en WordPress (una sola vez)

> **Importante:** el publisher *escribe valores* en los campos ACF; **no**
> crea las *definiciones* de los field-group. Los field groups de ACF no se pueden crear sobre
> la API REST de contenido, por lo que el grupo debe existir antes en WordPress, o el
> payload `acf` se descarta silenciosamente (obtendrías una entrada solo con título).

Dos formas de crearlo:

- **Importar el grupo listo** — WP admin → **ACF → Tools → Import Field Groups**
  → sube [`wordpress-acf/page-fields.acf.json`](../wordpress-acf/page-fields.acf.json).
  Coincide exactamente con `config/acf.yaml` y tiene **Show in REST API** activado.
- **O regístralo en tu repo de tema/sitio** mediante ACF local JSON (`acf-json/`),
  manteniendo los mismos nombres de campo.

Luego confirma que el grupo tenga **Show in REST API = On** (Field Group → Settings).
Elementor lee ACF directamente de la base de datos, así que Show-in-REST solo se necesita para que
*nuestra automatización* pueda escribir los valores.

## 2. Los campos

| ACF field | Type | Se llena desde |
| --------- | ---- | ----------- |
| `hero_eyebrow` | text | `eyebrow` opcional |
| `hero_heading` | text | el título |
| `hero_subheading` | textarea | tagline / "Quick Answer" |
| `hero_image` | image (ID) | primera imagen / `featured_image_query` |
| `hero_cta_label`, `hero_cta_url` | text | primer CTA del hero |
| `body` | wysiwyg | todas las secciones de prosa como HTML limpio (`<h2>`, `<p>`, `<ul>`, `<figure>` en línea) |
| `callout_title`, `callout_text` | text / wysiwyg | un `CALLOUT STAT` / `:::callout` |
| `faq` | repeater (`question`, `answer`) | el bloque de FAQ |
| `key_facts` | repeater (`label`, `value`) | un mapa `facts:` |
| `cta_heading`, `cta_text`, `cta_label`, `cta_url` | text / wysiwyg | un CTA de cierre |
| `page_subtitle` | text | `tagline`/`subtitle` |
| `seo_schema` | textarea | schema.org JSON-LD |

Para usar **tus propios** nombres de campo, edita el lado derecho de `config/acf.yaml`
(`flat:` y `top_level:`). El modelo de componentes es fijo; solo cambian los nombres.

## 3. Construir la página en Elementor con Dynamic Tags

Diseña la plantilla una vez (Elementor Pro):

1. Agrega un widget (Heading, Text Editor, Image…).
2. Haz clic en el icono **Dynamic Tags** (🛢️) del campo de contenido.
3. Elige **ACF Field** → selecciona el campo (p. ej. `hero_heading`).
4. Para `faq` / `key_facts` (repeaters): usa un **Loop Grid** / repeater, o un
   widget de acordeón que lea el repeater de ACF.

Cada nuevo documento que publicamos llena estos campos, y tu plantilla de Elementor los renderiza
automáticamente, sin rediseñar el layout por página.

## 4. Preview y publicación

```bash
wp-publish preview content/docdepruebainicial.docx     # writes output/<slug>/acf.json
wp-publish publish  content/docdepruebainicial.docx     # POSTs the flat acf payload (draft)
```

`acf.json` es exactamente lo que se envía como el objeto `acf` de la entrada; inspecciónalo para
confirmar el mapeo antes de publicar. `post_content` queda vacío.

### Seguridad
El publisher **se niega** a tocar una entrada existente con el mismo slug a menos que
pases `--update` (workflow: el toggle `update_existing`, desactivado por defecto), e incluso
así nunca cambia el status de una entrada existente ni vacía su contenido. Prueba documentos nuevos
bajo un slug nuevo.

## 5. Imágenes

Los campos de imagen de ACF almacenan **attachment IDs**. En el modo `--media library` el engine
busca coincidencias en tu Media Library y llena el ID; de lo contrario el campo queda vacío
y una advertencia lista los términos de búsqueda sugeridos para que una persona agregue el recurso.

## 6. SEO y schema

El title/description/focus keyword de SEO se escriben en la meta de RankMath/Yoast. El
schema.org JSON-LD se entrega como el campo `seo_schema`; publícalo dentro de una
etiqueta `<script type="application/ld+json">` en el header de tu Elementor/tema.
