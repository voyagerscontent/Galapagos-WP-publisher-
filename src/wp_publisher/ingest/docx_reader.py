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
        or "verify items" in head
        or "requires editorial review" in head
        or "version footer" in head
    )


def _is_sources_table(rows: list[list[str]]) -> bool:
    """A 2-column citations table whose rows carry URLs."""
    if _ncols(rows) < 2 or len(rows) < 2:
        return False
    with_urls = sum(1 for r in rows if _URL_RE.search(" ".join(r)))
    return with_urls >= max(2, len(rows) // 2)


def _extract_sources_table(rows: list[list[str]]) -> list[dict]:
    out: list[dict] = []
    for r in rows:
        m = _URL_RE.search(" ".join(r))
        if not m:
            continue
        url = m.group(0).rstrip(".,);")
        label = r[0].strip().rstrip("—–-. ").strip()
        if label:
            out.append({"label": label, "url": url})
    return out


_GEO_ANSWER_RE = re.compile(
    r"(?:PUBLISH THIS ANSWER TEXT[^:]*:|answer extraction[^\n]*|Quick Answer:?)"
    r"\s*\n?\s*(.+?)(?:\n\s*\n|WHAT THIS\b|WHY THIS\b|HOW \b|\Z)",
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


_URL_RE = re.compile(r"https?://\S+")
_TRADE_RE = re.compile(r"\b(trade|latin trails|dmc|wholesaler|agent|group programs)\b", re.IGNORECASE)
_CTA_BUTTON_RE = re.compile(r'CTA button:\s*"(.+?)"\s*(?:→|->)\s*(\S+)', re.IGNORECASE)
# Visible text inside a paragraph, INCLUDING content controls (w:sdt) and
# hyperlinks, which python-docx's `.text` silently drops.
_WT = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}t"
_INTERNAL_LINK_RE = re.compile(r"\[INTERNAL LINK:\s*(.+?)\s*(?:→|->)\s*([^\]\s]+)\s*\]")
# "⬛ WEBMASTER DESIGN NOTE: …" styling instructions — never published, even when
# appended inline to a real paragraph. Stripped from the marker to end of line.
_DESIGN_NOTE_RE = re.compile(
    r"\s*[^\w\s\n]*\s*WEBMASTER\s+DESIGN\s+NOTE\b[^\n]*", re.IGNORECASE
)


def _p_text(paragraph) -> str:
    return "".join(node.text or "" for node in paragraph._p.iter(_WT))


def _cell_text(cell) -> str:
    return "\n".join(_p_text(p) for p in cell.paragraphs).strip()


def _clean_markers(text: str) -> str:
    """[INTERNAL LINK: label → /url/] -> Markdown [label](/url/).

    WYSIWYG fields render the Markdown link to a real <a>; textarea fields strip
    it back to the label (they can't hold links). Either way the URL is kept here.
    """
    text = _DESIGN_NOTE_RE.sub("", text)
    return _INTERNAL_LINK_RE.sub(r"[\1](\2)", text)


def _is_cta_table(rows: list[list[str]]) -> bool:
    text = " ".join(c for row in rows for c in row).lower()
    return "cta button:" in text or "call to action" in text or "with a specialist" in text


# "CALL TO ACTION … CTA 1 — <audience>: Headline: … Body: … Button: label → url".
_CTA_INSTR_RE = re.compile(
    r"CTA\s*\d+\s*[—–-]\s*([^\n:]+):(.*?)(?=CTA\s*\d+\s*[—–-]|\Z)", re.IGNORECASE | re.DOTALL
)
_CTA_TRADE_RE = re.compile(r"trade|industry|partner|wholesal|agent|dmc", re.IGNORECASE)


def _grab_field(block: str, name: str) -> str:
    m = re.search(rf"{name}:\s*(.+)", block, re.IGNORECASE)
    return m.group(1).strip() if m else ""


def _parse_cta_instruction(cell: str) -> list[dict]:
    out: list[dict] = []
    for m in _CTA_INSTR_RE.finditer(cell):
        audience_text, body_block = m.group(1).strip(), m.group(2)
        block = {
            "audience": "Travel trade" if _CTA_TRADE_RE.search(audience_text) else "Direct travelers",
            "title": _grab_field(body_block, "Headline"),
            "text": _grab_field(body_block, "Body"),
        }
        bm = re.search(r"(.+?)\s*(?:→|->)\s*(.+)$", _grab_field(body_block, "Button"))
        if bm:
            block["button_label"] = bm.group(1).strip()
            url = bm.group(2).strip()
            if url.startswith(("/", "http", "mailto:")):  # ignore non-URL placeholders
                block["button_url"] = url
        out.append(block)
    return out


def _extract_cta_blocks(rows: list[list[str]]) -> list[dict]:
    out: list[dict] = []
    for row in rows:
        for cell in row:
            cell = cell.strip()
            if not cell:
                continue
            if re.search(r"call to action|CTA\s*\d+\s*[—–-]", cell, re.IGNORECASE):
                out.extend(_parse_cta_instruction(cell))
                continue
            buttons = _CTA_BUTTON_RE.findall(cell)
            body = _CTA_BUTTON_RE.sub("", cell)  # strip the button directive line
            lines = [ln.strip() for ln in body.split("\n") if ln.strip()]
            title = lines[0] if lines else ""
            text = " ".join(" ".join(lines[1:]).split())
            block = {
                "audience": "Travel trade" if _TRADE_RE.search(cell) else "Direct travelers",
                "title": title,
                "text": text,
            }
            if buttons:
                label, url = buttons[0]
                block["button_label"] = label.strip().rstrip("→ ").strip()
                block["button_url"] = url.strip()
            out.append(block)
    return out


def _extract_sources(section: Section) -> list[dict]:
    out: list[dict] = []
    lines: list[str] = []
    for b in section.blocks:
        if b.items:
            lines.extend(b.items)
        elif b.text:
            lines.append(b.text)
    for line in lines:
        m = _URL_RE.search(line)
        url = m.group(0).rstrip(".,);") if m else ""
        label = (line[: m.start()] if m else line).strip().rstrip("—–-. ").strip()
        if label or url:
            out.append({"label": label, "url": url})
    return out


_REL_LINK_RE = re.compile(r"^[•·\-*\s]*(.+?)\s*(?:→|->)\s*(\S+)\s*$")


def _extract_related_links(section: Section) -> list[dict]:
    """'• Label → /url/' bullet lines from an 'Explore More' footer."""
    out: list[dict] = []
    for b in section.blocks:
        lines = list(b.items) if b.items else ([b.text] if b.text else [])
        for line in lines:
            m = _REL_LINK_RE.match(line.strip())
            if not m:
                continue
            label, url = m.group(1).strip(), m.group(2).strip()
            if label and url.startswith(("/", "http")):
                out.append({"label": label, "url": url})
    return out


def _visual_heading_level(para) -> int:
    """Infer a heading level for docs that style headings by bold + font size
    rather than Word heading styles. 0 means 'not a heading'."""
    runs = [r for r in para.runs if (r.text or "").strip()]
    if not runs or not any(r.bold for r in runs):
        return 0
    text = para.text.strip()
    if len(text) > 140 or text.endswith((".", "!", "?")):
        return 0
    sizes = [r.font.size.pt for r in runs if r.font.size is not None]
    size = max(sizes) if sizes else 0.0
    if size >= 18:
        return 1
    if size >= 13.5:
        return 2
    if size >= 11.5:
        return 3
    return 0


def _is_title_para(para) -> bool:
    style = (para.style.name if para.style else "").lower()
    return style in ("title", "heading 1") or _visual_heading_level(para) == 1


_SCI_NAME_RE = re.compile(r"\(([A-ZÁÉÍÓÚ][a-zé]+ [a-z]+)\)")


def _split_off_button(paras: list[str]) -> tuple[list[str], dict | None]:
    """Pull a trailing '→ Label …/url' button line off a card's prose."""
    if not paras:
        return paras, None
    last = paras[-1].strip()
    m = re.match(r"(?:→|->)\s*(.+)$", last)
    if not m:
        return paras, None
    rest = m.group(1)
    link = re.search(r"\[([^\]]+)\]\(([^)\s]+)\)", rest)  # markdown link from [INTERNAL LINK]
    if link:
        label = rest[: link.start()].strip() or link.group(1)
        return paras[:-1], {"label": label.strip(), "url": link.group(2)}
    arrow = re.search(r"(.+?)\s*(?:→|->)\s*(\S+)$", rest)
    if arrow and arrow.group(2).startswith(("/", "http")):
        return paras[:-1], {"label": arrow.group(1).strip(), "url": arrow.group(2)}
    return paras, None


def _extract_wildlife(section: Section) -> tuple[str, list[dict]]:
    """Wildlife section -> (intro prose, species list).

    The H2 lead text (before the first H3) is the intro; each H3 sub-heading +
    its prose is a species, with an optional trailing '→' button.
    """
    intro: list[str] = []
    species: list[dict] = []
    name: str | None = None
    desc: list[str] = []

    def make(nm: str, paras: list[str]) -> dict:
        paras, button = _split_off_button(paras)
        text = "\n\n".join(paras).strip()
        sci = _SCI_NAME_RE.search(text)
        row = {
            "common_name": re.split(r"\s+[—–-]\s+", nm, maxsplit=1)[0].strip(),
            "scientific_name": sci.group(1) if sci else "",
            "description": text,
        }
        if button:
            row["button_label"] = button["label"]
            row["button_url"] = button["url"]
        return row

    for b in section.blocks:
        if b.type == BlockType.HEADING:
            if name and desc:
                species.append(make(name, desc))
            name, desc = b.text, []
        elif b.text or b.items:
            chunk = "\n".join(b.items) if b.items else b.text
            (desc if name else intro).append(chunk)
    if name and desc:
        species.append(make(name, desc))
    return "\n\n".join(intro).strip(), species
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

    # Skip a cover-letter preamble: start at the document title (styled or a
    # bold, large-font line) when one exists.
    paras = list(docx.paragraphs)
    start = 0
    for i, p in enumerate(paras):
        if _p_text(p).strip() and _is_title_para(p):
            start = i
            break

    for para in paras[start:]:
        text = _clean_markers(_p_text(para)).strip()
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
        # A stray "CTA button:" directive in the body is an instruction, not prose.
        if text.lower().startswith("cta button:"):
            continue

        # Heading from a heading/title style, or visually (bold + larger font for
        # docs that don't use Word heading styles).
        if style_l.startswith("heading"):
            level = _heading_level(style_l)
        elif style_l == "title":
            level = 1
        else:
            level = _visual_heading_level(para)

        if level:
            flush_list()
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
        rows = [[_clean_markers(_cell_text(cell)) for cell in row.cells] for row in table.rows]
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
        if _is_sources_table(rows):
            srcs = _extract_sources_table(rows)
            if srcs:
                doc.metadata.setdefault("sources", []).extend(srcs)
                continue
        if _is_quick_facts_table(rows):
            facts = _extract_quick_facts(rows)
            if facts:
                doc.metadata.setdefault("quick_facts", []).extend(facts)
                continue
        if _is_cta_table(rows):
            blocks = _extract_cta_blocks(rows)
            if blocks:
                doc.metadata.setdefault("cta_blocks", []).extend(blocks)
                continue
        target = doc.sections[-1] if doc.sections else current
        target.blocks.append(ContentBlock(type=BlockType.TABLE, rows=rows))

    # Citations / sources section -> structured metadata.
    sources_section = doc.find_section("sources")
    if sources_section is not None:
        sources = _extract_sources(sources_section)
        if sources:
            doc.metadata["sources"] = sources

    # "Explore More" footer -> related internal links.
    for s in doc.sections:
        if "explore" in s.slug or "footer" in s.slug:
            related = _extract_related_links(s)
            if related:
                doc.metadata["related_links"] = related
            break

    # "At a Glance" / "Quick Facts" section -> the quick-facts heading + intro.
    for s in doc.sections:
        tl = s.title.lower()
        if "at a glance" in tl or "quick facts" in tl or "glance" in s.slug:
            doc.metadata["quick_facts_title"] = s.title
            intro = "\n\n".join(
                ("\n".join(b.items) if b.items else b.text)
                for b in s.blocks
                if b.type in (BlockType.PARAGRAPH, BlockType.LIST) and (b.text or b.items)
            ).strip()
            if intro:
                doc.metadata["quick_facts_intro"] = intro
            break

    # A "Wildlife" section with H3 species sub-headings -> title + intro + species.
    for s in doc.sections:
        if s.slug.startswith("wildlife") or s.title.lower().startswith("wildlife"):
            doc.metadata["wildlife_title"] = s.title
            intro, wildlife = _extract_wildlife(s)
            if intro:
                doc.metadata["wildlife_intro"] = intro
            if wildlife:
                doc.metadata["wildlife"] = wildlife
            break

    # A "Visitor Sites" section heading -> the visitor-sites title.
    for s in doc.sections:
        if s.slug.startswith("visitor-sites") or s.title.lower().startswith("visitor sites"):
            doc.metadata["visitor_sites_title"] = s.title
            break

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
