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
from ..content.richtext import inline_md, md_to_html
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
    cta_blocks: list | None = None,
    sources: list | None = None,
    related_links: list | None = None,
    wildlife: list | None = None,
    wildlife_intro: str = "",
    visitor_sites_intro: str = "",
    quick_facts_title: str = "",
    quick_facts_intro: str = "",
    wildlife_title: str = "",
    visitor_sites_title: str = "",
) -> tuple[dict[str, Any], list[str]]:
    if config.mode == "flat":
        return build_acf_flat(components, config, subtitle=subtitle, schema_jsonld=schema_jsonld)
    if config.mode == "island":
        return build_acf_island(
            components, config, subtitle=subtitle, schema_jsonld=schema_jsonld,
            geo_answer=geo_answer, author=author,
            quick_facts=quick_facts, visitor_sites=visitor_sites,
            cta_blocks=cta_blocks, sources=sources, related_links=related_links,
            wildlife=wildlife, wildlife_intro=wildlife_intro,
            visitor_sites_intro=visitor_sites_intro,
            quick_facts_title=quick_facts_title, quick_facts_intro=quick_facts_intro,
            wildlife_title=wildlife_title, visitor_sites_title=visitor_sites_title,
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
    cta_blocks: list | None = None,
    sources: list | None = None,
    related_links: list | None = None,
    wildlife: list | None = None,
    wildlife_intro: str = "",
    visitor_sites_intro: str = "",
    quick_facts_title: str = "",
    quick_facts_intro: str = "",
    wildlife_title: str = "",
    visitor_sites_title: str = "",
) -> tuple[dict[str, Any], list[str]]:
    """Map components to the structured 'Island Guide Content' ACF group.

    Hero, FAQs, and prose fold into ``feature_sections``. ``quick_facts``,
    ``visitor_sites``, ``cta_blocks``, ``sources`` and ``related_links`` come
    pre-extracted from the doc; travel sections route to ``travel_information``;
    ``wildlife`` extraction is a later layer.
    """
    warnings: list[str] = []
    m = config.island
    out: dict[str, Any] = {}
    features: list[dict] = []
    travel: dict[str, str] = {}
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
    if wildlife and m.get("wildlife"):
        out[m["wildlife"]["field"]] = [_wildlife_row(m["wildlife"], r) for r in wildlife]
    if wildlife_title and m.get("wildlife_title"):
        out[m["wildlife_title"]] = wildlife_title
    if wildlife_intro and m.get("wildlife_intro"):
        out[m["wildlife_intro"]] = md_to_html(wildlife_intro)
    if visitor_sites_title and m.get("visitor_sites_title"):
        out[m["visitor_sites_title"]] = visitor_sites_title
    if visitor_sites_intro and m.get("visitor_sites_intro"):
        out[m["visitor_sites_intro"]] = md_to_html(visitor_sites_intro)
    if quick_facts_title and m.get("quick_facts_title"):
        out[m["quick_facts_title"]] = quick_facts_title
    if quick_facts_intro and m.get("quick_facts_intro"):
        out[m["quick_facts_intro"]] = md_to_html(quick_facts_intro)
    # Dual CTA from the doc's CTA table (overrides a single component CTA).
    if cta_blocks and m.get("cta"):
        cf = m["cta"]
        out[cf["field"]] = [_cta_block_row(cf, b) for b in cta_blocks]
        used.add("cta")
    if sources and m.get("sources"):
        sf = m["sources"]
        out[sf["field"]] = [
            {sf["label"]: r.get("label", ""), sf["url"]: r.get("url", "")} for r in sources
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
            # `answer` is a WYSIWYG field -> keep HTML (links survive).
            out[ff["field"]] = [
                {ff["question"]: it.get("question", ""), ff["answer"]: it.get("answer", "")}
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
            heading = d.get("heading", "")
            # Content MOVED to a dedicated field is not also a feature section.
            routed = (
                _route_travel(heading, d.get("content", ""))
                if m.get("travel_information")
                else None
            )
            if routed:
                for k, v in routed.items():
                    if k.endswith("_title"):
                        travel.setdefault(k, v)  # H2 heading -> keep it, never lose titles
                    elif v:
                        travel[k] = (travel[k] + "\n" + v) if travel.get(k) else v
                continue
            if _should_skip_feature(heading):
                continue  # sources / related-links footer -> dedicated fields
            # The lead paragraph (no heading, before any titled section) is the
            # island intro/overview -> its own field, not a title-less card.
            if m.get("intro") and not heading.strip() and m["intro"] not in out and not features:
                out[m["intro"]] = d.get("content", "")
                continue
            body, btn_label, btn_url = _split_feature_button(d.get("content", ""))
            features.append(_feature_row(m["feature_sections"], title=heading,
                                         content=body, button_label=btn_label,
                                         button_url=btn_url))
        elif m.get("feature_sections"):
            chunk = _component_body_html(comp)
            if chunk:
                features.append(_feature_row(m["feature_sections"], content=chunk))

    if m.get("feature_sections") and features:
        out[m["feature_sections"]["field"]] = features
    if m.get("travel_information") and travel:
        ti = m["travel_information"]
        out[ti["field"]] = {ti[k]: v for k, v in travel.items() if ti.get(k)}
    if related_links and m.get("related_links"):
        rl = m["related_links"]
        out[rl["field"]] = [
            {rl["label"]: r.get("label", ""), rl["url"]: r.get("url", "")} for r in related_links
        ]
    # GEO/AI answer: prefer the doc's extracted GEO block, else a tagline/subtitle.
    if m.get("geo_answer") and (geo_answer or subtitle):
        out[m["geo_answer"]] = geo_answer or subtitle
    if m.get("author") and author:
        out[m["author"]] = author
    if schema_jsonld and config.top_level.get("schema_jsonld"):
        out[config.top_level["schema_jsonld"]] = schema_jsonld
    return out, warnings


# Section headings handled by dedicated fields, not folded into feature_sections.
_FEATURE_SKIP_HEADINGS = {"sources", "sources & citations", "sources and citations", "citations"}
_SKIP_CONTAINS = ("explore more", "seo footer", "version footer")
_TRAVEL_RULES = [
    ("getting_there", re.compile(r"getting (to|there)|how to get|how to reach|arriv", re.I)),
    ("best_time", re.compile(r"best time|when to (visit|go)|best season", re.I)),
    ("accommodation", re.compile(r"where to stay|accommodation|hotels|lodging", re.I)),
]


def _travel_field(heading: str) -> str | None:
    for field, rx in _TRAVEL_RULES:
        if rx.search(heading):
            return field
    return None


# Which "section title" field holds a travel H2 heading (so it is never lost).
_TRAVEL_TITLE_FIELD = {
    "getting_there": "getting_there_title",
    "best_time": "stay_visit_title",
    "accommodation": "stay_visit_title",
}
_H3_SPLIT_RE = re.compile(r"(<h3[^>]*>.*?</h3>)", re.I | re.S)
_TAG_RE = re.compile(r"<[^>]+>")


def _split_by_h3(content: str) -> list[tuple[str | None, str]]:
    """[(h3_text|None, html_chunk), …]; the chunk keeps its own <h3>."""
    parts = _H3_SPLIT_RE.split(content or "")
    segments: list[tuple[str | None, str]] = []
    if parts and parts[0].strip():
        segments.append((None, parts[0]))
    i = 1
    while i < len(parts):
        h3_html = parts[i]
        chunk = h3_html + (parts[i + 1] if i + 1 < len(parts) else "")
        segments.append((_TAG_RE.sub("", h3_html).strip(), chunk))
        i += 2
    return segments


def _route_travel(heading: str, content: str) -> dict | None:
    """Route a travel H2 into Travel-Information sub-fields.

    A container H2 ('Where to Stay and When to Visit') is split by its H3 children
    so Accommodation and Best Time land in their own fields instead of one
    swallowing the other. The H2 heading is kept in a *_title field. Returns None
    when the section isn't travel-related (so it falls through to feature sections).
    """
    field = _travel_field(heading)
    if not field:
        return None
    out: dict[str, str] = {}
    segments = _split_by_h3(content)
    mapped = [(s, _travel_field(s[0])) for s in segments if s[0]]
    if any(f for _, f in mapped):
        # Container: each child H3 that maps goes to its field; the rest (lead
        # text, non-mapping H3s) join the H2's own field.
        for (h3, chunk), f in mapped:
            if f:
                out[f] = (out.get(f, "") + chunk) if out.get(f) else chunk
        leftover = "".join(
            chunk for (h3, chunk) in segments if not (h3 and _travel_field(h3))
        )
        if leftover.strip():
            out[field] = (out.get(field, "") + leftover) if out.get(field) else leftover
    else:
        out[field] = content
    title_field = _TRAVEL_TITLE_FIELD.get(field)
    if title_field and heading:
        out[title_field] = heading
    # A trailing standalone link in each block (e.g. "→ /planning/best-time/")
    # becomes that block's button, not an inline <a> at the foot of the prose.
    for f in ("getting_there", "best_time", "accommodation"):
        if out.get(f):
            body, label, url = _split_trailing_link(out[f])
            if url:
                out[f] = body
                out[f + "_button_label"] = label
                out[f + "_button_url"] = url
    return out


# A trailing paragraph that is ONLY a link (arrow optional) — the internal-link
# notation after _clean_markers/md_to_html renders as <p><a href>label</a></p>.
_TRAILING_LINK_RE = re.compile(
    r'<p>\s*(?:→|&rarr;|&#8594;)?\s*<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>\s*</p>\s*$',
    re.I | re.S,
)


def _split_trailing_link(html: str) -> tuple[str, str, str]:
    """Pull a trailing pure-link paragraph off HTML -> (html_without, label, url)."""
    if not html:
        return html, "", ""
    m = _TRAILING_LINK_RE.search(html)
    if not m:
        return html, "", ""
    return html[: m.start()].rstrip(), _plain_text(m.group(2)), m.group(1).strip()


def _should_skip_feature(heading: str) -> bool:
    h = heading.strip().lower()
    if h.startswith(("wildlife", "visitor sites")):  # -> their dedicated repeaters
        return True
    if "at a glance" in h or "quick facts" in h:  # -> the quick facts tab
        return True
    return h in _FEATURE_SKIP_HEADINGS or any(s in h for s in _SKIP_CONTAINS)


def _cta_block_row(cf: dict, b: dict) -> dict:
    row: dict[str, Any] = {}
    if cf.get("audience"):
        row[cf["audience"]] = b.get("audience", "Direct travelers")
    if cf.get("title"):
        row[cf["title"]] = b.get("title", "")
    if cf.get("text"):
        row[cf["text"]] = inline_md(b.get("text", ""))  # WYSIWYG -> keep links
    if cf.get("button_label") and b.get("button_label"):
        row[cf["button_label"]] = b["button_label"]
    if cf.get("button_url") and b.get("button_url"):
        row[cf["button_url"]] = b["button_url"]
    return row


# description is WYSIWYG (links survive); the short list fields stay plain text.
_VISITOR_RICH = {"description"}
_VISITOR_PLAIN = {"activities", "species_seen"}


def _wildlife_row(w: dict, r: dict) -> dict:
    row: dict[str, Any] = {}
    if w.get("common_name"):
        row[w["common_name"]] = r.get("common_name", "")
    if w.get("scientific_name") and r.get("scientific_name"):
        row[w["scientific_name"]] = r["scientific_name"]
    if w.get("description"):
        row[w["description"]] = md_to_html(r.get("description", ""))  # WYSIWYG
    if w.get("where_seen") and r.get("where_seen"):
        row[w["where_seen"]] = r["where_seen"]
    if w.get("best_season") and r.get("best_season"):
        row[w["best_season"]] = r["best_season"]
    if w.get("button_label") and r.get("button_label"):
        row[w["button_label"]] = r["button_label"]
    if w.get("button_url") and r.get("button_url"):
        row[w["button_url"]] = r["button_url"]
    return row


def _visitor_row(vs: dict, r: dict) -> dict:
    row: dict[str, Any] = {}
    for key in ("site_name", "access_type", "description", "activities", "species_seen", "access"):
        if vs.get(key):
            value = r.get(key, "")
            if key in _VISITOR_RICH:
                row[vs[key]] = md_to_html(value)  # WYSIWYG: paragraphs + links
            elif key in _VISITOR_PLAIN:
                row[vs[key]] = _plain_text(value)
            else:
                row[vs[key]] = value
    if vs.get("button_label") and r.get("button_label"):
        row[vs["button_label"]] = r["button_label"]
    if vs.get("button_url") and r.get("button_url"):
        row[vs["button_url"]] = r["button_url"]
    # Image is omitted until we extract one: an ACF image field over REST must be
    # an attachment ID or null, never a boolean.
    return row


_MD_LINK = re.compile(r"\[([^\]]+)\]\([^)\s]+\)")


def _plain_text(html_str: str) -> str:
    """HTML/Markdown -> clean single-line text for textarea fields (no tags/links)."""
    text = _MD_LINK.sub(r"\1", html_str or "")  # [label](url) -> label
    text = re.sub(r"(?i)</(p|li|h[1-6]|div)>", " ", text)
    text = re.sub(r"(?i)<br\s*/?>", " ", text)
    text = re.sub(r"<[^>]+>", "", text)
    return " ".join(html.unescape(text).split()).strip()


def _feature_row(
    fs: dict,
    *,
    title: str = "",
    content: str = "",
    subtitle: str = "",
    button_label: str = "",
    button_url: str = "",
) -> dict:
    row: dict[str, Any] = {}
    if fs.get("title"):
        row[fs["title"]] = title
    if fs.get("subtitle"):
        row[fs["subtitle"]] = subtitle
    if fs.get("content"):
        row[fs["content"]] = content
    if fs.get("button_label") and button_label:
        row[fs["button_label"]] = button_label
    if fs.get("button_url") and button_url:
        row[fs["button_url"]] = button_url
    return row


# A trailing "→ Label /url" paragraph at the end of a feature section is its
# call-to-action button (button_label / button_url), not body copy. No current
# island doc emits one, but the Española-style "→ Explore X" card uses it.
_FEATURE_BTN_RE = re.compile(r"<p>\s*(?:→|&rarr;|&#8594;)\s*(.*?)</p>\s*$", re.I | re.S)
_HTML_LINK_RE = re.compile(r'<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>', re.I | re.S)


def _split_feature_button(content: str) -> tuple[str, str, str]:
    """Pull a trailing '→ Label /url' CTA paragraph out of feature HTML.

    Returns ``(content_without_button, button_label, button_url)``. When the
    content has no such trailing paragraph (or no usable target) it comes back
    unchanged with empty label/url so the text stays in the body.
    """
    if not content:
        return content, "", ""
    m = _FEATURE_BTN_RE.search(content)
    if not m:
        return content, "", ""
    inner = m.group(1).strip()
    link = _HTML_LINK_RE.search(inner)
    if link:
        url, label = link.group(1).strip(), _plain_text(link.group(2))
    else:
        text = _plain_text(inner)
        head, _, tail = text.rpartition(" ")
        if head and re.match(r"^(/|https?:|mailto:)", tail):
            label, url = head.strip(), tail.strip()
        else:
            label, url = text, ""
    if not url:
        return content, "", ""  # no link target -> leave it in the body
    return content[: m.start()].rstrip(), label, url


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
