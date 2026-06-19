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
