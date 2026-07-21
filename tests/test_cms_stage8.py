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
