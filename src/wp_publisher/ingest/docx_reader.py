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
# House editorial markers that authors type literally into Word.
_HEADING_MARK = re.compile(r"^\[H[1-6]\]\s*", re.IGNORECASE)
_EDITORIAL_FLAG = re.compile(
    r"^(⚠️\s*)?(WEBMASTER\b|.*\bDo not publish\b|\[?VERIFY\]?\b)", re.IGNORECASE
)
# A byline near the top: "By Juan Magallanes, Naturalist Expert Contributor — …".
_BYLINE = re.compile(r"^By\s+[A-Z][\w'.-]+\s+[A-Z]")


def _clean_byline(text: str) -> str:
    s = re.sub(r"^By\s+", "", text).strip()
    return re.split(r"\s+[—–-]\s+", s, maxsplit=1)[0].strip()


def _is_instruction_table(rows: list[list[str]]) -> bool:
    """Internal-instruction / explainer tables (green boxes) are not content."""
    head = " ".join(rows[0]).lower() if rows else ""
    return (
        "internal instruction" in head
        or "what this is" in head
        or head.startswith("this green box")
    )


_GEO_ANSWER_RE = re.compile(
    r"PUBLISH THIS ANSWER TEXT[^:]*:\s*(.+?)(?:\n\s*\n|WHAT THIS\b|WHY THIS\b|HOW \b|\Z)",
    re.IGNORECASE | re.DOTALL,
)


def _extract_geo_answer(rows: list[list[str]]) -> str:
    """Pull the publishable ~50-word GEO/AI answer out of a GEO block table."""
    text = "\n".join(cell for row in rows for cell in row if cell)
    m = _GEO_ANSWER_RE.search(text)
    if not m:
        return ""
    return " ".join(m.group(1).split()).strip()


# --- structured tables ----------------------------------------------------- #
def _ncols(rows: list[list[str]]) -> int:
    return max((len(r) for r in rows), default=0)


def _is_visitor_sites_table(rows: list[list[str]]) -> bool:
    head = " ".join(c.lower() for c in rows[0])
    return _ncols(rows) >= 3 and ("visitor site" in head or ("site" in head and "access" in head))


def _is_quick_facts_table(rows: list[list[str]]) -> bool:
    """A 2-column label|value reference table (short labels, no prose header)."""
    if _ncols(rows) != 2 or len(rows) < 3:
        return False
    labels = [r[0].strip() for r in rows if len(r) >= 2 and r[0].strip()]
    return bool(labels) and all(len(label) <= 40 for label in labels)


def _infer_access_type(access: str) -> str:
    a = access.lower()
    if "cruise" in a:
        return "Cruise-only"
    if any(w in a for w in ("walk", "taxi", "bus", "free", "drive", "ferry", "road", "town")):
        return "Land-based"
    return ""


def _extract_visitor_sites(rows: list[list[str]]) -> list[dict]:
    header = [c.lower() for c in rows[0]]

    def col(*names: str) -> int | None:
        for i, h in enumerate(header):
            if any(n in h for n in names):
                return i
        return None

    i_name, i_access = col("site"), col("access")
    i_wild, i_notes = col("wildlife", "species"), col("note", "description")

    def cell(row: list[str], i: int | None) -> str:
        return row[i].strip() if i is not None and i < len(row) else ""

    out: list[dict] = []
    for r in rows[1:]:
        name = cell(r, i_name)
        if not name:
            continue
        access = cell(r, i_access)
        out.append({
            "site_name": name,
            "access": access,
            "access_type": _infer_access_type(access),
            "species_seen": cell(r, i_wild),
            "description": cell(r, i_notes),
        })
    return out


def _extract_quick_facts(rows: list[list[str]]) -> list[dict]:
    start = 1 if {c.lower() for c in rows[0]} & {"fact", "detail", "label", "value", "item"} else 0
    out: list[dict] = []
    for r in rows[start:]:
        if len(r) >= 2 and r[0].strip() and r[1].strip():
            out.append({"label": r[0].strip(), "value": r[1].strip()})
    return out
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

    # House "CMS Stage" docs use no heading styles and their own conventions;
    # route them to the dedicated adapter.
    from .cms import build_cms_document, looks_like_cms

    all_texts = [p.text for p in docx.paragraphs]
    if looks_like_cms([t for t in all_texts if t.strip()]):
        return build_cms_document(all_texts, path.name)

    doc = Document(source_name=path.name, source_kind="docx")

    # A real H1 / "Title"-styled line in the body wins. The core-properties title
    # is only a fallback — Word frequently leaves the generic "Word Document"
    # there, which must never become the page title.
    core_title = (docx.core_properties.title or "").strip()

    current = Section(title="", level=1, slug="_lead")
    list_items: list[str] = []
    list_ordered = False
    seen_body = False
    verify_warnings: list[str] = []
    # Reconstruct a markdown-ish body so the freeform builder can read any
    # directives an author typed directly into Word (e.g. "::: columns").
    raw_lines: list[str] = []

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

        # Strip literal house markers like "[H2] " from heading/paragraph text.
        text = _HEADING_MARK.sub("", text).strip()
        # Drop (and surface) editorial flags: "⚠️ WEBMASTER: Do not publish…".
        if _EDITORIAL_FLAG.match(text):
            verify_warnings.append("VERIFY: " + text.lstrip("⚠️ ").strip())
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
                raw_lines.append("#" * level + " " + text)
                seen_body = True
                continue
            if current.blocks or current.title:
                doc.sections.append(current)
            current = Section(title=text, level=level, slug=section_slug(text))
            raw_lines.extend(["", "#" * max(2, level) + " " + text])
            seen_body = True
            continue

        # A byline near the top -> author metadata (not body content).
        if not seen_body and not doc.metadata.get("author") and _BYLINE.match(text):
            doc.metadata["author"] = _clean_byline(text)
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
            raw_lines.append(("1. " if list_ordered else "- ") + text)
            continue
        flush_list()

        if style_l == "quote" or style_l == "intense quote":
            current.blocks.append(ContentBlock(type=BlockType.QUOTE, text=text))
            raw_lines.extend(["", "> " + text])
            continue

        current.blocks.append(ContentBlock(type=BlockType.PARAGRAPH, text=text))
        raw_lines.extend(["", text])

    flush_list()
    if current.blocks or current.title:
        doc.sections.append(current)
    doc.raw_body = "\n".join(raw_lines).strip()

    # Tables become table blocks appended to the lead/last section. Internal
    # instruction / explainer tables (green boxes) are skipped — not content.
    for table in docx.tables:
        rows = [[cell.text.strip() for cell in row.cells] for row in table.rows]
        if not rows:
            continue
        geo = _extract_geo_answer(rows)
        if geo:
            doc.metadata.setdefault("geo_answer", geo)
            continue  # the GEO block is scaffolding, not body content
        if _is_instruction_table(rows):
            continue
        if _is_visitor_sites_table(rows):
            sites = _extract_visitor_sites(rows)
            if sites:
                doc.metadata.setdefault("visitor_sites", []).extend(sites)
                continue
        if _is_quick_facts_table(rows):
            facts = _extract_quick_facts(rows)
            if facts:
                doc.metadata.setdefault("quick_facts", []).extend(facts)
                continue
        target = doc.sections[-1] if doc.sections else current
        target.blocks.append(ContentBlock(type=BlockType.TABLE, rows=rows))

    if verify_warnings:
        doc.metadata["_ingest_warnings"] = [
            *verify_warnings,
            "This doc has unresolved VERIFY flags — review before publishing live.",
        ]

    if not doc.title:
        if core_title and core_title.lower() != "word document":
            doc.title = core_title
        else:
            doc.title = path.stem.replace("_", " ").replace("-", " ").title()
    return doc


def _heading_level(style_name: str) -> int:
    m = re.search(r"(\d)", style_name)
    return int(m.group(1)) if m else 2
