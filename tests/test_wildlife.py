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


def test_builds_full_layout():
    doc = read_file(SAMPLE)
    page, template, _ = build_page(doc, _ctx())
    html = page.content_html
    assert template.key == "wildlife_tier1"
    # Hero + designed sections present
    assert "gwp-w-hero" in html
    assert 'id="overview"' in html
    assert 'id="identification"' in html
    assert 'id="where-to-see"' in html
    # Behavior cards, life-cycle steps, accordion FAQ, related strip
    assert "gwp-w-card" in html
    assert "gwp-w-step" in html
    assert "<!-- wp:details -->" in html
    assert "gwp-w-related" in html
    # CTA buttons rendered
    assert "wp-block-button" in html


def test_quick_facts_table_and_cta_button():
    doc = read_file(SAMPLE)
    page, _t, _ = build_page(doc, _ctx())
    assert "Up to 900 lbs / 5 feet" in page.content_html
    # A 'CTA: Label | /url' bullet becomes a real button link.
    assert 'href="/tours/santa-cruz"' in page.content_html


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
    assert tpl.renderer == "wildlife_tier2"
    assert tpl.max_search_volume == 499


def test_tier2_builds_compact_layout():
    doc = read_file(SAMPLE2)
    page, template, _ = build_page(doc, _ctx())
    html = page.content_html
    assert template.key == "wildlife_tier2"
    assert "gwp-w-hero-light" in html            # minimal hero
    assert 'id="identification"' in html
    assert 'id="overview"' in html               # creative heading still mapped
    assert "gwp-w-tour" in html                  # tour cards
    assert "<!-- wp:details -->" in html         # mini FAQ accordion
    assert "gwp-w-footer-cta" in html
    # A 'CTA: Label | /url' bullet becomes a button.
    assert 'href="/tours/western-islands"' in page.content_html
    # No required-section warnings for the sample.
    assert not any("missing" in w for w in page.warnings)


def test_tier2_high_volume_warns():
    doc = read_file(SAMPLE2)
    doc.metadata["search_volume"] = "5000"
    page, _t, _ = build_page(doc, _ctx())
    assert any("exceeds" in w.lower() and "tier 2" in w.lower() for w in page.warnings)


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
