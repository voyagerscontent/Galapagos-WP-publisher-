"""The render engine: Document + Template (+ media) -> block markup.

It walks the template's declared layout in order. For each slot it pulls the
matching document section (by canonical slug), renders its blocks in the style
the slot requests, and inserts media. Sections present in the document but not
named in the layout are appended at the end so no authored content is silently
dropped. Required-but-missing sections produce warnings, not failures.
"""

from __future__ import annotations

import html

from ..config import Settings
from ..media.resolver import MediaResolver
from ..models import BlockType, ContentBlock, Document, MediaItem, Section
from . import blocks as B
from .template import PageTemplate, SlotSpec


class RenderEngine:
    def __init__(self, settings: Settings, media: MediaResolver):
        self.settings = settings
        self.media = media

    def render(
        self, doc: Document, template: PageTemplate
    ) -> tuple[str, list[MediaItem], MediaItem | None, list[str]]:
        """Return (content_html, media_items, featured_media, warnings)."""
        parts: list[str] = []
        media_items: list[MediaItem] = []
        featured: MediaItem | None = None
        warnings: list[str] = []
        used_slugs: set[str] = set()

        for spec in template.layout:
            if spec.kind == "media":
                item = self._render_media(spec, doc, parts)
                if item is None:
                    continue
                if spec.role == "featured":
                    featured = item
                    # Featured image is set via REST, not embedded in content.
                else:
                    media_items.append(item)
                continue

            if spec.kind == "spacer":
                parts.append(B.spacer_block())
                continue

            # section-like slot
            section = doc.find_section(spec.slot)
            if section is None:
                if spec.required:
                    warnings.append(
                        f"Required section '{spec.slot}' is missing from the document."
                    )
                continue
            used_slugs.add(section.slug)
            parts.append(self._render_section(section, spec))

        # Append any unmapped sections (preserve authored content).
        for section in doc.sections:
            if section.slug in used_slugs or section.slug in ("_lead", "_lead".lower()):
                continue
            if not section.blocks:
                continue
            spec = SlotSpec(slot=section.slug, kind="section")
            parts.append(self._render_section(section, spec))

        content = "\n\n".join(p for p in parts if p)
        return content, media_items, featured, warnings

    # ------------------------------------------------------------------ #
    def _render_section(self, section: Section, spec: SlotSpec) -> str:
        out: list[str] = []
        heading_text = spec.heading or section.title
        if spec.show_heading and heading_text:
            out.append(B.heading_block(heading_text, level=max(2, section.level)))

        # "list" style coalesces a section's paragraphs into bullet points.
        if spec.style == "list":
            items = self._coerce_list(section)
            if items:
                out.append(B.list_block(items))
                return "\n\n".join(out)

        for block in section.blocks:
            rendered = self._render_block(block)
            if rendered:
                out.append(rendered)
        return "\n\n".join(out)

    def _render_block(self, block: ContentBlock) -> str:
        if block.type == BlockType.PARAGRAPH:
            return B.paragraph_block(html.escape(block.text)) if block.text else ""
        if block.type == BlockType.HEADING:
            return B.heading_block(block.text, level=block.level)
        if block.type == BlockType.LIST:
            return B.list_block(block.items, ordered=block.ordered) if block.items else ""
        if block.type == BlockType.QUOTE:
            return B.quote_block(block.text)
        if block.type == BlockType.TABLE:
            return B.table_block(block.rows)
        if block.type == BlockType.IMAGE:
            # Inline image referenced in the doc: resolve via library/placeholder.
            query = block.alt or block.text or "image"
            if block.src and block.src.startswith("http"):
                return B.image_block(block.src, alt=block.alt or "", caption=block.text or "")
            item = self.media.resolve("inline", query, alt=block.alt or "", caption=block.text or "")
            return self._media_to_block(item)
        if block.type == BlockType.CODE:
            return (
                "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>"
                f"{html.escape(block.text)}</code></pre>\n<!-- /wp:code -->"
            )
        if block.type == BlockType.HTML:
            return f"<!-- wp:html -->\n{block.html}\n<!-- /wp:html -->"
        return ""

    def _render_media(self, spec: SlotSpec, doc: Document, parts: list[str]) -> MediaItem | None:
        query = (
            str(doc.metadata.get("featured_image_query", ""))
            or str(doc.metadata.get("destination", ""))
            or doc.title
        )
        item = self.media.resolve(
            spec.role,
            query,
            alt=doc.metadata.get("featured_image_alt", "") or doc.title,
        )
        if spec.role != "featured":
            parts.append(self._media_to_block(item, hint=spec.hint))
        elif item.source == "placeholder":
            # No featured image available — leave a visible note at the top.
            parts.insert(0, B.placeholder_block(
                self.settings.media.get("placeholder_label", "📷 IMAGE NEEDED")
                + " — Featured image",
                hint=spec.hint or f"Suggested: {query}",
            ))
        return item

    def _media_to_block(self, item: MediaItem, hint: str = "") -> str:
        if item.source == "library" and item.url:
            return B.image_block(
                item.url, alt=item.alt, caption=item.caption, media_id=item.wp_media_id
            )
        label = self.settings.media.get("placeholder_label", "📷 IMAGE NEEDED")
        return B.placeholder_block(label, hint=hint or item.caption or item.alt)

    @staticmethod
    def _coerce_list(section: Section) -> list[str]:
        items: list[str] = []
        for block in section.blocks:
            if block.items:
                items.extend(block.items)
            elif block.text:
                items.append(block.text)
        return items
