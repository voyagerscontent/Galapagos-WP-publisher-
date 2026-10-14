"""A thin, robust WordPress REST API client.

Authentication uses an Application Password over HTTP Basic auth (the modern,
recommended approach — it does not expose the account's real password and can
be revoked independently). All calls retry on transient network/5xx errors
with exponential backoff.
"""

from __future__ import annotations

import time
from typing import Any

import requests
from requests.auth import HTTPBasicAuth

from ..config import Settings


class WordPressError(RuntimeError):
    pass


class WordPressClient:
    def __init__(self, settings: Settings, *, timeout: int = 30):
        settings.require_wordpress()
        self.base = settings.wp_base_url
        self.api = f"{self.base}/wp-json/wp/v2"
        self.auth = HTTPBasicAuth(settings.wp_username, settings.wp_app_password)
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({"Accept": "application/json"})

    # -- low-level ---------------------------------------------------------
    def _request(self, method: str, path: str, *, max_retries: int = 4, **kwargs) -> Any:
        url = path if path.startswith("http") else f"{self.api}{path}"
        delay = 2.0
        last_exc: Exception | None = None
        for attempt in range(max_retries):
            try:
                resp = self.session.request(
                    method, url, auth=self.auth, timeout=self.timeout, **kwargs
                )
                if resp.status_code >= 500:
                    raise WordPressError(f"{resp.status_code} server error: {resp.text[:300]}")
                if resp.status_code >= 400:
                    # Client errors are not retried — surface them immediately.
                    raise WordPressError(
                        f"{method} {url} -> {resp.status_code}: {resp.text[:500]}"
                    )
                return resp.json() if resp.content else {}
            except (requests.ConnectionError, requests.Timeout, WordPressError) as exc:
                last_exc = exc
                if isinstance(exc, WordPressError) and "server error" not in str(exc):
                    raise
                if attempt == max_retries - 1:
                    break
                time.sleep(delay)
                delay *= 2
        raise WordPressError(f"Request failed after {max_retries} attempts: {last_exc}")

    # -- connectivity ------------------------------------------------------
    def verify(self) -> dict[str, Any]:
        """Confirm credentials work; returns the authenticated user."""
        return self._request("GET", "/users/me")

    def detect_seo_plugin(self) -> str:
        """Best-effort detection of Yoast / RankMath via REST namespaces."""
        try:
            root = self._request("GET", f"{self.base}/wp-json/")
        except WordPressError:
            return "none"
        namespaces = set(root.get("namespaces", []))
        if any(ns.startswith("yoast") for ns in namespaces):
            return "yoast"
        if any(ns.startswith("rankmath") for ns in namespaces):
            return "rankmath"
        return "none"

    # -- taxonomy ----------------------------------------------------------
    def resolve_term(self, taxonomy: str, name: str) -> int:
        """Find a category/tag by name, creating it if absent. Returns its ID."""
        endpoint = "/categories" if taxonomy == "category" else "/tags"
        found = self._request("GET", endpoint, params={"search": name, "per_page": 100})
        for term in found:
            if term.get("name", "").strip().lower() == name.strip().lower():
                return term["id"]
        created = self._request("POST", endpoint, json={"name": name})
        return created["id"]

    def resolve_terms(self, taxonomy: str, names: list[str]) -> list[int]:
        return [self.resolve_term(taxonomy, n) for n in names if n.strip()]

    # -- media -------------------------------------------------------------
    def search_media(self, query: str, per_page: int = 20) -> list[dict[str, Any]]:
        return self._request(
            "GET", "/media", params={"search": query, "per_page": per_page}
        )

    def get_media(self, media_id: int) -> dict[str, Any]:
        return self._request("GET", f"/media/{media_id}")

    def upload_media(
        self, content: bytes, filename: str, mime_type: str, *, alt: str = "", caption: str = ""
    ) -> dict[str, Any]:
        headers = {
            "Content-Disposition": f'attachment; filename="{filename}"',
            "Content-Type": mime_type,
        }
        item = self._request("POST", "/media", headers=headers, data=content)
        if alt or caption:
            self._request(
                "POST",
                f"/media/{item['id']}",
                json={"alt_text": alt, "caption": caption},
            )
        return item

    # -- posts -------------------------------------------------------------
    def create_post(self, post_type: str, payload: dict[str, Any]) -> dict[str, Any]:
        endpoint = self._post_endpoint(post_type)
        return self._request("POST", endpoint, json=payload)

    def update_post(self, post_type: str, post_id: int, payload: dict[str, Any]) -> dict[str, Any]:
        endpoint = self._post_endpoint(post_type)
        return self._request("POST", f"{endpoint}/{post_id}", json=payload)

    def find_post_by_slug(self, post_type: str, slug: str) -> dict[str, Any] | None:
        endpoint = self._post_endpoint(post_type)
        results = self._request(
            "GET", endpoint, params={"slug": slug, "status": "any", "per_page": 1}
        )
        return results[0] if results else None

    def get_post(self, post_type: str, post_id: int, context: str = "edit") -> dict[str, Any]:
        """Fetch a single post with full field data. ``context=edit`` is what
        makes ACF return every sub-field (repeater images/icons), which the
        list endpoint used by ``find_post_by_slug`` can omit — needed so a
        republish can carry over editor-uploaded media instead of wiping it."""
        endpoint = self._post_endpoint(post_type)
        return self._request("GET", f"{endpoint}/{post_id}", params={"context": context})

    @staticmethod
    def _post_endpoint(post_type: str) -> str:
        if post_type in ("post", "posts"):
            return "/posts"
        if post_type in ("page", "pages"):
            return "/pages"
        return f"/{post_type}"  # custom post type REST base
