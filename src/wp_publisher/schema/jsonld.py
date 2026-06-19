"""Build schema.org JSON-LD for a page.

The template's ``schema_type`` selects the top-level @type (Article,
TouristTrip, TouristDestination, ...). We always attach Organization +
WebSite/publisher context and, when an FAQ section exists, an FAQPage entity so
the page is eligible for rich results.
"""

from __future__ import annotations

from datetime import date
from typing import Any

from ..config import Settings
from ..models import Document
from ..rendering.template import PageTemplate


def build_json_ld(
    doc: Document,
    template: PageTemplate,
    settings: Settings,
    *,
    url: str,
    title: str,
    description: str,
    image_url: str | None,
) -> dict[str, Any]:
    org = settings.organization
    today = date.today().isoformat()

    publisher = {
        "@type": "Organization",
        "name": org.get("name", ""),
        "url": org.get("url", ""),
    }
    if org.get("logo"):
        publisher["logo"] = {"@type": "ImageObject", "url": org["logo"]}

    main: dict[str, Any] = {
        "@type": template.schema_type,
        "name": title,
        "headline": title,
        "description": description,
        "url": url,
        "inLanguage": settings.locale.get("language", "en-US"),
        "datePublished": today,
        "dateModified": today,
        "publisher": publisher,
    }
    if image_url:
        main["image"] = image_url

    # Type-specific enrichment.
    _enrich_by_type(main, doc, template, settings)

    # Merge any template-declared extras (e.g. touristType, audience).
    for key, value in template.schema_extra.items():
        main.setdefault(key, value)

    graph: list[dict[str, Any]] = [main]

    faq = _faq_entity(doc)
    if faq:
        graph.append(faq)

    return {"@context": "https://schema.org", "@graph": graph}


def _enrich_by_type(
    main: dict[str, Any], doc: Document, template: PageTemplate, settings: Settings
) -> None:
    stype = template.schema_type
    meta = doc.metadata

    if stype in ("TouristTrip", "Trip"):
        if meta.get("duration"):
            main["itinerary"] = main.get("itinerary") or _itinerary_items(doc)
        if meta.get("destination"):
            main["arrivalLocation"] = {
                "@type": "Place",
                "name": str(meta["destination"]),
            }
        offer = _offer(meta, settings)
        if offer:
            main["offers"] = offer

    elif stype == "TouristDestination":
        name = meta.get("destination") or main["name"]
        main["touristType"] = meta.get("tourist_type", "Travelers")
        main["name"] = name
        if meta.get("country"):
            main["containedInPlace"] = {"@type": "Country", "name": str(meta["country"])}

    elif stype in ("Article", "BlogPosting"):
        main["author"] = {
            "@type": "Organization",
            "name": settings.organization.get("name", ""),
        }
        main["mainEntityOfPage"] = {"@type": "WebPage", "@id": main["url"]}


def _itinerary_items(doc: Document) -> list[dict[str, Any]]:
    itinerary = doc.find_section("itinerary")
    if not itinerary:
        return []
    items: list[dict[str, Any]] = []
    position = 1
    for block in itinerary.blocks:
        entries = block.items if block.items else ([block.text] if block.text else [])
        for entry in entries:
            if not entry.strip():
                continue
            items.append(
                {
                    "@type": "ListItem",
                    "position": position,
                    "item": {"@type": "TouristAttraction", "name": entry.strip()[:120]},
                }
            )
            position += 1
    return items


def _offer(meta: dict[str, Any], settings: Settings) -> dict[str, Any] | None:
    price = meta.get("price") or meta.get("from_price")
    if not price:
        return None
    digits = "".join(ch for ch in str(price) if ch.isdigit() or ch == ".")
    return {
        "@type": "Offer",
        "price": digits or str(price),
        "priceCurrency": settings.locale.get("currency", "USD"),
        "availability": "https://schema.org/InStock",
    }


def _faq_entity(doc: Document) -> dict[str, Any] | None:
    faq = doc.find_section("faq")
    if not faq:
        return None
    questions: list[dict[str, Any]] = []
    pending_q: str | None = None
    for block in faq.blocks:
        if block.type.value == "heading":
            pending_q = block.text
        elif block.text and pending_q:
            questions.append(
                {
                    "@type": "Question",
                    "name": pending_q,
                    "acceptedAnswer": {"@type": "Answer", "text": block.text},
                }
            )
            pending_q = None
    if not questions:
        return None
    return {"@type": "FAQPage", "mainEntity": questions}
