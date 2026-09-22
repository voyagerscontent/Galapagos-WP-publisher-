> Traducción al español. Versión original en inglés: [../../README.md](../../README.md).

# WP Publisher — Islas Galápagos

Un motor independiente del sitio que convierte un documento en una página de
WordPress totalmente formateada, optimizada para SEO y rica en esquemas — con la
menor intervención manual posible.

> **Este repositorio publica en https://www.galapagosislands.travel.**
> El motor en sí (`src/wp_publisher/`) no lleva ninguna identidad de sitio — cada
> sitio tiene su propio repositorio con su propio `config/`, `templates/` y `.env`.
> Para levantar otro sitio, consulta [../NEW_SITE.md](../NEW_SITE.md).

Tú escribes el contenido (en Word, Markdown, texto plano o Google Docs). El
sistema lo compone en **componentes** estructurados, genera los metadatos SEO y
el marcado de schema.org, coloca las imágenes (o marca dónde se necesitan) y
**puebla los campos ACF** a través de la API REST. Tu tema de WordPress renderiza
los campos ACF, de modo que el diseño y la UX se quedan del lado de WP — sin
bloques de Gutenberg, sin plantillas de página fijas en el documento. Consulta
[../ACF.md](../ACF.md).

```
  doc (.docx / .md / .txt / Google Doc)
        │
        ▼
  ingest  →  normalize  →  detect page-type profile (schema/categories)
        →  SEO (title, slug, meta)  →  compose into components (auto, or
        →  resolve media  →  schema.org JSON-LD  →  bespoke ::: directives)
        →  map components → ACF  →  publish to WordPress (REST `acf`)
```

## Motor vs. sitio

| Capa | Vive en | ¿Específico del sitio? |
| ----- | -------- | -------------- |
| **Motor** (ingesta, renderizado, SEO, esquema, publicación) | `src/wp_publisher/` | No — reutilizado tal cual por cada sitio |
| **Config del sitio** (organización, URL, valores por defecto de SEO, media) | `config/site.yaml` | Sí |
| **Plantillas** (una por tipo de página) | `templates/*.yaml` | Sí |
| **Secretos** (URL de WP, App Password) | `.env` | Sí |

Esa separación es todo el objetivo: el mismo motor impulsa cualquier cantidad de
sitios; cada repositorio solo difiere en config, plantillas y secretos.

## Plantillas (una por sección del sitio)

Cada **sección del sitio** tiene una plantilla (`templates/*.yaml`) que declara
el layout de la página, las secciones requeridas, el tipo de schema.org y las
reglas de SEO/media. Cuando envías un documento, el sistema lo formatea y publica
*correctamente para la sección para la que fue escrito*.

| Plantilla      | Para                                   | Tipo de esquema          |
| ------------- | ------------------------------------- | -------------------- |
| `blog_post`   | Artículos, guías de viaje, noticias         | `BlogPosting`        |
| `destination` | Descripciones de lugares (islas, sitios…)     | `TouristDestination` |
| `tour`        | Itinerarios / viajes vendibles              | `TouristTrip`        |
| `cruise`      | Cruceros de Galápagos / barcos específicos    | `TouristTrip`        |
| `wildlife_tier1` | Especies icónicas (500+ búsquedas/mes)  | `Article` + `FAQPage` |
| `wildlife_tier2` | Especies compactas (≤499 búsquedas/mes) | `Article` + `FAQPage` |
| `freeform`    | **Cualquier** página/entrada — tú diseñas el layout con directivas | `WebPage` |

Agrega o ajusta un tipo de página editando YAML — sin cambios de código. Consulta
[../TEMPLATES.md](../TEMPLATES.md).

## Inicio rápido

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"          # add ,gdrive for Google Drive support

cp .env.example .env             # fill in WordPress credentials for this site

# See what's available
wp-publish templates

# Build a page locally WITHOUT publishing (writes to ./output/)
wp-publish preview samples/galapagos-cruise.md

# Verify your WordPress connection
wp-publish check

# Publish (defaults to a DRAFT — safe)
wp-publish publish samples/galapagos-cruise.md
wp-publish publish my-tour.docx --type tour --media library --status draft

# Pull straight from Google Drive
wp-publish gdrive "8-Day Galapagos" --preview
```

> La publicación por defecto es **draft** para que nada se ponga en vivo por
> accidente. Usa `--status publish` (con un aviso de confirmación) cuando estés
> listo.

## Cómo se debe escribir un documento

Agrega un encabezado breve de metadatos para que el sistema conozca el tipo de
página y los campos clave. En Markdown eso es frontmatter YAML; en Word/texto son
unas pocas líneas `Key: value` al inicio. Todo lo demás son simplemente
encabezados y párrafos normales.

```markdown
---
type: cruise
title: "8-Day Galapagos Cruise Aboard the M/Y Evolution"
ship: "M/Y Evolution"
destination: "Galapagos Islands"
duration: "8 days"
price: "from $5,495"
focus_keyword: "Galapagos cruise"
categories: "Galapagos Cruises"
---

# 8-Day Galapagos Cruise Aboard the M/Y Evolution

## Overview
...

## Itinerary
1. Day 1 — ...

## What's Included
- ...

## FAQ
### Is the cruise suitable for non-swimmers?
Yes — every excursion offers a dry-landing alternative...
```

Los encabezados de sección se pueden expresar con naturalidad ("What's Included",
"Inclusions", "Price Includes" mapean todos al mismo slot). Consulta
[../WORKFLOW.md](../WORKFLOW.md) para la guía completa de autoría.

## Qué se genera

- **Campos ACF planos** (`hero_heading`, `hero_image`, `body`, repetidor `faq`,
  `key_facts`, `cta_*`, `seo_schema`…) poblados por REST, listos para vincular en
  **Elementor** con Dynamic Tags. El mapeo de campos vive en `config/acf.yaml`.
  Consulta [../ACF.md](../ACF.md).
- **SEO**: slug limpio, `<title>` con longitud verificada, meta descripción tejida
  con la palabra clave de enfoque, escrita en Yoast/RankMath cuando se detecta.
- **schema.org JSON‑LD**: el tipo correcto por perfil, más un `FAQPage` cuando el
  documento tiene un FAQ — entregado como el campo ACF `seo_schema`.
- **Media**: los IDs de adjuntos de mejor coincidencia de tu Biblioteca de Medios
  de WordPress, o una advertencia que lista lo que un humano necesita agregar.
- **Advertencias**: secciones esperadas faltantes, meta corta, brechas de palabras
  clave, componentes no mapeados — expuestas antes de que publiques.

## Documentación

- [../ACF.md](../ACF.md) — **cómo se mapea el contenido a los campos ACF** (configuración, `config/acf.yaml`, componentes)
- [../AUTHORING_GUIDE.md](../AUTHORING_GUIDE.md) — **qué poner en tu documento de contenido** (metadatos + directivas `:::` a medida)
- [../PUBLISH_VIA_GITHUB.md](../PUBLISH_VIA_GITHUB.md) — publica desde tu navegador (sin máquina local)
- [../TROUBLESHOOTING.md](../TROUBLESHOOTING.md) — soluciones para errores de auth/API REST (incl. miniOrange y WP Cerber)
- [SETUP.md](SETUP.md) — configuración de App Password de WordPress + Google Drive
- [../TEMPLATES.md](../TEMPLATES.md) — cómo funcionan las plantillas y cómo agregar una
- [../WILDLIFE_TEMPLATE.md](../WILDLIFE_TEMPLATE.md) — la plantilla de Wildlife Tier 1 (especies icónicas)
- [../WORKFLOW.md](../WORKFLOW.md) — guía de autoría y flujo de extremo a extremo
- [../NEW_SITE.md](../NEW_SITE.md) — levanta un repositorio para un sitio distinto

## Estructura del proyecto

```
config/site.yaml          Per-site defaults (org, SEO limits, media strategy)
templates/*.yaml          One template per page type (per-site)
samples/                  Example documents
src/wp_publisher/         The site-agnostic engine:
  ingest/                 Word / Markdown / text / Google Drive readers
  parse/                  Page-type detection
  rendering/              Templates + Gutenberg block engine
  seo/                    Title / slug / meta optimization
  schema/                 schema.org JSON-LD
  media/                  Library matching + placeholders
  wordpress/              REST client + publisher
  pipeline.py             Orchestration
  cli.py                  `wp-publish` command
```

Ejecuta las pruebas con `pytest`.
</content>
</invoke>
