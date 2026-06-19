"""Tests for document ingestion and structure parsing."""

from pathlib import Path

from wp_publisher.ingest import read_file
from wp_publisher.models import BlockType
from wp_publisher.utils import section_slug

SAMPLE = Path(__file__).resolve().parents[1] / "samples" / "galapagos-cruise.md"


def test_reads_frontmatter_and_title():
    doc = read_file(SAMPLE)
    assert doc.metadata["type"] == "cruise"
    assert doc.metadata["ship"] == "M/Y Evolution"
    assert "Galapagos Cruise" in doc.title


def test_sections_parsed():
    doc = read_file(SAMPLE)
    slugs = {s.slug for s in doc.sections}
    assert "overview" in slugs
    assert "itinerary" in slugs
    assert "whats_included" in slugs
    assert "faq" in slugs


def test_faq_questions_nested_not_separate_sections():
    doc = read_file(SAMPLE)
    faq = doc.find_section("faq")
    assert faq is not None
    headings = [b for b in faq.blocks if b.type == BlockType.HEADING]
    # The two H3 questions should be nested inside the FAQ section.
    assert len(headings) == 2


def test_ordered_list_detected():
    doc = read_file(SAMPLE)
    itinerary = doc.find_section("itinerary")
    lists = [b for b in itinerary.blocks if b.type == BlockType.LIST]
    assert lists and lists[0].ordered
    assert len(lists[0].items) == 8


def test_section_slug_keyword_mapping():
    assert section_slug("Cruise Highlights") == "highlights"
    assert section_slug("Rates & Departures") == "pricing"
    assert section_slug("Cabins & Accommodation") == "cabins"
    assert section_slug("What's Included") == "whats_included"
    assert section_slug("What's Not Included") == "whats_excluded"
