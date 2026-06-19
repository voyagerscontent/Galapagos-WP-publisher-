# Setup

## 1. Install

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"          # add the gdrive extra if you'll use Drive:
pip install -e ".[dev,gdrive]"
```

Copy the environment template and fill it in:

```bash
cp .env.example .env
```

## 2. WordPress Application Password

The publisher authenticates with an **Application Password** — a revocable
credential that does not expose the account's real password. It works on any
modern WordPress (5.6+) with the REST API enabled.

1. Log in to the **galapagosislands.travel** WordPress admin as the user that
   should own the content.
2. Go to **Users → Profile** (or **Users → Your Profile**).
3. Scroll to **Application Passwords**.
4. Enter a name like `wp-publisher` and click **Add New Application Password**.
5. Copy the generated password (looks like `abcd EFGH 1234 wxyz 5678 90ab`).
   The spaces are fine — paste it as‑is.
6. Put the values in `.env`:

   ```ini
   WP_BASE_URL=https://www.galapagosislands.travel
   WP_USERNAME=your-wp-username
   WP_APP_PASSWORD=abcd EFGH 1234 wxyz 5678 90ab
   WP_DEFAULT_STATUS=draft
   ```

Verify it:

```bash
wp-publish check
```

You should see the authenticated user and which SEO plugin (Yoast / RankMath /
none) was detected.

### Permissions & notes

- The user needs the `edit_posts` / `publish_posts` capability (Author or
  above; Editor recommended).
- Some hosts or security plugins block REST writes or Basic Auth. If `check`
  fails with 401/403, confirm Application Passwords are enabled and the REST
  API isn't firewalled.
- Categories and tags are created automatically if they don't exist.

## 3. Google Drive (optional)

Used only if you ingest documents straight from Drive. Set
`GDRIVE_ALLOWED_ACCOUNT` in `.env` to lock ingestion to a single Google account
(this repo defaults it to **businessops@latintrails.com** — the shared content
account); the system then refuses any other account. Leave it blank to allow
any authorized account.

1. In the [Google Cloud Console](https://console.cloud.google.com/), create (or
   reuse) a project.
2. Enable the **Google Drive API**.
3. Under **APIs & Services → Credentials**, create an **OAuth client ID** of
   type **Desktop app**.
4. Download the JSON and save it as `credentials.json` in the project root
   (the path is configurable via `GDRIVE_CREDENTIALS_FILE`).
5. First run opens a browser to authorize. Sign in as the account set in
   `GDRIVE_ALLOWED_ACCOUNT`. A `token.json` is cached for next time.

```bash
wp-publish gdrive "8-Day Galapagos" --preview
```

If you authorize the wrong account, the run aborts with a clear error — delete
`token.json` and retry with the correct account.

> `credentials.json`, `token.json`, and `.env` are git‑ignored. Never commit
> them.

## 4. Site defaults

Edit `config/site.yaml` to set your organization details, SEO length limits,
and the default media strategy (`library` vs `placeholder`). These feed the
schema.org output and SEO optimizer.
