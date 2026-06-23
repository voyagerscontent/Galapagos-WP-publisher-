"""Read Markdown (with optional YAML frontmatter) into a Document."""

from __future__ import annotations

import re
from pathlib import Path

import frontmatter

from ..models import BlockType, ContentBlock, Document, Section
from ..utils import section_slug

_HEADING_RE = re.compile(r"^(#{1,6})\s+(.*)$")
_UL_RE = re.compile(r"^\s*[-*+]\s+(.*)$")
_OL_RE = re.compile(r"^\s*\d+[.)]\s+(.*)$")
_IMG_RE = re.compile(r"!\[(?P<alt>[^\]]*)\]\((?P<src>[^)\s]+)(?:\s+\"(?P<title>[^\"]*)\")?\)")
_QUOTE_RE = re.compile(r"^\s*>\s?(.*)$")


def read_markdown(path: str | Path) -> Document:
    path = Path(path)
    post = frontmatter.loads(path.read_text(encoding="utf-8"))
    metadata = dict(post.metadata or {})
    body = post.content

    doc = Document(source_name=path.name, source_kind="markdown", metadata=metadata)
    doc.raw_body = body
    _parse_body(doc, body)

    # Title precedence: frontmatter > first H1 > filename
    if metadata.get("title"):
        doc.title = str(metadata["title"])
    elif not doc.title:
        doc.title = path.stem.replace("-", " ").replace("_", " ").title()
    return doc


def _parse_body(doc: Document, body: str) -> None:
    lines = body.splitlines()
    # Implicit lead-in section before the first heading.
    current = Section(title="", level=1, slug="_lead")
    para: list[str] = []
    list_items: list[str] = []
    list_ordered = False
    quote_lines: list[str] = []
    in_code = False
    code_buf: list[str] = []

    def flush_para() -> None:
        nonlocal para
        if para:
            text = " ".join(para).strip()
            _emit_text_block(current, text)
            para = []

    def flush_list() -> None:
        nonlocal list_items, list_ordered
        if list_items:
            current.blocks.append(
                ContentBlock(type=BlockType.LIST, ordered=list_ordered, items=list_items[:])
            )
            list_items = []
            list_ordered = False

    def flush_quote() -> None:
        nonlocal quote_lines
        if quote_lines:
            current.blocks.append(
                ContentBlock(type=BlockType.QUOTE, text=" ".join(quote_lines).strip())
            )
            quote_lines = []

    def flush_all() -> None:
        flush_para()
        flush_list()
        flush_quote()

    for line in lines:
        if line.strip().startswith("```"):
            if in_code:
                current.blocks.append(
                    ContentBlock(type=BlockType.CODE, text="\n".join(code_buf))
                )
                code_buf = []
            else:
                flush_all()
            in_code = not in_code
            continue
        if in_code:
            code_buf.append(line)
            continue

        heading = _HEADING_RE.match(line)
        if heading:
            flush_all()
            level = len(heading.group(1))
            title = heading.group(2).strip()
            if level == 1 and not doc.title:
                doc.title = title
                continue
            if level >= 3:
                # Subheadings stay nested as heading blocks inside the current
                # section (e.g. FAQ questions under an "## FAQ" section).
                current.blocks.append(
                    ContentBlock(type=BlockType.HEADING, text=title, level=level)
                )
                continue
            if current.blocks or current.title:
                doc.sections.append(current)
            current = Section(title=title, level=level, slug=section_slug(title))
            continue

        img = _IMG_RE.match(line.strip())
        if img:
            flush_all()
            current.blocks.append(
                ContentBlock(
                    type=BlockType.IMAGE,
                    src=img.group("src"),
                    alt=img.group("alt") or "",
                    text=img.group("title") or img.group("alt") or "",
                )
            )
            continue

        ul = _UL_RE.match(line)
        ol = _OL_RE.match(line)
        if ul or ol:
            flush_para()
            flush_quote()
            list_ordered = bool(ol)
            list_items.append((ol or ul).group(1).strip())
            continue
        else:
            flush_list()

        quote = _QUOTE_RE.match(line)
        if quote:
            flush_para()
            quote_lines.append(quote.group(1).strip())
            continue
        else:
            flush_quote()

        if not line.strip():
            flush_para()
        else:
            para.append(line.strip())

    flush_all()
    if current.blocks or current.title:
        doc.sections.append(current)


def _emit_text_block(section: Section, text: str) -> None:
    """Split inline images out of a paragraph; keep prose as paragraph."""
    if not text:
        return
    section.blocks.append(ContentBlock(type=BlockType.PARAGRAPH, text=text))
