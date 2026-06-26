"""Tests for the island ACF mapping (config/acf/island.yaml)."""

from pathlib import Path

from wp_publisher.acf import build_acf
from wp_publisher.acf.config import load_acf_config
from wp_publisher.config import get_settings
from wp_publisher.content import model as C
from wp_publisher.ingest import read_file
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.template import load_registry

SANTA_CRUZ = Path(__file__).resolve().parents[1] / "content" / "santa-cruz-island.docx"


def _cfg():
    cfg = load_acf_config("island")
    assert cfg.mode == "island"  # the profile file exists and loads
    return cfg


def test_island_profile_loads():
    cfg = _cfg()
    assert cfg.resolved_from_default is False
    assert cfg.island["hero"]["title"] == "hero_title"


def test_hero_faq_quickfacts_cta_and_features():
    components = [
        C.hero(heading="Santa Cruz", subheading="The hub island", image={}, ctas=[]),
        C.rich_text("<p>Charles Darwin Station…</p>", heading="Darwin Station"),
        C.stats([{"value": "986 km²", "label": "Area"}]),
        C.accordion([{"question": "Q1?", "answer": "<p>A1</p>"}]),
        C.cta(heading="Book", content="<p>Plan it</p>",
              ctas=[{"label": "Contact", "url": "https://x/contact"}]),
    ]
    acf, warnings = build_acf(components, _cfg())

    assert acf["hero_title"] == "Santa Cruz"
    assert acf["hero_subtitle"] == "The hub island"
    assert acf["quick_facts"] == [{"label": "Area", "value": "986 km²"}]
    # answer is a textarea -> clean text, no HTML tags.
    assert acf["faqs"] == [{"question": "Q1?", "answer": "A1"}]
    # CTA -> single repeater row tagged with an audience.
    assert acf["cta"][0]["audience"] == "Direct travelers"
    assert acf["cta"][0]["button_url"] == "https://x/contact"
    # Prose becomes a feature_sections row.
    titles = [r.get("title") for r in acf["feature_sections"]]
    assert "Darwin Station" in titles
    # No flat-group field names leak in.
    assert "hero_heading" not in acf and "body" not in acf and "faq" not in acf


def test_author_byline_maps_when_present():
    acf, _ = build_acf(
        [C.hero(heading="X", subheading="", image={}, ctas=[])],
        _cfg(), author="Juan Magallanes, Naturalist Expert Contributor",
    )
    assert acf["author"] == "Juan Magallanes, Naturalist Expert Contributor"


def test_no_seo_schema_field_in_island_output():
    components = [C.hero(heading="X", subheading="", image={}, ctas=[])]
    acf, _ = build_acf(components, _cfg(), schema_jsonld='{"@context":"x"}')
    # Island group has no SEO field; schema must not be written here.
    assert "seo_schema" not in acf


def test_santa_cruz_doc_extracts_geo_and_faqs():
    """Layer 2a: the GEO block -> geo_answer, and Q:/A: pairs -> faqs."""
    doc = read_file(SANTA_CRUZ)
    ctx = BuildContext(
        settings=get_settings(), registry=load_registry(), wp_client=None,
        media_strategy="placeholder",
    )
    page, template, _ = build_page(doc, ctx, page_type="destination")
    assert template.acf_profile == "island"
    acf = page.acf
    assert acf["geo_answer"].startswith("Santa Cruz is the most visited")
    assert len(acf["faqs"]) >= 3
    assert acf["faqs"][0]["question"].endswith("?")
    # Editorial "WEBMASTER: Do not publish" flags are surfaced and hold the draft.
    assert any("VERIFY" in w for w in page.warnings)


def test_santa_cruz_doc_extracts_tables():
    """Layer 2b: the facts table -> quick_facts, the sites table -> visitor_sites."""
    doc = read_file(SANTA_CRUZ)
    ctx = BuildContext(
        settings=get_settings(), registry=load_registry(), wp_client=None,
        media_strategy="placeholder",
    )
    page, _t, _ = build_page(doc, ctx, page_type="destination")
    acf = page.acf
    labels = [r["label"] for r in acf["quick_facts"]]
    assert "Location" in labels and "Area" in labels
    sites = {r["site_name"]: r for r in acf["visitor_sites"]}
    assert "Tortuga Bay" in sites
    assert sites["Tortuga Bay"]["access_type"] == "Land-based"
    assert sites["Cerro Dragón"]["access_type"] == "Cruise-only"
    # The extracted tables are not also duplicated into feature_sections.
    fs_text = " ".join(r.get("content", "") for r in acf["feature_sections"])
    assert "Black Turtle Cove" not in fs_text   # visitor-sites table not duplicated
    assert "864 m" not in fs_text               # quick-facts table not duplicated


def test_santa_cruz_doc_extracts_cta_and_sources():
    """Layer 2c-1: the CTA table -> dual cta; the Sources section -> sources."""
    doc = read_file(SANTA_CRUZ)
    ctx = BuildContext(
        settings=get_settings(), registry=load_registry(), wp_client=None,
        media_strategy="placeholder",
    )
    page, _t, _ = build_page(doc, ctx, page_type="destination")
    acf = page.acf
    audiences = {r["audience"] for r in acf["cta"]}
    assert {"Direct travelers", "Travel trade"} <= audiences
    # CTA button (label + URL) lives in a Word content control; it must be captured.
    direct = next(r for r in acf["cta"] if r["audience"] == "Direct travelers")
    assert direct["button_label"].startswith("Contact Voyagers")
    assert direct["button_url"] == "https://www.galapagosislands.travel/contact/"
    assert len(acf["sources"]) >= 3
    assert acf["sources"][0]["url"].startswith("http")
    # Sources are not duplicated as a feature section.
    titles = [r.get("title", "").lower() for r in acf["feature_sections"]]
    assert "sources" not in titles
