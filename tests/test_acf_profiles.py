"""Tests for per-section ACF mapping profiles."""

from wp_publisher.acf.config import get_acf_config, load_acf_config


def test_default_profile_is_flat():
    cfg = get_acf_config()
    assert cfg.mode == "flat"
    assert cfg.profile is None
    assert cfg.resolved_from_default is False


def test_missing_profile_falls_back_to_default():
    cfg = load_acf_config("does-not-exist")
    # Falls back to the default config (flat) and records that it did.
    assert cfg.mode == "flat"
    assert cfg.resolved_from_default is True


def test_explicit_path_loads_that_file(tmp_path):
    p = tmp_path / "custom.yaml"
    p.write_text("mode: flexible\nflexible_field: my_sections\n", encoding="utf-8")
    cfg = load_acf_config(path=p)
    assert cfg.mode == "flexible"
    assert cfg.flexible_field == "my_sections"
    assert cfg.resolved_from_default is False
