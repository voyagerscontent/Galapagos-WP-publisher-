"""Tests for the Tier 1 Wildlife template and renderer."""

from pathlib import Path

from wp_publisher.config import get_settings
from wp_publisher.ingest import read_file
from wp_publisher.parse import detect_page_type
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.template import load_registry
from wp_publisher.utils import section_slug

SAMPLE = Path(__file__).resolve().parents[1] / "samples" / "galapagos-giant-tortoise.md"


def _ctx():
    return BuildContext(
        settings=get_settings(),
        registry=load_registry(),
        wp_client=None,
        media_strategy="placeholder",
    )


def test_wildlife_template_registered():
    registry = load_registry()
    assert "wildlife_tier1" in registry
    tpl = registry.get("wildlife_tier1")
    assert tpl.renderer == "wildlife_tier1"
    assert tpl.min_search_volume == 500


def test_detect_and_aliases():
    doc = read_file(SAMPLE)
    key, _ = detect_page_type(doc, load_registry())
    assert key == "wildlife_tier1"


def test_section_slug_wildlife():
    assert section_slug("Identification Guide") == "identification"
    assert section_slug("Where They Live") == "range_habitat"
    assert section_slug("Behavior & Adaptations") == "behavior"
    assert section_slug("Life Cycle") == "life_cycle"
    assert section_slug("Threats & Conservation") == "conservation"
    assert section_slug("Best Places to See Them") == "where_to_see"


def test_builds_flat_acf_fields():
    doc = read_file(SAMPLE)
    page, template, _ = build_page(doc, _ctx())
    assert template.key == "wildlife_tier1"
    acf = page.acf
    assert acf["hero_heading"]
    assert acf["body"]
    assert isinstance(acf["faq"], list) and acf["faq"]   # Traveler FAQs
    assert isinstance(acf["key_facts"], list) and acf["key_facts"]  # `facts:` -> stats
    assert acf.get("page_subtitle")                      # tagline


def test_facts_become_key_facts_repeater():
    doc = read_file(SAMPLE)
    page, _t, _ = build_page(doc, _ctx())
    labels = [i["label"] for i in page.acf["key_facts"]]
    assert "Size & Weight" in labels


def test_schema_species_about_and_faq():
    doc = read_file(SAMPLE)
    page, _t, _ = build_page(doc, _ctx())
    graph = page.json_ld["@graph"]
    types = {e["@type"] for e in graph}
    assert {"Article", "FAQPage"} <= types
    main = graph[0]
    assert main["about"]["alternateName"] == "Chelonoidis niger"
    faq = next(e for e in graph if e["@type"] == "FAQPage")
    assert len(faq["mainEntity"]) == 5


def test_low_search_volume_warns():
    doc = read_file(SAMPLE)
    doc.metadata["search_volume"] = "120"
    page, _t, _ = build_page(doc, _ctx())
    assert any("threshold" in w.lower() for w in page.warnings)


# --------------------------------------------------------------------------- #
# Tier 2 (compact species)
# --------------------------------------------------------------------------- #
SAMPLE2 = Path(__file__).resolve().parents[1] / "samples" / "sally-lightfoot-crab.md"


def test_tier2_template_registered():
    tpl = load_registry().get("wildlife_tier2")
    assert tpl.max_search_volume == 499


def test_tier2_builds_flat_acf_fields():
    doc = read_file(SAMPLE2)
    page, template, _ = build_page(doc, _ctx())
    assert template.key == "wildlife_tier2"
    assert page.acf["hero_heading"]
    assert page.acf["body"]
    assert isinstance(page.acf["faq"], list) and page.acf["faq"]


def test_tier2_high_volume_warns():
    doc = read_file(SAMPLE2)
    doc.metadata["search_volume"] = "5000"
    page, _t, _ = build_page(doc, _ctx())
    assert any("exceeds" in w.lower() for w in page.warnings)


def test_generic_wildlife_routes_by_search_volume():
    registry = load_registry()
    low = read_file(SAMPLE2)
    low.metadata["type"] = "wildlife"
    low.metadata["search_volume"] = "200"
    key, _ = detect_page_type(low, registry)
    assert key == "wildlife_tier2"

    high = read_file(SAMPLE)
    high.metadata["type"] = "wildlife"
    high.metadata["search_volume"] = "5400"
    key, _ = detect_page_type(high, registry)
    assert key == "wildlife_tier1"


CONTENT = Path(__file__).resolve().parents[1] / "content"


def test_freeform_wildlife_title_extracts_species():
    """A section titled "The Wildlife" (not starting with "Wildlife") must
    still populate the wildlife repeater from its H3 species sub-headings."""
    doc = read_file(CONTENT / "genovesa-island.docx")
    wildlife = doc.metadata.get("wildlife") or []
    assert len(wildlife) >= 3
    assert doc.metadata.get("wildlife_title") == "The Wildlife"
    assert any("boob" in (r.get("common_name") or "").lower() for r in wildlife)


def test_wildlife_picks_section_with_most_species():
    """When several headings mention "wildlife", keep the one that actually
    yields species rows rather than the first passing mention."""
    doc = read_file(CONTENT / "fernandina-island.docx")
    wildlife = doc.metadata.get("wildlife") or []
    assert len(wildlife) >= 4
    assert "Punta Espinoza" in (doc.metadata.get("wildlife_title") or "")


def test_wildlife_calendar_extracted():
    """A seasonal 'what to see when' table (Santiago) fills wildlife_calendar."""
    doc = read_file(CONTENT / "santiago-island.docx")
    cal = doc.metadata.get("wildlife_calendar") or []
    assert len(cal) >= 3
    periods = [r["period"] for r in cal]
    assert any("January" in p for p in periods)
    assert any(r.get("label") for r in cal)          # e.g. "Warm / Wet Season"
    assert all(r.get("highlights") for r in cal)


def test_wildlife_calendar_maps_to_acf():
    doc = read_file(CONTENT / "santiago-island.docx")
    doc.metadata["type"] = "destination"
    page, _t, _ = build_page(doc, _ctx())
    rows = page.acf.get("wildlife_calendar")
    assert isinstance(rows, list) and len(rows) >= 3
    assert rows[0].get("period")
    assert "<p>" in rows[0].get("highlights", "")     # WYSIWYG HTML
