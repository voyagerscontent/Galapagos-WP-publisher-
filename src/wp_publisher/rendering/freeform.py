"""Directive-driven page builder ("freeform" template).

Instead of a fixed template, the content document carries its own layout
instructions and this renderer turns them into native Gutenberg blocks. Authors
write normal Markdown for prose and wrap structural pieces in `:::` directive
fences:

    ::: hero image="Galapagos sunset"
    # Welcome to the Islands
    A short intro line.
    [button:Plan a Trip|/contact]
    :::

    ::: columns 60/40
    ::: column
    Left content...
    :::
    ::: column
    Right content...
    :::
    :::

Supported directives: hero, columns/column, card/cards, callout, accordion,
buttons, image, cta, group (alias section), spacer, divider, html. Anything
outside a directive is rendered as native blocks from its Markdown.

See docs/AUTHORING_GUIDE.md for the full instruction set.
"""

from __future__ import annotations

import html
import re

from ..config import Settings
from ..ingest.markdown_reader import _parse_body
from ..media.resolver import MediaResolver
from ..models import BlockType, ContentBlock, Document, MediaItem
from ..rendering.template import PageTemplate
from . import blocks as B

_OPEN = re.compile(r"^:::+\s*([A-Za-z][\w-]*)\s*(.*)$")
_CLOSE = re.compile(r"^:::+\s*$")
_ARG = re.compile(r"""(\w+)=("[^"]*"|'[^']*'|\S+)|(\S+)""")
_BUTTON = re.compile(r"\[button:\s*([^|\]]+?)\s*(?:\|\s*([^\]]*?))?\s*\]")
_BUTTON_LINE = re.compile(r"^(?:\[button:[^\]]+\]\s*)+$")
_LINK = re.compile(r"\[([^\]]+)\]\(([^)\s]+)\)")
_BOLD = re.compile(r"\*\*([^*]+)\*\*")
_ITALIC = re.compile(r"(?<![*\w])\*([^*]+)\*(?![*\w])")
_CODE = re.compile(r"`([^`]+)`")

_HERO = (
    "background:linear-gradient(135deg,#0f3d2e,#1f7a4d);color:#fff;"
    "padding:3rem 2rem;border-radius:14px;text-align:center"
)
_CARD = (
    "border:1px solid #e3e6e3;border-radius:12px;padding:1.25rem;"
    "background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06);height:100%"
)
_CTA = "background:#0f3d2e;color:#fff;padding:2.5rem 2rem;border-radius:14px;text-align:center"


def parse_directives(text: str) -> list[tuple]:
    """Split text into a flat list of ('text', body) and ('dir', name, args, inner)."""
    lines = text.split("\n")
    nodes: list[tuple] = []
    i, n = 0, len(lines)
    while i < n:
        stripped = lines[i].strip()
        m = _OPEN.match(stripped)
        if m:
            name, args = m.group(1), m.group(2).strip()
            depth, j, inner = 1, i + 1, []
            while j < n:
                s = lines[j].strip()
                if _OPEN.match(s):
                    depth += 1
                    inner.append(lines[j])
                elif _CLOSE.match(s):
                    depth -= 1
                    if depth == 0:
                        break
                    inner.append(lines[j])
                else:
                    inner.append(lines[j])
                j += 1
            nodes.append(("dir", name.lower(), args, "\n".join(inner)))
            i = j + 1
        elif _CLOSE.match(stripped):
            i += 1  # stray closing fence
        else:
            buf = []
            while i < n:
                s = lines[i].strip()
                if _OPEN.match(s) or _CLOSE.match(s):
                    break
                buf.append(lines[i])
                i += 1
            if "\n".join(buf).strip():
                nodes.append(("text", "\n".join(buf)))
    return nodes


def _parse_args(argstr: str) -> tuple[list[str], dict[str, str]]:
    positional: list[str] = []
    kwargs: dict[str, str] = {}
    for m in _ARG.finditer(argstr or ""):
        if m.group(1):
            kwargs[m.group(1).lower()] = m.group(2).strip("\"'")
        elif m.group(3):
            positional.append(m.group(3))
    return positional, kwargs


def _ratio_to_widths(token: str) -> list[str] | None:
    if "/" not in token:
        return None
    try:
        parts = [float(p) for p in token.split("/")]
    except ValueError:
        return None
    total = sum(parts) or 1.0
    return [f"{round(p / total * 100, 2):g}%" for p in parts]


class FreeformRenderer:
    """Builds a page from the document's own layout directives."""

    def __init__(self, settings: Settings, media: MediaResolver):
        self.settings = settings
        self.media = media
        self.media_items: list[MediaItem] = []
        self.warnings: list[str] = []

    def render(
        self, doc: Document, template: PageTemplate
    ) -> tuple[str, list[MediaItem], MediaItem | None, list[str]]:
        self.media_items = []
        self.warnings = []
        body = doc.raw_body or ""
        if not body.strip():
            self.warnings.append("No content body found to build the page from.")

        featured: MediaItem | None = None
        query = doc.metadata.get("featured_image_query") or doc.metadata.get("hero_image_query")
        if query:
            featured = self.media.resolve("featured", str(query), alt=doc.title)

        content = self._render_nodes(parse_directives(body))
        return content, self.media_items, featured, self.warnings

    # ------------------------------------------------------------------ #
    def _render_nodes(self, nodes: list[tuple]) -> str:
        out = []
        for node in nodes:
            if node[0] == "text":
                out.append(self._md_to_blocks(node[1]))
            else:
                out.append(self._render_directive(node[1], node[2], node[3]))
        return "\n\n".join(p for p in out if p)

    def _render_directive(self, name: str, args: str, inner: str) -> str:
        positional, kwargs = _parse_args(args)
        handler = getattr(self, f"_d_{name}", None)
        if handler is None:
            # Aliases.
            alias = {"section": "_d_group", "raw": "_d_html", "divider": "_d_separator",
                     "hr": "_d_separator", "button": "_d_buttons", "faq": "_d_accordion"}.get(name)
            handler = getattr(self, alias, None) if alias else None
        if handler is None:
            self.warnings.append(f"Unknown directive ':::{name}' — rendered its contents as-is.")
            return self._render_nodes(parse_directives(inner))
        return handler(positional, kwargs, inner)

    # ---- directive handlers ------------------------------------------ #
    def _d_hero(self, positional, kwargs, inner) -> str:
        img_q = kwargs.get("image") or kwargs.get("img")
        style = _HERO
        if img_q:
            item = self.media.resolve("featured", str(img_q), alt=img_q)
            self.media_items.append(item)
            if item.source == "library" and item.url:
                style = (
                    f"background:linear-gradient(rgba(15,61,46,.55),rgba(31,122,77,.55)),"
                    f"url('{html.escape(item.url)}');background-size:cover;"
                    f"background-position:center;color:#fff;padding:4rem 2rem;"
                    f"border-radius:14px;text-align:center"
                )
        body = self._render_nodes(parse_directives(inner))
        return B.group_block(body, class_name="gwp-hero", style=style)

    def _d_columns(self, positional, kwargs, inner) -> str:
        widths = None
        for tok in positional:
            widths = _ratio_to_widths(tok) or widths
        child = parse_directives(inner)
        bodies = []
        for node in child:
            if node[0] == "dir" and node[1] == "column":
                bodies.append(self._render_nodes(parse_directives(node[3])))
            elif node[0] == "text":
                bodies.append(self._md_to_blocks(node[1]))
            else:
                bodies.append(self._render_directive(node[1], node[2], node[3]))
        if not bodies:
            return ""
        if widths and len(widths) != len(bodies):
            widths = None
        return B.columns_block(bodies, class_name="gwp-columns", widths=widths)

    def _d_column(self, positional, kwargs, inner) -> str:
        return self._render_nodes(parse_directives(inner))

    def _d_card(self, positional, kwargs, inner) -> str:
        return B.group_block(
            self._render_nodes(parse_directives(inner)), class_name="gwp-card", style=_CARD
        )

    def _d_cards(self, positional, kwargs, inner) -> str:
        cols = _to_int(kwargs.get("cols")) or 3
        child = parse_directives(inner)
        cards = [
            B.group_block(
                self._render_nodes(parse_directives(node[3])), class_name="gwp-card", style=_CARD
            )
            for node in child
            if node[0] == "dir" and node[1] == "card"
        ]
        if not cards:
            return ""
        rows = [
            B.columns_block(cards[i : i + cols], class_name="gwp-cards")
            for i in range(0, len(cards), cols)
        ]
        return "\n\n".join(rows)

    def _d_callout(self, positional, kwargs, inner) -> str:
        variant = (positional[0] if positional else kwargs.get("type", "note")).lower()
        title = kwargs.get("title", "")
        body = self._render_nodes(parse_directives(inner))
        return B.callout_block(body, variant=variant, title=title)

    def _d_accordion(self, positional, kwargs, inner) -> str:
        items = []
        question = None
        buf: list[str] = []
        for line in inner.split("\n"):
            if line.strip().startswith("### "):
                if question is not None:
                    items.append(B.details_block(question, self._md_to_blocks("\n".join(buf))))
                question = line.strip()[4:].strip()
                buf = []
            else:
                buf.append(line)
        if question is not None:
            items.append(B.details_block(question, self._md_to_blocks("\n".join(buf))))
        if not items:
            self.warnings.append("':::accordion' had no '### question' items.")
        return "\n\n".join(items)

    def _d_cta(self, positional, kwargs, inner) -> str:
        return B.group_block(
            self._render_nodes(parse_directives(inner)), class_name="gwp-cta", style=_CTA
        )

    def _d_group(self, positional, kwargs, inner) -> str:
        parts = []
        if kwargs.get("bg"):
            parts.append(f"background:{kwargs['bg']}")
        if kwargs.get("color"):
            parts.append(f"color:{kwargs['color']}")
        if kwargs.get("align") in ("center", "left", "right"):
            parts.append(f"text-align:{kwargs['align']}")
        if "bg" in kwargs or "color" in kwargs:
            parts.append("padding:2rem;border-radius:12px")
        style = ";".join(parts)
        cls = "gwp-section " + kwargs.get("class", "")
        return B.group_block(
            self._render_nodes(parse_directives(inner)), class_name=cls.strip(), style=style
        )

    def _d_image(self, positional, kwargs, inner) -> str:
        src = kwargs.get("src")
        caption = inner.strip()
        if src and src.startswith("http"):
            return B.image_block(src, alt=kwargs.get("alt", ""), caption=caption)
        query = kwargs.get("query") or kwargs.get("alt") or (positional[0] if positional else caption)
        return self._image_from_query(str(query or "image"), alt=kwargs.get("alt", ""), caption=caption)

    def _d_buttons(self, positional, kwargs, inner) -> str:
        return self._buttons_from_text(inner)

    def _d_spacer(self, positional, kwargs, inner) -> str:
        height = _to_int(positional[0] if positional else kwargs.get("height")) or 24
        return B.spacer_block(height)

    def _d_separator(self, positional, kwargs, inner) -> str:
        return B.separator_block()

    def _d_html(self, positional, kwargs, inner) -> str:
        return B.raw_html_block(inner.strip())

    # ---- Markdown -> native blocks ----------------------------------- #
    def _md_to_blocks(self, text: str) -> str:
        if not text.strip():
            return ""
        tmp = Document(title="_placeholder_")  # non-empty so a leading H1 is kept
        _parse_body(tmp, text)
        out = []
        for section in tmp.sections:
            if section.title:
                out.append(B.heading_block(section.title, level=max(2, section.level)))
            for block in section.blocks:
                rendered = self._render_content_block(block)
                if rendered:
                    out.append(rendered)
        return "\n\n".join(out)

    def _render_content_block(self, block: ContentBlock) -> str:
        t = block.type
        if t == BlockType.PARAGRAPH:
            if not block.text:
                return ""
            if _BUTTON_LINE.match(block.text.strip()):
                return self._buttons_from_text(block.text)
            return B.paragraph_block(self._inline(block.text))
        if t == BlockType.HEADING:
            return B.heading_block(block.text, level=max(2, block.level))
        if t == BlockType.LIST:
            return self._list_inline(block.items, block.ordered)
        if t == BlockType.QUOTE:
            return B.quote_block(block.text)
        if t == BlockType.TABLE:
            return B.table_block(block.rows)
        if t == BlockType.IMAGE:
            if block.src and block.src.startswith("http"):
                return B.image_block(block.src, alt=block.alt or "", caption=block.text or "")
            return self._image_from_query(block.alt or block.text or "image", alt=block.alt or "")
        if t == BlockType.CODE:
            return (
                '<!-- wp:code -->\n<pre class="wp-block-code"><code>'
                f"{html.escape(block.text)}</code></pre>\n<!-- /wp:code -->"
            )
        if t == BlockType.HTML:
            return B.raw_html_block(block.html)
        return ""

    # ---- helpers ------------------------------------------------------ #
    def _image_from_query(self, query: str, *, alt: str = "", caption: str = "") -> str:
        item = self.media.resolve("inline", query, alt=alt or query, caption=caption)
        self.media_items.append(item)
        if item.source == "library" and item.url:
            return B.image_block(item.url, alt=item.alt, caption=caption, media_id=item.wp_media_id)
        return B.placeholder_block("📷 IMAGE NEEDED", hint=caption or query)

    def _buttons_from_text(self, text: str) -> str:
        buttons: list[tuple[str, str]] = []
        for m in _BUTTON.finditer(text):
            label = m.group(1).strip()
            url = (m.group(2) or "#").strip()
            buttons.append((label, url))
        if not buttons:
            # Allow "Label | URL" lines as a fallback.
            for line in text.split("\n"):
                if "|" in line:
                    label, url = line.split("|", 1)
                    buttons.append((label.strip(), url.strip()))
        return B.buttons_block(buttons) if buttons else ""

    def _list_inline(self, items: list[str], ordered: bool) -> str:
        if not items:
            return ""
        tag = "ol" if ordered else "ul"
        attrs = ' {"ordered":true}' if ordered else ""
        inner = "".join(
            f"<!-- wp:list-item -->\n<li>{self._inline(i)}</li>\n<!-- /wp:list-item -->\n"
            for i in items
        )
        return f"<!-- wp:list{attrs} -->\n<{tag}>\n{inner}</{tag}>\n<!-- /wp:list -->"

    def _inline(self, text: str) -> str:
        out = html.escape(text)
        out = _LINK.sub(lambda m: f'<a href="{html.escape(m.group(2))}">{m.group(1)}</a>', out)
        out = _BOLD.sub(r"<strong>\1</strong>", out)
        out = _ITALIC.sub(r"<em>\1</em>", out)
        out = _CODE.sub(r"<code>\1</code>", out)
        return out


def _to_int(value) -> int | None:
    if value is None:
        return None
    digits = "".join(ch for ch in str(value) if ch.isdigit())
    return int(digits) if digits else None
