"""Configuration loading: merges site.yaml with environment variables."""

from __future__ import annotations

import os
from functools import lru_cache
from pathlib import Path
from typing import Any

import yaml
from dotenv import load_dotenv

PROJECT_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_SITE_CONFIG = PROJECT_ROOT / "config" / "site.yaml"
TEMPLATES_DIR = PROJECT_ROOT / "templates"


class Settings:
    """Resolved runtime settings.

    Site-level, mostly-static values live in `config/site.yaml`. Secrets and
    deployment-specific values come from the environment (`.env`).
    """

    def __init__(self, site_config_path: Path | None = None) -> None:
        load_dotenv(PROJECT_ROOT / ".env")
        path = site_config_path or DEFAULT_SITE_CONFIG
        self.site: dict[str, Any] = {}
        if path.exists():
            self.site = yaml.safe_load(path.read_text(encoding="utf-8")) or {}

        # WordPress
        self.wp_base_url = (os.getenv("WP_BASE_URL", "") or "").rstrip("/")
        self.wp_username = os.getenv("WP_USERNAME", "")
        self.wp_app_password = os.getenv("WP_APP_PASSWORD", "")
        self.wp_default_status = os.getenv(
            "WP_DEFAULT_STATUS", self.site.get("defaults", {}).get("status", "draft")
        )
        author = os.getenv("WP_DEFAULT_AUTHOR_ID")
        self.wp_default_author_id = int(author) if author else None

        # Google Drive
        self.gdrive_credentials_file = os.getenv("GDRIVE_CREDENTIALS_FILE", "credentials.json")
        self.gdrive_token_file = os.getenv("GDRIVE_TOKEN_FILE", "token.json")
        self.gdrive_allowed_account = os.getenv(
            "GDRIVE_ALLOWED_ACCOUNT", "businessops@latintrails.com"
        )

    # Convenience accessors -------------------------------------------------
    @property
    def organization(self) -> dict[str, Any]:
        return self.site.get("organization", {})

    @property
    def seo(self) -> dict[str, Any]:
        return self.site.get("seo", {})

    @property
    def media(self) -> dict[str, Any]:
        return self.site.get("media", {})

    @property
    def locale(self) -> dict[str, Any]:
        return self.site.get("locale", {})

    @property
    def defaults(self) -> dict[str, Any]:
        return self.site.get("defaults", {})

    def require_wordpress(self) -> None:
        missing = [
            name
            for name, val in (
                ("WP_BASE_URL", self.wp_base_url),
                ("WP_USERNAME", self.wp_username),
                ("WP_APP_PASSWORD", self.wp_app_password),
            )
            if not val
        ]
        if missing:
            raise RuntimeError(
                "Missing WordPress credentials: "
                + ", ".join(missing)
                + ". Copy .env.example to .env and fill them in."
            )


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()
