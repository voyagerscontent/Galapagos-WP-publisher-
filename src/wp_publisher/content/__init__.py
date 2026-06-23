"""Structured content model + ACF mapping.

The pipeline no longer emits Gutenberg blocks. Instead a document is composed
into an ordered list of typed `Component`s (hero, rich_text, accordion, …),
which an `AcfMapper` turns into ACF field values populated over the REST API.
The WordPress theme renders those ACF fields, so design lives on the WP side.
"""

from .model import Component, ContentModel

__all__ = ["Component", "ContentModel"]
