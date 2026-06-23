"""Parse `:::` directive fences from a content body.

Returns a flat list of nodes: ``("text", body)`` for runs of Markdown and
``("dir", name, args, inner)`` for directive blocks (whose ``inner`` may contain
further nested directives, parsed recursively by the caller).
"""

from __future__ import annotations

import re

_OPEN = re.compile(r"^:::+\s*([A-Za-z][\w-]*)\s*(.*)$")
_CLOSE = re.compile(r"^:::+\s*$")


def parse_directives(text: str) -> list[tuple]:
    lines = text.split("\n")
    nodes: list[tuple] = []
    i, n = 0, len(lines)
    while i < n:
        stripped = lines[i].strip()
        m = _OPEN.match(stripped)
        if m:
            name, args = m.group(1), m.group(2).strip()
            depth, j, inner = 1, i + 1, []
            while j < n:
                s = lines[j].strip()
                if _OPEN.match(s):
                    depth += 1
                    inner.append(lines[j])
                elif _CLOSE.match(s):
                    depth -= 1
                    if depth == 0:
                        break
                    inner.append(lines[j])
                else:
                    inner.append(lines[j])
                j += 1
            nodes.append(("dir", name.lower(), args, "\n".join(inner)))
            i = j + 1
        elif _CLOSE.match(stripped):
            i += 1  # stray closing fence
        else:
            buf = []
            while i < n:
                s = lines[i].strip()
                if _OPEN.match(s) or _CLOSE.match(s):
                    break
                buf.append(lines[i])
                i += 1
            if "\n".join(buf).strip():
                nodes.append(("text", "\n".join(buf)))
    return nodes
