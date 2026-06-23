"""End-to-end orchestration.

    Document --(detect type)--> Template (schema/category profile only)
            --(SEO)----------> titles/slug/meta
            --(compose)------> structured components (deterministic, or bespoke
                               directives) + media
            --(schema)-------> JSON-LD
            --(ACF map)------> acf field payload
            ==> RenderedPage

Output targets **ACF fields**, not Gutenberg blocks — the WordPress theme
renders the populated fields. "Build the page" and "push the page" stay
separable, so you can preview the ACF payload before anything goes live.
"""

from __future__ import annotations

import json
from dataclasses import dataclass

from .acf import build_acf
from .acf.config import AcfConfig, get_acf_config
from .config import Settings, get_settings
from .content.compose import Composer
from .ingest import read_file
from .media.resolver import MediaResolver
from .models import Document, RenderedPage
from .parse import detect_page_type
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
    acf_config: AcfConfig | None = None


def build_page(
    doc: Document,
    ctx: BuildContext,
    *,
    page_type: str | None = None,
    status: str | None = None,
) -> tuple[RenderedPage, PageTemplate, str]:
    """Build a RenderedPage (ACF payload) from a Document. Returns (page, template, reason)."""
    settings = ctx.settings
    acf_config = ctx.acf_config or get_acf_config()

    # 1) Choose the page-type profile (drives schema + default categories).
    if page_type:
        key, reason = page_type, "explicitly requested"
        ctx.registry.get(key)  # validate
    else:
        key, reason = detect_page_type(doc, ctx.registry)
    template = ctx.registry.get(key)

    # 2) SEO.
    seo = optimize_seo(doc, template, settings)

    # 3) Compose the document into structured components (+ resolve media).
    resolver = MediaResolver(settings, ctx.wp_client, ctx.media_strategy)
    composer = Composer(settings, resolver)
    components, compose_warnings = composer.compose(doc)
    featured = _first_featured(composer.media_items)

    # 4) Canonical URL + schema.org JSON-LD.
    page_url = f"{settings.wp_base_url}/{seo.slug}/" if settings.wp_base_url else f"/{seo.slug}/"
    featured_url = featured.url if (featured and featured.url) else None
    json_ld = build_json_ld(
        doc,
        template,
        settings,
        url=page_url,
        title=doc.title,
        description=seo.meta_description,
        image_url=featured_url,
    )

    # 5) Map components -> ACF payload.
    subtitle = str(doc.metadata.get("tagline") or doc.metadata.get("subtitle") or "")
    acf_payload, acf_warnings = build_acf(
        components,
        acf_config,
        subtitle=subtitle,
        schema_jsonld=json.dumps(json_ld, ensure_ascii=False, separators=(",", ":")),
    )

    # 6) Status / taxonomies / post type.
    final_status = status or template.status or settings.wp_default_status
    categories = _csv(doc.metadata.get("categories")) or list(template.categories)
    tags = _csv(doc.metadata.get("tags")) or list(template.tags)
    post_type = _normalize_post_type(doc.metadata.get("post_type")) or template.post_type

    page = RenderedPage(
        title=doc.title,
        slug=seo.slug,
        acf=acf_payload,
        content_html="",  # ACF-driven; the theme renders the fields
        excerpt=strip_html(seo.meta_description),
        status=final_status,
        post_type=post_type,
        categories=categories,
        tags=tags,
        featured_media=featured,
        media=composer.media_items,
        seo_title=seo.seo_title,
        meta_description=seo.meta_description,
        focus_keyword=seo.focus_keyword,
        json_ld=json_ld,
        warnings=[
            *seo.warnings,
            *_template_warnings(template, doc),
            *doc.metadata.get("_ingest_warnings", []),
            *compose_warnings,
            *acf_warnings,
        ],
    )
    return page, template, reason


def _template_warnings(template: PageTemplate, doc: Document) -> list[str]:
    """Content-QA warnings: missing expected sections and search-volume tier."""
    out: list[str] = []
    for slug in template.required_sections:
        if doc.find_section(slug) is None:
            out.append(f"Expected section '{slug}' is missing from the document.")

    vol = _to_int(doc.metadata.get("search_volume"))
    if template.min_search_volume:
        if vol is None:
            out.append(
                f"No 'search_volume' given; this template targets "
                f"{template.min_search_volume}+ monthly searches."
            )
        elif vol < template.min_search_volume:
            out.append(
                f"search_volume ({vol}) is below the {template.min_search_volume} "
                f"threshold; consider a lighter template."
            )
    if template.max_search_volume and vol is not None and vol > template.max_search_volume:
        out.append(
            f"search_volume ({vol}) exceeds this template's cap "
            f"({template.max_search_volume}); consider a richer template."
        )
    return out


def _to_int(value) -> int | None:
    if value is None:
        return None
    digits = "".join(ch for ch in str(value) if ch.isdigit())
    return int(digits) if digits else None


def _first_featured(media_items):
    """The first resolved library image becomes the post's featured image."""
    for item in media_items:
        if item.source == "library" and item.wp_media_id:
            return item
    return media_items[0] if media_items else None


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
