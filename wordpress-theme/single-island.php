<?php
/**
 * Template Name: Island Guide
 *
 * Renders an island page entirely from the "Island Guide Content" ACF group
 * (group_island_pages). Drop this file in your (child) theme and assign it to an
 * island page via  Page → Attributes → Template → "Island Guide".  Every field
 * the publisher fills is rendered here — the uploader only edits content (text,
 * images, links) in ACF; the layout stays consistent across all islands.
 *
 * To auto-apply it to every child of the "Galapagos Islands" page (id 10263)
 * without picking the template by hand, add this to the theme's functions.php:
 *
 *   add_filter('template_include', function ($t) {
 *       if (is_page() && wp_get_post_parent_id(get_the_ID()) === 10263) {
 *           $f = locate_template('single-island.php');
 *           if ($f) return $f;
 *       }
 *       return $t;
 *   });
 *
 * Design tokens (palette) live in :root below — swap them to restyle globally.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ----------------------------------------------------------------------- *
 *  Small render helpers (guarded so a theme reload never redeclares them)
 * ----------------------------------------------------------------------- */
if (!function_exists('island_btn')) {
    function island_btn($label, $url, $ghost = false)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $label = trim((string) $label) ?: 'Learn more';
        $cls = $ghost ? 'btn ghost' : 'btn';
        return sprintf(
            '<a class="%s" href="%s">%s &rarr;</a>',
            esc_attr($cls),
            esc_url($url),
            esc_html($label)
        );
    }
}

if (!function_exists('island_img')) {
    /** ACF image fields store an attachment ID; fall back to a placeholder box. */
    function island_img($id, $alt = '', $height = 200, $class = '')
    {
        if ($id && ($src = wp_get_attachment_image_url((int) $id, 'large'))) {
            return sprintf(
                '<img class="isl-img %s" src="%s" alt="%s" loading="lazy" style="height:%dpx">',
                esc_attr($class),
                esc_url($src),
                esc_attr($alt),
                (int) $height
            );
        }
        return sprintf(
            '<div class="ph %s" style="height:%dpx"><span>%s</span></div>',
            esc_attr($class),
            (int) $height,
            esc_html__('Image', 'island')
        );
    }
}

if (!function_exists('island_head')) {
    /** Centered section header with the ornament rule, matching the mockup. */
    function island_head($title, $sub = '')
    {
        if (!$title && !$sub) {
            return '';
        }
        $subhtml = $sub ? '<p class="sec-sub">' . esc_html($sub) . '</p>' : '';
        return '<div class="sec-head"><h2>' . esc_html($title) . '</h2>'
            . '<div class="orn"><span class="ln"></span>'
            . '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">'
            . '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/></svg>'
            . '<span class="ln"></span></div>' . $subhtml . '</div>';
    }
}

get_header();

while (have_posts()) :
    the_post();

    // --- pull every ACF field once -------------------------------------- //
    $hero_title   = get_field('hero_title') ?: get_the_title();
    $hero_sub     = get_field('hero_subtitle');
    $hero_img     = get_field('hero_image');        // attachment ID
    $hero_alt     = get_field('hero_image_alt');
    $author       = get_field('author');
    $geo          = get_field('geo_answer');

    $qf_title     = get_field('quick_facts_title');
    $qf_intro     = get_field('quick_facts_intro'); // wysiwyg HTML
    $quick_facts  = get_field('quick_facts') ?: [];

    $features     = get_field('feature_sections') ?: [];

    $wild_title   = get_field('wildlife_title');
    $wild_intro   = get_field('wildlife_intro');
    $wildlife     = get_field('wildlife') ?: [];

    $vs_title     = get_field('visitor_sites_title');
    $vs_intro     = get_field('visitor_sites_intro');
    $visitor      = get_field('visitor_sites') ?: [];

    $travel       = get_field('travel_information') ?: [];

    $faqs         = get_field('faqs') ?: [];
    $ctas         = get_field('cta') ?: [];
    $sources      = get_field('sources') ?: [];
    $related      = get_field('related_links') ?: [];

    $hero_bg = $hero_img ? wp_get_attachment_image_url((int) $hero_img, 'full') : '';
    ?>

    <style>
    .isl{--sand:#ECE5DE;--card:#faf9f7;--ondark:#FCFAF9;--cta-border:#D3BAA3;
         --card-border:#DBCEC4;--brown:#64402c;--ink:#202020;
         color:var(--ink);background:var(--sand);font-family:'Open Sans',system-ui,sans-serif;line-height:1.65}
    .isl h1,.isl h2,.isl h3,.isl h4{font-family:'Merriweather',Georgia,serif;font-weight:700;line-height:1.25}
    .isl a{color:var(--brown)}
    .isl .wrap{max-width:1100px;margin:0 auto;padding:0 24px}
    .isl .band{padding:64px 0}
    .isl .band.sand{background:var(--sand)}.isl .band.brown{background:var(--brown);color:var(--ondark)}
    .isl .band.brown a{color:#e9d9c8}
    .isl .hero{position:relative;min-height:460px;display:flex;align-items:flex-end;color:var(--ondark)}
    .isl .hero-bg{position:absolute;inset:0;background-size:cover;background-position:center;
      background-image:linear-gradient(180deg,rgba(32,32,32,.15),rgba(100,64,44,.85)),radial-gradient(circle at 30% 30%,#7c5640,#3c2516)}
    .isl .hero-bg.has-img::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(32,32,32,.1),rgba(40,25,15,.7))}
    .isl .hero-inner{position:relative;max-width:1100px;margin:0 auto;padding:48px 24px;width:100%}
    .isl .kicker{font-size:13px;letter-spacing:.18em;text-transform:uppercase;opacity:.85}
    .isl .hero h1{font-size:48px;margin:10px 0 6px;max-width:18ch;font-style:italic}
    .isl .hero .lead{max-width:60ch;opacity:.95;margin:0 0 6px}
    .isl .byline{opacity:.85;font-size:14px;margin:0}
    .isl .sec-head{text-align:center;margin-bottom:38px}
    .isl .sec-head h2{font-size:30px;font-style:italic;margin:0 0 12px;color:var(--brown)}
    .isl .band.brown .sec-head h2{color:var(--ondark)}
    .isl .orn{display:flex;align-items:center;justify-content:center;gap:14px;color:var(--cta-border)}
    .isl .orn .ln{height:1px;width:90px;background:var(--cta-border);opacity:.8}
    .isl .sec-sub{margin:14px auto 0;max-width:60ch;color:#6b5747;font-size:15px}
    .isl .band.brown .sec-sub{color:#e3d3c4}
    .isl .geo{display:flex;gap:16px;align-items:flex-start;background:var(--card);border:1px solid var(--card-border);
      border-left:4px solid var(--brown);border-radius:10px;padding:22px 26px;font-size:17px}
    .isl .geo p{margin:0}
    .isl table.facts{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--card-border);border-radius:10px;overflow:hidden}
    .isl table.facts th,.isl table.facts td{text-align:left;padding:13px 18px;border-bottom:1px solid var(--card-border)}
    .isl table.facts thead th{background:var(--brown);color:var(--ondark);font-family:'Merriweather',serif;font-size:14px}
    .isl table.facts tbody th{width:34%;font-family:'Open Sans',sans-serif;font-weight:700;color:var(--brown)}
    .isl table.facts tr:last-child th,.isl table.facts tr:last-child td{border-bottom:0}
    .isl .qf-intro{max-width:70ch;margin:0 auto 26px;text-align:center;color:#5a4636}
    .isl .grid{display:grid;gap:24px}
    .isl .g2{grid-template-columns:repeat(2,1fr)}.isl .g3{grid-template-columns:repeat(3,1fr)}
    .isl .card{background:var(--card);border:1px solid var(--card-border);border-radius:12px;overflow:hidden;
      display:flex;flex-direction:column;box-shadow:0 1px 2px rgba(60,40,25,.05)}
    .isl .dark-card{background:#fbf6f1;border-color:#caa988}
    .isl .band.brown .card,.isl .band.brown .dark-card{color:var(--ink)}
    .isl .band.brown .card a,.isl .band.brown .dark-card a{color:var(--brown)}
    .isl .card-body{padding:20px 22px;display:flex;flex-direction:column;gap:10px}
    .isl .card h3{font-size:20px;font-style:italic;color:var(--brown);margin:0}
    .isl .card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .isl .sci{display:block;font-style:italic;font-size:13px;color:#8a7058;font-weight:400}
    .isl .subtitle{margin:0;color:#8a7058;font-size:14px}
    .isl .rt{font-size:15px}.isl .rt :first-child{margin-top:0}.isl .rt :last-child{margin-bottom:0}
    .isl .rt h3{font-size:17px;color:var(--brown);font-style:normal;margin:16px 0 6px}
    .isl .rt p{margin:0 0 10px}.isl .rt a{font-weight:600}
    .isl .center{text-align:center;max-width:70ch;margin:0 auto 30px}.isl .light{color:#ecd9c8}
    .isl .meta,.isl .kv{font-size:13.5px}.isl .meta{list-style:none;padding:0;margin:6px 0 0;color:#5a4636}
    .isl .meta li{margin:2px 0}.isl .kv{color:#5a4636;margin:4px 0 0}
    .isl .badge{font-size:11px;letter-spacing:.05em;text-transform:uppercase;padding:4px 9px;border-radius:20px;white-space:nowrap;font-weight:700}
    .isl .badge.land{background:#e7efe6;color:#3f6b46}.isl .badge.cruise{background:#e7ecf5;color:#3a5a8c}
    .isl .btn{align-self:flex-start;background:var(--brown);color:var(--ondark);text-decoration:none;
      padding:10px 18px;border-radius:6px;font-size:14px;font-weight:600;margin-top:4px;border:1px solid var(--brown)}
    .isl .btn.ghost{background:transparent;color:var(--brown);border:1px solid var(--cta-border)}
    .isl .ph{background:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 12px,#dccdbb 12px,#dccdbb 24px);
      display:flex;align-items:center;justify-content:center;color:#8a7058;font-size:13px}
    .isl .isl-img{width:100%;object-fit:cover;display:block}
    .isl .ti{display:flex;flex-direction:column;gap:22px;max-width:820px;margin:0 auto}
    .isl .ti-block{background:var(--card);border:1px solid var(--card-border);border-radius:12px;padding:24px 28px}
    .isl .ti-block h3{margin:0 0 10px;color:var(--brown);font-size:22px;font-style:italic}
    .isl .ctas .cta{background:var(--card);border:1.5px solid var(--cta-border);border-radius:14px;padding:28px 30px;display:flex;flex-direction:column;gap:12px}
    .isl .cta-aud{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#9a7f66;font-weight:700}
    .isl .cta h3{margin:0;color:var(--brown);font-size:22px}
    .isl .faqs{max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:12px}
    .isl .faq{background:var(--card);border:1px solid var(--card-border);border-radius:10px;padding:6px 22px}
    .isl .faq summary{cursor:pointer;font-family:'Merriweather',serif;font-weight:700;color:var(--brown);padding:14px 0;font-size:17px;list-style:none}
    .isl .faq summary::-webkit-details-marker{display:none}
    .isl .faq summary::after{content:'+';float:right;font-size:22px;color:var(--cta-border)}
    .isl .faq[open] summary::after{content:'\2013'}
    .isl .faq .rt{padding:0 0 16px}
    .isl .src{display:grid;grid-template-columns:1fr 1fr;gap:40px}
    .isl .src h4{font-size:18px;font-style:italic;margin:0 0 14px}
    .isl .links{list-style:none;padding:0;margin:0}.isl .links li{padding:6px 0;border-bottom:1px solid rgba(255,255,255,.12)}
    .isl .links.small{font-size:13px}
    @media(max-width:860px){.isl .g2,.isl .g3,.isl .src{grid-template-columns:1fr}.isl .hero h1{font-size:34px}}
    </style>

    <main class="isl">

        <!-- HERO -------------------------------------------------------- -->
        <header class="hero">
            <div class="hero-bg <?php echo $hero_bg ? 'has-img' : ''; ?>"
                 <?php if ($hero_bg) : ?>style="background-image:url('<?php echo esc_url($hero_bg); ?>')"<?php endif; ?>></div>
            <div class="hero-inner">
                <span class="kicker"><?php esc_html_e('Galápagos Islands · Visitor Guide', 'island'); ?></span>
                <h1><?php echo esc_html($hero_title); ?></h1>
                <?php if ($hero_sub) : ?><p class="lead"><?php echo esc_html($hero_sub); ?></p><?php endif; ?>
                <?php if ($author) : ?><p class="byline"><?php echo esc_html('By ' . $author); ?></p><?php endif; ?>
            </div>
        </header>

        <!-- GEO / AI ANSWER --------------------------------------------- -->
        <?php if ($geo) : ?>
        <section class="band sand"><div class="wrap">
            <div class="geo">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64402c" stroke-width="1.4"><circle cx="12" cy="12" r="9"/><path d="M12 8v5l3 2"/></svg>
                <p><?php echo esc_html($geo); ?></p>
            </div>
        </div></section>
        <?php endif; ?>

        <!-- QUICK FACTS ------------------------------------------------- -->
        <?php if ($quick_facts) : ?>
        <section class="band sand"><div class="wrap">
            <?php echo island_head($qf_title ?: 'At a Glance', ''); ?>
            <?php if ($qf_intro) : ?><div class="qf-intro rt"><?php echo wp_kses_post($qf_intro); ?></div><?php endif; ?>
            <table class="facts">
                <thead><tr><th><?php esc_html_e('Fact', 'island'); ?></th><th><?php esc_html_e('Data', 'island'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($quick_facts as $f) : ?>
                    <tr><th><?php echo esc_html($f['label'] ?? ''); ?></th><td><?php echo wp_kses_post($f['value'] ?? ''); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></section>
        <?php endif; ?>

        <!-- FEATURE SECTIONS -------------------------------------------- -->
        <?php if ($features) : ?>
        <section class="band brown"><div class="wrap">
            <?php echo island_head(__('Why This Island Is Unlike Anywhere Else', 'island')); ?>
            <div class="grid g2">
            <?php foreach ($features as $f) : ?>
                <article class="card dark-card">
                    <?php echo island_img($f['image'] ?? 0, $f['image_alt'] ?? '', 180); ?>
                    <div class="card-body">
                        <?php if (!empty($f['title'])) : ?><h3><?php echo esc_html($f['title']); ?></h3><?php endif; ?>
                        <?php if (!empty($f['subtitle'])) : ?><p class="subtitle"><?php echo esc_html($f['subtitle']); ?></p><?php endif; ?>
                        <div class="rt"><?php echo wp_kses_post($f['content'] ?? ''); ?></div>
                        <?php echo island_btn($f['button_label'] ?? '', $f['button_url'] ?? ''); ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </div></section>
        <?php endif; ?>

        <!-- WILDLIFE ---------------------------------------------------- -->
        <?php if ($wildlife) : ?>
        <section class="band sand"><div class="wrap">
            <?php echo island_head($wild_title ?: 'Wildlife'); ?>
            <?php if ($wild_intro) : ?><div class="rt center"><?php echo wp_kses_post($wild_intro); ?></div><?php endif; ?>
            <div class="grid g2">
            <?php foreach ($wildlife as $w) :
                $meta = '';
                if (!empty($w['where_seen']))  { $meta .= '<li><b>' . esc_html__('Where:', 'island') . '</b> ' . esc_html($w['where_seen']) . '</li>'; }
                if (!empty($w['best_season'])) { $meta .= '<li><b>' . esc_html__('Season:', 'island') . '</b> ' . esc_html($w['best_season']) . '</li>'; }
            ?>
                <article class="card">
                    <?php echo island_img($w['image'] ?? 0, $w['common_name'] ?? '', 170); ?>
                    <div class="card-body">
                        <h3><?php echo esc_html($w['common_name'] ?? ''); ?>
                            <?php if (!empty($w['scientific_name'])) : ?><span class="sci"><?php echo esc_html($w['scientific_name']); ?></span><?php endif; ?>
                        </h3>
                        <div class="rt"><?php echo wp_kses_post($w['description'] ?? ''); ?></div>
                        <?php if ($meta) : ?><ul class="meta"><?php echo $meta; ?></ul><?php endif; ?>
                        <?php echo island_btn($w['button_label'] ?? '', $w['button_url'] ?? '', true); ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </div></section>
        <?php endif; ?>

        <!-- VISITOR SITES (cards) --------------------------------------- -->
        <?php if ($visitor) : ?>
        <section class="band brown"><div class="wrap">
            <?php echo island_head($vs_title ?: 'Visitor Sites'); ?>
            <?php if ($vs_intro) : ?><div class="rt center light"><?php echo wp_kses_post($vs_intro); ?></div><?php endif; ?>
            <div class="grid g3">
            <?php foreach ($visitor as $v) :
                $at = $v['access_type'] ?? '';
                $bcls = ($at === 'Cruise-only') ? 'cruise' : 'land';
            ?>
                <article class="card">
                    <?php echo island_img($v['image'] ?? 0, $v['site_name'] ?? '', 160); ?>
                    <div class="card-body">
                        <div class="card-top">
                            <h3><?php echo esc_html($v['site_name'] ?? ''); ?></h3>
                            <?php if ($at) : ?><span class="badge <?php echo esc_attr($bcls); ?>"><?php echo esc_html($at); ?></span><?php endif; ?>
                        </div>
                        <div class="rt"><?php echo wp_kses_post($v['description'] ?? ''); ?></div>
                        <?php if (!empty($v['species_seen'])) : ?><p class="kv"><b><?php esc_html_e('Species:', 'island'); ?></b> <?php echo esc_html($v['species_seen']); ?></p><?php endif; ?>
                        <?php echo island_btn($v['button_label'] ?? '', $v['button_url'] ?? '', true); ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </div></section>
        <?php endif; ?>

        <!-- TRAVEL INFORMATION ------------------------------------------ -->
        <?php
        if ($travel) :
            $ti_blocks = [
                [$travel['getting_there_title'] ?? 'Getting There', $travel['getting_there'] ?? '', $travel['getting_there_button_label'] ?? '', $travel['getting_there_button_url'] ?? ''],
                [$travel['stay_visit_title'] ?? 'Best Time to Visit', $travel['best_time'] ?? '', $travel['best_time_button_label'] ?? '', $travel['best_time_button_url'] ?? ''],
                [__('Where to Stay', 'island'), $travel['accommodation'] ?? '', $travel['accommodation_button_label'] ?? '', $travel['accommodation_button_url'] ?? ''],
            ];
            $has = array_filter($ti_blocks, fn($b) => trim((string) $b[1]) !== '');
            if ($has) :
        ?>
        <section class="band sand"><div class="wrap">
            <?php echo island_head(__('Travel Information', 'island'), __('Getting there, when to go, and where to stay', 'island')); ?>
            <div class="ti">
            <?php foreach ($ti_blocks as [$t, $c, $bl, $bu]) :
                if (trim((string) $c) === '') { continue; } ?>
                <div class="ti-block">
                    <?php if ($t) : ?><h3><?php echo esc_html($t); ?></h3><?php endif; ?>
                    <div class="rt"><?php echo wp_kses_post($c); ?></div>
                    <?php echo island_btn($bl, $bu); ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div></section>
        <?php endif; endif; ?>

        <!-- CTA --------------------------------------------------------- -->
        <?php if ($ctas) : ?>
        <section class="band sand"><div class="wrap">
            <?php echo island_head(sprintf(__('Plan Your Visit to %s', 'island'), get_the_title())); ?>
            <div class="grid g2 ctas">
            <?php foreach ($ctas as $c) : ?>
                <article class="cta">
                    <?php if (!empty($c['audience'])) : ?><span class="cta-aud"><?php echo esc_html($c['audience']); ?></span><?php endif; ?>
                    <?php if (!empty($c['title'])) : ?><h3><?php echo esc_html($c['title']); ?></h3><?php endif; ?>
                    <div class="rt"><?php echo wp_kses_post($c['text'] ?? ''); ?></div>
                    <?php echo island_btn($c['button_label'] ?? '', $c['button_url'] ?? ''); ?>
                </article>
            <?php endforeach; ?>
            </div>
        </div></section>
        <?php endif; ?>

        <!-- FAQ --------------------------------------------------------- -->
        <?php if ($faqs) : ?>
        <section class="band sand"><div class="wrap">
            <?php echo island_head(__('Frequently Asked Questions', 'island')); ?>
            <div class="faqs">
            <?php foreach ($faqs as $f) : ?>
                <details class="faq">
                    <summary><?php echo esc_html($f['question'] ?? ''); ?></summary>
                    <div class="rt"><?php echo wp_kses_post($f['answer'] ?? ''); ?></div>
                </details>
            <?php endforeach; ?>
            </div>
        </div></section>
        <?php endif; ?>

        <!-- EXPLORE MORE / SOURCES -------------------------------------- -->
        <?php if ($related || $sources) : ?>
        <section class="band brown"><div class="wrap">
            <div class="src">
                <?php if ($related) : ?>
                <div>
                    <h4><?php esc_html_e('Explore More', 'island'); ?></h4>
                    <ul class="links">
                    <?php foreach ($related as $r) : ?>
                        <li><a href="<?php echo esc_url($r['url'] ?? ''); ?>"><?php echo esc_html($r['label'] ?? ''); ?></a></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if ($sources) : ?>
                <div>
                    <h4><?php esc_html_e('Sources &amp; Citations', 'island'); ?></h4>
                    <ul class="links small">
                    <?php foreach ($sources as $s) : ?>
                        <li><a href="<?php echo esc_url($s['url'] ?? ''); ?>"><?php echo esc_html($s['label'] ?? ''); ?></a></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div></section>
        <?php endif; ?>

    </main>

<?php
endwhile;

get_footer();
