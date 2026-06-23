"""Render content blocks / Markdown to clean semantic HTML.

ACF WYSIWYG and textarea fields store ordinary HTML (not Gutenberg block
markup), so this produces plain `<p>`, `<ul>`, `<h2>`, `<blockquote>`, `<table>`
and `<figure>` — the kind of HTML the WordPress editor and theme expect.
"""

from __future__ import annotations

import html
import re

from ..ingest.markdown_reader import _parse_body
from ..models import BlockType, ContentBlock, Document

_LINK = re.compile(r"\[([^\]]+)\]\(([^)\s]+)\)")
_BOLD = re.compile(r"\*\*([^*]+)\*\*")
_ITALIC = re.compile(r"(?<![*\w])\*([^*]+)\*(?![*\w])")
_CODE = re.compile(r"`([^`]+)`")


def inline_md(text: str) -> str:
    """Convert inline Markdown (links, bold, italic, code) to safe HTML."""
    out = html.escape(text or "")
    out = _LINK.sub(lambda m: f'<a href="{html.escape(m.group(2))}">{m.group(1)}</a>', out)
    out = _BOLD.sub(r"<strong>\1</strong>", out)
    out = _ITALIC.sub(r"<em>\1</em>", out)
    out = _CODE.sub(r"<code>\1</code>", out)
    return out


def block_to_html(block: ContentBlock) -> str:
    t = block.type
    if t == BlockType.PARAGRAPH:
        return f"<p>{inline_md(block.text)}</p>" if block.text else ""
    if t == BlockType.HEADING:
        level = max(2, min(6, block.level))
        return f"<h{level}>{html.escape(block.text)}</h{level}>"
    if t == BlockType.LIST:
        if not block.items:
            return ""
        tag = "ol" if block.ordered else "ul"
        lis = "".join(f"<li>{inline_md(i)}</li>" for i in block.items)
        return f"<{tag}>{lis}</{tag}>"
    if t == BlockType.QUOTE:
        return f"<blockquote><p>{inline_md(block.text)}</p></blockquote>"
    if t == BlockType.TABLE:
        return _table_html(block.rows)
    if t == BlockType.IMAGE and block.src:
        cap = f"<figcaption>{html.escape(block.text)}</figcaption>" if block.text else ""
        return (
            f'<figure><img src="{html.escape(block.src)}" '
            f'alt="{html.escape(block.alt or "")}"/>{cap}</figure>'
        )
    if t == BlockType.CODE:
        return f"<pre><code>{html.escape(block.text)}</code></pre>"
    if t == BlockType.HTML:
        return block.html
    return ""


def blocks_to_html(blocks: list[ContentBlock], *, include_headings: bool = True) -> str:
    parts = []
    for b in blocks:
        if b.type == BlockType.HEADING and not include_headings:
            continue
        rendered = block_to_html(b)
        if rendered:
            parts.append(rendered)
    return "\n".join(parts)


def md_to_html(text: str) -> str:
    """Parse a Markdown chunk and render it to semantic HTML."""
    if not text.strip():
        return ""
    tmp = Document(title="_placeholder_")  # non-empty so a leading H1 is kept
    _parse_body(tmp, text)
    parts = []
    for section in tmp.sections:
        if section.title:
            level = max(2, section.level)
            parts.append(f"<h{level}>{html.escape(section.title)}</h{level}>")
        parts.append(blocks_to_html(section.blocks))
    return "\n".join(p for p in parts if p)


def _table_html(rows: list[list[str]]) -> str:
    if not rows:
        return ""
    head = rows[0]
    body = rows[1:]
    thead = "<thead><tr>" + "".join(f"<th>{html.escape(c)}</th>" for c in head) + "</tr></thead>"
    trs = "".join(
        "<tr>" + "".join(f"<td>{html.escape(c)}</td>" for c in r) + "</tr>" for r in body
    )
    return f"<table>{thead}<tbody>{trs}</tbody></table>"
