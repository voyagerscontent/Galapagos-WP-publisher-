"""End-to-end orchestration.

    Document --(detect type)--> Template
            --(SEO)----------> titles/slug/meta
            --(render)--------> Gutenberg blocks + media
            --(schema)--------> JSON-LD
            ==> RenderedPage

The pipeline does NOT talk to WordPress for publishing — it only optionally uses
a client for Media Library lookups. This keeps "build the page" and "push the
page" cleanly separable, so you can preview before anything goes live.
"""

from __future__ import annotations

from dataclasses import dataclass

from .config import Settings, get_settings
from .ingest import read_file
from .media.resolver import MediaResolver
from .models import Document, RenderedPage
from .parse import detect_page_type
from .rendering.renderer import RenderEngine
from .rendering.template import PageTemplate, TemplateRegistry, load_registry
from .schema import build_json_ld
from .seo import optimize_seo
from .utils import strip_html
from .wordpress.client import WordPressClient


@dataclass
class BuildContext:
    settings: Settings
    registry: TemplateRegistry
    wp_client: WordPressClient | None
    media_strategy: str | None = None


def build_page(
    doc: Document,
    ctx: BuildContext,
    *,
    page_type: str | None = None,
    status: str | None = None,
) -> tuple[RenderedPage, PageTemplate, str]:
    """Build a RenderedPage from a Document. Returns (page, template, reason)."""
    settings = ctx.settings

    # 1) Choose the template.
    if page_type:
        key, reason = page_type, "explicitly requested"
        ctx.registry.get(key)  # validate
    else:
        key, reason = detect_page_type(doc, ctx.registry)
    template = ctx.registry.get(key)

    # 2) SEO.
    seo = optimize_seo(doc, template, settings)

    # 3) Render content + media.
    resolver = MediaResolver(settings, ctx.wp_client, ctx.media_strategy)
    engine = RenderEngine(settings, resolver)
    content_html, media_items, featured, render_warnings = engine.render(doc, template)

    # 4) Determine canonical URL (for schema) from base + slug.
    page_url = f"{settings.wp_base_url}/{seo.slug}/" if settings.wp_base_url else f"/{seo.slug}/"
    featured_url = featured.url if (featured and featured.url) else None

    # 5) schema.org JSON-LD.
    json_ld = build_json_ld(
        doc,
        template,
        settings,
        url=page_url,
        title=doc.title,
        description=seo.meta_description,
        image_url=featured_url,
    )

    # 6) Resolve status precedence: arg > template > env/site default.
    final_status = status or template.status or settings.wp_default_status

    # 7) Categories/tags: doc metadata overrides template defaults.
    categories = _csv(doc.metadata.get("categories")) or list(template.categories)
    tags = _csv(doc.metadata.get("tags")) or list(template.tags)

    # 8) Post type: doc metadata ("post" | "page") overrides the template.
    post_type = _normalize_post_type(doc.metadata.get("post_type")) or template.post_type

    page = RenderedPage(
        title=doc.title,
        slug=seo.slug,
        content_html=content_html,
        excerpt=strip_html(seo.meta_description),
        status=final_status,
        post_type=post_type,
        categories=categories,
        tags=tags,
        featured_media=featured,
        media=media_items,
        seo_title=seo.seo_title,
        meta_description=seo.meta_description,
        focus_keyword=seo.focus_keyword,
        json_ld=json_ld,
        warnings=[*seo.warnings, *render_warnings],
    )
    return page, template, reason


def build_from_file(
    path: str,
    *,
    page_type: str | None = None,
    status: str | None = None,
    media_strategy: str | None = None,
    use_wordpress: bool = False,
    settings: Settings | None = None,
) -> tuple[RenderedPage, PageTemplate, str]:
    """Convenience: read a file and build a page in one call."""
    settings = settings or get_settings()
    registry = load_registry()
    client = None
    if use_wordpress or media_strategy == "library":
        client = WordPressClient(settings)
    ctx = BuildContext(
        settings=settings,
        registry=registry,
        wp_client=client,
        media_strategy=media_strategy,
    )
    doc = read_file(path)
    return build_page(doc, ctx, page_type=page_type, status=status)


def _normalize_post_type(value) -> str | None:
    if not value:
        return None
    v = str(value).strip().lower()
    if v in ("page", "pages"):
        return "page"
    if v in ("post", "posts"):
        return "post"
    return v  # allow a custom post type REST base


def _csv(value) -> list[str]:
    if not value:
        return []
    if isinstance(value, list):
        return [str(v).strip() for v in value if str(v).strip()]
    return [part.strip() for part in str(value).split(",") if part.strip()]
