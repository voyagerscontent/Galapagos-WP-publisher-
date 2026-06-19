"""Rendering: Document + Template -> WordPress Gutenberg block markup."""

from .blocks import (
    faq_block,
    heading_block,
    image_block,
    list_block,
    paragraph_block,
    placeholder_block,
    quote_block,
    table_block,
)
from .template import PageTemplate, TemplateRegistry, load_registry

__all__ = [
    "paragraph_block",
    "heading_block",
    "list_block",
    "image_block",
    "quote_block",
    "table_block",
    "faq_block",
    "placeholder_block",
    "PageTemplate",
    "TemplateRegistry",
    "load_registry",
]
