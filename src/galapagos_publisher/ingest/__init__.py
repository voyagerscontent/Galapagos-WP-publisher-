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


def read_file(path: str | Path) -> Document:
    """Read a local file and return a normalized Document.

    The correct reader is chosen by file extension.
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
    return reader(path)


__all__ = ["read_file", "read_markdown", "read_docx", "read_text", "supported_extensions"]
