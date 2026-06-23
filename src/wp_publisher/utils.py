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
    # --- Tier 1 Wildlife sections ---
    "identification": "identification",
    "identification-guide": "identification",
    "how-to-identify": "identification",
    "where-they-live": "range_habitat",
    "range-habitat": "range_habitat",
    "range-and-habitat": "range_habitat",
    "range": "range_habitat",
    "habitat": "range_habitat",
    "behavior": "behavior",
    "behaviour": "behavior",
    "behavior-adaptations": "behavior",
    "behavior-diet-adaptations": "behavior",
    "behaviour-diet-adaptations": "behavior",
    "survival-traits": "behavior",
    "adaptations": "behavior",
    "life-cycle": "life_cycle",
    "lifecycle": "life_cycle",
    "threats-conservation": "conservation",
    "threats-and-conservation": "conservation",
    "conservation": "conservation",
    "threats": "conservation",
    "best-places-to-see-them": "where_to_see",
    "best-places-to-encounter-them": "where_to_see",
    "where-to-see": "where_to_see",
    "where-to-see-them": "where_to_see",
    "related-wildlife": "related",
    "related-species": "related",
    # Tier 2 wildlife phrasings
    "how-to-spot-them": "identification",
    "how-to-spot": "identification",
    "quick-identification": "identification",
    "lifestyle": "behavior",
    "lifestyle-traits": "behavior",
    "traits": "behavior",
    "dynamic-adaptations": "behavior",
    "where-to-see-book": "where_to_see",
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
    # --- Tier 1 Wildlife ---
    ("identification", ["identif", "how-to-id", "how-to-spot", "spot-them"]),
    ("life_cycle", ["life-cycle", "lifecycle", "breeding-cycle"]),
    ("conservation", ["conservation", "threat", "endangered", "protecting"]),
    ("where_to_see", ["where-to-see", "best-place", "encounter", "see-them", "see-book"]),
    ("behavior", ["behavi", "adaptation", "survival-trait", "lifestyle", "dynamic-adapt"]),
    ("range_habitat", ["where-they-live", "habitat", "distribution", "range"]),
    ("related", ["related-wildlife", "related-species"]),
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
