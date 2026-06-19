# Authoring & publishing workflow

This is the day‑to‑day guide for turning a doc into a published page.

## 1. Write the document

Use whatever you're comfortable with — Word, Google Docs, Markdown, or plain
text. Two rules make the automation work well:

### a) Add a metadata header

Tell the system the page type and key fields. In **Markdown**, use YAML
frontmatter. In **Word/Google Docs/plain text**, put a few `Key: value` lines
at the very top (before the first paragraph), then a blank line.

```
Type: tour
Title: 7-Day Peru Highlights
Destination: Peru
Duration: 7 days
Price: from $2,150
Focus keyword: Peru tour
Categories: Tours, Peru
Tags: Peru, Machu Picchu, Cusco
```

Recognized keys: `type`, `title`, `destination`, `duration`, `price`,
`focus_keyword`, `categories`, `tags`, `ship`, `country`, `region`,
`best_time`, `slug`, `meta_description`, `featured_image_query`, `status`.

If you omit `type`, the system guesses from the content (and you can always
override with `--type`).

### b) Use clear headings

Each `##` heading becomes a section. Phrase them naturally — the system maps
synonyms to the right template slot. Helpful sections by page type:

- **Tour / Cruise**: Overview, Highlights, Itinerary, What's Included, Pricing, FAQ
- **Destination**: Overview, Highlights, Things to Do, Best Time to Visit,
  Getting There, FAQ
- **Blog post**: Overview/Intro, body sections, FAQ, Conclusion

For **FAQs**, use `###` for each question with the answer as the paragraph
below it — that produces `FAQPage` rich‑result schema automatically.

## 2. Preview before publishing

```bash
wp-publish preview my-doc.docx
```

This builds the page **without** touching WordPress and writes three files to
`output/<slug>/`:

- `content.html` — the Gutenberg block markup
- `schema.json` — the schema.org JSON‑LD
- `page.json` — everything (SEO fields, media decisions, warnings)

The console prints a summary: detected page type, title, slug, SEO title/meta,
categories/tags, and any **warnings** (missing required sections, short meta,
focus‑keyword gaps). Fix warnings in the source doc and re‑preview.

## 3. Choose a media strategy

| Strategy           | Flag                 | Behavior                                            |
| ------------------ | -------------------- | --------------------------------------------------- |
| Placeholder (default) | `--media placeholder` | Inserts clearly marked blocks for a human to fill |
| Media Library      | `--media library`    | Finds the best matching image already in WordPress  |

The default comes from `config/site.yaml` (`media.default_strategy`). With the
library strategy, the featured image and inline images are matched by
destination/title; anything below the match threshold falls back to a
placeholder so you're never stuck with a wrong image.

## 4. Publish

```bash
# Safe default: creates/updates a DRAFT
wp-publish publish my-doc.docx --type tour --media library

# Go live (prompts for confirmation)
wp-publish publish my-doc.docx --status publish
```

Publishing is **idempotent on the slug**: running it again updates the existing
post instead of creating a duplicate. Use `--no-update` to force a new post.

After publishing you get the post ID, the public URL, and a direct **edit**
link to review in the block editor.

## 5. From Google Drive

```bash
wp-publish gdrive "7-Day Peru Highlights" --preview      # build only
wp-publish gdrive 1AbCdEfGhIjKlMnOpQrStUvWxYz --type tour  # by file ID
```

Only `businessops@latintrails.com` is allowed; the first run authorizes the
account in your browser.

## Typical loop

```
write doc  →  wp-publish preview  →  fix warnings  →  wp-publish publish (draft)
           →  review in WP editor  →  wp-publish publish --status publish
```
