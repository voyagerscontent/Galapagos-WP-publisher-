<?php
/**
 * Plugin Name: Island Guide Shortcodes
 * Description: Renders the "Island Guide Content" ACF group as shortcodes so the
 *              repeaters (wildlife, visitor sites, feature sections, FAQs, CTA…)
 *              can be dropped into Elementor "Shortcode" widgets. Simple fields
 *              (hero_title, geo_answer…) can stay as Elementor + ACF Dynamic Tags.
 * Version:     1.0.0
 * Author:      Galápagos Islands Travel
 *
 * USAGE in Elementor: add a "Shortcode" widget and paste any of —
 *   [island_quickfacts] [island_features] [island_wildlife]
 *   [island_visitor_sites] [island_travel] [island_faqs]
 *   [island_cta] [island_sources] [island_geo] [island_hero]
 * Each reads the current page's ACF fields. Pass id="123" to target another page.
 *
 * Install: zip this file (or the folder) and upload under Plugins → Add New →
 *          Upload, then Activate.  Palette lives in the CSS :root below.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Island_Guide_Shortcodes
{
    public function __construct()
    {
        foreach ([
            'island_hero', 'island_intro', 'island_geo', 'island_quickfacts',
            'island_features', 'island_wildlife', 'island_visitor_sites',
            'island_travel', 'island_faqs', 'island_cta', 'island_sources',
        ] as $tag) {
            $method = 'sc_' . substr($tag, 7);          // island_geo -> sc_geo
            add_shortcode($tag, [$this, $method]);
        }
    }

    /* ---- helpers ----------------------------------------------------- */

    private function pid($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        return $atts['id'] ? (int) $atts['id'] : get_the_ID();
    }

    private function f($pid, $name)
    {
        return function_exists('get_field') ? get_field($name, $pid) : null;
    }

    private function btn($label, $url, $ghost = false)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $label = trim((string) $label) ?: 'Learn more';
        return sprintf(
            '<a class="%s" href="%s">%s &rarr;</a>',
            $ghost ? 'btn ghost' : 'btn',
            esc_url($url),
            esc_html($label)
        );
    }

    private function img_src($img, $size = 'large')
    {
        if (empty($img)) {
            return '';
        }
        if (is_array($img)) {
            return $img['sizes'][$size] ?? ($img['url'] ?? '');
        }
        if (is_numeric($img)) {
            return wp_get_attachment_image_url((int) $img, $size) ?: '';
        }
        return is_string($img) ? $img : '';
    }

    private function img($id, $alt = '', $h = 200)
    {
        if ($id && ($src = $this->img_src($id))) {
            return sprintf(
                '<img class="isl-img" src="%s" alt="%s" loading="lazy" style="height:%dpx">',
                esc_url($src),
                esc_attr($alt),
                (int) $h
            );
        }
        return sprintf('<div class="ph" style="height:%dpx"><span>Image</span></div>', (int) $h);
    }

    private function head($title, $sub = '')
    {
        if (!$title && !$sub) {
            return '';
        }
        $s = $sub ? '<p class="sec-sub">' . esc_html($sub) . '</p>' : '';
        return '<div class="sec-head"><h2>' . esc_html($title) . '</h2>'
            . '<div class="orn"><span class="ln"></span>'
            . '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">'
            . '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/></svg>'
            . '<span class="ln"></span></div>' . $s . '</div>';
    }

    /** Print the palette/CSS once per page, wrapped so styles are scoped to .isl. */
    private function wrap($inner, $band = 'sand')
    {
        static $css_done = false;
        $css = '';
        if (!$css_done) {
            $css_done = true;
            $css = '<style>' . $this->css() . '</style>';
        }
        return $css . '<div class="isl"><section class="band ' . esc_attr($band) . '"><div class="wrap">'
            . $inner . '</div></section></div>';
    }

    /* ---- shortcodes -------------------------------------------------- */

    public function sc_hero($atts)
    {
        $pid = $this->pid($atts);
        $title = $this->f($pid, 'hero_title') ?: get_the_title($pid);
        $sub   = $this->f($pid, 'hero_subtitle');
        $author = $this->f($pid, 'author');
        $img = $this->f($pid, 'hero_image');
        $bg = $this->img_src($img, 'full');
        $style = $bg ? ' style="background-image:url(\'' . esc_url($bg) . '\')"' : '';
        ob_start(); ?>
        <div class="isl"><?php echo $this->styleonce(); ?>
          <header class="hero"><div class="hero-bg <?php echo $bg ? 'has-img' : ''; ?>"<?php echo $style; ?>></div>
            <div class="hero-inner">
              <span class="kicker">Galápagos Islands · Visitor Guide</span>
              <h1><?php echo esc_html($title); ?></h1>
              <?php if ($sub) : ?><p class="lead"><?php echo esc_html($sub); ?></p><?php endif; ?>
              <?php if ($author) : ?><p class="byline"><?php echo esc_html('By ' . $author); ?></p><?php endif; ?>
            </div></header></div>
        <?php return ob_get_clean();
    }

    public function sc_intro($atts)
    {
        $intro = $this->f($this->pid($atts), 'intro');
        if (!$intro) {
            return '';
        }
        return $this->wrap('<div class="rt center intro-lead">' . wp_kses_post($intro) . '</div>', 'sand');
    }

    public function sc_geo($atts)
    {
        $geo = $this->f($this->pid($atts), 'geo_answer');
        if (!$geo) {
            return '';
        }
        $inner = '<div class="geo"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64402c" stroke-width="1.4"><circle cx="12" cy="12" r="9"/><path d="M12 8v5l3 2"/></svg><p>'
            . esc_html($geo) . '</p></div>';
        return $this->wrap($inner, 'sand');
    }

    public function sc_quickfacts($atts)
    {
        $pid = $this->pid($atts);
        $rows = $this->f($pid, 'quick_facts') ?: [];
        if (!$rows) {
            return '';
        }
        $intro = $this->f($pid, 'quick_facts_intro');
        $body = $this->head($this->f($pid, 'quick_facts_title') ?: 'At a Glance');
        if ($intro) {
            $body .= '<div class="qf-intro rt">' . wp_kses_post($intro) . '</div>';
        }
        $body .= '<table class="facts"><thead><tr><th>Fact</th><th>Data</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $body .= '<tr><th>' . esc_html($r['label'] ?? '') . '</th><td>' . wp_kses_post($r['value'] ?? '') . '</td></tr>';
        }
        $body .= '</tbody></table>';
        return $this->wrap($body, 'sand');
    }

    public function sc_features($atts)
    {
        $rows = $this->f($this->pid($atts), 'feature_sections') ?: [];
        if (!$rows) {
            return '';
        }
        $body = $this->head('Why This Island Is Unlike Anywhere Else') . '<div class="grid g2">';
        foreach ($rows as $f) {
            $body .= '<article class="card dark-card">' . $this->img($f['image'] ?? 0, $f['image_alt'] ?? '', 180)
                . '<div class="card-body">'
                . (!empty($f['title']) ? '<h3>' . esc_html($f['title']) . '</h3>' : '')
                . (!empty($f['subtitle']) ? '<p class="subtitle">' . esc_html($f['subtitle']) . '</p>' : '')
                . '<div class="rt">' . wp_kses_post($f['content'] ?? '') . '</div>'
                . $this->btn($f['button_label'] ?? '', $f['button_url'] ?? '')
                . '</div></article>';
        }
        return $this->wrap($body . '</div>', 'brown');
    }

    public function sc_wildlife($atts)
    {
        $pid = $this->pid($atts);
        $rows = $this->f($pid, 'wildlife') ?: [];
        if (!$rows) {
            return '';
        }
        $intro = $this->f($pid, 'wildlife_intro');
        $body = $this->head($this->f($pid, 'wildlife_title') ?: 'Wildlife');
        if ($intro) {
            $body .= '<div class="rt center">' . wp_kses_post($intro) . '</div>';
        }
        $body .= '<div class="grid g2">';
        foreach ($rows as $w) {
            $meta = '';
            if (!empty($w['where_seen'])) {
                $meta .= '<li><b>Where:</b> ' . esc_html($w['where_seen']) . '</li>';
            }
            if (!empty($w['best_season'])) {
                $meta .= '<li><b>Season:</b> ' . esc_html($w['best_season']) . '</li>';
            }
            $sci = !empty($w['scientific_name']) ? '<span class="sci">' . esc_html($w['scientific_name']) . '</span>' : '';
            $body .= '<article class="card">' . $this->img($w['image'] ?? 0, $w['common_name'] ?? '', 170)
                . '<div class="card-body"><h3>' . esc_html($w['common_name'] ?? '') . ' ' . $sci . '</h3>'
                . '<div class="rt">' . wp_kses_post($w['description'] ?? '') . '</div>'
                . ($meta ? '<ul class="meta">' . $meta . '</ul>' : '')
                . $this->btn($w['button_label'] ?? '', $w['button_url'] ?? '', true)
                . '</div></article>';
        }
        return $this->wrap($body . '</div>', 'sand');
    }

    public function sc_visitor_sites($atts)
    {
        $pid = $this->pid($atts);
        $rows = $this->f($pid, 'visitor_sites') ?: [];
        if (!$rows) {
            return '';
        }
        $intro = $this->f($pid, 'visitor_sites_intro');
        $body = $this->head($this->f($pid, 'visitor_sites_title') ?: 'Visitor Sites');
        if ($intro) {
            $body .= '<div class="rt center light">' . wp_kses_post($intro) . '</div>';
        }
        $body .= '<div class="grid g3">';
        foreach ($rows as $v) {
            $at = $v['access_type'] ?? '';
            $badge = $at ? '<span class="badge ' . ($at === 'Cruise-only' ? 'cruise' : 'land') . '">' . esc_html($at) . '</span>' : '';
            $kv = '';
            foreach (['activities' => 'Activities', 'species_seen' => 'Species', 'access' => 'Access'] as $k => $lbl) {
                if (!empty($v[$k])) {
                    $kv .= '<p class="kv"><b>' . $lbl . ':</b> ' . esc_html($v[$k]) . '</p>';
                }
            }
            $body .= '<article class="card">' . $this->img($v['image'] ?? 0, $v['site_name'] ?? '', 160)
                . '<div class="card-body"><div class="card-top"><h3>' . esc_html($v['site_name'] ?? '') . '</h3>' . $badge . '</div>'
                . '<div class="rt">' . wp_kses_post($v['description'] ?? '') . '</div>' . $kv
                . $this->btn($v['button_label'] ?? '', $v['button_url'] ?? '', true)
                . '</div></article>';
        }
        return $this->wrap($body . '</div>', 'brown');
    }

    public function sc_travel($atts)
    {
        $t = $this->f($this->pid($atts), 'travel_information') ?: [];
        if (!$t) {
            return '';
        }
        $blocks = [
            [$t['getting_there_title'] ?? 'Getting There', $t['getting_there'] ?? '', $t['getting_there_button_label'] ?? '', $t['getting_there_button_url'] ?? ''],
            [$t['stay_visit_title'] ?? 'Best Time to Visit', $t['best_time'] ?? '', $t['best_time_button_label'] ?? '', $t['best_time_button_url'] ?? ''],
            ['Where to Stay', $t['accommodation'] ?? '', $t['accommodation_button_label'] ?? '', $t['accommodation_button_url'] ?? ''],
        ];
        $has = array_filter($blocks, fn($b) => trim((string) $b[1]) !== '');
        $notes = $t['travel_notes'] ?? '';
        if (!$has && !$notes) {
            return '';
        }
        $body = $this->head('Travel Information', 'Getting there, when to go, and where to stay');
        $dur = $t['recommended_duration'] ?? '';
        $diff = $t['difficulty'] ?? '';
        if ($dur || $diff) {
            $body .= '<div class="ti-meta">'
                . ($dur ? '<span><b>Recommended stay:</b> ' . esc_html($dur) . '</span>' : '')
                . ($diff ? '<span><b>Difficulty:</b> ' . esc_html($diff) . '</span>' : '')
                . '</div>';
        }
        $body .= '<div class="ti">';
        foreach ($blocks as [$h, $c, $bl, $bu]) {
            if (trim((string) $c) === '') {
                continue;
            }
            $body .= '<div class="ti-block">' . ($h ? '<h3>' . esc_html($h) . '</h3>' : '')
                . '<div class="rt">' . wp_kses_post($c) . '</div>' . $this->btn($bl, $bu) . '</div>';
        }
        if ($notes) {
            $body .= '<div class="ti-block"><h3>Good to Know</h3><div class="rt">' . wp_kses_post($notes) . '</div></div>';
        }
        return $this->wrap($body . '</div>', 'sand');
    }

    public function sc_faqs($atts)
    {
        $rows = $this->f($this->pid($atts), 'faqs') ?: [];
        if (!$rows) {
            return '';
        }
        $body = $this->head('Frequently Asked Questions') . '<div class="faqs">';
        foreach ($rows as $f) {
            $body .= '<details class="faq"><summary>' . esc_html($f['question'] ?? '') . '</summary>'
                . '<div class="rt">' . wp_kses_post($f['answer'] ?? '') . '</div></details>';
        }
        return $this->wrap($body . '</div>', 'sand');
    }

    public function sc_cta($atts)
    {
        $rows = $this->f($this->pid($atts), 'cta') ?: [];
        if (!$rows) {
            return '';
        }
        $body = $this->head('Plan Your Visit') . '<div class="grid g2 ctas">';
        foreach ($rows as $c) {
            $body .= '<article class="cta">'
                . (!empty($c['audience']) ? '<span class="cta-aud">' . esc_html($c['audience']) . '</span>' : '')
                . (!empty($c['title']) ? '<h3>' . esc_html($c['title']) . '</h3>' : '')
                . '<div class="rt">' . wp_kses_post($c['text'] ?? '') . '</div>'
                . $this->btn($c['button_label'] ?? '', $c['button_url'] ?? '')
                . '</article>';
        }
        return $this->wrap($body . '</div>', 'sand');
    }

    public function sc_sources($atts)
    {
        $pid = $this->pid($atts);
        $sources = $this->f($pid, 'sources') ?: [];
        $related = $this->f($pid, 'related_links') ?: [];
        if (!$sources && !$related) {
            return '';
        }
        $body = '<div class="src">';
        if ($related) {
            $body .= '<div><h4>Explore More</h4><ul class="links">';
            foreach ($related as $r) {
                $body .= '<li><a href="' . esc_url($r['url'] ?? '') . '">' . esc_html($r['label'] ?? '') . '</a></li>';
            }
            $body .= '</ul></div>';
        }
        if ($sources) {
            $body .= '<div><h4>Sources &amp; Citations</h4><ul class="links small">';
            foreach ($sources as $s) {
                $body .= '<li><a href="' . esc_url($s['url'] ?? '') . '">' . esc_html($s['label'] ?? '') . '</a></li>';
            }
            $body .= '</ul></div>';
        }
        return $this->wrap($body . '</div>', 'brown');
    }

    /** For the hero shortcode, emit CSS once without a band wrapper. */
    private function styleonce()
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;
        return '<style>' . $this->css() . '</style>';
    }

    private function css()
    {
        return <<<CSS
.isl{--sand:#ECE5DE;--card:#faf9f7;--ondark:#FCFAF9;--cta-border:#D3BAA3;--card-border:#DBCEC4;--brown:#64402c;--ink:#202020;color:var(--ink);font-family:'Open Sans',system-ui,sans-serif;line-height:1.65}
.isl h1,.isl h2,.isl h3,.isl h4{font-family:'Merriweather',Georgia,serif;font-weight:700;line-height:1.25}
.isl a{color:var(--brown)}
.isl .wrap{max-width:1100px;margin:0 auto;padding:0 24px}
.isl .band{padding:56px 0}
.isl .band.sand{background:var(--sand)}.isl .band.brown{background:var(--brown);color:var(--ondark)}
.isl .band.brown a{color:#e9d9c8}
.isl .hero{position:relative;min-height:440px;display:flex;align-items:flex-end;color:var(--ondark)}
.isl .hero-bg{position:absolute;inset:0;background-size:cover;background-position:center;background-image:linear-gradient(180deg,rgba(32,32,32,.15),rgba(100,64,44,.85)),radial-gradient(circle at 30% 30%,#7c5640,#3c2516)}
.isl .hero-inner{position:relative;max-width:1100px;margin:0 auto;padding:48px 24px;width:100%}
.isl .kicker{font-size:13px;letter-spacing:.18em;text-transform:uppercase;opacity:.85}
.isl .hero h1{font-size:46px;margin:10px 0 6px;max-width:18ch;font-style:italic}
.isl .hero .lead{max-width:60ch;opacity:.95;margin:0 0 6px}.isl .byline{opacity:.85;font-size:14px;margin:0}
.isl .sec-head{text-align:center;margin-bottom:36px}
.isl .sec-head h2{font-size:30px;font-style:italic;margin:0 0 12px;color:var(--brown)}
.isl .band.brown .sec-head h2{color:var(--ondark)}
.isl .orn{display:flex;align-items:center;justify-content:center;gap:14px;color:var(--cta-border)}
.isl .orn .ln{height:1px;width:90px;background:var(--cta-border);opacity:.8}
.isl .sec-sub{margin:14px auto 0;max-width:60ch;color:#6b5747;font-size:15px}.isl .band.brown .sec-sub{color:#e3d3c4}
.isl .geo{display:flex;gap:16px;align-items:flex-start;background:var(--card);border:1px solid var(--card-border);border-left:4px solid var(--brown);border-radius:10px;padding:22px 26px;font-size:17px}.isl .geo p{margin:0}
.isl table.facts{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--card-border);border-radius:10px;overflow:hidden}
.isl table.facts th,.isl table.facts td{text-align:left;padding:13px 18px;border-bottom:1px solid var(--card-border)}
.isl table.facts thead th{background:var(--brown);color:var(--ondark);font-family:'Merriweather',serif;font-size:14px}
.isl table.facts tbody th{width:34%;font-weight:700;color:var(--brown)}
.isl table.facts tr:last-child th,.isl table.facts tr:last-child td{border-bottom:0}
.isl .qf-intro{max-width:70ch;margin:0 auto 26px;text-align:center;color:#5a4636}
.isl .grid{display:grid;gap:24px}.isl .g2{grid-template-columns:repeat(2,1fr)}.isl .g3{grid-template-columns:repeat(3,1fr)}
.isl .card{background:var(--card);border:1px solid var(--card-border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 1px 2px rgba(60,40,25,.05)}
.isl .dark-card{background:#fbf6f1;border-color:#caa988}
.isl .band.brown .card,.isl .band.brown .dark-card{color:var(--ink)}
.isl .band.brown .card a:not(.btn),.isl .band.brown .dark-card a:not(.btn){color:var(--brown)}
.isl .card-body{padding:20px 22px;display:flex;flex-direction:column;gap:10px}
.isl .card h3{font-size:20px;font-style:italic;color:var(--brown);margin:0}
.isl .card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.isl .sci{display:block;font-style:italic;font-size:13px;color:#8a7058;font-weight:400}
.isl .subtitle{margin:0;color:#8a7058;font-size:14px}
.isl .rt{font-size:15px}.isl .rt :first-child{margin-top:0}.isl .rt :last-child{margin-bottom:0}
.isl .rt h3{font-size:17px;color:var(--brown);font-style:normal;margin:16px 0 6px}.isl .rt p{margin:0 0 10px}.isl .rt a{font-weight:600}
.isl .center{text-align:center;max-width:70ch;margin:0 auto 30px}.isl .light{color:#ecd9c8}
.isl .intro-lead{font-size:19px;color:#4a3a2c;margin:0 auto}
.isl .meta,.isl .kv{font-size:13.5px}.isl .meta{list-style:none;padding:0;margin:6px 0 0;color:#5a4636}.isl .meta li{margin:2px 0}.isl .kv{color:#5a4636;margin:4px 0 0}
.isl .badge{font-size:11px;letter-spacing:.05em;text-transform:uppercase;padding:4px 9px;border-radius:20px;white-space:nowrap;font-weight:700}
.isl .badge.land{background:#e7efe6;color:#3f6b46}.isl .badge.cruise{background:#e7ecf5;color:#3a5a8c}
.isl .btn{align-self:flex-start;background:var(--brown);color:var(--ondark);text-decoration:none;padding:10px 18px;border-radius:6px;font-size:14px;font-weight:600;margin-top:4px;border:1px solid var(--brown)}
.isl .btn.ghost{background:transparent;color:var(--brown);border:1px solid var(--cta-border)}
.isl .ph{background:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 12px,#dccdbb 12px,#dccdbb 24px);display:flex;align-items:center;justify-content:center;color:#8a7058;font-size:13px}
.isl .isl-img{width:100%;object-fit:cover;display:block}
.isl .ti-meta{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin:0 auto 24px}
.isl .ti-meta span{background:var(--card);border:1px solid var(--card-border);border-radius:30px;padding:8px 18px;font-size:14px}
.isl .ti{display:flex;flex-direction:column;gap:22px;max-width:820px;margin:0 auto}
.isl .ti-block{background:var(--card);border:1px solid var(--card-border);border-radius:12px;padding:24px 28px}
.isl .ti-block h3{margin:0 0 10px;color:var(--brown);font-size:22px;font-style:italic}
.isl .ctas .cta{background:var(--card);border:1.5px solid var(--cta-border);border-radius:14px;padding:28px 30px;display:flex;flex-direction:column;gap:12px}
.isl .cta-aud{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#9a7f66;font-weight:700}.isl .cta h3{margin:0;color:var(--brown);font-size:22px}
.isl .faqs{max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:12px}
.isl .faq{background:var(--card);border:1px solid var(--card-border);border-radius:10px;padding:6px 22px}
.isl .faq summary{cursor:pointer;font-family:'Merriweather',serif;font-weight:700;color:var(--brown);padding:14px 0;font-size:17px;list-style:none}
.isl .faq summary::-webkit-details-marker{display:none}.isl .faq summary::after{content:'+';float:right;font-size:22px;color:var(--cta-border)}
.isl .faq[open] summary::after{content:'\\2013'}.isl .faq .rt{padding:0 0 16px}
.isl .src{display:grid;grid-template-columns:1fr 1fr;gap:40px}.isl .src h4{font-size:18px;font-style:italic;margin:0 0 14px}
.isl .links{list-style:none;padding:0;margin:0}.isl .links li{padding:6px 0;border-bottom:1px solid rgba(255,255,255,.12)}.isl .links.small{font-size:13px}
@media(max-width:860px){.isl .g2,.isl .g3,.isl .src{grid-template-columns:1fr}.isl .hero h1{font-size:34px}}
CSS;
    }
}

new Island_Guide_Shortcodes();
