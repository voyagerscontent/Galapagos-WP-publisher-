"""Resolve images for a page.

Two strategies, selectable per run (and per the site config default):

* ``library``     — search the WordPress Media Library for the best match and
                    reuse it (no duplicate uploads, respects existing alt text).
* ``placeholder`` — emit a clearly-marked placeholder block for a human to fill
                    before publishing.

A document can override the strategy per image with an explicit URL/media ID.
"""

from __future__ import annotations

import re
from difflib import SequenceMatcher

from ..config import Settings
from ..models import MediaItem
from ..wordpress.client import WordPressClient, WordPressError

_WORD_RE = re.compile(r"[a-z0-9]+")


class MediaResolver:
    def __init__(
        self,
        settings: Settings,
        client: WordPressClient | None,
        strategy: str | None = None,
    ):
        self.settings = settings
        self.client = client
        self.strategy = strategy or settings.media.get("default_strategy", "placeholder")
        self.threshold = float(settings.media.get("library_match_threshold", 0.45))

    def resolve(self, slot: str, query: str, *, alt: str = "", caption: str = "") -> MediaItem:
        """Resolve one image slot into a MediaItem."""
        alt = alt or query
        if self.strategy == "library" and self.client is not None:
            item = self._from_library(slot, query, alt, caption)
            if item is not None:
                return item
        # Fall through to placeholder if library disabled or no good match.
        return MediaItem(
            source="placeholder",
            slot=slot,
            alt=alt,
            caption=caption or query,
        )

    def _from_library(self, slot: str, query: str, alt: str, caption: str) -> MediaItem | None:
        try:
            results = self.client.search_media(query)
        except WordPressError:
            return None
        best: tuple[float, dict] | None = None
        for item in results:
            title = (item.get("title", {}).get("rendered") or "") if isinstance(
                item.get("title"), dict
            ) else (item.get("title") or "")
            alt_text = item.get("alt_text", "") or ""
            score = max(_score(query, title), _score(query, alt_text))
            if best is None or score > best[0]:
                best = (score, item)
        if not best or best[0] < self.threshold:
            return None
        score, item = best
        source_url = item.get("source_url") or ""
        return MediaItem(
            source="library",
            slot=slot,
            wp_media_id=item.get("id"),
            url=source_url,
            alt=item.get("alt_text") or alt,
            caption=caption,
            match_score=round(score, 3),
        )


def _tokens(text: str) -> set[str]:
    return set(_WORD_RE.findall(text.lower()))


def _score(query: str, candidate: str) -> float:
    """Blend token overlap (Jaccard) with sequence similarity, 0..1."""
    if not candidate:
        return 0.0
    qt, ct = _tokens(query), _tokens(candidate)
    jaccard = len(qt & ct) / len(qt | ct) if (qt | ct) else 0.0
    seq = SequenceMatcher(None, query.lower(), candidate.lower()).ratio()
    return 0.6 * jaccard + 0.4 * seq
