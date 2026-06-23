"""Specialized renderer for the content-intensive Tier 1 Wildlife template.

Unlike the generic slot engine, this builds a fixed, designed layout (hero,
sticky TOC + quick facts, overview, identification, range/habitat, behavior
cards, life-cycle steps, conservation stats, "where to see" cards, accordion
FAQs, related-species strip, booking CTA) from a species document.

Authoring model: the writer supplies a metadata header (scientific name, chips,
quick facts, CTAs, related species, population numbers) plus normal H2 sections.
H3 sub-headings inside a section become the individual cards/steps. Everything
is emitted as native Gutenberg blocks with inline styling so it renders on any
theme; the optional `assets/wildlife.css` refines the look.
"""

from __future__ import annotations

import html

from ..config import Settings
from ..media.resolver import MediaResolver
from ..models import BlockType, ContentBlock, Document, MediaItem, Section
from . import blocks as B
from .template import PageTemplate

# Section slug -> (anchor, table-of-contents label). Order = page order.
SECTIONS = [
    ("overview", "overview", "Overview"),
    ("identification", "identification", "Identification Guide"),
    ("range_habitat", "range-habitat", "Where They Live"),
    ("behavior", "behavior", "Behavior & Adaptations"),
    ("life_cycle", "life-cycle", "Life Cycle"),
    ("conservation", "conservation", "Threats & Conservation"),
    ("where_to_see", "where-to-see", "Best Places to See Them"),
    ("faq", "faqs", "Traveler FAQs"),
]

# Inline style fragments (kept terse; assets/wildlife.css can override).
_CARD = (
    "border:1px solid #e3e6e3;border-radius:12px;padding:1.25rem;"
    "background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,.06);height:100%"
)
_CALLOUT = (
    "border-left:4px solid #1f7a4d;background:#f3f8f5;padding:1rem 1.25rem;"
    "border-radius:8px"
)
_HERO = (
    "background:linear-gradient(135deg,#0f3d2e,#1f7a4d);color:#fff;"
    "padding:3rem 2rem;border-radius:14px;text-align:center"
)
_CHIP = (
    "display:inline-block;padding:.3rem .8rem;margin:.2rem;border-radius:999px;"
    "background:rgba(255,255,255,.18);color:#fff;font-size:.85rem;font-weight:600"
)
_CHIP_LIGHT = (
    "display:inline-block;padding:.3rem .8rem;margin:.2rem;border-radius:999px;"
    "background:#eef4f0;color:#1f7a4d;font-size:.85rem;font-weight:600;"
    "border:1px solid #d6e4dc"
)
_HERO_LIGHT = (
    "background:#f6f9f7;border:1px solid #e3e6e3;border-bottom:4px solid #1f7a4d;"
    "color:#15241d;padding:2.5rem 2rem;border-radius:14px;text-align:center"
)
_STAT = (
    "border:1px solid #e3e6e3;border-radius:12px;padding:1.25rem;"
    "background:#fbfdfb;text-align:center"
)


class WildlifeRenderer:
    """Builds the Tier 1 Wildlife page. Same return contract as RenderEngine."""

    def __init__(self, settings: Settings, media: MediaResolver):
        self.settings = settings
        self.media = media
        self.media_items: list[MediaItem] = []

    # ------------------------------------------------------------------ #
    def render(
        self, doc: Document, template: PageTemplate
    ) -> tuple[str, list[MediaItem], MediaItem | None, list[str]]:
        warnings: list[str] = []
        self.media_items = []
        meta = doc.metadata

        # Advisory: this template is for high-search-volume species.
        threshold = template.min_search_volume
        if threshold:
            vol = _to_int(meta.get("search_volume"))
            if vol is None:
                warnings.append(
                    f"No 'search_volume' given; the Tier 1 Wildlife template is "
                    f"intended for species with {threshold}+ monthly searches."
                )
            elif vol < threshold:
                warnings.append(
                    f"search_volume ({vol}) is below the Tier 1 threshold "
                    f"({threshold}); consider a lighter wildlife template."
                )

        # Required sections present?
        for slug in template.required_sections:
            if not self._section_present(doc, slug):
                warnings.append(f"Required section '{slug}' is missing from the document.")

        hero_html, featured = self._hero(doc)
        parts = [
            hero_html,
            self._facts_and_toc(doc),
            self._overview(doc),
            self._identification(doc),
            self._range_habitat(doc),
            self._behavior(doc),
            self._life_cycle(doc),
            self._conservation(doc),
            self._where_to_see(doc),
            self._faqs(doc),
            self._related(doc),
            self._booking_cta(doc),
        ]
        content = "\n\n".join(p for p in parts if p)
        return content, self.media_items, featured, warnings

    # ---- HERO --------------------------------------------------------- #
    def _hero(self, doc: Document) -> tuple[str, MediaItem | None]:
        meta = doc.metadata
        query = str(meta.get("hero_image_query") or meta.get("featured_image_query") or doc.title)
        featured = self.media.resolve("featured", query, alt=doc.title)

        buttons = self._ctas(
            doc,
            default_primary=("See This Species", "#where-to-see"),
            default_secondary=("Plan a Trip", "/contact"),
        )
        btn_html = B.buttons_block(buttons, class_name="gwp-w-hero-cta")
        # Center the buttons.
        btn_html = btn_html.replace(
            'class="wp-block-buttons gwp-w-hero-cta"',
            'class="wp-block-buttons gwp-w-hero-cta" style="justify-content:center;margin-top:1.5rem"',
        )

        bg = ""
        if featured.source == "library" and featured.url:
            bg = (
                f"background:linear-gradient(rgba(15,61,46,.55),rgba(31,122,77,.55)),"
                f"url('{html.escape(featured.url)}');background-size:cover;"
                f"background-position:center;color:#fff;padding:4rem 2rem;"
                f"border-radius:14px;text-align:center"
            )
        style = bg or _HERO

        inner = self._hero_inner(doc)
        hero_div = (
            '<!-- wp:group {"className":"gwp-w-hero","layout":{"type":"constrained"}} -->\n'
            f'<div class="wp-block-group gwp-w-hero" style="{style}">\n'
            f"{B.raw_html_block(inner)}\n{btn_html}\n</div>\n"
            "<!-- /wp:group -->"
        )
        return hero_div, featured

    def _hero_inner(self, doc: Document, *, chip_on_dark: bool = True) -> str:
        """Breadcrumbs + name + scientific name + tagline + chips (shared)."""
        meta = doc.metadata
        crumbs = _as_list(meta.get("breadcrumbs"))
        crumb_html = ""
        if crumbs:
            crumb_html = (
                '<p style="opacity:.85;font-size:.85rem;margin:0 0 .75rem">'
                + " &rsaquo; ".join(html.escape(str(c)) for c in crumbs)
                + "</p>"
            )
        name_html = (
            '<p class="gwp-w-hero-name" style="font-size:2.4rem;font-weight:800;'
            'letter-spacing:.04em;margin:0;text-transform:uppercase">'
            f"{html.escape(doc.title)}</p>"
        )
        sci = meta.get("scientific_name")
        sci_html = (
            f'<p style="font-style:italic;opacity:.9;margin:.25rem 0 0">{html.escape(str(sci))}</p>'
            if sci
            else ""
        )
        tagline = meta.get("tagline") or meta.get("subtitle")
        tag_html = (
            f'<p style="font-size:1.1rem;max-width:46rem;margin:1rem auto 0">'
            f"&ldquo;{html.escape(str(tagline))}&rdquo;</p>"
            if tagline
            else ""
        )
        chip_style = _CHIP if chip_on_dark else _CHIP_LIGHT
        chips = self._chip_values(doc)
        chip_html = ""
        if chips:
            spans = "".join(f'<span style="{chip_style}">{html.escape(c)}</span>' for c in chips)
            chip_html = f'<p style="margin:1.25rem 0 0">{spans}</p>'
        return crumb_html + name_html + sci_html + tag_html + chip_html

    def _chip_values(self, doc: Document) -> list[str]:
        meta = doc.metadata
        chips = _as_list(meta.get("chips"))
        if chips:
            return [str(c) for c in chips]
        derived: list[str] = []
        if meta.get("conservation_status"):
            derived.append(str(meta["conservation_status"]))
        if _truthy(meta.get("endemic")):
            derived.append("Endemic")
        if meta.get("location"):
            derived.append(str(meta["location"]))
        if meta.get("best_season") or meta.get("best_time"):
            derived.append(str(meta.get("best_season") or meta.get("best_time")))
        return derived

    def _ctas(self, doc, *, default_primary, default_secondary) -> list[tuple[str, str]]:
        meta = doc.metadata
        primary = (
            str(meta.get("cta_primary_label") or default_primary[0]),
            str(meta.get("cta_primary_url") or default_primary[1]),
        )
        secondary = (
            str(meta.get("cta_secondary_label") or default_secondary[0]),
            str(meta.get("cta_secondary_url") or default_secondary[1]),
        )
        return [primary, secondary]

    # ---- QUICK FACTS + TOC ------------------------------------------- #
    def _facts_and_toc(self, doc: Document) -> str:
        # TOC of the sections that actually exist.
        links = []
        for slug, anchor, label in SECTIONS:
            if doc.find_section(slug) is not None:
                links.append(
                    f'<!-- wp:list-item -->\n<li><a href="#{anchor}">{html.escape(label)}</a></li>\n'
                    "<!-- /wp:list-item -->"
                )
        if not links:
            return ""
        toc_inner = (
            '<!-- wp:heading {"level":3} -->\n<h3>On this page</h3>\n<!-- /wp:heading -->\n'
            '<!-- wp:list -->\n<ul>\n' + "\n".join(links) + "\n</ul>\n<!-- /wp:list -->"
        )
        toc_col = B.group_block(toc_inner, class_name="gwp-w-toc", style="position:sticky;top:1rem")

        facts = _as_dict(doc.metadata.get("facts"))
        if facts:
            rows = [["Quick Facts", ""]] + [[k, str(v)] for k, v in facts.items()]
            facts_col = B.table_block(rows)
        else:
            facts_col = B.placeholder_block(
                "📋 QUICK FACTS NEEDED",
                hint="Add a 'facts:' map (Size & Weight, Lifespan, Diet, Habitat) to the doc header.",
            )
        return B.columns_block([toc_col, facts_col], class_name="gwp-w-facts")

    # ---- OVERVIEW ----------------------------------------------------- #
    def _overview(self, doc: Document) -> str:
        section = self._overview_section(doc)
        if section is None:
            return ""
        prose, callout = self._split_callout(section)
        text_html = (
            B.heading_anchor_block(section.title or "Overview", "overview")
            + "\n\n"
            + self._blocks_to_html(prose)
        )
        if callout:
            text_html += "\n\n" + self._callout(callout)
        img_html, _ = self._image(
            doc.metadata.get("overview_image_query") or doc.title,
            alt=f"{doc.title} in the wild",
            hint="Emotional in-situ wildlife photo",
        )
        return B.columns_block([text_html, img_html], class_name="gwp-w-overview")

    # ---- IDENTIFICATION ---------------------------------------------- #
    def _identification(self, doc: Document) -> str:
        section = doc.find_section("identification")
        if section is None:
            return ""
        annotated = B.placeholder_block(
            "🔍 ANNOTATED ID IMAGE",
            hint=str(doc.metadata.get("id_image_hint", "Label key features with pointers")),
        )
        body = self._blocks_to_html(section.blocks)
        inner = (
            B.heading_anchor_block(section.title or "How to Identify", "identification")
            + "\n\n"
            + annotated
            + "\n\n"
            + body
        )
        return B.group_block(inner, class_name="gwp-w-identification")

    # ---- RANGE & HABITAT --------------------------------------------- #
    def _range_habitat(self, doc: Document) -> str:
        section = doc.find_section("range_habitat")
        if section is None:
            return ""
        prose_blocks, access = self._extract_prefixed(section, "accessibility")
        map_ph = B.placeholder_block(
            "🗺️ DISTRIBUTION MAP",
            hint=str(doc.metadata.get("map_hint", "Interactive range / distribution map")),
        )
        if access:
            map_ph += "\n\n" + B.paragraph_block(
                "<em>Map summary: " + html.escape(access) + "</em>"
            )
        text_html = (
            B.heading_anchor_block(section.title or "Where They Live", "range-habitat")
            + "\n\n"
            + self._blocks_to_html(prose_blocks)
        )
        return B.columns_block([map_ph, text_html], class_name="gwp-w-range")

    # ---- BEHAVIOR CARDS ---------------------------------------------- #
    def _behavior(self, doc: Document) -> str:
        section = doc.find_section("behavior")
        if section is None:
            return ""
        lead, groups = self._split_by_subheadings(section)
        head = B.heading_anchor_block(section.title or "Behavior & Adaptations", "behavior")
        intro = self._blocks_to_html(lead) if lead else ""
        if groups:
            cards = [self._card(title, body) for title, body in groups]
            grid = self._grid(cards, per_row=2, class_name="gwp-w-behavior")
        else:
            grid = self._blocks_to_html(section.blocks)
        return "\n\n".join(p for p in [head, intro, grid] if p)

    # ---- LIFE CYCLE STEPS -------------------------------------------- #
    def _life_cycle(self, doc: Document) -> str:
        section = doc.find_section("life_cycle")
        if section is None:
            return ""
        lead, groups = self._split_by_subheadings(section)
        head = B.heading_anchor_block(section.title or "Life Cycle", "life-cycle")
        intro = self._blocks_to_html(lead) if lead else ""
        steps = []
        for i, (title, body) in enumerate(groups, start=1):
            img, _ = self._image(
                f"{doc.title} {title}", alt=title, hint=f"Phase {i}: {title}"
            )
            inner = (
                f'<p style="font-weight:700;color:#1f7a4d;margin:0 0 .25rem">PHASE {i}</p>'
            )
            step_html = (
                B.raw_html_block(inner)
                + "\n\n"
                + B.heading_block(title, level=3)
                + "\n\n"
                + img
                + "\n\n"
                + self._blocks_to_html(body)
            )
            steps.append(B.group_block(step_html, class_name="gwp-w-step", style=_CARD))
        grid = self._grid(steps, per_row=3, class_name="gwp-w-lifecycle") if steps else ""
        return "\n\n".join(p for p in [head, intro, grid] if p)

    # ---- THREATS & CONSERVATION -------------------------------------- #
    def _conservation(self, doc: Document) -> str:
        section = doc.find_section("conservation")
        if section is None:
            return ""
        meta = doc.metadata
        stat_cards = []
        if meta.get("population_current"):
            stat_cards.append(
                f'<div style="{_STAT}"><p style="font-size:1.6rem;font-weight:800;'
                f'margin:0;color:#1f7a4d">{html.escape(str(meta["population_current"]))}</p>'
                '<p style="margin:.25rem 0 0">Current wild population</p></div>'
            )
        if meta.get("population_historical"):
            stat_cards.append(
                f'<div style="{_STAT}"><p style="font-size:1.6rem;font-weight:800;'
                f'margin:0;color:#7a1f1f">{html.escape(str(meta["population_historical"]))}</p>'
                '<p style="margin:.25rem 0 0">Historical baseline</p></div>'
            )
        graphic = (
            B.raw_html_block(
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">'
                + "".join(stat_cards)
                + "</div>"
            )
            if stat_cards
            else B.placeholder_block("📊 STATUS GRAPHIC", hint="Population trend graphic")
        )
        text_html = (
            B.heading_anchor_block(section.title or "Threats & Conservation", "conservation")
            + "\n\n"
            + self._blocks_to_html(section.blocks)
        )
        return B.columns_block([graphic, text_html], class_name="gwp-w-conservation")

    # ---- WHERE TO SEE CARDS ------------------------------------------ #
    def _where_to_see(self, doc: Document) -> str:
        section = doc.find_section("where_to_see")
        if section is None:
            return ""
        lead, groups = self._split_by_subheadings(section)
        head = B.heading_anchor_block(section.title or "Best Places to See Them", "where-to-see")
        intro = self._blocks_to_html(lead) if lead else ""
        cards = []
        for title, body in groups:
            img, _ = self._image(f"{doc.title} {title}", alt=title, hint=title)
            bullets, cta = self._extract_cta(body)
            card_inner = (
                img
                + "\n\n"
                + B.heading_block(title, level=3)
                + "\n\n"
                + self._blocks_to_html(bullets)
            )
            if cta:
                card_inner += "\n\n" + B.buttons_block([cta])
            cards.append(B.group_block(card_inner, class_name="gwp-w-place", style=_CARD))
        grid = self._grid(cards, per_row=3, class_name="gwp-w-places") if cards else ""
        return "\n\n".join(p for p in [head, intro, grid] if p)

    # ---- FAQ ACCORDION ------------------------------------------------ #
    def _faqs(self, doc: Document) -> str:
        section = doc.find_section("faq")
        if section is None:
            return ""
        head = B.heading_anchor_block(section.title or "Frequently Asked Questions", "faqs")
        items = []
        question = None
        answer: list[ContentBlock] = []
        for block in section.blocks:
            if block.type == BlockType.HEADING:
                if question is not None:
                    items.append(B.details_block(question, self._blocks_to_html(answer)))
                question = block.text
                answer = []
            else:
                answer.append(block)
        if question is not None:
            items.append(B.details_block(question, self._blocks_to_html(answer)))
        if not items:
            return ""
        return head + "\n\n" + "\n\n".join(items)

    # ---- RELATED WILDLIFE -------------------------------------------- #
    def _related(self, doc: Document) -> str:
        related = _as_list(doc.metadata.get("related"))
        if not related:
            return ""
        cards = []
        for item in related:
            name = item.get("name", "") if isinstance(item, dict) else str(item)
            note = item.get("note", "") if isinstance(item, dict) else ""
            thumb = B.placeholder_block("🖼️", hint=name)
            inner = (
                thumb
                + "\n\n"
                + B.raw_html_block(
                    f'<p style="font-weight:700;margin:.5rem 0 .25rem">{html.escape(str(name))}</p>'
                    f'<p style="margin:0;font-size:.9rem;opacity:.85">{html.escape(str(note))}</p>'
                )
            )
            cards.append(B.group_block(inner, class_name="gwp-w-related-card", style=_CARD))
        head = B.heading_block("Related Wildlife", level=2)
        return head + "\n\n" + self._grid(cards, per_row=4, class_name="gwp-w-related")

    # ---- FINAL BOOKING CTA ------------------------------------------- #
    def _booking_cta(self, doc: Document) -> str:
        meta = doc.metadata
        heading = str(meta.get("booking_heading", "Ready to See This Species in the Wild?"))
        blurb = str(
            meta.get(
                "booking_blurb",
                "Our expert-led expeditions bring you face-to-face with the Galapagos' most iconic wildlife.",
            )
        )
        buttons = self._ctas(
            doc,
            default_primary=("Find Trips Featuring This Species", "#where-to-see"),
            default_secondary=("Talk to an Expert", "/contact"),
        )
        btn_html = B.buttons_block(buttons).replace(
            'class="wp-block-buttons"',
            'class="wp-block-buttons" style="justify-content:center"',
        )
        trust = meta.get("rating")
        trust_html = (
            f'<p style="margin:1rem 0 0;font-weight:600">⭐ {html.escape(str(trust))}</p>'
            if trust
            else ""
        )
        inner = (
            B.raw_html_block(
                f'<h2 style="margin:0 0 .5rem">{html.escape(heading)}</h2>'
                f'<p style="max-width:42rem;margin:0 auto">{html.escape(blurb)}</p>'
            )
            + "\n\n"
            + btn_html
            + ("\n\n" + B.raw_html_block(trust_html) if trust_html else "")
        )
        style = (
            "background:#0f3d2e;color:#fff;padding:3rem 2rem;border-radius:14px;text-align:center"
        )
        return (
            '<!-- wp:group {"className":"gwp-w-booking","layout":{"type":"constrained"}} -->\n'
            f'<div class="wp-block-group gwp-w-booking" style="{style}">\n{inner}\n</div>\n'
            "<!-- /wp:group -->"
        )

    # ================================================================== #
    # Helpers
    # ================================================================== #
    def _image(self, query, *, alt: str, hint: str) -> tuple[str, MediaItem]:
        item = self.media.resolve("inline", str(query), alt=alt)
        self.media_items.append(item)
        if item.source == "library" and item.url:
            return (
                B.image_block(item.url, alt=item.alt, caption="", media_id=item.wp_media_id),
                item,
            )
        return B.placeholder_block("📷 IMAGE NEEDED", hint=hint), item

    def _card(self, title: str, body: list[ContentBlock]) -> str:
        inner = B.heading_block(title, level=3) + "\n\n" + self._blocks_to_html(body)
        return B.group_block(inner, class_name="gwp-w-card", style=_CARD)

    def _callout(self, text: str) -> str:
        return B.raw_html_block(
            f'<div class="gwp-w-callout" style="{_CALLOUT}"><strong>Did You Know?</strong> '
            f"{html.escape(text)}</div>"
        )

    # Sections "owned" by other wildlife slots; the overview fallback skips them.
    _KNOWN_SLUGS = {
        "identification",
        "range_habitat",
        "behavior",
        "life_cycle",
        "conservation",
        "where_to_see",
        "faq",
        "related",
        "gallery",
        "_lead",
    }

    def _overview_section(self, doc: Document) -> Section | None:
        """Find the overview, tolerating a creatively-titled heading.

        Tries the canonical 'overview'/lead first, then the first section that
        isn't claimed by another wildlife slot (e.g. "A Splash of Color...").
        """
        sec = doc.find_section("overview", "_lead")
        if sec is not None and sec.blocks:
            return sec
        for s in doc.sections:
            if s.slug not in self._KNOWN_SLUGS and s.blocks:
                return s
        return sec

    def _section_present(self, doc: Document, slug: str) -> bool:
        if slug == "overview":
            return self._overview_section(doc) is not None
        return doc.find_section(slug) is not None

    def _grid(self, cards: list[str], *, per_row: int, class_name: str) -> str:
        rows = []
        for i in range(0, len(cards), per_row):
            rows.append(B.columns_block(cards[i : i + per_row], class_name=class_name))
        return "\n\n".join(rows)

    def _blocks_to_html(self, blocks: list[ContentBlock]) -> str:
        out: list[str] = []
        for b in blocks:
            if b.type == BlockType.HEADING:
                out.append(B.heading_block(b.text, level=max(3, b.level)))
            elif b.type == BlockType.PARAGRAPH and b.text:
                out.append(B.paragraph_block(html.escape(b.text)))
            elif b.type == BlockType.LIST and b.items:
                out.append(B.list_block(b.items, ordered=b.ordered))
            elif b.type == BlockType.QUOTE and b.text:
                out.append(B.quote_block(b.text))
            elif b.type == BlockType.TABLE and b.rows:
                out.append(B.table_block(b.rows))
        return "\n\n".join(out)

    @staticmethod
    def _split_by_subheadings(
        section: Section,
    ) -> tuple[list[ContentBlock], list[tuple[str, list[ContentBlock]]]]:
        lead: list[ContentBlock] = []
        groups: list[tuple[str, list[ContentBlock]]] = []
        title: str | None = None
        current: list[ContentBlock] = []
        for b in section.blocks:
            if b.type == BlockType.HEADING and b.level >= 3:
                if title is not None:
                    groups.append((title, current))
                title = b.text
                current = []
            elif title is None:
                lead.append(b)
            else:
                current.append(b)
        if title is not None:
            groups.append((title, current))
        return lead, groups

    @staticmethod
    def _split_callout(section: Section) -> tuple[list[ContentBlock], str | None]:
        """Pull a 'Did You Know' paragraph or a quote out as a callout."""
        prose: list[ContentBlock] = []
        callout: str | None = None
        for b in section.blocks:
            if callout is None and b.type == BlockType.QUOTE and b.text:
                callout = b.text
            elif (
                callout is None
                and b.type == BlockType.PARAGRAPH
                and b.text.lower().startswith("did you know")
            ):
                callout = b.text.split(":", 1)[-1].strip() if ":" in b.text else b.text
            else:
                prose.append(b)
        return prose, callout

    @staticmethod
    def _extract_prefixed(
        section: Section, prefix: str
    ) -> tuple[list[ContentBlock], str | None]:
        """Remove a 'Prefix: ...' paragraph and return it separately."""
        kept: list[ContentBlock] = []
        found: str | None = None
        for b in section.blocks:
            if (
                found is None
                and b.type == BlockType.PARAGRAPH
                and b.text.lower().startswith(prefix.lower())
            ):
                found = b.text.split(":", 1)[-1].strip() if ":" in b.text else b.text
            else:
                kept.append(b)
        return kept, found

    @staticmethod
    def _extract_cta(
        body: list[ContentBlock],
    ) -> tuple[list[ContentBlock], tuple[str, str] | None]:
        """Pull a 'CTA: Label | URL' list item or paragraph out as a button."""
        kept: list[ContentBlock] = []
        cta: tuple[str, str] | None = None
        for b in body:
            if b.type == BlockType.LIST and b.items:
                remaining = []
                for it in b.items:
                    if cta is None and it.lower().startswith("cta:"):
                        cta = _parse_cta(it[4:])
                    else:
                        remaining.append(it)
                if remaining:
                    kept.append(ContentBlock(type=BlockType.LIST, items=remaining, ordered=b.ordered))
            elif (
                cta is None
                and b.type == BlockType.PARAGRAPH
                and b.text.lower().startswith("cta:")
            ):
                cta = _parse_cta(b.text[4:])
            else:
                kept.append(b)
        return kept, cta


class WildlifeTier2Renderer(WildlifeRenderer):
    """Lighter, compact species page for lower-search-volume wildlife (<500/mo).

    Reuses all of the Tier 1 helpers; only the page composition differs: a
    minimal hero, a 40/60 quick-facts + identification split, a 60/40 overview,
    stacked lifestyle traits, tour cards, a mini FAQ, and a regional footer CTA.
    """

    def render(
        self, doc: Document, template: PageTemplate
    ) -> tuple[str, list[MediaItem], MediaItem | None, list[str]]:
        warnings: list[str] = []
        self.media_items = []
        meta = doc.metadata

        cap = template.max_search_volume
        if cap:
            vol = _to_int(meta.get("search_volume"))
            if vol is not None and vol > cap:
                warnings.append(
                    f"search_volume ({vol}) exceeds the Tier 2 cap ({cap}); "
                    f"consider the richer wildlife_tier1 template."
                )

        for slug in template.required_sections:
            if not self._section_present(doc, slug):
                warnings.append(f"Required section '{slug}' is missing from the document.")

        hero_html, featured = self._hero_minimal(doc)
        parts = [
            hero_html,
            self._facts_and_id(doc),
            self._overview_habitat(doc),
            self._lifestyle(doc),
            self._where_to_book(doc),
            self._faqs(doc),
            self._footer_cta(doc),
        ]
        content = "\n\n".join(p for p in parts if p)
        return content, self.media_items, featured, warnings

    def _hero_minimal(self, doc: Document) -> tuple[str, MediaItem | None]:
        meta = doc.metadata
        query = str(meta.get("hero_image_query") or meta.get("featured_image_query") or doc.title)
        featured = self.media.resolve("featured", query, alt=doc.title)
        primary = (
            str(meta.get("cta_primary_label") or "Find Tours Checking This Region"),
            str(meta.get("cta_primary_url") or "#where-to-see"),
        )
        btn_html = B.buttons_block([primary], class_name="gwp-w-hero-cta").replace(
            'class="wp-block-buttons gwp-w-hero-cta"',
            'class="wp-block-buttons gwp-w-hero-cta" style="justify-content:center;margin-top:1.25rem"',
        )
        inner = self._hero_inner(doc, chip_on_dark=False)
        return (
            '<!-- wp:group {"className":"gwp-w-hero gwp-w-hero-light","layout":{"type":"constrained"}} -->\n'
            f'<div class="wp-block-group gwp-w-hero gwp-w-hero-light" style="{_HERO_LIGHT}">\n'
            f"{B.raw_html_block(inner)}\n{btn_html}\n</div>\n"
            "<!-- /wp:group -->",
            featured,
        )

    def _facts_and_id(self, doc: Document) -> str:
        facts = _as_dict(doc.metadata.get("facts"))
        if facts:
            facts_col = B.table_block([[k, str(v)] for k, v in facts.items()], has_header=False)
        else:
            facts_col = B.placeholder_block(
                "📋 QUICK FACTS NEEDED", hint="Add a 'facts:' map (Size, Diet, Habitat, Predators)."
            )
        ident = doc.find_section("identification")
        if ident is not None:
            id_col = (
                B.heading_anchor_block(ident.title or "How to Spot Them", "identification")
                + "\n\n"
                + self._blocks_to_html(ident.blocks)
            )
        else:
            id_col = B.placeholder_block(
                "🔍 IDENTIFICATION NEEDED", hint="Add a 'How to Spot Them' section."
            )
        return B.columns_block(
            [facts_col, id_col], class_name="gwp-w-facts", widths=["40%", "60%"]
        )

    def _overview_habitat(self, doc: Document) -> str:
        section = self._overview_section(doc)
        if section is None:
            return ""
        text_html = (
            B.heading_anchor_block(section.title or "Overview & Habitat", "overview")
            + "\n\n"
            + self._blocks_to_html(section.blocks)
        )
        img_html, _ = self._image(
            doc.metadata.get("overview_image_query") or doc.title,
            alt=str(doc.title),
            hint="Supporting visual",
        )
        return B.columns_block(
            [text_html, img_html], class_name="gwp-w-overview", widths=["60%", "40%"]
        )

    def _lifestyle(self, doc: Document) -> str:
        section = doc.find_section("behavior")
        if section is None:
            return ""
        head = B.heading_anchor_block(section.title or "Lifestyle & Traits", "behavior")
        return head + "\n\n" + self._blocks_to_html(section.blocks)

    def _where_to_book(self, doc: Document) -> str:
        section = doc.find_section("where_to_see")
        if section is None:
            return ""
        lead, groups = self._split_by_subheadings(section)
        head = B.heading_anchor_block(section.title or "Where to See & Book", "where-to-see")
        intro = self._blocks_to_html(lead) if lead else ""
        cards = []
        for title, body in groups:
            bullets, cta = self._extract_cta(body)
            inner = B.heading_block(title, level=3) + "\n\n" + self._blocks_to_html(bullets)
            if cta:
                inner += "\n\n" + B.buttons_block([cta])
            cards.append(B.group_block(inner, class_name="gwp-w-tour", style=_CARD))
        grid = self._grid(cards, per_row=2, class_name="gwp-w-tours") if cards else ""
        return "\n\n".join(p for p in [head, intro, grid] if p)

    def _footer_cta(self, doc: Document) -> str:
        meta = doc.metadata
        heading = str(meta.get("footer_heading", "Explore all Galapagos Island Expeditions"))
        blurb = str(
            meta.get(
                "footer_blurb",
                "Discover hundreds of unique island species with our resident marine naturalists.",
            )
        )
        primary = (
            str(meta.get("footer_cta_primary_label", "Download Destination Guide")),
            str(meta.get("footer_cta_primary_url", "/guide")),
        )
        secondary = (
            str(meta.get("footer_cta_secondary_label", "Contact Trip Planner")),
            str(meta.get("footer_cta_secondary_url", "/contact")),
        )
        btn_html = B.buttons_block([primary, secondary]).replace(
            'class="wp-block-buttons"',
            'class="wp-block-buttons" style="justify-content:center"',
        )
        inner = (
            B.raw_html_block(
                f'<h3 style="margin:0 0 .5rem">{html.escape(heading)}</h3>'
                f'<p style="max-width:40rem;margin:0 auto">{html.escape(blurb)}</p>'
            )
            + "\n\n"
            + btn_html
        )
        style = (
            "background:#f6f9f7;border:1px solid #e3e6e3;padding:2rem;"
            "border-radius:14px;text-align:center"
        )
        return (
            '<!-- wp:group {"className":"gwp-w-footer-cta","layout":{"type":"constrained"}} -->\n'
            f'<div class="wp-block-group gwp-w-footer-cta" style="{style}">\n{inner}\n</div>\n'
            "<!-- /wp:group -->"
        )


# --------------------------------------------------------------------------- #
def _parse_cta(raw: str) -> tuple[str, str]:
    if "|" in raw:
        label, url = raw.split("|", 1)
        return label.strip(), url.strip()
    return raw.strip(), "#where-to-see"


def _as_list(value) -> list:
    if value is None:
        return []
    if isinstance(value, list):
        return value
    return [v.strip() for v in str(value).split(",") if v.strip()]


def _as_dict(value) -> dict:
    return value if isinstance(value, dict) else {}


def _truthy(value) -> bool:
    return str(value).strip().lower() in {"true", "yes", "1", "y"}


def _to_int(value) -> int | None:
    if value is None:
        return None
    digits = "".join(ch for ch in str(value) if ch.isdigit())
    return int(digits) if digits else None
