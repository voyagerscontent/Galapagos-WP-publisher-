"""The intermediate content model: typed components, ACF-agnostic.

A `Component` is a unit of page content (a hero, a rich-text section, an FAQ
accordion, an image, …). Composition produces a `ContentModel` (an ordered list
of components); the ACF mapper then turns each component into a flexible-content
layout row using the site's field names. Keeping this layer ACF-agnostic means
the same composed content can target any ACF schema by editing config, not code.
"""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field


class Component(BaseModel):
    """One page component. `type` selects the ACF layout; `data` carries values."""

    type: str
    data: dict[str, Any] = Field(default_factory=dict)


ContentModel = list[Component]


# --- Builders (keep component shapes consistent across composers) ---------- #

def hero(
    *,
    heading: str,
    subheading: str = "",
    eyebrow: str = "",
    image: dict | None = None,
    ctas: list[dict] | None = None,
) -> Component:
    return Component(
        type="hero",
        data={
            "heading": heading,
            "subheading": subheading,
            "eyebrow": eyebrow,
            "image": image or {},
            "ctas": ctas or [],
        },
    )


def rich_text(html: str, *, heading: str = "") -> Component:
    return Component(type="rich_text", data={"heading": heading, "content": html})


def bullets(items: list[str], *, ordered: bool = False, heading: str = "") -> Component:
    return Component(
        type="bullets", data={"items": items, "ordered": ordered, "heading": heading}
    )


def image(image_ref: dict, *, caption: str = "", alt: str = "") -> Component:
    return Component(type="image", data={"image": image_ref, "caption": caption, "alt": alt})


def gallery(images: list[dict], *, heading: str = "") -> Component:
    return Component(type="gallery", data={"images": images, "heading": heading})


def callout(content_html: str, *, variant: str = "note", title: str = "") -> Component:
    return Component(
        type="callout", data={"variant": variant, "title": title, "content": content_html}
    )


def accordion(items: list[dict], *, heading: str = "") -> Component:
    """items: [{question, answer (html)}]."""
    return Component(type="accordion", data={"heading": heading, "items": items})


def stats(items: list[dict], *, heading: str = "") -> Component:
    """items: [{value, label}]."""
    return Component(type="stats", data={"heading": heading, "items": items})


def cta(*, heading: str = "", content: str = "", ctas: list[dict] | None = None) -> Component:
    return Component(type="cta", data={"heading": heading, "content": content, "ctas": ctas or []})


def quote(text: str, *, citation: str = "") -> Component:
    return Component(type="quote", data={"text": text, "citation": citation})


def columns(column_html: list[str], *, widths: list[str] | None = None) -> Component:
    return Component(type="columns", data={"columns": column_html, "widths": widths or []})


def raw_html(html: str) -> Component:
    return Component(type="html", data={"content": html})
