"""A surgical publish (`only_acf_fields`) writes only the named ACF fields and
never creates a page."""

import pytest

from wp_publisher.config import get_settings
from wp_publisher.models import RenderedPage
from wp_publisher.wordpress.client import WordPressError
from wp_publisher.wordpress.publisher import publish_page


class FakeClient:
    """Minimal WordPressClient stand-in that records the update payload."""

    def __init__(self, existing=None):
        self._existing = existing
        self.updated_payload = None

    def find_post_by_slug(self, post_type, slug):
        return self._existing

    def get_post(self, post_type, post_id, context="edit"):
        return self._existing

    def update_post(self, post_type, post_id, payload):
        self.updated_payload = payload
        return {"id": post_id, "link": f"https://x/{post_id}", "status": "publish"}

    def create_post(self, post_type, payload):  # pragma: no cover - must not run
        raise AssertionError("surgical update must never create a post")


def _page():
    return RenderedPage(
        title="Santa Cruz",
        slug="santa-cruz-test",
        post_type="page",
        status="publish",
        excerpt="should not be sent",
        acf={
            "hero_title": "Santa Cruz",
            "intro": "long body the editor is still editing",
            "related_links": [{"label": "Cruises", "url": "/cruises/", "group": "Plan"}],
        },
    )


def test_surgical_writes_only_named_field_and_leaves_the_rest():
    existing = {"id": 11667, "status": "publish", "acf": {"related_links": []}}
    client = FakeClient(existing)
    publish_page(
        client, _page(), get_settings(),
        update_existing=True, only_acf_fields=["related_links"],
    )
    payload = client.updated_payload
    # Only related_links is written; hero_title / intro are not touched.
    assert set(payload["acf"].keys()) == {"related_links"}
    assert payload["acf"]["related_links"][0]["group"] == "Plan"
    # No title / excerpt / status are sent on a surgical patch.
    assert "title" not in payload
    assert "excerpt" not in payload
    assert "status" not in payload


def test_surgical_refuses_to_create_when_page_absent():
    client = FakeClient(existing=None)
    with pytest.raises(WordPressError, match="Refusing to create"):
        publish_page(
            client, _page(), get_settings(),
            update_existing=True, only_acf_fields=["related_links"],
        )
