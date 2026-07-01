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
