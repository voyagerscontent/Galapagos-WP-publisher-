"""HTML reader: the production-HTML class contract maps deterministically to
the Document / ACF payload, with section HTML preserved verbatim."""

from __future__ import annotations

from pathlib import Path

from wp_publisher.config import get_settings
from wp_publisher.ingest import read_file
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.template import load_registry

_HTML = """<!DOCTYPE html>
<html lang="en"><head>
<title>Galápagos Trip Cost Per Day: Real 2026 Budget Ranges</title>
<meta name="description" content="What a Galápagos trip really costs per day.">
<link rel="canonical" href="https://www.galapagosislands.travel/planning/galapagos-trip-cost-per-day/">
<script type="application/ld+json">
{"@context":"https://schema.org","@graph":[
 {"@type":"Article","headline":"Cost","author":{"@type":"Person","name":"Juan Magallanes"}},
 {"@type":"FAQPage","mainEntity":[]}]}
</script>
</head><body>
<nav aria-label="Breadcrumb"><ol><li>Home</li></ol></nav>
<article>
<header><h1>How Much Does a Galápagos Trip Cost Per Day?</h1>
<p class="dateline">Reviewed by Juan Magallanes, Advisor</p></header>
<div class="answer-box" data-speakable="true"><p>Plan on <strong>$150–$700</strong> per day.</p></div>
<nav class="on-this-page"><p>On this page: ...</p></nav>
<section id="fixed"><h2>What fixed costs does everyone pay?</h2>
<p>Three costs hit every traveller.</p><ul><li>Park fee</li><li>Transit card</li></ul></section>
<section id="week"><h2>What does a sample week cost?</h2>
<p>A costed week:</p>
<table><thead><tr><th>Style</th><th>Total</th></tr></thead>
<tbody><tr><td>Budget</td><td>$2,070</td></tr></tbody></table></section>
<section id="less"><h2>How can I spend less?</h2><p>Base yourself on land.</p>
<div class="lead-magnet"><p>Download the Free Galápagos Guide (PDF).</p></div></section>
<section id="faq"><h2>FAQ</h2>
<details><summary><h3>How much per day?</h3></summary><p>Roughly <strong>$150–$700</strong>.</p></details>
<details><summary><h3>Cruise or land?</h3></summary><p>Land is cheaper.</p></details></section>
<section id="related"><h2>Related guides</h2><ul>
<li><a href="/planning/cruise-vs-land-based/">Cruise vs land</a></li></ul></section>
<section class="conversion-band"><h2>Want your trip costed?</h2>
<p>Send your dates and we'll map the cost.</p>
<p><a class="cta-primary" href="https://www.galapagosislands.travel/enquire/">Talk to a Specialist</a></p></section>
<footer class="sources"><p>Sources: DPNG fee schedule; INGALA card requirements.</p></footer>
</article></body></html>
"""


def _doc(tmp_path: Path):
    f = tmp_path / "cost.html"
    f.write_text(_HTML, encoding="utf-8")
    return read_file(f)


def test_reader_metadata_and_sections(tmp_path):
    doc = _doc(tmp_path)
    m = doc.metadata
    assert doc.source_kind == "html"
    assert doc.title.startswith("How Much Does")
    assert m["slug"] == "galapagos-trip-cost-per-day"  # from <link canonical>
    assert m["seo_title"].startswith("Galápagos Trip Cost")  # curated <title>
    assert m["author"] == "Juan Magallanes"  # from JSON-LD Article
    assert "<strong>$150" in m["geo_answer"]  # answer-box -> GEO
    assert m["schema_jsonld"] and "FAQPage" in m["schema_jsonld"]
    assert len(m["sources"]) == 2
    assert len(m["related_links"]) == 1
    # Two CTAs: conversion-band (primary) + lead-magnet (secondary).
    assert len(m["cta_blocks"]) == 2
    assert m["cta_blocks"][0]["button_label"] == "Talk to a Specialist"
    # FAQ section carries HEADING+HTML pairs; nav/breadcrumb are not sections.
    slugs = [s.slug for s in doc.sections]
    assert "faq" in slugs
    assert "related" not in slugs  # related -> metadata, not a feature section


def test_build_preserves_html_tags(tmp_path):
    doc = _doc(tmp_path)
    ctx = BuildContext(
        settings=get_settings(), registry=load_registry(),
        wp_client=None, media_strategy="placeholder",
    )
    page, _t, _r = build_page(doc, ctx, page_type="informative")
    acf = page.acf
    assert acf["geo_answer"].startswith("<p>")  # GEO filled, not empty
    assert acf["author"] == "Juan Magallanes"
    assert len(acf["faqs"]) == 2
    assert len(acf["cta"]) == 2
    # Section HTML (incl. the <table>) survives verbatim into the WYSIWYG field.
    bodies = "".join(
        v for r in acf["feature_sections"] for v in r.values() if isinstance(v, str)
    )
    assert "<table>" in bodies and "<th>Style</th>" in bodies
    assert "<ul>" in bodies and "<li>Park fee</li>" in bodies
