"""Load the site's ACF mapping.

ACF mappings are **per-profile**, so each site section can target its own ACF
field group:

    config/acf.yaml            -> the DEFAULT profile (used when a page type
                                  declares no profile)
    config/acf/<profile>.yaml  -> a named profile (e.g. config/acf/island.yaml),
                                  selected by a template's `acf_profile`

A page template declares which profile it uses (`acf_profile: island`); the
pipeline loads that profile. If a named profile file is missing, we fall back to
the default config so a build never crashes — `AcfConfig.resolved_from_default`
records whether that happened.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any

import yaml

from ..config import PROJECT_ROOT

DEFAULT_ACF_CONFIG = PROJECT_ROOT / "config" / "acf.yaml"
ACF_PROFILE_DIR = PROJECT_ROOT / "config" / "acf"


class AcfConfig:
    def __init__(self, data: dict[str, Any], *, profile: str | None = None,
                 resolved_from_default: bool = False):
        self.profile: str | None = profile
        # True when a named profile was requested but its file was missing, so
        # the default config was used instead.
        self.resolved_from_default: bool = resolved_from_default
        self.mode: str = data.get("mode", "flexible")  # flat | flexible | island
        self.flat: dict[str, Any] = data.get("flat", {}) or {}
        self.island: dict[str, Any] = data.get("island", {}) or {}
        self.flexible_field: str = data.get("flexible_field", "page_sections")
        self.fallback_layout: str = data.get("fallback_layout", "rich_text")
        self.top_level: dict[str, str] = data.get("top_level", {}) or {}
        self.image_as: str = data.get("image_as", "id")
        self.list_as: str = data.get("list_as", "html")
        self.layouts: dict[str, dict] = data.get("layouts", {}) or {}

    def layout_for(self, component_type: str) -> dict | None:
        return self.layouts.get(component_type)


def _profile_path(profile: str) -> Path:
    return ACF_PROFILE_DIR / f"{profile}.yaml"


def load_acf_config(profile: str | None = None, *, path: Path | None = None) -> AcfConfig:
    """Load a profile's ACF mapping.

    `profile` selects config/acf/<profile>.yaml; with no profile (or an explicit
    `path`) the default config/acf.yaml is used. A missing profile file falls
    back to the default config.
    """
    resolved_from_default = False
    if path is not None:
        p = path
    elif profile:
        p = _profile_path(profile)
        if not p.exists():
            p = DEFAULT_ACF_CONFIG
            resolved_from_default = True
    else:
        p = DEFAULT_ACF_CONFIG
    data = yaml.safe_load(p.read_text(encoding="utf-8")) if p.exists() else {}
    return AcfConfig(data or {}, profile=profile, resolved_from_default=resolved_from_default)


_CACHE: dict[str, AcfConfig] = {}


def get_acf_config(profile: str | None = None) -> AcfConfig:
    key = profile or ""
    if key not in _CACHE:
        _CACHE[key] = load_acf_config(profile)
    return _CACHE[key]
