"""Read a production HTML page (+ optional sidecar schema.json) into a Document.

This is the **canonical** production format: the content team ships semantic
HTML whose CSS classes are a stable contract, so mapping is deterministic —
class/tag -> field — with no prose-guessing heuristics. Section bodies are kept
as HTML verbatim (``<p>``, ``<ul>``, ``<table>``, ``<h3>``) in a single
``BlockType.HTML`` block, which the WYSIWYG ACF fields expect unchanged.

Class contract (this site's "Guide" layout):

    div.answer-box[data-speakable]   -> geo_answer  (the AI-Overview answer)
    header h1                        -> title
    p.dateline / JSON-LD author      -> author
    section (with h2)                -> a feature section (title + HTML body)
    section#faq details/summary      -> FAQ accordion
    section.conversion-band          -> primary CTA
    div.lead-magnet                  -> secondary CTA
    section#related li a             -> related_links
    footer.sources                   -> sources
    <script type=ld+json> / sidecar  -> schema_jsonld (-> seo_schema)

Anything green/internal lives only in the editor .docx, never in this HTML, so
there is nothing to strip.
"""

from __future__ import annotations

import json
import re
from pathlib import Path

from bs4 import BeautifulSoup

from ..models import BlockType, ContentBlock, Document, Section
from ..utils import section_slug


def read_html(path: str | Path) -> Document:
    path = Path(path)
    raw = path.read_text(encoding="utf-8")
    soup = BeautifulSoup(raw, "html.parser")

    doc = Document(source_name=path.name, source_kind="html")
    doc.raw_body = raw  # no ':::' directives -> auto compose path
    meta: dict = doc.metadata

    _read_head(soup, meta)
    _read_schema(soup, path, meta)

    article = soup.find("article") or soup.body or soup
    doc.title = _title(soup, article)

    _read_author(soup, meta)
    _read_answer_box(article, meta)

    for section in article.find_all("section", recursive=True):
        _handle_section(section, doc, meta)

    _read_sources(article, meta)

    # Order the two CTAs primary-first for the repeater.
    ctas = [c for c in (meta.pop("_cta_primary", None), meta.pop("_cta_secondary", None)) if c]
    if ctas:
        meta["cta_blocks"] = ctas
    return doc


# --- head / meta ----------------------------------------------------------- #
def _read_head(soup: BeautifulSoup, meta: dict) -> None:
    title_tag = soup.find("title")
    if title_tag and title_tag.get_text(strip=True):
        # Curated meta title (differs from the visible H1 by design).
        meta["seo_title"] = title_tag.get_text(strip=True)
    desc = soup.find("meta", attrs={"name": "description"})
    if desc and desc.get("content"):
        meta["meta_description"] = desc["content"].strip()
    canonical = soup.find("link", attrs={"rel": "canonical"}) or soup.find(
        "link", rel="canonical"
    )
    href = canonical.get("href") if canonical else ""
    slug = _slug_from_url(href)
    if slug:
        meta["slug"] = slug


def _slug_from_url(url: str) -> str:
    """Last non-empty path segment of a canonical URL -> slug."""
    if not url:
        return ""
    path = re.sub(r"^https?://[^/]+", "", url.strip())
    parts = [p for p in path.split("/") if p]
    return parts[-1] if parts else ""


def _read_schema(soup: BeautifulSoup, path: Path, meta: dict) -> None:
    """Prefer a sidecar ``<stem>.schema.json``; else the inline JSON-LD block."""
    sidecar = path.with_suffix(".schema.json")
    text = ""
    if sidecar.exists():
        text = sidecar.read_text(encoding="utf-8").strip()
    else:
        tag = soup.find("script", attrs={"type": "application/ld+json"})
        if tag and tag.string:
            text = tag.string.strip()
    if not text:
        return
    try:
        meta["schema_jsonld"] = json.dumps(json.loads(text), ensure_ascii=False)
    except (ValueError, TypeError):
        meta["schema_jsonld"] = text  # keep raw if it isn't clean JSON


def _title(soup: BeautifulSoup, article) -> str:
    header = article.find("header")
    h1 = (header.find("h1") if header else None) or article.find("h1") or soup.find("h1")
    return h1.get_text(strip=True) if h1 else ""


def _read_author(soup: BeautifulSoup, meta: dict) -> None:
    # 1) JSON-LD Article author (already parsed into schema_jsonld).
    raw = meta.get("schema_jsonld")
    if raw:
        try:
            data = json.loads(raw)
            for node in data.get("@graph", [data]) if isinstance(data, dict) else []:
                if isinstance(node, dict) and "Article" in str(node.get("@type", "")):
                    name = (node.get("author") or {}).get("name")
                    if name:
                        meta["author"] = name
                        return
        except (ValueError, TypeError):
            pass
    # 2) Fall back to a "Reviewed by <name>" dateline.
    dl = soup.find(class_="dateline")
    if dl:
        m = re.search(r"(?:Reviewed|Written|By)\s+by\s+([^·,]+)", dl.get_text(" ", strip=True))
        if m:
            meta["author"] = m.group(1).strip()


def _read_answer_box(article, meta: dict) -> None:
    box = article.find(class_="answer-box")
    if box:
        html = _inner_html(box).strip()
        if html:
            meta["geo_answer"] = html  # the GEO / AI-Overview answer


# --- sections -------------------------------------------------------------- #
def _handle_section(section, doc: Document, meta: dict) -> None:
    classes = section.get("class") or []
    sid = section.get("id") or ""

    if "conversion-band" in classes:
        meta["_cta_primary"] = _cta_from_band(section)
        return
    if sid == "related":
        links = _links(section)
        if links:
            meta["related_links"] = links
        return
    if sid == "faq" or _has_details(section):
        doc.sections.append(_faq_section(section))
        return

    doc.sections.append(_feature_section(section, meta))


def _feature_section(section, meta: dict) -> Section:
    h2 = section.find(["h2", "h3"])
    title = h2.get_text(strip=True) if h2 else ""
    if h2:
        h2.extract()  # heading becomes Section.title; don't duplicate in body
    # A lead-magnet inside the section is a soft secondary CTA, not body prose.
    lm = section.find(class_="lead-magnet")
    if lm:
        meta.setdefault("_cta_secondary", _cta_from_lead_magnet(lm))
        lm.extract()
    body = _inner_html(section).strip()
    blocks = [ContentBlock(type=BlockType.HTML, html=body)] if body else []
    return Section(title=title, level=2, slug=section_slug(title), blocks=blocks)


def _faq_section(section) -> Section:
    """Each <details> -> a HEADING (question) + HTML (answer) so compose builds
    the accordion via its existing _faq_items path."""
    blocks: list[ContentBlock] = []
    heading = section.find(["h2"])
    title = heading.get_text(strip=True) if heading else "FAQ"
    for det in section.find_all("details"):
        summary = det.find("summary")
        q = summary.get_text(strip=True) if summary else ""
        if summary:
            summary.extract()
        answer = _inner_html(det).strip()
        if not q:
            continue
        blocks.append(ContentBlock(type=BlockType.HEADING, text=q, level=3))
        blocks.append(ContentBlock(type=BlockType.HTML, html=answer))
    return Section(title=title, level=2, slug="faq", blocks=blocks)


# --- CTAs / links / sources ------------------------------------------------ #
def _cta_from_band(section) -> dict:
    heading = section.find(["h2", "h3"])
    btn = section.find("a", class_="cta-primary") or section.find("a")
    paras = [p.get_text(" ", strip=True) for p in section.find_all("p") if p.get_text(strip=True)]
    # Drop the button's own line and any "Chat / Call us" aside from the blurb.
    blurb = next((p for p in paras if not btn or btn.get_text(strip=True) not in p), "")
    return {
        "audience": "Direct travelers",
        "title": heading.get_text(strip=True) if heading else "",
        "text": blurb,
        "button_label": btn.get_text(strip=True) if btn else "",
        "button_url": (btn.get("href") if btn else "") or "",
    }


def _cta_from_lead_magnet(lm) -> dict:
    btn = lm.find("a")
    text = lm.get_text(" ", strip=True)
    return {
        "audience": "Direct travelers",
        "title": "",
        "text": text,
        "button_label": btn.get_text(strip=True) if btn else "",
        "button_url": (btn.get("href") if btn else "") or "",
    }


def _links(section) -> list[dict]:
    out = []
    for a in section.find_all("a"):
        label = a.get_text(strip=True)
        if label:
            out.append({"label": label, "url": a.get("href", "") or ""})
    return out


def _read_sources(article, meta: dict) -> None:
    footer = article.find("footer", class_="sources") or article.find(class_="sources")
    if not footer:
        return
    text = footer.get_text(" ", strip=True)
    text = re.sub(r"^\s*Sources?:\s*", "", text, flags=re.IGNORECASE)
    rows = [{"label": s.strip(" .;"), "url": ""} for s in text.split(";") if s.strip()]
    if rows:
        meta["sources"] = rows


# --- helpers --------------------------------------------------------------- #
def _has_details(section) -> bool:
    return section.find("details") is not None


def _inner_html(tag) -> str:
    return "".join(str(c) for c in tag.contents)
