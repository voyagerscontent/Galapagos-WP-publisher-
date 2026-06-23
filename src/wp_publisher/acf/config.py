"""Load the site's ACF mapping (config/acf.yaml)."""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path
from typing import Any

import yaml

from ..config import PROJECT_ROOT

DEFAULT_ACF_CONFIG = PROJECT_ROOT / "config" / "acf.yaml"


class AcfConfig:
    def __init__(self, data: dict[str, Any]):
        self.mode: str = data.get("mode", "flexible")  # flat | flexible
        self.flat: dict[str, Any] = data.get("flat", {}) or {}
        self.flexible_field: str = data.get("flexible_field", "page_sections")
        self.fallback_layout: str = data.get("fallback_layout", "rich_text")
        self.top_level: dict[str, str] = data.get("top_level", {}) or {}
        self.image_as: str = data.get("image_as", "id")
        self.list_as: str = data.get("list_as", "html")
        self.layouts: dict[str, dict] = data.get("layouts", {}) or {}

    def layout_for(self, component_type: str) -> dict | None:
        return self.layouts.get(component_type)


def load_acf_config(path: Path | None = None) -> AcfConfig:
    p = path or DEFAULT_ACF_CONFIG
    data = yaml.safe_load(p.read_text(encoding="utf-8")) if p.exists() else {}
    return AcfConfig(data or {})


@lru_cache(maxsize=1)
def get_acf_config() -> AcfConfig:
    return load_acf_config()
