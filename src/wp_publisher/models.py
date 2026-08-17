"""Core data models shared across the pipeline.

The flow is:

    raw file  -->  Document  -->  (template + renderer)  -->  RenderedPage  -->  WordPress

`Document` is the source-agnostic, normalized representation of whatever was
ingested (Word, Markdown, plain text, Google Doc). Everything downstream only
ever sees a `Document`, never the original format.
"""

from __future__ import annotations

from enum import Enum
from typing import Any

from pydantic import BaseModel, Field


class BlockType(str, Enum):
    """Logical content blocks we recognize inside a document section."""

    PARAGRAPH = "paragraph"
    HEADING = "heading"
    LIST = "list"
    QUOTE = "quote"
    IMAGE = "image"
    TABLE = "table"
    CODE = "code"
    HTML = "html"


class ContentBlock(BaseModel):
    """A single piece of content within a section."""

    type: BlockType = BlockType.PARAGRAPH
    # For headings: the text. For lists: items joined by newline. For images:
    # the caption/alt. `html` carries pre-rendered inline HTML when present.
    text: str = ""
    html: str = ""
    level: int = 2  # heading level, when type == HEADING
    ordered: bool = False  # for lists
    items: list[str] = Field(default_factory=list)  # for lists
    rows: list[list[str]] = Field(default_factory=list)  # for tables
    # Image-specific
    src: str | None = None
    alt: str | None = None
    meta: dict[str, Any] = Field(default_factory=dict)


class Section(BaseModel):
    """A titled chunk of a document (delimited by a heading)."""

    title: str = ""
    level: int = 2
    # A normalized key used to map this section onto a template slot, e.g.
    # "overview", "itinerary", "whats_included". Derived from the title.
    slug: str = ""
    blocks: list[ContentBlock] = Field(default_factory=list)

    def plain_text(self) -> str:
        parts: list[str] = []
        for b in self.blocks:
            if b.items:
                parts.extend(b.items)
            elif b.text:
                parts.append(b.text)
        return "\n".join(parts).strip()


class Document(BaseModel):
    """Normalized source document, independent of input format."""

    title: str = ""
    # Free-form metadata pulled from frontmatter or "Key: value" lines, e.g.
    # type, focus_keyword, destination, duration, price, categories, tags...
    metadata: dict[str, Any] = Field(default_factory=dict)
    sections: list[Section] = Field(default_factory=list)
    # The raw, un-sectioned body as authored (used by the directive/freeform
    # builder, which parses its own layout instructions from the text).
    raw_body: str = ""
    # Provenance for logging/debugging.
    source_name: str = ""
    source_kind: str = ""  # docx | markdown | text | gdrive

    def all_text(self) -> str:
        chunks = [self.title]
        for s in self.sections:
            chunks.append(s.title)
            chunks.append(s.plain_text())
        return "\n".join(c for c in chunks if c).strip()

    def find_section(self, *slugs: str) -> Section | None:
        wanted = {s.lower() for s in slugs}
        for section in self.sections:
            if section.slug in wanted:
                return section
        return None


class MediaItem(BaseModel):
    """An image resolved for the page (from the library or a placeholder)."""

    source: str  # "library" | "placeholder"
    wp_media_id: int | None = None
    url: str | None = None
    alt: str = ""
    caption: str = ""
    slot: str = ""  # which template slot it fills (e.g. "featured", "gallery")
    match_score: float | None = None


class RenderedPage(BaseModel):
    """The final, WordPress-ready artifact produced by the build."""

    title: str
    slug: str
    # Slug of the parent page this post should nest under (e.g. "islands"). The
    # publisher resolves it to an ID and sets the post `parent`. Empty = none.
    parent_slug: str = ""
    # ACF field payload (the post's `acf` object) — the primary output.
    acf: dict[str, Any] = Field(default_factory=dict)
    # Optional HTML for post_content (normally empty; the theme renders ACF).
    content_html: str = ""
    excerpt: str = ""
    status: str = "draft"
    post_type: str = "post"
    categories: list[str] = Field(default_factory=list)
    tags: list[str] = Field(default_factory=list)
    featured_media: MediaItem | None = None
    media: list[MediaItem] = Field(default_factory=list)
    # SEO
    seo_title: str = ""
    meta_description: str = ""
    focus_keyword: str = ""
    # schema.org JSON-LD (already serialized as a dict)
    json_ld: dict[str, Any] = Field(default_factory=dict)
    # Anything the publisher should pass straight through as REST fields.
    extra_fields: dict[str, Any] = Field(default_factory=dict)
    # Extra post meta merged into the REST `meta` object (e.g. a page-type marker
    # that attaches the ACF group and targets an Elementor template).
    wp_meta: dict[str, Any] = Field(default_factory=dict)
    # Human-facing warnings (missing sections, unresolved media, etc.)
    warnings: list[str] = Field(default_factory=list)
