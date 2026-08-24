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
    page, template, reason = build_page(doc, _ctx())
    # No explicit page_type (the generic "publish a page" workflow): a pages-only
    # site never auto-produces a CPT, so the cruise sample is re-routed to the
    # page default (informative) and published as a PAGE. The cruise CPT is only
    # reached via an explicit page_type=cruise (see the test below).
    assert template.post_type == "page"
    assert "re-routed" in reason
    assert page.post_type == "page"
    assert page.slug.startswith("8-day-galapagos-cruise")
    assert page.status == "draft"


def test_itineraries_and_experts_page_types_are_registered():
    # The n8n Itineraries / Experts forms dispatch `--type itineraries` /
    # `--type experts`; both must resolve to a page-type profile (not KeyError).
    # itineraries publishes to the dedicated `itinerary` CPT; experts publishes
    # as a page. Both carry the gp_page_type marker so the matching ACF group
    # attaches.
    reg = load_registry()
    assert reg.get("itineraries").post_type == "itinerary"
    assert reg.get("experts").post_type == "page"
    for key in ("itineraries", "experts"):
        assert reg.get(key).acf_profile == key

    doc = read_file(SAMPLE)
    # Explicit page_type is honored (the dedicated CPT is NOT flattened to page).
    page, template, reason = build_page(doc, _ctx(), page_type="itineraries")
    assert template.key == "itineraries"
    assert page.post_type == "itinerary"
    # The marker equals the page-type KEY (itineraries), not the CPT slug.
    assert page.wp_meta.get("gp_page_type") == "itineraries"
    assert "explicitly requested" in reason


def test_cruise_cpt_via_explicit_page_type():
    # The dedicated cruise n8n workflow forces page_type=cruise; the resulting
    # page must publish to the cruise CPT (not flattened to page).
    doc = read_file(SAMPLE)
    page, template, _r = build_page(doc, _ctx(), page_type="cruise")
    assert template.post_type == "cruise"
    assert page.post_type == "cruise"


def test_cruise_maps_into_cruise_page_widget_fields():
    # The cruise profile must fill the "Cruise Page" group's widget repeaters
    # (feature_sections, faqs) from the document — not fall back to the bare
    # config/acf.yaml, which left them empty.
    doc = read_file(SAMPLE)
    page, template, _r = build_page(doc, _ctx(), page_type="cruise")
    assert template.acf_profile == "cruise"
    assert len(page.acf.get("feature_sections") or []) > 0
    assert page.acf["feature_sections"][0]["title"]
    assert len(page.acf.get("faqs") or []) > 0


def test_doc_declared_post_type_still_wins_over_cpt():
    # An explicit post_type in the document header has the highest precedence,
    # so an author can still force a cruise doc onto a plain page if needed.
    doc = read_file(SAMPLE)
    doc.metadata["post_type"] = "page"
    page, _t, _r = build_page(doc, _ctx())
    assert page.post_type == "page"


def test_output_is_flat_acf_fields():
    doc = read_file(SAMPLE)
    page, _t, _r = build_page(doc, _ctx())
    # post_content is empty; content lives in flat ACF fields (Elementor-bound).
    assert page.content_html == ""
    acf = page.acf
    # The cruise sample maps via the cruise profile into feature_sections; the
    # body HTML is plain semantic HTML, never Gutenberg block markup.
    sections = acf.get("feature_sections") or []
    assert sections and sections[0]["title"]
    assert "<p>" in sections[0]["content"] and "<!-- wp:" not in sections[0]["content"]
    assert isinstance(acf.get("faqs"), list) and acf["faqs"][0]["question"]
    # Schema is never generated: a doc with no schema block leaves seo_schema empty.
    assert not acf.get("seo_schema")


def test_schema_from_document_standardizes_both_styles():
    """Both authoring styles normalize to ONE @graph, and the engine never
    invents schema."""
    import json as _json

    from wp_publisher.ingest.docx_reader import _extract_schema_jsonld

    # Baltra style: several separate labeled blocks -> combined into one @graph.
    multi = (
        'TouristAttraction:\n{ "@context": "https://schema.org", "@type": '
        '"TouristAttraction", "name": "Baltra" }\n\nFAQPage:\n{ "@context": '
        '"https://schema.org", "@type": "FAQPage", "mainEntity": [] }'
    )
    out = _json.loads(_extract_schema_jsonld(multi))
    assert out["@context"] == "https://schema.org"
    assert [n["@type"] for n in out["@graph"]] == ["TouristAttraction", "FAQPage"]

    # Santa Cruz style: a single @graph block is kept as one @graph.
    graph = ('SCHEMA JSON-LD:\n{"@context":"https://schema.org","@graph":['
             '{"@type":"Article"},{"@type":"BreadcrumbList"}]}')
    out2 = _json.loads(_extract_schema_jsonld(graph))
    assert [n["@type"] for n in out2["@graph"]] == ["Article", "BreadcrumbList"]

    # Word curly quotes and literal newlines inside strings still parse.
    smart = '{ “@context”: “https://schema.org”,\n “@type”: “Thing”,\n "name": "a\nb" }'
    _json.loads(_extract_schema_jsonld(smart))  # must not raise

    # No schema in the text -> empty (never generated).
    assert _extract_schema_jsonld("just prose, no schema here") == ""


def test_seo_fields():
    doc = read_file(SAMPLE)
    page, _t, _r = build_page(doc, _ctx())
    assert page.focus_keyword == "Galapagos cruise"
    assert page.meta_description
    assert len(page.seo_title) <= 64  # title_max + small slack


def test_schema_jsonld():
    doc = read_file(SAMPLE)
    # TouristTrip schema is a cruise-profile trait, so request it explicitly.
    page, _t, _r = build_page(doc, _ctx(), page_type="cruise")
    graph = page.json_ld["@graph"]
    types = {entity["@type"] for entity in graph}
    assert "TouristTrip" in types
    assert "FAQPage" in types
    trip = next(e for e in graph if e["@type"] == "TouristTrip")
    assert trip["offers"]["price"] == "5495"
    assert len(trip["itinerary"]) == 8


def test_missing_required_section_warns():
    doc = read_file(SAMPLE)
    # Drop the itinerary to trigger a required-section warning (the cruise
    # profile requires it, so request that profile explicitly).
    doc.sections = [s for s in doc.sections if s.slug != "itinerary"]
    page, _t, _r = build_page(doc, _ctx(), page_type="cruise")
    assert any("itinerary" in w for w in page.warnings)
