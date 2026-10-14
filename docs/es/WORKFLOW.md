> Traducción al español. Versión original en inglés: [../WORKFLOW.md](../WORKFLOW.md).

# Flujo de trabajo de autoría y publicación

Esta es la guía cotidiana para convertir un documento en una página publicada.

## 1. Escribe el documento

Usa lo que te resulte cómodo — Word, Google Docs, Markdown o texto plano. Dos
reglas hacen que la automatización funcione bien:

### a) Agrega un encabezado de metadatos

Indícale al sistema el tipo de página y los campos clave. En **Markdown**, usa
YAML frontmatter. En **Word/Google Docs/texto plano**, coloca unas cuantas
líneas `Key: value` al inicio (antes del primer párrafo), y luego una línea en
blanco.

```
Type: tour
Title: 7-Day Peru Highlights
Destination: Peru
Duration: 7 days
Price: from $2,150
Focus keyword: Peru tour
Categories: Tours, Peru
Tags: Peru, Machu Picchu, Cusco
```

Claves reconocidas: `type`, `title`, `destination`, `duration`, `price`,
`focus_keyword`, `categories`, `tags`, `ship`, `country`, `region`,
`best_time`, `slug`, `meta_description`, `featured_image_query`, `status`.

Si omites `type`, el sistema lo deduce del contenido (y siempre puedes
sobrescribirlo con `--type`).

### b) Usa encabezados claros

Cada encabezado `##` se convierte en una sección. Redáctalos de forma natural —
el sistema mapea sinónimos al espacio correcto de la plantilla. Secciones útiles
por tipo de página:

- **Tour / Cruise**: Overview, Highlights, Itinerary, What's Included, Pricing, FAQ
- **Destination**: Overview, Highlights, Things to Do, Best Time to Visit,
  Getting There, FAQ
- **Blog post**: Overview/Intro, secciones del cuerpo, FAQ, Conclusion

Para las **FAQs**, usa `###` para cada pregunta con la respuesta como el párrafo
debajo de ella — eso produce automáticamente el schema de resultados
enriquecidos `FAQPage`.

## 2. Previsualiza antes de publicar

```bash
wp-publish preview my-doc.docx
```

Esto construye la página **sin** tocar WordPress y escribe tres archivos en
`output/<slug>/`:

- `content.html` — el marcado de bloques de Gutenberg
- `schema.json` — el JSON‑LD de schema.org
- `page.json` — todo (campos de SEO, decisiones de medios, advertencias)

La consola imprime un resumen: tipo de página detectado, título, slug, título
SEO/meta, categorías/etiquetas y cualquier **advertencia** (secciones requeridas
faltantes, meta corta, brechas en la palabra clave objetivo). Corrige las
advertencias en el documento fuente y vuelve a previsualizar.

## 3. Elige una estrategia de medios

| Estrategia         | Bandera              | Comportamiento                                      |
| ------------------ | -------------------- | --------------------------------------------------- |
| Placeholder (por defecto) | `--media placeholder` | Inserta bloques claramente marcados para que un humano los complete |
| Media Library      | `--media library`    | Encuentra la mejor imagen coincidente ya existente en WordPress  |

El valor por defecto proviene de `config/site.yaml`
(`media.default_strategy`). Con la estrategia library, la imagen destacada y las
imágenes en línea se emparejan por destino/título; cualquier cosa por debajo del
umbral de coincidencia recae en un placeholder para que nunca te quedes con una
imagen incorrecta.

## 4. Publica

```bash
# Safe default: creates/updates a DRAFT
wp-publish publish my-doc.docx --type tour --media library

# Go live (prompts for confirmation)
wp-publish publish my-doc.docx --status publish
```

La publicación es **idempotente sobre el slug**: ejecutarla de nuevo actualiza
la entrada existente en lugar de crear un duplicado. Usa `--no-update` para
forzar una nueva entrada.

Después de publicar obtienes el ID de la entrada, la URL pública y un enlace
directo de **edición** para revisarla en el editor de bloques.

## 5. Desde Google Drive

```bash
wp-publish gdrive "7-Day Peru Highlights" --preview      # build only
wp-publish gdrive 1AbCdEfGhIjKlMnOpQrStUvWxYz --type tour  # by file ID
```

Solo se permite la cuenta en `GDRIVE_ALLOWED_ACCOUNT`; la primera ejecución la
autoriza en tu navegador.

## Ciclo típico

```
write doc  →  wp-publish preview  →  fix warnings  →  wp-publish publish (draft)
           →  review in WP editor  →  wp-publish publish --status publish
```
