"""Map composed components to an ACF REST payload.

Produces the dict sent as the post's ``acf`` field over core REST:

    {
      "<flexible_field>": [
        {"acf_fc_layout": "hero", "heading": "...", "buttons": [...]},
        {"acf_fc_layout": "rich_text", "content": "<p>...</p>"},
        ...
      ],
      "<subtitle_field>": "...",
      "<schema_field>": "{...json-ld...}",
    }
"""

from __future__ import annotations

import html
from typing import Any

from ..content.model import Component
from .config import AcfConfig

# Component data keys that hold an image reference / list of references.
_IMAGE_KEYS = {"image"}
_IMAGE_LIST_KEYS = {"images"}


def build_acf(
    components: list[Component],
    config: AcfConfig,
    *,
    subtitle: str = "",
    schema_jsonld: str = "",
) -> tuple[dict[str, Any], list[str]]:
    warnings: list[str] = []
    rows: list[dict] = []

    for comp in components:
        layout = config.layout_for(comp.type)
        if layout is None:
            coerced = _coerce_to_html(comp)
            layout = config.layout_for(config.fallback_layout)
            if layout is None:
                warnings.append(f"No ACF layout for component '{comp.type}' and no fallback.")
                continue
            warnings.append(f"Component '{comp.type}' has no ACF layout; using fallback.")
            comp = Component(type=config.fallback_layout, data={"content": coerced})
        rows.append(_row(comp, layout, config, warnings))

    acf: dict[str, Any] = {config.flexible_field: rows}
    if subtitle and config.top_level.get("subtitle"):
        acf[config.top_level["subtitle"]] = subtitle
    if schema_jsonld and config.top_level.get("schema_jsonld"):
        acf[config.top_level["schema_jsonld"]] = schema_jsonld
    return acf, warnings


def _row(comp: Component, layout: dict, config: AcfConfig, warnings: list[str]) -> dict:
    row: dict[str, Any] = {"acf_fc_layout": layout.get("layout", comp.type)}
    fields: dict[str, str] = layout.get("fields", {})
    repeaters: dict[str, dict] = layout.get("repeaters", {})

    for comp_key, wp_field in fields.items():
        value = comp.data.get(comp_key)
        if comp_key in repeaters:
            row[wp_field] = _repeater(value, repeaters[comp_key], config)
        elif comp_key in _IMAGE_KEYS:
            row[wp_field] = _image_value(value, config)
        elif comp_key in _IMAGE_LIST_KEYS:
            row[wp_field] = [_image_value(v, config) for v in (value or [])]
        elif comp_key == "items" and _is_str_list(value):
            row[wp_field] = _list_value(value, config)
        elif comp_key == "columns" and not repeaters.get("columns"):
            row[wp_field] = value or []
        else:
            row[wp_field] = value if value is not None else ""
    return row


def _repeater(value, sub_map: dict[str, str], config: AcfConfig) -> list[dict]:
    out = []
    for item in value or []:
        if isinstance(item, dict):
            out.append({wp: item.get(src, "") for src, wp in sub_map.items()})
        elif isinstance(item, str):
            # Single-field repeaters (e.g. columns -> {content}).
            only = next(iter(sub_map.values()))
            out.append({only: item})
    return out


def _image_value(ref, config: AcfConfig):
    if not isinstance(ref, dict):
        return "" if config.image_as == "url" else False
    if config.image_as == "url":
        return ref.get("url") or ""
    return ref.get("media_id") or False  # ACF image field: attachment ID or false


def _list_value(items: list[str], config: AcfConfig):
    mode = config.list_as
    if mode == "array":
        return items
    if mode == "newline":
        return "\n".join(items)
    # default: html unordered list
    return "<ul>" + "".join(f"<li>{html.escape(i)}</li>" for i in items) + "</ul>"


def _is_str_list(value) -> bool:
    return isinstance(value, list) and all(isinstance(i, str) for i in value)


def _coerce_to_html(comp: Component) -> str:
    d = comp.data
    if comp.type == "quote":
        cite = f"<cite>{html.escape(d.get('citation', ''))}</cite>" if d.get("citation") else ""
        return f"<blockquote><p>{html.escape(d.get('text', ''))}</p>{cite}</blockquote>"
    if comp.type == "columns":
        return "".join(f"<div>{c}</div>" for c in d.get("columns", []))
    if comp.type == "html":
        return d.get("content", "")
    return d.get("content", "") or d.get("text", "")
