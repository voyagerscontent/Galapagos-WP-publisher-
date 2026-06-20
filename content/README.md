# content/

Put the documents you want to publish here.

**To publish from GitHub.com (no local setup):**

1. Click **Add file → Upload files** and drop your `.docx`, `.md`, or `.txt`
   into this folder, then commit.
2. Go to the **Actions** tab → **Publish to WordPress** → **Run workflow**.
3. Set **file** to `content/your-file.md`, pick a **mode**
   (`preview` first, then `publish-draft`), and run it.

Supported formats: `.docx`, `.md` / `.markdown`, `.txt`.

Add a short metadata header so the system knows the page type. In Markdown use
YAML frontmatter; in Word/text use a few `Key: value` lines at the very top:

```
type: cruise
title: 8-Day Galapagos Cruise Aboard the M/Y Evolution
destination: Galapagos Islands
price: from $5,495
focus_keyword: Galapagos cruise
```

See `docs/WORKFLOW.md` for the full authoring guide and `samples/` for
ready-made examples.
