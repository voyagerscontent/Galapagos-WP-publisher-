# Templates

A **template** describes how one *type* of page is built. It lives in
`templates/<key>.yaml` and is pure configuration — adding or changing a page
type never requires touching Python.

## Anatomy

```yaml
key: tour                      # unique id; also the value you put in "type:"
name: Tour / Itinerary         # human label (shown by `wp-publish templates`)
description: >                 # what it's for
  A sellable trip with a day-by-day itinerary, inclusions, and pricing.
post_type: post                # post | page | <custom-post-type-rest-base>
status: draft                  # optional: overrides the global default status
schema_type: TouristTrip       # schema.org @type for the JSON-LD
categories: [Tours]            # default categories (doc can override)
tags: []                       # default tags (doc can override)
focus_keyword_from: destination # metadata key to use if no explicit keyword

layout:                        # ordered list of slots (see below)
  - { slot: featured, kind: media, role: featured }
  - { slot: overview, kind: section, required: true, show_heading: false }
  - { slot: highlights, kind: section, heading: "Trip Highlights", style: list }
  - { slot: itinerary, kind: section, required: true, heading: "Day-by-Day Itinerary" }
  - { slot: whats_included, kind: section, heading: "What's Included", style: list }
  - { slot: faq, kind: faq }

required_sections: [overview, itinerary]   # missing -> warning (not an error)

schema_extra:                  # merged verbatim into the JSON-LD entity
  touristType: [Adventure, Eco-tourism]
```

## Slots

Each entry in `layout` is a **slot**, rendered in order.

| Field          | Default     | Meaning                                                        |
| -------------- | ----------- | -------------------------------------------------------------- |
| `slot`         | —           | Canonical section key to pull from the document               |
| `kind`         | `section`   | `section` \| `media` \| `faq` \| `spacer`                     |
| `required`     | `false`     | Warn if the document has no matching section                  |
| `heading`      | —           | Force/override the heading text for this slot                 |
| `show_heading` | `true`      | Set `false` to suppress the heading (used for the lead/overview) |
| `style`        | `prose`     | `prose` keeps paragraphs; `list` coalesces content to bullets |
| `role`         | `inline`    | For `media`: `featured` \| `inline` \| `gallery`              |
| `hint`         | —           | Text shown inside a media placeholder                         |

### How sections map to slots

The writer's headings don't have to match the slot names exactly. Titles are
normalized to a **canonical slug** with synonym + keyword matching, so:

- "What's Included", "Inclusions", "Price Includes" → `whats_included`
- "Cruise Highlights", "Trip Highlights", "Key Highlights" → `highlights`
- "Rates & Departures", "Pricing", "Cost" → `pricing`
- "Cabins & Accommodation" → `cabins`

Mappings live in `src/galapagos_publisher/utils.py`
(`SECTION_SYNONYMS` and `_KEYWORD_RULES`). Any document section that doesn't
match a declared slot is **still rendered**, appended after the templated
slots — so authored content is never dropped.

Sub‑headings (H3 and deeper) stay nested inside their parent section. This is
how FAQ questions under an `## FAQ` heading become Q&A pairs (and feed the
`FAQPage` schema).

## Schema.org

`schema_type` picks the top‑level entity. The generator adds type‑specific
fields automatically:

- `TouristTrip` → `itinerary` (from the itinerary section), `arrivalLocation`
  (from `destination`), and `offers` (from `price`).
- `TouristDestination` → `touristType`, `containedInPlace` (from `country`).
- `Article` / `BlogPosting` → `author`, `mainEntityOfPage`.

Anything under `schema_extra` is merged in as‑is for full control.

## Adding a new page type

1. Copy an existing file, e.g. `cp templates/tour.yaml templates/expedition.yaml`.
2. Change `key`, `name`, `schema_type`, and the `layout`.
3. (Optional) add detection signals for it in
   `src/galapagos_publisher/parse/pagetype.py` so it can be auto‑detected, or
   just publish with `--type expedition`.
4. `wp-publish templates` to confirm it loads.
