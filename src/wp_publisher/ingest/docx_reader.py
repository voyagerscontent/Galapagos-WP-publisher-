"""Read Microsoft Word (.docx) into a Document.

Uses python-docx. Word's named styles ("Heading 1".."Heading 6", "Title",
"List Bullet"/"List Number", "Quote") map cleanly onto our block model, which
makes Word the highest-fidelity input format.
"""

from __future__ import annotations

import re
from pathlib import Path

from ..models import BlockType, ContentBlock, Document, Section
from ..utils import section_slug

_META_RE = re.compile(r"^([A-Za-z][A-Za-z0-9 _/-]{1,40}):\s*(.+)$")
_KNOWN_META_KEYS = {
    "type",
    "page type",
    "template",
    "title",
    "slug",
    "destination",
    "duration",
    "price",
    "focus keyword",
    "keyword",
    "meta description",
    "description",
    "categories",
    "tags",
    "status",
    "ship",
    "region",
    "country",
    "best time",
    "featured image query",
    "featured image alt",
}


def read_docx(path: str | Path) -> Document:
    from docx import Document as DocxDocument  # imported lazily

    path = Path(path)
    docx = DocxDocument(str(path))
    doc = Document(source_name=path.name, source_kind="docx")

    # Core properties give us a title for free if the author set one.
    if docx.core_properties.title:
        doc.title = docx.core_properties.title.strip()

    current = Section(title="", level=1, slug="_lead")
    list_items: list[str] = []
    list_ordered = False
    seen_body = False

    def flush_list() -> None:
        nonlocal list_items, list_ordered
        if list_items:
            current.blocks.append(
                ContentBlock(type=BlockType.LIST, ordered=list_ordered, items=list_items[:])
            )
            list_items = []
            list_ordered = False

    for para in docx.paragraphs:
        text = para.text.strip()
        style = (para.style.name if para.style else "") or ""
        style_l = style.lower()

        if not text:
            continue

        # Title style -> document title.
        if style_l == "title" and not doc.title:
            doc.title = text
            continue

        # Heading styles -> new section.
        if style_l.startswith("heading"):
            flush_list()
            level = _heading_level(style_l)
            if level == 1 and not doc.title and not seen_body:
                doc.title = text
                continue
            if level >= 3:
                # Subheadings nest inside the current section (e.g. FAQ items).
                current.blocks.append(
                    ContentBlock(type=BlockType.HEADING, text=text, level=level)
                )
                seen_body = True
                continue
            if current.blocks or current.title:
                doc.sections.append(current)
            current = Section(title=text, level=level, slug=section_slug(text))
            seen_body = True
            continue

        # Leading "Key: value" lines before any prose -> metadata.
        if not seen_body and not current.blocks:
            m = _META_RE.match(text)
            if m and m.group(1).strip().lower() in _KNOWN_META_KEYS:
                key = m.group(1).strip().lower().replace(" ", "_")
                doc.metadata[key] = m.group(2).strip()
                continue

        seen_body = True

        # List styles.
        if "list bullet" in style_l or "list number" in style_l or style_l.startswith("list"):
            list_ordered = "number" in style_l
            list_items.append(text)
            continue
        flush_list()

        if style_l == "quote" or style_l == "intense quote":
            current.blocks.append(ContentBlock(type=BlockType.QUOTE, text=text))
            continue

        current.blocks.append(ContentBlock(type=BlockType.PARAGRAPH, text=text))

    flush_list()
    if current.blocks or current.title:
        doc.sections.append(current)

    # Tables become table blocks appended to the lead/last section.
    for table in docx.tables:
        rows = [[cell.text.strip() for cell in row.cells] for row in table.rows]
        if rows:
            target = doc.sections[-1] if doc.sections else current
            target.blocks.append(ContentBlock(type=BlockType.TABLE, rows=rows))

    if not doc.title:
        doc.title = path.stem.replace("_", " ").title()
    return doc


def _heading_level(style_name: str) -> int:
    m = re.search(r"(\d)", style_name)
    return int(m.group(1)) if m else 2
