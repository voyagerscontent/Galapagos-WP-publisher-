"""Compose a Document into a structured ContentModel (deterministic rules).

Two paths, chosen automatically:

* **Auto** — a normally-written content doc (headings + prose) is broken into
  UX components by rules: a hero from the title/metadata, an intro, one section
  per ``##`` heading, an FAQ accordion, a stats block from `facts`, and a
  closing CTA. The arrangement targets readability and engagement.
* **Bespoke** — if the doc contains ``:::`` directives, the uploader is
  hand-designing the page; each directive maps to a component so they get full
  control over a one-off layout.
"""

from __future__ import annotations

import re

from ..config import Settings
from ..media.resolver import MediaResolver
from ..models import BlockType, Document, MediaItem, Section
from . import model as C
from .directives import parse_directives
from .model import Component
from .richtext import blocks_to_html, inline_md, md_to_html

_HAS_DIRECTIVES = re.compile(r"(?m)^\s*:::+\s*[A-Za-z]")
_BUTTON = re.compile(r"\[button:\s*([^|\]]+?)\s*(?:\|\s*([^\]]*?))?\s*\]")
# FAQ written as "Q: …" / "A: …" paragraph pairs (a common house style).
_Q_PREFIX = re.compile(r"^Q[:.]\s*", re.IGNORECASE)
_A_PREFIX = re.compile(r"^<p>\s*A[:.]\s*", re.IGNORECASE)


class Composer:
    def __init__(self, settings: Settings, media: MediaResolver):
        self.settings = settings
        self.media = media
        self.media_items: list[MediaItem] = []
        self.warnings: list[str] = []

    def compose(self, doc: Document) -> tuple[list[Component], list[str]]:
        self.media_items = []
        self.warnings = []
        body = doc.raw_body or ""
        if _HAS_DIRECTIVES.search(body):
            components = self._from_directives(parse_directives(body))
        else:
            components = self._auto(doc)
        components = [c for c in components if self._not_empty(c)]
        return components, self.warnings

    # ---- AUTO --------------------------------------------------------- #
    def _auto(self, doc: Document) -> list[Component]:
        meta = doc.metadata
        out: list[Component] = []

        # Hero
        hero_img = None
        query = meta.get("hero_image_query") or meta.get("featured_image_query")
        if query:
            hero_img = self._img(str(query), alt=doc.title, role="featured")
        out.append(
            C.hero(
                heading=doc.title,
                subheading=str(meta.get("tagline") or meta.get("subtitle") or ""),
                eyebrow=str(meta.get("eyebrow") or ""),
                image=hero_img or {},
                ctas=self._meta_ctas(meta),
            )
        )

        for section in doc.sections:
            if section.slug == "_lead":
                inner = blocks_to_html(section.blocks)
                if inner:
                    out.append(C.rich_text(inner))
                continue
            if section.slug == "faq":
                items = self._faq_items(section)
                if items:
                    out.append(C.accordion(items, heading=section.title or "FAQ"))
                continue
            content = blocks_to_html(section.blocks)
            if content or section.title:
                out.append(C.rich_text(content, heading=section.title))

        # Stats from a `facts` map
        facts = meta.get("facts")
        if isinstance(facts, dict) and facts:
            out.append(
                C.stats([{"value": str(v), "label": str(k)} for k, v in facts.items()])
            )

        # Closing CTA from metadata, if present
        if meta.get("booking_heading") or meta.get("footer_cta_primary_label"):
            ctas = self._meta_ctas(meta, prefix="footer_") or self._meta_ctas(meta)
            out.append(
                C.cta(
                    heading=str(meta.get("booking_heading") or "Ready to go?"),
                    content=str(meta.get("booking_blurb") or meta.get("footer_blurb") or ""),
                    ctas=ctas,
                )
            )
        return out

    # ---- BESPOKE (directives) ---------------------------------------- #
    def _from_directives(self, nodes: list[tuple]) -> list[Component]:
        out: list[Component] = []
        for node in nodes:
            if node[0] == "text":
                html = md_to_html(node[1])
                if html:
                    out.append(C.rich_text(html))
                continue
            name, args, inner = node[1], node[2], node[3]
            out.extend(self._directive_components(name, args, inner))
        return out

    def _directive_components(self, name: str, args: str, inner: str) -> list[Component]:
        kwargs = _parse_args(args)
        if name == "hero":
            heading, sub, ctas = self._split_hero(inner)
            img = {}
            if kwargs.get("image"):
                img = self._img(kwargs["image"], alt=heading, role="featured")
            return [C.hero(heading=heading, subheading=sub, image=img, ctas=ctas)]
        if name == "columns":
            cols, widths = self._columns(inner, args)
            return [C.columns(cols, widths=widths)] if cols else []
        if name in ("card", "cards"):
            cols = self._cards(inner) if name == "cards" else [md_to_html(inner)]
            return [C.columns(cols)] if cols else []
        if name == "callout":
            variant = kwargs.get("type") or _first_positional(args) or "note"
            return [C.callout(md_to_html(inner), variant=variant, title=kwargs.get("title", ""))]
        if name in ("accordion", "faq"):
            return [C.accordion(self._accordion_items(inner))]
        if name == "cta":
            heading, content, ctas = self._split_cta(inner)
            return [C.cta(heading=heading, content=content, ctas=ctas)]
        if name in ("group", "section"):
            return self._from_directives(parse_directives(inner))
        if name == "image":
            caption = inner.strip()
            if kwargs.get("src", "").startswith("http"):
                ref = {"media_id": None, "url": kwargs["src"], "alt": kwargs.get("alt", "")}
            else:
                q = kwargs.get("query") or kwargs.get("alt") or _first_positional(args) or caption
                ref = self._img(str(q or "image"), alt=kwargs.get("alt", ""))
            return [C.image(ref, caption=caption, alt=kwargs.get("alt", ""))]
        if name == "buttons":
            return [C.cta(ctas=_extract_buttons(inner))]
        if name in ("html", "raw"):
            return [C.raw_html(inner.strip())]
        if name in ("spacer", "divider", "hr", "column"):
            return [] if name in ("spacer", "divider", "hr") else self._from_directives(
                parse_directives(inner)
            )
        self.warnings.append(f"Unknown directive ':::{name}' — kept as rich text.")
        return [C.rich_text(md_to_html(inner))]

    # ---- helpers ------------------------------------------------------ #
    def _img(self, query: str, *, alt: str = "", role: str = "inline") -> dict:
        item = self.media.resolve(role, str(query), alt=alt or str(query))
        self.media_items.append(item)
        if not item.wp_media_id:
            self.warnings.append(
                f"Image needed for '{query}' — no Media Library match (use --media library "
                "or set it in WordPress)."
            )
        return {
            "media_id": item.wp_media_id,
            "url": item.url,
            "alt": item.alt or alt,
            "query": str(query),
        }

    def _meta_ctas(self, meta: dict, prefix: str = "") -> list[dict]:
        ctas = []
        for n in ("primary", "secondary"):
            label = meta.get(f"{prefix}cta_{n}_label")
            if label:
                ctas.append({"label": str(label), "url": str(meta.get(f"{prefix}cta_{n}_url", "#"))})
        return ctas

    def _faq_items(self, section: Section) -> list[dict]:
        """FAQ items from H3 headings OR 'Q:'/'A:' paragraph pairs."""
        items: list[dict] = []
        question: str | None = None
        answer: list = []

        def flush() -> None:
            if question is not None:
                html = blocks_to_html(answer)
                html = _A_PREFIX.sub("<p>", html)  # drop a leading "A:" marker
                items.append({"question": question, "answer": html})

        for b in section.blocks:
            if b.type == BlockType.HEADING:
                flush()
                question, answer = b.text, []
            elif b.type == BlockType.PARAGRAPH and _Q_PREFIX.match(b.text):
                flush()
                question, answer = _Q_PREFIX.sub("", b.text).strip(), []
            elif b.type == BlockType.PARAGRAPH and b.text.strip().endswith("?"):
                # A bare question paragraph (no "Q:" prefix, no heading style).
                flush()
                question, answer = b.text.strip(), []
            elif question is not None:
                answer.append(b)
        flush()
        return items

    def _accordion_items(self, inner: str) -> list[dict]:
        items, question, buf = [], None, []
        for line in inner.split("\n"):
            if line.strip().startswith("### "):
                if question is not None:
                    items.append({"question": question, "answer": md_to_html("\n".join(buf))})
                question, buf = line.strip()[4:].strip(), []
            else:
                buf.append(line)
        if question is not None:
            items.append({"question": question, "answer": md_to_html("\n".join(buf))})
        return items

    def _columns(self, inner: str, args: str) -> tuple[list[str], list[str]]:
        widths = _ratio_to_widths(_first_positional(args) or "")
        bodies = []
        for node in parse_directives(inner):
            if node[0] == "dir" and node[1] == "column":
                bodies.append(md_to_html(node[3]))
            elif node[0] == "text":
                bodies.append(md_to_html(node[1]))
        if widths and len(widths) != len(bodies):
            widths = []
        return bodies, widths or []

    def _cards(self, inner: str) -> list[str]:
        return [
            md_to_html(node[3])
            for node in parse_directives(inner)
            if node[0] == "dir" and node[1] == "card"
        ]

    def _split_hero(self, inner: str) -> tuple[str, str, list[dict]]:
        heading, subs, ctas = "", [], []
        for line in inner.split("\n"):
            s = line.strip()
            if not s:
                continue
            if s.startswith("#"):
                if not heading:
                    heading = s.lstrip("# ").strip()
            elif _BUTTON.search(s):
                ctas.extend(_extract_buttons(s))
            else:
                subs.append(inline_md(s))
        return heading, " ".join(subs), ctas

    def _split_cta(self, inner: str) -> tuple[str, str, list[dict]]:
        heading, content, ctas = "", [], []
        for line in inner.split("\n"):
            s = line.strip()
            if not s:
                continue
            if s.startswith("#") and not heading:
                heading = s.lstrip("# ").strip()
            elif _BUTTON.search(s):
                ctas.extend(_extract_buttons(s))
            else:
                content.append(f"<p>{inline_md(s)}</p>")
        return heading, "".join(content), ctas

    @staticmethod
    def _not_empty(c: Component) -> bool:
        d = c.data
        if c.type == "rich_text":
            return bool(d.get("content") or d.get("heading"))
        if c.type in ("accordion", "stats", "gallery"):
            return bool(d.get("items") or d.get("images"))
        if c.type == "columns":
            return bool(d.get("columns"))
        if c.type == "cta":
            return bool(d.get("heading") or d.get("content") or d.get("ctas"))
        return True


# --- module helpers -------------------------------------------------------- #
def _extract_buttons(text: str) -> list[dict]:
    return [
        {"label": m.group(1).strip(), "url": (m.group(2) or "#").strip()}
        for m in _BUTTON.finditer(text)
    ]


def _parse_args(argstr: str) -> dict[str, str]:
    out = {}
    for m in re.finditer(r'(\w+)=("[^"]*"|\'[^\']*\'|\S+)', argstr or ""):
        out[m.group(1).lower()] = m.group(2).strip("\"'")
    return out


def _first_positional(argstr: str) -> str | None:
    for tok in (argstr or "").split():
        if "=" not in tok:
            return tok
    return None


def _ratio_to_widths(token: str) -> list[str]:
    if "/" not in token:
        return []
    try:
        parts = [float(p) for p in token.split("/")]
    except ValueError:
        return []
    total = sum(parts) or 1.0
    return [f"{round(p / total * 100, 2):g}%" for p in parts]
