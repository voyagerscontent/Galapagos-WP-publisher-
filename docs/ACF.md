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

## 7. Standalone field group: Experts Profile

`wordpress-acf/profile.acf.json` is an importable ACF field group for **expert
pages**, independent of the composer output. Import it via **Custom Fields →
Tools → Import**.

- **Location:** `Galápagos Page Type == Experts` (post-meta marker
  `gp_page_type = experts`). Organised with ACF tabs (`type: "tab"`): **About**
  (photo image — `return_format: id` —, `role`, `company`, + an intro wysiwyg), **Idiomas** (wysiwyg),
  **Destino** (`destinations` repeater, sub-field `text` wysiwyg), **Expertise**
  (`expertise` repeater, sub-field `text`), **Información Adicional**
  (`additional_info` repeater: `title` + `text`), **Redes Sociales**
  (`social_networks` repeater: `name` + `text`).
- `show_in_rest` is **on**; labels are in Spanish; keys are prefixed
  `field_exp_*` — verified not to collide with `page-fields.acf.json` or
  `page-builder.acf.json`.
- **The publisher does NOT fill these fields.** `config/acf.yaml` only maps the
  composer's *compound components* (hero / body / faq / cta / feature_sections …).
  Expert pages are authored by hand in wp-admin, or populated by your own script
  writing the REST `acf` object.

### Registering the "Experts" page-type option

The site's page-type marker rule is **`gp_page_type`** (label "Galápagos Page
Type"). The `island-elementor-widgets` plugin already registers the **Experts**
option (the location-rule value **and** the editor meta box). If you are *not*
using that plugin, add this to `functions.php` or a mu-plugin:

```php
// 1) Offer "Experts" as a value of the Galápagos Page Type location rule.
add_filter('acf/location/rule_values/gp_page_type', function ($choices) {
    $choices['experts'] = 'Experts';
    return $choices;
});

// 2) Match the rule against the post's gp_page_type meta.
add_filter('acf/location/rule_match/gp_page_type', function ($match, $rule, $screen) {
    $post_id = $screen['post_id'] ?? 0;
    if (!$post_id) { return false; }
    $val = (string) get_post_meta((int) $post_id, 'gp_page_type', true);
    return ($rule['operator'] === '!=') ? ($val !== $rule['value']) : ($val === $rule['value']);
}, 10, 3);

// 3) (optional) register the rule type + a meta box so editors can SET the marker.
add_filter('acf/location/rule_types', function ($choices) {
    $choices['Galápagos']['gp_page_type'] = 'Galápagos Page Type';
    return $choices;
});
```

> **Param name:** the group JSON uses `gp_page_type` — the site's real rule slug —
> not `galapagos_page_type`. They refer to the same "Galápagos Page Type" rule;
> use `gp_page_type` so the group matches the editor meta box and the existing
> Informative / Itineraries options.

### Alternative: page type as a taxonomy term

If you model the page type as a **taxonomy** term rather than a post-meta marker,
drop the custom rule and use ACF's built-in `post_taxonomy` rule instead — no PHP
filter needed:

```json
"location": [[{ "param": "post_taxonomy", "operator": "==", "value": "page-type:experts" }]]
```

(where `page-type` is the taxonomy and `experts` the term slug).
