"""Stage-8 CMS adapter: banner+KV header, [AIO BLOCK] markers, data tables,
FAQ/sources, and the trailing pipeline/audit block that must be dropped."""

from __future__ import annotations

from wp_publisher.ingest.cms import build_cms_stage8_document, looks_like_stage8

# An ordered block list as _ordered_blocks() would produce from the .docx.
_ITEMS = [
    ("p", "GalapagosIslands.travel | CMS Stage 8 | Bartolomé Island | v2"),
    ("p", "Slug: /islands/bartolome/"),
    ("p", "Page type: island | Persona: first_timer"),
    ("p", "Primary CTA: Talk to a Galápagos Specialist"),
    ("p", "Schema: Article, FAQPage, BreadcrumbList"),
    ("p", "Publisher: Voyagers Travel Company"),
    ("p", "Bartolomé Island: Pinnacle Rock, Penguins, and Volcanic Views"),
    ("p", "[AIO BLOCK 1 — speakable]"),
    ("p", "Bartolomé is a small volcanic island near Santiago, known for Pinnacle Rock."),
    ("p", "Juan Magallanes, Naturalist Expert Contributor — GalapagosIslands.travel"),
    ("p", "Data Snapshot"),
    ("p", "[AIO BLOCK 2 — data table]"),
    ("table", [["Field", "Detail"], ["Island group", "Central"], ["Max elevation", "114 m"]]),
    ("p", "What You'll See at Bartolomé"),
    ("p", "Two visitor sites, usually combined into one visit. [source: visitor_sites.csv]"),
    ("p", "Wildlife by Season"),
    ("table", [["Species", "Months"], ["Penguins", "Year-round"]]),
    ("p", "FAQs"),
    ("p", "[AIO BLOCK 5 — FAQPage schema]"),
    ("p", "What is Pinnacle Rock?"),
    ("p", "A volcanic tuff cone beside the snorkel beach."),
    ("p", "Can you swim with penguins?"),
    ("p", "Yes, near Pinnacle Rock."),
    ("p", "Plan Your Trip"),
    ("p", "A specialist can match you to an itinerary that includes Bartolomé."),
    ("table", [["📍 Direct Travelers", "🤝 Trade & DMC"], ["Contact us", "Partner with us"]]),
    ("p", "Sources & Citations"),
    ("p", "GALAPAGOS_FACTS_ADDENDUM.md — internal source-of-truth file"),
    ("p", "Galápagos Conservancy — https://www.galapagos.org/"),
    # --- Everything below is internal pipeline scaffold: must be dropped. ---
    ("p", "VERIFY Summary"),
    ("p", "WF6 — Polish: Output Confirmation"),
    ("p", "Meta Title & Meta Description"),
    ("p", "Meta Title (53 characters):"),
    ("p", "Bartolomé Island Guide: Pinnacle Rock, Penguins, Hike"),
    ("p", "Meta Description (60 characters):"),
    ("p", "Bartolomé offers a boardwalk climb to the 114m Pinnacle Rock summit."),
]
_TEXTS = [v for k, v in _ITEMS if k == "p"]


def test_looks_like_stage8():
    assert looks_like_stage8(_TEXTS)
    assert not looks_like_stage8(["Some other doc", "no banner here"])


# The "PUBLISHER HEADER BLOCK" variant: a big internal header, an inline
# "AIO SUMMARY BLOCK (…): <answer>", and section titles marked only with BOLD
# (no heading styles) — including a long title the text heuristic would miss.
# Items are ('p', text, bold) as _ordered_blocks() now emits.
_ITEMS_V2 = [
    ("p", "■ CMS STAGE 8 — PUBLISHER HEADER BLOCK", True),
    ("p", "INTERNAL USE — NOT PUBLISHED TO FRONT END", True),
    ("p", "SITE:          GalapagosIslands.travel", False),
    ("p", "SLUG:          /wildlife/darwin-finches/", False),
    ("p", "PAGE TYPE:     Species / Natural History Reference Page", False),
    ("p", "AUTHOR:        Juan Magallanes, Naturalist Expert Contributor", False),
    ("p", "JSON-LD — STRUCTURED DATA (TouristAttraction + FAQPage)", True),
    ("p", "ENTITY RULES — ABSOLUTE (all editors must observe):", True),
    ("p", "■ PUBLISH URL BLOCK", True),
    ("p", "Robots:        index, follow", False),
    ("p", "Darwin's Finches — The Birds That Changed How We Understand Life", True),
    ("p", "AIO SUMMARY BLOCK (≤50 words): Darwin's finches are 18 species endemic to the Galapagos.", True),
    ("p", "By Juan Magallanes, Naturalist Expert Contributor", False),
    ("p", "Species at a Glance", True),
    ("p", "The 18 recognised species span five functional groups.", False),
    ("p", "The Misconception: Darwin, the Finches, and the Man Who Actually Identified Them", True),
    ("p", "The popular story goes that Darwin observed the finches and grasped evolution at once.", False),
    ("p", "What Darwin Actually Did on the Islands", True),
    ("p", "Darwin spent approximately five weeks in the Galapagos across four islands.", False),
]
_TEXTS_V2 = [v for k, v, *_ in _ITEMS_V2]


def test_stage8_publisher_header_block_variant():
    assert looks_like_stage8(_TEXTS_V2)  # "AIO SUMMARY BLOCK" counts, not just "[AIO BLOCK"
    doc = build_cms_stage8_document(_ITEMS_V2, _TEXTS_V2, "darwin-finches.docx")
    m = doc.metadata
    # The bold H1 (not the banner / internal scaffold) is the title.
    assert doc.title.startswith("Darwin's Finches")
    # Routing metadata comes from the SLUG line.
    assert m["slug"] == "darwin-finches"
    assert m["url_section"] == "wildlife"
    assert m["author"] == "Juan Magallanes"
    # Inline AIO summary -> geo_answer.
    assert "18 species" in m["geo_answer"]
    titles = [s.title for s in doc.sections]
    # Each bold heading becomes its own section — including the long one the
    # text-length heuristic would have missed.
    assert "Species at a Glance" in titles
    assert "What Darwin Actually Did on the Islands" in titles
    assert any(t.startswith("The Misconception:") for t in titles)
    # The internal header scaffold never leaks into the body.
    bodies = "".join(b.html for s in doc.sections for b in s.blocks if b.html)
    assert "PUBLISHER HEADER BLOCK" not in bodies
    assert "NOT PUBLISHED" not in bodies


def test_stage8_extraction():
    doc = build_cms_stage8_document(_ITEMS, _TEXTS, "bartolome.docx")
    m = doc.metadata
    # Header: the H1 (not the banner) is the title; slug from the Slug: line.
    assert doc.title.startswith("Bartolomé Island: Pinnacle Rock")
    assert m["slug"] == "bartolome"
    assert m["author"] == "Juan Magallanes"
    # The [AIO BLOCK 1 — speakable] answer becomes geo_answer.
    assert "volcanic island near Santiago" in m["geo_answer"]
    # Curated meta title/description pulled from the (dropped) trailing block.
    assert m["seo_title"].startswith("Bartolomé Island Guide")
    assert m["meta_description"].startswith("Bartolomé offers")
    # CTA (2 audiences) and sources (internal .md dropped, external kept).
    assert len(m["cta_blocks"]) == 2
    assert [s["label"] for s in m["sources"]] == ["Galápagos Conservancy"]
    assert m["sources"][0]["url"] == "https://www.galapagos.org/"
    # Feature sections keep their tables verbatim; FAQ becomes its own section.
    slugs = [s.slug for s in doc.sections]
    assert "faq" in slugs
    bodies = "".join(b.html for s in doc.sections for b in s.blocks if b.html)
    assert "<table>" in bodies and "Max elevation" in bodies
    assert "Wildlife by Season" in [s.title for s in doc.sections]
    faq = next(s for s in doc.sections if s.slug == "faq")
    assert len([b for b in faq.blocks if b.type.value == "heading"]) == 2
    # The trailing pipeline/audit scaffold never becomes content.
    assert "VERIFY Summary" not in bodies
    assert "WF6" not in bodies and "Output Confirmation" not in bodies
