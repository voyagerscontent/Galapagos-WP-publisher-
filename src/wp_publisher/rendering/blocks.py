"""Builders for WordPress Gutenberg block markup.

Each function returns a string of valid block markup (the HTML comment
delimiters Gutenberg uses, e.g. ``<!-- wp:paragraph -->``). Producing native
blocks — rather than a raw "Classic" HTML blob — is what lets the content
respect the active theme and remain fully editable in the block editor.
"""

from __future__ import annotations

import html
import json


def _attrs(attrs: dict | None) -> str:
    if not attrs:
        return ""
    return " " + json.dumps(attrs, separators=(",", ":"), ensure_ascii=False)


def paragraph_block(text_html: str) -> str:
    return f"<!-- wp:paragraph -->\n<p>{text_html}</p>\n<!-- /wp:paragraph -->"


def heading_block(text: str, level: int = 2) -> str:
    level = max(2, min(6, level))
    attrs = {"level": level} if level != 2 else None
    return (
        f"<!-- wp:heading{_attrs(attrs)} -->\n"
        f"<h{level}>{html.escape(text)}</h{level}>\n"
        f"<!-- /wp:heading -->"
    )


def list_block(items: list[str], ordered: bool = False) -> str:
    tag = "ol" if ordered else "ul"
    attrs = {"ordered": True} if ordered else None
    inner = "".join(
        f"<!-- wp:list-item -->\n<li>{html.escape(i)}</li>\n<!-- /wp:list-item -->\n"
        for i in items
    )
    return (
        f"<!-- wp:list{_attrs(attrs)} -->\n"
        f"<{tag}>\n{inner}</{tag}>\n"
        f"<!-- /wp:list -->"
    )


def quote_block(text: str, citation: str = "") -> str:
    cite = f"<cite>{html.escape(citation)}</cite>" if citation else ""
    return (
        "<!-- wp:quote -->\n"
        f"<blockquote class=\"wp-block-quote\"><p>{html.escape(text)}</p>{cite}</blockquote>\n"
        "<!-- /wp:quote -->"
    )


def image_block(
    url: str,
    alt: str = "",
    caption: str = "",
    media_id: int | None = None,
    size_slug: str = "large",
) -> str:
    attrs: dict = {"sizeSlug": size_slug}
    if media_id:
        attrs["id"] = media_id
    cap = (
        f'<figcaption class="wp-element-caption">{html.escape(caption)}</figcaption>'
        if caption
        else ""
    )
    class_attr = f' class="wp-image-{media_id}"' if media_id else ""
    img_tag = f'<img src="{html.escape(url)}" alt="{html.escape(alt)}"{class_attr}/>'
    return (
        f"<!-- wp:image{_attrs(attrs)} -->\n"
        f'<figure class="wp-block-image size-{size_slug}">{img_tag}{cap}</figure>\n'
        f"<!-- /wp:image -->"
    )


def placeholder_block(label: str, hint: str = "") -> str:
    """A highly visible block prompting a human to drop in media/content."""
    body = html.escape(label)
    if hint:
        body += f"<br><small>{html.escape(hint)}</small>"
    return (
        "<!-- wp:group {\"className\":\"gwp-placeholder\",\"layout\":{\"type\":\"constrained\"}} -->\n"
        "<div class=\"wp-block-group gwp-placeholder\" "
        "style=\"border:2px dashed #c0392b;padding:1.25rem;text-align:center;"
        "background:#fdf2f0;border-radius:8px\">\n"
        f"<!-- wp:paragraph -->\n<p><strong>{body}</strong></p>\n<!-- /wp:paragraph -->\n"
        "</div>\n<!-- /wp:group -->"
    )


def table_block(rows: list[list[str]], has_header: bool = True) -> str:
    if not rows:
        return ""
    parts = ["<figure class=\"wp-block-table\"><table>"]
    body_rows = rows
    if has_header:
        head = rows[0]
        parts.append("<thead><tr>")
        parts.extend(f"<th>{html.escape(c)}</th>" for c in head)
        parts.append("</tr></thead>")
        body_rows = rows[1:]
    parts.append("<tbody>")
    for row in body_rows:
        parts.append("<tr>")
        parts.extend(f"<td>{html.escape(c)}</td>" for c in row)
        parts.append("</tr>")
    parts.append("</tbody></table></figure>")
    return "<!-- wp:table -->\n" + "".join(parts) + "\n<!-- /wp:table -->"


def faq_block(pairs: list[tuple[str, str]]) -> str:
    """Render Q&A pairs as heading + paragraph blocks (schema added separately)."""
    out: list[str] = []
    for question, answer in pairs:
        out.append(heading_block(question, level=3))
        out.append(paragraph_block(html.escape(answer)))
    return "\n\n".join(out)


def spacer_block(height_px: int = 24) -> str:
    return (
        f"<!-- wp:spacer {{\"height\":\"{height_px}px\"}} -->\n"
        f"<div style=\"height:{height_px}px\" aria-hidden=\"true\" "
        f"class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->"
    )


# --------------------------------------------------------------------------- #
# Structural / layout blocks (used by richer templates).
# --------------------------------------------------------------------------- #

def group_block(inner_html: str, *, class_name: str = "", style: str = "") -> str:
    """Wrap inner block markup in a constrained wp:group."""
    attrs: dict = {"layout": {"type": "constrained"}}
    if class_name:
        attrs["className"] = class_name
    cls = f"wp-block-group {class_name}".strip()
    style_attr = f' style="{style}"' if style else ""
    return (
        f"<!-- wp:group{_attrs(attrs)} -->\n"
        f'<div class="{cls}"{style_attr}>\n{inner_html}\n</div>\n'
        f"<!-- /wp:group -->"
    )


def columns_block(columns: list[str], *, class_name: str = "", style: str = "") -> str:
    """Build a wp:columns row from a list of inner-HTML column bodies."""
    attrs: dict = {}
    if class_name:
        attrs["className"] = class_name
    cls = f"wp-block-columns {class_name}".strip()
    style_attr = f' style="{style}"' if style else ""
    inner = []
    for body in columns:
        inner.append(
            "<!-- wp:column -->\n"
            f'<div class="wp-block-column">\n{body}\n</div>\n'
            "<!-- /wp:column -->"
        )
    return (
        f"<!-- wp:columns{_attrs(attrs)} -->\n"
        f'<div class="{cls}"{style_attr}>\n' + "\n".join(inner) + "\n</div>\n"
        "<!-- /wp:columns -->"
    )


def buttons_block(buttons: list[tuple[str, str]], *, class_name: str = "") -> str:
    """Build a wp:buttons group. Each button is (label, url)."""
    if not buttons:
        return ""
    attrs: dict = {}
    if class_name:
        attrs["className"] = class_name
    cls = f"wp-block-buttons {class_name}".strip()
    inner = []
    for label, url in buttons:
        href = html.escape(url or "#")
        inner.append(
            "<!-- wp:button -->\n"
            '<div class="wp-block-button">'
            f'<a class="wp-block-button__link wp-element-button" href="{href}">'
            f"{html.escape(label)}</a></div>\n"
            "<!-- /wp:button -->"
        )
    return (
        f"<!-- wp:buttons{_attrs(attrs)} -->\n"
        f'<div class="{cls}">\n' + "\n".join(inner) + "\n</div>\n"
        "<!-- /wp:buttons -->"
    )


def details_block(summary: str, inner_html: str) -> str:
    """Native accordion item (wp:details, WordPress 6.4+)."""
    return (
        "<!-- wp:details -->\n"
        f'<details class="wp-block-details"><summary>{html.escape(summary)}</summary>\n'
        f"{inner_html}\n</details>\n"
        "<!-- /wp:details -->"
    )


def heading_anchor_block(text: str, anchor: str, level: int = 2) -> str:
    """Heading with an id anchor (so a table of contents can link to it)."""
    level = max(2, min(6, level))
    attrs: dict = {"anchor": anchor}
    if level != 2:
        attrs["level"] = level
    return (
        f"<!-- wp:heading{_attrs(attrs)} -->\n"
        f'<h{level} id="{html.escape(anchor)}">{html.escape(text)}</h{level}>\n'
        f"<!-- /wp:heading -->"
    )


def raw_html_block(inner_html: str) -> str:
    return f"<!-- wp:html -->\n{inner_html}\n<!-- /wp:html -->"
