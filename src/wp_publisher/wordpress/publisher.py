"""Turn a RenderedPage into WordPress and report the result."""

from __future__ import annotations

import html
import re

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
    only_acf_fields: list[str] | None = None,
) -> PublishResult:
    """Create a post, or update one with the same slug.

    Safety: if a post with this slug already exists, we **refuse** unless
    ``update_existing`` is explicitly set — this prevents accidentally
    overwriting an unrelated live page that happens to share the slug. Even when
    updating, we never change the existing post's ``status`` and never blank its
    ``content``, so an existing page can't be unpublished or emptied by a run.

    ``only_acf_fields`` enables a **surgical** update: only those ACF fields are
    written and nothing else on the post is touched (no title, excerpt, featured
    image or SEO meta). Because a surgical run carries almost no page, it also
    **refuses to create** a new post — it can only patch a post that already
    exists — so it can never leave a near-empty page behind.
    """
    defaults = settings.defaults
    surgical = only_acf_fields is not None

    # Fields safe to set on both create and update.
    payload: dict = {"slug": page.slug}
    if not surgical:
        payload["title"] = page.title
        payload["excerpt"] = page.excerpt or page.meta_description
    if page.acf:
        acf = page.acf
        if surgical:
            wanted = set(only_acf_fields)
            acf = {k: v for k, v in acf.items() if k in wanted}
        if acf:
            payload["acf"] = acf
    parent_warning = None
    if not surgical:
        if settings.wp_default_author_id:
            payload["author"] = settings.wp_default_author_id
        if page.post_type in ("post", "posts"):
            if page.categories:
                payload["categories"] = client.resolve_terms("category", page.categories)
            if page.tags:
                payload["tags"] = client.resolve_terms("post_tag", page.tags)
        if page.featured_media and page.featured_media.wp_media_id:
            payload["featured_media"] = page.featured_media.wp_media_id
        parent_warning = _resolve_parent(client, page, payload)
        seo_meta = _seo_meta(page, seo_plugin)
        if seo_meta:
            payload["meta"] = seo_meta
        payload.update(page.extra_fields)
        # Extra post meta (e.g. the page-type marker used to attach the ACF group
        # and target an Elementor template) — MERGED into meta so it never clobbers
        # the SEO meta above.
        if page.wp_meta:
            payload.setdefault("meta", {}).update(page.wp_meta)

    existing = client.find_post_by_slug(page.post_type, page.slug)
    if surgical and not existing:
        raise WordPressError(
            f"Surgical update (only {', '.join(only_acf_fields)}) requested but no "
            f"{page.post_type} with slug '{page.slug}' exists. Refusing to create a "
            f"near-empty page — create the page first, then re-run to patch it."
        )
    if existing and not update_existing:
        raise WordPressError(
            f"A {page.post_type} with slug '{page.slug}' already exists "
            f"(#{existing['id']}, status '{existing.get('status')}'). Refusing to "
            f"overwrite it. Use a different slug, or pass --update to deliberately "
            f"update that post."
        )

    if existing:
        # Non-destructive update: do NOT send status (never unpublish) and do NOT
        # send content (never blank an existing page's body). Also carry over any
        # editor-uploaded repeater sub-fields (icons/images) the payload doesn't
        # set, so a republish can't wipe them.
        #
        # The slug lookup uses the list endpoint, whose ACF payload can be
        # incomplete (context 'view' drops repeater sub-fields). Re-read the post
        # with context=edit so the preserve step sees the real, full ACF.
        try:
            full = client.get_post(page.post_type, existing["id"], context="edit")
            if isinstance(full, dict) and full.get("acf"):
                existing = full
        except WordPressError:
            pass  # fall back to the list-endpoint copy we already have
        _preserve_unmanaged_subfields(existing, payload, page.warnings)
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
    warnings = [*page.warnings, parent_warning] if parent_warning else page.warnings
    return PublishResult(
        post_id=post_id,
        url=link,
        edit_url=edit_url,
        status=result.get("status", page.status),
        created=created,
        warnings=warnings,
    )


def _preserve_unmanaged_subfields(existing: dict, payload: dict, warnings: list | None = None) -> None:
    """Keep editor-managed repeater sub-fields across a republish.

    The generated payload rebuilds each repeater from the document, which would
    otherwise blank sub-fields the engine never sets — e.g. an icon or image the
    editor uploaded in WordPress. For every repeater the payload sends, match each
    new row to the live row by its managed content and copy over any sub-field the
    new row omits. Fully site-agnostic: it never names a specific field.

    Fail-safe: if the live ACF could not be read at all (empty), a republish
    cannot know which media to carry over — so rather than blank every repeater,
    the repeaters are dropped from the payload (left untouched on the page) and a
    warning is recorded. Better to skip a content update than to wipe uploads.
    """
    new_acf = payload.get("acf")
    if not isinstance(new_acf, dict):
        return
    old_acf = (existing or {}).get("acf")
    if not isinstance(old_acf, dict) or not old_acf:
        dropped = [f for f, rows in new_acf.items() if _is_row_list(rows)]
        for field in dropped:
            del new_acf[field]
        if dropped and warnings is not None:
            warnings.append(
                "Could not read the live page's ACF, so repeater fields "
                f"({', '.join(dropped)}) were left untouched to avoid wiping "
                "editor-uploaded images/icons. Re-run once ACF is readable to "
                "update them."
            )
        return
    for field, new_rows in new_acf.items():
        old_rows = old_acf.get(field)
        if not _is_row_list(new_rows) or not _is_row_list(old_rows):
            continue
        used: set[int] = set()
        for new_row in new_rows:
            idx = _match_row(new_row, old_rows, used)
            if idx is None:
                continue
            used.add(idx)
            for key, value in old_rows[idx].items():
                if key not in new_row and not _is_blank(value):
                    new_row[key] = _as_acf_value(value)


def _is_row_list(value) -> bool:
    return isinstance(value, list) and len(value) > 0 and all(isinstance(r, dict) for r in value)


def _is_blank(value) -> bool:
    return value in (None, "", False, 0, [], {})


def _norm(value) -> str:
    """Normalize a managed field for matching. Strips HTML tags and decodes
    entities so a WYSIWYG sub-field still matches after WordPress rewrites it on
    save (wpautop, entity-encoding, stray newlines) — otherwise a title-less
    card's content would drift and its uploaded image would be dropped."""
    text = re.sub(r"<[^>]+>", " ", str(value))  # drop tags -> compare visible text
    text = html.unescape(text)                  # &amp;/&#8594; -> &/→
    return " ".join(text.split()).strip().lower()


def _as_acf_value(value):
    """An image/file sub-field may come back from REST as an object; ACF's update
    accepts the attachment ID, so reduce {id: N, …} -> N."""
    if isinstance(value, dict) and "id" in value:
        return value["id"]
    return value


def _match_row(new_row: dict, old_rows: list, used: set) -> int | None:
    """Find the live row for a new row: prefer an all-managed-fields match, then
    fall back to matching on the row's first managed field (its identity/label)."""
    keys = [k for k in new_row if not _is_blank(new_row[k])]
    if not keys:
        return None
    fallback = None
    for i, old in enumerate(old_rows):
        if i in used:
            continue
        if all(k in old and _norm(old[k]) == _norm(new_row[k]) for k in keys):
            return i
        if fallback is None and keys[0] in old and _norm(old[keys[0]]) == _norm(new_row[keys[0]]):
            fallback = i
    return fallback


def _resolve_parent(client: WordPressClient, page: RenderedPage, payload: dict) -> str | None:
    """Resolve ``page.parent_slug`` to a page ID and set ``payload['parent']``.

    Returns a warning string if a parent slug was given but no matching page was
    found (so the post is still published, just without nesting — and a
    section-scoped ACF group located by page_parent would not attach).
    """
    slug = (page.parent_slug or "").strip()
    if not slug:
        # No parent for this page type (e.g. informative / standalone). Set 0
        # EXPLICITLY so a republish CLEARS any stale parent — otherwise a page
        # that once nested under "Galapagos Wildlife" (id 9657) keeps that
        # parent, and the wildlife ACF group (located by page_parent) stays
        # wrongly attached alongside the correct one.
        payload["parent"] = 0
        return None
    if page.post_type in ("post", "posts"):
        # Only hierarchical types (pages / hierarchical CPTs) support `parent`.
        return (
            f"parent_page '{slug}' is set but post type '{page.post_type}' is not "
            f"hierarchical; parent ignored. Use a page or hierarchical CPT to nest."
        )
    try:
        parent = client.find_post_by_slug("page", slug)
    except WordPressError:
        return f"Could not look up parent page '{slug}'; published without a parent."
    if not parent:
        return (
            f"Parent page '{slug}' not found — published without a parent. "
            f"Create a page with that slug, or section-scoped ACF fields won't attach."
        )
    payload["parent"] = parent["id"]
    return None
