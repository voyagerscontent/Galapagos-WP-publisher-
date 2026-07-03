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


# Strong "internal / do-not-publish" markers. Scanned across the WHOLE table
# because these blocks often lead with a separator rule, pushing the real marker
# into a later row. Kept CTA-agnostic (no 'Voyagers'/'Latin Trails') so real CTA
# tables are never dropped.
_INSTR_HEAD_CHARS = ("⚠", "▶", "■", "⬛", "⚑", "🟩", "🟢", "🛑")
_INSTR_MARKERS = (
    "do not publish", "internal cms", "cms use only", "internal use only",
    "cms editor", "publisher header", "webmaster", "green box",
    "internal instruction", "requires editorial review", "version footer",
    "verify items", "access note", "for internal use",
)


def _is_instruction_table(rows: list[list[str]]) -> bool:
    """Internal-instruction / explainer tables (green boxes) are not content."""
    if not rows:
        return False
    head = " ".join(rows[0]).strip()
    full = " ".join(c for row in rows for c in row).lower()
    looks_internal = (
        head.startswith(_INSTR_HEAD_CHARS)
        or "what this is" in full
        or any(m in full for m in _INSTR_MARKERS)
    )
    if not looks_internal:
        return False
    # Some docs pack the real dual-CTA inside the same green box as the notes.
    # Keep those so the CTA path (which dedupes to 2) can mine them.
    if _AUD_SPLIT_RE.search(full) or re.search(r"\bcta\s*\d", full):
        return False
    return True


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


# A GEO/AIO answer block, in any of its house variants. The answer is the prose
# AFTER the marker line ("AIO / GEO BLOCK …", "PUBLISH THIS ANSWER TEXT:", etc.).
_GEO_MARKER_RE = re.compile(
    r"(AIO\b|GEO\s+(?:BLOCK|ANSWER)|PUBLISH THIS ANSWER TEXT|answer extraction|Quick Answer)",
    re.IGNORECASE,
)
_PUBLISH_ANSWER_RE = re.compile(r"PUBLISH(?:ABLE)?[^\n:]*ANSWER[^\n:]*:\s*", re.IGNORECASE)
_GEO_STOP_RE = re.compile(r"^(PLACE UNDER|PUBLISH URL|SLUG\b|■|⚠|⬛|VERSION)", re.IGNORECASE)
_GEO_EXPLAINER_RE = re.compile(r"^(WHAT THIS|WHY THIS|HOW \b|HOW THIS)", re.IGNORECASE)


def _extract_geo_answer(rows: list[list[str]]) -> str:
    """Pull the publishable ~50-word GEO/AI answer out of a GEO block table.

    Handles two house styles: an explicit 'PUBLISH THIS ANSWER TEXT:' marker
    (answer follows it), or an 'AIO / GEO …' header whose answer is the first
    prose after the marker line (skipping any 'WHAT THIS IS' explainer).
    """
    text = "\n".join(cell for row in rows for cell in row if cell).strip()
    lines = [ln.strip() for ln in text.split("\n") if ln.strip()]

    # Case 1: explicit publishable-answer marker.
    for idx, line in enumerate(lines):
        if _PUBLISH_ANSWER_RE.search(line):
            rest = _PUBLISH_ANSWER_RE.sub("", line).strip()
            collected = [rest] if rest else []
            for nxt in lines[idx + 1:]:
                if _GEO_STOP_RE.match(nxt):
                    break
                collected.append(nxt)
            ans = " ".join(" ".join(collected).split()).strip()
            if ans:
                return ans

    # Case 2: AIO / GEO header, answer is the first prose after the marker.
    if not _GEO_MARKER_RE.search(text[:160]):
        return ""
    out: list[str] = []
    started = False
    for line in lines:
        if not started:
            started = bool(_GEO_MARKER_RE.search(line))
            continue
        if _GEO_STOP_RE.match(line):
            break
        if _GEO_EXPLAINER_RE.match(line):
            if out:
                break
            continue  # skip explainer text before the answer
        out.append(line)
    return " ".join(" ".join(out).split()).strip()


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


# A seasonal wildlife/what-to-see calendar: 2 columns, a "season/period/month"
# header on the left and a "wildlife/highlights/conditions" header on the right.
_CAL_SEASON_HEAD = ("season", "period", "month", "when to", "time of year")
_CAL_HL_HEAD = ("wildlife", "highlight", "condition", "what to see", "activity", "to see")


def _is_wildlife_calendar_table(rows: list[list[str]]) -> bool:
    if _ncols(rows) < 2 or len(rows) < 2:
        return False
    h0 = rows[0][0].lower()
    h1 = " ".join(rows[0][1:]).lower()
    return any(k in h0 for k in _CAL_SEASON_HEAD) and any(k in h1 for k in _CAL_HL_HEAD)


def _extract_wildlife_calendar(rows: list[list[str]]) -> list[dict]:
    """Season → highlights. The left cell may carry a parenthetical label,
    e.g. 'January – April (Warm / Wet Season)' -> period + label."""
    out: list[dict] = []
    for r in rows[1:]:
        if len(r) < 2:
            continue
        season = (r[0] or "").strip()
        highlights = (r[1] or "").strip()
        if not season and not highlights:
            continue
        label = ""
        m = re.search(r"\(([^)]*)\)", season)
        if m:
            label = m.group(1).strip()
            season = (season[: m.start()] + season[m.end():]).strip()
        period = re.sub(r"\s+", " ", season.replace("\n", " ")).strip(" —–-")
        highlights = re.sub(r"\[VERIFY[^\]]*\]", "", highlights).strip()
        highlights = re.sub(r"\s+", " ", highlights.replace("\n", " ")).strip()
        if period or highlights:
            out.append({"period": period, "label": label, "highlights": highlights})
    return out


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
_W_NS = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
_WT = _W_NS + "t"
_WBR = _W_NS + "br"  # soft line break (Shift+Enter)
_WCR = _W_NS + "cr"  # carriage return
_WTAB = _W_NS + "tab"
_INTERNAL_LINK_RE = re.compile(r"\[INTERNAL LINK:\s*(.+?)\s*(?:→|->)\s*([^\]\s]+)\s*\]")
# "⬛ WEBMASTER DESIGN NOTE: …" styling instructions — never published, even when
# appended inline to a real paragraph. Stripped from the marker to end of line.
_DESIGN_NOTE_RE = re.compile(
    r"\s*[^\w\s\n]*\s*WEBMASTER\s+DESIGN\s+NOTE\b[^\n]*", re.IGNORECASE
)


def _p_text(paragraph) -> str:
    # Walk descendants in document order so soft line breaks (<w:br>) and tabs
    # survive as real separators — Word packs multi-line cells (e.g. CTA blocks)
    # into ONE paragraph with <w:br>, and dropping them runs the lines together.
    parts: list[str] = []
    for node in paragraph._p.iter():
        if node.tag == _WT:
            parts.append(node.text or "")
        elif node.tag in (_WBR, _WCR):
            parts.append("\n")
        elif node.tag == _WTAB:
            parts.append("\t")
    return "".join(parts)


def _cell_text(cell) -> str:
    return "\n".join(_p_text(p) for p in cell.paragraphs).strip()


# A visual rule made of box-drawing / dash / underscore / equals runs. These are
# layout separators in the house docs, never content — drop them so they don't
# become empty feature sections or trail into the previous card's prose.
_SEP_CHARS = set("─━╌╍—–-_=~⎯。・ \t")
_SEP_KEY = set("─━╌╍—–-_=")


def _is_separator(text: str) -> bool:
    t = text.strip()
    if len(t) < 3 or any(ch.isalnum() for ch in t):
        return False
    return all(ch in _SEP_CHARS for ch in t) and any(ch in _SEP_KEY for ch in t)


# "→ INTERNAL LINK: /url/ [| See also: /url2/ | /url3/]" — the arrow-style
# internal-link notation used across the house docs (no brackets, no label).
_ARROW_LINK_RE = re.compile(r"(?:→|->)\s*INTERNAL LINK:\s*([^\n]+)", re.IGNORECASE)
_ANY_URL_RE = re.compile(r"https?://\S+|/[\w\-./]+/?")


def _slug_label(url: str) -> str:
    """Derive a human label from a URL slug: /wildlife/giant-tortoise/ -> 'Giant Tortoise'."""
    slug = url.rstrip("/").rsplit("/", 1)[-1]
    slug = re.sub(r"[-_]+", " ", slug).strip()
    return slug.title() if slug else "Learn more"


def _arrow_links_to_md(text: str) -> str:
    """'→ INTERNAL LINK: /url/ | …' -> Markdown link(s) with slug-derived labels.

    Drops the arrow + "INTERNAL LINK:"/"See also:" scaffolding, keeping each URL as
    a real link. A standalone link paragraph later becomes a card button; an inline
    one stays an inline <a>.
    """
    def repl(m: re.Match) -> str:
        urls = _ANY_URL_RE.findall(m.group(1))
        if not urls:
            return ""
        return " ".join(f"[{_slug_label(u)}]({u})" for u in urls)

    return _ARROW_LINK_RE.sub(repl, text)


def _clean_markers(text: str) -> str:
    """[INTERNAL LINK: label → /url/] -> Markdown [label](/url/).

    WYSIWYG fields render the Markdown link to a real <a>; textarea fields strip
    it back to the label (they can't hold links). Either way the URL is kept here.
    """
    text = _DESIGN_NOTE_RE.sub("", text)
    text = _INTERNAL_LINK_RE.sub(r"[\1](\2)", text)
    return _arrow_links_to_md(text)


_CTA_SIGNALS = (
    "cta button:", "call to action", "dual cta block", "with a specialist",
    "book with voyagers", "direct travelers — book", "direct travellers — book",
    "voyagers travel company", "latin trails", "planning your galápagos",
    "planning your galapagos", "independent travellers", "independent travelers",
)


def _is_cta_table(rows: list[list[str]]) -> bool:
    text = " ".join(c for row in rows for c in row).lower()
    return any(sig in text for sig in _CTA_SIGNALS)


_TRADE_MARK_RE = re.compile(
    r"latin trails|dmc|trade use|travel agent|tour operator|wholesal|"
    r"industry partner|group program|travel trade",
    re.IGNORECASE,
)
# Audience label ("DIRECT TRAVELERS", "TRAVEL TRADE"…) up to its separator, which
# may be a colon OR an em/en dash ("DIRECT TRAVELERS — Book with…").
_AUD_SPLIT_RE = re.compile(
    r"(INDEPENDENT TRAVELLERS?|DIRECT TRAVEL\w*|TRAVEL AGENTS?|TRAVEL TRADE|TRADE\b)"
    r"[^\n:—–]*[:—–]",
    re.IGNORECASE,
)
_FIELD_PREFIX_RE = re.compile(r"^(website|email|web|tel|phone|button)\s*:\s*", re.IGNORECASE)


def _cta_audience(text: str) -> str:
    return "Travel trade" if _TRADE_MARK_RE.search(text) else "Direct travelers"


def _split_audience_segments(cell: str) -> list[str]:
    marks = list(_AUD_SPLIT_RE.finditer(cell))
    if len(marks) < 2:
        return []
    return [
        cell[m.start(): (marks[i + 1].start() if i + 1 < len(marks) else len(cell))].strip()
        for i, m in enumerate(marks)
    ]


# A button line like "Contact Latin Trails → https://…" or "Book now → /cruises/".
_CONTACT_BTN_RE = re.compile(r"^(.{0,60}?)\s*(?:→|->)\s*(.+)$")
# A bare domain (no scheme), e.g. "galapagosislands.travel/contact", "latintrails.com".
_BARE_DOMAIN_RE = re.compile(
    r"\b([a-z0-9][a-z0-9-]*(?:\.[a-z0-9-]+)*\.(?:com|travel|org|net|io|co)(?:\.[a-z]{2})?(?:/\S*)?)",
    re.IGNORECASE,
)


def _norm_url(u: str) -> str:
    u = u.rstrip(".,);")
    if u.startswith(("http://", "https://", "/", "mailto:")):
        return u
    return "https://" + u


def _cta_from_text(cell: str) -> dict:
    btn = _CTA_BUTTON_RE.search(cell)
    cell_wo = _CTA_BUTTON_RE.sub("", cell)
    # House format: "INDEPENDENT TRAVELLERS: <body…>" — the audience label is a
    # prefix, not a headline; everything after it is body copy (no separate title).
    aud_prefix = _AUD_SPLIT_RE.match(cell_wo.strip())
    if aud_prefix:
        cell_wo = cell_wo[aud_prefix.end():].strip()
    lines = [ln.strip() for ln in cell_wo.split("\n") if ln.strip()]
    # After the audience label: if more copy follows, the first line is the
    # headline ("DIRECT TRAVELERS — Book with Voyagers…"); a lone line is body.
    if len(lines) > 1:
        title, rest = lines[0], lines[1:]
    else:
        title, rest = "", lines
    url = btn.group(2).strip() if btn else ""
    label = btn.group(1).strip().rstrip("→ ").strip() if btn else ""
    body: list[str] = []
    for ln in rest:
        # A "Label → url" contact/button line: lift it out of the body.
        bm = _CONTACT_BTN_RE.match(ln)
        if not btn and bm and _ANY_URL_RE.search(bm.group(2)):
            urls = _ANY_URL_RE.findall(bm.group(2))
            url = url or _norm_url(urls[-1])  # prefer the absolute (last) URL
            label = label or bm.group(1).strip()
            continue
        # A trailing bare-domain URL (no scheme, no arrow): pull it as the button,
        # but only when it sits at the END of the line — not when it opens a
        # sentence ("GalapagosIslands.travel is maintained by…").
        if not url and not _URL_RE.search(ln):
            dm = _BARE_DOMAIN_RE.search(ln)
            if dm and dm.end() >= len(ln.rstrip()):
                url = _norm_url(dm.group(1))
                ln = ln[: dm.start()].strip()
                if not ln:
                    continue
        if not url:
            m = _URL_RE.search(ln)
            if m:
                url = m.group(0).rstrip(".,);")
        if _FIELD_PREFIX_RE.match(ln):
            continue  # drop "Website:/Email:" label lines from body
        body.append(ln)
    block = {
        "audience": _cta_audience(cell),
        "title": title,
        "text": " ".join(" ".join(body).split()),
    }
    if label:
        block["button_label"] = label
    if url:
        block["button_url"] = url
    return block


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
    """Parse the two formal CTAs (Voyagers + Latin Trails) across house formats.

    The whole table is joined first, because a CTA is often spread across cells:
    the audience+headline ("DIRECT TRAVELERS — Book with Voyagers…") in one cell,
    the body and contact lines in others. Joining lets the headline become the
    CTA title instead of being mistaken for body copy.
    """
    joined = "\n".join(c.strip() for row in rows for c in row if c.strip())
    if re.search(r"call to action|CTA\s*\d+\s*[—–-]", joined, re.IGNORECASE):
        blocks = _parse_cta_instruction(joined)
        if blocks:
            return blocks
    segments = _split_audience_segments(joined)  # "INDEPENDENT…:" / "DIRECT… —" …
    if len(segments) >= 2:
        return [_cta_from_text(seg) for seg in segments]
    if _AUD_SPLIT_RE.match(joined):  # a single-audience CTA table (e.g. Floreana)
        return [_cta_from_text(joined)]
    # Fallback: simple tables with one contact/CTA block per cell.
    out: list[dict] = []
    for row in rows:
        for cell in row:
            if cell.strip():
                out.append(_cta_from_text(cell.strip()))
    return out


_CTA_KEYWORDS = (
    "book", "contact", "voyager", "latin trails", "travel company", "dmc",
    "itinerary", "plan your", "specialist", "enquir", "inquir", "trade",
)
# SEO / editorial scaffolding that sometimes carries a URL but is never a CTA.
_CTA_JUNK = (
    "title tag", "meta description", "focus keyword", "canonical", "url slug",
    "slug:", "h1 tag", "schema", "og:", "alt text", "internal link",
)


def _cta_is_real(b: dict) -> bool:
    """Reject scaffolding rows (separators, publisher/SEO notes) posing as CTAs."""
    blob = " ".join(
        [b.get("title") or "", b.get("text") or "", b.get("button_label") or ""]
    ).strip().lower()
    if any(m in blob for m in _INSTR_MARKERS) or any(m in blob for m in _CTA_JUNK):
        return False
    if b.get("button_url"):
        return True
    if not blob or _is_separator(blob):
        return False
    return any(k in blob for k in _CTA_KEYWORDS)


def _dedupe_ctas(blocks: list[dict]) -> list[dict]:
    """Reduce the raw CTA candidates to the two canonical ones (best Direct +
    best Trade), dropping junk. A block with a real button and more copy wins."""
    best: dict[str, tuple] = {}
    for b in blocks:
        if not _cta_is_real(b):
            continue
        aud = b.get("audience") or "Direct travelers"
        score = (1 if b.get("button_url") else 0, len(b.get("text") or "") + len(b.get("title") or ""))
        if aud not in best or score > best[aud][0]:
            best[aud] = (score, b)
    order = ["Direct travelers", "Travel trade"]
    out = [best[a][1] for a in order if a in best]
    out += [v[1] for a, v in best.items() if a not in order]
    return out


def _extract_sources(section: Section) -> list[dict]:
    """Citations -> [{label, url}]. Requires a URL; supports one-line
    ('Label — desc. URL') and multi-line ('1. Label' / 'URL' / 'desc') styles."""
    out: list[dict] = []
    lines: list[str] = []
    for b in section.blocks:
        if b.items:
            lines.extend(b.items)
        elif b.text:
            lines.append(b.text)
    pending = ""
    for line in lines:
        m = _URL_RE.search(line)
        if not m:
            pending = line.strip()  # a label line for a following URL
            continue
        url = m.group(0).rstrip(".,);")
        label = (line[: m.start()]).strip().rstrip("—–-.) ").strip() or pending
        label = re.sub(r"^\d+[.)]\s*", "", label).strip()  # drop list numbering
        out.append({"label": label, "url": url})
        pending = ""
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


_MD_LINK_RE = re.compile(r"\[([^\]]+)\]\(([^)\s]+)\)")


def _split_off_button(paras: list[str]) -> tuple[list[str], dict | None]:
    """Pull a trailing button off a card's prose.

    Fires when the LAST paragraph is *only* link(s) (the internal-link notation
    sits on its own line) or an explicit '→ Label …/url' line. A paragraph that
    ends with a link but also carries prose is left alone — that link stays inline.
    """
    if not paras:
        return paras, None
    last = paras[-1].strip()
    body = re.sub(r"^(?:→|->)\s*", "", last)  # tolerate a leading arrow

    # The paragraph is purely link(s) (optionally "See also:" / "|" separated).
    links = _MD_LINK_RE.findall(body)
    residue = _MD_LINK_RE.sub("", body)
    residue = re.sub(r"(?i)see also:?|[\s,|·•]", "", residue).strip()
    if links and not residue:
        label, url = links[0]
        return paras[:-1], {"label": label.strip(), "url": url.strip()}

    # Explicit "→ Label /url" with a bare path/URL.
    if last.startswith(("→", "->")):
        arrow = re.search(r"(.+?)\s*(?:→|->)\s*(\S+)$", body)
        if arrow and arrow.group(2).startswith(("/", "http")):
            return paras[:-1], {"label": arrow.group(1).strip(), "url": arrow.group(2)}
    return paras, None


def _extract_cards(section: Section) -> tuple[str, list[tuple[str, list[str]]]]:
    """Split a prose section into (intro, [(h3_heading, paragraphs), …])."""
    intro: list[str] = []
    cards: list[tuple[str, list[str]]] = []
    name: str | None = None
    desc: list[str] = []
    for b in section.blocks:
        if b.type == BlockType.HEADING:
            if name is not None:
                cards.append((name, desc))
            name, desc = b.text, []
        elif b.text or b.items:
            chunk = "\n".join(b.items) if b.items else b.text
            (desc if name is not None else intro).append(chunk)
    if name is not None:
        cards.append((name, desc))
    return "\n\n".join(intro).strip(), cards


def _extract_visitor_sites_prose(section: Section) -> tuple[str, list[dict]]:
    """Visitor sites as H3 sub-headings + prose. Returns (section intro, sites)."""
    tl = section.title.lower()
    access = "Cruise-only" if "cruise" in tl else ("Land-based" if "land" in tl else "")
    intro, cards = _extract_cards(section)
    out: list[dict] = []
    for name, paras in cards:
        paras, button = _split_off_button(paras)
        row = {
            "site_name": name.strip(),
            "description": "\n\n".join(paras).strip(),
            "access_type": access,
        }
        if button:
            row["button_label"] = button["label"]
            row["button_url"] = button["url"]
        out.append(row)
    return intro, out


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

        # Visual separator rule (────, ____) -> layout, not content.
        if _is_separator(text):
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
        if _is_wildlife_calendar_table(rows):
            cal = _extract_wildlife_calendar(rows)
            if cal:
                doc.metadata.setdefault("wildlife_calendar", []).extend(cal)
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

    # Collapse the raw CTA candidates to the two canonical CTAs (drops any
    # scaffolding that leaked through a mixed green box).
    if doc.metadata.get("cta_blocks"):
        doc.metadata["cta_blocks"] = _dedupe_ctas(doc.metadata["cta_blocks"])

    # Citations / sources section -> structured metadata ('sources',
    # 'sources-citations', 'sources & citations'…).
    for s in doc.sections:
        if s.slug.startswith("sources") or s.title.lower().startswith("sources"):
            srcs = _extract_sources(s)
            if srcs:
                doc.metadata.setdefault("sources", []).extend(srcs)
            break

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
    # Match "wildlife" as a whole word anywhere in the heading so free-form
    # titles ("The Wildlife", "Christmas Iguanas and Other Wildlife") still
    # qualify. When several sections mention wildlife, keep the one that yields
    # the most species rows (so we don't stop on a passing mention that has none).
    best: tuple[int, Section, str, list[dict]] | None = None
    for s in doc.sections:
        if not re.search(r"\bwildlife\b", s.slug) and not re.search(
            r"\bwildlife\b", s.title.lower()
        ):
            continue
        intro, wildlife = _extract_wildlife(s)
        if not wildlife:
            continue
        if best is None or len(wildlife) > best[0]:
            best = (len(wildlife), s, intro, wildlife)
    if best is not None:
        _, s, intro, wildlife = best
        doc.metadata["wildlife_title"] = s.title
        if intro:
            doc.metadata["wildlife_intro"] = intro
        doc.metadata["wildlife"] = wildlife

    # "Visitor Sites" sections -> the title + prose-based sites (H3 sub-headings).
    # (A table-based extraction may already have filled visitor_sites upstream.)
    for s in doc.sections:
        if s.slug.startswith("visitor-sites") or s.title.lower().startswith("visitor sites"):
            doc.metadata.setdefault(
                "visitor_sites_title", re.split(r"\s+[—–-]\s+", s.title, maxsplit=1)[0].strip()
            )
            intro, prose_sites = _extract_visitor_sites_prose(s)
            # Land-Based and Cruise-Only sections keep their own lead text.
            if intro:
                key = (
                    "visitor_sites_intro_cruise"
                    if "cruise" in s.title.lower()
                    else "visitor_sites_intro"
                )
                doc.metadata.setdefault(key, intro)
            if prose_sites:
                doc.metadata.setdefault("visitor_sites", []).extend(prose_sites)

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
