# Wildlife templates

Species pages come in two tiers, chosen by monthly search volume:

| Tier | `type` | Use for | Layout |
| ---- | ------ | ------- | ------ |
| **Tier 1** | `wildlife_tier1` | Iconic species, **500+ searches/mo** | Full content-intensive page |
| **Tier 2** | `wildlife_tier2` | Lower-search species, **≤499/mo** | Lighter, compact page |

You can also declare a generic `type: wildlife` and let the engine pick the tier
from `search_volume` (≥500 → Tier 1, otherwise → Tier 2). Each template warns if
the search volume is on the wrong side of the 500 line.

Both tiers share the same authoring model (metadata header + `##` sections,
with `###` sub-headings becoming cards/steps) and both emit `Article` + `FAQPage`
schema describing the species.

---

# Tier 1 Wildlife template

A content-intensive page for the **most iconic species** — the ones worth a full
designed page. Use it when a species has **500+ monthly searches** (the engine
warns if `search_volume` is missing or below the threshold).

`type: wildlife_tier1`

## What it builds

A fixed, designed layout rendered as native Gutenberg blocks:

1. **Hero** — name, scientific name, tagline, status chips, breadcrumbs, two CTAs
2. **Quick stats & TOC** — sticky table of contents + a quick-facts table
3. **Overview** — text + image, with an optional "Did You Know?" callout
4. **Identification Guide** — annotated image placeholder + ID notes
5. **Where They Live** — distribution-map placeholder + range text
6. **Behavior & Adaptations** — a grid of trait cards
7. **Life Cycle** — a 3-step linear process
8. **Threats & Conservation** — population stat graphic + text
9. **Best Places to See Them** — cards with sighting odds + a CTA each
10. **Traveler FAQs** — native accordion (also emits `FAQPage` schema)
11. **Related Wildlife** — a strip of related species
12. **Final Booking CTA** — closing banner with trust signal

schema.org: `Article` describing the species via `about` (scientific name +
conservation status) plus `FAQPage`.

## How to write the document

Provide a **metadata header** plus normal `##` sections. Inside the card/step
sections, each `###` sub-heading becomes one card or step.

### Metadata header (frontmatter)

```yaml
type: wildlife_tier1
title: "Galapagos Giant Tortoise"
scientific_name: "Chelonoidis niger"
tagline: "The ancient, slow-moving icons..."
search_volume: 5400                 # used for the 500+ tier check
conservation_status: "Vulnerable"
endemic: true
chips: ["Vulnerable", "Endemic", "Santa Cruz & San Cristobal", "June – Dec"]
breadcrumbs: ["Home", "Wildlife", "Galapagos", "Galapagos Giant Tortoise"]
hero_image_query: "Galapagos giant tortoise highlands"
overview_image_query: "Galapagos giant tortoise close up"
facts:                              # the quick-facts table
  "Size & Weight": "Up to 900 lbs / 5 feet"
  "Lifespan": "100–150+ years"
  "Diet Type": "Herbivore"
  "Habitat Type": "Highlands & Arid Lowlands"
population_current: "~20,000"       # conservation stat graphic
population_historical: "250,000"
cta_primary_label: "See This Species"
cta_primary_url: "#where-to-see"
cta_secondary_label: "Plan a Trip"
cta_secondary_url: "/contact"
booking_heading: "Ready to Walk Alongside Giants?"
booking_blurb: "Our expert-led expeditions bring you face-to-face..."
rating: "4.9/5 from 2,000+ wildlife travelers"
related:
  - { name: "Land Iguana", note: "Shares lowland cactus habitat." }
  - { name: "Marine Iguana", note: "Co-exists on rocky coasts." }
```

### Sections (H2) and their special rules

| Section heading            | Maps to        | Special authoring |
| -------------------------- | -------------- | ----------------- |
| Overview                   | `overview`     | A line starting `Did You Know:` or a `>` quote becomes the callout |
| Identification Guide       | `identification` | Bullets = ID notes |
| Where They Live            | `range_habitat`  | A line starting `Accessibility:` becomes the map summary |
| Behavior & Adaptations     | `behavior`     | Each `###` = one card; bullets are the card body |
| Life Cycle                 | `life_cycle`   | Each `###` = one phase/step |
| Threats & Conservation     | `conservation` | Stat graphic comes from `population_*` metadata |
| Best Places to See Them    | `where_to_see` | Each `###` = one place card; a `CTA: Label \| /url` bullet becomes a button |
| Traveler FAQs              | `faq`          | Each `###` = a question, the text below = the answer |

Section titles are flexible — "How to Identify…", "Range & Habitat", "Survival
Traits", "Where to See" etc. all map correctly.

## Build & publish

```bash
wp-publish preview samples/galapagos-giant-tortoise.md      # local, no network
wp-publish publish samples/galapagos-giant-tortoise.md --type wildlife_tier1
```

Required sections: `overview`, `identification`, `range_habitat`, `behavior`,
`conservation`, `where_to_see`. Missing ones produce warnings, not failures.

---

# Tier 2 Wildlife template

A lighter, compact page for species that don't warrant the full Tier 1
treatment. Use it for **499 or fewer monthly searches** (it warns if the volume
exceeds that).

`type: wildlife_tier2`

## What it builds

1. **Minimal hero** — clean light style, chips, a single CTA
2. **Quick facts + identification** — a 40/60 split (compact facts table + "How to Spot Them")
3. **Overview & Habitat** — a 60/40 split (short copy + image)
4. **Lifestyle & Traits** — stacked bullet adaptations
5. **Where to See & Book** — tour cards (duration / landing style + a CTA each)
6. **Quick Traveler FAQ** — a mini accordion (`FAQPage` schema)
7. **Regional footer CTA** — guide download + trip-planner buttons

## Sections (H2)

| Section heading        | Maps to          | Notes |
| ---------------------- | ---------------- | ----- |
| How to Spot Them       | `identification` | Shows in the right column of the facts split |
| (Overview & Habitat)   | `overview`       | A creatively-titled first section is detected automatically |
| Lifestyle & Traits     | `behavior`       | Rendered as stacked bullets |
| Where to See & Book    | `where_to_see`   | Each `###` = a tour card; `CTA: Label \| /url` bullet → button |
| Quick Traveler FAQ     | `faq`            | Each `###` = a question |

Footer CTA and hero use `footer_*` and `cta_primary_*` metadata. Required
sections: `identification`, `overview`, `where_to_see`.

```bash
wp-publish preview samples/sally-lightfoot-crab.md
wp-publish publish samples/sally-lightfoot-crab.md --type wildlife_tier2
```

---

## Styling

Pages render correctly with no setup (styles are inlined). For extra polish
(hover states, sticky TOC, tighter spacing), add **`assets/wildlife.css`** to
your theme via **Appearance → Customize → Additional CSS**. All hooks are
prefixed `.gwp-w-`.
