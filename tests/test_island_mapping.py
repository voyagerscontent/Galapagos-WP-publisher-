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
    # answer is now a WYSIWYG field -> HTML is preserved (links survive).
    assert acf["faqs"] == [{"question": "Q1?", "answer": "<p>A1</p>"}]
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


def test_santa_cruz_doc_extracts_travel_and_related_links():
    """Layer 2c-2: travel sections -> travel_information; footer -> related_links."""
    doc = read_file(SANTA_CRUZ)
    ctx = BuildContext(
        settings=get_settings(), registry=load_registry(), wp_client=None,
        media_strategy="placeholder",
    )
    page, _t, _ = build_page(doc, ctx, page_type="destination")
    acf = page.acf
    assert acf["travel_information"]["getting_there"]
    assert acf["travel_information"]["accommodation"]
    assert len(acf["related_links"]) >= 5
    # Domainless related links are made absolute.
    assert acf["related_links"][0]["url"].startswith("https://www.galapagosislands.travel/")
    # Those sections are not also duplicated as feature sections.
    titles = [r.get("title", "").lower() for r in acf["feature_sections"]]
    assert not any("getting to" in t or "explore more" in t or "where to stay" in t for t in titles)


def test_schema_jsonld_maps_to_seo_schema_field():
    components = [C.hero(heading="X", subheading="", image={}, ctas=[])]
    acf, _ = build_acf(components, _cfg(), schema_jsonld='{"@context":"x"}')
    # Page-specific JSON-LD is written to the island `seo_schema` field, which the
    # "Island Schema" Elementor widget prints per page.
    assert acf["seo_schema"] == '{"@context":"x"}'


def test_related_link_groups_flatten_with_group_column():
    """Grouped 'Explore More' links map to related_links rows that each carry
    their group heading, so the widget can render one titled column per group."""
    acf, _ = build_acf([], _cfg(), related_link_groups=[
        {"title": "Santa Cruz Essentials", "links": [
            {"label": "Giant Tortoise Guide", "url": "/wildlife/giant-tortoise/"},
            {"label": "Isabela Island", "url": "/islands/isabela/"},
        ]},
        {"title": "Plan Your Visit", "links": [{"label": "Cruises", "url": "/cruises/"}]},
    ])
    rl = acf["related_links"]
    assert len(rl) == 3
    assert rl[0]["group"] == "Santa Cruz Essentials"
    assert rl[0]["label"] == "Giant Tortoise Guide"
    assert rl[2]["group"] == "Plan Your Visit"
    # distinct group headings present
    assert {r["group"] for r in rl} == {"Santa Cruz Essentials", "Plan Your Visit"}


def test_geo_answer_inline_quick_answer():
    """A 'QUICK ANSWER: <prose>' box (marker + answer on one line, Baltra-style)
    yields the answer; a header-only 'AIO / GEO BLOCK' line does not."""
    from wp_publisher.ingest.docx_reader import _extract_geo_answer

    ans = _extract_geo_answer([["QUICK ANSWER: Baltra Island is the main air "
                                "gateway to the Galápagos and has little to see."]])
    assert ans.startswith("Baltra Island is the main air gateway")
    # A header-only marker with trailing header tokens must not be captured.
    assert _extract_geo_answer([["AIO / GEO BLOCK — internal answer extraction"]]) == ""


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
    # Internal links render as real anchors in wysiwyg feature sections,
    # while the textarea CTA text keeps no raw markdown link syntax.
    fs_html = " ".join(r.get("content", "") for r in acf["feature_sections"])
    assert "<a href=" in fs_html
    assert all("](" not in r.get("text", "") for r in acf["cta"])


def test_fix_schema_url_uses_real_published_url():
    """The schema's page URL is rewritten to base + parent path + final slug,
    not the title-derived slug the JSON-LD was first built with."""
    import json as _json
    from types import SimpleNamespace
    from wp_publisher.cli import _apply_slug, _fix_schema_url

    graph = {"@graph": [{"@type": "TouristDestination",
                         "url": "https://site/santa-cruz-island-the-complete-guide/"}]}
    page = SimpleNamespace(
        slug="santa-cruz-island-the-complete-guide", parent_slug="islands",
        json_ld=graph,
        acf={"seo_schema": _json.dumps(graph, ensure_ascii=False, separators=(",", ":"))},
    )
    settings = SimpleNamespace(wp_base_url="https://site")
    _apply_slug(page, "santa-cruz", False)
    _fix_schema_url(page, settings)
    assert _json.loads(page.acf["seo_schema"])["@graph"][0]["url"] == \
        "https://site/islands/santa-cruz/"
