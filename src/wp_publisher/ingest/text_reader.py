"""Read plain text into a Document.

Plain text has no markup, so we infer structure with light heuristics:

* A leading "Key: value" block (before the first blank line) is treated as
  metadata when keys look like field names (type, destination, duration...).
* Short, title-case lines with no trailing punctuation become headings.
* Lines beginning with -, *, •, or "1." become list items.
* Everything else is grouped into paragraphs separated by blank lines.
"""

from __future__ import annotations

import re
from pathlib import Path

from ..models import BlockType, ContentBlock, Document, Section
from ..utils import section_slug

_META_RE = re.compile(r"^([A-Za-z][A-Za-z0-9 _/-]{1,40}):\s*(.+)$")
_BULLET_RE = re.compile(r"^\s*(?:[-*•]|\d+[.)])\s+(.*)$")

_KNOWN_META_KEYS = {
    "type",
    "page type",
    "template",
    "title",
    "slug",
    "destination",
    "duration",
    "price",
    "from price",
    "focus keyword",
    "keyword",
    "meta description",
    "description",
    "categories",
    "category",
    "tags",
    "author",
    "status",
    "ship",
    "region",
    "country",
    "difficulty",
    "best time",
    "featured image query",
    "featured image alt",
}


def read_text(path: str | Path) -> Document:
    path = Path(path)
    lines = path.read_text(encoding="utf-8").splitlines()
    doc = Document(source_name=path.name, source_kind="text")

    idx = _consume_metadata(lines, doc)

    # Title = first non-empty content line if not set via metadata.
    title = str(doc.metadata.get("title", "")).strip()
    body = lines[idx:]
    if not title:
        for i, line in enumerate(body):
            if line.strip():
                title = line.strip()
                body = body[i + 1 :]
                break
    doc.title = title or path.stem.replace("_", " ").title()

    _parse_body(doc, body)
    return doc


def _consume_metadata(lines: list[str], doc: Document) -> int:
    """Parse a leading metadata block. Returns index where prose begins.

    A header block must *start* with a recognized key (e.g. "Type:" / "Title:").
    Once started, every following "Key: value" line is captured — even keys we
    don't specifically know — until a blank line or a line of prose. Requiring a
    known first key avoids mistaking a prose title like "Galapagos: A Paradise"
    for metadata.
    """
    started = False
    idx = 0
    for i, line in enumerate(lines):
        stripped = line.strip()
        if not stripped:
            if started:
                return i + 1
            continue  # skip leading blank lines
        m = _META_RE.match(stripped)
        key = m.group(1).strip().lower() if m else ""
        if not started:
            if m and key in _KNOWN_META_KEYS:
                started = True
            else:
                return 0  # no header present
        elif not m:
            return i  # prose begins
        doc.metadata[key.replace(" ", "_")] = m.group(2).strip()
        idx = i + 1
    return idx


def _parse_body(doc: Document, body: list[str]) -> None:
    current = Section(title="", level=1, slug="_lead")
    para: list[str] = []
    items: list[str] = []

    def flush_para() -> None:
        nonlocal para
        if para:
            current.blocks.append(
                ContentBlock(type=BlockType.PARAGRAPH, text=" ".join(para).strip())
            )
            para = []

    def flush_items() -> None:
        nonlocal items
        if items:
            current.blocks.append(ContentBlock(type=BlockType.LIST, items=items[:]))
            items = []

    for line in body:
        stripped = line.strip()
        bullet = _BULLET_RE.match(line)
        if bullet:
            flush_para()
            items.append(bullet.group(1).strip())
            continue
        flush_items()

        if not stripped:
            flush_para()
            continue

        if _looks_like_heading(stripped):
            flush_para()
            if current.blocks or current.title:
                doc.sections.append(current)
            current = Section(title=stripped, level=2, slug=section_slug(stripped))
            continue

        para.append(stripped)

    flush_para()
    flush_items()
    if current.blocks or current.title:
        doc.sections.append(current)


def _looks_like_heading(line: str) -> bool:
    if len(line) > 60 or line.endswith((".", ",", ":", ";", "!", "?")):
        return False
    words = line.split()
    if not 1 <= len(words) <= 8:
        return False
    # Mostly capitalized words -> a heading.
    capitalized = sum(1 for w in words if w[:1].isupper())
    return capitalized >= max(1, len(words) - 1)
