"""Document ingestion: turn raw inputs into a normalized `Document`."""

from __future__ import annotations

from pathlib import Path

from ..models import Document
from .docx_reader import read_docx
from .markdown_reader import read_markdown
from .text_reader import read_text

# Extension -> reader function
_READERS = {
    ".docx": read_docx,
    ".md": read_markdown,
    ".markdown": read_markdown,
    ".txt": read_text,
    ".text": read_text,
}


def supported_extensions() -> list[str]:
    return sorted(_READERS.keys())


def read_file(path: str | Path, *, page_type: str | None = None) -> Document:
    """Read a local file and return a normalized Document.

    The correct reader is chosen by file extension. ``page_type`` is an optional
    hint (the caller's intended page type, e.g. from ``--page-type``); the docx
    reader uses it to bypass the CMS-Stage adapter for species pages, whose
    table-heavy layout that adapter would otherwise drop.
    """
    path = Path(path)
    if not path.exists():
        raise FileNotFoundError(f"No such file: {path}")
    reader = _READERS.get(path.suffix.lower())
    if reader is None:
        raise ValueError(
            f"Unsupported file type '{path.suffix}'. "
            f"Supported: {', '.join(supported_extensions())}"
        )
    if reader is read_docx:
        return read_docx(path, page_type=page_type)
    return reader(path)


__all__ = ["read_file", "read_markdown", "read_docx", "read_text", "supported_extensions"]
