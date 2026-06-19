# Galapagos WP Publisher

Turn a document into a fully formatted, SEO‑optimized, schema‑rich WordPress
page — with the least manual intervention possible.

You write the content (in Word, Markdown, plain text, or Google Docs). The
system detects what *kind* of page it is, arranges it with a reusable
**template**, generates the SEO metadata and schema.org markup, places images
(or marks where they're needed), and publishes it to WordPress as native
Gutenberg blocks that respect your theme.

```
  doc (.docx / .md / .txt / Google Doc)
        │
        ▼
  ingest  →  normalize  →  detect page type  →  apply template
        →  SEO (title, slug, meta)  →  render Gutenberg blocks
        →  resolve media (library / placeholder)  →  schema.org JSON-LD
        →  publish to WordPress (REST API)
```

## Why templates

Each **section of the site** has its own template (`templates/*.yaml`) that
declares the page's layout, required sections, schema.org type, and SEO/media
rules. When you send a doc, the system formats and publishes it *correctly for
the section it was written for* — a cruise page, a tour, a destination, or a
blog post — without you re‑doing the layout each time.

| Template      | For                                   | Schema type          |
| ------------- | ------------------------------------- | -------------------- |
| `blog_post`   | Articles, travel guides, news         | `BlogPosting`        |
| `destination` | Place overviews (Galapagos, Cusco…)   | `TouristDestination` |
| `tour`        | Sellable itineraries / trips          | `TouristTrip`        |
| `cruise`      | Galapagos cruises / specific ships    | `TouristTrip`        |

Add or tune a page type by editing YAML — no code changes. See
[docs/TEMPLATES.md](docs/TEMPLATES.md).

## Quick start

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"          # add ,gdrive for Google Drive support

cp .env.example .env             # fill in WordPress credentials

# See what's available
wp-publish templates

# Build a page locally WITHOUT publishing (writes to ./output/)
wp-publish preview samples/galapagos-cruise.md

# Verify your WordPress connection
wp-publish check

# Publish (defaults to a DRAFT — safe)
wp-publish publish samples/galapagos-cruise.md
wp-publish publish my-tour.docx --type tour --media library --status draft

# Pull straight from Google Drive (businessops@latintrails.com)
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
type: tour
title: "7-Day Peru Highlights: Cusco, Sacred Valley & Machu Picchu"
destination: "Peru"
duration: "7 days"
price: "from $2,150"
focus_keyword: "Peru tour"
categories: "Tours, Peru"
---

# 7-Day Peru Highlights

## Overview
...

## Trip Highlights
- ...

## Day-by-Day Itinerary
1. Day 1 — ...

## What's Included
- ...

## FAQ
### Is altitude a concern?
Yes — we build in acclimatization days...
```

The section headings can be phrased naturally ("What's Included", "Inclusions",
"Price Includes" all map to the same slot). See
[docs/WORKFLOW.md](docs/WORKFLOW.md) for the full authoring guide.

## What gets generated

- **Native Gutenberg blocks** (headings, lists, quotes, tables, images) so the
  content stays editable and on‑theme — never a frozen HTML blob.
- **SEO**: clean slug, length‑checked `<title>`, meta description woven with
  the focus keyword, written to Yoast/RankMath when detected.
- **schema.org JSON‑LD**: the right type per template, plus an `FAQPage` when
  the doc has a FAQ — embedded so it ships regardless of plugins.
- **Media**: best‑match from your WordPress Media Library, or a clearly marked
  placeholder block for a human to fill.
- **Warnings**: missing required sections, short meta, keyword gaps — surfaced
  before you publish.

## Documentation

- [docs/SETUP.md](docs/SETUP.md) — WordPress App Password + Google Drive setup
- [docs/TEMPLATES.md](docs/TEMPLATES.md) — how templates work and how to add one
- [docs/WORKFLOW.md](docs/WORKFLOW.md) — authoring guide and end‑to‑end flow

## Project layout

```
config/site.yaml          Site-wide defaults (org, SEO limits, media strategy)
templates/*.yaml          One template per page type
samples/                  Example documents
src/galapagos_publisher/
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
