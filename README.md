# WP Publisher — Galapagos Islands

A site-agnostic engine that turns a document into a fully formatted,
SEO‑optimized, schema‑rich WordPress page — with the least manual intervention
possible.

> **This repository publishes to https://www.galapagosislands.travel.**
> The engine itself (`src/wp_publisher/`) carries no site identity — every site
> gets its own repository with its own `config/`, `templates/`, and `.env`.
> To stand up another site, see [docs/NEW_SITE.md](docs/NEW_SITE.md).

You write the content (in Word, Markdown, plain text, or Google Docs). The
system composes it into structured **components**, generates the SEO metadata and
schema.org markup, places images (or marks where they're needed), and
**populates ACF fields** over the REST API. Your WordPress theme renders the ACF
fields, so design and UX stay on the WP side — no Gutenberg blocks, no fixed page
templates in the doc. See [docs/ACF.md](docs/ACF.md).

```
  doc (.docx / .md / .txt / Google Doc)
        │
        ▼
  ingest  →  normalize  →  detect page-type profile (schema/categories)
        →  SEO (title, slug, meta)  →  compose into components (auto, or
        →  resolve media  →  schema.org JSON-LD  →  bespoke ::: directives)
        →  map components → ACF  →  publish to WordPress (REST `acf`)
```

## Engine vs. site

| Layer | Lives in | Site-specific? |
| ----- | -------- | -------------- |
| **Engine** (ingest, render, SEO, schema, publish) | `src/wp_publisher/` | No — reused as‑is by every site |
| **Site config** (org, URL, SEO defaults, media) | `config/site.yaml` | Yes |
| **Templates** (one per page type) | `templates/*.yaml` | Yes |
| **Secrets** (WP URL, App Password) | `.env` | Yes |

That separation is the whole point: the same engine powers any number of sites;
each repo only differs in config, templates, and secrets.

## Templates (one per site section)

Each **section of the site** has a template (`templates/*.yaml`) that declares
the page's layout, required sections, schema.org type, and SEO/media rules. When
you send a doc, the system formats and publishes it *correctly for the section
it was written for*.

| Template      | For                                   | Schema type          |
| ------------- | ------------------------------------- | -------------------- |
| `blog_post`   | Articles, travel guides, news         | `BlogPosting`        |
| `destination` | Place overviews (islands, sites…)     | `TouristDestination` |
| `tour`        | Sellable itineraries / trips          | `TouristTrip`        |
| `cruise`      | Galapagos cruises / specific ships    | `TouristTrip`        |
| `wildlife_tier1` | Iconic species (500+ searches/mo)  | `Article` + `FAQPage` |
| `wildlife_tier2` | Compact species (≤499 searches/mo) | `Article` + `FAQPage` |
| `freeform`    | **Any** page/post — you design the layout with directives | `WebPage` |

Add or tune a page type by editing YAML — no code changes. See
[docs/TEMPLATES.md](docs/TEMPLATES.md).

## Quick start

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

> Publishing defaults to **draft** so nothing goes live by accident. Use
> `--status publish` (with a confirmation prompt) when you're ready.

## How a doc should be written

Add a short metadata header so the system knows the page type and key fields.
In Markdown that's YAML frontmatter; in Word/text it's a few `Key: value` lines
at the top. Everything else is just normal headings and paragraphs.

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

Section headings can be phrased naturally ("What's Included", "Inclusions",
"Price Includes" all map to the same slot). See
[docs/WORKFLOW.md](docs/WORKFLOW.md) for the full authoring guide.

## What gets generated

- **Flat ACF fields** (`hero_heading`, `hero_image`, `body`, `faq` repeater,
  `key_facts`, `cta_*`, `seo_schema`…) populated over REST, ready to bind in
  **Elementor** with Dynamic Tags. The field mapping lives in `config/acf.yaml`.
  See [docs/ACF.md](docs/ACF.md).
- **SEO**: clean slug, length‑checked `<title>`, meta description woven with
  the focus keyword, written to Yoast/RankMath when detected.
- **schema.org JSON‑LD**: the right type per profile, plus an `FAQPage` when
  the doc has a FAQ — delivered as the `seo_schema` ACF field.
- **Media**: best‑match attachment IDs from your WordPress Media Library, or a
  warning listing what a human needs to add.
- **Warnings**: missing expected sections, short meta, keyword gaps, unmapped
  components — surfaced before you publish.

## Documentation

- [docs/ACF.md](docs/ACF.md) — **how content maps to ACF fields** (setup, `config/acf.yaml`, components)
- [docs/AUTHORING_GUIDE.md](docs/AUTHORING_GUIDE.md) — **what to put in your content doc** (metadata + bespoke `:::` directives)
- [docs/PUBLISH_VIA_GITHUB.md](docs/PUBLISH_VIA_GITHUB.md) — publish from your browser (no local machine)
- [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) — fixes for auth/REST API errors (incl. miniOrange & WP Cerber)
- [docs/SETUP.md](docs/SETUP.md) — WordPress App Password + Google Drive setup
- [docs/TEMPLATES.md](docs/TEMPLATES.md) — how templates work and how to add one
- [docs/WILDLIFE_TEMPLATE.md](docs/WILDLIFE_TEMPLATE.md) — the Tier 1 Wildlife (iconic species) template
- [docs/WORKFLOW.md](docs/WORKFLOW.md) — authoring guide and end‑to‑end flow
- [docs/NEW_SITE.md](docs/NEW_SITE.md) — spin up a repo for a different site

## Project layout

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

Run the tests with `pytest`.
