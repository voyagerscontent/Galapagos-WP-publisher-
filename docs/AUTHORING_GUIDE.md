# Authoring guide — what to put in your content doc

This is the reference for the people **writing the content**. It covers the
instructions your document must (and can) include so it publishes correctly,
and — for the **freeform builder** — the layout directives that let you design
the page however you want, independent of the fixed templates.

Works in **Markdown (`.md`), plain text (`.txt`), or Word (`.docx`)**, and from
Google Drive.

---

## 1. The metadata header (always include this)

Every document starts with a small header of `key: value` settings. In Markdown
use a YAML frontmatter block fenced by `---`. In Word/plain text, put the same
`Key: value` lines at the very top, then a blank line.

```yaml
---
type: freeform            # which builder/template to use (see §2)
post_type: page           # page | post   (default: post)
title: "Why Travel With Us"
slug: "why-travel-with-us"   # optional; auto-generated from the title if omitted
meta_description: "One or two sentences for Google (70–156 chars)."
focus_keyword: "Galapagos expeditions"
featured_image_query: "Galapagos expedition boat"   # used to find/set the hero image
categories: "About, Company"     # comma-separated (posts only)
tags: "Galapagos, expeditions"   # comma-separated (posts only)
status: draft             # draft | pending | publish  (default: draft)
---
```

### Minimum to publish correctly
| Field | Why it matters |
| ----- | -------------- |
| `type` | Picks the builder/template. Use `freeform` for a custom layout. |
| `title` | Becomes the page `<h1>` and the WordPress title. |
| `post_type` | `page` for evergreen pages, `post` for blog/news. Default `post`. |
| `meta_description` | Your Google snippet. **Strongly recommended** — otherwise it's auto-generated from the first paragraph. |
| `focus_keyword` | Drives SEO checks; should appear in the title. |

Everything else is optional. Slugs auto-generate, status defaults to **draft**,
and images fall back to a clearly-marked placeholder if none is found.

### `type` values
| `type` | Use for |
| ------ | ------- |
| `freeform` | **Any custom page/post** — you control the layout with directives (§2). |
| `blog_post` | Standard article / guide |
| `destination` | Destination overview |
| `tour` / `cruise` | Itineraries / cruises |
| `wildlife_tier1` / `wildlife_tier2` | Species pages (by search volume) |

> The fixed templates (`tour`, `cruise`, `wildlife_*`, …) expect specific
> **section headings** — see their own guides. The rest of *this* document is
> about the **freeform builder**, where you design the layout yourself.

---

## 2. Freeform layout directives (`type: freeform`)

Write normal Markdown for text. Wrap any **structural / designed** piece in a
`:::` directive fence:

```
::: name  optional-arguments
   ...content (more Markdown, or nested directives)...
:::
```

Open a directive with `::: name`; close it with a bare `:::`. Directives can
nest (e.g. `column` inside `columns`).

### Available directives

| Directive | Arguments | What it does |
| --------- | --------- | ------------ |
| `hero` | `image="search terms"` | Full-width banner (gradient, or your image as background). Put a heading, a line of text, and buttons inside. |
| `columns` | a ratio like `60/40` or `1/1/1` | Side-by-side layout. Each child must be a `column`. |
| `column` | — | One column (used inside `columns`). |
| `cards` | `cols=3` | A responsive grid of `card`s. |
| `card` | — | A bordered card (used inside `cards`, or alone). |
| `callout` | `note` \| `tip` \| `warning` \| `danger`, `title="…"` | Highlighted info box. |
| `accordion` | — | Collapsible FAQ. Each `### Question` becomes one expandable item. |
| `cta` | — | Centered call-to-action banner (heading, text, buttons). |
| `group` (or `section`) | `bg="#f6f9f7"` `color="#111"` `align=center` | A styled wrapper around any content. |
| `image` | `query="search terms"` or `src="https://…"`, `alt="…"` | An image (caption = the text inside). |
| `buttons` | — | A row of buttons. |
| `spacer` | a number (px) | Vertical space. |
| `divider` (or `hr`) | — | A horizontal rule. |
| `html` | — | Raw HTML passthrough (advanced). |

### Buttons
Write buttons inline, anywhere, as:

```
[button:Label|/the-url]   [button:Another|/second-url]
```

A line containing only buttons becomes a button row.

### Images
- `::: image query="Galapagos sea lion"` → the system finds a matching image
  from your **Media Library** (in `--media library` mode) or drops a clearly
  labeled placeholder for you to fill.
- `::: image src="https://…/photo.jpg"` → uses that exact image.
- The text inside the `image` directive is the caption.
- The hero/featured image comes from `featured_image_query` (header) or the
  `hero` directive's `image="…"`.

### A worked example

```markdown
---
type: freeform
post_type: page
title: "Why Travel With Us"
meta_description: "Small-group Galapagos expeditions led by resident naturalists."
featured_image_query: "Galapagos expedition boat"
---

::: hero image="Galapagos sunset"
# Travel the Galapagos, Differently
Small-group expeditions led by resident naturalists.
[button:Plan Your Trip|/contact] [button:Browse Cruises|/cruises]
:::

## What Makes Us Different
A short intro paragraph here.

::: cards cols=3
::: card
### Expert Naturalists
Certified Galapagos National Park guides on every departure.
:::
::: card
### Small Groups
A maximum of 16 guests per sailing.
:::
::: card
### Carbon-Neutral
Every itinerary is certified carbon-neutral.
:::
:::

::: callout tip title="Did You Know?"
Our guests have logged over **40,000 wildlife sightings**.
:::

::: columns 60/40
::: column
## A Voyage Built Around Wildlife
We time landings to the rhythms of the islands.
:::
::: column
::: image query="Galapagos sea lion snorkeling"
Snorkeling with curious sea lions.
:::
:::
:::

::: accordion
### How big are the groups?
A maximum of 16 guests per sailing.
### Are the trips family friendly?
Yes — we run dedicated family departures.
:::

::: cta
## Ready to Set Sail?
[button:Talk to an Expert|/contact]
:::
```

See `samples/freeform-example.md` for the full file.

---

## 3. Text formatting inside content

Standard Markdown works inside any directive or plain section:

- `## Heading` / `### Subheading`
- `**bold**`, `*italic*`, `` `code` ``
- `- bullet` lists, `1. numbered` lists
- `> quote`
- `[link text](https://…)`
- `![alt text](https://…/image.jpg)` for an inline image

> Note: the page's main `<h1>` is your `title:` from the header. Inside the body,
> start headings at `##` — a leading `#` is automatically demoted so you don't
> get two `<h1>`s.

---

## 4. Publish checklist

1. Header has `type`, `title`, and (recommended) `meta_description` + `post_type`.
2. Body uses directives for layout and Markdown for text.
3. Save as `.md` / `.txt` / `.docx`, or keep it in the approved Google Drive
   account.
4. Preview first (`mode: preview`), fix any warnings, then `publish-draft`,
   review in WordPress, and `publish-live`.

Anything the system can't place (a missing image, an empty directive) shows up
as a **warning** and a labeled placeholder — never a broken page.
