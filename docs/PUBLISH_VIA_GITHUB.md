# Publishing from GitHub.com (no local machine)

You can run the whole pipeline from your browser using **GitHub Actions** — no
terminal, no local install. You upload a doc to the repo, then click a button to
publish it. Credentials are stored once as encrypted repository secrets.

> **Publish in 3 steps (once set up):**
> 1. **Add file → Upload files** into the `content/` folder → Commit.
> 2. **Actions → Publish to WordPress → Run workflow** → set `file` to
>    `content/your-doc.md`, pick a `mode`.
> 3. Use `preview` → `publish-draft` → `publish-live`. Review the draft in
>    `wp-admin` between steps.
>
> If a run fails on authentication, see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## One-time setup: add your WordPress secrets

1. On **galapagosislands.travel** `wp-admin`, create an Application Password:
   **Users → Profile → Application Passwords** → name it `wp-publisher` → **Add
   New** → copy the value (looks like `abcd EFGH 1234 wxyz 5678 90ab`).

2. In **this GitHub repo**, go to
   **Settings → Secrets and variables → Actions → New repository secret** and
   add three secrets:

   | Name              | Value                                   |
   | ----------------- | --------------------------------------- |
   | `WP_BASE_URL`     | `https://www.galapagosislands.travel`   |
   | `WP_USERNAME`     | your WordPress username                  |
   | `WP_APP_PASSWORD` | the Application Password from step 1      |

   These are encrypted and never visible in logs.

## Publishing a document

1. **Add the doc to the repo.** Open the `content/` folder → **Add file →
   Upload files** → drop in your `.docx`, `.md`, or `.txt` → **Commit changes**.
   (Add a short metadata header — see `content/README.md`.)

2. **Run the workflow.** Go to the **Actions** tab → **Publish to WordPress** →
   **Run workflow**, and fill in:

   - **file** — e.g. `content/my-cruise.md`
   - **mode** — start with `preview`, then `publish-draft`, finally
     `publish-live`
   - **page_type** — leave blank to auto-detect, or force `cruise` / `tour` /
     `destination` / `blog_post`
   - **media** — `placeholder` (image-needed markers) or `library` (reuse
     images already in your Media Library)

3. **Check the result.** Open the running job:
   - `preview` mode produces a downloadable **preview-output** artifact
     (the generated HTML + schema) and prints a summary — nothing is published.
   - `publish-draft` / `publish-live` print the post's URL and a WordPress
     **edit link** in the log.

## Recommended flow

```
upload doc  →  run "preview"  →  read warnings, fix the doc if needed
            →  run "publish-draft"  →  review the draft in wp-admin
            →  run "publish-live"   →  it's live
```

Re-running on the same file **updates** the existing post (matched by slug)
instead of creating a duplicate, so it's safe to iterate.

## Alternative: GitHub Codespaces

If you'd rather have a real (browser-based) terminal, open the repo in a
**Codespace** (green **Code** button → **Codespaces** → **Create codespace**).
Then follow the local steps in [SETUP.md](SETUP.md): `cp .env.example .env`,
fill it in, `pip install -e ".[dev]"`, and use `wp-publish` directly. Add the
same three values as **Codespaces secrets** so they're available automatically.
