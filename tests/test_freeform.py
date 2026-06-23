"""Tests for the directive-driven freeform page builder."""

from pathlib import Path

from wp_publisher.config import get_settings
from wp_publisher.ingest import read_file
from wp_publisher.parse import detect_page_type
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.freeform import FreeformRenderer, parse_directives
from wp_publisher.rendering.template import load_registry

SAMPLE = Path(__file__).resolve().parents[1] / "samples" / "freeform-example.md"


def _ctx():
    return BuildContext(
        settings=get_settings(),
        registry=load_registry(),
        wp_client=None,
        media_strategy="placeholder",
    )


def test_freeform_registered_and_aliased():
    registry = load_registry()
    assert "freeform" in registry
    assert registry.get("freeform").renderer == "freeform"
    doc = read_file(SAMPLE)
    doc.metadata["type"] = "builder"
    key, _ = detect_page_type(doc, registry)
    assert key == "freeform"


def test_parse_directives_nesting():
    nodes = parse_directives("intro\n::: columns 60/40\n::: column\nA\n:::\n::: column\nB\n:::\n:::\n")
    kinds = [(n[0], n[1] if n[0] == "dir" else None) for n in nodes]
    assert ("text", None) in kinds
    assert ("dir", "columns") in kinds
    # The columns directive keeps its two column children in its inner text.
    cols = next(n for n in nodes if n[0] == "dir" and n[1] == "columns")
    inner_nodes = parse_directives(cols[3])
    assert sum(1 for n in inner_nodes if n[0] == "dir" and n[1] == "column") == 2


def test_post_type_override_to_page():
    doc = read_file(SAMPLE)
    page, template, _ = build_page(doc, _ctx())
    assert template.key == "freeform"
    assert page.post_type == "page"          # overridden via metadata
    assert page.slug == "why-travel-with-us"


def test_directives_render_to_blocks():
    doc = read_file(SAMPLE)
    page, _t, _ = build_page(doc, _ctx())
    h = page.content_html
    assert "gwp-hero" in h
    assert "gwp-cards" in h and "gwp-card" in h
    assert "gwp-callout-tip" in h
    assert 'wp:column {"width":"60%"}' in h and 'wp:column {"width":"40%"}' in h
    assert "<!-- wp:details -->" in h          # accordion
    assert "gwp-cta" in h
    assert "wp-block-button" in h


def test_meta_description_not_polluted_by_directives():
    doc = read_file(SAMPLE)
    page, _t, _ = build_page(doc, _ctx())
    assert ":::" not in page.meta_description
    assert "[button" not in page.meta_description
    assert "naturalists" in page.meta_description


def test_plain_markdown_without_directives():
    settings = get_settings()
    resolver_ctx = _ctx()
    doc = read_file(SAMPLE)
    doc.raw_body = "## Hello\n\nA simple paragraph with **bold** and a [link](https://x.io).\n\n- one\n- two"
    from wp_publisher.media.resolver import MediaResolver

    renderer = FreeformRenderer(settings, MediaResolver(settings, None, "placeholder"))
    html, _media, _featured, warnings = renderer.render(doc, resolver_ctx.registry.get("freeform"))
    assert "<!-- wp:heading -->" in html
    assert "<strong>bold</strong>" in html
    assert '<a href="https://x.io">link</a>' in html
    assert "<!-- wp:list -->" in html
    assert not warnings
