> Traducción al español. Versión original en inglés: [../WILDLIFE_TEMPLATE.md](../WILDLIFE_TEMPLATE.md).

# Templates de wildlife

> **Nota:** La salida ahora son **campos ACF** (ver [ACF.md](ACF.md)), no Gutenberg.
> Los dos *perfiles* de wildlife de abajo siguen impulsando la detección de tipo de página, el schema, y
> las advertencias por nivel de volumen de búsqueda; su contenido se compone en componentes ACF
> (hero, rich_text, acordeón de FAQ, stats) como cualquier otro documento. Los layouts enriquecidos de
> card/step/anchor descritos más adelante son históricos y ahora viven en
> el tema de WordPress, no en la salida publicada.

Las páginas de especies vienen en dos niveles, elegidos según el volumen mensual de búsqueda:

| Tier | `type` | Usar para | Layout |
| ---- | ------ | ------- | ------ |
| **Tier 1** | `wildlife_tier1` | Especies icónicas, **500+ searches/mo** | Página completa e intensiva en contenido |
| **Tier 2** | `wildlife_tier2` | Especies de menor búsqueda, **≤499/mo** | Página más ligera y compacta |

También puedes declarar un `type: wildlife` genérico y dejar que el engine elija el nivel
a partir de `search_volume` (≥500 → Tier 1, de lo contrario → Tier 2). Cada template advierte si
el volumen de búsqueda está del lado equivocado de la línea de 500.

Ambos niveles comparten el mismo modelo de autoría (encabezado de metadatos + secciones `##`,
con sub-headings `###` que se convierten en cards/steps) y ambos emiten schema `Article` + `FAQPage`
que describe la especie.

---

# Template Tier 1 de wildlife

Una página intensiva en contenido para las **especies más icónicas**, las que ameritan una página
completamente diseñada. Úsala cuando una especie tenga **500+ búsquedas mensuales** (el engine
advierte si `search_volume` falta o está por debajo del umbral).

`type: wildlife_tier1`

## Qué construye

Un layout fijo y diseñado renderizado como bloques nativos de Gutenberg:

1. **Hero** — nombre, nombre científico, tagline, chips de estado, breadcrumbs, dos CTAs
2. **Quick stats & TOC** — tabla de contenidos fija + una tabla de datos rápidos
3. **Overview** — texto + imagen, con un callout opcional "Did You Know?"
4. **Identification Guide** — placeholder de imagen anotada + notas de identificación
5. **Where They Live** — placeholder de mapa de distribución + texto de rango
6. **Behavior & Adaptations** — una grilla de cards de rasgos
7. **Life Cycle** — un proceso lineal de 3 pasos
8. **Threats & Conservation** — gráfico de stat de población + texto
9. **Best Places to See Them** — cards con probabilidades de avistamiento + un CTA cada una
10. **Traveler FAQs** — acordeón nativo (también emite schema `FAQPage`)
11. **Related Wildlife** — una tira de especies relacionadas
12. **Final Booking CTA** — banner de cierre con señal de confianza

schema.org: `Article` que describe la especie mediante `about` (nombre científico +
estado de conservación) más `FAQPage`.

## Cómo escribir el documento

Proporciona un **encabezado de metadatos** más secciones `##` normales. Dentro de las
secciones de card/step, cada sub-heading `###` se convierte en una card o step.

### Encabezado de metadatos (frontmatter)

```yaml
type: wildlife_tier1
title: "Galapagos Giant Tortoise"
scientific_name: "Chelonoidis niger"
tagline: "The ancient, slow-moving icons..."
search_volume: 5400                 # used for the 500+ tier check
conservation_status: "Vulnerable"
endemic: true
chips: ["Vulnerable", "Endemic", "Santa Cruz & San Cristobal", "June – Dec"]
breadcrumbs: ["Home", "Wildlife", "Galapagos", "Galapagos Giant Tortoise"]
hero_image_query: "Galapagos giant tortoise highlands"
overview_image_query: "Galapagos giant tortoise close up"
facts:                              # the quick-facts table
  "Size & Weight": "Up to 900 lbs / 5 feet"
  "Lifespan": "100–150+ years"
  "Diet Type": "Herbivore"
  "Habitat Type": "Highlands & Arid Lowlands"
population_current: "~20,000"       # conservation stat graphic
population_historical: "250,000"
cta_primary_label: "See This Species"
cta_primary_url: "#where-to-see"
cta_secondary_label: "Plan a Trip"
cta_secondary_url: "/contact"
booking_heading: "Ready to Walk Alongside Giants?"
booking_blurb: "Our expert-led expeditions bring you face-to-face..."
rating: "4.9/5 from 2,000+ wildlife travelers"
related:
  - { name: "Land Iguana", note: "Shares lowland cactus habitat." }
  - { name: "Marine Iguana", note: "Co-exists on rocky coasts." }
```

### Secciones (H2) y sus reglas especiales

| Encabezado de sección      | Se mapea a     | Autoría especial |
| -------------------------- | -------------- | ----------------- |
| Overview                   | `overview`     | Una línea que comience con `Did You Know:` o una cita `>` se convierte en el callout |
| Identification Guide       | `identification` | Las viñetas = notas de identificación |
| Where They Live            | `range_habitat`  | Una línea que comience con `Accessibility:` se convierte en el resumen del mapa |
| Behavior & Adaptations     | `behavior`     | Cada `###` = una card; las viñetas son el cuerpo de la card |
| Life Cycle                 | `life_cycle`   | Cada `###` = una fase/step |
| Threats & Conservation     | `conservation` | El gráfico de stat proviene de los metadatos `population_*` |
| Best Places to See Them    | `where_to_see` | Cada `###` = una card de lugar; una viñeta `CTA: Label \| /url` se convierte en un botón |
| Traveler FAQs              | `faq`          | Cada `###` = una pregunta, el texto debajo = la respuesta |

Los títulos de sección son flexibles — "How to Identify…", "Range & Habitat", "Survival
Traits", "Where to See", etc., todos se mapean correctamente.

## Build y publicación

```bash
wp-publish preview samples/galapagos-giant-tortoise.md      # local, no network
wp-publish publish samples/galapagos-giant-tortoise.md --type wildlife_tier1
```

Secciones requeridas: `overview`, `identification`, `range_habitat`, `behavior`,
`conservation`, `where_to_see`. Las faltantes producen advertencias, no fallos.

---

# Template Tier 2 de wildlife

Una página más ligera y compacta para especies que no ameritan el tratamiento completo de Tier 1.
Úsala para **499 o menos búsquedas mensuales** (advierte si el volumen
excede eso).

`type: wildlife_tier2`

## Qué construye

1. **Minimal hero** — estilo limpio y claro, chips, un solo CTA
2. **Quick facts + identification** — una división 40/60 (tabla compacta de datos + "How to Spot Them")
3. **Overview & Habitat** — una división 60/40 (texto corto + imagen)
4. **Lifestyle & Traits** — adaptaciones en viñetas apiladas
5. **Where to See & Book** — cards de tour (duración / estilo de landing + un CTA cada una)
6. **Quick Traveler FAQ** — un mini acordeón (schema `FAQPage`)
7. **Regional footer CTA** — descarga de guía + botones de planificador de viaje

## Secciones (H2)

| Encabezado de sección  | Se mapea a       | Notas |
| ---------------------- | ---------------- | ----- |
| How to Spot Them       | `identification` | Se muestra en la columna derecha de la división de datos |
| (Overview & Habitat)   | `overview`       | Una primera sección con título creativo se detecta automáticamente |
| Lifestyle & Traits     | `behavior`       | Renderizada como viñetas apiladas |
| Where to See & Book    | `where_to_see`   | Cada `###` = una card de tour; viñeta `CTA: Label \| /url` → botón |
| Quick Traveler FAQ     | `faq`            | Cada `###` = una pregunta |

El CTA del footer y el hero usan los metadatos `footer_*` y `cta_primary_*`. Secciones
requeridas: `identification`, `overview`, `where_to_see`.

```bash
wp-publish preview samples/sally-lightfoot-crab.md
wp-publish publish samples/sally-lightfoot-crab.md --type wildlife_tier2
```

---

## Estilos

Las páginas se renderizan correctamente sin ninguna configuración (los estilos están en línea). Para un pulido adicional
(hover states, TOC fija, espaciado más ajustado), agrega **`assets/wildlife.css`** a
tu tema mediante **Appearance → Customize → Additional CSS**. Todos los hooks llevan el
prefijo `.gwp-w-`.
