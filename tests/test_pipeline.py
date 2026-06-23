"""Tests for page-type detection, rendering, SEO, and schema."""

from pathlib import Path

from wp_publisher.config import get_settings
from wp_publisher.ingest import read_file
from wp_publisher.parse import detect_page_type
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.template import load_registry

SAMPLE = Path(__file__).resolve().parents[1] / "samples" / "galapagos-cruise.md"


def _ctx():
    return BuildContext(
        settings=get_settings(),
        registry=load_registry(),
        wp_client=None,
        media_strategy="placeholder",
    )


def test_detects_declared_type():
    doc = read_file(SAMPLE)
    key, reason = detect_page_type(doc, load_registry())
    assert key == "cruise"
    assert "declared" in reason


def test_build_page_basic_fields():
    doc = read_file(SAMPLE)
    page, template, _reason = build_page(doc, _ctx())
    assert template.key == "cruise"
    assert page.post_type == "post"
    assert page.slug.startswith("8-day-galapagos-cruise")
    assert page.status == "draft"
    assert "Galapagos Cruises" in page.categories


def test_output_is_acf_not_gutenberg():
    doc = read_file(SAMPLE)
    page, _t, _r = build_page(doc, _ctx())
    # post_content is empty; content lives in ACF fields.
    assert page.content_html == ""
    rows = page.acf["page_sections"]
    layouts = [r["acf_fc_layout"] for r in rows]
    assert layouts[0] == "hero"
    assert "rich_text" in layouts
    assert "faq" in layouts
    # rich_text carries semantic HTML, not block markup.
    rt = next(r for r in rows if r["acf_fc_layout"] == "rich_text")
    assert "<p>" in rt["content"] and "<!-- wp:" not in rt["content"]
    # JSON-LD is delivered as an ACF field, not embedded in content.
    assert page.acf.get("seo_schema")


def test_seo_fields():
    doc = read_file(SAMPLE)
    page, _t, _r = build_page(doc, _ctx())
    assert page.focus_keyword == "Galapagos cruise"
    assert page.meta_description
    assert len(page.seo_title) <= 64  # title_max + small slack


def test_schema_jsonld():
    doc = read_file(SAMPLE)
    page, _t, _r = build_page(doc, _ctx())
    graph = page.json_ld["@graph"]
    types = {entity["@type"] for entity in graph}
    assert "TouristTrip" in types
    assert "FAQPage" in types
    trip = next(e for e in graph if e["@type"] == "TouristTrip")
    assert trip["offers"]["price"] == "5495"
    assert len(trip["itinerary"]) == 8


def test_missing_required_section_warns():
    doc = read_file(SAMPLE)
    # Drop the itinerary to trigger a required-section warning.
    doc.sections = [s for s in doc.sections if s.slug != "itinerary"]
    page, _t, _r = build_page(doc, _ctx())
    assert any("itinerary" in w for w in page.warnings)
