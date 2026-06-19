# Standing up a new site

The publishing engine (`src/wp_publisher/`) is **site-agnostic**. A site is just
a repository that carries its own configuration, templates, and secrets. This
repo is the first such site → **https://www.galapagosislands.travel**.

## What is site-specific vs. shared

| Site-specific (changes per repo) | Shared engine (copied as‑is) |
| -------------------------------- | ---------------------------- |
| `config/site.yaml`               | `src/wp_publisher/**`         |
| `templates/*.yaml`               | `pyproject.toml` deps         |
| `.env` (never committed)         | `tests/` (engine tests)       |
| `samples/` (optional examples)   | the `wp-publish` CLI          |
| `.claude/skills/` description    |                              |

## Steps to create another site (e.g. a Peru site)

1. **Create the repo** from this one (template/fork or copy the tree). Keep
   `src/wp_publisher/` unchanged.

2. **Edit `config/site.yaml`** — organization name/URL/logo/socials, SEO
   `title_suffix`, locale (`currency`, `country`), and the default media
   strategy.

3. **Point `.env` at the new WordPress site:**
   ```ini
   WP_BASE_URL=https://www.example.travel
   WP_USERNAME=...
   WP_APP_PASSWORD=...
   GDRIVE_ALLOWED_ACCOUNT=...      # optional Drive lock
   ```
   See [SETUP.md](SETUP.md) for the App Password steps.

4. **Tailor `templates/`** to that site's sections. Remove templates you don't
   need, add new ones (e.g. `expedition.yaml`, `lodge.yaml`). Templates are pure
   YAML — see [TEMPLATES.md](TEMPLATES.md).

5. **(Optional) Replace `samples/`** with example docs for that site and update
   the skill description in `.claude/skills/publish-to-wordpress/SKILL.md`.

6. **Verify:**
   ```bash
   pip install -e ".[dev]"
   wp-publish templates
   wp-publish preview samples/<a-doc>.md     # no network
   wp-publish check                          # confirms the new WP site
   ```

## Keeping the engine in sync across sites

For now the engine is **vendored** (copied into each site repo). When several
sites are live and you want one source of truth, promote `src/wp_publisher/` to
a standalone package (its own repo / private index) and have each site depend on
it via `pyproject.toml` instead of vendoring. The code is already structured for
this: the engine never imports anything site-specific — it only reads
`config/site.yaml` and the environment.
