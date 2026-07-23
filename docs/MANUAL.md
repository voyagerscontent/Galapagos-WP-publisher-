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
- Recognized by the **`CMS Stage N`** banner + **`[AIO BLOCK]`** markers.
- `[AIO BLOCK 1 — speakable]` → `geo_answer`.
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
