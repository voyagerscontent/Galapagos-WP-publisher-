"""Tests for the Voyagers 'CMS Stage' house-format adapter."""

from pathlib import Path

from wp_publisher.config import get_settings
from wp_publisher.ingest import read_file
from wp_publisher.ingest.cms import looks_like_cms
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.template import load_registry

DOC = Path(__file__).resolve().parents[1] / "content" / "santa-fe-island.docx"


def _ctx():
    return BuildContext(
        settings=get_settings(),
        registry=load_registry(),
        wp_client=None,
        media_strategy="placeholder",
    )


def test_detects_house_format():
    assert looks_like_cms(["PUBLISHER HEADER BLOCK", "Publisher: X"])
    assert not looks_like_cms(["# A normal markdown title", "Some prose."])


def test_header_block_becomes_metadata():
    doc = read_file(DOC)
    assert "Santa Fe Island" in doc.title
    assert doc.metadata["type"] == "destination"
    assert doc.metadata["slug"] == "santa-fe"
    assert "Santa Fe is a small" in doc.metadata["meta_description"]
    assert doc.metadata.get("author") == "Juan Magallanes"


def test_composes_into_island_acf_fields():
    doc = read_file(DOC)
    page, template, _ = build_page(doc, _ctx())
    assert template.key == "destination"
    assert template.acf_profile == "island"
    assert page.post_type == "page"
    assert page.slug == "santa-fe"
    acf = page.acf
    assert "Santa Fe Island" in acf["hero_title"]
    assert acf["hero_subtitle"]                   # quick answer
    assert len(acf["faqs"]) == 5                  # FAQ -> faqs repeater
    assert acf["feature_sections"]                # prose folded into feature sections
    # Layer-1 island mapping uses the island group's field names, not flat ones.
    assert "hero_heading" not in acf and "body" not in acf


def test_verify_flags_surface_and_hold_draft():
    doc = read_file(DOC)
    page, _t, _ = build_page(doc, _ctx())
    assert page.status == "draft"
    assert any(w.startswith("VERIFY:") for w in page.warnings)
    assert any("DRAFT pending VERIFY" in w for w in page.warnings)


def test_schema_is_destination_plus_faqpage():
    doc = read_file(DOC)
    page, _t, _ = build_page(doc, _ctx())
    types = {e["@type"] for e in page.json_ld["@graph"]}
    assert {"TouristDestination", "FAQPage"} <= types
