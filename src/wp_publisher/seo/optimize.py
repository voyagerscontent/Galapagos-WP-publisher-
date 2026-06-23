"""Derive SEO fields from a document + template + site config.

Outputs are intentionally conservative and deterministic so results are
reviewable. The focus keyword drives meta generation; if the author supplied
one we trust it, otherwise we infer from the title/destination.
"""

from __future__ import annotations

import re

from pydantic import BaseModel

from ..config import Settings
from ..models import Document
from ..rendering.template import PageTemplate
from ..utils import first_sentences, to_slug, truncate_at_word

_STOPWORDS = {
    "the", "a", "an", "and", "or", "to", "of", "in", "on", "for", "with",
    "your", "our", "you", "this", "that", "is", "are", "best", "guide",
}


class SeoResult(BaseModel):
    seo_title: str
    slug: str
    meta_description: str
    focus_keyword: str
    warnings: list[str] = []


def optimize_seo(
    doc: Document, template: PageTemplate, settings: Settings
) -> SeoResult:
    seo_cfg = settings.seo
    warnings: list[str] = []

    title = (doc.metadata.get("title") or doc.title or "").strip()

    # --- Focus keyword ----------------------------------------------------
    focus = (
        doc.metadata.get("focus_keyword")
        or doc.metadata.get("keyword")
        or ""
    ).strip()
    if not focus and template.focus_keyword_from:
        focus = str(doc.metadata.get(template.focus_keyword_from, "")).strip()
    if not focus:
        focus = _infer_keyword(title)

    # --- Slug -------------------------------------------------------------
    slug = (doc.metadata.get("slug") or "").strip() or to_slug(title)

    # --- SEO title --------------------------------------------------------
    title_max = int(seo_cfg.get("title_max", 60))
    suffix = seo_cfg.get("title_suffix", "")
    seo_title = title
    if focus and focus.lower() not in seo_title.lower():
        warnings.append(
            f"Focus keyword '{focus}' is not present in the title; consider adding it."
        )
    if suffix and (len(title) + len(suffix)) <= title_max:
        seo_title = f"{title}{suffix}"
    if len(seo_title) > title_max:
        seo_title = truncate_at_word(seo_title, title_max)
        warnings.append(f"Title trimmed to {title_max} characters for SEO.")

    # --- Meta description -------------------------------------------------
    desc = (doc.metadata.get("meta_description") or doc.metadata.get("description") or "").strip()
    if not desc:
        desc = _build_description(doc)
    desc_max = int(seo_cfg.get("meta_description_max", 156))
    desc_min = int(seo_cfg.get("meta_description_min", 70))
    desc = first_sentences(desc, desc_max) if len(desc) > desc_max else desc
    if focus and focus.lower() not in desc.lower():
        # Try to weave the keyword in at the front without exceeding the cap.
        candidate = f"{focus}: {desc}"
        desc = candidate if len(candidate) <= desc_max else desc
    if len(desc) < desc_min:
        warnings.append(
            f"Meta description is short ({len(desc)} chars); aim for {desc_min}-{desc_max}."
        )
    if not desc:
        warnings.append("No meta description could be generated; add an overview section.")

    return SeoResult(
        seo_title=seo_title,
        slug=slug,
        meta_description=desc,
        focus_keyword=focus,
        warnings=warnings,
    )


def _infer_keyword(title: str) -> str:
    words = [w for w in re.findall(r"[A-Za-z]+", title.lower()) if w not in _STOPWORDS]
    return " ".join(words[:3])


def _build_description(doc: Document) -> str:
    overview = doc.find_section("overview", "_lead")
    if overview:
        text = _clean(overview.plain_text())
        if text:
            return text
    # Fall back to the first clean paragraph anywhere.
    for section in doc.sections:
        text = _clean(section.plain_text())
        if text:
            return text
    return ""


def _clean(text: str) -> str:
    """Strip layout directives / inline markup so they don't pollute meta text."""
    lines = []
    for line in (text or "").split("\n"):
        s = line.strip()
        if s.startswith(":::") or s.startswith("#") or s.startswith("[button:"):
            continue
        lines.append(line)
    cleaned = " ".join(lines)
    cleaned = re.sub(r"\[button:[^\]]*\]", "", cleaned)
    cleaned = re.sub(r"\[([^\]]+)\]\([^)]*\)", r"\1", cleaned)  # links -> text
    cleaned = re.sub(r"[*_`>]", "", cleaned)
    return re.sub(r"\s+", " ", cleaned).strip()
