<?php
/**
 * Plugin Name: Island Elementor Widgets (example)
 * Description: GENERIC EXAMPLE — a native Elementor widget that loops the ACF
 *              "wildlife" repeater and exposes visual style controls (columns,
 *              colors, borders, typography, buttons, image). Shows how a custom
 *              widget gives Elementor-side visual control over a repeater without
 *              a paid add-on. The same pattern applies to visitor_sites, features…
 * Version:     0.1.0
 * Author:      Galápagos Islands Travel
 *
 * Install like any plugin (Plugins → Add New → Upload → Activate). Requires
 * Elementor + ACF. In Elementor you'll find the widget "Island Wildlife" under
 * the "General" category.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve an ACF image sub-field to a URL regardless of its Return Format
 * (Image ID, Image Array, or Image URL). This is why "the image is set but
 * doesn't show" — the widget must not assume one format.
 */
if (!function_exists('island_ew_image_src')) {
    function island_ew_image_src($img, $size = 'large')
    {
        if (empty($img)) {
            return '';
        }
        if (is_array($img)) {                         // Return Format: Image Array
            return $img['sizes'][$size] ?? ($img['url'] ?? '');
        }
        if (is_numeric($img)) {                        // Return Format: Image ID
            return wp_get_attachment_image_url((int) $img, $size) ?: '';
        }
        return is_string($img) ? $img : '';            // Return Format: Image URL
    }
}

/** Split a quick-fact "value" into (title, detail): "Cerro Crocker, 864 m" -> ["Cerro Crocker","864 m"]. */
if (!function_exists('island_ew_split')) {
    function island_ew_split($v)
    {
        $v = trim((string) $v);
        foreach (['—', '–', ', ', '('] as $d) {
            $i = mb_strpos($v, $d);
            if ($i !== false && $i > 0) {
                return [
                    trim(mb_substr($v, 0, $i), " ,(—–"),
                    trim(mb_substr($v, $i + mb_strlen($d)), " ,)—–"),
                ];
            }
        }
        return [$v, ''];
    }
}

add_action('elementor/widgets/register', function ($widgets_manager) {
    if (!did_action('elementor/loaded')) {
        return;
    }

    // Declare the widget classes only once. Elementor can fire this hook more
    // than once per request (editor + preview, etc.); re-declaring a class is a
    // compile-time fatal ("Cannot redeclare class") that white-screens the site.
    if (!class_exists('Island_Wildlife_Widget')) {

    class Island_Wildlife_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_wildlife';
        }

        public function get_title()
        {
            return 'Island Wildlife';
        }

        public function get_icon()
        {
            return 'eicon-image-box';
        }

        public function get_categories()
        {
            return ['general'];
        }

        private function tag($v, $allowed, $default)
        {
            return in_array($v, $allowed, true) ? $v : $default;
        }

        protected function register_controls()
        {
            $tags = ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div'];
            $align = [
                'left' => ['title' => 'Left', 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => 'Center', 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => 'Right', 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => 'Justify', 'icon' => 'eicon-text-align-justify'],
            ];

            /* CONTENT */
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('card_style', [
                'label' => 'Card design', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'overlay',
                'options' => ['overlay' => 'Overlay (photo-forward)', 'editorial' => 'Editorial (index)', 'offset' => 'Offset (card over photo)'],
            ]);
            $this->add_responsive_control('columns', [
                'label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3',
                'tablet_default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'condition' => ['card_style!' => 'editorial'],
                'selectors' => ['{{WRAPPER}} .iw2-grid' => 'grid-template-columns:repeat({{VALUE}},1fr)'],
            ]);
            $this->add_control('reveal', [
                'label' => 'Long text', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'expand',
                'options' => ['expand' => 'Clamp + reveal (hover / tap)', 'full' => 'Always show full'],
                'description' => 'Reveal shows a teaser; the full text opens on hover (desktop) or tap (mobile).',
            ]);
            $this->add_control('teaser_lines', [
                'label' => 'Teaser lines', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 3, 'min' => 1, 'max' => 20,
                'condition' => ['reveal' => 'expand'],
                'selectors' => ['{{WRAPPER}} .iw2-desc.clip' => '--tl:{{VALUE}}'],
            ]);
            $this->add_control('title_tag', ['label' => 'Name tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h4', 'options' => $tags]);
            $this->add_control('show_sci', ['label' => 'Show scientific name', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_meta', ['label' => 'Show where / season', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_desc', ['label' => 'Show description', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_btn', ['label' => 'Show button', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('btn_text', ['label' => 'Button fallback text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Read more']);
            $this->end_controls_section();

            /* CARD */
            $this->start_controls_section('card', ['label' => 'Card', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('gap', [
                'label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 50]],
                'default' => ['size' => 18, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .iw2-grid' => 'gap:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_responsive_control('card_h', [
                'label' => 'Photo height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 140, 'max' => 620]],
                'default' => ['size' => 340, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .iw2-ov' => 'min-height:{{SIZE}}{{UNIT}}',
                    '{{WRAPPER}} .iw2-of .iw2-ph,{{WRAPPER}} .iw2-ed .iw2-ph' => 'height:calc({{SIZE}}{{UNIT}} * .6)',
                ],
            ]);
            $this->add_control('img_fit', [
                'label' => 'Image fit', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'cover',
                'options' => ['cover' => 'Cover (fill)', 'contain' => 'Contain (fit)', 'auto' => 'Auto (original)'],
                'selectors' => [
                    '{{WRAPPER}} .iw2-ov' => 'background-size:{{VALUE}}',
                    '{{WRAPPER}} .iw2-ph' => 'background-size:{{VALUE}}',
                ],
            ]);
            $this->add_responsive_control('img_pos', [
                'label' => 'Image position', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'center center',
                'options' => [
                    'center center' => 'Center', 'top center' => 'Top', 'bottom center' => 'Bottom',
                    'center left' => 'Left', 'center right' => 'Right',
                    'top left' => 'Top left', 'top right' => 'Top right',
                    'bottom left' => 'Bottom left', 'bottom right' => 'Bottom right',
                ],
                'selectors' => [
                    '{{WRAPPER}} .iw2-ov' => 'background-position:{{VALUE}}',
                    '{{WRAPPER}} .iw2-ph' => 'background-position:{{VALUE}}',
                ],
            ]);
            $this->add_control('card_bg', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => [
                    '{{WRAPPER}} .iw2-ofc' => 'background:{{VALUE}}',
                    '{{WRAPPER}} .iw2-ofc .iw2-desc' => '--fade:{{VALUE}}',
                ]]);
            $this->add_control('card_border', ['label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .iw2-ofc' => 'border-color:{{VALUE}}', '{{WRAPPER}} .iw2-ed' => 'border-bottom-color:{{VALUE}}']]);
            $this->add_control('card_radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 12, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .iw2-ph,{{WRAPPER}} .iw2-ov,{{WRAPPER}} .iw2-ofc' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->end_controls_section();

            /* TEXT */
            $this->start_controls_section('txt', ['label' => 'Text', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('h_name', ['label' => 'Name', 'type' => \Elementor\Controls_Manager::HEADING]);
            $this->add_control('name_color', ['label' => 'Color', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-name' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'name_typo', 'selector' => '{{WRAPPER}} .iw2-name']);
            $this->add_control('h_sci', ['label' => 'Scientific tag', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('sci_color', ['label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-tag,{{WRAPPER}} .iw2-sci' => 'color:{{VALUE}}']]);
            $this->add_control('sci_bg', ['label' => 'Tag background', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-tag' => 'background:{{VALUE}}']]);
            $this->add_control('sci_bd', ['label' => 'Tag border', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-tag' => 'border-color:{{VALUE}}']]);
            $this->add_control('h_desc', ['label' => 'Description', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('desc_color', ['label' => 'Color', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-desc,{{WRAPPER}} .iw2-desc p' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'desc_typo', 'selector' => '{{WRAPPER}} .iw2-desc,{{WRAPPER}} .iw2-desc p']);
            $this->add_responsive_control('desc_align', ['label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => ['{{WRAPPER}} .iw2-desc,{{WRAPPER}} .iw2-desc p' => 'text-align:{{VALUE}}']]);
            $this->add_control('h_meta', ['label' => 'Meta', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('meta_color', ['label' => 'Color', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-meta' => 'color:{{VALUE}}']]);
            $this->end_controls_section();

            /* BUTTON */
            $this->start_controls_section('btn', ['label' => 'Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('btn_color', ['label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-btn' => 'color:{{VALUE}}']]);
            $this->add_control('btn_bg', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-btn' => 'background:{{VALUE}}']]);
            $this->add_control('btn_bd', ['label' => 'Border', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .iw2-btn' => 'border-color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'btn_typo', 'selector' => '{{WRAPPER}} .iw2-btn']);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $rows = get_field('wildlife', $pid) ?: [];
            if (!$rows) {
                return;
            }
            $style = in_array($s['card_style'], ['overlay', 'editorial', 'offset'], true) ? $s['card_style'] : 'overlay';
            $tag = $this->tag($s['title_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h4');
            $expand = ($s['reveal'] ?? 'expand') === 'expand';
            echo '<style>
              {{WRAPPER}} .iw2-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;align-items:start}
              {{WRAPPER}} .iw2-list{display:flex;flex-direction:column}
              {{WRAPPER}} .iw2-name{font-family:Merriweather,Georgia,serif;font-style:italic;margin:0 0 6px;font-size:20px;line-height:1.25;color:#64402c}
              {{WRAPPER}} .iw2-tag{align-self:flex-start;display:inline-block;font-style:italic;font-size:10px;letter-spacing:.06em;text-transform:uppercase;padding:3px 9px;border-radius:20px;border:1px solid rgba(211,186,163,.7);background:rgba(211,186,163,.28);color:#6b4832;margin-bottom:9px}
              {{WRAPPER}} .iw2-sci{display:block;font-style:italic;font-size:12.5px;color:#8a7058;margin:-2px 0 7px}
              {{WRAPPER}} .iw2-desc{--tl:3;--fade:#faf9f7;font-size:13.5px;line-height:1.62;color:#333}
              {{WRAPPER}} .iw2-desc p{margin:0 0 8px}{{WRAPPER}} .iw2-desc :last-child{margin-bottom:0}
              /* Paragraph-safe clamp: max-height on the whole block + a fade, opens on hover/tap. */
              {{WRAPPER}} .iw2-desc.clip{position:relative;max-height:calc(var(--tl) * 1.62em);overflow:hidden;transition:max-height .45s ease}
              {{WRAPPER}} .iw2-desc.clip::after{content:"";position:absolute;left:0;right:0;bottom:0;height:1.7em;background:linear-gradient(rgba(0,0,0,0),var(--fade));pointer-events:none;transition:opacity .3s ease}
              {{WRAPPER}} .rev.is-open .iw2-desc.clip,{{WRAPPER}} .rev:hover .iw2-desc.clip{max-height:2000px}
              {{WRAPPER}} .rev.is-open .iw2-desc.clip::after,{{WRAPPER}} .rev:hover .iw2-desc.clip::after{opacity:0}
              {{WRAPPER}} .rev{cursor:pointer}
              {{WRAPPER}} .iw2-meta{list-style:none;padding:0;margin:9px 0 0;font-size:13px;color:#5a4636}{{WRAPPER}} .iw2-meta li{margin:0 0 2px}
              {{WRAPPER}} .iw2-btn{align-self:flex-start;display:inline-block;color:#64402c;background:transparent;border:1px solid #D3BAA3;font-weight:600;text-decoration:none;font-size:13px;margin-top:12px;padding:7px 14px;border-radius:6px}
              {{WRAPPER}} .iw2-ph{background-image:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 12px,#d8c8b8 12px,#d8c8b8 24px);background-repeat:no-repeat}
              /* OVERLAY — image bg, text in normal flow at the bottom so expanding grows the card DOWN */
              {{WRAPPER}} .iw2-ov{position:relative;display:flex;flex-direction:column;justify-content:flex-end;min-height:340px;overflow:hidden;background-color:#6b4832;background-repeat:no-repeat;box-shadow:0 6px 18px rgba(60,40,25,.18)}
              {{WRAPPER}} .iw2-ov .iw2-tx{position:relative;padding:20px 18px 16px;color:#fff;background:linear-gradient(180deg,rgba(0,0,0,0),rgba(24,15,8,.55) 30%,rgba(24,15,8,.93));display:flex;flex-direction:column}
              {{WRAPPER}} .iw2-ov .iw2-name{color:#fff}
              {{WRAPPER}} .iw2-ov .iw2-desc{--fade:rgba(24,15,8,.93);color:#f3e9df}
              {{WRAPPER}} .iw2-ov .iw2-meta{color:#e6d5c4}
              {{WRAPPER}} .iw2-ov .iw2-btn{color:#f0d9c4;border-color:rgba(240,217,196,.6)}
              /* EDITORIAL — numbered index rows */
              {{WRAPPER}} .iw2-ed{position:relative;display:grid;grid-template-columns:150px 1fr;gap:22px;align-items:start;padding:24px 6px;border-bottom:1px solid #D3BAA3}
              {{WRAPPER}} .iw2-ed .iw2-num{position:absolute;left:-2px;top:6px;font-family:Merriweather,serif;font-size:54px;color:#e7dbcf;font-weight:700;z-index:0;line-height:1}
              {{WRAPPER}} .iw2-ed .iw2-ph{position:relative;z-index:1;height:150px;border-radius:10px}
              {{WRAPPER}} .iw2-ed .iw2-tx{position:relative;z-index:1;display:flex;flex-direction:column}
              /* OFFSET — card overlaps the photo (secondary/brown card, light text) */
              {{WRAPPER}} .iw2-of{display:flex;flex-direction:column}
              {{WRAPPER}} .iw2-of .iw2-ph{height:200px;border-radius:12px}
              {{WRAPPER}} .iw2-ofc{background:#64402c;--fade:#64402c;border:1px solid rgba(240,217,196,.35);border-radius:12px;padding:18px 20px;margin:-46px 16px 0;position:relative;z-index:1;box-shadow:0 6px 16px rgba(60,40,25,.22);display:flex;flex-direction:column}
              {{WRAPPER}} .iw2-ofc .iw2-name{color:#fff}
              {{WRAPPER}} .iw2-ofc .iw2-sci{color:#e9d9c8}
              {{WRAPPER}} .iw2-ofc .iw2-desc{color:#f3e9df;--fade:#64402c}
              {{WRAPPER}} .iw2-ofc .iw2-meta{color:#e6d5c4}
              {{WRAPPER}} .iw2-ofc .iw2-btn{color:#f0d9c4;border-color:rgba(240,217,196,.6)}
              @media(max-width:760px){{{WRAPPER}} .iw2-grid{grid-template-columns:1fr!important}{{WRAPPER}} .iw2-ed{grid-template-columns:96px 1fr;gap:14px}{{WRAPPER}} .iw2-ed .iw2-num{display:none}}
            </style>';

            $i = 0;
            $isgrid = ($style !== 'editorial');
            echo '<div class="iw2 ' . ($isgrid ? 'iw2-grid' : 'iw2-list') . '">';
            foreach ($rows as $w) {
                $i++;
                $img = island_ew_image_src($w['image'] ?? '');
                $bg = $img ? ' style="background-image:url(\'' . esc_url($img) . '\')"' : '';
                $common = esc_html($w['common_name'] ?? '');
                $sci = trim($w['scientific_name'] ?? '');
                $name = '<' . $tag . ' class="iw2-name">' . $common . '</' . $tag . '>';
                $tagEl = ($s['show_sci'] === 'yes' && $sci) ? '<span class="iw2-tag">' . esc_html($sci) . '</span>' : '';
                $sciU = ($s['show_sci'] === 'yes' && $sci) ? '<span class="iw2-sci">' . esc_html($sci) . '</span>' : '';
                $descClass = 'iw2-desc' . ($expand ? ' clip' : '');
                $desc = ($s['show_desc'] === 'yes' && !empty($w['description']))
                    ? '<div class="' . $descClass . '">' . wp_kses_post($w['description']) . '</div>' : '';
                $meta = '';
                if ($s['show_meta'] === 'yes') {
                    if (!empty($w['where_seen'])) {
                        $meta .= '<li><b>Where:</b> ' . esc_html($w['where_seen']) . '</li>';
                    }
                    if (!empty($w['best_season'])) {
                        $meta .= '<li><b>Season:</b> ' . esc_html($w['best_season']) . '</li>';
                    }
                    $meta = $meta ? '<ul class="iw2-meta">' . $meta . '</ul>' : '';
                }
                $btn = '';
                if ($s['show_btn'] === 'yes' && !empty($w['button_url'])) {
                    $btn = '<a class="iw2-btn" href="' . esc_url($w['button_url']) . '">'
                        . esc_html($w['button_label'] ?: $s['btn_text']) . ' &rarr;</a>';
                }
                $rev = $expand ? ' rev' : '';

                if ($style === 'overlay') {
                    echo '<article class="iw2-ov' . $rev . '"' . $bg . '><div class="iw2-tx">'
                        . $tagEl . $name . $desc . $meta . $btn . '</div></article>';
                } elseif ($style === 'offset') {
                    echo '<article class="iw2-of' . $rev . '"><div class="iw2-ph"' . $bg . '></div>'
                        . '<div class="iw2-ofc">' . $tagEl . $name . $sciU . $desc . $meta . $btn . '</div></article>';
                } else {
                    echo '<article class="iw2-ed' . $rev . '"><span class="iw2-num">' . sprintf('%02d', $i) . '</span>'
                        . '<div class="iw2-ph"' . $bg . '></div>'
                        . '<div class="iw2-tx">' . $tagEl . $name . $sciU . $desc . $meta . $btn . '</div></article>';
                }
            }
            echo '</div>';
            // Mobile / click: tap a card to toggle its expanded state (bound once).
            echo '<script>if(!window.__iw2tap){window.__iw2tap=1;document.addEventListener("click",function(e){'
                . 'if(e.target.closest(".iw2 a"))return;var c=e.target.closest(".iw2 .rev");if(c)c.classList.toggle("is-open");});}</script>';
        }
    }

    /* ===================================================================
     *  ISLAND QUICK FACTS — icon (your own SVG) + label + value(title/detail)
     * =================================================================== */
    class Island_QuickFacts_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_quickfacts';
        }

        public function get_title()
        {
            return 'Island Quick Facts';
        }

        public function get_icon()
        {
            return 'eicon-info-circle-o';
        }

        public function get_categories()
        {
            return ['general'];
        }

        protected function register_controls()
        {
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', [
                'label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER,
            ]);
            $this->add_responsive_control('columns', [
                'label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '2', 'tablet_default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3'],
                'selectors' => ['{{WRAPPER}} .qf-grid' => 'grid-template-columns:repeat({{VALUE}},1fr)'],
            ]);
            $this->add_control('layout', [
                'label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'panel',
                'options' => ['panel' => 'Single panel (lines)', 'cards' => 'Separate cards'],
            ]);
            $this->add_control('show_detail', [
                'label' => 'Show detail line', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
            ]);
            $rep = new \Elementor\Repeater();
            $rep->add_control('ic', ['label' => 'Icon', 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline']);
            $this->add_control('row_icons', [
                'label' => 'Fallback row icons (shared by all pages)', 'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $rep->get_controls(), 'prevent_empty' => false, 'title_field' => 'Icon {{{ _id }}}',
                'description' => 'Optional and SHARED across every island (this is one template). Each page\'s own uploaded icon (the SVG/Image field on that fact row) always wins; these fallback icons only fill rows where the page set no icon. To vary icons per island, upload them on the page — every Font Awesome icon can be downloaded as an SVG and uploaded there.',
            ]);
            $this->end_controls_section();

            /* CARD */
            $this->start_controls_section('card', ['label' => 'Card', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('gap', [
                'label' => 'Gap (cards mode)', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 50]], 'default' => ['size' => 16, 'unit' => 'px'],
                'condition' => ['layout' => 'cards'],
                'selectors' => ['{{WRAPPER}} .qf-cards' => 'gap:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_control('card_bg', [
                'label' => 'Item background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#F1EAE4',
                'selectors' => ['{{WRAPPER}} .qf-item' => 'background:{{VALUE}}'],
            ]);
            $this->add_control('card_radius', [
                'label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 40]], 'default' => ['size' => 9, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .qf-card,{{WRAPPER}} .qf-cards .qf-item' => 'border-radius:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_control('border_color', [
                'label' => 'Panel border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'condition' => ['layout' => 'panel'],
                'selectors' => ['{{WRAPPER}} .qf-card' => 'border-color:{{VALUE}}'],
            ]);
            $this->add_control('border_width', [
                'label' => 'Panel border width', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 8, 'step' => 0.5]], 'default' => ['size' => 1.5, 'unit' => 'px'],
                'condition' => ['layout' => 'panel'],
                'selectors' => ['{{WRAPPER}} .qf-card' => 'border-width:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_control('divider_color', [
                'label' => 'Divider color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf9f7',
                'condition' => ['layout' => 'panel'],
                'selectors' => ['{{WRAPPER}} .qf-card,{{WRAPPER}} .qf-panel' => 'background-color:{{VALUE}}'],
            ]);
            $this->add_responsive_control('card_pad', [
                'label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 20, 'right' => 22, 'bottom' => 20, 'left' => 22, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .qf-item' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}'],
            ]);
            $this->end_controls_section();

            /* ICON */
            $this->start_controls_section('icon', ['label' => 'Icon', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('icon_bg', [
                'label' => 'Circle color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .qf-ic' => 'background:{{VALUE}}'],
            ]);
            $this->add_control('icon_recolor', [
                'label' => 'Recolor uploaded SVG',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => 'Affects uploaded SVGs only. On: the SVG takes the Icon color (best for single-color SVGs). Off: keep the SVG\'s own colors. Font Awesome icons always use the Icon color.',
            ]);
            $this->add_control('icon_color', [
                'label' => 'Icon color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#F1EAE4',
                'selectors' => [
                    '{{WRAPPER}} .qf-glyph' => 'background-color:{{VALUE}}',
                    '{{WRAPPER}} .qf-ic i' => 'color:{{VALUE}}',
                    '{{WRAPPER}} .qf-ic svg' => 'color:{{VALUE}};fill:{{VALUE}}',
                ],
            ]);
            $this->add_responsive_control('icon_circle', [
                'label' => 'Circle size', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 30, 'max' => 90]], 'default' => ['size' => 46, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .qf-ic' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_responsive_control('icon_glyph', [
                'label' => 'Icon size', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 12, 'max' => 50]], 'default' => ['size' => 22, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .qf-ic img,{{WRAPPER}} .qf-glyph,{{WRAPPER}} .qf-ic svg' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}',
                    '{{WRAPPER}} .qf-ic i' => 'font-size:{{SIZE}}{{UNIT}}',
                ],
            ]);
            $this->end_controls_section();

            /* TEXT */
            $this->start_controls_section('text', ['label' => 'Text', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('label_color', [
                'label' => 'Label color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .qf-l' => 'color:{{VALUE}}'],
            ]);
            $this->add_control('title_color', [
                'label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .qf-t' => 'color:{{VALUE}}'],
            ]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'title_typo', 'selector' => '{{WRAPPER}} .qf-t',
            ]);
            $this->add_control('desc_color', [
                'label' => 'Detail color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#222222',
                'selectors' => ['{{WRAPPER}} .qf-d' => 'color:{{VALUE}}'],
            ]);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $rows = get_field('quick_facts', $pid) ?: [];
            if (!$rows) {
                return;
            }
            $panel = ($s['layout'] ?? 'panel') === 'panel';
            echo '<style>
              {{WRAPPER}} .qf-grid{display:grid}
              {{WRAPPER}} .qf-cards{gap:16px}
              {{WRAPPER}} .qf-card{background:#faf9f7;border:1.5px solid #D3BAA3;border-radius:9px;overflow:hidden}
              {{WRAPPER}} .qf-panel{gap:1px;background:#faf9f7}
              {{WRAPPER}} .qf-item{display:flex;gap:18px;align-items:flex-start;background:#F1EAE4;padding:22px 26px}
              {{WRAPPER}} .qf-span{grid-column:1 / -1;justify-content:center}
              {{WRAPPER}} .qf-span .qf-tx{flex:0 1 auto;max-width:340px}
              {{WRAPPER}} .qf-ic{flex:0 0 auto;width:46px;height:46px;border-radius:50%;background:#64402C;display:flex;align-items:center;justify-content:center}
              {{WRAPPER}} .qf-ic img{object-fit:contain}
              {{WRAPPER}} .qf-ic i{line-height:1}
              {{WRAPPER}} .qf-glyph{display:inline-block}
              {{WRAPPER}} .qf-tx{flex:1;min-width:0}
              {{WRAPPER}} .qf-l{margin:0 0 3px;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:700;font-size:17px}
              {{WRAPPER}} .qf-t{margin:0 0 3px;font-weight:700;font-size:15px;color:#3a2c22}
              {{WRAPPER}} .qf-d{margin:0;font-size:14px;line-height:1.5}
            </style>';
            $cols = (int) ($s['columns'] ?? 2) ?: 2;
            $n = count($rows);
            if ($panel) {
                echo '<div class="qf-card">';
            }
            echo '<div class="qf-grid ' . ($panel ? 'qf-panel' : 'qf-cards') . '">';
            $overrides = is_array($s['row_icons'] ?? null) ? $s['row_icons'] : [];
            $i = 0;
            foreach ($rows as $r) {
                $i++;
                [$title, $detail] = island_ew_split($r['value'] ?? '');
                $glyph = '';
                // 1) Per-page Font Awesome class typed on this page's fact row
                //    (e.g. "fa-solid fa-location-dot"). Varies per island.
                $fa = trim((string) ($r['icon_fa'] ?? ''));
                if ($fa !== '') {
                    $glyph = '<i class="' . esc_attr($fa) . '" aria-hidden="true"></i>';
                }
                // 2) Per-page uploaded SVG / image icon. Also varies per island.
                if ($glyph === '') {
                    $icon = island_ew_image_src($r['icon'] ?? '');
                    if ($icon) {
                        if ($s['icon_recolor'] === 'yes') {
                            $m = "url('" . esc_url($icon) . "') center/contain no-repeat";
                            $glyph = '<span class="qf-glyph" style="-webkit-mask:' . esc_attr($m) . ';mask:' . esc_attr($m) . '"></span>';
                        } else {
                            $glyph = '<img src="' . esc_url($icon) . '" alt="">';
                        }
                    }
                }
                // 3) Fallback only: an icon chosen in the widget (shared by every
                //    island via the single template). Used when the page has none.
                if ($glyph === '') {
                    $ov = $overrides[$i - 1]['ic'] ?? null;
                    if (is_array($ov) && !empty($ov['value']) && class_exists('\Elementor\Icons_Manager')) {
                        ob_start();
                        \Elementor\Icons_Manager::render_icon($ov, ['aria-hidden' => 'true']);
                        $glyph = ob_get_clean();
                    }
                }
                // In panel mode, a lone item on the last row spans + centers.
                $span = $panel && $i === $n && ($n % $cols) === 1 && $cols > 1;
                echo '<div class="qf-item' . ($span ? ' qf-span' : '') . '">';
                echo '<span class="qf-ic">' . $glyph . '</span>';
                echo '<div class="qf-tx">';
                echo '<p class="qf-l">' . esc_html($r['label'] ?? '') . '</p>';
                echo '<p class="qf-t">' . esc_html($title) . '</p>';
                if ($s['show_detail'] === 'yes' && $detail !== '') {
                    echo '<p class="qf-d">' . esc_html($detail) . '</p>';
                }
                echo '</div></div>';
            }
            echo '</div>';
            if ($panel) {
                echo '</div>';
            }
        }
    }

    /* ===================================================================
     *  ISLAND VISITOR SITES — card grid, split into Land-Based / Cruise-Only
     *  groups (each with its own intro), badge over the image, per-text styles.
     * =================================================================== */
    class Island_VisitorSites_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_visitor_sites';
        }

        public function get_title()
        {
            return 'Island Visitor Sites';
        }

        public function get_icon()
        {
            return 'eicon-gallery-grid';
        }

        public function get_categories()
        {
            return ['general'];
        }

        private function tag($v, $allowed, $default)
        {
            return in_array($v, $allowed, true) ? $v : $default;
        }

        protected function register_controls()
        {
            $tags = ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div'];

            /* CONTENT */
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('layout', [
                'label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'auto',
                'options' => [
                    'auto' => 'Auto (detect by data)',
                    'table' => 'Table (short Access / Wildlife / Notes)',
                    'cards' => 'Cards (long description + button)',
                    'carousel' => 'Carousel (image left, text right)',
                ],
                'description' => 'Auto = table when rows carry short Access/Key Wildlife/Notes columns (Santa Cruz), cards when rows carry long descriptions + buttons (Isabela, etc). Carousel = one site at a time, image left / text right.',
            ]);
            $this->add_responsive_control('columns', [
                'label' => 'Columns (cards)', 'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'condition' => ['layout!' => 'table'],
                'selectors' => ['{{WRAPPER}} .vs-grid' => 'grid-template-columns:repeat({{VALUE}},1fr)'],
            ]);
            $this->add_control('show_headings', ['label' => 'Show group headings', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('heading_land', ['label' => 'Land-Based heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Land-Based']);
            $this->add_control('heading_cruise', ['label' => 'Cruise-Only heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Cruise-Only']);
            $this->add_control('heading_tag', ['label' => 'Heading tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h3', 'options' => $tags]);
            $this->add_control('title_tag', ['label' => 'Site title tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h4', 'options' => $tags]);
            $this->add_control('show_access', ['label' => 'Show Access', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_wildlife', ['label' => 'Show Key Wildlife', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_desc', ['label' => 'Show Description', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_badge', ['label' => 'Show badge', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->end_controls_section();

            /* CARD */
            $this->start_controls_section('card', ['label' => 'Card', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('gap', [
                'label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 50]],
                'default' => ['size' => 18, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vs-grid' => 'gap:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_control('card_bg', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf9f7',
                'selectors' => ['{{WRAPPER}} .vs-card' => 'background:{{VALUE}}']]);
            $this->add_control('card_border', ['label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .vs-card' => 'border-color:{{VALUE}}']]);
            $this->add_control('card_bw', ['label' => 'Border width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 6, 'step' => 0.5]],
                'default' => ['size' => 1, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vs-card' => 'border-width:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('card_radius', ['label' => 'Radius (per corner)', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'], 'default' => ['top' => 9, 'right' => 9, 'bottom' => 9, 'left' => 9, 'unit' => 'px', 'isLinked' => true],
                'selectors' => ['{{WRAPPER}} .vs-card' => 'border-radius:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->add_responsive_control('card_pad', ['label' => 'Body padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 16, 'right' => 18, 'bottom' => 16, 'left' => 18, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .vs-bd' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->end_controls_section();

            /* IMAGE */
            $this->start_controls_section('img', ['label' => 'Image', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('img_h', ['label' => 'Height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 60, 'max' => 400]],
                'default' => ['size' => 150, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vs-ph' => 'height:{{SIZE}}{{UNIT}}']]);
            $this->add_control('img_fit', ['label' => 'Fit', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'cover',
                'options' => ['cover' => 'Cover', 'contain' => 'Contain'], 'selectors' => ['{{WRAPPER}} .vs-img' => 'object-fit:{{VALUE}}']]);
            $this->add_responsive_control('img_radius', ['label' => 'Radius (per corner)', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'], 'default' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0, 'unit' => 'px', 'isLinked' => true],
                'description' => 'Round each corner independently (unlink the chain to set different values).',
                'selectors' => ['{{WRAPPER}} .vs-ph' => 'border-radius:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->end_controls_section();

            /* BADGE */
            $this->start_controls_section('badge', ['label' => 'Badge', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('badge_land_bg', ['label' => 'Land bg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3f6b46',
                'selectors' => ['{{WRAPPER}} .vs-badge.lan' => 'background:{{VALUE}}']]);
            $this->add_control('badge_cruise_bg', ['label' => 'Cruise bg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3a5a8c',
                'selectors' => ['{{WRAPPER}} .vs-badge.cru' => 'background:{{VALUE}}']]);
            $this->add_control('badge_color', ['label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .vs-badge' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'badge_typo', 'selector' => '{{WRAPPER}} .vs-badge']);
            $this->end_controls_section();

            /* TEXT STYLES — heading / intro / title / meta / desc / button */
            $this->start_controls_section('txt', ['label' => 'Text', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $align = [
                'left' => ['title' => 'Left', 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => 'Center', 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => 'Right', 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => 'Justify', 'icon' => 'eicon-text-align-justify'],
            ];
            $this->add_control('h_head', ['label' => 'Group heading', 'type' => \Elementor\Controls_Manager::HEADING]);
            $this->add_control('head_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vs-gh' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'head_typo', 'selector' => '{{WRAPPER}} .vs-gh']);
            $this->add_responsive_control('head_align', ['label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => ['{{WRAPPER}} .vs-gh' => 'text-align:{{VALUE}}']]);
            $this->add_control('h_intro', ['label' => 'Intro', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('intro_color', ['label' => 'Intro color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#4a3a2c',
                'selectors' => ['{{WRAPPER}} .vs-intro,{{WRAPPER}} .vs-intro p' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'intro_typo', 'selector' => '{{WRAPPER}} .vs-intro,{{WRAPPER}} .vs-intro p']);
            $this->add_responsive_control('intro_align', ['label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => ['{{WRAPPER}} .vs-intro,{{WRAPPER}} .vs-intro p' => 'text-align:{{VALUE}}']]);
            $this->add_control('h_title', ['label' => 'Site title', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('title_color', ['label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vs-title' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typo', 'selector' => '{{WRAPPER}} .vs-title']);
            $this->add_responsive_control('title_align', ['label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => ['{{WRAPPER}} .vs-title' => 'text-align:{{VALUE}}']]);
            $this->add_control('h_meta', ['label' => 'Meta (Access / Wildlife)', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('meta_color', ['label' => 'Meta color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#4a3a2c',
                'selectors' => ['{{WRAPPER}} .vs-meta' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'meta_typo', 'selector' => '{{WRAPPER}} .vs-meta']);
            $this->add_control('h_desc', ['label' => 'Description', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('desc_color', ['label' => 'Description color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#333333',
                'selectors' => ['{{WRAPPER}} .vs-desc,{{WRAPPER}} .vs-desc p' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'desc_typo', 'selector' => '{{WRAPPER}} .vs-desc,{{WRAPPER}} .vs-desc p']);
            $this->add_responsive_control('desc_align', ['label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => ['{{WRAPPER}} .vs-desc,{{WRAPPER}} .vs-desc p' => 'text-align:{{VALUE}}']]);
            $this->end_controls_section();

            /* BUTTON */
            $this->start_controls_section('btn', ['label' => 'Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('btn_color', ['label' => 'Text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vs-btn' => 'color:{{VALUE}}']]);
            $this->add_control('btn_bg', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(0,0,0,0)',
                'selectors' => ['{{WRAPPER}} .vs-btn' => 'background:{{VALUE}}']]);
            $this->add_control('btn_bd', ['label' => 'Border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .vs-btn' => 'border-color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'btn_typo', 'selector' => '{{WRAPPER}} .vs-btn']);
            $this->end_controls_section();

            /* TABLE (only relevant when the Table layout renders) */
            $this->start_controls_section('tablestyle', ['label' => 'Table', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['layout!' => 'cards']]);
            $this->add_control('t_icons_head', ['label' => 'Header icons (pick your own, blank = default)', 'type' => \Elementor\Controls_Manager::HEADING]);
            $this->add_control('t_icon_site', ['label' => 'Visitor Site icon', 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline']);
            $this->add_control('t_icon_access', ['label' => 'Access icon', 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline']);
            $this->add_control('t_icon_wild', ['label' => 'Key Wildlife icon', 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline']);
            $this->add_control('t_icon_notes', ['label' => 'Notes icon', 'type' => \Elementor\Controls_Manager::ICONS, 'skin' => 'inline']);
            $this->add_control('t_icon_size', ['label' => 'Header icon size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 10, 'max' => 44]],
                'default' => ['size' => 16, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .vt-hc svg,{{WRAPPER}} .vt-hc img.vt-ic' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}',
                    '{{WRAPPER}} .vt-hc i' => 'font-size:{{SIZE}}{{UNIT}}',
                ]]);
            $this->add_control('t_icon_color', ['label' => 'Header icon color', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .vt-hc svg' => 'stroke:{{VALUE}}', '{{WRAPPER}} .vt-hc i,{{WRAPPER}} .vt-hc svg[fill]' => 'color:{{VALUE}};fill:{{VALUE}}']]);
            $this->add_control('t_border', ['label' => 'Outer border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#DBCEC4',
                'selectors' => ['{{WRAPPER}} .vt-wrap' => 'border-color:{{VALUE}}']]);
            $this->add_control('t_radius', ['label' => 'Outer radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 30]],
                'default' => ['size' => 9, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vt-wrap' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('t_head_bg', ['label' => 'Header background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vt-head' => 'background:{{VALUE}}']]);
            $this->add_control('t_head_color', ['label' => 'Header text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FCFAF9',
                'selectors' => ['{{WRAPPER}} .vt-head' => 'color:{{VALUE}}']]);
            $this->add_control('t_row_bg', ['label' => 'Row background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .vt-row' => 'background:{{VALUE}}']]);
            $this->add_control('t_divider', ['label' => 'Row divider', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ece5de',
                'selectors' => ['{{WRAPPER}} .vt-row' => 'border-top-color:{{VALUE}}']]);
            $this->add_control('t_name_color', ['label' => 'Site name', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#202020',
                'selectors' => ['{{WRAPPER}} .vt-name' => 'color:{{VALUE}}']]);
            $this->add_control('t_cell_color', ['label' => 'Cell text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5a4636',
                'selectors' => ['{{WRAPPER}} .vt-cell' => 'color:{{VALUE}}']]);
            $this->add_control('t_land_color', ['label' => 'Land badge color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3f6b46',
                'selectors' => ['{{WRAPPER}} .vt-tag.lan' => 'color:{{VALUE}}']]);
            $this->add_control('t_land_bg', ['label' => 'Land badge fill', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(63,107,70,.10)',
                'selectors' => ['{{WRAPPER}} .vt-tag.lan' => 'background:{{VALUE}}']]);
            $this->add_control('t_cruise_color', ['label' => 'Cruise badge color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3a5a8c',
                'selectors' => ['{{WRAPPER}} .vt-tag.cru' => 'color:{{VALUE}}']]);
            $this->add_control('t_cruise_bg', ['label' => 'Cruise badge fill', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(58,90,140,.10)',
                'selectors' => ['{{WRAPPER}} .vt-tag.cru' => 'background:{{VALUE}}']]);
            $this->add_control('t_thumb', ['label' => 'Thumbnail size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 120]],
                'default' => ['size' => 72, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vt-thumb' => 'width:{{SIZE}}{{UNIT}};height:calc({{SIZE}}{{UNIT}} * .82)']]);
            $this->end_controls_section();

            /* CAROUSEL (only relevant when the Carousel layout renders) */
            $this->start_controls_section('carstyle', ['label' => 'Carousel', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['layout' => 'carousel']]);
            $this->add_responsive_control('car_img_w', ['label' => 'Image width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['%' => ['min' => 20, 'max' => 70]],
                'default' => ['size' => 44, 'unit' => '%'], 'tablet_default' => ['size' => 44, 'unit' => '%'],
                'selectors' => ['{{WRAPPER}} .vcar-card' => 'grid-template-columns:{{SIZE}}{{UNIT}} 1fr']]);
            $this->add_responsive_control('car_minh', ['label' => 'Card min height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 180, 'max' => 560]],
                'default' => ['size' => 300, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vcar-card' => 'min-height:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('car_peek', ['label' => 'Slide width (%)', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['%' => ['min' => 60, 'max' => 100]],
                'default' => ['size' => 100, 'unit' => '%'], 'tablet_default' => ['size' => 100, 'unit' => '%'],
                'description' => 'Below 100% shows a peek of the next slide.',
                'selectors' => ['{{WRAPPER}} .vcar-slide' => 'flex-basis:{{SIZE}}{{UNIT}}']]);
            $this->add_control('car_gap', ['label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 50]],
                'default' => ['size' => 20, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vcar-track' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_control('car_radius', ['label' => 'Card radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 14, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vcar-card' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('car_bg', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf9f7',
                'selectors' => ['{{WRAPPER}} .vcar-card' => 'background:{{VALUE}}']]);
            $this->add_control('car_border', ['label' => 'Card border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#DBCEC4',
                'selectors' => ['{{WRAPPER}} .vcar-card' => 'border-color:{{VALUE}}']]);
            $this->add_control('car_badge_pos', [
                'label' => 'Badge position', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'text',
                'options' => ['text' => 'With text (right)', 'image' => 'Over image'],
                'description' => 'Land-Based / Cruise-Only badge next to the text (readable) or over the photo.',
            ]);
            $this->add_control('car_h_nav', ['label' => 'Navigation', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('car_show_arrows', ['label' => 'Show arrows', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('car_show_dots', ['label' => 'Show dots', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('car_arrow_c', ['label' => 'Arrow color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vcar-arw' => 'color:{{VALUE}}']]);
            $this->add_control('car_arrow_bg', ['label' => 'Arrow background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .vcar-arw' => 'background:{{VALUE}}']]);
            $this->add_control('car_arrow_bd', ['label' => 'Arrow border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .vcar-arw' => 'border-color:{{VALUE}}']]);
            $this->add_control('car_arrow_hc', ['label' => 'Arrow hover color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .vcar-arw:hover' => 'color:{{VALUE}}']]);
            $this->add_control('car_arrow_hbg', ['label' => 'Arrow hover background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vcar-arw:hover' => 'background:{{VALUE}};border-color:{{VALUE}}']]);
            $this->add_control('car_dot', ['label' => 'Dot color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .vcar-dot' => 'background:{{VALUE}}']]);
            $this->add_control('car_dot_on', ['label' => 'Active dot color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vcar-dot.on' => 'background:{{VALUE}}']]);
            $this->add_control('car_autoplay', ['label' => 'Autoplay', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '']);
            $this->add_control('car_autoplay_ms', ['label' => 'Autoplay delay (ms)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 5000, 'min' => 1500, 'max' => 15000,
                'condition' => ['car_autoplay' => 'yes']]);
            $this->end_controls_section();
        }

        private function is_tabular($rows)
        {
            $n = count($rows);
            if (!$n) {
                return false;
            }
            $structured = 0;
            $buttons = 0;
            $len = 0;
            foreach ($rows as $r) {
                if (!empty($r['access']) || !empty($r['species_seen'])) {
                    $structured++;
                }
                if (!empty($r['button_url'])) {
                    $buttons++;
                }
                $len += mb_strlen(wp_strip_all_tags($r['description'] ?? ''));
            }
            return $structured >= $n * 0.6 && ($len / $n) < 160 && $buttons === 0;
        }

        private function card($r, $s)
        {
            $title_tag = $this->tag($s['title_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h4');
            $at = $r['access_type'] ?? '';
            $badge = '';
            if ($s['show_badge'] === 'yes' && $at) {
                $cls = $at === 'Cruise-only' ? 'cru' : 'lan';
                $badge = '<span class="vs-badge ' . $cls . '">' . esc_html($at) . '</span>';
            }
            $img = island_ew_image_src($r['image'] ?? '');
            $imghtml = $img
                ? '<img class="vs-img" src="' . esc_url($img) . '" alt="' . esc_attr($r['site_name'] ?? '') . '">'
                : '<span class="vs-noimg">Image</span>';
            $pin = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s6-5 6-10a6 6 0 10-12 0c0 5 6 10 6 10z"/><circle cx="12" cy="11" r="2"/></svg>';
            $paw = '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><circle cx="6" cy="11" r="2"/><circle cx="10" cy="7.5" r="2"/><circle cx="14" cy="7.5" r="2"/><circle cx="18" cy="11" r="2"/><path d="M8.5 14c-2 1.5-2 4 .5 4 1 0 1.8-.5 3-.5s2 .5 3 .5c2.5 0 2.5-2.5.5-4-1-.8-2.2-1.5-3.5-1.5S9.5 13.2 8.5 14z"/></svg>';
            $meta = '';
            if ($s['show_access'] === 'yes' && !empty($r['access'])) {
                $meta .= '<p class="vs-meta"><span class="vs-mi">' . $pin . '</span><b>Access:</b> ' . esc_html(wp_strip_all_tags($r['access'])) . '</p>';
            }
            if ($s['show_wildlife'] === 'yes' && !empty($r['species_seen'])) {
                $meta .= '<p class="vs-meta"><span class="vs-mi">' . $paw . '</span><b>Wildlife:</b> ' . esc_html(wp_strip_all_tags($r['species_seen'])) . '</p>';
            }
            $desc = '';
            if ($s['show_desc'] === 'yes' && !empty($r['description'])) {
                $desc = '<div class="vs-desc">' . wp_kses_post($r['description']) . '</div>';
            }
            $btn = '';
            if (!empty($r['button_url'])) {
                $btn = '<a class="vs-btn" href="' . esc_url($r['button_url']) . '">' . esc_html($r['button_label'] ?: 'Learn more') . ' &rarr;</a>';
            }
            return '<article class="vs-card"><div class="vs-ph">' . $imghtml . $badge . '</div>'
                . '<div class="vs-bd"><' . $title_tag . ' class="vs-title">' . esc_html($r['site_name'] ?? '') . '</' . $title_tag . '>'
                . $meta . $desc . $btn . '</div></article>';
        }

        private function hdr_icon($setting, $fallback)
        {
            if (is_array($setting) && !empty($setting['value']) && class_exists('\Elementor\Icons_Manager')) {
                ob_start();
                \Elementor\Icons_Manager::render_icon($setting, ['aria-hidden' => 'true', 'class' => 'vt-ic']);
                $out = ob_get_clean();
                if (trim($out) !== '') {
                    return $out;
                }
            }
            return $fallback;
        }

        private function carousel_card($r, $s, $title_tag)
        {
            $img = island_ew_image_src($r['image'] ?? '');
            $bg = $img ? ' style="background-image:url(\'' . esc_url($img) . '\')"' : '';
            $badge = '';
            if ($s['show_badge'] === 'yes' && !empty($r['access_type'])) {
                $cls = ($r['access_type'] === 'Cruise-only') ? 'cru' : 'lan';
                $badge = '<span class="vs-badge ' . $cls . '">' . esc_html($r['access_type']) . '</span>';
            }
            $meta = '';
            if ($s['show_access'] === 'yes' && !empty($r['access'])) {
                $meta .= '<span><b>Access:</b> ' . esc_html(wp_strip_all_tags($r['access'])) . '</span>';
            }
            if ($s['show_wildlife'] === 'yes' && !empty($r['species_seen'])) {
                $meta .= '<span><b>Wildlife:</b> ' . esc_html(wp_strip_all_tags($r['species_seen'])) . '</span>';
            }
            $meta = $meta ? '<p class="vs-meta">' . $meta . '</p>' : '';
            $desc = ($s['show_desc'] === 'yes' && !empty($r['description']))
                ? '<div class="vs-desc">' . wp_kses_post($r['description']) . '</div>' : '';
            $btn = '';
            if (!empty($r['button_url'])) {
                $btn = '<a class="vs-btn" href="' . esc_url($r['button_url']) . '">' . esc_html($r['button_label'] ?: 'Learn more') . ' &rarr;</a>';
            }
            $badge_over = ($s['car_badge_pos'] ?? 'text') === 'image';
            return '<div class="vcar-slide"><article class="vcar-card">'
                . '<div class="vcar-img"' . $bg . '>' . ($badge_over ? $badge : '') . '</div>'
                . '<div class="vcar-bd">' . ($badge_over ? '' : $badge)
                . '<' . $title_tag . ' class="vs-title">' . esc_html($r['site_name'] ?? '') . '</' . $title_tag . '>'
                . $meta . $desc . $btn . '</div></article></div>';
        }

        private function carousel_block($rows, $s, $title_tag, $cid)
        {
            echo '<div class="vcar"><div class="vcar-track" id="' . esc_attr($cid) . '">';
            foreach ($rows as $r) {
                echo $this->carousel_card($r, $s, $title_tag);
            }
            echo '</div>';
            $arrows = $s['car_show_arrows'] === 'yes';
            $dots = $s['car_show_dots'] === 'yes';
            if ($arrows || $dots) {
                echo '<div class="vcar-nav">';
                if ($arrows) {
                    echo '<button class="vcar-arw" data-vcar="prev" aria-label="Previous"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg></button>';
                }
                if ($dots) {
                    echo '<div class="vcar-dots"></div>';
                }
                if ($arrows) {
                    echo '<button class="vcar-arw" data-vcar="next" aria-label="Next"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg></button>';
                }
                echo '</div>';
            }
            echo '</div>';
            $auto = ($s['car_autoplay'] === 'yes') ? max(1500, (int) ($s['car_autoplay_ms'] ?: 5000)) : 0;
            echo '<script>(function(){var t=document.getElementById(' . json_encode($cid) . ');if(!t||t.dataset.init)return;t.dataset.init=1;'
                . 'var car=t.closest(".vcar"),sl=t.children,n=sl.length,cur=0,dw=car.querySelector(".vcar-dots"),ap=' . $auto . ';'
                . 'if(dw){for(var i=0;i<n;i++){(function(i){var b=document.createElement("button");b.className="vcar-dot"+(i?"":" on");b.onclick=function(){go(i)};dw.appendChild(b);})(i);}}'
                . 'function go(i){cur=Math.max(0,Math.min(n-1,i));sl[cur].scrollIntoView({behavior:"smooth",inline:"center",block:"nearest"});paint();}'
                . 'function paint(){if(!dw)return;var d=dw.children;for(var i=0;i<n;i++)d[i].className="vcar-dot"+(i===cur?" on":"");}'
                . 'car.querySelectorAll("[data-vcar]").forEach(function(b){b.onclick=function(){go(cur+(b.dataset.vcar==="next"?1:-1));};});'
                . 't.addEventListener("scroll",function(){var i=Math.round(t.scrollLeft/t.clientWidth);if(i!==cur){cur=i;paint();}});'
                . 'if(ap){setInterval(function(){go(cur+1>=n?0:cur+1);},ap);}'
                . '})();</script>';
        }

        private function render_carousel($rows, $s, $pid)
        {
            $title_tag = $this->tag($s['title_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h4');
            $htag = $this->tag($s['heading_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h3');
            echo '<style>
              {{WRAPPER}} .vs-gh{margin:22px 0 6px}
              {{WRAPPER}} .vs-intro{margin:0 0 16px}{{WRAPPER}} .vs-intro :first-child{margin-top:0}{{WRAPPER}} .vs-intro :last-child{margin-bottom:0}
              {{WRAPPER}} .vcar{position:relative;margin-bottom:8px}
              {{WRAPPER}} .vcar-track{display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:4px 2px 8px;scrollbar-width:none}
              {{WRAPPER}} .vcar-track::-webkit-scrollbar{display:none}
              {{WRAPPER}} .vcar-slide{scroll-snap-align:center;flex:0 0 100%;min-width:0}
              {{WRAPPER}} .vcar-card{display:grid;grid-template-columns:44% 1fr;background:#faf9f7;border:1px solid #DBCEC4;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(60,40,25,.10);min-height:300px}
              {{WRAPPER}} .vcar-img{position:relative;background:#cbb89b center/cover no-repeat;min-height:180px}
              {{WRAPPER}} .vcar-bd{padding:28px 30px;display:flex;flex-direction:column;justify-content:center}
              {{WRAPPER}} .vcar-bd .vs-title{margin:0 0 6px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:23px;color:#64402C}
              {{WRAPPER}} .vcar-bd .vs-meta{margin:0 0 10px;font-size:12.5px;color:#8a7058;display:flex;gap:14px;flex-wrap:wrap;align-items:baseline}
              {{WRAPPER}} .vcar-bd .vs-desc{font-size:14px;line-height:1.62;color:#333}{{WRAPPER}} .vcar-bd .vs-desc p{margin:0 0 8px}{{WRAPPER}} .vcar-bd .vs-desc :last-child{margin-bottom:0}
              {{WRAPPER}} .vcar-bd .vs-btn{align-self:flex-start;margin-top:16px;display:inline-block;font-size:13px;font-weight:600;text-decoration:none;color:#64402C;border:1px solid #D3BAA3;border-radius:7px;padding:9px 16px;background:#fff}
              {{WRAPPER}} .vcar .vs-badge{padding:5px 11px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;font-size:10px;font-weight:700;color:#fff}
              {{WRAPPER}} .vcar-img .vs-badge{position:absolute;top:14px;left:14px;box-shadow:0 1px 4px rgba(0,0,0,.2)}
              {{WRAPPER}} .vcar-bd .vs-badge{align-self:flex-start;margin:0 0 10px}
              {{WRAPPER}} .vcar-nav{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:14px}
              {{WRAPPER}} .vcar-arw{width:42px;height:42px;border-radius:50%;border:1px solid #D3BAA3;background:#fff;color:#64402C;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(60,40,25,.10);transition:background .2s,color .2s}
              {{WRAPPER}} .vcar-dots{display:flex;gap:8px}
              {{WRAPPER}} .vcar-dot{width:9px;height:9px;border-radius:50%;background:#D3BAA3;border:none;cursor:pointer;padding:0;transition:width .2s,background .2s}
              {{WRAPPER}} .vcar-dot.on{background:#64402C;width:24px;border-radius:20px}
              @media(max-width:680px){{{WRAPPER}} .vcar-card{grid-template-columns:1fr}{{WRAPPER}} .vcar-img{height:190px}}
            </style>';

            // Keep the Land-Based / Cruise-Only structure: a heading + its intro
            // and its own carousel per group (same as the Cards layout).
            $land = array_filter($rows, fn($r) => ($r['access_type'] ?? '') !== 'Cruise-only');
            $cruise = array_filter($rows, fn($r) => ($r['access_type'] ?? '') === 'Cruise-only');
            $groups = [];
            if ($land) {
                $groups[] = [$s['heading_land'], get_field('visitor_sites_intro', $pid), $land, 'l'];
            }
            if ($cruise) {
                $groups[] = [$s['heading_cruise'], get_field('visitor_sites_intro_cruise', $pid), $cruise, 'c'];
            }
            foreach ($groups as [$label, $intro, $grows, $suf]) {
                if ($s['show_headings'] === 'yes' && $label) {
                    echo '<' . $htag . ' class="vs-gh">' . esc_html($label) . '</' . $htag . '>';
                }
                if ($intro) {
                    echo '<div class="vs-intro">' . wp_kses_post($intro) . '</div>';
                }
                $this->carousel_block($grows, $s, $title_tag, 'vcar-' . $this->get_id() . '-' . $suf);
            }
        }

        private function render_table($rows, $s, $pid)
        {
            $title_tag = $this->tag($s['title_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h4');
            $pin = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s6-5 6-10a6 6 0 10-12 0c0 5 6 10 6 10z"/><circle cx="12" cy="11" r="2"/></svg>';
            $walk = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="13" cy="4" r="1.6"/><path d="M13 8l-3 4 2 2 1 5M13 12l3 2M10 12l-3 6"/></svg>';
            $paw = '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><circle cx="6" cy="11" r="2"/><circle cx="10" cy="7.5" r="2"/><circle cx="14" cy="7.5" r="2"/><circle cx="18" cy="11" r="2"/><path d="M8.5 14c-2 1.5-2 4 .5 4 1 0 1.8-.5 3-.5s2 .5 3 .5c2.5 0 2.5-2.5.5-4-1-.8-2.2-1.5-3.5-1.5S9.5 13.2 8.5 14z"/></svg>';
            $note = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>';

            $cols = ['minmax(190px,1.4fr)'];
            $head = ['<div class="vt-hc">' . $this->hdr_icon($s['t_icon_site'] ?? '', $pin) . ' Visitor Site</div>'];
            $show_access = $s['show_access'] === 'yes';
            $show_wild = $s['show_wildlife'] === 'yes';
            $show_notes = $s['show_desc'] === 'yes';
            if ($show_access) {
                $cols[] = '1fr';
                $head[] = '<div class="vt-hc">' . $this->hdr_icon($s['t_icon_access'] ?? '', $walk) . ' Access</div>';
            }
            if ($show_wild) {
                $cols[] = '1.3fr';
                $head[] = '<div class="vt-hc">' . $this->hdr_icon($s['t_icon_wild'] ?? '', $paw) . ' Key Wildlife</div>';
            }
            if ($show_notes) {
                $cols[] = '1fr';
                $head[] = '<div class="vt-hc">' . $this->hdr_icon($s['t_icon_notes'] ?? '', $note) . ' Notes</div>';
            }
            $tpl = implode(' ', $cols);

            echo '<style>
              {{WRAPPER}} .vs-intro{margin:0 0 16px}{{WRAPPER}} .vs-intro :first-child{margin-top:0}{{WRAPPER}} .vs-intro :last-child{margin-bottom:0}
              {{WRAPPER}} .vt-scroll{overflow-x:auto}
              {{WRAPPER}} .vt-wrap{min-width:700px;border:1px solid #DBCEC4;border-radius:11px;overflow:hidden;background:#fff;box-shadow:0 6px 20px rgba(60,40,25,.08)}
              {{WRAPPER}} .vt-head{display:grid;background:#64402C;color:#FCFAF9}
              {{WRAPPER}} .vt-head .vt-hc{padding:17px 20px;display:flex;align-items:center;gap:10px;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:700;font-size:15px}
              {{WRAPPER}} .vt-row{display:grid;background:#faf9f7;border-top:1px solid #ece5de}
              {{WRAPPER}} .vt-row:nth-child(even){background:#fff}
              {{WRAPPER}} .vt-cell{padding:17px 20px;font-size:14px;line-height:1.55;color:#5a4636}
              {{WRAPPER}} .vt-site{display:flex;gap:14px;align-items:flex-start;padding:17px 20px}
              {{WRAPPER}} .vt-thumb{flex:0 0 auto;width:72px;height:59px;border-radius:8px;object-fit:cover;display:block;box-shadow:0 2px 6px rgba(60,40,25,.16);background:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 8px,#d8c8b8 8px,#d8c8b8 16px)}
              {{WRAPPER}} .vt-nm{display:flex;flex-direction:column}
              {{WRAPPER}} .vt-name{margin:0;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:700;font-size:15px;color:#202020;line-height:1.3}
              {{WRAPPER}} .vt-tag{align-self:flex-start;margin-top:8px;display:inline-block;font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;padding:4px 10px;border-radius:20px;border:1px solid transparent}
              {{WRAPPER}} .vt-tag.lan{color:#3f6b46;background:rgba(63,107,70,.10);border-color:rgba(63,107,70,.28)}
              {{WRAPPER}} .vt-tag.cru{color:#3a5a8c;background:rgba(58,90,140,.10);border-color:rgba(58,90,140,.28)}
              @media(max-width:640px){{{WRAPPER}} .vt-wrap{min-width:560px}}
            </style>';

            $introL = get_field('visitor_sites_intro', $pid);
            $introC = get_field('visitor_sites_intro_cruise', $pid);
            if ($introL) {
                echo '<div class="vs-intro">' . wp_kses_post($introL) . '</div>';
            }
            if ($introC) {
                echo '<div class="vs-intro">' . wp_kses_post($introC) . '</div>';
            }

            $gtc = ' style="grid-template-columns:' . esc_attr($tpl) . '"';
            echo '<div class="vt-scroll"><div class="vt-wrap">';
            echo '<div class="vt-head"' . $gtc . '>' . implode('', $head) . '</div>';
            foreach ($rows as $r) {
                $img = island_ew_image_src($r['image'] ?? '');
                $thumb = $img
                    ? '<img class="vt-thumb" src="' . esc_url($img) . '" alt="' . esc_attr($r['site_name'] ?? '') . '">'
                    : '<span class="vt-thumb"></span>';
                $badge = '';
                if ($s['show_badge'] === 'yes' && !empty($r['access_type'])) {
                    $cls = ($r['access_type'] === 'Cruise-only') ? 'cru' : 'lan';
                    $badge = '<span class="vt-tag ' . $cls . '">' . esc_html($r['access_type']) . '</span>';
                }
                echo '<div class="vt-row"' . $gtc . '><div class="vt-site">' . $thumb
                    . '<span class="vt-nm"><' . $title_tag . ' class="vt-name">' . esc_html($r['site_name'] ?? '') . '</' . $title_tag . '>'
                    . $badge . '</span></div>';
                if ($show_access) {
                    echo '<div class="vt-cell">' . esc_html(wp_strip_all_tags($r['access'] ?? '')) . '</div>';
                }
                if ($show_wild) {
                    echo '<div class="vt-cell">' . esc_html(wp_strip_all_tags($r['species_seen'] ?? '')) . '</div>';
                }
                if ($show_notes) {
                    echo '<div class="vt-cell">' . esc_html(wp_strip_all_tags($r['description'] ?? '')) . '</div>';
                }
                echo '</div>';
            }
            echo '</div></div>';
        }

        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $rows = get_field('visitor_sites', $pid) ?: [];
            if (!$rows) {
                return;
            }
            $layout = in_array($s['layout'] ?? 'auto', ['auto', 'table', 'cards', 'carousel'], true) ? $s['layout'] : 'auto';
            if ($layout === 'auto') {
                $layout = $this->is_tabular($rows) ? 'table' : 'cards';
            }
            if ($layout === 'table') {
                $this->render_table($rows, $s, $pid);
                return;
            }
            if ($layout === 'carousel') {
                $this->render_carousel($rows, $s, $pid);
                return;
            }
            $land = array_filter($rows, fn($r) => ($r['access_type'] ?? '') !== 'Cruise-only');
            $cruise = array_filter($rows, fn($r) => ($r['access_type'] ?? '') === 'Cruise-only');
            $groups = [];
            if ($land) {
                $groups[] = [$s['heading_land'], get_field('visitor_sites_intro', $pid), $land];
            }
            if ($cruise) {
                $groups[] = [$s['heading_cruise'], get_field('visitor_sites_intro_cruise', $pid), $cruise];
            }
            $htag = $this->tag($s['heading_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h3');
            echo '<style>
              {{WRAPPER}} .vs-gh{margin:22px 0 6px}
              {{WRAPPER}} .vs-intro{margin:0 0 16px}
              {{WRAPPER}} .vs-intro :first-child{margin-top:0}{{WRAPPER}} .vs-intro :last-child{margin-bottom:0}
              .vs-grid{display:grid;gap:18px}
              .vs-card{background:#faf9f7;border:1px solid #D3BAA3;border-radius:9px;overflow:hidden;display:flex;flex-direction:column}
              .vs-ph{position:relative;height:150px;overflow:hidden;background:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 10px,#d8c8b8 10px,#d8c8b8 20px);display:flex;align-items:center;justify-content:center}
              .vs-img{width:100%;height:100%;object-fit:cover;display:block}
              {{WRAPPER}} .vs-noimg{color:#8a7058;font-size:13px}
              {{WRAPPER}} .vs-badge{position:absolute;top:10px;left:10px;padding:4px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;font-size:10px;font-weight:700;box-shadow:0 1px 3px rgba(0,0,0,.18)}
              {{WRAPPER}} .vs-bd{padding:16px 18px;display:flex;flex-direction:column;gap:8px}
              {{WRAPPER}} .vs-title{margin:0;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:17px}
              {{WRAPPER}} .vs-meta{margin:0;font-size:13px;display:flex;gap:6px;align-items:baseline}
              {{WRAPPER}} .vs-mi{position:relative;top:2px}
              {{WRAPPER}} .vs-desc{font-size:13.5px;line-height:1.5}{{WRAPPER}} .vs-desc p{margin:0 0 8px}{{WRAPPER}} .vs-desc :last-child{margin-bottom:0}
              {{WRAPPER}} .vs-btn{align-self:flex-start;margin-top:4px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid #D3BAA3;border-radius:6px;padding:7px 13px}
            </style>';
            foreach ($groups as [$label, $intro, $grows]) {
                if ($s['show_headings'] === 'yes' && $label) {
                    echo '<' . $htag . ' class="vs-gh">' . esc_html($label) . '</' . $htag . '>';
                }
                if ($intro) {
                    echo '<div class="vs-intro">' . wp_kses_post($intro) . '</div>';
                }
                echo '<div class="vs-grid">';
                foreach ($grows as $r) {
                    echo $this->card($r, $s);
                }
                echo '</div>';
            }
        }
    }

    /* ===================================================================
     *  ISLAND SITES CAROUSEL — the Visitor Sites carousel as its own
     *  identifiable widget (image left / text right). Reuses everything
     *  from Island Visitor Sites but always renders the carousel layout.
     * =================================================================== */
    class Island_SitesCarousel_Widget extends Island_VisitorSites_Widget
    {
        public function get_name()
        {
            return 'island_sites_carousel';
        }

        public function get_title()
        {
            return 'Island Sites Carousel';
        }

        public function get_icon()
        {
            return 'eicon-slider-push';
        }

        protected function register_controls()
        {
            parent::register_controls();
            // Lock this widget to the carousel layout and hide the selector.
            $this->update_control('layout', [
                'type' => \Elementor\Controls_Manager::HIDDEN,
                'default' => 'carousel',
            ]);
        }
    }

    /* ===================================================================
     *  ISLAND WILDLIFE CALENDAR — seasonal "what to see when" season cards
     *  (period + optional label + highlights). Reusable for any island that
     *  has a wildlife_calendar repeater (e.g. Santiago).
     * =================================================================== */
    class Island_WildlifeCalendar_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_wildlife_calendar';
        }

        public function get_title()
        {
            return 'Island Wildlife Calendar';
        }

        public function get_icon()
        {
            return 'eicon-calendar';
        }

        public function get_categories()
        {
            return ['general'];
        }

        private function tag($v, $allowed, $default)
        {
            return in_array($v, $allowed, true) ? $v : $default;
        }

        protected function register_controls()
        {
            $tags = ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div'];

            /* CONTENT */
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('show_title', ['label' => 'Show section title', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('title_text', [
                'label' => 'Section title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Wildlife Calendar',
                'placeholder' => 'Wildlife Calendar', 'condition' => ['show_title' => 'yes'],
                'description' => 'Single heading for this section (one source, no duplicate). The widget only shows when the island has calendar data, so this title appears only there.',
            ]);
            $this->add_control('title_tag', ['label' => 'Title tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h2',
                'options' => $tags, 'condition' => ['show_title' => 'yes']]);
            $this->add_responsive_control('columns', [
                'label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '2',
                'tablet_default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'selectors' => ['{{WRAPPER}} .wcal-grid' => 'grid-template-columns:repeat({{VALUE}},1fr)'],
            ]);
            $this->add_control('period_tag', ['label' => 'Period tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h3', 'options' => $tags]);
            $this->add_control('show_label', ['label' => 'Show season label', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->end_controls_section();

            /* CARD */
            $this->start_controls_section('card', ['label' => 'Card', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('gap', ['label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 50]],
                'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wcal-grid' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_control('card_bg', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf9f7',
                'selectors' => ['{{WRAPPER}} .wcal-card' => 'background:{{VALUE}}']]);
            $this->add_control('card_border', ['label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#DBCEC4',
                'selectors' => ['{{WRAPPER}} .wcal-card' => 'border-color:{{VALUE}}']]);
            $this->add_control('card_radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 10, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wcal-card' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('accent_w', ['label' => 'Accent bar width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 14]],
                'default' => ['size' => 5, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wcal-card' => 'border-left-width:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('card_pad', ['label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 18, 'right' => 20, 'bottom' => 18, 'left' => 20, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .wcal-card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->end_controls_section();

            /* ACCENTS — cycle by row */
            $this->start_controls_section('accents', ['label' => 'Season accents (cycle by row)', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('acc1', ['label' => 'Accent 1', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#c0703a']);
            $this->add_control('acc2', ['label' => 'Accent 2', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#4f7d52']);
            $this->add_control('acc3', ['label' => 'Accent 3', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3f6b86']);
            $this->add_control('acc4', ['label' => 'Accent 4', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#8a6d3b']);
            $this->add_control('label_text', ['label' => 'Label text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .wcal-label' => 'color:{{VALUE}}']]);
            $this->end_controls_section();

            /* TEXT */
            $this->start_controls_section('text', ['label' => 'Text', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('title_color', ['label' => 'Section title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .wcal-h' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typo', 'selector' => '{{WRAPPER}} .wcal-h']);
            $this->add_control('period_color', ['label' => 'Period color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .wcal-period' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'period_typo', 'selector' => '{{WRAPPER}} .wcal-period']);
            $this->add_control('hl_color', ['label' => 'Highlights color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3a2c22',
                'selectors' => ['{{WRAPPER}} .wcal-hl,{{WRAPPER}} .wcal-hl p' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'hl_typo', 'selector' => '{{WRAPPER}} .wcal-hl,{{WRAPPER}} .wcal-hl p']);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $rows = get_field('wildlife_calendar', $pid) ?: [];
            if (!$rows) {
                return;
            }
            $ptag = $this->tag($s['period_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h3');
            $htag = $this->tag($s['title_tag'] ?? 'h2', ['h2', 'h3', 'h4', 'h5', 'div'], 'h2');
            $acc = [$s['acc1'] ?: '#64402c', $s['acc2'] ?: '#64402c', $s['acc3'] ?: '#64402c', $s['acc4'] ?: '#64402c'];
            echo '<style>
              {{WRAPPER}} .wcal-h{margin:0 0 16px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:24px;color:#64402C}
              {{WRAPPER}} .wcal-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
              {{WRAPPER}} .wcal-card{background:#faf9f7;border:1px solid #DBCEC4;border-left:5px solid #64402c;border-radius:10px;padding:18px 20px;box-shadow:0 4px 14px rgba(60,40,25,.06)}
              {{WRAPPER}} .wcal-top{display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap}
              {{WRAPPER}} .wcal-period{margin:0;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:700;font-size:18px;color:#64402C}
              {{WRAPPER}} .wcal-label{font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:3px 10px;border-radius:20px;color:#fff}
              {{WRAPPER}} .wcal-hl{margin:0;font-size:13.7px;line-height:1.6;color:#3a2c22}{{WRAPPER}} .wcal-hl p{margin:0 0 8px}{{WRAPPER}} .wcal-hl :last-child{margin-bottom:0}
              @media(max-width:680px){{{WRAPPER}} .wcal-grid{grid-template-columns:1fr!important}}
            </style>';
            if (($s['show_title'] ?? 'yes') === 'yes' && !empty($s['title_text'])) {
                echo '<' . $htag . ' class="wcal-h">' . esc_html($s['title_text']) . '</' . $htag . '>';
            }
            echo '<div class="wcal-grid">';
            $i = 0;
            foreach ($rows as $r) {
                $c = $acc[$i % 4];
                $i++;
                $period = $r['period'] ?? '';
                $label = '';
                if ($s['show_label'] === 'yes' && !empty($r['label'])) {
                    $label = '<span class="wcal-label" style="background:' . esc_attr($c) . '">' . esc_html($r['label']) . '</span>';
                }
                $hl = !empty($r['highlights']) ? '<div class="wcal-hl">' . wp_kses_post($r['highlights']) . '</div>' : '';
                echo '<article class="wcal-card" style="border-left-color:' . esc_attr($c) . '">'
                    . '<div class="wcal-top"><' . $ptag . ' class="wcal-period">' . esc_html($period) . '</' . $ptag . '>' . $label . '</div>'
                    . $hl . '</article>';
            }
            echo '</div>';
        }
    }

    /* ===================================================================
     *  ISLAND FEATURE SECTIONS — editorial stories (zig-zag image/text)
     * =================================================================== */
    class Island_FeatureSections_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_feature_sections';
        }
        public function get_title()
        {
            return 'Island Feature Sections';
        }
        public function get_icon()
        {
            return 'eicon-post-content';
        }
        public function get_categories()
        {
            return ['general'];
        }
        private function tg($v, $a, $d)
        {
            return in_array($v, $a, true) ? $v : $d;
        }
        protected function register_controls()
        {
            $tags = ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'];
            $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('layout', [
                'label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'rows',
                'options' => ['rows' => 'Rows (zig-zag)', 'carousel' => 'Carousel (image + text, one at a time)'],
                'description' => 'Rows stacks every feature as a zig-zag block. Carousel shows one feature at a time (image on one side, text on the other) with arrows/dots.',
            ]);
            $this->add_control('title_tag', ['label' => 'Title tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h3', 'options' => $tags]);
            $this->add_control('alternate', ['label' => 'Alternate image side', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => ['layout' => 'rows']]);
            $this->add_control('show_img', ['label' => 'Show image', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);

            $this->add_control('car_img_side', [
                'label' => 'Image side', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'left',
                'options' => ['left' => 'Left', 'right' => 'Right'], 'condition' => ['layout' => 'carousel'],
            ]);
            $this->add_control('car_show_arrows', ['label' => 'Show arrows', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => ['layout' => 'carousel']]);
            $this->add_control('car_show_dots', ['label' => 'Show dots', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => ['layout' => 'carousel']]);
            $this->add_control('car_autoplay', ['label' => 'Autoplay', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '', 'condition' => ['layout' => 'carousel']]);
            $this->add_control('car_autoplay_ms', [
                'label' => 'Autoplay delay (ms)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 5000,
                'condition' => ['layout' => 'carousel', 'car_autoplay' => 'yes'],
            ]);
            $this->end_controls_section();

            $this->start_controls_section('s', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('gap', ['label' => 'Row gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 100]],
                'default' => ['size' => 48, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('img_w', ['label' => 'Image width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['%' => ['min' => 25, 'max' => 65]],
                'default' => ['size' => 45, 'unit' => '%'], 'selectors' => ['{{WRAPPER}} .ifs-row' => 'grid-template-columns:{{SIZE}}% 1fr']]);
            $this->add_control('img_h', ['label' => 'Image height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 140, 'max' => 560]],
                'default' => ['size' => 300, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-img' => 'height:{{SIZE}}{{UNIT}}']]);
            $this->add_control('img_radius', ['label' => 'Image radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 12, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-img' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('eyebrow_color', ['label' => 'Subtitle color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#9c7b4e',
                'selectors' => ['{{WRAPPER}} .ifs-eyebrow' => 'color:{{VALUE}}']]);
            $this->add_control('title_color', ['label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .ifs-title' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typo', 'selector' => '{{WRAPPER}} .ifs-title']);
            $this->add_control('body_color', ['label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#333333',
                'selectors' => ['{{WRAPPER}} .ifs-body,{{WRAPPER}} .ifs-body p' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'body_typo', 'selector' => '{{WRAPPER}} .ifs-body,{{WRAPPER}} .ifs-body p']);
            $this->add_control('btn_color', ['label' => 'Button text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .ifs-btn' => 'color:{{VALUE}}']]);
            $this->add_control('btn_bd', ['label' => 'Button border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .ifs-btn' => 'border-color:{{VALUE}}']]);
            $this->end_controls_section();
        }
        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $rows = get_field('feature_sections', $pid) ?: [];
            if (!$rows) {
                return;
            }
            if (($s['layout'] ?? 'rows') === 'carousel') {
                $this->render_carousel($rows, $s);
                return;
            }
            $tag = $this->tg($s['title_tag'], ['h2', 'h3', 'h4', 'div'], 'h3');
            $alt = $s['alternate'] === 'yes';
            $showimg = $s['show_img'] === 'yes';
            echo '<style>
              {{WRAPPER}} .ifs{display:flex;flex-direction:column;gap:48px}
              {{WRAPPER}} .ifs-row{display:grid;grid-template-columns:45% 1fr;gap:34px;align-items:center}
              {{WRAPPER}} .ifs-row.noimg{grid-template-columns:1fr}
              {{WRAPPER}} .ifs-row.rev .ifs-img{order:2}
              {{WRAPPER}} .ifs-img{height:300px;border-radius:12px;background:#e3d6c8 center/cover no-repeat;box-shadow:0 8px 22px rgba(60,40,25,.12)}
              {{WRAPPER}} .ifs-eyebrow{margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9c7b4e}
              {{WRAPPER}} .ifs-title{margin:0 0 12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:26px;line-height:1.2;color:#64402C}
              {{WRAPPER}} .ifs-body{font-size:15px;line-height:1.7;color:#333}{{WRAPPER}} .ifs-body p{margin:0 0 12px}{{WRAPPER}} .ifs-body :last-child{margin-bottom:0}
              {{WRAPPER}} .ifs-btn{display:inline-block;margin-top:16px;font-size:13px;font-weight:600;text-decoration:none;color:#64402C;border:1px solid #D3BAA3;border-radius:7px;padding:10px 18px}
              @media(max-width:760px){{{WRAPPER}} .ifs-row{grid-template-columns:1fr!important}{{WRAPPER}} .ifs-row.rev .ifs-img{order:0}}
            </style>';
            echo '<div class="ifs">';
            $i = 0;
            foreach ($rows as $r) {
                $img = $showimg ? island_ew_image_src($r['image'] ?? '') : '';
                $rev = ($alt && ($i % 2 === 1)) ? ' rev' : '';
                $noimg = $img ? '' : ' noimg';
                echo '<article class="ifs-row' . $rev . $noimg . '">';
                if ($img) {
                    echo '<div class="ifs-img" style="background-image:url(\'' . esc_url($img) . '\')"></div>';
                }
                echo '<div class="ifs-tx">';
                if (!empty($r['subtitle'])) {
                    echo '<p class="ifs-eyebrow">' . esc_html($r['subtitle']) . '</p>';
                }
                echo '<' . $tag . ' class="ifs-title">' . esc_html($r['title'] ?? '') . '</' . $tag . '>';
                if (!empty($r['content'])) {
                    echo '<div class="ifs-body">' . wp_kses_post($r['content']) . '</div>';
                }
                if (!empty($r['button_url'])) {
                    echo '<a class="ifs-btn" href="' . esc_url($r['button_url']) . '">' . esc_html($r['button_label'] ?: 'Read more') . ' &rarr;</a>';
                }
                echo '</div></article>';
                $i++;
            }
            echo '</div>';
        }

        /* Carousel layout: one feature per slide, image on one side / text on
         * the other — same interaction as the Sites Carousel, but driven by
         * the feature_sections repeater (subtitle/title/content/button). */
        private function render_carousel($rows, $s)
        {
            $tag = $this->tg($s['title_tag'], ['h2', 'h3', 'h4', 'div'], 'h3');
            $showimg = $s['show_img'] === 'yes';
            $imgright = ($s['car_img_side'] ?? 'left') === 'right';
            $cid = 'ifsc-' . $this->get_id();
            echo '<style>
              {{WRAPPER}} .ifsc{position:relative;margin-bottom:8px}
              {{WRAPPER}} .ifsc-track{display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:4px 2px 8px;scrollbar-width:none}
              {{WRAPPER}} .ifsc-track::-webkit-scrollbar{display:none}
              {{WRAPPER}} .ifsc-slide{scroll-snap-align:center;flex:0 0 100%;min-width:0}
              {{WRAPPER}} .ifsc-card{display:grid;grid-template-columns:45% 1fr;background:#faf9f7;border:1px solid #DBCEC4;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(60,40,25,.10);min-height:300px}
              {{WRAPPER}} .ifsc-card.rev .ifsc-img{order:2}
              {{WRAPPER}} .ifsc-img{position:relative;background:#cbb89b center/cover no-repeat;min-height:200px}
              {{WRAPPER}} .ifsc-card.noimg{grid-template-columns:1fr}
              {{WRAPPER}} .ifsc-bd{padding:28px 30px;display:flex;flex-direction:column;justify-content:center}
              {{WRAPPER}} .ifsc-eyebrow{margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9c7b4e}
              {{WRAPPER}} .ifsc-title{margin:0 0 12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:24px;line-height:1.2;color:#64402C}
              {{WRAPPER}} .ifsc-body{font-size:14.5px;line-height:1.66;color:#333}{{WRAPPER}} .ifsc-body p{margin:0 0 10px}{{WRAPPER}} .ifsc-body :last-child{margin-bottom:0}
              {{WRAPPER}} .ifsc-btn{align-self:flex-start;margin-top:16px;display:inline-block;font-size:13px;font-weight:600;text-decoration:none;color:#64402C;border:1px solid #D3BAA3;border-radius:7px;padding:9px 16px;background:#fff}
              {{WRAPPER}} .ifsc-nav{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:14px}
              {{WRAPPER}} .ifsc-arw{width:42px;height:42px;border-radius:50%;border:1px solid #D3BAA3;background:#fff;color:#64402C;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(60,40,25,.10);transition:background .2s,color .2s}
              {{WRAPPER}} .ifsc-arw:hover{background:#64402C;color:#fff}
              {{WRAPPER}} .ifsc-dots{display:flex;gap:8px}
              {{WRAPPER}} .ifsc-dot{width:9px;height:9px;border-radius:50%;background:#D3BAA3;border:none;cursor:pointer;padding:0;transition:width .2s,background .2s}
              {{WRAPPER}} .ifsc-dot.on{background:#64402C;width:24px;border-radius:20px}
              @media(max-width:680px){{{WRAPPER}} .ifsc-card{grid-template-columns:1fr}{{WRAPPER}} .ifsc-card.rev .ifsc-img{order:0}{{WRAPPER}} .ifsc-img{height:200px}}
            </style>';
            echo '<div class="ifsc"><div class="ifsc-track" id="' . esc_attr($cid) . '">';
            foreach ($rows as $r) {
                $img = $showimg ? island_ew_image_src($r['image'] ?? '') : '';
                $bg = $img ? ' style="background-image:url(\'' . esc_url($img) . '\')"' : '';
                $rev = ($img && $imgright) ? ' rev' : '';
                $noimg = $img ? '' : ' noimg';
                echo '<div class="ifsc-slide"><article class="ifsc-card' . $rev . $noimg . '">';
                if ($img) {
                    echo '<div class="ifsc-img"' . $bg . '></div>';
                }
                echo '<div class="ifsc-bd">';
                if (!empty($r['subtitle'])) {
                    echo '<p class="ifsc-eyebrow">' . esc_html($r['subtitle']) . '</p>';
                }
                echo '<' . $tag . ' class="ifsc-title">' . esc_html($r['title'] ?? '') . '</' . $tag . '>';
                if (!empty($r['content'])) {
                    echo '<div class="ifsc-body">' . wp_kses_post($r['content']) . '</div>';
                }
                if (!empty($r['button_url'])) {
                    echo '<a class="ifsc-btn" href="' . esc_url($r['button_url']) . '">' . esc_html($r['button_label'] ?: 'Read more') . ' &rarr;</a>';
                }
                echo '</div></article></div>';
            }
            echo '</div>';
            $arrows = $s['car_show_arrows'] === 'yes';
            $dots = $s['car_show_dots'] === 'yes';
            if ($arrows || $dots) {
                echo '<div class="ifsc-nav">';
                if ($arrows) {
                    echo '<button class="ifsc-arw" data-ifsc="prev" aria-label="Previous"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg></button>';
                }
                if ($dots) {
                    echo '<div class="ifsc-dots"></div>';
                }
                if ($arrows) {
                    echo '<button class="ifsc-arw" data-ifsc="next" aria-label="Next"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg></button>';
                }
                echo '</div>';
            }
            echo '</div>';
            $auto = ($s['car_autoplay'] === 'yes') ? max(1500, (int) ($s['car_autoplay_ms'] ?: 5000)) : 0;
            echo '<script>(function(){var t=document.getElementById(' . json_encode($cid) . ');if(!t||t.dataset.init)return;t.dataset.init=1;'
                . 'var car=t.closest(".ifsc"),sl=t.children,n=sl.length,cur=0,dw=car.querySelector(".ifsc-dots"),ap=' . $auto . ';'
                . 'if(dw){for(var i=0;i<n;i++){(function(i){var b=document.createElement("button");b.className="ifsc-dot"+(i?"":" on");b.onclick=function(){go(i)};dw.appendChild(b);})(i);}}'
                . 'function go(i){cur=Math.max(0,Math.min(n-1,i));sl[cur].scrollIntoView({behavior:"smooth",inline:"center",block:"nearest"});paint();}'
                . 'function paint(){if(!dw)return;var d=dw.children;for(var i=0;i<n;i++)d[i].className="ifsc-dot"+(i===cur?" on":"");}'
                . 'car.querySelectorAll("[data-ifsc]").forEach(function(b){b.onclick=function(){go(cur+(b.dataset.ifsc==="next"?1:-1));};});'
                . 't.addEventListener("scroll",function(){var i=Math.round(t.scrollLeft/t.clientWidth);if(i!==cur){cur=i;paint();}});'
                . 'if(ap){setInterval(function(){go(cur+1>=n?0:cur+1);},ap);}'
                . '})();</script>';
        }
    }

    /* ===================================================================
     *  ISLAND FAQS — accordion
     * =================================================================== */
    class Island_FAQs_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_faqs';
        }
        public function get_title()
        {
            return 'Island FAQs';
        }
        public function get_icon()
        {
            return 'eicon-help-o';
        }
        public function get_categories()
        {
            return ['general'];
        }
        protected function register_controls()
        {
            $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('first_open', ['label' => 'Open first item', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->end_controls_section();

            $this->start_controls_section('s', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('gap', ['label' => 'Gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 30]],
                'default' => ['size' => 10, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifaq' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_control('item_bg', ['label' => 'Question / header background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f1ebe4',
                'selectors' => ['{{WRAPPER}} .ifaq-q' => 'background:{{VALUE}}']]);
            $this->add_control('a_bg', ['label' => 'Answer background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .ifaq-a' => 'background:{{VALUE}}']]);
            $this->add_control('sep_color', ['label' => 'Separator color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#e4dace',
                'selectors' => ['{{WRAPPER}} .ifaq-a' => 'border-top-color:{{VALUE}}']]);
            $this->add_control('item_bd', ['label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#DBCEC4',
                'selectors' => ['{{WRAPPER}} .ifaq-item' => 'border-color:{{VALUE}}']]);
            $this->add_control('radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 24]],
                'default' => ['size' => 9, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifaq-item' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('q_color', ['label' => 'Question color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .ifaq-q' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'q_typo', 'selector' => '{{WRAPPER}} .ifaq-q']);
            $this->add_control('a_color', ['label' => 'Answer color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#333333',
                'selectors' => ['{{WRAPPER}} .ifaq-a,{{WRAPPER}} .ifaq-a p' => 'color:{{VALUE}}']]);
            $this->add_control('icon_color', ['label' => 'Icon color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#9c7b4e',
                'selectors' => ['{{WRAPPER}} .ifaq-q::after' => 'color:{{VALUE}}']]);
            $this->end_controls_section();
        }
        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $rows = get_field('faqs', $pid) ?: [];
            if (!$rows) {
                return;
            }
            echo '<style>
              {{WRAPPER}} .ifaq{display:flex;flex-direction:column;gap:10px}
              {{WRAPPER}} .ifaq-item{background:#fff;border:1px solid #DBCEC4;border-radius:9px;overflow:hidden}
              {{WRAPPER}} .ifaq-q{margin:0;cursor:pointer;padding:16px 46px 16px 18px;position:relative;font-weight:700;font-size:15.5px;color:#64402C;list-style:none;background:#f1ebe4}
              {{WRAPPER}} .ifaq-q::-webkit-details-marker{display:none}
              {{WRAPPER}} .ifaq-q::after{content:"+";position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:22px;color:#9c7b4e;transition:transform .2s}
              {{WRAPPER}} details[open] .ifaq-q::after{content:"\2013"}
              {{WRAPPER}} .ifaq-a{padding:16px 18px;background:#fff;border-top:1px solid #e4dace;font-size:14.5px;line-height:1.65;color:#333}{{WRAPPER}} .ifaq-a p{margin:0 0 10px}{{WRAPPER}} .ifaq-a :last-child{margin-bottom:0}
            </style>';
            echo '<div class="ifaq">';
            $i = 0;
            foreach ($rows as $r) {
                $open = ($s['first_open'] === 'yes' && $i === 0) ? ' open' : '';
                echo '<details class="ifaq-item"' . $open . '><summary class="ifaq-q">' . esc_html($r['question'] ?? '') . '</summary>'
                    . '<div class="ifaq-a">' . wp_kses_post($r['answer'] ?? '') . '</div></details>';
                $i++;
            }
            echo '</div>';
        }
    }

    /* ===================================================================
     *  ISLAND PLAN YOUR VISIT / CTA — heading + intro + audience cards
     * =================================================================== */
    class Island_CTA_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_cta';
        }
        public function get_title()
        {
            return 'Island Plan Your Visit (CTA)';
        }
        public function get_icon()
        {
            return 'eicon-call-to-action';
        }
        public function get_categories()
        {
            return ['general'];
        }
        protected function register_controls()
        {
            $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('content_source', [
                'label' => 'Content source', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'auto',
                'options' => ['auto' => 'Automatic (from page)', 'manual' => 'Manual (type it here)'],
                'description' => 'Automatic reads the CTA title / intro / cards from this page\'s fields. Manual lets you write your own heading, intro, cards and images right here (ignores the page fields).',
            ]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER, 'condition' => ['content_source' => 'auto']]);
            $this->add_responsive_control('columns', ['label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3'], 'selectors' => ['{{WRAPPER}} .icta-grid' => 'grid-template-columns:repeat({{VALUE}},1fr)']]);
            $this->add_control('show_badge', ['label' => 'Show audience badge', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);

            /* Manual content — used only when Content source = Manual. */
            $this->add_control('m_heading', ['label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true,
                'default' => 'Plan Your Visit', 'condition' => ['content_source' => 'manual']]);
            $this->add_control('m_intro', ['label' => 'Intro', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 3,
                'condition' => ['content_source' => 'manual']]);
            $card = new \Elementor\Repeater();
            $card->add_control('audience', ['label' => 'Badge', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Direct Travelers']);
            $card->add_control('title', ['label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true]);
            $card->add_control('text', ['label' => 'Text', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 3]);
            $card->add_control('image', ['label' => 'Image (optional)', 'type' => \Elementor\Controls_Manager::MEDIA]);
            $card->add_control('button_label', ['label' => 'Button label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Book now']);
            $card->add_control('button_url', ['label' => 'Button URL', 'type' => \Elementor\Controls_Manager::URL, 'default' => ['url' => '']]);
            $this->add_control('m_cards', [
                'label' => 'Cards', 'type' => \Elementor\Controls_Manager::REPEATER, 'fields' => $card->get_controls(),
                'title_field' => '{{{ audience }}} — {{{ title }}}', 'condition' => ['content_source' => 'manual'],
                'default' => [
                    ['audience' => 'Direct Travelers', 'title' => '', 'button_label' => 'Plan My Trip'],
                    ['audience' => 'Travel Trade', 'title' => '', 'button_label' => 'Partner With Us'],
                ],
            ]);
            $this->end_controls_section();

            $this->start_controls_section('s', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('head_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .icta-head' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'head_typo', 'selector' => '{{WRAPPER}} .icta-head']);
            $this->add_control('intro_color', ['label' => 'Intro color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#4a3a2c',
                'selectors' => ['{{WRAPPER}} .icta-intro,{{WRAPPER}} .icta-intro p' => 'color:{{VALUE}}']]);
            $this->add_control('card_bg', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .icta-card' => 'background:{{VALUE}}']]);
            $this->add_control('card_title', ['label' => 'Card title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .icta-t' => 'color:{{VALUE}}']]);
            $this->add_control('card_text', ['label' => 'Card text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f3e9df',
                'selectors' => ['{{WRAPPER}} .icta-x,{{WRAPPER}} .icta-x p' => 'color:{{VALUE}}']]);
            $this->add_control('btn_bg', ['label' => 'Button background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ECE5DE',
                'selectors' => ['{{WRAPPER}} .icta-btn' => 'background:{{VALUE}}']]);
            $this->add_control('btn_color', ['label' => 'Button text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .icta-btn' => 'color:{{VALUE}}']]);
            $this->end_controls_section();
        }
        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            if (($s['content_source'] ?? 'auto') === 'manual') {
                $title = $s['m_heading'] ?? '';
                $intro = $s['m_intro'] ?? '';
                $rows = [];
                foreach (($s['m_cards'] ?? []) as $c) {
                    $rows[] = [
                        'audience' => $c['audience'] ?? '',
                        'title' => $c['title'] ?? '',
                        'text' => !empty($c['text']) ? wpautop($c['text']) : '',
                        'image' => island_ew_image_src($c['image'] ?? ''),
                        'button_label' => $c['button_label'] ?? '',
                        'button_url' => is_array($c['button_url'] ?? '') ? ($c['button_url']['url'] ?? '') : ($c['button_url'] ?? ''),
                    ];
                }
                $intro = $intro ? wpautop($intro) : '';
            } else {
                $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
                $title = get_field('cta_title', $pid);
                $intro = get_field('cta_intro', $pid);
                $rows = get_field('cta', $pid) ?: [];
            }
            if (!$title && !$intro && !$rows) {
                return;
            }
            echo '<style>
              {{WRAPPER}} .icta-head{margin:0 0 8px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:24px;color:#64402C}
              {{WRAPPER}} .icta-intro{margin:0 0 20px;font-size:15px;line-height:1.6;color:#4a3a2c;max-width:70ch}
              {{WRAPPER}} .icta-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
              {{WRAPPER}} .icta-card{background:#64402C;border-radius:12px;padding:24px 26px;display:flex;flex-direction:column;box-shadow:0 8px 22px rgba(60,40,25,.14)}
              {{WRAPPER}} .icta-badge{align-self:flex-start;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#ECE5DE;border:1px solid rgba(236,229,222,.4);padding:3px 10px;border-radius:20px;margin-bottom:12px}
              {{WRAPPER}} .icta-img{height:150px;border-radius:9px;background:#cbb89b center/cover no-repeat;margin-bottom:14px}
              {{WRAPPER}} .icta-t{margin:0 0 8px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:19px;color:#fff}
              {{WRAPPER}} .icta-x{font-size:14px;line-height:1.6;color:#f3e9df}{{WRAPPER}} .icta-x p{margin:0 0 10px}{{WRAPPER}} .icta-x :last-child{margin-bottom:0}
              {{WRAPPER}} .icta-btn{align-self:flex-start;margin-top:16px;font-size:13.5px;font-weight:700;text-decoration:none;background:#ECE5DE;color:#64402C;border-radius:8px;padding:11px 18px}
              @media(max-width:680px){{{WRAPPER}} .icta-grid{grid-template-columns:1fr!important}}
            </style>';
            if ($title) {
                echo '<h2 class="icta-head">' . esc_html($title) . '</h2>';
            }
            if ($intro) {
                echo '<div class="icta-intro">' . wp_kses_post($intro) . '</div>';
            }
            if ($rows) {
                echo '<div class="icta-grid">';
                foreach ($rows as $r) {
                    echo '<article class="icta-card">';
                    if ($s['show_badge'] === 'yes' && !empty($r['audience'])) {
                        echo '<span class="icta-badge">' . esc_html($r['audience']) . '</span>';
                    }
                    if (!empty($r['image'])) {
                        echo '<div class="icta-img" style="background-image:url(\'' . esc_url($r['image']) . '\')"></div>';
                    }
                    if (!empty($r['title'])) {
                        echo '<h3 class="icta-t">' . esc_html($r['title']) . '</h3>';
                    }
                    if (!empty($r['text'])) {
                        echo '<div class="icta-x">' . wp_kses_post($r['text']) . '</div>';
                    }
                    if (!empty($r['button_url'])) {
                        echo '<a class="icta-btn" href="' . esc_url($r['button_url']) . '">' . esc_html($r['button_label'] ?: 'Book now') . ' &rarr;</a>';
                    }
                    echo '</article>';
                }
                echo '</div>';
            }
        }
    }

    /* ===================================================================
     *  ISLAND TRAVEL INFORMATION — getting there / when / accommodation
     * =================================================================== */
    class Island_Travel_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_travel';
        }
        public function get_title()
        {
            return 'Island Travel Information';
        }
        public function get_icon()
        {
            return 'eicon-map-pin';
        }
        public function get_categories()
        {
            return ['general'];
        }
        protected function register_controls()
        {
            $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('show_meta', ['label' => 'Show duration / difficulty', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->end_controls_section();

            $align = [
                'left' => ['title' => 'Left', 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => 'Center', 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => 'Right', 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => 'Justify', 'icon' => 'eicon-text-align-justify'],
            ];
            /* BLOCK box */
            $this->start_controls_section('s', ['label' => 'Block', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('gap', ['label' => 'Space between blocks', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]],
                'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .itr' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_control('block_bg', ['label' => 'Block background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf9f7',
                'selectors' => ['{{WRAPPER}} .itr-block' => 'background:{{VALUE}}']]);
            $this->add_control('block_bd', ['label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#DBCEC4',
                'selectors' => ['{{WRAPPER}} .itr-block' => 'border-color:{{VALUE}}']]);
            $this->add_control('block_bw', ['label' => 'Border width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 6, 'step' => 0.5]],
                'default' => ['size' => 1, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .itr-block' => 'border-width:{{SIZE}}{{UNIT}};border-style:solid']]);
            $this->add_responsive_control('block_radius', ['label' => 'Radius (per corner)', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'], 'default' => ['top' => 11, 'right' => 11, 'bottom' => 11, 'left' => 11, 'unit' => 'px', 'isLinked' => true],
                'selectors' => ['{{WRAPPER}} .itr-block' => 'border-radius:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->add_responsive_control('block_pad', ['label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 22, 'right' => 24, 'bottom' => 22, 'left' => 24, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .itr-block' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->end_controls_section();

            /* TITLE */
            $this->start_controls_section('s_title', ['label' => 'Block title', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('h_color', ['label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .itr-h' => 'color:{{VALUE}}']]);
            $this->add_responsive_control('h_size', ['label' => 'Title size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 12, 'max' => 48]],
                'default' => ['size' => 19, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .itr-h' => 'font-size:{{SIZE}}{{UNIT}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'h_typo', 'selector' => '{{WRAPPER}} .itr-h',
                'description' => 'Font family, weight, style, letter-spacing… (the quick Size slider above is the easiest way to change size).']);
            $this->add_responsive_control('h_align', ['label' => 'Title alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => ['{{WRAPPER}} .itr-h' => 'text-align:{{VALUE}}']]);
            $this->end_controls_section();

            /* BODY + BUTTON */
            $this->start_controls_section('s_body', ['label' => 'Text & button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('body_color', ['label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#333333',
                'selectors' => ['{{WRAPPER}} .itr-body,{{WRAPPER}} .itr-body p' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'body_typo', 'selector' => '{{WRAPPER}} .itr-body,{{WRAPPER}} .itr-body p']);
            $this->add_responsive_control('body_align', ['label' => 'Text alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => ['{{WRAPPER}} .itr-body,{{WRAPPER}} .itr-body p' => 'text-align:{{VALUE}}']]);
            $this->add_control('btn_h', ['label' => 'Button', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('btn_color', ['label' => 'Button text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .itr-btn' => 'color:{{VALUE}}']]);
            $this->add_control('btn_bg', ['label' => 'Button background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(0,0,0,0)',
                'selectors' => ['{{WRAPPER}} .itr-btn' => 'background:{{VALUE}}']]);
            $this->add_control('btn_bd', ['label' => 'Button border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .itr-btn' => 'border-color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'btn_typo', 'selector' => '{{WRAPPER}} .itr-btn']);
            $this->end_controls_section();
        }
        private function block($title, $html, $blabel, $burl)
        {
            // Only render a block that actually has content — a fallback title
            // alone must never produce an empty box (also treat "<p></p>" as empty).
            if (!$html || trim(wp_strip_all_tags($html)) === '') {
                return '';
            }
            $out = '<div class="itr-block">';
            if ($title) {
                $out .= '<h3 class="itr-h">' . esc_html($title) . '</h3>';
            }
            if ($html) {
                $out .= '<div class="itr-body">' . wp_kses_post($html) . '</div>';
            }
            if ($burl) {
                $out .= '<a class="itr-btn" href="' . esc_url($burl) . '">' . esc_html($blabel ?: 'Learn more') . ' &rarr;</a>';
            }
            return $out . '</div>';
        }
        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $t = get_field('travel_information', $pid);
            if (!$t || !is_array($t)) {
                return;
            }
            echo '<style>
              .itr{display:flex;flex-direction:column;gap:16px}
              .itr-block{background:#faf9f7;border:1px solid #DBCEC4;border-radius:11px;padding:22px 24px}
              .itr-h{margin:0 0 10px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:19px;color:#64402C}
              .itr-body{font-size:14.5px;line-height:1.65;color:#333}.itr-body p{margin:0 0 10px}.itr-body :last-child{margin-bottom:0}
              .itr-btn{display:inline-block;margin-top:12px;font-size:13px;font-weight:600;text-decoration:none;color:#64402C;border:1px solid #D3BAA3;border-radius:7px;padding:9px 16px}
              .itr-meta{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:4px}
              .itr-chip{background:#ECE5DE;border-radius:20px;padding:6px 14px;font-size:13px;color:#64402C}.itr-chip b{font-weight:700}
            </style>';
            $meta = '';
            if ($s['show_meta'] === 'yes' && (!empty($t['recommended_duration']) || !empty($t['difficulty']))) {
                $meta .= '<div class="itr-meta">';
                if (!empty($t['recommended_duration'])) {
                    $meta .= '<span class="itr-chip"><b>Duration:</b> ' . esc_html($t['recommended_duration']) . '</span>';
                }
                if (!empty($t['difficulty'])) {
                    $meta .= '<span class="itr-chip"><b>Difficulty:</b> ' . esc_html($t['difficulty']) . '</span>';
                }
                $meta .= '</div>';
            }
            $blocks = $meta
                . $this->block($t['getting_there_title'] ?? 'Getting There', $t['getting_there'] ?? '', $t['getting_there_button_label'] ?? '', $t['getting_there_button_url'] ?? '')
                . $this->block($t['stay_visit_title'] ?? 'When to Visit', $t['best_time'] ?? '', $t['best_time_button_label'] ?? '', $t['best_time_button_url'] ?? '')
                . $this->block('Where to Stay', $t['accommodation'] ?? '', $t['accommodation_button_label'] ?? '', $t['accommodation_button_url'] ?? '')
                . $this->block('Good to Know', $t['travel_notes'] ?? '', '', '');
            // Nothing to show -> render nothing (no empty wrapper).
            if (trim($blocks) === '') {
                return;
            }
            echo '<div class="itr">' . $blocks . '</div>';
        }
    }

    /* ===================================================================
     *  ISLAND SOURCES & LINKS — sources + related links
     * =================================================================== */
    class Island_Sources_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_sources';
        }
        public function get_title()
        {
            return 'Island Sources & Links';
        }
        public function get_icon()
        {
            return 'eicon-editor-link';
        }
        public function get_categories()
        {
            return ['general'];
        }
        protected function register_controls()
        {
            $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('sources_title', ['label' => 'Sources heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Sources & Citations']);
            $this->add_control('related_title', ['label' => 'Related heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Explore More']);
            $this->add_control('show_related', ['label' => 'Show related links', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_responsive_control('columns', ['label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3'], 'selectors' => ['{{WRAPPER}} .isrc-list' => 'grid-template-columns:repeat({{VALUE}},1fr)']]);
            $this->end_controls_section();

            $this->start_controls_section('s', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('h_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .isrc-h' => 'color:{{VALUE}}']]);
            $this->add_control('link_color', ['label' => 'Link color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3a5a8c',
                'selectors' => ['{{WRAPPER}} .isrc-list a' => 'color:{{VALUE}}']]);
            $this->add_control('text_color', ['label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5a4636',
                'selectors' => ['{{WRAPPER}} .isrc-list li' => 'color:{{VALUE}}']]);
            $this->end_controls_section();
        }
        private function links($rows, $heading)
        {
            if (!$rows) {
                return;
            }
            echo '<div class="isrc-block">';
            if ($heading) {
                echo '<h3 class="isrc-h">' . esc_html($heading) . '</h3>';
            }
            echo '<ul class="isrc-list">';
            foreach ($rows as $r) {
                $label = $r['label'] ?? '';
                $url = $r['url'] ?? '';
                if (!$label && !$url) {
                    continue;
                }
                if ($url) {
                    echo '<li><a href="' . esc_url($url) . '" rel="nofollow noopener" target="_blank">' . esc_html($label ?: $url) . '</a></li>';
                } else {
                    echo '<li>' . esc_html($label) . '</li>';
                }
            }
            echo '</ul></div>';
        }
        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : get_the_ID();
            $sources = get_field('sources', $pid) ?: [];
            $related = ($s['show_related'] === 'yes') ? (get_field('related_links', $pid) ?: []) : [];
            if (!$sources && !$related) {
                return;
            }
            echo '<style>
              {{WRAPPER}} .isrc-block{margin-bottom:22px}
              {{WRAPPER}} .isrc-h{margin:0 0 12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:20px;color:#64402C}
              {{WRAPPER}} .isrc-list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(2,1fr);gap:8px 26px}
              {{WRAPPER}} .isrc-list li{font-size:14px;line-height:1.5;color:#5a4636;padding-left:16px;position:relative}
              {{WRAPPER}} .isrc-list li::before{content:"\2192";position:absolute;left:0;color:#9c7b4e}
              {{WRAPPER}} .isrc-list a{color:#3a5a8c;text-decoration:none}{{WRAPPER}} .isrc-list a:hover{text-decoration:underline}
              @media(max-width:680px){{{WRAPPER}} .isrc-list{grid-template-columns:1fr!important}}
            </style>';
            $this->links($sources, $s['sources_title']);
            $this->links($related, $s['related_title']);
        }
    }

    } // end: declare widget classes once

    $widgets_manager->register(new Island_Wildlife_Widget());
    $widgets_manager->register(new Island_QuickFacts_Widget());
    $widgets_manager->register(new Island_VisitorSites_Widget());
    $widgets_manager->register(new Island_SitesCarousel_Widget());
    $widgets_manager->register(new Island_WildlifeCalendar_Widget());
    $widgets_manager->register(new Island_FeatureSections_Widget());
    $widgets_manager->register(new Island_FAQs_Widget());
    $widgets_manager->register(new Island_CTA_Widget());
    $widgets_manager->register(new Island_Travel_Widget());
    $widgets_manager->register(new Island_Sources_Widget());
});
