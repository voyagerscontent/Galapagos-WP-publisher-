"""Small shared helpers."""

from __future__ import annotations

import re

from slugify import slugify

# Common heading synonyms -> canonical slot slug. This lets a writer title a
# section however they like ("What's Included", "Inclusions", "Price Includes")
# and still have it land in the template's `whats_included` slot.
SECTION_SYNONYMS: dict[str, str] = {
    "overview": "overview",
    "introduction": "overview",
    "intro": "overview",
    "summary": "overview",
    "about": "overview",
    "highlights": "highlights",
    "trip-highlights": "highlights",
    "key-highlights": "highlights",
    "itinerary": "itinerary",
    "day-by-day": "itinerary",
    "daily-itinerary": "itinerary",
    "schedule": "itinerary",
    "whats-included": "whats_included",
    "what-is-included": "whats_included",
    "inclusions": "whats_included",
    "included": "whats_included",
    "price-includes": "whats_included",
    "whats-not-included": "whats_excluded",
    "exclusions": "whats_excluded",
    "not-included": "whats_excluded",
    "best-time-to-visit": "best_time",
    "when-to-go": "best_time",
    "best-time": "best_time",
    "how-to-get-there": "getting_there",
    "getting-there": "getting_there",
    "faq": "faq",
    "faqs": "faq",
    "frequently-asked-questions": "faq",
    "things-to-do": "things_to_do",
    "what-to-do": "things_to_do",
    "practical-info": "practical_info",
    "practical-information": "practical_info",
    "good-to-know": "practical_info",
    "pricing": "pricing",
    "prices": "pricing",
    "rates": "pricing",
    "cost": "pricing",
    "gallery": "gallery",
    "photos": "gallery",
    "conclusion": "conclusion",
    "final-thoughts": "conclusion",
}


# Ordered keyword rules applied when an exact synonym match fails. The first
# matching rule wins, so negations ("not included") are listed before their
# positive counterparts. Each entry: (canonical_slot, [substrings to look for]).
_KEYWORD_RULES: list[tuple[str, list[str]]] = [
    ("whats_excluded", ["not-included", "not-inclu", "exclu"]),
    ("whats_included", ["includ", "inclusion"]),
    ("itinerary", ["itinerary", "day-by-day", "schedule"]),
    ("highlights", ["highlight"]),
    ("overview", ["overview", "introduction", "summary", "about-the"]),
    ("best_time", ["best-time", "when-to-go", "when-to-visit"]),
    ("getting_there", ["getting-there", "get-there", "how-to-get", "how-to-arrive"]),
    ("things_to_do", ["things-to-do", "what-to-do", "things-to-see"]),
    ("practical_info", ["practical", "good-to-know", "know-before"]),
    ("pricing", ["pric", "rate", "cost", "departure", "fare"]),
    ("gallery", ["galler", "photo"]),
    ("cabins", ["cabin", "stateroom", "accommodation"]),
    ("ship", ["ship", "yacht", "vessel", "the-boat"]),
    ("faq", ["faq", "frequently-asked", "question"]),
    ("conclusion", ["conclusion", "final-thought", "wrap-up"]),
]


def section_slug(title: str) -> str:
    """Normalize a heading into a canonical slot slug.

    Tries an exact synonym match first, then falls back to keyword detection so
    free-form titles ("Cruise Highlights", "Rates & Departures", "Cabins &
    Accommodation") still land in the right template slot.
    """
    raw = slugify(title or "")
    if raw in SECTION_SYNONYMS:
        return SECTION_SYNONYMS[raw]
    for canonical, needles in _KEYWORD_RULES:
        if any(needle in raw for needle in needles):
            return canonical
    return raw


def to_slug(text: str, max_length: int = 70) -> str:
    return slugify(text or "", max_length=max_length)


def truncate_at_word(text: str, max_len: int) -> str:
    """Trim text to <= max_len without cutting a word in half."""
    text = text.strip()
    if len(text) <= max_len:
        return text
    cut = text[:max_len].rsplit(" ", 1)[0].rstrip(",.;:—- ")
    return cut


def first_sentences(text: str, max_len: int) -> str:
    """Return whole sentences that fit within max_len."""
    text = re.sub(r"\s+", " ", text).strip()
    if len(text) <= max_len:
        return text
    out = ""
    for sentence in re.split(r"(?<=[.!?])\s+", text):
        if len(out) + len(sentence) + 1 > max_len:
            break
        out = (out + " " + sentence).strip()
    return out or truncate_at_word(text, max_len)


def strip_html(html: str) -> str:
    return re.sub(r"<[^>]+>", "", html or "").strip()
