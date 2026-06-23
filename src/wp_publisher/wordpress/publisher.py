"""Turn a RenderedPage into WordPress and report the result."""

from __future__ import annotations

from pydantic import BaseModel

from ..config import Settings
from ..models import RenderedPage
from .client import WordPressClient, WordPressError


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


def publish_page(
    client: WordPressClient,
    page: RenderedPage,
    settings: Settings,
    *,
    seo_plugin: str = "none",
    update_existing: bool = False,
) -> PublishResult:
    """Create a post, or update one with the same slug.

    Safety: if a post with this slug already exists, we **refuse** unless
    ``update_existing`` is explicitly set — this prevents accidentally
    overwriting an unrelated live page that happens to share the slug. Even when
    updating, we never change the existing post's ``status`` and never blank its
    ``content``, so an existing page can't be unpublished or emptied by a run.
    """
    defaults = settings.defaults

    # Fields safe to set on both create and update.
    payload: dict = {
        "title": page.title,
        "slug": page.slug,
        "excerpt": page.excerpt or page.meta_description,
    }
    if page.acf:
        payload["acf"] = page.acf
    if settings.wp_default_author_id:
        payload["author"] = settings.wp_default_author_id
    if page.post_type in ("post", "posts"):
        if page.categories:
            payload["categories"] = client.resolve_terms("category", page.categories)
        if page.tags:
            payload["tags"] = client.resolve_terms("post_tag", page.tags)
    if page.featured_media and page.featured_media.wp_media_id:
        payload["featured_media"] = page.featured_media.wp_media_id
    seo_meta = _seo_meta(page, seo_plugin)
    if seo_meta:
        payload["meta"] = seo_meta
    payload.update(page.extra_fields)

    existing = client.find_post_by_slug(page.post_type, page.slug)
    if existing and not update_existing:
        raise WordPressError(
            f"A {page.post_type} with slug '{page.slug}' already exists "
            f"(#{existing['id']}, status '{existing.get('status')}'). Refusing to "
            f"overwrite it. Use a different slug, or pass --update to deliberately "
            f"update that post."
        )

    if existing:
        # Non-destructive update: do NOT send status (never unpublish) and do NOT
        # send content (never blank an existing page's body).
        result = client.update_post(page.post_type, existing["id"], payload)
        created = False
    else:
        payload["status"] = page.status
        payload["content"] = page.content_html
        payload["comment_status"] = defaults.get("comment_status", "closed")
        payload["ping_status"] = defaults.get("ping_status", "closed")
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
