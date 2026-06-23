"""Page-type templates (schema / category / SEO profiles).

The output layer is ACF (see ``wp_publisher.acf`` and ``wp_publisher.content``);
templates here only carry per-page-type metadata: schema.org type, default
categories/tags, focus-keyword source, and the search-volume tier hints.
"""

from .template import PageTemplate, TemplateRegistry, load_registry

__all__ = ["PageTemplate", "TemplateRegistry", "load_registry"]
