"""Republishing must not wipe editor-uploaded repeater sub-fields (icons/images)."""

from wp_publisher.wordpress.publisher import _preserve_unmanaged_subfields


def test_preserves_uploaded_icon_by_matching_row():
    existing = {
        "acf": {
            "quick_facts": [
                {"label": "Location", "value": "Central Galápagos", "icon": 55},
                {"label": "Area", "value": "986 km²", "icon": 56},
            ]
        }
    }
    payload = {
        "acf": {
            "quick_facts": [
                {"label": "Location", "value": "Central Galápagos"},
                {"label": "Area", "value": "986 km²"},
            ]
        }
    }
    _preserve_unmanaged_subfields(existing, payload)
    assert payload["acf"]["quick_facts"][0]["icon"] == 55
    assert payload["acf"]["quick_facts"][1]["icon"] == 56


def test_preserves_by_label_when_value_changed():
    existing = {"acf": {"quick_facts": [{"label": "Area", "value": "old", "icon": 9}]}}
    payload = {"acf": {"quick_facts": [{"label": "Area", "value": "986 km² (new)"}]}}
    _preserve_unmanaged_subfields(existing, payload)
    assert payload["acf"]["quick_facts"][0]["icon"] == 9


def test_reduces_image_object_to_id():
    existing = {"acf": {"wildlife": [{"common_name": "Penguin", "image": {"id": 42, "url": "x"}}]}}
    payload = {"acf": {"wildlife": [{"common_name": "Penguin", "description": "..."}]}}
    _preserve_unmanaged_subfields(existing, payload)
    assert payload["acf"]["wildlife"][0]["image"] == 42


def test_new_row_without_match_is_untouched():
    existing = {"acf": {"quick_facts": [{"label": "Area", "icon": 9}]}}
    payload = {"acf": {"quick_facts": [{"label": "Population", "value": "1,800"}]}}
    _preserve_unmanaged_subfields(existing, payload)
    assert "icon" not in payload["acf"]["quick_facts"][0]


def test_does_not_overwrite_managed_field():
    existing = {"acf": {"quick_facts": [{"label": "Area", "value": "STALE", "icon": 9}]}}
    payload = {"acf": {"quick_facts": [{"label": "Area", "value": "FRESH"}]}}
    _preserve_unmanaged_subfields(existing, payload)
    assert payload["acf"]["quick_facts"][0]["value"] == "FRESH"  # engine value wins
    assert payload["acf"]["quick_facts"][0]["icon"] == 9         # upload preserved


def test_no_acf_is_safe():
    payload = {"title": "x"}
    _preserve_unmanaged_subfields({"acf": {}}, payload)  # no throw
    assert payload == {"title": "x"}


def test_unreadable_live_acf_drops_repeaters_instead_of_wiping():
    """If the live ACF can't be read, repeaters are removed from the payload
    (left untouched on the page) and a warning is recorded — never wiped."""
    warnings: list[str] = []
    payload = {"acf": {
        "quick_facts": [{"label": "Area", "value": "986 km²"}],
        "feature_sections": [{"title": "Wildlife", "content": "..."}],
        "geo_answer": "a scalar field is still updated",
    }}
    _preserve_unmanaged_subfields({"acf": {}}, payload, warnings)
    assert "quick_facts" not in payload["acf"]      # repeater left untouched
    assert "feature_sections" not in payload["acf"]
    assert payload["acf"]["geo_answer"] == "a scalar field is still updated"
    assert warnings and "quick_facts" in warnings[0]
