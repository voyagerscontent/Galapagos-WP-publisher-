# Publishing into ACF fields

The publisher does **not** write Gutenberg blocks. It captures your content doc,
composes it into structured **components**, and populates **ACF fields** over the
REST API. Your WordPress theme renders those fields, so design and UX live on the
WP side — no fixed page templates required in the doc.

```
content doc → compose (components) → map to ACF → POST { "acf": {...} } → WordPress
```

## 1. One-time WordPress setup

### a) Enable ACF in REST
Each ACF **field group** that the publisher writes to must have **Show in REST
API = On** (ACF 6.x: field group → Settings → *Show in REST API*). Without it,
core REST silently drops the `acf` payload.

### b) The field structure
The engine writes one ACF **flexible content** field whose rows are layouts
(hero, rich_text, faq, …), plus a couple of top-level fields (subtitle, schema).

You have two options:

- **Use your existing ACF group** — keep your field/layout names and tell the
  engine about them in `config/acf.yaml` (see §2). Recommended.
- **Adopt the reference group** — import
  [`wordpress-acf/page-builder.acf.json`](../wordpress-acf/page-builder.acf.json)
  via **ACF → Tools → Import Field Groups**. It matches the default
  `config/acf.yaml` out of the box.

### c) Auth
Same as before — a WordPress Application Password in the repo secrets, and (on
this site) the miniOrange REST endpoints left open for `/wp/v2/*`. See
[TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## 2. Map to your fields — `config/acf.yaml`

This file is the **only** place field names live. The left side is the engine's
component model (fixed); the right side is **your** ACF field/layout names.

```yaml
flexible_field: page_sections      # your ACF flexible-content field name
top_level:
  subtitle: page_subtitle          # your text field (set "" to skip)
  schema_jsonld: seo_schema        # your textarea field for JSON-LD
image_as: id                       # ACF image fields store attachment IDs
layouts:
  hero:
    layout: hero                   # the ACF layout name
    fields: { heading: heading, subheading: subheading, image: image, ctas: buttons }
    repeaters: { ctas: { label: label, url: url } }
  rich_text:
    layout: rich_text
    fields: { heading: heading, content: content }
  faq:
    layout: faq
    fields: { heading: heading, items: items }
    repeaters: { items: { question: question, answer: answer } }
  # ... callout, stats, cta, columns, image, gallery, quote, bullets, html
```

Each component becomes one flexible-content row:

```json
{ "acf_fc_layout": "hero", "heading": "…", "buttons": [ { "label": "…", "url": "…" } ] }
```

A component type with **no** mapping falls back to `rich_text` (its content
coerced to HTML), so nothing is ever dropped.

## 3. How content becomes components

### Auto (a normally-written doc)
Deterministic rules build the page for readability:

| In the doc | Component |
| ---------- | --------- |
| Title + `tagline` + `featured_image_query` + `cta_*` metadata | **hero** |
| Lead paragraph(s) before the first heading | **rich_text** (intro) |
| Each `## Section` | **rich_text** (heading + HTML) |
| An `## FAQ` with `### question` items | **faq** (accordion) |
| A `facts:` map in the header | **stats** |
| `booking_heading` / `footer_cta_*` metadata | **cta** |

### Bespoke (hand-designed page)
If the doc contains `:::` directives, the uploader is designing a one-off
layout; each directive maps to a component:

| Directive | Component (ACF layout) |
| --------- | ---------------------- |
| `hero` | hero |
| `columns` / `column` | columns |
| `cards` / `card` | columns |
| `callout` | callout |
| `accordion` | faq |
| `cta` | cta |
| `image` | image |
| `buttons` | cta (buttons only) |
| `group` / `section` | flattened into its inner components |
| `html` | html |
| plain Markdown | rich_text |

See [AUTHORING_GUIDE.md](AUTHORING_GUIDE.md) for the directive syntax.

## 4. Preview & publish

```bash
wp-publish preview samples/galapagos-cruise.md      # writes output/<slug>/acf.json
wp-publish publish samples/galapagos-cruise.md      # POSTs the acf payload (draft)
```

`acf.json` is exactly what gets sent as the post's `acf` object — inspect it to
confirm the mapping before publishing. `post_content` is intentionally empty.

## 5. Images

ACF image fields store **attachment IDs**. In `--media library` mode the engine
matches your Media Library and fills the ID; otherwise the image field is left
empty and a warning lists what's needed (the suggested search terms are kept so
a human can drop the right asset in). `image_as: url` switches to storing URLs
if your fields expect them.

## 6. SEO & schema

SEO title/description/focus keyword are still written to RankMath/Yoast meta,
and the schema.org JSON-LD is delivered as the `seo_schema` ACF field — output
it inside a `<script type="application/ld+json">` tag in your theme `<head>`.
