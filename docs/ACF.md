# Publishing into ACF fields (flat) for Elementor

The publisher does **not** write Gutenberg blocks. It captures your content doc,
composes it into structured components, and writes **flat, named ACF fields** over
the REST API. You then design the page in **Elementor**, binding each field with
**Dynamic Tags → ACF Field**. The data and the design stay cleanly separate.

```
content doc → compose → flat ACF fields → POST { "acf": {...} } → WordPress → Elementor (Dynamic Tags)
```

## 1. Create the field group in WordPress (one time)

> **Important:** the publisher *writes values* into ACF fields; it does **not**
> create the field-group *definitions*. ACF field groups can't be created over
> the content REST API — so the group must exist in WordPress first, or the
> `acf` payload is silently dropped (you'd get a post with only a title).

Two ways to create it:

- **Import the ready group** — WP admin → **ACF → Tools → Import Field Groups**
  → upload [`wordpress-acf/page-fields.acf.json`](../wordpress-acf/page-fields.acf.json).
  It matches `config/acf.yaml` exactly and has **Show in REST API** on.
- **Or register it in your theme/site repo** via ACF local JSON (`acf-json/`),
  keeping the same field names.

Then confirm the group has **Show in REST API = On** (Field Group → Settings).
Elementor reads ACF straight from the database, so Show-in-REST is only needed so
*our automation* can write the values.

## 2. The fields

| ACF field | Type | Filled from |
| --------- | ---- | ----------- |
| `hero_eyebrow` | text | optional `eyebrow` |
| `hero_heading` | text | the title |
| `hero_subheading` | textarea | tagline / "Quick Answer" |
| `hero_image` | image (ID) | first image / `featured_image_query` |
| `hero_cta_label`, `hero_cta_url` | text | first hero CTA |
| `body` | wysiwyg | all prose sections as clean HTML (`<h2>`, `<p>`, `<ul>`, inline `<figure>`) |
| `callout_title`, `callout_text` | text / wysiwyg | a `CALLOUT STAT` / `:::callout` |
| `faq` | repeater (`question`, `answer`) | the FAQ block |
| `key_facts` | repeater (`label`, `value`) | a `facts:` map |
| `cta_heading`, `cta_text`, `cta_label`, `cta_url` | text / wysiwyg | a closing CTA |
| `page_subtitle` | text | `tagline`/`subtitle` |
| `seo_schema` | textarea | schema.org JSON-LD |

To use **your own** field names, edit the right-hand side of `config/acf.yaml`
(`flat:` and `top_level:`). The component model is fixed; only the names change.

## 3. Build the page in Elementor with Dynamic Tags

Design the template once (Elementor Pro):

1. Add a widget (Heading, Text Editor, Image…).
2. Click the **Dynamic Tags** icon (🛢️) on the content field.
3. Choose **ACF Field** → pick the field (e.g. `hero_heading`).
4. For `faq` / `key_facts` (repeaters): use a **Loop Grid** / repeater, or an
   accordion widget that reads the ACF repeater.

Every new doc we publish fills these fields, and your Elementor template renders
them automatically — no re-layout per page.

## 4. Preview & publish

```bash
wp-publish preview content/docdepruebainicial.docx     # writes output/<slug>/acf.json
wp-publish publish  content/docdepruebainicial.docx     # POSTs the flat acf payload (draft)
```

`acf.json` is exactly what gets sent as the post's `acf` object — inspect it to
confirm the mapping before publishing. `post_content` stays empty.

### Safety
The publisher **refuses** to touch an existing post with the same slug unless you
pass `--update` (workflow: the `update_existing` toggle, default off), and even
then it never changes an existing post's status or blanks its content. Test new
docs under a fresh slug.

## 5. Images

ACF image fields store **attachment IDs**. In `--media library` mode the engine
matches your Media Library and fills the ID; otherwise the field is left empty
and a warning lists the suggested search terms for a human to add the asset.

## 6. SEO & schema

SEO title/description/focus keyword are written to RankMath/Yoast meta. The
schema.org JSON-LD is delivered as the `seo_schema` field — output it inside a
`<script type="application/ld+json">` tag in your Elementor/theme header.
