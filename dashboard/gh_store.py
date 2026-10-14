"""The GitHub repo as durable document storage (the dashboard's "memory").

Uploaded documents are committed to `content/…` so they survive the host's
ephemeral disk: republish reads them back without re-uploading, and Git keeps
every version for comparison. Needs a token with repo write access.

Env: GITHUB_TOKEN, GITHUB_REPO ("owner/name"), GITHUB_BRANCH (default "main").
"""

from __future__ import annotations

import base64
import os

import requests

_API = "https://api.github.com"
_DOC_EXTS = (".html", ".htm", ".docx")


def _cfg() -> tuple[str, str, str]:
    return (
        os.environ.get("GITHUB_TOKEN", ""),
        os.environ.get("GITHUB_REPO", ""),
        os.environ.get("GITHUB_BRANCH", "main"),
    )


def enabled() -> bool:
    tok, repo, _ = _cfg()
    return bool(tok and repo)


def _headers() -> dict:
    tok, _, _ = _cfg()
    return {"Authorization": f"Bearer {tok}", "Accept": "application/vnd.github+json"}


def list_docs(folder: str = "content") -> list[str]:
    """Every document blob under `folder/` (recursive), newest paths sorted."""
    _, repo, branch = _cfg()
    r = requests.get(
        f"{_API}/repos/{repo}/git/trees/{branch}?recursive=1",
        headers=_headers(), timeout=20,
    )
    r.raise_for_status()
    tree = r.json().get("tree", [])
    return sorted(
        n["path"] for n in tree
        if n.get("type") == "blob"
        and n["path"].startswith(folder + "/")
        and n["path"].lower().endswith(_DOC_EXTS)
    )


def get_file(path: str) -> bytes | None:
    """File bytes, or None if it doesn't exist."""
    _, repo, branch = _cfg()
    r = requests.get(
        f"{_API}/repos/{repo}/contents/{path}",
        headers=_headers(), params={"ref": branch}, timeout=20,
    )
    if r.status_code == 404:
        return None
    r.raise_for_status()
    return base64.b64decode(r.json()["content"])


def put_file(path: str, data: bytes, message: str) -> str:
    """Create or update a file; returns the commit's HTML URL."""
    _, repo, branch = _cfg()
    sha = None
    g = requests.get(
        f"{_API}/repos/{repo}/contents/{path}",
        headers=_headers(), params={"ref": branch}, timeout=20,
    )
    if g.status_code == 200:
        sha = g.json().get("sha")
    payload = {
        "message": message,
        "content": base64.b64encode(data).decode(),
        "branch": branch,
    }
    if sha:
        payload["sha"] = sha
    p = requests.put(
        f"{_API}/repos/{repo}/contents/{path}",
        headers=_headers(), json=payload, timeout=30,
    )
    p.raise_for_status()
    return p.json().get("commit", {}).get("html_url", "")
