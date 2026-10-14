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

## 7. Expert profile group (`profile.acf.json`)

[`wordpress-acf/profile.acf.json`](../wordpress-acf/profile.acf.json) is an
importable field group — **Perfil de Experto** (`group_expert_profile`) — for
expert / guide profile pages. Import it the same way as any other group: WP admin
→ **ACF → Tools → Import Field Groups**. Labels are in Spanish, `show_in_rest` is
on, and every key is prefixed `field_exp_*` so it can't collide with
`page-fields.acf.json` (`f_*`) or `page-builder.acf.json` (`f_*`).

> **The publisher does not fill these fields.** `config/acf.yaml` only maps the
> composed component model (hero, body, callout, faq, key_facts, cta…) onto the
> flat page group. The expert profile is hand-edited in the WordPress admin; it
> exists so the theme/Elementor has structured data to render. Publishing a doc
> to an Experts page fills the *page* group, never this one.

Fields, organised with ACF tabs (`type: "tab"`):

| Tab | Field | Type |
| --- | ----- | ---- |
| About | `about_image` | image (`return_format: "id"`) |
| About | `about_text` | wysiwyg |
| Idiomas | `languages` | wysiwyg |
| Destino | `destinations` | repeater → `text` (wysiwyg) |
| Expertise | `expertise` | repeater → `text` (text) |
| Información Adicional | `additional_info` | repeater → `title` (text), `text` (wysiwyg) |
| Redes Sociales | `social_networks` | repeater → `name` (text), `text` (text) |

Loop the repeaters in Elementor with a Loop Grid or a repeater-aware widget, the
same as `faq` / `key_facts` in §3.

### Location: Galápagos Page Type == Experts

The group attaches through this site's own location rule, the same one
`informative-page.json` and `itineraries-page.json` use:

```json
[ { "param": "gp_page_type", "operator": "==", "value": "experts" } ]
```

The rule is labelled **"Galápagos Page Type"** in the ACF UI but its *param slug*
is `gp_page_type` — that is the key registered in
[`wordpress-plugin/island-elementor-widgets.php`](../wordpress-plugin/island-elementor-widgets.php).
A group locating on `galapagos_page_type` would never attach, because no rule is
registered under that slug. If you prefer the longer slug, rename it in all four
places at once: the plugin's `rule_types` key, both filter names
(`acf/location/rule_values/<slug>`, `acf/location/rule_match/<slug>`), the
`register_post_meta` name, and the `param` in every `*.acf.json`.

### Registering the "Experts" option

The dropdown behind that rule only offered *Informative* and *Itineraries*. The
bundled plugin now also registers *Experts* (`rule_values`, the editor sidebar
meta box, and the save whitelist). If you don't run that plugin, drop this into
`functions.php` or an mu-plugin — it is self-contained and idempotent, so it's
also safe alongside the plugin:

```php
<?php
// Galápagos Page Type → "Experts": registers the marker meta, the ACF location
// rule and its match callback. Safe to load next to island-elementor-widgets.php.

add_action('init', function () {
    register_post_meta('page', 'gp_page_type', [
        'type'          => 'string',
        'single'        => true,
        'show_in_rest'  => true,
        'default'       => '',
        'auth_callback' => function () {
            return current_user_can('edit_pages');
        },
    ]);
});

// Make the rule itself available (no-op if already registered).
add_filter('acf/location/rule_types', function ($choices) {
    $choices['Galápagos']['gp_page_type'] = 'Galápagos Page Type';
    return $choices;
});

// Add "Experts" to the rule's value dropdown.
add_filter('acf/location/rule_values/gp_page_type', function ($choices) {
    $choices['experts'] = 'Experts';
    return $choices;
});

// Match the rule against the gp_page_type post meta.
add_filter('acf/location/rule_match/gp_page_type', function ($match, $rule, $screen) {
    $post_id = $screen['post_id'] ?? 0;
    if (!$post_id) {
        return false;
    }
    $val = (string) get_post_meta((int) $post_id, 'gp_page_type', true);
    return ($rule['operator'] === '!=') ? ($val !== $rule['value']) : ($val === $rule['value']);
}, 10, 3);
```

Mark a page as an expert profile by setting the `gp_page_type` meta to `experts`
— via the **Galápagos Page Type** box in the page sidebar, or over the REST API
(`"meta": { "gp_page_type": "experts" }`).

### Alternative: locate by taxonomy

If your page type is a **taxonomy term** rather than a post-meta marker, skip the
custom rule entirely and use ACF's built-in `post_taxonomy` rule — no PHP needed:

```json
[ { "param": "post_taxonomy", "operator": "==", "value": "page_type:experts" } ]
```

The value is `<taxonomy>:<term-slug>` (e.g. `page_type:experts`). Two caveats:
the taxonomy must be registered for the post type the profiles live on, and ACF
evaluates the rule against the terms *saved* on the post — a page created over
REST needs the term assigned in the same request (`"page_type": [<term_id>]`,
with the taxonomy `show_in_rest` enabled) or the group won't appear until the
term is set and the editor reloaded.
