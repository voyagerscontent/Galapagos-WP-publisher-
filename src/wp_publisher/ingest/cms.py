"""Adapter for the Voyagers "CMS Stage" house document format.

These docs are authored in Word with **no heading styles** — every paragraph is
"Normal" — and carry their own conventions instead:

* a leading ``PUBLISHER HEADER BLOCK`` (Publisher / Website / Page type / Slug /
  JSON-LD hints / breadcrumb / geo),
* plain-text section titles (short, title-case lines),
* ``QUICK ANSWER`` and ``CALLOUT STAT`` labels,
* ``[ PHOTO PLACEHOLDER — … ]`` / ``[ INFOGRAPHIC PLACEHOLDER — … ]`` markers
  with a following ``Suggested caption:`` line,
* a ``Frequently Asked Questions`` block (question / answer paragraph pairs),
* inline ``[VERIFY — …]`` editorial flags and a closing ``VERIFY SUMMARY``.

This module detects that format and normalizes it into a `Document`: metadata +
a `raw_body` of Markdown/`:::` directives (so the composer produces hero / image
/ callout / accordion components) + a real FAQ section (so the schema generator
emits FAQPage). `[VERIFY]` items are stripped from the published text and
surfaced as warnings — the doc must not go live until they're resolved.
"""

from __future__ import annotations

import re

from ..models import BlockType, ContentBlock, Document, Section
from ..utils import section_slug, to_slug

_KV = re.compile(r"^([A-Za-z][\w /\-]{1,34}):\s*(.+)$")
_PLACEHOLDER = re.compile(r"^\[\s*(PHOTO|INFOGRAPHIC)\s+PLACEHOLDER\s*[—\-:]\s*(.+?)\s*\]$", re.I)
_CAPTION = re.compile(r"^Suggested caption:\s*(.+)$", re.I)
_VERIFY_INLINE = re.compile(r"\[\s*VERIFY[^\]]*\]")
_HEADER_KEYS = {
    "publisher", "website", "page type", "slug", "canonical url",
    "json-ld schema types", "author",
}


def looks_like_cms(texts: list[str]) -> bool:
    head = " | ".join(texts[:5]).upper()
    return "PUBLISHER HEADER BLOCK" in head or "CMS STAGE" in head


def build_cms_document(texts: list[str], source_name: str) -> Document:
    doc = Document(source_name=source_name, source_kind="docx")
    meta = doc.metadata
    warnings: list[str] = []

    idx, title = _parse_header(texts, meta)
    doc.title = title or source_name

    parts: list[str] = []
    faq_pairs: list[tuple[str, str]] = []
    quick_answer = ""
    featured_q = ""

    body = texts[idx:]
    i, n = 0, len(body)
    pending_section: list[str] = []     # buffered prose for the current section

    def flush_prose():
        if pending_section:
            parts.append("\n\n".join(pending_section))
            pending_section.clear()

    while i < n:
        line = body[i].strip()
        i += 1
        if not line:
            continue

        # Stop at the editorial VERIFY summary / footer.
        if line.upper().startswith("VERIFY SUMMARY") or line.startswith("GalapagosIslands.travel"):
            while i < n:
                tail = body[i].strip()
                i += 1
                if re.match(r"^V\d+\b", tail) or "VERIFY" in tail.upper():
                    warnings.append("VERIFY: " + _VERIFY_INLINE.sub("", tail).strip(" —-"))
            break

        # Strip inline [VERIFY …] flags, recording them.
        if _VERIFY_INLINE.search(line):
            for m in _VERIFY_INLINE.finditer(line):
                warnings.append("VERIFY: " + m.group(0).strip("[] ").removeprefix("VERIFY").strip(" —-:"))
            line = _VERIFY_INLINE.sub("", line).strip()
            if not line:
                continue

        # Byline -> author metadata.
        if line.lower().startswith("by ") and "," in line:
            meta.setdefault("author", line[3:].split(",")[0].strip())
            continue

        # Image / infographic placeholders.
        ph = _PLACEHOLDER.match(line)
        if ph:
            desc = ph.group(2).strip()
            caption = ""
            if i < n and _CAPTION.match(body[i].strip()):
                caption = _CAPTION.match(body[i].strip()).group(1).strip()
                i += 1
            elif ph.group(1).upper() == "INFOGRAPHIC" and i < n:
                caption = body[i].strip()
                i += 1
            if not featured_q:
                # The first image becomes the hero background, not an inline block.
                featured_q = desc
                continue
            flush_prose()
            cap = caption.replace('"', "'")
            parts.append(f'::: image query="{desc}"\n{cap}\n:::')
            continue

        # QUICK ANSWER -> hero subheading + meta description.
        if line.upper() == "QUICK ANSWER":
            if i < n:
                quick_answer = body[i].strip()
                i += 1
            continue

        # CALLOUT STAT -> callout directive with the following short paras.
        if line.upper() == "CALLOUT STAT":
            stat_lines = []
            while i < n and body[i].strip() and not _is_heading(body[i].strip()):
                cl = _VERIFY_INLINE.sub("", body[i].strip()).strip()
                i += 1
                if cl:
                    stat_lines.append(cl)
            flush_prose()
            inner = "\n\n".join(stat_lines)
            parts.append(f'::: callout tip title="Key stat"\n{inner}\n:::')
            continue

        # FAQ block -> accordion + collected pairs (for schema).
        if re.sub(r"[^a-z]", "", line.lower()) == "frequentlyaskedquestions":
            flush_prose()
            i = _consume_faq(body, i, n, parts, faq_pairs)
            continue

        # Section heading vs prose.
        if _is_heading(line):
            flush_prose()
            parts.append(f"## {line}")
        else:
            pending_section.append(line)

    flush_prose()

    # Lead with a hero from the title + quick answer + first image.
    hero_img = f' image="{featured_q}"' if featured_q else ""
    hero = f"::: hero{hero_img}\n# {doc.title}"
    if quick_answer:
        hero += f"\n{quick_answer}"
    hero += "\n:::"
    parts.insert(0, hero)

    doc.raw_body = "\n\n".join(p for p in parts if p.strip())

    # Metadata the pipeline uses.
    if quick_answer:
        meta.setdefault("meta_description", quick_answer)
    if featured_q:
        meta.setdefault("featured_image_query", featured_q)
    meta.setdefault("type", _map_page_type(meta.get("page_type", "")))
    meta.setdefault("destination", title.split("—")[0].strip() if title else "")
    meta.setdefault("status", "draft")
    if warnings:
        meta["_ingest_warnings"] = warnings + [
            "This doc is marked DRAFT pending VERIFY — review the flags above before publishing live."
        ]

    # A real FAQ section so the schema generator emits FAQPage.
    if faq_pairs:
        faq = Section(title="FAQ", slug="faq", level=2)
        for q, a in faq_pairs:
            faq.blocks.append(ContentBlock(type=BlockType.HEADING, text=q, level=3))
            faq.blocks.append(ContentBlock(type=BlockType.PARAGRAPH, text=a))
        doc.sections.append(faq)

    return doc


# --------------------------------------------------------------------------- #
def _parse_header(texts: list[str], meta: dict) -> tuple[int, str]:
    """Consume the PUBLISHER HEADER BLOCK; return (body_start_index, title)."""
    i, n = 0, len(texts)
    title = ""
    while i < n:
        line = texts[i].strip()
        if not line:
            i += 1
            continue
        if line.upper() == "PUBLISHER HEADER BLOCK":
            i += 1
            continue
        m = _KV.match(line)
        key = m.group(1).strip().lower() if m else ""
        if m and key in _HEADER_KEYS:
            _store_header_kv(key, m.group(2).strip(), meta)
            i += 1
            continue
        if line[0] in "[{\"" or '"@type"' in line or "latitude" in line or line.startswith("Home >") or line.endswith("]") or line.startswith('"'):
            if line.startswith("Home >"):
                meta.setdefault("breadcrumbs", [p.strip() for p in line.split(">")])
            i += 1
            continue
        # First content-like line is the page title.
        title = line
        i += 1
        break
    return i, title


def _store_header_kv(key: str, value: str, meta: dict) -> None:
    if key == "slug":
        segs = [s for s in value.strip("/").split("/") if s]
        if segs:
            meta["slug"] = to_slug(segs[-1])
            if len(segs) > 1:
                meta["url_section"] = segs[0]
    elif key == "page type":
        meta["page_type"] = value
    elif key == "author":
        meta.setdefault("author", value)
    elif key == "canonical url":
        meta["canonical_url"] = value
    elif key == "website":
        meta["site_url"] = value


def _consume_faq(body, i, n, parts, faq_pairs) -> int:
    parts.append("::: accordion")
    question = None
    answer: list[str] = []

    def flush():
        nonlocal question, answer
        if question is not None:
            ans = " ".join(answer).strip()
            parts.append(f"### {question}")
            parts.append(ans)
            faq_pairs.append((question, ans))
        question, answer = None, []

    while i < n:
        line = _VERIFY_INLINE.sub("", body[i].strip()).strip()
        if not line:
            i += 1
            continue
        # A non-question heading ends the FAQ block.
        if not line.endswith("?") and _is_heading(line) and question is None:
            break
        if not line.endswith("?") and _is_heading(line) and question is not None and not answer:
            break
        if line.endswith("?"):
            flush()
            question = line
        else:
            answer.append(line)
        i += 1
    flush()
    parts.append(":::")
    return i


def _is_heading(line: str) -> bool:
    line = line.strip()
    if not line or line[0] not in _UPPER or line.endswith((".", ",", ":", ";", "?", "!")):
        return False
    if line.startswith("[") or _KV.match(line) or line.lower().startswith("www."):
        return False
    if len(line) > 72:
        return False
    return len(line.split()) <= 9


# --------------------------------------------------------------------------- #
# Stage-8 "CMS-READY ANNOTATED" docs. A newer house format than the one above:
# a `<domain> | CMS Stage 8 | <name> | vN` banner, `[AIO BLOCK …]` markers, data
# tables, and a large trailing block of pipeline/audit logs that must be dropped.
# Parsed into the same Document shape the HTML reader produces (geo_answer,
# feature sections with HTML tables, faqs, cta_blocks, sources).
# --------------------------------------------------------------------------- #
_STAGE8_BANNER = re.compile(r"\bCMS\s+Stage\s+\d+\b", re.I)
_BRACKET_MARK = re.compile(r"^\[.*\]$")  # a whole-line [AIO BLOCK …] / [PHOTO …]
_INLINE_BRACKET = re.compile(r"\[[^\]]*\]")  # inline [source: …] / [VERIFY …] tags
_SOURCE_URL = re.compile(r"^(.*?)\s+[—–-]\s+(https?://\S+)\s*$")
_META_TITLE = re.compile(r"^Meta Title\s*\(", re.I)  # "Meta Title (53 characters):"
_META_DESC = re.compile(r"^Meta Description\s*\(", re.I)
# Known Stage-8 header KV labels. Only these are consumed as header metadata; any
# other "X: Y" line (e.g. the H1 "Bartolomé Island: Pinnacle Rock…") is content.
_STAGE8_HEADER_KEYS = {
    "slug", "page type", "primary cta", "secondary cta", "trade cta",
    "objections", "objections pre-empted", "schema", "aio blocks", "publisher",
    "persona", "funnel", "canonical url", "canonical", "author", "website",
}
# Everything from here on is internal pipeline/audit scaffold — never published.
_STAGE8_JUNK = re.compile(
    r"^(VERIFY Summary|Open \[VERIFY\]|WF\d|WF5|WF6|WF7|Pipeline complete|"
    r"Logged by WF|Files Produced|Audit date|Part [A-G]\b|AUDITOR|"
    r"Meta Title & Meta Description)",
    re.I,
)


# The AIO/answer marker comes in two house variants: the bracketed
# "[AIO BLOCK 1 — speakable]" (answer on the next line) and the inline
# "AIO SUMMARY BLOCK (≤50 words …): <answer>" (answer after the colon).
_AIO_MARK = re.compile(r"\bAIO\s+(?:SUMMARY\s+)?BLOCK", re.I)
_AIO_SUMMARY = re.compile(r"^AIO\s+SUMMARY\s+BLOCK\b[^:]*:\s*(.+)$", re.I)
# Internal scaffold lines in the header block — never the page title/content.
_SCAFFOLD_RE = re.compile(
    r"^(?:■|▪|□|▶|●)|^INTERNAL\b|^JSON-?LD\b|^ENTITY RULES\b|^PUBLISH URL\b|"
    r"PUBLISHER HEADER BLOCK|NOT PUBLISHED|^SITE\b|^TIER\b|^ROBOTS\b",
    re.I,
)


def _is_scaffold_line(line: str) -> bool:
    return bool(_SCAFFOLD_RE.search(line.strip()))


def looks_like_stage8(texts: list[str]) -> bool:
    banner = _STAGE8_BANNER.search(texts[0]) if texts else None
    has_aio = any(_AIO_MARK.search(t) for t in texts[:60])
    return bool(banner) and has_aio


def _esc(s: str) -> str:
    return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def _table_html(rows: list[list[str]]) -> str:
    rows = [[c.strip() for c in r] for r in rows if any(c.strip() for c in r)]
    if not rows:
        return ""
    head, *body = rows
    out = ["<table><thead><tr>"]
    out += [f"<th>{_esc(c)}</th>" for c in head]
    out.append("</tr></thead><tbody>")
    for r in body:
        out.append("<tr>" + "".join(f"<td>{_esc(c)}</td>" for c in r) + "</tr>")
    out.append("</tbody></table>")
    return "".join(out)


def _stage8_scan_meta(texts: list[str], meta: dict) -> None:
    """Pull the curated Meta Title / Meta Description out of the trailing block."""
    for i, t in enumerate(texts):
        line = t.strip()
        if _META_TITLE.match(line) and i + 1 < len(texts):
            val = texts[i + 1].strip()
            if val:
                meta.setdefault("seo_title", val)
        elif _META_DESC.match(line) and i + 1 < len(texts):
            val = texts[i + 1].strip()
            if val:
                meta.setdefault("meta_description", val)


def build_cms_stage8_document(items: list, texts: list[str], source_name: str) -> Document:
    """``items`` is an ordered list of ('p', text) / ('table', rows) blocks."""
    doc = Document(source_name=source_name, source_kind="docx")
    meta = doc.metadata
    warnings: list[str] = []

    idx, title = _stage8_header(items, meta)
    doc.title = title or source_name
    _stage8_scan_meta(texts, meta)

    sections: list[Section] = []
    faq_pairs: list[tuple[str, str]] = []
    cta_blocks: list[dict] = []
    sources: list[dict] = []
    cur_title, cur_slug, buf = "", "_lead", []
    mode = "body"  # body | faq | sources | cta
    expect_geo = False

    def flush() -> None:
        html = "".join(buf).strip()
        if html or cur_title:
            blocks = [ContentBlock(type=BlockType.HTML, html=html)] if html else []
            sections.append(Section(title=cur_title, level=2, slug=cur_slug, blocks=blocks))
        buf.clear()

    for item in items[idx:]:
        kind, val, bold = _kind_val_bold(item)
        if kind == "table":
            if mode == "cta":
                cta_blocks.extend(_stage8_cta_from_table(val, meta))
            elif val and len(val) == 1 and len(val[0]) == 1:
                buf.append(f"<p>{_esc(val[0][0].strip())}</p>")  # 1x1 -> prose
            else:
                buf.append(_table_html(val))
            continue

        line = str(val).strip()
        if not line:
            continue
        if _STAGE8_JUNK.match(line):
            break  # trailing pipeline/audit scaffold — stop here

        # Inline AIO summary: "AIO SUMMARY BLOCK (…): <answer>" -> geo_answer.
        m_sum = _AIO_SUMMARY.match(line)
        if m_sum:
            meta.setdefault("geo_answer", f"<p>{_esc(_strip_tags(m_sum.group(1).strip()))}</p>")
            continue

        # The speakable answer box: the paragraph right after the AIO-1 marker.
        if expect_geo and not _BRACKET_MARK.match(line):
            meta.setdefault("geo_answer", f"<p>{_esc(_strip_tags(line))}</p>")
            expect_geo = False
            continue
        if re.match(r"^\[\s*AIO BLOCK 1\b", line, re.I) and "speakable" in line.lower():
            expect_geo = True
            continue
        if _BRACKET_MARK.match(line):
            continue  # drop [AIO BLOCK …] / [PHOTO/INFOGRAPHIC PLACEHOLDER …]

        # Byline -> author.
        by = re.match(r"^([A-ZÁ-Ú][\w'’.-]+(?: [A-ZÁ-Ú][\w'’.-]+)+),\s", line)
        if by and not meta.get("author") and (
            "contributor" in line.lower() or "naturalist" in line.lower() or "expert" in line.lower()
        ):
            meta["author"] = by.group(1).strip()
            continue

        # Once in the sources block (the last real section), every line is a
        # citation — don't let a URL line be mistaken for a new heading.
        if mode == "sources":
            src = _stage8_source(line)
            if src:
                sources.append(src)
            continue

        stripped = _strip_marks(line)
        heading = _is_heading(stripped) or (bold and _is_bold_heading(stripped))
        if heading:
            h = _strip_marks(line)
            low = h.lower()
            if low.startswith("faq") or "frequently asked" in low:
                flush()
                mode = "faq"
                continue
            if low.startswith("sources") or low.startswith("citations"):
                flush()
                mode = "sources"
                continue
            if low.startswith("plan your") or "talk to" in low:
                flush()
                mode = "cta"
                cur_title, cur_slug = h, section_slug(h)
                continue
            # A normal section heading (skip a duplicate of the current one).
            new_slug = section_slug(h)
            if not (new_slug == cur_slug and not "".join(buf).strip()):
                flush()
                cur_title, cur_slug = h, new_slug
            mode = "body"
            continue

        # FAQ question / answer pairs.
        if mode == "faq":
            if line.endswith("?"):
                faq_pairs.append((_strip_tags(line), ""))
            elif faq_pairs:
                q, a = faq_pairs[-1]
                faq_pairs[-1] = (q, (a + " " + _strip_tags(line)).strip())
            continue
        # Body / cta prose.
        prose = _strip_tags(line)
        for m in _INLINE_BRACKET.finditer(line):
            if "verify" in m.group(0).lower():
                warnings.append("VERIFY: " + m.group(0).strip("[] "))
        if prose:
            buf.append(f"<p>{_esc(prose)}</p>")

    flush()
    doc.sections = [s for s in sections if s.blocks or s.slug == "faq"]
    if faq_pairs:
        faq = Section(title="FAQ", slug="faq", level=2)
        for q, a in faq_pairs:
            faq.blocks.append(ContentBlock(type=BlockType.HEADING, text=q, level=3))
            faq.blocks.append(ContentBlock(type=BlockType.PARAGRAPH, text=a))
        doc.sections.append(faq)

    if cta_blocks:
        meta["cta_blocks"] = cta_blocks
    if sources:
        meta["sources"] = sources
    meta.setdefault("type", _map_page_type(meta.get("page_type", "")))
    meta.setdefault("status", "draft")
    if warnings:
        meta["_ingest_warnings"] = warnings
    return doc


def _kind_val_bold(item) -> tuple[str, object, bool]:
    """Unpack an ordered block tolerantly: ('p', text[, bold]) / ('table', rows)."""
    kind = item[0]
    val = item[1] if len(item) > 1 else ""
    bold = bool(item[2]) if len(item) > 2 else False
    return kind, val, bold


def _apply_header_kv(key: str, value: str, meta: dict) -> None:
    """Store a known header KV. Routing keys (slug/url) win over content."""
    if key == "slug":
        segs = [s for s in value.strip("/").split("/") if s]
        if segs:
            meta["slug"] = to_slug(segs[-1])
            if len(segs) > 1:
                meta["url_section"] = segs[0]  # /wildlife/darwin-finches/ -> wildlife
    elif key in ("canonical url", "canonical"):
        tail = value.split("://", 1)[-1]
        segs = [s for s in tail.split("/")[1:] if s]  # drop the domain
        if segs:
            meta.setdefault("slug", to_slug(segs[-1]))
            if len(segs) > 1:
                meta.setdefault("url_section", segs[0])
    elif key == "page type":
        meta.setdefault("page_type", value.split("|")[0].strip())
    elif key == "primary cta":
        meta.setdefault("primary_cta", value.split("|")[0].strip())
    elif key == "author":
        meta.setdefault("author", value.split(",")[0].strip())


def _stage8_header(items: list, meta: dict) -> tuple[int, str]:
    """Consume the header block and return ``(body_start, title)``.

    The body starts at the first AIO marker; everything before it is header —
    banner, KV lines (some docs wrap them in a large "PUBLISHER HEADER BLOCK" with
    JSON-LD, internal links and entity rules) and the H1 title. The title is the
    last bold, non-scaffold line before the AIO marker; when bold data is absent
    (e.g. hand-built test items) it falls back to the last non-banner / non-KV /
    non-scaffold line.
    """
    aio_idx = None
    for i, item in enumerate(items):
        kind, val, _ = _kind_val_bold(item)
        if kind == "p" and _AIO_MARK.search(str(val)):
            aio_idx = i
            break
    scan_end = aio_idx if aio_idx is not None else len(items)

    bold_title = ""
    plain_title = ""
    for i in range(scan_end):
        kind, val, bold = _kind_val_bold(items[i])
        if kind == "table":
            continue
        line = str(val).strip()
        if not line or _STAGE8_BANNER.search(line):
            continue
        m = _KV.match(line)
        if m and m.group(1).strip().lower() in _STAGE8_HEADER_KEYS:
            _apply_header_kv(m.group(1).strip().lower(), m.group(2).strip(), meta)
            continue
        if _is_scaffold_line(line):
            continue
        plain_title = line          # last non-scaffold content line (fallback)
        if bold:
            bold_title = line       # last bold non-scaffold line (preferred H1)

    title = bold_title or plain_title
    body_start = aio_idx if aio_idx is not None else scan_end
    return body_start, title


def _is_bold_heading(line: str) -> bool:
    """A bold paragraph that reads as a section title (used when the text-length
    heuristic in ``_is_heading`` is too strict — e.g. a long title with a colon)."""
    line = line.strip()
    if not line or line[0] not in _UPPER:
        return False
    if line.endswith((".", ",", ";")):   # a sentence, not a heading
        return False
    if line.startswith("[") or line.lower().startswith("www."):
        return False
    if len(line) > 100:
        return False
    return len(line.split()) <= 16


def _stage8_cta_from_table(rows: list[list[str]], meta: dict) -> list[dict]:
    rows = [[c.strip() for c in r] for r in rows if any(c.strip() for c in r)]
    if not rows:
        return []
    header = [c.lower() for c in rows[0]]
    values = rows[1] if len(rows) > 1 else rows[0]
    out = []
    for i, cell in enumerate(values):
        col = header[i] if i < len(header) else ""
        aud = "Travel trade" if ("trade" in col or "dmc" in col) else "Direct travelers"
        text = _strip_tags(cell)
        if text:
            row = {"audience": aud, "text": text}
            if aud == "Direct travelers" and meta.get("primary_cta"):
                row["button_label"] = meta["primary_cta"]
            out.append(row)
    return out


def _stage8_source(line: str) -> dict | None:
    line = _strip_tags(line).strip(" .")
    if not line or ".md" in line.lower() or ".csv" in line.lower() or "internal" in line.lower():
        return None  # internal source-of-truth files are not public citations
    m = _SOURCE_URL.match(line)
    if m:
        return {"label": m.group(1).strip(" —–-"), "url": m.group(2).strip()}
    return {"label": line, "url": ""}


def _strip_marks(line: str) -> str:
    """Remove a trailing bracket marker from a heading, e.g. 'KEY TAKEAWAYS [AIO BLOCK 4]'."""
    return re.sub(r"\s*\[[^\]]*\]\s*$", "", line).strip()


def _strip_tags(line: str) -> str:
    """Strip inline [source: …] / [VERIFY …] / [Citation] tags from prose."""
    return re.sub(r"\s{2,}", " ", _INLINE_BRACKET.sub("", line)).strip()


def _map_page_type(page_type: str) -> str:
    p = (page_type or "").lower()
    if "island" in p or "destination" in p or "guide" in p:
        return "destination"
    if "wildlife" in p or "species" in p:
        return "wildlife_tier1"
    if "tour" in p or "itinerary" in p:
        return "tour"
    if "cruise" in p:
        return "cruise"
    return "destination"


_UPPER = set("ABCDEFGHIJKLMNOPQRSTUVWXYZ")
