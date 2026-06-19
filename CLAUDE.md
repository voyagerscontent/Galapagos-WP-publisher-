# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this is

A **site-agnostic WordPress publishing engine** (`src/wp_publisher/`) that turns
a document (Word, Markdown, plain text, or Google Drive) into a formatted,
SEO‑optimized, schema‑rich WordPress page using per‑page‑type templates.

**This repository is configured for one site: https://www.galapagosislands.travel.**
Each site lives in its own repository — same engine, different `config/site.yaml`,
`templates/`, and `.env`. Keep the engine free of site-specific values; anything
that names a particular site belongs in config, not in `src/`.

## Architecture (data flow)

```
ingest → Document → detect page type → apply template
       → SEO → render Gutenberg blocks → resolve media → schema.org JSON-LD
       → publish (WordPress REST API)
```

Key modules under `src/wp_publisher/`:

- `models.py` — the normalized `Document` and final `RenderedPage`. Everything
  downstream of ingestion sees only these, never the original file format.
- `ingest/` — format readers (`docx`, `markdown`, `text`, `gdrive`). Each
  returns a `Document`.
- `parse/pagetype.py` — picks the template (declared `type:` first, then
  heuristics).
- `rendering/` — `template.py` (YAML page templates), `blocks.py` (Gutenberg
  block builders), `renderer.py` (the layout engine).
- `seo/`, `schema/`, `media/` — SEO fields, JSON‑LD, image resolution.
- `wordpress/` — REST `client.py` (retries, App Password auth) and
  `publisher.py` (payload assembly, idempotent on slug).
- `pipeline.py` — orchestration; `cli.py` — the `wp-publish` command.
- `config.py` — merges `config/site.yaml` with `.env`. The only place site
  identity enters the engine, and only via config.

Templates are **data, not code**: `templates/*.yaml`. Adding a page type =
adding a YAML file. See `docs/TEMPLATES.md`. Standing up a new site =
`docs/NEW_SITE.md`.

## Conventions

- Section titles map to canonical slot slugs via `utils.py`
  (`SECTION_SYNONYMS`, `_KEYWORD_RULES`). Extend those when a new heading
  phrasing should map to an existing slot.
- H3+ headings nest inside their parent section (this is how FAQ Q&A pairs are
  formed). Don't "flatten" them.
- Content is emitted as **native Gutenberg blocks** so it respects the theme —
  never a raw Classic HTML blob.
- schema.org JSON‑LD is embedded as a `wp:html` block so it ships regardless of
  SEO plugin; Yoast/RankMath meta is also written when detected.
- Do not hardcode a site URL, org name, or account in `src/`. Read it from
  `config/site.yaml` or the environment.

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
