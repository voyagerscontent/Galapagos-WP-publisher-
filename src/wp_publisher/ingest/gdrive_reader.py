"""Read documents from Google Drive.

This reader is optional and requires the `gdrive` extra:

    pip install -e ".[gdrive]"

Authentication uses OAuth user credentials. On first run it opens a browser to
authorize a Google account, then caches a token so later runs are
non-interactive. If GDRIVE_ALLOWED_ACCOUNT is set (per-site, in .env), the
reader refuses to proceed unless the authorized account matches it — this
guarantees content is only ever pulled from the approved account. When the
setting is empty, no account restriction is enforced.

See docs/SETUP.md for credential creation steps.
"""

from __future__ import annotations

import io
import tempfile
from pathlib import Path

from ..config import get_settings
from ..models import Document
from .docx_reader import read_docx
from .text_reader import read_text

# Read-only Drive scope plus userinfo to verify the account identity.
SCOPES = [
    "https://www.googleapis.com/auth/drive.readonly",
    "https://www.googleapis.com/auth/userinfo.email",
    "openid",
]

# MIME types we know how to export/convert.
GOOGLE_DOC = "application/vnd.google-apps.document"
DOCX = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
PLAIN = "text/plain"


def _build_service():
    try:
        from google.auth.transport.requests import Request
        from google.oauth2.credentials import Credentials
        from google_auth_oauthlib.flow import InstalledAppFlow
        from googleapiclient.discovery import build
    except ImportError as exc:  # pragma: no cover - dependency guard
        raise RuntimeError(
            "Google Drive support requires the 'gdrive' extra. "
            'Install it with: pip install -e ".[gdrive]"'
        ) from exc

    settings = get_settings()
    token_path = Path(settings.gdrive_token_file)
    creds_path = Path(settings.gdrive_credentials_file)

    creds = None
    if token_path.exists():
        creds = Credentials.from_authorized_user_file(str(token_path), SCOPES)
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            if not creds_path.exists():
                raise RuntimeError(
                    f"Google OAuth client secret not found at '{creds_path}'. "
                    "See docs/SETUP.md to create it."
                )
            flow = InstalledAppFlow.from_client_secrets_file(str(creds_path), SCOPES)
            creds = flow.run_local_server(port=0)
        token_path.write_text(creds.to_json(), encoding="utf-8")

    _verify_account(creds)
    return build("drive", "v3", credentials=creds)


def _verify_account(creds) -> None:
    """Refuse to operate under any account other than the approved one."""
    from googleapiclient.discovery import build

    allowed = (get_settings().gdrive_allowed_account or "").lower()
    if not allowed:
        return
    oauth2 = build("oauth2", "v2", credentials=creds)
    info = oauth2.userinfo().get().execute()
    email = (info.get("email") or "").lower()
    if email != allowed:
        raise PermissionError(
            f"Authorized Google account '{email}' is not the approved account "
            f"'{allowed}'. Re-authorize with the correct account "
            f"(delete the token file to retry)."
        )


def read_gdrive(file_id: str) -> Document:
    """Fetch a Google Doc / uploaded doc by file ID and normalize it."""
    from googleapiclient.http import MediaIoBaseDownload

    service = _build_service()
    meta = service.files().get(fileId=file_id, fields="id,name,mimeType").execute()
    mime = meta["mimeType"]
    name = meta.get("name", file_id)

    if mime == GOOGLE_DOC:
        # Export native Google Docs as .docx to preserve structure/styles.
        request = service.files().export_media(fileId=file_id, mimeType=DOCX)
        suffix = ".docx"
    elif mime == DOCX:
        request = service.files().get_media(fileId=file_id)
        suffix = ".docx"
    elif mime in (PLAIN, "text/markdown"):
        request = service.files().get_media(fileId=file_id)
        suffix = ".txt"
    else:
        raise ValueError(f"Unsupported Google Drive file type: {mime}")

    buffer = io.BytesIO()
    downloader = MediaIoBaseDownload(buffer, request)
    done = False
    while not done:
        _status, done = downloader.next_chunk()
    buffer.seek(0)

    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as tmp:
        tmp.write(buffer.read())
        tmp_path = Path(tmp.name)

    try:
        doc = read_docx(tmp_path) if suffix == ".docx" else read_text(tmp_path)
    finally:
        tmp_path.unlink(missing_ok=True)

    doc.source_name = name
    doc.source_kind = "gdrive"
    return doc


def find_file_id(name_query: str) -> list[dict]:
    """Search Drive for files matching a name. Returns id/name/mimeType dicts."""
    service = _build_service()
    safe = name_query.replace("'", "\\'")
    resp = (
        service.files()
        .list(
            q=f"name contains '{safe}' and trashed = false",
            fields="files(id,name,mimeType,modifiedTime)",
            pageSize=20,
        )
        .execute()
    )
    return resp.get("files", [])
