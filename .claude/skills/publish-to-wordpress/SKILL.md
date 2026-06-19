---
name: publish-to-wordpress
description: >
  Publish a document to this repository's WordPress site
  (galapagosislands.travel). Use when the user wants to turn a doc (Word,
  Markdown, text, or a Google Drive file) into a formatted, SEO-optimized,
  schema-rich WordPress page — e.g. "publish this tour doc", "create a cruise
  page from this", "preview this as a destination page", or "push my Galapagos
  itinerary to WordPress".
---

# Publish to WordPress

This repository uses the site-agnostic **WP Publisher** engine to convert a
document into a fully formatted WordPress page using per-page-type templates,
and publishes to the site configured in `.env`
(**https://www.galapagosislands.travel**). Drive it through the `wp-publish`
CLI.

## Setup check (first time in a session)

```bash
source .venv/bin/activate 2>/dev/null || (python3 -m venv .venv && source .venv/bin/activate && pip install -e ".[dev]")
wp-publish templates        # confirm templates load
```

Publishing needs a configured `.env` (see `docs/SETUP.md`). If `wp-publish
check` fails, the WordPress credentials aren't set — tell the user and point
them to `docs/SETUP.md`; you can still `preview` without credentials.

## Workflow

1. **Locate the doc.**
   - Local file: use its path directly.
   - Google Doc: `wp-publish gdrive "<name>" --preview` (account is locked to
     businessops@latintrails.com).
   - Content pasted into chat: save it to `samples/<slug>.md` (add YAML
     frontmatter with at least `type:` and `title:`), then use that path.

2. **Preview first — always.**
   ```bash
   wp-publish preview <file> --media placeholder
   ```
   Read the printed summary and the warnings. Report to the user: detected page
   type, title, slug, SEO title/meta, and any warnings (missing required
   sections, short meta, focus-keyword gaps).

3. **Resolve warnings** by editing the source doc (add the missing section, a
   focus keyword, etc.), then preview again. Do not silently ignore required-
   section warnings.

4. **Confirm intent before going live.** Default to `--status draft`. Only use
   `--status publish` when the user explicitly asks to publish live, and tell
   them it will be public.
   ```bash
   wp-publish publish <file> --type <type> --media <library|placeholder> --status draft
   ```

5. **Report the result**: post ID, public URL, and the edit link.

## Choosing the page type

Let auto-detection work, but override with `--type` when the user states the
section. Valid types: `blog_post`, `destination`, `tour`, `cruise`
(run `wp-publish templates` for the live list).

## Choosing media

- `--media placeholder` (default): inserts blocks for a human to fill. Safest.
- `--media library`: reuse images already in the WordPress Media Library.
  Use when the user wants images filled automatically.

## Guardrails

- Never publish `--status publish` without explicit user confirmation.
- Never commit `.env`, `credentials.json`, or `token.json`.
- If a doc lacks a metadata header, add a minimal one (`type`, `title`) rather
  than guessing silently — and tell the user what you set.
