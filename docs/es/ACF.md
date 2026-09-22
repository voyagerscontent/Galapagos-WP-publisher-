> Traducción al español. Versión original en inglés: [../ACF.md](../ACF.md).

# Publicación en campos ACF (flat) para Elementor

El publisher **no** escribe bloques de Gutenberg. Toma tu documento de contenido,
lo compone en componentes estructurados y escribe **campos ACF planos y nombrados** sobre
la API REST. Luego diseñas la página en **Elementor**, enlazando cada campo con
**Dynamic Tags → ACF Field**. Los datos y el diseño quedan claramente separados.

```
content doc → compose → flat ACF fields → POST { "acf": {...} } → WordPress → Elementor (Dynamic Tags)
```

## 1. Crear el field group en WordPress (una sola vez)

> **Importante:** el publisher *escribe valores* en los campos ACF; **no**
> crea las *definiciones* de los field-group. Los field groups de ACF no se pueden crear sobre
> la API REST de contenido, por lo que el grupo debe existir antes en WordPress, o el
> payload `acf` se descarta silenciosamente (obtendrías una entrada solo con título).

Dos formas de crearlo:

- **Importar el grupo listo** — WP admin → **ACF → Tools → Import Field Groups**
  → sube [`wordpress-acf/page-fields.acf.json`](../wordpress-acf/page-fields.acf.json).
  Coincide exactamente con `config/acf.yaml` y tiene **Show in REST API** activado.
- **O regístralo en tu repo de tema/sitio** mediante ACF local JSON (`acf-json/`),
  manteniendo los mismos nombres de campo.

Luego confirma que el grupo tenga **Show in REST API = On** (Field Group → Settings).
Elementor lee ACF directamente de la base de datos, así que Show-in-REST solo se necesita para que
*nuestra automatización* pueda escribir los valores.

## 2. Los campos

| ACF field | Type | Se llena desde |
| --------- | ---- | ----------- |
| `hero_eyebrow` | text | `eyebrow` opcional |
| `hero_heading` | text | el título |
| `hero_subheading` | textarea | tagline / "Quick Answer" |
| `hero_image` | image (ID) | primera imagen / `featured_image_query` |
| `hero_cta_label`, `hero_cta_url` | text | primer CTA del hero |
| `body` | wysiwyg | todas las secciones de prosa como HTML limpio (`<h2>`, `<p>`, `<ul>`, `<figure>` en línea) |
| `callout_title`, `callout_text` | text / wysiwyg | un `CALLOUT STAT` / `:::callout` |
| `faq` | repeater (`question`, `answer`) | el bloque de FAQ |
| `key_facts` | repeater (`label`, `value`) | un mapa `facts:` |
| `cta_heading`, `cta_text`, `cta_label`, `cta_url` | text / wysiwyg | un CTA de cierre |
| `page_subtitle` | text | `tagline`/`subtitle` |
| `seo_schema` | textarea | schema.org JSON-LD |

Para usar **tus propios** nombres de campo, edita el lado derecho de `config/acf.yaml`
(`flat:` y `top_level:`). El modelo de componentes es fijo; solo cambian los nombres.

## 3. Construir la página en Elementor con Dynamic Tags

Diseña la plantilla una vez (Elementor Pro):

1. Agrega un widget (Heading, Text Editor, Image…).
2. Haz clic en el icono **Dynamic Tags** (🛢️) del campo de contenido.
3. Elige **ACF Field** → selecciona el campo (p. ej. `hero_heading`).
4. Para `faq` / `key_facts` (repeaters): usa un **Loop Grid** / repeater, o un
   widget de acordeón que lea el repeater de ACF.

Cada nuevo documento que publicamos llena estos campos, y tu plantilla de Elementor los renderiza
automáticamente, sin rediseñar el layout por página.

## 4. Preview y publicación

```bash
wp-publish preview content/docdepruebainicial.docx     # writes output/<slug>/acf.json
wp-publish publish  content/docdepruebainicial.docx     # POSTs the flat acf payload (draft)
```

`acf.json` es exactamente lo que se envía como el objeto `acf` de la entrada; inspecciónalo para
confirmar el mapeo antes de publicar. `post_content` queda vacío.

### Seguridad
El publisher **se niega** a tocar una entrada existente con el mismo slug a menos que
pases `--update` (workflow: el toggle `update_existing`, desactivado por defecto), e incluso
así nunca cambia el status de una entrada existente ni vacía su contenido. Prueba documentos nuevos
bajo un slug nuevo.

## 5. Imágenes

Los campos de imagen de ACF almacenan **attachment IDs**. En el modo `--media library` el engine
busca coincidencias en tu Media Library y llena el ID; de lo contrario el campo queda vacío
y una advertencia lista los términos de búsqueda sugeridos para que una persona agregue el recurso.

## 6. SEO y schema

El title/description/focus keyword de SEO se escriben en la meta de RankMath/Yoast. El
schema.org JSON-LD se entrega como el campo `seo_schema`; publícalo dentro de una
etiqueta `<script type="application/ld+json">` en el header de tu Elementor/tema.

## 7. Grupo de campos independiente: Perfil de Experto

`wordpress-acf/profile.acf.json` es un grupo de campos ACF **importable** para
**páginas de expertos**, independiente de la salida del compositor. Impórtalo en
**Custom Fields → Tools → Import**.

- **Ubicación:** `Galápagos Page Type == Experts` (marcador post-meta
  `gp_page_type = experts`). Organizado con pestañas ACF (`type: "tab"`):
  **About** (imagen de foto — `return_format: id` —, `role`, `company`, + un wysiwyg de introducción),
  **Idiomas** (wysiwyg), **Destino** (repeater `destinations`, subcampo `text`
  wysiwyg), **Expertise** (repeater `expertise`, subcampo `text`), **Información
  Adicional** (repeater `additional_info`: `title` + `text`), **Redes Sociales**
  (repeater `social_networks`: `name` + `text`).
- `show_in_rest` está **activo**; las etiquetas están en español; las claves
  llevan el prefijo `field_exp_*` — verificado que **no colisionan** con
  `page-fields.acf.json` ni `page-builder.acf.json`.
- **El publisher NO rellena estos campos.** `config/acf.yaml` sólo mapea los
  *componentes compuestos* del compositor (hero / body / faq / cta /
  feature_sections …). Las páginas de expertos se cargan a mano en wp-admin, o
  con tu propio script escribiendo el objeto `acf` de la REST.

### Registrar la opción de page type "Experts"

La regla del marcador de page type del sitio es **`gp_page_type`** (etiqueta
"Galápagos Page Type"). El plugin `island-elementor-widgets` ya registra la
opción **Experts** (el valor de la regla de ubicación **y** el meta box del
editor). Si *no* usas ese plugin, agrega esto a `functions.php` o a un mu-plugin:

```php
// 1) Ofrecer "Experts" como valor de la regla de ubicación Galápagos Page Type.
add_filter('acf/location/rule_values/gp_page_type', function ($choices) {
    $choices['experts'] = 'Experts';
    return $choices;
});

// 2) Evaluar la regla contra la meta gp_page_type del post.
add_filter('acf/location/rule_match/gp_page_type', function ($match, $rule, $screen) {
    $post_id = $screen['post_id'] ?? 0;
    if (!$post_id) { return false; }
    $val = (string) get_post_meta((int) $post_id, 'gp_page_type', true);
    return ($rule['operator'] === '!=') ? ($val !== $rule['value']) : ($val === $rule['value']);
}, 10, 3);

// 3) (opcional) registrar el tipo de regla + un meta box para SETEAR el marcador.
add_filter('acf/location/rule_types', function ($choices) {
    $choices['Galápagos']['gp_page_type'] = 'Galápagos Page Type';
    return $choices;
});
```

> **Nombre del param:** el JSON del grupo usa `gp_page_type` — el slug real de la
> regla del sitio — no `galapagos_page_type`. Se refieren a la misma regla
> "Galápagos Page Type"; usa `gp_page_type` para que el grupo coincida con el
> meta box del editor y con las opciones Informative / Itineraries ya existentes.

### Alternativa: page type como término de taxonomía

Si modelas el page type como un **término de taxonomía** en vez de un marcador
post-meta, elimina la regla personalizada y usa la regla nativa `post_taxonomy`
de ACF — sin necesidad de filtro PHP:

```json
"location": [[{ "param": "post_taxonomy", "operator": "==", "value": "page-type:experts" }]]
```

(donde `page-type` es la taxonomía y `experts` el slug del término).
