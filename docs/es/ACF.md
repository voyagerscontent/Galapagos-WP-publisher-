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

## 7. Grupo de perfil de experto (`profile.acf.json`)

[`wordpress-acf/profile.acf.json`](../../wordpress-acf/profile.acf.json) es un
field group importable — **Perfil de Experto** (`group_expert_profile`) — para
páginas de perfil de expertos / guías. Se importa como cualquier otro grupo: WP
admin → **ACF → Tools → Import Field Groups**. Las etiquetas están en español,
`show_in_rest` está activado y todas las claves llevan el prefijo `field_exp_*`,
de modo que no pueden colisionar con `page-fields.acf.json` (`f_*`) ni con
`page-builder.acf.json` (`f_*`).

> **El publisher no rellena estos campos.** `config/acf.yaml` solo mapea el modelo
> de componentes compuestos (hero, body, callout, faq, key_facts, cta…) sobre el
> grupo plano de página. El perfil de experto se edita a mano en el admin de
> WordPress; existe para que el tema/Elementor tenga datos estructurados que
> renderizar. Publicar un documento en una página Experts llena el grupo de
> *página*, nunca este.

Campos, organizados con pestañas ACF (`type: "tab"`):

| Pestaña | Campo | Tipo |
| ------- | ----- | ---- |
| About | `about_image` | image (`return_format: "id"`) |
| About | `about_text` | wysiwyg |
| Idiomas | `languages` | wysiwyg |
| Destino | `destinations` | repeater → `text` (wysiwyg) |
| Expertise | `expertise` | repeater → `text` (text) |
| Información Adicional | `additional_info` | repeater → `title` (text), `text` (wysiwyg) |
| Redes Sociales | `social_networks` | repeater → `name` (text), `text` (text) |

Recorre los repeaters en Elementor con un Loop Grid o un widget que lea
repeaters, igual que `faq` / `key_facts` en §3.

### Ubicación: Galápagos Page Type == Experts

El grupo se engancha mediante la regla de ubicación propia de este sitio, la
misma que usan `informative-page.json` e `itineraries-page.json`:

```json
[ { "param": "gp_page_type", "operator": "==", "value": "experts" } ]
```

La regla se muestra como **"Galápagos Page Type"** en la interfaz de ACF, pero su
*slug de param* es `gp_page_type`: esa es la clave registrada en
[`wordpress-plugin/island-elementor-widgets.php`](../../wordpress-plugin/island-elementor-widgets.php).
Un grupo ubicado en `galapagos_page_type` nunca se engancharía, porque no hay
ninguna regla registrada con ese slug. Si prefieres el slug largo, renómbralo en
los cuatro sitios a la vez: la clave de `rule_types` del plugin, los dos nombres
de filtro (`acf/location/rule_values/<slug>`, `acf/location/rule_match/<slug>`),
el nombre de `register_post_meta` y el `param` de cada `*.acf.json`.

### Registrar la opción "Experts"

El desplegable detrás de esa regla solo ofrecía *Informative* e *Itineraries*. El
plugin incluido ahora también registra *Experts* (`rule_values`, la caja lateral
del editor y la whitelist de guardado). Si no usas ese plugin, pega esto en
`functions.php` o en un mu-plugin — es autocontenido e idempotente, así que
también es seguro junto al plugin:

```php
<?php
// Galápagos Page Type → "Experts": registra la meta marcadora, la regla de
// ubicación de ACF y su callback de match. Seguro junto a
// island-elementor-widgets.php.

add_action('init', function () {
    register_post_meta('page', 'gp_page_type', [
        'type'          => 'string',
        'single'        => true,
        'show_in_rest'  => true,
        'default'       => '',
        'auth_callback' => function () {
            return current_user_can('edit_pages');
        },
    ]);
});

// Deja disponible la regla en sí (no hace nada si ya está registrada).
add_filter('acf/location/rule_types', function ($choices) {
    $choices['Galápagos']['gp_page_type'] = 'Galápagos Page Type';
    return $choices;
});

// Añade "Experts" al desplegable de valores de la regla.
add_filter('acf/location/rule_values/gp_page_type', function ($choices) {
    $choices['experts'] = 'Experts';
    return $choices;
});

// Compara la regla contra la post meta gp_page_type.
add_filter('acf/location/rule_match/gp_page_type', function ($match, $rule, $screen) {
    $post_id = $screen['post_id'] ?? 0;
    if (!$post_id) {
        return false;
    }
    $val = (string) get_post_meta((int) $post_id, 'gp_page_type', true);
    return ($rule['operator'] === '!=') ? ($val !== $rule['value']) : ($val === $rule['value']);
}, 10, 3);
```

Marca una página como perfil de experto poniendo la meta `gp_page_type` en
`experts`: con la caja **Galápagos Page Type** de la barra lateral, o sobre la
API REST (`"meta": { "gp_page_type": "experts" }`).

### Alternativa: ubicar por taxonomía

Si tu page type es un **término de taxonomía** en vez de un marcador en post
meta, sáltate la regla propia y usa la regla nativa `post_taxonomy` de ACF — sin
PHP:

```json
[ { "param": "post_taxonomy", "operator": "==", "value": "page_type:experts" } ]
```

El valor es `<taxonomía>:<slug-del-término>` (p. ej. `page_type:experts`). Dos
advertencias: la taxonomía debe estar registrada para el post type donde viven
los perfiles, y ACF evalúa la regla contra los términos ya *guardados* en la
entrada — una página creada por REST necesita el término asignado en la misma
petición (`"page_type": [<term_id>]`, con `show_in_rest` activado en la
taxonomía) o el grupo no aparecerá hasta que se asigne el término y se recargue
el editor.
