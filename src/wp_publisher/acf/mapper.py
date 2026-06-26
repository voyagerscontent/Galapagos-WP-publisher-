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
import re
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
    geo_answer: str = "",
    author: str = "",
    quick_facts: list | None = None,
    visitor_sites: list | None = None,
) -> tuple[dict[str, Any], list[str]]:
    if config.mode == "flat":
        return build_acf_flat(components, config, subtitle=subtitle, schema_jsonld=schema_jsonld)
    if config.mode == "island":
        return build_acf_island(
            components, config, subtitle=subtitle, schema_jsonld=schema_jsonld,
            geo_answer=geo_answer, author=author,
            quick_facts=quick_facts, visitor_sites=visitor_sites,
        )
    return build_acf_flexible(components, config, subtitle=subtitle, schema_jsonld=schema_jsonld)


def build_acf_flexible(
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


def build_acf_flat(
    components: list[Component],
    config: AcfConfig,
    *,
    subtitle: str = "",
    schema_jsonld: str = "",
) -> tuple[dict[str, Any], list[str]]:
    """Map components to flat, named ACF fields (Elementor Dynamic Tag friendly)."""
    warnings: list[str] = []
    f = config.flat
    out: dict[str, Any] = {}
    body: list[str] = []
    used: set[str] = set()

    for comp in components:
        t = comp.type
        if t == "hero" and "hero" not in used and f.get("hero"):
            used.add("hero")
            hf, d = f["hero"], comp.data
            _set(out, hf.get("eyebrow"), d.get("eyebrow"))
            _set(out, hf.get("heading"), d.get("heading"))
            _set(out, hf.get("subheading"), d.get("subheading"))
            _set(out, hf.get("image"), _image_value(d.get("image"), config), allow_falsey=True)
            ctas = d.get("ctas") or []
            if ctas:
                _set(out, hf.get("cta_label"), ctas[0].get("label"))
                _set(out, hf.get("cta_url"), ctas[0].get("url"))
        elif t == "callout" and "callout" not in used and f.get("callout"):
            used.add("callout")
            cf = f["callout"]
            _set(out, cf.get("title"), comp.data.get("title"))
            _set(out, cf.get("content"), comp.data.get("content"))
        elif t == "accordion" and "faq" not in used and f.get("faq"):
            used.add("faq")
            ff = f["faq"]
            out[ff["field"]] = [
                {ff["question"]: it.get("question", ""), ff["answer"]: it.get("answer", "")}
                for it in comp.data.get("items", [])
            ]
        elif t == "stats" and "stats" not in used and f.get("stats"):
            used.add("stats")
            sf = f["stats"]
            out[sf["field"]] = [
                {sf["value"]: it.get("value", ""), sf["label"]: it.get("label", "")}
                for it in comp.data.get("items", [])
            ]
        elif t == "cta" and "cta" not in used and f.get("cta"):
            used.add("cta")
            cf, d = f["cta"], comp.data
            _set(out, cf.get("heading"), d.get("heading"))
            _set(out, cf.get("content"), d.get("content"))
            ctas = d.get("ctas") or []
            if ctas:
                _set(out, cf.get("cta_label"), ctas[0].get("label"))
                _set(out, cf.get("cta_url"), ctas[0].get("url"))
        else:
            html_chunk = _component_body_html(comp)
            if html_chunk:
                body.append(html_chunk)

    if f.get("body") and body:
        out[f["body"]] = "\n".join(body)
    if subtitle and config.top_level.get("subtitle"):
        out[config.top_level["subtitle"]] = subtitle
    if schema_jsonld and config.top_level.get("schema_jsonld"):
        out[config.top_level["schema_jsonld"]] = schema_jsonld
    return out, warnings


def build_acf_island(
    components: list[Component],
    config: AcfConfig,
    *,
    subtitle: str = "",
    schema_jsonld: str = "",
    geo_answer: str = "",
    author: str = "",
    quick_facts: list | None = None,
    visitor_sites: list | None = None,
) -> tuple[dict[str, Any], list[str]]:
    """Map components to the structured 'Island Guide Content' ACF group.

    Hero, FAQs, a CTA row, and prose fold into ``feature_sections``.
    ``quick_facts`` and ``visitor_sites`` come pre-extracted from the doc's
    tables (via metadata); ``wildlife`` extraction is a later layer.
    """
    warnings: list[str] = []
    m = config.island
    out: dict[str, Any] = {}
    features: list[dict] = []
    used: set[str] = set()

    # Table-derived repeaters (extracted upstream from the doc's tables).
    if quick_facts and m.get("quick_facts"):
        qf = m["quick_facts"]
        out[qf["field"]] = [
            {qf["label"]: r.get("label", ""), qf["value"]: r.get("value", "")}
            for r in quick_facts
        ]
        used.add("quick_facts")
    if visitor_sites and m.get("visitor_sites"):
        out[m["visitor_sites"]["field"]] = [
            _visitor_row(m["visitor_sites"], r) for r in visitor_sites
        ]

    for comp in components:
        t = comp.type
        if t == "hero" and "hero" not in used and m.get("hero"):
            used.add("hero")
            hf, d = m["hero"], comp.data
            img = d.get("image") if isinstance(d.get("image"), dict) else {}
            _set(out, hf.get("title"), d.get("heading"))
            _set(out, hf.get("subtitle"), d.get("subheading"))
            _set(out, hf.get("image"), _image_value(img, config), allow_falsey=True)
            _set(out, hf.get("image_alt"), img.get("alt"))
        elif t == "accordion" and "faqs" not in used and m.get("faqs"):
            used.add("faqs")
            ff = m["faqs"]
            # `answer` is a textarea -> store clean text, not HTML.
            out[ff["field"]] = [
                {ff["question"]: it.get("question", ""), ff["answer"]: _plain_text(it.get("answer", ""))}
                for it in comp.data.get("items", [])
            ]
        elif t == "stats" and "quick_facts" not in used and m.get("quick_facts"):
            used.add("quick_facts")
            qf = m["quick_facts"]
            out[qf["field"]] = [
                {qf["label"]: it.get("label", ""), qf["value"]: it.get("value", "")}
                for it in comp.data.get("items", [])
            ]
        elif t == "cta" and "cta" not in used and m.get("cta"):
            used.add("cta")
            out[m["cta"]["field"]] = [_cta_row(m["cta"], comp.data)]
        elif t == "rich_text" and m.get("feature_sections"):
            d = comp.data
            features.append(_feature_row(m["feature_sections"], title=d.get("heading", ""),
                                         content=d.get("content", "")))
        elif m.get("feature_sections"):
            chunk = _component_body_html(comp)
            if chunk:
                features.append(_feature_row(m["feature_sections"], content=chunk))

    if m.get("feature_sections") and features:
        out[m["feature_sections"]["field"]] = features
    # GEO/AI answer: prefer the doc's extracted GEO block, else a tagline/subtitle.
    if m.get("geo_answer") and (geo_answer or subtitle):
        out[m["geo_answer"]] = geo_answer or subtitle
    if m.get("author") and author:
        out[m["author"]] = author
    if schema_jsonld and config.top_level.get("schema_jsonld"):
        out[config.top_level["schema_jsonld"]] = schema_jsonld
    return out, warnings


def _visitor_row(vs: dict, r: dict) -> dict:
    row: dict[str, Any] = {}
    for key in ("site_name", "access_type", "description", "activities", "species_seen", "access"):
        if vs.get(key):
            row[vs[key]] = r.get(key, "")
    # Image is omitted until we extract one: an ACF image field over REST must be
    # an attachment ID or null, never a boolean.
    return row


def _plain_text(html_str: str) -> str:
    """HTML -> clean single-line text for textarea fields (no tags)."""
    text = re.sub(r"(?i)</(p|li|h[1-6]|div)>", " ", html_str or "")
    text = re.sub(r"(?i)<br\s*/?>", " ", text)
    text = re.sub(r"<[^>]+>", "", text)
    return " ".join(html.unescape(text).split()).strip()


def _feature_row(fs: dict, *, title: str = "", content: str = "", subtitle: str = "") -> dict:
    row: dict[str, Any] = {}
    if fs.get("title"):
        row[fs["title"]] = title
    if fs.get("subtitle"):
        row[fs["subtitle"]] = subtitle
    if fs.get("content"):
        row[fs["content"]] = content
    return row


def _cta_row(cf: dict, d: dict) -> dict:
    ctas = d.get("ctas") or []
    first = ctas[0] if ctas else {}
    row: dict[str, Any] = {}
    if cf.get("audience"):
        row[cf["audience"]] = "Direct travelers"
    if cf.get("title"):
        row[cf["title"]] = d.get("heading", "")
    if cf.get("text"):
        row[cf["text"]] = d.get("content", "")
    if cf.get("button_label"):
        row[cf["button_label"]] = first.get("label", "")
    if cf.get("button_url"):
        row[cf["button_url"]] = first.get("url", "")
    return row


def _set(out: dict, key, value, *, allow_falsey: bool = False) -> None:
    if key and (value or (allow_falsey and value is not None and value is not False)):
        out[key] = value


def _component_body_html(comp: Component) -> str:
    d = comp.data
    if comp.type == "rich_text":
        heading = f"<h2>{html.escape(d.get('heading', ''))}</h2>" if d.get("heading") else ""
        return heading + (d.get("content") or "")
    if comp.type == "callout":
        title = f"<strong>{html.escape(d.get('title', ''))}</strong> " if d.get("title") else ""
        return f'<aside class="callout">{title}{d.get("content", "")}</aside>'
    if comp.type == "image":
        ref = d.get("image") or {}
        url = ref.get("url")
        if not url:
            return ""
        cap = f"<figcaption>{html.escape(d.get('caption', ''))}</figcaption>" if d.get("caption") else ""
        return f'<figure><img src="{html.escape(url)}" alt="{html.escape(d.get("alt", ""))}"/>{cap}</figure>'
    if comp.type == "columns":
        return "".join(f'<div class="col">{c}</div>' for c in d.get("columns", []))
    if comp.type == "quote":
        return f"<blockquote><p>{html.escape(d.get('text', ''))}</p></blockquote>"
    if comp.type == "html":
        return d.get("content", "")
    return ""


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
