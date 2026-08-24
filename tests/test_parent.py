"""Tests for section parent-page nesting (template -> page -> publisher)."""

from pathlib import Path

from wp_publisher.config import get_settings
from wp_publisher.ingest import read_file
from wp_publisher.models import RenderedPage
from wp_publisher.pipeline import BuildContext, build_page
from wp_publisher.rendering.template import load_registry
from wp_publisher.wordpress.publisher import publish_page

DESTINATION_DOC = Path(__file__).resolve().parents[1] / "content" / "docdepruebainicial.docx"


def _ctx():
    return BuildContext(
        settings=get_settings(),
        registry=load_registry(),
        wp_client=None,
        media_strategy="placeholder",
    )


class StubClient:
    """Minimal WordPressClient stand-in for publisher unit tests."""

    def __init__(self, pages=None):
        # slug -> existing page dict
        self.pages = pages or {}
        self.created_payload = None

    def find_post_by_slug(self, post_type, slug):
        return self.pages.get(slug)

    def create_post(self, post_type, payload):
        self.created_payload = payload
        return {"id": 101, "link": "https://example.test/p/101", "status": payload.get("status", "draft")}

    def update_post(self, post_type, post_id, payload):
        self.created_payload = payload
        return {"id": post_id, "link": f"https://example.test/p/{post_id}", "status": "draft"}


# --- template + pipeline --------------------------------------------------- #


def test_destination_template_declares_islands_parent():
    tpl = load_registry().get("destination")
    assert tpl.parent_page == "islands"


def test_wildlife_templates_have_no_parent():
    reg = load_registry()
    assert reg.get("wildlife_tier1").parent_page is None
    assert reg.get("wildlife_tier2").parent_page is None


def test_pipeline_sets_parent_slug_for_islands():
    doc = read_file(DESTINATION_DOC)
    page, template, _ = build_page(doc, _ctx())
    assert template.key == "destination"
    assert page.parent_slug == "islands"


# --- publisher resolution -------------------------------------------------- #


def _page(**kw):
    base = dict(title="X", slug="santa-cruz", post_type="page", status="draft")
    base.update(kw)
    return RenderedPage(**base)


def test_publish_sets_parent_when_found():
    client = StubClient(pages={"islands": {"id": 42, "status": "publish"}})
    page = _page(parent_slug="islands")
    result = publish_page(client, page, get_settings())
    assert result.created
    assert client.created_payload["parent"] == 42


def test_publish_warns_when_parent_missing():
    client = StubClient(pages={})  # 'islands' not found, slug free
    page = _page(parent_slug="islands")
    result = publish_page(client, page, get_settings())
    assert "parent" not in client.created_payload
    assert any("not found" in w for w in result.warnings)


def test_publish_skips_parent_for_non_hierarchical_post():
    client = StubClient(pages={"wildlife": {"id": 7}})
    page = _page(post_type="post", slug="giant-tortoise", parent_slug="wildlife")
    result = publish_page(client, page, get_settings())
    assert "parent" not in client.created_payload
    assert any("not hierarchical" in w for w in result.warnings)
