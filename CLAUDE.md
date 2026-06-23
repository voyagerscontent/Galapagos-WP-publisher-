# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this is

A **site-agnostic WordPress publishing engine** (`src/wp_publisher/`) that turns
a document (Word, Markdown, plain text, or Google Drive) into a
SEO‑optimized, schema‑rich WordPress page by **populating ACF fields** over the
REST API. The WordPress theme renders the ACF fields, so design/UX lives on the
WP side — the engine does **not** emit Gutenberg blocks.

**This repository is configured for one site: https://www.galapagosislands.travel.**
Each site lives in its own repository — same engine, different `config/site.yaml`,
`config/acf.yaml`, `templates/`, and `.env`. Keep the engine free of
site-specific values; anything that names a particular site belongs in config.

## Architecture (data flow)

```
ingest → Document → detect page-type profile (schema/categories)
       → SEO → compose into structured components → resolve media
       → schema.org JSON-LD → map components to ACF → publish (REST `acf`)
```

Key modules under `src/wp_publisher/`:

- `models.py` — normalized `Document`, `Component`-free `RenderedPage` carrying
  the `acf` payload. (`content/model.py` holds the `Component` model.)
- `ingest/` — format readers (`docx`, `markdown`, `text`, `gdrive`); each
  returns a `Document` and populates `Document.raw_body`.
- `content/` — the new core: `compose.py` (Document → components, deterministic
  rules + a bespoke `:::` directive path), `richtext.py` (blocks/Markdown →
  semantic HTML for WYSIWYG fields), `directives.py` (the `:::` parser).
- `acf/` — `config.py` loads `config/acf.yaml`; `mapper.py` turns components
  into the `acf` REST payload (flexible-content rows).
- `parse/pagetype.py` — picks the page-type profile (declared `type:`, then
  heuristics; routes generic `wildlife` by search volume).
- `rendering/template.py` — page-type **profiles** only (schema type, default
  categories, focus-keyword source, search-volume tier hints). No layout/render.
- `seo/`, `schema/`, `media/`, `wordpress/`, `pipeline.py`, `cli.py`, `config.py`.

Mapping to ACF is **config, not code**: `config/acf.yaml` (your field names).
The component model is fixed; the mapping targets any ACF schema. Reference
field group: `wordpress-acf/page-builder.acf.json`. See `docs/ACF.md`.

## Conventions

- Output is **ACF fields**, never Gutenberg. `post_content` stays empty.
- Composition is **deterministic**; a doc with `:::` directives takes the
  bespoke path (uploader-designed one-off layout).
- WYSIWYG/text content is plain semantic HTML (`<p>`, `<ul>`, `<h2>`), not block
  markup. ACF image fields store attachment IDs (`image_as` in `config/acf.yaml`).
- schema.org JSON‑LD is delivered as an ACF field (`seo_schema`); Yoast/RankMath
  meta is also written when detected.
- Section titles map to canonical slugs via `utils.py` (`SECTION_SYNONYMS`,
  `_KEYWORD_RULES`); H3+ headings nest inside their parent section.
- Do not hardcode a site URL, org name, or account in `src/`. Read it from
  `config/*.yaml` or the environment.

## Working here

```bash
source .venv/bin/activate            # or create: python3 -m venv .venv && pip install -e ".[dev]"
pytest                               # run tests
ruff check src tests                 # lint
wp-publish preview samples/galapagos-cruise.md   # smoke test (no network)
```

After changing ingestion, rendering, or templates, run `pytest` and re‑preview
the sample to confirm structure/ordering still hold.

## Safety

- Publishing defaults to **draft**. Never switch a publish to `--status
  publish` without explicit user intent.
- Never commit `.env`, `credentials.json`, or `token.json` (git‑ignored).
- Google Drive ingestion is restricted to `GDRIVE_ALLOWED_ACCOUNT` when set
  (this repo's `.env.example` sets it to `businessops@latintrails.com`).
