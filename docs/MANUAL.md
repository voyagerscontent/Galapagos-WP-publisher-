> English version · Versión en español: [es/MANUAL.md](es/MANUAL.md).

# Usage manual — Galápagos Publisher

A practical manual for the system that converts a document (HTML, Word) into an
optimized WordPress page (SEO + schema) on **galapagosislands.travel**.

It is divided into two parts:

- **[Part A — Marketing](#part-a--marketing-daily-use)**: how to upload a document
  and publish it. You do not need to know how to code.
- **[Part B — Technical](#part-b--technical-maintenance)**: how the system is built,
  the repository, the engine, and the n8n workflow under the hood.

> **Golden safety rule:** everything is published as a **draft** and with the
> **`-test`** suffix in the URL. Nothing goes “live” automatically. A human reviews
> the draft in WordPress and decides.

---

# Part A — Marketing (daily use)

## 1. What the system does (in one paragraph)

You upload a document. The system reads it, detects **what type of page** it is (by
its URL), assembles the content, optimizes it for SEO, generates the schema (the
data Google uses for rich results), and creates a **draft** in WordPress by filling
in the ACF fields. The WordPress theme (with Elementor) takes care of the design.

The system leaves a **draft**, but it does **not** leave it ready to publish on its
own. Before publishing, a person must **review, validate, and complete** some things
by hand — especially the **SEO** and the **deindexing** (`noindex`), which the
system does **not** set. See the mandatory checklist in [§4](#4-before-publishing--mandatory-checklist).

## 2. The 3 document formats

The system understands **three formats**. It is essential to send the correct one:

| Format | What it is | Upload it? |
|---|---|---|
| **HTML + `schema.json`** | The page in HTML **plus** its separate schema file | ✅ Yes — upload **both** |
| **CMS Stage 8** (`.docx`) | A Word file that **contains the content** (`[AIO BLOCK]` blocks, tables, FAQs…) | ✅ Yes |
| **Editor Copy** (green, `.docx`) | A Word file that only carries **instructions for the editor** (highlighted in green) | ❌ **No** — it is not content |

**Simple rules:**
- If you receive an **HTML** file with its **`.json`** on the side → upload **both**.
- If the HTML already includes the schema inside → the HTML alone is enough.
- If you receive a **CMS Stage 8** file (Word with content) → upload only that `.docx`.
- The **green Editor Copy** is only for reading the guidance — **never upload it**.

## 3. Publishing with n8n (usage)

The system is already **configured and active** — you just have to use the form.
Publishing is uploading the document and submitting; the rest is automatic.

### How to publish

1. Open the n8n **form URL** (the technical team shares it with you; it is fixed).
2. Fill in the form:
   - **Document**: upload the HTML or the `.docx` (CMS Stage 8).
   - **Schema JSON** (optional): only if it is an HTML file whose schema comes separately, upload the `.json`.
3. Press **submit**.
4. In **~1 minute** the draft will be in WordPress.

That's it. There is nothing to activate, no need to touch n8n, and no need to choose
the page type (it is detected automatically from the document's URL).

### What to check afterwards

- **WordPress**: a new draft appears with the **`-test`** suffix in the URL.
- Review the content, the images, and the design (rendered by Elementor).
- If something looks wrong, fix the document and **upload it again** (see §5).

## 4. Before publishing — mandatory checklist

The draft **is not ready as it comes out**. The system assembles the content and
pre-fills part of the SEO, but **you must review, validate, and complete** this in
WordPress **before** publishing:

**1. Deindex the page (`noindex`) — the system does NOT do this.**
- In the page's SEO plugin (Rank Math / Yoast), set **`noindex`** (robots:
  no index, no follow) while it is a test/`-test` page or not yet approved.
- This keeps Google from indexing it prematurely. The `noindex` is only removed when
  the final page is approved to go live.

**2. Validate and complete the SEO — by hand.**
The system can pre-fill the **meta title**, the **meta description**, and the
**focus keyword**, but **do not trust them to be complete or final**. Review and
complete in the SEO plugin:
- **Meta title** — correct, with the keyword, within the recommended length.
- **Meta description** — attractive and within the length.
- **Focus keyword / keywords** — set/adjust them yourself.
- **Schema** — the system generates the JSON-LD; verify that it matches the page.

**3. Validate the content and the design.**
- Text, images, tables, FAQs, and the Elementor render.

**4. Only then, publish.**
- Change the status to published **only when** 1–3 are done and approved.

> In short: the system saves you 80% of the work, but the **final SEO and the
> `noindex` are the reviewer's responsibility**, not the system's.

## 5. Republishing / fixing without losing images

If you fix the document and upload it again, the system **does not create a
duplicate**: it finds the `-test` draft with the same slug and **updates** it.

- The **images and icons you uploaded by hand** in WordPress **are preserved** when
  republishing (the system detects and respects them).
- The **text** is replaced with the new version of the document.
- A copy of each uploaded document is stored in the repository (history).

## 6. Common errors and what to do

| Symptom | Likely cause | What to do |
|---|---|---|
| The draft came out without a schema | You uploaded only the HTML and the schema came separately | Upload the HTML again **with** its `.json` |
| The page appears indexed / without SEO | The system does not set `noindex` or finalize the SEO | Complete it by hand (see §4) |
| It published but with the wrong page type | The document's URL does not fall into a known section | Notify the technician: that URL section needs to be mapped |
| A field (image/text) is not saved in WordPress | ACF group configuration problem | Notify the technician (see Part B §10) |
| The form errors out on a node | The credential is missing or the token lacks permissions | Notify the technician (config in Part B §9.1) |

---

# Part B — Technical (maintenance)

## 6. Repository map

```
galapagos-wp-publisher-/
├── src/wp_publisher/     # THE ENGINE (Python): reads doc → assembles ACF → publishes
│   ├── ingest/           #   format readers: html_reader, cms (Stage 8), docx, markdown, text, gdrive
│   ├── content/          #   composition: compose, richtext, directives
│   ├── acf/              #   config.py (loads acf.yaml) + mapper.py (components → ACF payload)
│   ├── parse/            #   pagetype.py (detects the page type by URL/heuristic)
│   ├── seo/ schema/ media/ wordpress/   # SEO, JSON-LD, media, REST client + publisher
│   ├── pipeline.py cli.py config.py
├── config/
│   ├── site.yaml         # site config + ROUTING by URL
│   ├── acf.yaml          # default ACF mapping ("flat" profile)
│   └── acf/              # profiles by section: island.yaml, wildlife_single.yaml, informative.yaml
├── templates/            # page-type profiles (schema, categories, parent)
├── content/             # document STORE (the "memory"): islands, wildlife, cruises, informative, uploads/
├── wordpress-acf/        # ACF group definitions (JSON to import into WP)
├── wordpress-plugin/     # Elementor plugin (widgets that render the ACF fields)
├── wordpress-theme/      # theme
├── dashboard/            # alternative dashboard in Streamlit (option B to n8n)
├── docs/                 # documentation (includes this manual)
├── samples/ assets/ tests/
└── .github/workflows/publish.yml   # the GitHub Actions workflow that runs the engine
```

## 7. The engine: flow and commands

**Internal flow:**
```
ingest → Document → detect page type → SEO → compose → resolve media
       → schema.org JSON-LD → map to ACF → publish (REST, `acf` field)
```
It is **deterministic** (it does not use AI at runtime). The output is **ACF fields**,
never Gutenberg blocks; `post_content` stays empty.

**Commands (CLI `wp-publish`):**

| Command | What it does |
|---|---|
| `wp-publish check` | Verifies WordPress credentials and detects the SEO plugin |
| `wp-publish templates` | Lists the available page types |
| `wp-publish preview <doc>` | Assembles the page **without publishing** (local test, no network) |
| `wp-publish publish <doc>` | Publishes (defaults to **draft**) |
| `wp-publish gdrive <id>` | Ingests from Google Drive (restricted by account) |

## 8. Routing by URL → page type, parent, and ACF group

The system does **not** ask you to choose the page type: it decides it based on the
**first segment of the document's canonical URL** (`config/site.yaml → routing.url_sections`):

| URL section | Page type | Parent | ACF group |
|---|---|---|---|
| `/planning/…` | `informative` | (no parent) | Informative Page |
| `/wildlife/…` | `wildlife_single` | Wildlife (9657) | Wildlife Single |
| `/islands/…` | `destination` | Islands (10263) | Island Guide |
| `/cruises/…`, `/galapagos-cruises/…` | `informative` | (no parent) | Informative Page (temporary) |

Each type carries its **parent** and its **ACF group**. To add a new section, add the
segment here along with its `templates/<type>.yaml` + `config/acf/<profile>.yaml`.

## 9. The n8n workflow under the hood

Workflow **“Publicar página Galápagos (borrador test)”** — 6 chained nodes:

1. **Form: subir documento** — form with 2 fields: *Documento*
   (`.html/.htm/.docx`) and optional *Schema JSON* (`.json`).
2. **Preparar archivo** (Code) — reads the actual bytes of the binary with
   `getBinaryDataBuffer`, converts them to base64, and builds the path
   `content/uploads/<timestamp>-<name>`. If there is HTML + `.json`, it names them with the
   **same base name** so the engine detects the schema as a *sidecar*.
3. **Guardar documento** (HTTP PUT) — commits the document to the repo (GitHub Contents API).
4. **¿Hay schema?** (IF) — if a `.json` was attached, it goes to save it; if not, it skips to publish.
5. **Guardar schema** (HTTP PUT) — commits the `.json` (only if it exists).
6. **Publicar** (HTTP POST) — triggers `publish.yml` with
   `mode=publish-draft`, `test_mode=true`, `update_existing=true`.

**Branch:** the commits and the dispatch go to `claude/wordpress-automation-system-wlnxlp`
(the same one `publish.yml` uses). If it is merged into `main`, update `branch`/`ref`
in the HTTP nodes.

### 9.1 Initial configuration (one time only — technician)

The workflow (`n8n-galapagos-v3-FINAL.json`) is imported into n8n once and left
active. It only needs **one GitHub credential**:

1. n8n → **Credentials → Add credential → Header Auth**.
2. **Header Name:** `Authorization` · **Header Value:** `Bearer <TOKEN>`.
3. The GitHub token needs **Contents: write** + **Actions: write** on
   `voyagerscontent/galapagos-wp-publisher-`.
4. Assign that credential to the **3 HTTP nodes**: *Guardar documento*,
   *Guardar schema*, and *Publicar*.
5. **Save** → **Publish** (leaves the form at its Production URL, fixed, to
   share with marketing).

> ⚠️ The token lives **only** inside the n8n credential — never in the workflow JSON
> nor in plain text.

### 9.1 The GitHub Actions workflow (`publish.yml`)

Main inputs:

| Input | Purpose |
|---|---|
| `file` | Path of the document in the repo (or `ALL_ISLANDS` / `ALL_WILDLIFE`) |
| `mode` | `preview` · `publish-draft` · `publish-live` |
| `page_type` | Force type (empty = auto by URL) |
| `slug` | Force slug (empty = derive from the doc) |
| `test_mode` | `true` adds `-test` to the slug |
| `media` | Media strategy (`placeholder`…) |
| `update_existing` | Overwrite a post with the same slug (off = rejects, safe) |
| `only_fields` | Surgical: write ONLY those ACF fields (never creates a page) |

## 10. ACF groups and when to re-import

- The definitions live in `wordpress-acf/*.json` (one per page type).
- The engine writes **by field name** via REST; WordPress and Elementor **read by
  name**. Manual saving in the WordPress admin uses the field's **key** (`field_…`).
- **Every ACF field key must start with `field_`.** If a group has malformed keys,
  the manual saving of the **repeaters** breaks (the engine keeps publishing because
  it goes by name, which **hides** the problem).
- When a group is fixed or changed (`wordpress-acf/<group>.json`), it must be
  **re-imported** into WordPress: **ACF → Tools → Import Field Groups**. Since the
  group key does not change, it updates the existing one. Saved data goes by name, so
  **it is not lost**.
- After updating the Elementor plugin, run **“Regenerate CSS & Data”**.

## 11. The Elementor plugin (widgets)

`wordpress-plugin/island-elementor-widgets.php` — widgets that **render** the ACF
fields (the engine does not emit design HTML): Hero, Feature Sections (zig-zag cards +
infographic + icon badge with recoloring), FAQs (emits `FAQPage` JSON-LD),
Quick Facts, Visitor Sites, Wildlife, CTA, Related Links, and the Schema widget
that prints the `seo_schema` field per page. The dimensional controls have a
responsive version.

### Related Links ("Explore More")

This one is deliberately **not** styleable per page: typography, colours, the
accent rule under every group heading, the chevrons and the column count are
fixed in the widget's CSS so that every instance looks identical across the
site. Only the outer container (background, padding, max width) stays
adjustable. Columns follow the content — one per link group, plus the contact
card when it is shown.

Its links come from one of two places, chosen with **"Where the links come
from"**:

- **Automatic** (default) — reads the page's `related_links` ACF field, and
  falls back to the widget's own **Manual links** list when the page has none.
  That is what hand-built parent pages (which carry no ACF group) need.
- **ACF field only** / **Manual list only** — force one source.

A row with no group renders as an untitled column; no heading is ever invented,
so group names are always chosen by hand.

**Careful with `{{WRAPPER}}`.** Elementor resolves that token only inside a
control's `selectors`. In markup a widget *prints* it is just text, so a
`<style>` block echoed with `{{WRAPPER}} .foo{…}` is invalid CSS and the
browser drops it — the page then looks styled only because the controls'
defaults generate real CSS behind the scenes, and it loses its styling the
moment those controls are removed. This widget therefore prints its stylesheet
once per request, scoped to its own `.irl-*` classes and with no token. Most of
the other widgets in this file still echo `{{WRAPPER}}` blocks that never
apply; they get their look from control defaults instead.

When there is only ONE column (for example just the “Need help?” contact block, because
the page has no related links), that block is centred on the page and its text and
button are centred too.

### Expert Pages (the pages an expert has written)

Drop **Island Expert Pages** on an expert's profile page and it lists, as cards,
every published page that names that person in its ACF `author` byline. Nothing
is maintained by hand: a page appears as soon as it is published with the byline.

- **Who it looks for.** The name comes from the profile page's own title (override
  it in the widget if the title carries more than the name). Only the part of the
  byline before the first comma is compared, ignoring case, accents and a leading
  "By", so `Juan Magallanes, Naturalist Expert Contributor` counts for *Juan
  Magallanes*, while *María Magallanes* or *Juan Magallanes Jr* do not.
- **What a card shows.** The page's featured image (else its `hero_image`), its
  title and up to **three lines** of description. The description is the first of:
  the page excerpt, the Yoast / Rank Math meta description, `geo_answer`,
  `hero_subtitle`, `intro`.
- **Elementor-only pages.** A page with no ACF fields is also found when it has an Elementor
  **Author Box** (Source: Custom) whose **Name** is typed by hand (e.g. “By Juan Magallanes,
  Galapagos Travel Advisor”) — the part before the comma is compared, same as the ACF byline.
  An Author Box whose Name is a dynamic tag is ignored. Matches are cached for a few hours and
  refresh whenever any page is saved.
- **Limits.** It reads the `author` field, which only the Informative, Island and
  Wildlife groups carry. A page type without that field (cruises, itineraries)
  will not appear until the field is added to its group. The profile page never
  lists itself.
- **Design.** Fixed, like Related Links: only the container (background, padding,
  max width) is adjustable. Controls cover the heading (`{name}` is replaced), which
  post types to look in, the order and a maximum.

The cards show up in a **carousel**: three at a time on desktop, two on tablet,
one on mobile. If there are more pages than fit, arrows appear outside the cards,
centred on the widget (and you can swipe on a phone). Cards can show a small date at the bottom (switch **Show date on cards**, **off** by default) — “Updated Oct 12, 2026”, or the
publish date (**Date**); it uses the site's own date format.
The card **title** is the page's ACF `hero_title` (page title if empty). The card
**text** is the Quick Answer (`geo_answer`) with tags removed, cut to 3 lines with
“…”; only if it is empty does it fall back to: Excerpt → `hero_subtitle` → `intro`
→ Yoast description → Rank Math description.

### Long text: “Read more” bar (all screen sizes)

Feature bands/rows, "where to see" items and wildlife features clip long text and
show a **Read more ↓** bar (thin line, label on the left, arrow on the right).
Click it to open the full text (it becomes **Read less ↑**); closing it scrolls
back to the start of that section if you had scrolled past it. Short text gets no
bar — and neither does text that is only slightly longer than the clamp (the bar
appears only when MORE than 6 lines would be hidden). There is no hover-to-expand any more — it caused the page to jump when a
section collapsed under the mouse. The switch is labelled “Clamp long text (Read
more button)” and the “open height” setting is no longer used (text always opens
in full). Links inside that text take the section's text colour (cream on a brown
band) with an underline, so they never disappear on a dark background.

### Tables inside feature sections (wrap, no sideways scroll)

Tables in feature rows/bands now **wrap their text and always fit the section** —
no horizontal scrollbar. The Table group has a switch **“Wrap table text (no
horizontal scroll)”** (on by default); switch it off to bring back the old
behaviour (minimum width, scrolls sideways). A **one-column table** (each cell =
a title line, then a paragraph) reads as a bold title plus normal text; the header
cell uses the same pattern in the header colour (title line bold, same size as the text). Its text is 15px with 1.7 line
height — the same as the section's body text — and in feature rows it follows the
“Body” typography setting. If a one-column table has no header row (`<thead>`), its
first row is shown as the brown header automatically (colours follow the “Header
background / text” controls).

### Back-to-top button

Long pages get a round grey **back-to-top** button (white chevron, bottom-right).
It appears only when the page is longer than about 3 screens and the visitor has
scrolled down a little; one click scrolls smoothly to the top (instant if the
visitor prefers reduced motion). It is not shown in the Elementor editor/preview or
when printing. Developers: turn it off with
`add_filter('island_back_to_top', '__return_false');` or change the length
threshold (in screens, default 3) with the `island_back_to_top_screens` filter.

### Table style for any widget: class `island-table`

Tables outside the Feature Sections widget (for example inside a Text Editor) have no design of
their own. Add the CSS class **`island-table`** to that widget or container (Advanced → CSS
Classes), or write `<table class="island-table">`, and every table inside gets the site design:
brown header (a `<thead>` or a first row of `<th>`), striped rows, bold brown first column,
rounded corners, and text that wraps (no sideways scroll). A one-column table (each cell: a
title line, then a paragraph) shows the title in bold and, if it has no `<thead>`, its first row as the
brown header — exactly like the “Ages” tables. Tables inside Feature Sections already
have this look and need no class.

#### On a brown section: class `table-in-brown`

**`table-in-brown`** is the same design as `island-table` (cream body, striped rows, rounded corners) for
use on a **brown section**, with two differences: a **sand header** with brown text (a brown header would melt
into the brown background) and a thin cream outline. All three table classes also force a normal font,
15px size and comfortable line height on header and cells, so theme heading styles can't squash them. Use it the same way:
Advanced → CSS Classes, or `<table class="table-in-brown">`.

### Bottom fade of the scrolling feature list

The widgets that scroll their feature list inside a fixed height (Feature Sections with “Match
neighbour column”, and Wildlife · At a Glance + Features) show a soft gradient at the bottom edge
as a “there is more” hint. Switch **Show bottom fade** off (next to the Fade color) for no gradient.
The “Read more” text fade inside each card is separate and stays.

#### On a cream section: class `table-in-cream`

For a **cream page or section** use **`table-in-cream`**: a **white table** (more contrast against the cream)
with the brown header, soft cream stripes and a light shadow. Same use as the other two: Advanced → CSS
Classes, or `<table class="table-in-cream">`. Inside a Feature Sections table frame (the white card / rounded box the widget already draws)
the classes add **no second outline, margin or shadow**. Summary — `island-table`: default; `table-in-brown`: same look
for brown sections; `table-in-cream`: white table for cream sections.

### Island Author Box (the hero author box as a template)

One widget for the box under the page title — round photo, “By Juan Magallanes, Galapagos Travel
Advisor” and the review note — so it can live in a single template and work on every page.
Put it in the template once; it fills itself from each page:

- **Name and role** — the page's ACF **Author / Byline** (`author`). The part before the comma is the name.
- **Photo and link** — the matching **expert profile page** (the page typed “Experts” whose title is that
  name; if none is marked “Experts”, any published page titled exactly with the name is used): its **about image**, else its featured image; the link goes to that profile. The ACF **Author URL**
  (`author_url`), if filled, wins as the link.
- **Review note** — the ACF **Review Note** (`review_note`, a new text field you add by hand next to Author /
  Byline). If the page has none, the widget's **Default review note** is used.
- No byline on the page → the widget prints nothing (the editor shows a dashed hint). Pages that are pure
  Elementor keep Elementor's own Author Box; this widget is for pages with the ACF fields.

Style controls: name/note colours and typography, photo size and ring colour, gap, box position (left / centre /
right), max width (box shrinks to its content, default 760px), background and padding. To sit at the bottom of a
hero, put the widget last in the hero container and set that container's Justify Content to “Space Between”. The widget also has optional manual overrides (byline, note, link, photo).

#### Keep Elementor's own Author Box: the “Island Author” dynamic tags

If you prefer Elementor's **Author Box** (Source: Custom), nothing changes for the pages that already use it
with typed-in values. For a **template**, fill its fields with these dynamic tags instead (click the database
icon of the field → group **Island Author**):

| Author Box field | Tag | Value |
|---|---|---|
| Name | **Author name (By Name, Role)** | “By ” + the page's ACF `author` (prefix editable) |
| Profile Picture | **Author photo (expert profile)** | the matching expert profile's about image / featured image |
| Link | **Author link (Author URL / expert profile)** | ACF `author_url`, else the expert profile page |
| Biography | **Review note** | ACF `review_note`, else the default text you type in the tag |

Needs Elementor Pro (dynamic tags). A page whose `author` field is empty shows an empty box, so use the tags only
in a template for pages that have that field. **Island Author Box** (above) remains available as an alternative.

### Sources (Island Sources & Links)

Prints the ACF **`sources`** list (label + URL) as one tidy list: each source is **its label, linked to its URL** (a
row with no URL is plain text). It no longer prints the “Explore More” block — use **Island Related Links** for that.
If a label arrives from a document with junk glued on (“…Image: photo.jpg (400×300px…)Alt: …”, a Markdown link,
a stray “[”), the widget shows only the readable label and takes the URL from the row or from the Markdown link.
The list is **collapsible** by default (switch **Collapsible list**): a thin bar with the heading, the number of
sources and an arrow; click to open. **Start open** keeps it open. Style controls: heading, link and text colours;
columns (1–3, always 1 on phones).

### Byline link (the author's name links to their profile)

Drop **Island Byline** (category “Galápagos Site Widgets”) where the “By Juan
Magallanes, Naturalist Expert Contributor” line goes. It reads the page's ACF
`author` field (or the **Text** box if filled) and turns the **“By Name”** part (everything before the comma) into a
link to that expert's profile page — the published page whose *Galápagos Page
Type* is **Experts** and whose title is the same name (case and accents ignored).
The role / review note after the comma stays plain text. Put the link in the ACF field
**Author URL** (`author_url`, next to Author / Byline); if it is filled it wins, otherwise
the name links to the matching expert profile as described above. The widget also has a
“Link” box to set it by hand.

**Already have the byline in another widget** (a Heading, Text Editor…)? You don't have to
replace it, and there is nothing to set up: any widget on the page that prints the byline
(for example a Heading showing the ACF `author` field after “By ”) automatically gets “By
Juan Magallanes” turned into the link (Author URL, or the matching expert profile); the
role after the comma stays plain text. The link keeps the text's own font, size, colour
and italic — nothing changes visually except an underline on hover. If a widget shows only the name, give it the CSS
class `island-byline` (Advanced → CSS Classes). No matching profile, or
you are already on that profile → plain text, no link. Outside Elementor the same
thing is available as the shortcode `[island_byline]`.

## 12. The 3 document formats under the hood (technical contract)

How the engine reads each format. The correct reader is chosen by the extension and
by content detection (`looks_like_stage8`).

### 12.1 Production HTML (+ `schema.json` sidecar)
Reader: `ingest/html_reader.py`. It is the **canonical path**.
- Segments the body by each **`<h2>`** (supports `<section>` layout or flat).
- Block-to-field mapping:
  - `.answer-box` → `geo_answer` (GEO/AIO answer)
  - `.dateline` / `.byline` / JSON-LD author → `author`
  - `.conversion-band` / `.cta-primary` / `.cta-close` → CTA (primaries first); `.lead-magnet` → secondary CTA
  - `#related` / `h2#related` → `related_links` (consumes only the `<ul>/<ol>` that follows it)
  - `footer.sources` / `p.sources` → `sources`
  - `<script type="application/ld+json">` **or** `<name>.schema.json` sidecar → `seo_schema`
- Captures `metadata["slug"]` (last segment of the URL) and `metadata["url_section"]`
  (first segment → used by the routing, see §8).
- The body of each section is kept **as-is** as HTML.

### 12.2 CMS Stage 8 (`.docx` with content)
Reader: `ingest/cms.py` (Stage 8 adapter) via `ingest/docx_reader.py`.
- Recognized by the **`CMS Stage N`** banner + an AIO marker, in two variants:
  **`[AIO BLOCK …]`** (bracketed) or **`AIO SUMMARY BLOCK (…): <answer>`** (inline).
- Supports the large **"PUBLISHER HEADER BLOCK"** header (JSON-LD, internal links,
  entity rules) — all of it is skipped up to the real title (H1).
- **Section titles are bold** (no heading styles); they are detected from the
  `.docx` bold run, so long titles are segmented too.
- `[AIO BLOCK 1 — speakable]` or `AIO SUMMARY BLOCK (…):` → `geo_answer`.
- Discards the production markers (`[AIO/PHOTO/INFOGRAPHIC …]`).
- **Tables** are kept as HTML.
- **FAQs** → `faqs` repeater.
- **Sources & Citations** → `sources` (discards internal `.md`/`.csv` paths).
- Recovers **Meta Title / Meta Description** from the document.
- Cuts the pipeline's trailing junk (`VERIFY Summary`, `WF5-7`, etc.).
- Captures `url_section` from the `Slug:` line.

### 12.3 Editor Copy (green, `.docx`)
- **It is NOT content** — it is instructions for the editor (highlighted in green).
- **It is not ingested.** If you receive this file, it is not uploaded to the system.

## 13. Troubleshooting

### 13.1 Operational (what to check when something fails)

| Symptom | Cause | What to do |
|---|---|---|
| Actions fails with an authentication error | `WP_USERNAME`/`WP_APP_PASSWORD` wrong or expired | Check the repo secrets; regenerate the Application Password |
| “A page with slug … already exists. Refusing to overwrite” | The slug already exists and `update_existing` is off | Publish with `update_existing: true` (or change the slug) |
| The page publishes without a parent / the ACF group does not appear | The parent (slug) does not exist or `gp_page_type` does not match | Create the parent page with that slug; check the URL routing (§8) |
| n8n: error on an HTTP node (401/403) | The credential is missing or the token lacks permissions | Credential on the 3 nodes; token with Contents + Actions (§9.1) |
| A repeater does not save in the admin | ACF keys without the `field_` prefix | Re-import the corrected group (§10) |
| Duplicate schema on the page | The SEO plugin also emits JSON-LD | Turn off the plugin's schema (§14) |

### 13.2 History of resolved bugs

| Problem | Cause | Applied fix |
|---|---|---|
| n8n: `content is not valid Base64` | The binary in *filesystem* mode returned `filesystem-v2`, not the base64 | Read the bytes with `getBinaryDataBuffer` and convert to base64 |
| n8n: `Required input 'file' not provided` | The dispatch read `$json.path` (no longer exists after the commit) | Reference `$('Preparar archivo').first().json.mainPath` |
| HTML with separate schema did not apply | The workflow uploaded a single file | Support for 2 files with the same base name (sidecar) |
| Double ACF group on a page | Outdated parent and `gp_page_type` attracted 2 groups | `parent=0` when there is no parent + always stamp `gp_page_type` |
| Feature Sections images were deleted when republishing | The row match failed with WYSIWYG normalized by WordPress | Normalize (strip tags/entities) before comparing; preserve unmanaged media |
| Feature Sections saved nothing in the admin (islands) | The 99 keys of the islands group had no `field_` prefix | Re-key to `field_isl_…` + re-import the group |
| A CMS Stage 8 doc did not segment (everything fell into hero/intro) | Variant with `AIO SUMMARY BLOCK` (no brackets) + bold titles → did not enter the Stage 8 adapter | Tolerant detection (accepts `AIO SUMMARY BLOCK`), "PUBLISHER HEADER BLOCK" header skipped, and bold-based headings |

## 14. SEO: plugin and schema

The system pre-fills part of the SEO, but **configuring the SEO plugin and setting
`noindex` are the team's responsibility** (see also §4).

### 14.1 What the engine does (automatic)

- It writes the **meta title**, **meta description** and **focus keyword** to the
  keys of the plugin set in `config/site.yaml` → `seo.seo_plugin` (pinned to
  **`rankmath`**: `rank_math_title`, `rank_math_description`,
  `rank_math_focus_keyword`). With `auto` it detects the active plugin (Yoast first).
- It generates the page's **schema JSON-LD** (from the document) and delivers it in
  the ACF field `seo_schema`, rendered by the **“Island Schema”** widget.
- It does **not** set `noindex` — that is manual.

### 14.2 Recommended plugin: Rank Math

**Rank Math** is used for its SEO/GEO features in the free tier (multiple keywords,
AIO signals, redirects). Yoast also works, but you get less out of it.

**Golden rules:**

1. **Only one plugin active.** Never Yoast and Rank Math at the same time: they
   duplicate meta and schema, and the engine **detects Yoast first** (it would
   write to the wrong plugin).
2. **Turn off the plugin's schema.** The system already emits its own JSON-LD
   (“Island Schema” widget). If the plugin emits it too, you get **duplicate
   schema** — bad for GEO/AIO and for rich results.

### 14.3 Configure Rank Math (once)

1. Leave **only Rank Math active** (Yoast deactivated/deleted after migrating, §14.4).
2. **Rank Math → Titles & Meta →** for each content type (Posts, Pages and the
   islands/wildlife CPTs) → **Schema Type = None / Off**.
3. Check that **Sitemaps** and **Breadcrumbs** don't duplicate what the theme
   already does.

### 14.4 Migrate from Yoast to Rank Math

1. **Back up** the database (or export) before starting.
2. Keep **Yoast active**; install and activate **Rank Math** (both active **only**
   during the import — do not publish/republish in that window).
3. **Rank Math → Status & Tools → Import & Export → Import from Yoast**: import
   **Titles & Meta**, **focus keywords**, **robots meta (`noindex`)** and
   **redirects**.
4. Verify on 2–3 pages that the meta title/description, focus keyword and `noindex`
   came over.
5. **Deactivate and delete Yoast.** Only Rank Math stays active.
6. Apply §14.3 (turn off the plugin's schema).

> The engine needs no changes: on the next publish it detects Rank Math and writes
> the meta to `rank_math_*` automatically.

### 14.5 Per-page SEO checklist (before publishing)

- [ ] `noindex` set while the page is `-test` / not approved (§4).
- [ ] Meta title, meta description and keyword reviewed and completed.
- [ ] **Only one** schema block on the page (the system's, not the plugin's).

---

## References

Complementary technical documentation in `docs/` (English) and its translation in
`docs/es/` (Spanish): `SETUP`, `WORKFLOW`, `PUBLISH_VIA_GITHUB`, `AUTHORING_GUIDE`,
`ACF`, `TEMPLATES`, `WILDLIFE_TEMPLATE`, `NEW_SITE`, `TROUBLESHOOTING`.
