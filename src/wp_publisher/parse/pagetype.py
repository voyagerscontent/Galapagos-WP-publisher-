"""Decide which template a document should use.

Resolution order:

1. Explicit metadata: a ``type`` / ``page_type`` field in frontmatter or a
   leading "Type: ..." line. This is the recommended, unambiguous path.
2. Heuristic scoring over section slugs and keywords, as a fallback so a
   document with no declared type still lands somewhere sensible.
"""

from __future__ import annotations

from ..models import Document
from ..rendering.template import TemplateRegistry

# Section/keyword signals that point at a given template key.
_SIGNALS: dict[str, dict[str, float]] = {
    "tour": {
        "itinerary": 3.0,
        "whats_included": 1.5,
        "whats_excluded": 1.0,
        "day": 0.5,
        "duration": 1.0,
    },
    "cruise": {
        "itinerary": 1.5,
        "ship": 3.0,
        "cabin": 2.0,
        "deck": 1.5,
        "yacht": 2.0,
        "cruise": 2.0,
    },
    "destination": {
        "best_time": 2.0,
        "getting_there": 2.0,
        "things_to_do": 2.0,
        "practical_info": 1.5,
    },
    "wildlife_tier1": {
        "identification": 3.0,
        "behavior": 2.0,
        "life_cycle": 2.0,
        "conservation": 2.0,
        "range_habitat": 2.0,
        "where_to_see": 1.5,
        "species": 1.0,
        "scientific_name": 1.0,
    },
    "blog_post": {
        "conclusion": 1.0,
        "overview": 0.3,
    },
}

# Map a variety of user-typed values onto canonical template keys.
_ALIASES = {
    "tour": "tour",
    "itinerary": "tour",
    "trip": "tour",
    "package": "tour",
    "cruise": "cruise",
    "ship": "cruise",
    "yacht": "cruise",
    "destination": "destination",
    "place": "destination",
    "region": "destination",
    "blog": "blog_post",
    "blog_post": "blog_post",
    "post": "blog_post",
    "article": "blog_post",
    "guide": "blog_post",
    "wildlife": "wildlife_tier1",
    "wildlife_tier1": "wildlife_tier1",
    "wildlife_tier_1": "wildlife_tier1",
    "tier1_wildlife": "wildlife_tier1",
    "species": "wildlife_tier1",
    "animal": "wildlife_tier1",
}


def detect_page_type(doc: Document, registry: TemplateRegistry) -> tuple[str, str]:
    """Return (template_key, reason)."""
    # 1) Explicit declaration.
    declared = (
        doc.metadata.get("type")
        or doc.metadata.get("page_type")
        or doc.metadata.get("template")
    )
    if declared:
        norm = str(declared).strip().lower().replace(" ", "_")
        key = _ALIASES.get(norm, norm)
        if key in registry:
            return key, f"declared type '{declared}'"

    # 2) Heuristic scoring.
    slugs = {s.slug for s in doc.sections}
    haystack = doc.all_text().lower()
    scores: dict[str, float] = {}
    for key, signals in _SIGNALS.items():
        if key not in registry:
            continue
        score = 0.0
        for token, weight in signals.items():
            if token in slugs:
                score += weight
            elif token in haystack:
                score += weight * 0.4
        scores[key] = score

    if scores:
        best = max(scores, key=scores.get)
        if scores[best] > 0:
            return best, f"matched signals (score {scores[best]:.1f})"

    # 3) Safe default.
    fallback = "blog_post" if "blog_post" in registry else registry.keys()[0]
    return fallback, "defaulted (no strong signal)"
