"""Page templates: declarative layouts loaded from templates/*.yaml.

A template describes, for one type of page, how an incoming document should be
arranged: which sections are expected, in what order, how each renders, what
schema.org type to emit, and SEO/media defaults. Editors customize behavior by
editing YAML — no Python changes required to add or tune a page type.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any

import yaml
from pydantic import BaseModel, Field

from ..config import TEMPLATES_DIR


class SlotSpec(BaseModel):
    """One entry in a template's layout."""

    slot: str
    # section | media | faq | table | spacer | html
    kind: str = "section"
    required: bool = False
    # Default heading text if the matched section has none (or to override).
    heading: str | None = None
    # Suppress the section's own heading (used for the lead/overview).
    show_heading: bool = True
    # Preferred block style for a section's content: prose | list | callout
    style: str = "prose"
    # For media slots: featured | inline | gallery
    role: str = "inline"
    # Hint text shown in placeholders.
    hint: str = ""


class PageTemplate(BaseModel):
    key: str
    name: str
    description: str = ""
    post_type: str = "post"
    status: str | None = None  # overrides default if set
    schema_type: str = "Article"
    categories: list[str] = Field(default_factory=list)
    tags: list[str] = Field(default_factory=list)
    # Which document metadata key supplies the focus keyword, if not explicit.
    focus_keyword_from: str | None = None
    layout: list[SlotSpec] = Field(default_factory=list)
    required_sections: list[str] = Field(default_factory=list)
    # Free-form extra schema fields merged into JSON-LD (e.g. touristType).
    schema_extra: dict[str, Any] = Field(default_factory=dict)

    @classmethod
    def from_file(cls, path: Path) -> "PageTemplate":
        data = yaml.safe_load(Path(path).read_text(encoding="utf-8")) or {}
        return cls.model_validate(data)


class TemplateRegistry:
    """Holds all available page templates and resolves which one to use."""

    def __init__(self, templates: dict[str, PageTemplate]):
        self._templates = templates

    def __contains__(self, key: str) -> bool:
        return key in self._templates

    def keys(self) -> list[str]:
        return sorted(self._templates.keys())

    def get(self, key: str) -> PageTemplate:
        key = (key or "").strip().lower().replace(" ", "_")
        if key not in self._templates:
            raise KeyError(
                f"Unknown page type '{key}'. Available: {', '.join(self.keys())}"
            )
        return self._templates[key]

    def all(self) -> list[PageTemplate]:
        return [self._templates[k] for k in self.keys()]


def load_registry(templates_dir: Path | None = None) -> TemplateRegistry:
    directory = templates_dir or TEMPLATES_DIR
    templates: dict[str, PageTemplate] = {}
    for path in sorted(Path(directory).glob("*.yaml")):
        tpl = PageTemplate.from_file(path)
        templates[tpl.key] = tpl
    if not templates:
        raise RuntimeError(f"No templates found in {directory}")
    return TemplateRegistry(templates)
