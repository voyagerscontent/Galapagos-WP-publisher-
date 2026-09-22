> Traducción al español. Versión original en inglés: [../TEMPLATES.md](../TEMPLATES.md).

# Templates

Un **template** describe cómo se construye un *tipo* de página. Vive en
`templates/<key>.yaml` y es pura configuración: agregar o cambiar un tipo de
página nunca requiere tocar Python.

## Anatomía

```yaml
key: tour                      # unique id; also the value you put in "type:"
name: Tour / Itinerary         # human label (shown by `wp-publish templates`)
description: >                 # what it's for
  A sellable trip with a day-by-day itinerary, inclusions, and pricing.
post_type: post                # post | page | <custom-post-type-rest-base>
status: draft                  # optional: overrides the global default status
schema_type: TouristTrip       # schema.org @type for the JSON-LD
categories: [Tours]            # default categories (doc can override)
tags: []                       # default tags (doc can override)
focus_keyword_from: destination # metadata key to use if no explicit keyword

layout:                        # ordered list of slots (see below)
  - { slot: featured, kind: media, role: featured }
  - { slot: overview, kind: section, required: true, show_heading: false }
  - { slot: highlights, kind: section, heading: "Trip Highlights", style: list }
  - { slot: itinerary, kind: section, required: true, heading: "Day-by-Day Itinerary" }
  - { slot: whats_included, kind: section, heading: "What's Included", style: list }
  - { slot: faq, kind: faq }

required_sections: [overview, itinerary]   # missing -> warning (not an error)

schema_extra:                  # merged verbatim into the JSON-LD entity
  touristType: [Adventure, Eco-tourism]
```

## Slots

Cada entrada en `layout` es un **slot**, renderizado en orden.

| Field          | Default     | Significado                                                    |
| -------------- | ----------- | -------------------------------------------------------------- |
| `slot`         | —           | Clave canónica de sección que se extrae del documento         |
| `kind`         | `section`   | `section` \| `media` \| `faq` \| `spacer`                     |
| `required`     | `false`     | Advierte si el documento no tiene una sección que coincida     |
| `heading`      | —           | Fuerza/sobrescribe el texto del heading para este slot         |
| `show_heading` | `true`      | Ponlo en `false` para suprimir el heading (usado para el lead/overview) |
| `style`        | `prose`     | `prose` mantiene los párrafos; `list` fusiona el contenido en viñetas |
| `role`         | `inline`    | Para `media`: `featured` \| `inline` \| `gallery`             |
| `hint`         | —           | Texto mostrado dentro de un placeholder de media              |

### Cómo se mapean las secciones a los slots

Los headings del autor no tienen que coincidir exactamente con los nombres de los slots. Los títulos se
normalizan a un **slug canónico** con coincidencia por sinónimos + keywords, de modo que:

- "What's Included", "Inclusions", "Price Includes" → `whats_included`
- "Cruise Highlights", "Trip Highlights", "Key Highlights" → `highlights`
- "Rates & Departures", "Pricing", "Cost" → `pricing`
- "Cabins & Accommodation" → `cabins`

Los mapeos viven en `src/wp_publisher/utils.py`
(`SECTION_SYNONYMS` y `_KEYWORD_RULES`). Cualquier sección del documento que no
coincida con un slot declarado **igual se renderiza**, agregándose después de los slots
del template, de modo que el contenido escrito nunca se descarta.

Los sub-headings (H3 y más profundos) permanecen anidados dentro de su sección padre. Así
es como las preguntas de FAQ bajo un heading `## FAQ` se convierten en pares de pregunta/respuesta (y alimentan el
schema `FAQPage`).

## Schema.org

`schema_type` elige la entidad de nivel superior. El generador agrega campos
específicos del tipo automáticamente:

- `TouristTrip` → `itinerary` (de la sección de itinerary), `arrivalLocation`
  (de `destination`), y `offers` (de `price`).
- `TouristDestination` → `touristType`, `containedInPlace` (de `country`).
- `Article` / `BlogPosting` → `author`, `mainEntityOfPage`.

Todo lo que esté bajo `schema_extra` se fusiona tal cual para control total.

## Agregar un nuevo tipo de página

1. Copia un archivo existente, p. ej. `cp templates/tour.yaml templates/expedition.yaml`.
2. Cambia `key`, `name`, `schema_type`, y el `layout`.
3. (Opcional) agrega señales de detección para él en
   `src/wp_publisher/parse/pagetype.py` para que se auto-detecte, o
   simplemente publica con `--type expedition`.
4. `wp-publish templates` para confirmar que carga.
