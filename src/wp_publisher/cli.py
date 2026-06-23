"""Command-line interface for the WP Publisher engine.

Examples
--------
    wp-publish check                       # verify WordPress connectivity
    wp-publish templates                   # list available page types
    wp-publish preview samples/galapagos-cruise.md
    wp-publish publish samples/galapagos-cruise.md --status draft
    wp-publish publish doc.docx --type tour --media library
    wp-publish gdrive "Galapagos 8-day" --preview
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Optional

import typer
from rich.console import Console
from rich.panel import Panel
from rich.table import Table

from .config import get_settings
from .ingest import read_file
from .models import Document
from .pipeline import BuildContext, build_page
from .rendering.template import load_registry
from .utils import to_slug as _slugify
from .wordpress.client import WordPressClient
from .wordpress.publisher import publish_page

app = typer.Typer(
    add_completion=False,
    help="Turn a doc into a formatted, SEO-optimized, schema-rich WordPress page.",
)
console = Console()


def _print_warnings(warnings: list[str]) -> None:
    if warnings:
        console.print("[yellow]Warnings:[/yellow]")
        for w in warnings:
            console.print(f"  [yellow]•[/yellow] {w}")


@app.command()
def check() -> None:
    """Verify WordPress credentials and detect the SEO plugin."""
    settings = get_settings()
    client = WordPressClient(settings)
    user = client.verify()
    plugin = client.detect_seo_plugin()
    console.print(
        Panel.fit(
            f"[green]✓ Connected[/green] to {settings.wp_base_url}\n"
            f"Authenticated as: [bold]{user.get('name')}[/bold] (id {user.get('id')})\n"
            f"SEO plugin detected: [bold]{plugin}[/bold]",
            title="WordPress",
        )
    )


@app.command()
def templates() -> None:
    """List available page templates."""
    registry = load_registry()
    table = Table(title="Page templates")
    table.add_column("key", style="cyan")
    table.add_column("name")
    table.add_column("post type")
    table.add_column("schema.org type")
    for tpl in registry.all():
        table.add_row(tpl.key, tpl.name, tpl.post_type, tpl.schema_type)
    console.print(table)


def _build(
    doc: Document,
    page_type: Optional[str],
    status: Optional[str],
    media: Optional[str],
    use_wordpress: bool,
):
    settings = get_settings()
    registry = load_registry()
    client = None
    if use_wordpress or media == "library":
        client = WordPressClient(settings)
    ctx = BuildContext(
        settings=settings, registry=registry, wp_client=client, media_strategy=media
    )
    page, template, reason = build_page(doc, ctx, page_type=page_type, status=status)
    return page, template, reason, client, settings


def _acf_summary(page) -> str:
    from .acf.config import get_acf_config

    cfg = get_acf_config()
    acf = page.acf if isinstance(page.acf, dict) else {}
    if cfg.mode == "flat":
        keys = [k for k, v in acf.items() if v]
        shown = ", ".join(keys[:8]) + ("…" if len(keys) > 8 else "")
        return f"{len(keys)} ACF field(s): {shown or '—'}"
    rows = acf.get(cfg.flexible_field, [])
    layouts = [r.get("acf_fc_layout", "?") for r in rows]
    return f"{len(rows)} ACF section(s): {', '.join(layouts) or '—'}"


def _summary(doc: Document, page, template, reason) -> None:
    console.print(
        Panel.fit(
            f"Source: [bold]{doc.source_name}[/bold] ({doc.source_kind})\n"
            f"Page type: [bold cyan]{template.key}[/bold cyan] — {reason}\n"
            f"Title: {page.title}\n"
            f"Slug: {page.slug}\n"
            f"SEO title: {page.seo_title}\n"
            f"Meta: {page.meta_description}\n"
            f"Focus keyword: {page.focus_keyword or '—'}\n"
            f"Status: [bold]{page.status}[/bold]   post_type: {page.post_type}\n"
            f"Categories: {', '.join(page.categories) or '—'}   "
            f"Tags: {', '.join(page.tags) or '—'}\n"
            f"ACF: [bold]{_acf_summary(page)}[/bold]\n"
            f"Featured: {_media_desc(page.featured_media)}",
            title="Built page",
        )
    )
    _print_warnings(page.warnings)


def _media_desc(item) -> str:
    if not item:
        return "—"
    if item.source == "library":
        return f"library media #{item.wp_media_id} (score {item.match_score})"
    return "placeholder (needs a human)"


@app.command()
def preview(
    file: Path = typer.Argument(..., exists=True, readable=True),
    type: Optional[str] = typer.Option(None, "--type", "-t", help="Force a page type."),
    media: Optional[str] = typer.Option(
        None, "--media", "-m", help="Media strategy: library | placeholder."
    ),
    slug: Optional[str] = typer.Option(
        None, "--slug", help="Override the page slug (e.g. for an isolated test page)."
    ),
    out: Optional[Path] = typer.Option(None, "--out", "-o", help="Write artifacts here."),
) -> None:
    """Build a page and show/save it WITHOUT publishing."""
    doc = read_file(file)
    use_wp = media == "library"
    page, template, reason, _client, _settings = _build(doc, type, None, media, use_wp)
    if slug:
        page.slug = _slugify(slug)
    _summary(doc, page, template, reason)

    out_dir = out or (Path("output") / page.slug)
    out_dir.mkdir(parents=True, exist_ok=True)
    (out_dir / "acf.json").write_text(
        json.dumps(page.acf, indent=2, ensure_ascii=False), encoding="utf-8"
    )
    (out_dir / "schema.json").write_text(
        json.dumps(page.json_ld, indent=2, ensure_ascii=False), encoding="utf-8"
    )
    (out_dir / "page.json").write_text(
        page.model_dump_json(indent=2), encoding="utf-8"
    )
    console.print(f"[green]Artifacts written to[/green] {out_dir}/ (acf.json = what gets populated)")


@app.command()
def publish(
    file: Path = typer.Argument(..., exists=True, readable=True),
    type: Optional[str] = typer.Option(None, "--type", "-t", help="Force a page type."),
    status: Optional[str] = typer.Option(
        None, "--status", "-s", help="draft | pending | publish (default: site default)."
    ),
    media: Optional[str] = typer.Option(
        None, "--media", "-m", help="Media strategy: library | placeholder."
    ),
    slug: Optional[str] = typer.Option(
        None, "--slug", help="Override the page slug (e.g. for an isolated test page)."
    ),
    update: bool = typer.Option(
        False, "--update", help="Allow overwriting an existing post with the same slug."
    ),
    yes: bool = typer.Option(False, "--yes", "-y", help="Skip the confirmation prompt."),
) -> None:
    """Build and publish a document to WordPress."""
    from .wordpress.client import WordPressError

    doc = read_file(file)
    page, template, reason, client, settings = _build(doc, type, status, media, True)
    if slug:
        page.slug = _slugify(slug)
    _summary(doc, page, template, reason)

    if page.status == "publish" and not yes:
        typer.confirm(
            "This will publish LIVE on the site. Continue?", abort=True
        )

    plugin = settings.seo.get("seo_plugin", "auto")
    if plugin == "auto":
        plugin = client.detect_seo_plugin()

    try:
        result = publish_page(
            client, page, settings, seo_plugin=plugin, update_existing=update
        )
    except WordPressError as exc:
        console.print(f"[red]Refused:[/red] {exc}")
        raise typer.Exit(1) from exc
    verb = "Created" if result.created else "Updated"
    console.print(
        Panel.fit(
            f"[green]✓ {verb}[/green] post #{result.post_id} ([bold]{result.status}[/bold])\n"
            f"View: {result.url}\n"
            f"Edit: {result.edit_url}",
            title="Published",
        )
    )
    _print_warnings(result.warnings)


@app.command()
def gdrive(
    query: str = typer.Argument(..., help="File ID or name to search for in Drive."),
    type: Optional[str] = typer.Option(None, "--type", "-t"),
    status: Optional[str] = typer.Option(None, "--status", "-s"),
    media: Optional[str] = typer.Option(None, "--media", "-m"),
    preview_only: bool = typer.Option(False, "--preview", help="Build but do not publish."),
) -> None:
    """Ingest a document from Google Drive (restricted to GDRIVE_ALLOWED_ACCOUNT)."""
    from .ingest.gdrive_reader import find_file_id, read_gdrive

    file_id = query
    if len(query) < 25 or " " in query:  # looks like a name, not an ID
        matches = find_file_id(query)
        if not matches:
            console.print(f"[red]No Drive files matched '{query}'.[/red]")
            raise typer.Exit(1)
        if len(matches) > 1:
            console.print("[yellow]Multiple matches — using the first:[/yellow]")
            for m in matches[:10]:
                console.print(f"  {m['id']}  {m['name']}")
        file_id = matches[0]["id"]

    doc = read_gdrive(file_id)
    use_wp = (not preview_only) or media == "library"
    page, template, reason, client, settings = _build(doc, type, status, media, use_wp)
    _summary(doc, page, template, reason)

    if preview_only:
        console.print("[cyan]Preview only — not published.[/cyan]")
        return

    plugin = settings.seo.get("seo_plugin", "auto")
    if plugin == "auto":
        plugin = client.detect_seo_plugin()
    result = publish_page(client, page, settings, seo_plugin=plugin)
    console.print(f"[green]✓ Published[/green] #{result.post_id}: {result.url}")


if __name__ == "__main__":
    app()
