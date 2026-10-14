"""URL-section page-type routing: a doc is placed by the canonical URL's first
path segment (site.yaml routing.url_sections), so each section carries its own
template + parent + ACF group without a hand-picked type."""

from __future__ import annotations

from wp_publisher.models import Document
from wp_publisher.parse import detect_page_type
from wp_publisher.rendering.template import load_registry

_MAP = {
    "planning": "informative",
    "wildlife": "wildlife_single",
    "islands": "destination",
    "cruises": "informative",
}


def _doc(section: str, declared: str | None = None) -> Document:
    m = {"url_section": section}
    if declared:
        m["type"] = declared
    return Document(title="X", metadata=m)


def test_url_section_routes_page_type():
    reg = load_registry()
    assert detect_page_type(_doc("wildlife"), reg, _MAP)[0] == "wildlife_single"
    assert detect_page_type(_doc("islands"), reg, _MAP)[0] == "destination"
    assert detect_page_type(_doc("planning"), reg, _MAP)[0] == "informative"
    assert detect_page_type(_doc("cruises"), reg, _MAP)[0] == "informative"


def test_url_section_wins_over_declared_type():
    # A doc physically under /wildlife/ is a wildlife page even if it declares
    # something else — placement follows the URL.
    reg = load_registry()
    key, reason = detect_page_type(_doc("wildlife", declared="blog"), reg, _MAP)
    assert key == "wildlife_single"
    assert "URL section" in reason


def test_unmapped_section_falls_through_to_declared():
    reg = load_registry()
    key, _ = detect_page_type(_doc("random-section", declared="destination"), reg, _MAP)
    assert key == "destination"  # not in the map -> declared type is used


def test_no_map_uses_declared():
    reg = load_registry()
    key, _ = detect_page_type(_doc("wildlife", declared="destination"), reg, None)
    assert key == "destination"  # no routing configured -> declared type
