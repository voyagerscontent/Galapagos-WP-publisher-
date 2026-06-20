# Troubleshooting

## Publishing fails with an authentication error

Symptoms in the GitHub Actions run log, e.g.:

```
WordPressError: GET .../wp-json/wp/v2/categories -> 400:
{"status":"error","error":"INVALID_PASSWORD","code":"400","error_description":"Incorrect password."}
```

Work through these in order.

### 1. Username / Application Password

- `WP_USERNAME` must be the user's **login username** (Users → Profile →
  "Username"), not their display name or email.
- `WP_APP_PASSWORD` must be a WordPress **Application Password** (Users →
  Profile → Application Passwords), not the normal login password. Generate a
  fresh one and paste it exactly (spaces are fine).
- Quick check: Users → Profile → Application Passwords → the **"Last used"**
  column. If it stays **empty** after a run, the request never reached
  WordPress core — something is intercepting it (see below). If it shows a
  recent time, the value itself is wrong.

### 2. A security plugin / firewall is intercepting the REST API

A non-standard error body (with `status` / `error` / `error_description`)
usually means a plugin or host firewall is handling auth before WordPress does.

**This site (galapagosislands.travel) runs the miniOrange "REST API
Authentication" plugin.** By default it protects every `/wp-json` endpoint and
**does not accept WordPress Application Passwords** — which blocks publishing.

**The fix (already applied here):**

1. In WordPress admin, open **miniOrange → Protected REST APIs**.
2. Under **Protected WordPress Default REST APIs → WordPress**, **uncheck**
   `/wp/v2` (this clears all the default endpoints below it). At minimum,
   uncheck: `/wp/v2/posts`, `/wp/v2/pages`, `/wp/v2/categories`,
   `/wp/v2/tags`, `/wp/v2/media`.
3. Click **Save**.

Unchecking these hands those endpoints back to WordPress's normal rules.
Creating/editing content still requires authentication (your Application
Password) — you're only removing miniOrange's *extra* gate. Reads of
already-public content being public is WordPress's standard behavior.

> WP Cerber Security is **also** installed but was **not** the cause — turning
> it off did not change the error. Leave it active.

### 3. Apache strips the Authorization header (other hosts)

On some Apache hosts the `Authorization` header is removed before WordPress
sees it, so Application Passwords always fail. If you hit this on another site,
add to `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
```

## How to read a failed run

GitHub → **Actions** → click the failed run → click the **run** job → expand
the **Run** step. The real error (the `WordPressError: ...` line) is near the
bottom, just above the cleanup output.

## Other notes

- Publishing is **idempotent on the slug** — re-running the same document
  updates the existing post instead of creating a duplicate.
- `preview` mode never touches WordPress; use it to sanity-check a document.
- If you ever expose a real password (e.g. in a screenshot/test), rotate it.
