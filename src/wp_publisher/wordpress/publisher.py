"""Turn a RenderedPage into WordPress and report the result."""

from __future__ import annotations

import json

from pydantic import BaseModel

from ..config import Settings
from ..models import RenderedPage
from .client import WordPressClient


class PublishResult(BaseModel):
    post_id: int
    url: str
    edit_url: str
    status: str
    created: bool
    warnings: list[str] = []


def _seo_meta(page: RenderedPage, plugin: str) -> dict[str, str]:
    """Map SEO fields onto the detected plugin's post-meta keys.

    Unknown keys are harmless: WordPress silently ignores meta it doesn't
    recognize, so sending these is safe even if the plugin isn't active.
    """
    if plugin == "yoast":
        return {
            "_yoast_wpseo_title": page.seo_title,
            "_yoast_wpseo_metadesc": page.meta_description,
            "_yoast_wpseo_focuskw": page.focus_keyword,
        }
    if plugin == "rankmath":
        return {
            "rank_math_title": page.seo_title,
            "rank_math_description": page.meta_description,
            "rank_math_focus_keyword": page.focus_keyword,
        }
    return {}


def _content_with_schema(page: RenderedPage) -> str:
    """Append the JSON-LD as a wp:html block.

    Embedding schema directly in the content guarantees it ships regardless of
    which (if any) SEO plugin is installed — the "least intervention" path.
    """
    if not page.json_ld:
        return page.content_html
    script = (
        '<script type="application/ld+json">'
        + json.dumps(page.json_ld, ensure_ascii=False, separators=(",", ":"))
        + "</script>"
    )
    schema_block = f"<!-- wp:html -->\n{script}\n<!-- /wp:html -->"
    return f"{page.content_html}\n\n{schema_block}"


def publish_page(
    client: WordPressClient,
    page: RenderedPage,
    settings: Settings,
    *,
    seo_plugin: str = "none",
    update_if_exists: bool = True,
) -> PublishResult:
    defaults = settings.defaults

    payload: dict = {
        "title": page.title,
        "slug": page.slug,
        "status": page.status,
        "content": _content_with_schema(page),
        "excerpt": page.excerpt or page.meta_description,
        "comment_status": defaults.get("comment_status", "closed"),
        "ping_status": defaults.get("ping_status", "closed"),
    }

    if settings.wp_default_author_id:
        payload["author"] = settings.wp_default_author_id

    # Taxonomies (only apply to the standard 'post' type by default).
    if page.post_type in ("post", "posts"):
        if page.categories:
            payload["categories"] = client.resolve_terms("category", page.categories)
        if page.tags:
            payload["tags"] = client.resolve_terms("post_tag", page.tags)

    # Featured image, if one was resolved from the library.
    if page.featured_media and page.featured_media.wp_media_id:
        payload["featured_media"] = page.featured_media.wp_media_id

    seo_meta = _seo_meta(page, seo_plugin)
    if seo_meta:
        payload["meta"] = seo_meta

    payload.update(page.extra_fields)

    # Create or update (idempotent on slug).
    existing = client.find_post_by_slug(page.post_type, page.slug) if update_if_exists else None
    if existing:
        result = client.update_post(page.post_type, existing["id"], payload)
        created = False
    else:
        result = client.create_post(page.post_type, payload)
        created = True

    post_id = result["id"]
    link = result.get("link", "")
    edit_url = f"{settings.wp_base_url}/wp-admin/post.php?post={post_id}&action=edit"
    return PublishResult(
        post_id=post_id,
        url=link,
        edit_url=edit_url,
        status=result.get("status", page.status),
        created=created,
        warnings=page.warnings,
    )
