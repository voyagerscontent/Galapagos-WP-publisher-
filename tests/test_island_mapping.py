"""Tests for the Layer-1 island ACF mapping (config/acf/island.yaml)."""

from wp_publisher.acf import build_acf
from wp_publisher.acf.config import load_acf_config
from wp_publisher.content import model as C


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
    assert acf["faqs"] == [{"question": "Q1?", "answer": "<p>A1</p>"}]
    # CTA -> single repeater row tagged with an audience.
    assert acf["cta"][0]["audience"] == "Direct travelers"
    assert acf["cta"][0]["button_url"] == "https://x/contact"
    # Prose becomes a feature_sections row.
    titles = [r.get("title") for r in acf["feature_sections"]]
    assert "Darwin Station" in titles
    # No flat-group field names leak in.
    assert "hero_heading" not in acf and "body" not in acf and "faq" not in acf


def test_no_seo_schema_field_in_island_output():
    components = [C.hero(heading="X", subheading="", image={}, ctas=[])]
    acf, _ = build_acf(components, _cfg(), schema_jsonld='{"@context":"x"}')
    # Island group has no SEO field; schema must not be written here.
    assert "seo_schema" not in acf
