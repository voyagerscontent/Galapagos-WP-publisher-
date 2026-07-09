"""Tests for the ACF-driven Wildlife Single (species) section.

Covers the Phase 2 extraction: routing table-heavy species docs out of the CMS
adapter, mining the species facts (scientific name, IUCN status, population,
endemism), and the seasonality / subspecies repeaters.
"""

from pathlib import Path

from wp_publisher.config import get_settings
from wp_publisher.ingest import read_file
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.template import load_registry

WILDLIFE = Path(__file__).resolve().parents[1] / "content" / "wildlife"


def _ctx():
    return BuildContext(
        settings=get_settings(),
        registry=load_registry(),
        wp_client=None,
        media_strategy="placeholder",
    )


def _build(name: str):
    doc = read_file(WILDLIFE / name, page_type="wildlife_single")
    page, template, _ = build_page(doc, _ctx(), page_type="wildlife_single")
    return doc, page, template


def test_wildlife_single_template_is_acf_page():
    tpl = load_registry().get("wildlife_single")
    assert tpl.acf_profile == "wildlife_single"
    assert tpl.post_type == "page"
    assert tpl.parent_page == "wildlife"
    assert tpl.renderer is None  # NOT the legacy Gutenberg tier renderer


def test_species_facts_mined_from_facts_table():
    """A doc whose PUBLISHER HEADER BLOCK would send it to the CMS adapter still
    yields a real title and the dedicated species facts (not just schema)."""
    doc, page, template = _build("waved-albatross.docx")
    assert template.key == "wildlife_single"
    assert "PUBLISHER HEADER" not in doc.title  # routed to the normal path
    acf = page.acf
    assert acf["scientific_name"] == "Phoebastria irrorata"
    assert acf["conservation_status"] == "Critically Endangered"
    assert acf["population"]
    # The Month | Status | Notes calendar -> the seasonality repeater.
    assert isinstance(acf.get("seasonality"), list) and len(acf["seasonality"]) >= 10
    row = acf["seasonality"][0]
    assert set(row) >= {"period", "label", "notes"}


def test_subspecies_repeater_from_island_table():
    _doc, page, _t = _build("giant-tortoise.docx")
    sub = page.acf.get("subspecies")
    assert isinstance(sub, list) and len(sub) >= 10  # 13-row island-by-island table
    first = sub[0]
    assert first["island"] and first["name"]


def test_conservation_status_normalized_to_iucn_choice():
    _doc, page, _t = _build("galapagos-penguin.docx")
    # "IUCN status: Endangered" -> the canonical select choice.
    assert page.acf["conservation_status"] == "Endangered"
    assert page.acf["endemic"] == 1


def test_repeater_image_subfield_never_empty_string():
    """An ACF image field over REST must be an integer or null — an empty string
    triggers a 400 (rest_invalid_type). Repeater rows must omit image when we
    have no attachment ID."""
    _doc, page, _t = _build("giant-tortoise.docx")
    for row in page.acf.get("subspecies", []):
        assert row.get("image", None) not in ("", 0, False)
    _doc2, page2, _t2 = _build("marine-iguana.docx")
    # true_false -> a real boolean, not an int.
    assert page2.acf["endemic"] is True


def test_schema_never_leaks_into_body():
    """A raw JSON-LD block in a single-cell table must not become body prose."""
    _doc, page, _t = _build("giant-tortoise.docx")
    assert "@context" not in (page.acf.get("intro") or "")
    for fs in page.acf.get("feature_sections", []):
        assert "@graph" not in (fs.get("content") or "")


def test_geo_answer_is_the_answer_not_the_instruction():
    """The webmaster 'this block is your GEO snippet' instruction box must be
    dropped; geo_answer is the real Quick Answer."""
    _doc, page, _t = _build("giant-tortoise.docx")
    geo = page.acf.get("geo_answer") or ""
    assert geo.startswith("The Galápagos giant tortoise")
    assert "is your GEO" not in geo and "Place this" not in geo


def test_single_cell_cta_becomes_repeater():
    """'CTA: <audience> | <company>' single-cell blocks parse into the cta
    repeater (2 cards), not the Plan-Your-Visit intro prose."""
    _doc, page, _t = _build("giant-tortoise.docx")
    cta = page.acf.get("cta") or []
    assert len(cta) == 2
    assert {c["audience"] for c in cta} == {"Direct travelers", "Travel trade"}
    assert "CTA:" not in (page.acf.get("cta_intro") or "")


def test_explore_footer_becomes_related_links_from_hyperlinks():
    """The 'Explore …' footer (Word hyperlinks, no '→ url' text) is mined into
    related_links via the hyperlink URLs — and is NOT also a feature section."""
    _doc, page, _t = _build("giant-tortoise.docx")
    rel = page.acf.get("related_links") or []
    assert len(rel) >= 5
    assert all(r["url"].startswith(("/", "http")) for r in rel)
    assert not any("explore" in (fs.get("title") or "").lower()
                   for fs in page.acf.get("feature_sections", []))


def test_publishes_under_wildlife_hub():
    _doc, page, _t = _build("marine-iguana.docx")
    assert page.post_type == "page"
    assert page.parent_slug == "wildlife"
    # Schema is taken verbatim from the doc, never generated.
    assert page.acf.get("seo_schema", "").lstrip().startswith("{")
