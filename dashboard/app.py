"""Marketing dashboard — upload a document, the engine detects its format and
page type (by canonical URL), validates it, and publishes/updates a `-test`
draft on WordPress. A thin Streamlit UI over the `wp_publisher` engine; the
publishing logic is reused verbatim (same path as `wp-publish publish --test`).

Run locally:   streamlit run dashboard/app.py
Deploy:        Streamlit Community Cloud / Render / Railway (see dashboard/README.md)
"""

from __future__ import annotations

import os
import re
import sys
import tempfile
from pathlib import Path

# Make the engine importable without installing the package (works on
# Streamlit Cloud, which only runs `pip install -r requirements.txt`).
sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "src"))

import streamlit as st  # noqa: E402

# Secrets -> environment BEFORE the engine reads its settings.
for _k in ("WP_BASE_URL", "WP_USERNAME", "WP_APP_PASSWORD", "DASHBOARD_PASSWORD"):
    try:
        if _k in st.secrets and not os.environ.get(_k):
            os.environ[_k] = str(st.secrets[_k])
    except Exception:
        pass

from wp_publisher.cli import _fix_schema_url  # noqa: E402
from wp_publisher.config import get_settings  # noqa: E402
from wp_publisher.ingest import read_file  # noqa: E402
from wp_publisher.pipeline import BuildContext, build_page  # noqa: E402
from wp_publisher.rendering.template import load_registry  # noqa: E402
from wp_publisher.wordpress.client import WordPressClient, WordPressError  # noqa: E402
from wp_publisher.wordpress.publisher import publish_page  # noqa: E402

TYPE_LABELS = {
    "informative": "Informativa / Guía",
    "wildlife_single": "Wildlife / Especie",
    "destination": "Isla / Destino",
    "cruise": "Crucero",
    "tour": "Tour",
    "blog_post": "Blog",
}
ACF_GROUP = {
    "informative": "Informative Page",
    "wildlife_single": "Wildlife Single",
    "destination": "Island Guide",
}
_SCAFFOLD_TITLE = re.compile(r"^(cms stage|galapagosislands\.travel|publisher header)", re.I)

st.set_page_config(page_title="Publicador Galápagos", page_icon="🐢", layout="wide")

st.markdown(
    """
    <style>
      .stApp{background:#ece5de}
      section[data-testid="stSidebar"]{background:#faf7f4;border-right:1px solid #e4dacd}
      h1,h2,h3,h4{color:#2c2017!important;font-family:Georgia,"Times New Roman",serif}
      .stApp,.stMarkdown,p,label,span,div{color:#2c2017}
      .stButton>button{background:#64402c;color:#faf7f4;border:0;border-radius:10px;
        padding:.55rem 1.1rem;font-weight:600}
      .stButton>button:hover{background:#4a2e1e;color:#fff}
      .card{background:#faf7f4;border:1px solid #e4dacd;border-radius:14px;padding:16px 18px;margin-bottom:14px}
      .eyebrow{font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#7c6a5b;font-weight:700}
      .kv{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
      .kv .b{background:#fff;border:1px solid #e4dacd;border-radius:9px;padding:7px 11px;font-size:13px}
      .kv .b b{font-size:15px}
      .mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px}
    </style>
    """,
    unsafe_allow_html=True,
)


def _gate() -> bool:
    want = os.environ.get("DASHBOARD_PASSWORD", "")
    if not want:
        return True  # no password configured -> open (local/dev)
    if st.session_state.get("authed"):
        return True
    st.markdown("### 🔒 Publicador Galápagos")
    pw = st.text_input("Contraseña del equipo", type="password")
    if pw and pw == want:
        st.session_state.authed = True
        st.rerun()
    elif pw:
        st.error("Contraseña incorrecta.")
    return False


def _stage(files) -> Path | None:
    """Save uploads to a temp dir; return the primary doc path (html/docx).
    A .json is stored as `<htmlstem>.schema.json` so the reader finds the sidecar."""
    if not files:
        return None
    d = Path(tempfile.mkdtemp(prefix="galapagos_"))
    saved = {}
    for f in files:
        p = d / f.name
        p.write_bytes(f.getvalue())
        saved[f.name.lower()] = p
    html = next((p for n, p in saved.items() if n.endswith((".html", ".htm"))), None)
    docx = next((p for n, p in saved.items() if n.endswith(".docx")), None)
    js = next((p for n, p in saved.items() if n.endswith(".json")), None)
    primary = html or docx
    if html and js and js.name != html.stem + ".schema.json":
        (d / (html.stem + ".schema.json")).write_bytes(js.read_bytes())
    return primary


def _fmt_label(path: Path, doc) -> str:
    if path.suffix.lower() in (".html", ".htm"):
        return "HTML + schema.json" if doc.metadata.get("schema_jsonld") else "HTML"
    return "Word · CMS Stage 8" if doc.source_kind == "docx" else "Word"


def _client_or_none():
    s = get_settings()
    if s.wp_base_url and s.wp_username and s.wp_app_password:
        try:
            return WordPressClient(s)
        except Exception:
            return None
    return None


def _table_count(acf) -> int:
    return sum(
        str(v).count("<table")
        for r in acf.get("feature_sections", [])
        if isinstance(r, dict)
        for v in r.values()
    )


def _validate(doc, page):
    acf = page.acf
    checks = []
    ok = not _SCAFFOLD_TITLE.match(page.title or "")
    checks.append(("ok" if ok else "crit",
                   "Título correcto" if ok else "El título parece cabecera del documento — revísalo"))
    nsec = len(acf.get("feature_sections", []))
    ntbl = _table_count(acf)
    checks.append(("ok" if nsec else "warn", f"{nsec} secciones · {ntbl} tablas"))
    nfaq = len(acf.get("faqs", []))
    checks.append(("ok" if nfaq else "warn", f"{nfaq} preguntas frecuentes"))
    has_schema = bool(acf.get("seo_schema"))
    checks.append(("ok" if has_schema else "warn",
                   "Schema JSON-LD presente" if has_schema else "Sin schema JSON-LD (adjunta el .json para completarlo)"))
    verifies = [w for w in (doc.metadata.get("_ingest_warnings") or []) if "VERIFY" in str(w).upper()]
    if verifies:
        checks.append(("warn", f"{len(verifies)} punto(s) VERIFY por revisar antes de producción"))
    return checks


# ------------------------------------------------------------------ UI --- #
if not _gate():
    st.stop()

st.markdown("# 🐢 Publicador Galápagos")
st.caption("Sube el documento → el sistema reconoce el tipo → publica un borrador de test.")

client = _client_or_none()
with st.sidebar:
    st.markdown("### Estado")
    if client:
        st.success("Conectado a WordPress ✓")
    else:
        st.warning("Sin conexión a WordPress — modo **vista previa** (no publica). Configura WP_USERNAME y WP_APP_PASSWORD.")
    st.markdown("**Modo:** Borrador → URL de test `-test`")
    st.divider()
    st.markdown("**Historial de esta sesión**")
    for h in reversed(st.session_state.get("recent", [])):
        st.markdown(f"- {h}")

files = st.file_uploader(
    "Documento a publicar",
    type=["html", "htm", "json", "docx"],
    accept_multiple_files=True,
    help="HTML + schema.json, o un Word (CMS Stage 8). Puedes soltar el .html y el .json juntos.",
)

primary = _stage(files)
if not primary:
    st.info("Sube un documento para empezar. Para HTML, incluye también su `.json` si lo tienes.")
    st.stop()

try:
    doc = read_file(primary)
    settings = get_settings()
    ctx = BuildContext(settings, load_registry(), None, "placeholder")
    page, template, reason = build_page(doc, ctx)
except Exception as exc:  # noqa: BLE001
    st.error(f"No pude leer el documento: {exc}")
    st.stop()

if not page.slug.endswith("-test"):
    test_slug = page.slug + "-test"
else:
    test_slug = page.slug

# Detection ---------------------------------------------------------------- #
action = "Crear nuevo"
existing_id = None
if client:
    try:
        found = client.find_post_by_slug("page", test_slug)
        if found:
            action, existing_id = "Actualizar", found.get("id")
    except Exception:
        pass

st.markdown('<span class="eyebrow">Detección automática</span>', unsafe_allow_html=True)
c1, c2, c3 = st.columns(3)
c1.metric("Formato", _fmt_label(primary, doc))
c2.metric("Tipo de página", TYPE_LABELS.get(template.key, template.key))
c3.metric("Acción", f"{action}" + (f" · #{existing_id}" if existing_id else ""))
c4, c5, c6 = st.columns(3)
c4.metric("Parent", template.parent_page or "— (ninguno)")
c5.metric("Grupo ACF", ACF_GROUP.get(template.key, "—"))
c6.metric("Slug destino", test_slug)
st.caption(f"Ruta: {reason}")

# Validation --------------------------------------------------------------- #
st.markdown('<span class="eyebrow">Validación</span>', unsafe_allow_html=True)
for state, text in _validate(doc, page):
    icon = {"ok": "✅", "warn": "⚠️", "crit": "⛔"}[state]
    st.markdown(f"{icon}  {text}")

# ACF preview -------------------------------------------------------------- #
acf = page.acf
st.markdown('<span class="eyebrow">Vista previa de campos</span>', unsafe_allow_html=True)
st.markdown(
    '<div class="kv">'
    + "".join(
        f'<span class="b"><b>{v}</b> {k}</span>'
        for k, v in [
            ("GEO", "✓" if acf.get("geo_answer") else "—"),
            ("secciones", len(acf.get("feature_sections", []))),
            ("tablas", _table_count(acf)),
            ("FAQs", len(acf.get("faqs", []))),
            ("CTAs", len(acf.get("cta", []))),
            ("schema", "✓" if acf.get("seo_schema") else "—"),
        ]
    )
    + "</div>",
    unsafe_allow_html=True,
)

st.divider()

# Publish ------------------------------------------------------------------ #
label = "Republicar borrador" if action == "Actualizar" else "Publicar borrador"
disabled = client is None
if disabled:
    st.info("Conecta WordPress (credenciales) para habilitar la publicación. Ahora mismo es solo vista previa.")

if st.button(f"🚀 {label} · {test_slug}", disabled=disabled, type="primary"):
    try:
        pub_settings = get_settings()
        pub_client = WordPressClient(pub_settings)
        pub_ctx = BuildContext(pub_settings, load_registry(), pub_client, "placeholder")
        page2, tmpl2, _ = build_page(doc, pub_ctx)
        if not page2.slug.endswith("-test"):
            page2.slug += "-test"
        _fix_schema_url(page2, pub_settings)
        plugin = pub_settings.seo.get("seo_plugin", "auto")
        if plugin == "auto":
            plugin = pub_client.detect_seo_plugin()
        with st.spinner("Publicando borrador…"):
            result = publish_page(pub_client, page2, pub_settings, seo_plugin=plugin, update_existing=True)
        verb = "creado" if result.created else "actualizado"
        st.success(f"Borrador {verb}: #{result.post_id}")
        st.markdown(f"**Ver:** {result.url}")
        rec = st.session_state.setdefault("recent", [])
        rec.append(f"{page2.title[:40]} → #{result.post_id} ({verb})")
        for w in result.warnings:
            st.caption(f"⚠️ {w}")
    except WordPressError as exc:
        st.error(f"Rechazado por WordPress: {exc}")
    except Exception as exc:  # noqa: BLE001
        st.error(f"Error al publicar: {exc}")
