"""Marketing dashboard — upload a document, the engine detects its format and
page type (by canonical URL), validates it, and publishes/updates a `-test`
draft on WordPress. Uploaded docs are saved to the GitHub repo (the "memory")
so they can be re-published later without re-uploading, and Git keeps versions.

A thin Streamlit UI over the `wp_publisher` engine; the publishing logic is
reused verbatim (same path as `wp-publish publish --test`).

Run locally:   streamlit run dashboard/app.py
Deploy:        Streamlit Community Cloud / Render / Railway (see dashboard/README.md)
"""

from __future__ import annotations

import os
import re
import sys
import tempfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "src"))

import streamlit as st  # noqa: E402

for _k in ("WP_BASE_URL", "WP_USERNAME", "WP_APP_PASSWORD", "DASHBOARD_PASSWORD",
           "GITHUB_TOKEN", "GITHUB_REPO", "GITHUB_BRANCH"):
    try:
        if _k in st.secrets and not os.environ.get(_k):
            os.environ[_k] = str(st.secrets[_k])
    except Exception:
        pass

import gh_store  # noqa: E402
from wp_publisher.cli import _fix_schema_url  # noqa: E402
from wp_publisher.config import get_settings  # noqa: E402
from wp_publisher.ingest import read_file  # noqa: E402
from wp_publisher.pipeline import BuildContext, build_page  # noqa: E402
from wp_publisher.rendering.template import load_registry  # noqa: E402
from wp_publisher.wordpress.client import WordPressClient, WordPressError  # noqa: E402
from wp_publisher.wordpress.publisher import publish_page  # noqa: E402

TYPE_LABELS = {
    "informative": "Informativa / Guía", "wildlife_single": "Wildlife / Especie",
    "destination": "Isla / Destino", "cruise": "Crucero", "tour": "Tour", "blog_post": "Blog",
}
ACF_GROUP = {"informative": "Informative Page", "wildlife_single": "Wildlife Single",
             "destination": "Island Guide"}
_SCAFFOLD_TITLE = re.compile(r"^(cms stage|galapagosislands\.travel|publisher header)", re.I)

st.set_page_config(page_title="Publicador Galápagos", page_icon="🐢", layout="wide")
st.markdown(
    """<style>
      .stApp{background:#ece5de}
      section[data-testid="stSidebar"]{background:#faf7f4;border-right:1px solid #e4dacd}
      h1,h2,h3,h4{color:#2c2017!important;font-family:Georgia,"Times New Roman",serif}
      .stApp,.stMarkdown,p,label,span,div{color:#2c2017}
      .stButton>button{background:#64402c;color:#faf7f4;border:0;border-radius:10px;padding:.55rem 1.1rem;font-weight:600}
      .stButton>button:hover{background:#4a2e1e;color:#fff}
      .eyebrow{font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#7c6a5b;font-weight:700}
      .kv{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
      .kv .b{background:#fff;border:1px solid #e4dacd;border-radius:9px;padding:7px 11px;font-size:13px}
      .kv .b b{font-size:15px}
    </style>""",
    unsafe_allow_html=True,
)


# ------------------------------------------------------------ helpers --- #
def gate() -> bool:
    want = os.environ.get("DASHBOARD_PASSWORD", "")
    if not want or st.session_state.get("authed"):
        return True
    st.markdown("### 🔒 Publicador Galápagos")
    pw = st.text_input("Contraseña del equipo", type="password")
    if pw and pw == want:
        st.session_state.authed = True
        st.rerun()
    elif pw:
        st.error("Contraseña incorrecta.")
    return False


def client_or_none():
    s = get_settings()
    if s.wp_base_url and s.wp_username and s.wp_app_password:
        try:
            return WordPressClient(s)
        except Exception:
            return None
    return None


def stage_uploads(files) -> Path | None:
    if not files:
        return None
    d = Path(tempfile.mkdtemp(prefix="galapagos_"))
    saved = {}
    for f in files:
        (d / f.name).write_bytes(f.getvalue())
        saved[f.name.lower()] = d / f.name
    html = next((p for n, p in saved.items() if n.endswith((".html", ".htm"))), None)
    docx = next((p for n, p in saved.items() if n.endswith(".docx")), None)
    js = next((p for n, p in saved.items() if n.endswith(".json")), None)
    if html and js and js.name != html.stem + ".schema.json":
        (d / (html.stem + ".schema.json")).write_bytes(js.read_bytes())
    return html or docx


def table_count(acf) -> int:
    return sum(str(v).count("<table") for r in acf.get("feature_sections", [])
               if isinstance(r, dict) for v in r.values())


def analyze(primary: Path):
    doc = read_file(primary)
    ctx = BuildContext(get_settings(), load_registry(), None, "placeholder")
    page, template, reason = build_page(doc, ctx)
    return doc, page, template, reason


def validate(doc, page):
    acf, out = page.acf, []
    ok = not _SCAFFOLD_TITLE.match(page.title or "")
    out.append(("ok" if ok else "crit",
                "Título correcto" if ok else "El título parece cabecera del documento — revísalo"))
    out.append(("ok" if acf.get("feature_sections") else "warn",
                f"{len(acf.get('feature_sections', []))} secciones · {table_count(acf)} tablas"))
    out.append(("ok" if acf.get("faqs") else "warn", f"{len(acf.get('faqs', []))} preguntas frecuentes"))
    out.append(("ok" if acf.get("seo_schema") else "warn",
                "Schema JSON-LD presente" if acf.get("seo_schema")
                else "Sin schema JSON-LD (adjunta el .json para completarlo)"))
    verifies = [w for w in (doc.metadata.get("_ingest_warnings") or []) if "VERIFY" in str(w).upper()]
    if verifies:
        out.append(("warn", f"{len(verifies)} punto(s) VERIFY por revisar antes de producción"))
    return out


def render_analysis(doc, page, template, reason, client):
    test_slug = page.slug if page.slug.endswith("-test") else page.slug + "-test"
    action, existing_id = "Crear nuevo", None
    if client:
        try:
            found = client.find_post_by_slug("page", test_slug)
            if found:
                action, existing_id = "Actualizar", found.get("id")
        except Exception:
            pass
    fmt = ("HTML + schema.json" if (doc.source_kind != "docx" and doc.metadata.get("schema_jsonld"))
           else "HTML" if doc.source_kind != "docx" else "Word · CMS Stage 8")
    st.markdown('<span class="eyebrow">Detección automática</span>', unsafe_allow_html=True)
    a, b, c = st.columns(3)
    a.metric("Formato", fmt)
    b.metric("Tipo de página", TYPE_LABELS.get(template.key, template.key))
    c.metric("Acción", action + (f" · #{existing_id}" if existing_id else ""))
    d, e, f = st.columns(3)
    d.metric("Parent", template.parent_page or "— (ninguno)")
    e.metric("Grupo ACF", ACF_GROUP.get(template.key, "—"))
    f.metric("Slug destino", test_slug)
    st.caption(f"Ruta: {reason}")

    st.markdown('<span class="eyebrow">Validación</span>', unsafe_allow_html=True)
    for state, text in validate(doc, page):
        st.markdown(f"{ {'ok': '✅', 'warn': '⚠️', 'crit': '⛔'}[state] }  {text}")

    acf = page.acf
    st.markdown('<span class="eyebrow">Vista previa de campos</span>', unsafe_allow_html=True)
    st.markdown('<div class="kv">' + "".join(
        f'<span class="b"><b>{v}</b> {k}</span>' for k, v in [
            ("GEO", "✓" if acf.get("geo_answer") else "—"),
            ("secciones", len(acf.get("feature_sections", []))),
            ("tablas", table_count(acf)), ("FAQs", len(acf.get("faqs", []))),
            ("CTAs", len(acf.get("cta", []))), ("schema", "✓" if acf.get("seo_schema") else "—"),
        ]) + "</div>", unsafe_allow_html=True)
    return action


def publish_now(doc):
    s = get_settings()
    c = WordPressClient(s)
    ctx = BuildContext(s, load_registry(), c, "placeholder")
    page, _t, _r = build_page(doc, ctx)
    if not page.slug.endswith("-test"):
        page.slug += "-test"
    _fix_schema_url(page, s)
    plugin = s.seo.get("seo_plugin", "auto")
    if plugin == "auto":
        plugin = c.detect_seo_plugin()
    return publish_page(c, page, s, seo_plugin=plugin, update_existing=True), page


def repo_paths(doc, page, primary: Path) -> tuple[str, str | None]:
    section = (doc.metadata.get("url_section") or "misc").strip("/").lower() or "misc"
    slug = page.slug[:-5] if page.slug.endswith("-test") else page.slug
    base = f"content/{section}/{slug}"
    ext = primary.suffix.lower()
    side = primary.parent / (primary.stem + ".schema.json")
    return base + ext, (base + ".schema.json" if side.exists() else None)


def save_to_repo(doc, page, primary: Path, who: str) -> str:
    doc_path, side_path = repo_paths(doc, page, primary)
    gh_store.put_file(doc_path, primary.read_bytes(), f"dashboard: {who} sube {Path(doc_path).name}")
    if side_path:
        side = primary.parent / (primary.stem + ".schema.json")
        gh_store.put_file(side_path, side.read_bytes(), f"dashboard: {who} sube {Path(side_path).name}")
    return doc_path


def fetch_stored(path: str) -> Path:
    d = Path(tempfile.mkdtemp(prefix="galapagos_repo_"))
    f = d / Path(path).name
    f.write_bytes(gh_store.get_file(path) or b"")
    if f.suffix.lower() in (".html", ".htm"):
        side = gh_store.get_file(path.rsplit(".", 1)[0] + ".schema.json")
        if side:
            (d / (f.stem + ".schema.json")).write_bytes(side)
    return f


def show_result(result, page):
    verb = "creado" if result.created else "actualizado"
    st.success(f"Borrador {verb}: #{result.post_id}")
    st.markdown(f"**Ver:** {result.url}")
    st.session_state.setdefault("recent", []).append(f"{page.title[:40]} → #{result.post_id} ({verb})")
    for w in result.warnings:
        st.caption(f"⚠️ {w}")


# ----------------------------------------------------------------- app --- #
if not gate():
    st.stop()

st.markdown("# 🐢 Publicador Galápagos")
st.caption("Sube el documento → el sistema reconoce el tipo → publica un borrador de test.")

client = client_or_none()
with st.sidebar:
    st.markdown("### Estado")
    st.success("WordPress ✓") if client else st.warning("Sin WordPress — modo vista previa (no publica).")
    st.success("Memoria (repo) ✓") if gh_store.enabled() else st.info("Sin repo — no se guardan documentos.")
    st.markdown("**Modo:** Borrador → URL de test `-test`")
    st.divider()
    st.markdown("**Historial de esta sesión**")
    for h in reversed(st.session_state.get("recent", [])):
        st.markdown(f"- {h}")

tab_up, tab_mem = st.tabs(["⬆️ Subir documento", "🗂️ Documentos guardados"])

# ---- Upload / re-upload -------------------------------------------------- #
with tab_up:
    files = st.file_uploader(
        "Documento a publicar (sube uno nuevo, o resube una versión corregida)",
        type=["html", "htm", "json", "docx"], accept_multiple_files=True,
        help="HTML + schema.json, o un Word (CMS Stage 8). Puedes soltar el .html y el .json juntos.",
    )
    primary = stage_uploads(files)
    if not primary:
        st.info("Sube un documento para empezar. Para HTML, incluye también su `.json` si lo tienes.")
    else:
        try:
            doc, page, template, reason = analyze(primary)
        except Exception as exc:  # noqa: BLE001
            st.error(f"No pude leer el documento: {exc}")
            st.stop()
        action = render_analysis(doc, page, template, reason, client)
        st.divider()
        label = "Republicar borrador" if action == "Actualizar" else "Publicar borrador"
        if client is None:
            st.info("Conecta WordPress para habilitar la publicación. Ahora es solo vista previa.")
        if st.button(f"🚀 {label}", disabled=client is None, type="primary", key="pub_upload"):
            try:
                with st.spinner("Publicando borrador…"):
                    result, pub_page = publish_now(doc)
                show_result(result, pub_page)
                if gh_store.enabled():
                    try:
                        saved = save_to_repo(doc, pub_page, primary, "marketing")
                        st.caption(f"🗂️ Guardado en el repo: `{saved}` (queda en memoria para republicar).")
                    except Exception as exc:  # noqa: BLE001
                        st.caption(f"⚠️ Publicado, pero no pude guardarlo en el repo: {exc}")
            except WordPressError as exc:
                st.error(f"Rechazado por WordPress: {exc}")
            except Exception as exc:  # noqa: BLE001
                st.error(f"Error al publicar: {exc}")

# ---- Stored documents (memory) ------------------------------------------ #
with tab_mem:
    if not gh_store.enabled():
        st.info("La memoria de documentos usa el repo de GitHub. Configura GITHUB_TOKEN y GITHUB_REPO para activarla.")
    else:
        st.caption("Documentos ya guardados. Republica sin volver a subir — o sube una versión nueva en la otra pestaña.")
        try:
            docs = gh_store.list_docs()
        except Exception as exc:  # noqa: BLE001
            docs = []
            st.error(f"No pude leer el repo: {exc}")
        if not docs:
            st.info("Aún no hay documentos guardados. Publica uno desde “Subir documento”.")
        for path in docs:
            col1, col2 = st.columns([4, 1])
            col1.markdown(f"📄 `{path}`")
            if col2.button("Republicar", key="rep_" + path, disabled=client is None):
                try:
                    with st.spinner(f"Republicando {Path(path).name}…"):
                        stored = fetch_stored(path)
                        doc = read_file(stored)
                        result, pub_page = publish_now(doc)
                    show_result(result, pub_page)
                except WordPressError as exc:
                    st.error(f"Rechazado por WordPress: {exc}")
                except Exception as exc:  # noqa: BLE001
                    st.error(f"Error al republicar: {exc}")
