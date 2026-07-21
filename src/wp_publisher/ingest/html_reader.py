"""Read a production HTML page (+ optional sidecar schema.json) into a Document.

This is the **canonical** production format: the content team ships semantic
HTML whose tags/classes are a stable contract, so mapping is deterministic —
no prose-guessing. Section bodies are kept as HTML verbatim (``<p>``, ``<ul>``,
``<table>``, ``<h3>``) in a single ``BlockType.HTML`` block, which the WYSIWYG
ACF fields expect unchanged.

The reader segments the article **by ``<h2>``**, which handles both layouts the
team uses interchangeably:

* **sectioned** — each block wrapped in ``<section>`` (the ``<h2>`` inside it);
* **flat** — the ``<h2>``/``<h3>`` headings are direct children of ``<article>``.

Special elements are pulled out by class first, tolerating synonyms:

    .answer-box                       -> geo_answer (the AI-Overview answer)
    .dateline / .byline / JSON-LD     -> author
    .conversion-band / .cta-primary   -> CTA blocks (primary)
    .lead-magnet                      -> CTA block (secondary)
    #related / h2#related             -> related_links
    footer.sources / p.sources        -> sources
    <script ld+json> / sidecar        -> schema_jsonld (-> seo_schema)

Everything green/internal lives only in the editor .docx, never in this HTML,
so there is nothing to strip.
"""

from __future__ import annotations

import json
import re
from pathlib import Path

from bs4 import BeautifulSoup, NavigableString, Tag

from ..models import BlockType, ContentBlock, Document, Section
from ..utils import section_slug

_FAQ_RE = re.compile(r"^\s*(faq|frequently asked)", re.IGNORECASE)
_RELATED_RE = re.compile(r"^\s*related\b", re.IGNORECASE)


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

    # Pull the special, class-identified pieces out of the tree first, then
    # flatten any <section> wrappers so the remaining flow is a single stream
    # of headings + content we can segment by <h2>.
    _consume_specials(article, meta)
    for sec in article.find_all("section"):
        sec.unwrap()
    _segment(article, doc)
    return doc


# --- head / meta ----------------------------------------------------------- #
def _read_head(soup: BeautifulSoup, meta: dict) -> None:
    title_tag = soup.find("title")
    if title_tag and title_tag.get_text(strip=True):
        meta["seo_title"] = title_tag.get_text(strip=True)  # curated meta title
    desc = soup.find("meta", attrs={"name": "description"})
    if desc and desc.get("content"):
        meta["meta_description"] = desc["content"].strip()
    canonical = soup.find("link", attrs={"rel": "canonical"}) or soup.find(
        "link", rel="canonical"
    )
    slug = _slug_from_url(canonical.get("href") if canonical else "")
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
            nodes = data.get("@graph", [data]) if isinstance(data, dict) else []
            for node in nodes:
                if isinstance(node, dict) and "Article" in str(node.get("@type", "")):
                    name = (node.get("author") or {}).get("name")
                    if name:
                        meta["author"] = name
                        return
        except (ValueError, TypeError):
            pass
    # 2) Fall back to a byline/dateline ("Reviewed by …" / "By …").
    line = soup.find(class_="dateline") or soup.find(class_="byline")
    if line:
        text = line.get_text(" ", strip=True)
        m = re.search(r"(?:Reviewed by|Written by|By)\s+(.+?)(?:\s*[·,]|$)", text)
        if m:
            meta["author"] = m.group(1).strip()


# --- pull special pieces out of the tree ----------------------------------- #
def _consume_specials(article, meta: dict) -> None:
    # Title header + navs (table of contents) are not body content.
    for h in article.find_all("header"):
        h.extract()
    for h in article.find_all("h1"):
        h.extract()
    for nav in article.find_all("nav"):
        nav.extract()

    box = article.find(class_="answer-box")
    if box:
        html = _inner_html(box).strip()
        if html:
            meta["geo_answer"] = html  # the GEO / AI-Overview answer
        box.extract()

    # Author line(s) — value already read into metadata.
    for cls in ("dateline", "byline"):
        for el in article.find_all(class_=cls):
            el.extract()

    # Sources: footer.sources or p.sources.
    src = article.find(class_="sources")
    if src:
        text = re.sub(r"^\s*Sources?:\s*", "", src.get_text(" ", strip=True), flags=re.I)
        rows = [{"label": s.strip(" .;"), "url": ""} for s in text.split(";") if s.strip()]
        if rows:
            meta["sources"] = rows
        src.extract()

    # Related links: a #related section, or an h2#related in flat layout.
    _consume_related(article, meta)

    # CTAs, in document order. Containers: conversion-band / cta-primary (div or
    # section) / lead-magnet. Plus a *standalone* <a class="cta-primary"> that is
    # not inside such a container (some pages use a bare anchor as the CTA). An
    # <a> inside a band is its own button — handled by _cta_from_el — so skip it.
    _CTA = ("conversion-band", "cta-primary", "lead-magnet")
    containers = [e for e in article.find_all(class_=list(_CTA)) if e.name != "a"]
    chosen = []
    for el in article.find_all(True):
        cls = el.get("class") or []
        is_container = el.name != "a" and any(c in cls for c in _CTA)
        is_bare_anchor = (
            el.name == "a"
            and "cta-primary" in cls
            and not any(el in c.descendants for c in containers)
        )
        # Don't double-count a container nested inside another chosen container.
        if (is_container or is_bare_anchor) and not any(
            el is not c and el in c.descendants for c in containers
        ):
            chosen.append(el)
    primaries, secondaries = [], []
    for el in chosen:
        is_secondary = "lead-magnet" in (el.get("class") or [])
        (secondaries if is_secondary else primaries).append(
            _cta_from_el(el, secondary=is_secondary)
        )
        el.extract()
    # Primary CTAs (closing conversion band) lead; soft lead-magnets trail.
    blocks = primaries + secondaries
    if blocks:
        meta["cta_blocks"] = blocks


def _consume_related(article, meta: dict) -> None:
    node = article.find(id="related")
    links: list[dict] = []
    if node is not None and node.name == "section":
        links = _links(node)
        node.extract()
    else:
        # Flat layout: an <h2 id="related"> or <h2>Related …</h2> whose links live
        # in the following <ul>/<ol>. Consume ONLY that list — stop at anything
        # else (a trailing CTA or sources block must not be swallowed).
        h2 = next(
            (
                h
                for h in article.find_all("h2")
                if h.get("id") == "related" or _RELATED_RE.match(h.get_text(strip=True))
            ),
            None,
        )
        if h2 is not None:
            for sib in h2.find_next_siblings():
                if sib.name in ("ul", "ol"):
                    links.extend(_links(sib))
                    sib.extract()
                else:
                    break  # next h2 / CTA / sources — leave it alone
            h2.extract()
    if links:
        meta["related_links"] = links


# --- segment the remaining flow by <h2> ------------------------------------ #
def _segment(article, doc: Document) -> None:
    current_title = ""
    current_slug = "_lead"
    buffer: list = []

    def flush() -> None:
        nonlocal buffer
        if _FAQ_RE.match(current_title) or (current_slug == "faq"):
            doc.sections.append(_faq_from_nodes(current_title or "FAQ", buffer))
        else:
            html = "".join(str(n) for n in buffer).strip()
            if html or current_title:
                blocks = [ContentBlock(type=BlockType.HTML, html=html)] if html else []
                doc.sections.append(
                    Section(title=current_title, level=2, slug=current_slug, blocks=blocks)
                )
        buffer = []

    for node in list(article.children):
        if isinstance(node, NavigableString):
            if node.strip():
                buffer.append(node)
            continue
        if node.name == "h2":
            flush()
            current_title = node.get_text(strip=True)
            current_slug = "faq" if _FAQ_RE.match(current_title) else section_slug(current_title)
            continue
        buffer.append(node)
    flush()


def _faq_from_nodes(title: str, nodes: list) -> Section:
    """Turn <details>/<summary> pairs (anywhere in the nodes) into HEADING +
    HTML blocks so compose's _faq_items builds the accordion."""
    blocks: list[ContentBlock] = []
    details = []
    for n in nodes:
        if isinstance(n, Tag):
            details.extend(n.find_all("details") if n.name != "details" else [n])
    for det in details:
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


# --- CTAs / links ---------------------------------------------------------- #
def _cta_from_el(el, *, secondary: bool = False) -> dict:
    heading = el.find(["h2", "h3"])
    btn = el.find("a", class_="cta-primary") or el.find("a")
    paras = [p.get_text(" ", strip=True) for p in el.find_all("p") if p.get_text(strip=True)]
    btn_text = btn.get_text(strip=True) if btn else ""
    blurb = next((p for p in paras if not btn_text or btn_text not in p), "")
    if not blurb and not heading:
        blurb = el.get_text(" ", strip=True)
    return {
        "audience": "Direct travelers",
        "title": "" if secondary else (heading.get_text(strip=True) if heading else ""),
        "text": blurb,
        "button_label": btn_text,
        "button_url": (btn.get("href") if btn else "") or "",
    }


def _links(el) -> list[dict]:
    out = []
    for a in el.find_all("a"):
        label = a.get_text(strip=True)
        if label:
            out.append({"label": label, "url": a.get("href", "") or ""})
    return out


# --- helpers --------------------------------------------------------------- #
def _inner_html(tag) -> str:
    return "".join(str(c) for c in tag.contents)
