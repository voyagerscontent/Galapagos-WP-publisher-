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

/** True inside the Elementor editor or its preview — used to skip front-end-only
 * behaviour (like hiding an empty section) so blocks stay visible while editing. */
if (!function_exists('island_ew_is_editing')) {
    function island_ew_is_editing()
    {
        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }
        $p = \Elementor\Plugin::$instance;
        return (isset($p->editor) && $p->editor->is_edit_mode())
            || (isset($p->preview) && $p->preview->is_preview_mode());
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
            $this->add_control('hide_on_ids', [
                'label' => 'Hide title on these page IDs', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true,
                'placeholder' => 'e.g. 1197, 1205', 'condition' => ['show_title' => 'yes'],
                'description' => 'Comma-separated island page IDs where this widget title should NOT show — use it for islands whose content already has its own calendar heading (e.g. Santiago, Isabela), so it is not duplicated. Other islands keep the title.',
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
            $hidden = array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($s['hide_on_ids'] ?? ''))));
            $show_title = ($s['show_title'] ?? 'yes') === 'yes' && !in_array((int) $pid, $hidden, true);
            if ($show_title && !empty($s['title_text'])) {
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
            $this->add_control('hover_expand', ['label' => 'Clamp text, expand on hover', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'condition' => ['layout' => 'rows'],
                'description' => 'Show a few lines by default; the full text opens on hover (tap on mobile) and closes on leave. A brown bottom border hints there is more.']);
            $this->add_control('clamp_lines', ['label' => 'Lines when collapsed', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 5, 'min' => 2, 'max' => 20,
                'condition' => ['layout' => 'rows', 'hover_expand' => 'yes'],
                'selectors' => ['{{WRAPPER}} .ifs' => '--cl:{{VALUE}}']]);
            $this->add_control('open_h', ['label' => 'Open height (max)', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 300, 'max' => 2000]],
                'default' => ['size' => 900, 'unit' => 'px'], 'condition' => ['layout' => 'rows', 'hover_expand' => 'yes'],
                'description' => 'Max height when opened on hover. Lower = snappier close; raise it if a long section gets cut off.',
                'selectors' => ['{{WRAPPER}} .ifs' => '--open:{{SIZE}}{{UNIT}}']]);

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

            /* Height / scroll — pair this column with "At a Glance" on its left.
             * When on, the feature list matches the neighbouring column's height
             * and scrolls internally so the two columns stay level (desktop only,
             * rows layout). */
            $this->start_controls_section('cbind', ['label' => 'Height / Scroll', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT, 'condition' => ['layout' => 'rows']]);
            $this->add_control('bind_height', ['label' => 'Match neighbour column (internal scroll)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '',
                'description' => 'On: this widget matches the height of the column next to it (e.g. “At a Glance”) and scrolls its feature list inside that height, so the two columns end level. Two-column row, desktop only — on mobile it flows normally.']);
            $this->add_control('bind_min', ['label' => 'Minimum height (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 300, 'min' => 160,
                'condition' => ['bind_height' => 'yes'],
                'description' => 'Never shrinks below this, even if the neighbour column is very short.']);
            $this->add_control('bind_fade', ['label' => 'Fade color (match page bg)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#efe7dd',
                'condition' => ['bind_height' => 'yes'], 'selectors' => ['{{WRAPPER}} .ifs-scrollwrap' => '--ifs-fade:{{VALUE}}'],
                'description' => 'The soft “there is more below” fade at the bottom edge. Set to the page/section background so it blends.']);
            $this->end_controls_section();

            $this->start_controls_section('s', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('fade_color', ['label' => 'Fade color (hint of “more”)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#efe7dd',
                'condition' => ['hover_expand' => 'yes'], 'selectors' => ['{{WRAPPER}} .ifs' => '--fade:{{VALUE}}'],
                'description' => 'Set this to the page/section background so the text fades softly into it (elegant “there’s more” cue instead of a hard line).']);
            $this->add_control('gap', ['label' => 'Row gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 100]],
                'default' => ['size' => 48, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('img_w', ['label' => 'Image width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['%' => ['min' => 25, 'max' => 65]],
                'default' => ['size' => 42, 'unit' => '%'], 'selectors' => [
                    '{{WRAPPER}} .ifs-top' => 'grid-template-columns:{{SIZE}}% 1fr',
                    '{{WRAPPER}} .ifs-row.rev .ifs-top' => 'grid-template-columns:1fr {{SIZE}}%']]);
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

            /* Card (each feature section sits in a card) */
            $this->add_control('card_h', ['label' => 'Card', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('card_bg', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FBF8F4',
                'selectors' => ['{{WRAPPER}} .ifs-row' => 'background:{{VALUE}}']]);
            $this->add_control('card_border', ['label' => 'Card border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(100,64,44,.14)',
                'selectors' => ['{{WRAPPER}} .ifs-row' => 'border-color:{{VALUE}}']]);
            $this->add_control('card_radius', ['label' => 'Card radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 32]],
                'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-row' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('card_pad', ['label' => 'Card padding', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 56]],
                'default' => ['size' => 28, 'unit' => 'px'], 'selectors' => [
                    '{{WRAPPER}} .ifs-top' => 'padding:{{SIZE}}{{UNIT}}',
                    '{{WRAPPER}} .ifs-tbl' => 'margin-left:{{SIZE}}{{UNIT}};margin-right:{{SIZE}}{{UNIT}}',
                    '{{WRAPPER}} .ifs-after' => 'padding-left:{{SIZE}}{{UNIT}};padding-right:{{SIZE}}{{UNIT}}']]);

            /* Table (styling for feature sections that contain a table) */
            $this->add_control('tbl_h', ['label' => 'Table', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('tbl_head_bg', ['label' => 'Header background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .ifs-tbl thead th' => 'background:{{VALUE}}']]);
            $this->add_control('tbl_head_tx', ['label' => 'Header text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#F6EFE7',
                'selectors' => ['{{WRAPPER}} .ifs-tbl thead th' => 'color:{{VALUE}}']]);
            $this->add_control('tbl_head_fs', ['label' => 'Header text size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 9, 'max' => 18]],
                'default' => ['size' => 10.5, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-tbl thead th' => 'font-size:{{SIZE}}{{UNIT}}']]);
            $this->add_control('tbl_tx', ['label' => 'Cell text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3A2A1E',
                'selectors' => ['{{WRAPPER}} .ifs-tbl tbody td' => 'color:{{VALUE}}']]);
            $this->add_control('tbl_fs', ['label' => 'Cell text size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 11, 'max' => 20]],
                'default' => ['size' => 13, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-tbl table' => 'font-size:{{SIZE}}{{UNIT}}']]);
            $this->add_control('tbl_first', ['label' => 'First column color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .ifs-tbl td:first-child' => 'color:{{VALUE}}']]);
            $this->add_control('tbl_stripe', ['label' => 'Row stripe', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#F5EEE4',
                'selectors' => ['{{WRAPPER}} .ifs-tbl tbody tr:nth-child(even)' => 'background:{{VALUE}}']]);
            $this->add_control('tbl_line', ['label' => 'Border / lines', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(100,64,44,.14)',
                'selectors' => ['{{WRAPPER}} .ifs-tbl' => 'border-color:{{VALUE}}', '{{WRAPPER}} .ifs-tbl tbody td' => 'border-top-color:{{VALUE}}']]);
            $this->add_control('tbl_bw', ['label' => 'Line thickness', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 4]],
                'default' => ['size' => 1, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-tbl tbody td' => 'border-top-width:{{SIZE}}{{UNIT}}']]);
            $this->add_control('tbl_radius', ['label' => 'Table radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 24]],
                'default' => ['size' => 12, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-tbl' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('tbl_pad', ['label' => 'Cell padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px'],
                'default' => ['top' => 13, 'right' => 16, 'bottom' => 13, 'left' => 16, 'unit' => 'px', 'isLinked' => false],
                'selectors' => ['{{WRAPPER}} .ifs-tbl thead th,{{WRAPPER}} .ifs-tbl tbody td' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->end_controls_section();

            /* Infographic (renders after a section's card when that row has an
             * "infographic" image — content width, between this card and next). */
            $this->start_controls_section('si', ['label' => 'Infographic', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('info_lightbox', ['label' => 'Open full on click (lightbox)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'On: a controlled-height banner that opens the full image on click. Off: show the full image inline.']);
            $this->add_responsive_control('info_maxw', ['label' => 'Max width', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['%', 'px'],
                'range' => ['%' => ['min' => 40, 'max' => 100], 'px' => ['min' => 400, 'max' => 1200]], 'default' => ['size' => 100, 'unit' => '%'],
                'selectors' => ['{{WRAPPER}} .ifs-info' => 'max-width:{{SIZE}}{{UNIT}};margin-left:auto;margin-right:auto']]);
            $this->add_control('info_h', ['label' => 'Banner height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 140, 'max' => 520]],
                'default' => ['size' => 240, 'unit' => 'px'], 'condition' => ['info_lightbox' => 'yes'],
                'selectors' => ['{{WRAPPER}} .ifs-info-band' => '--ig-h:{{SIZE}}{{UNIT}}'],
                'description' => 'Height of the preview strip; the full image opens in the lightbox.']);
            $this->add_control('info_radius', ['label' => 'Corner radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .ifs-info-band,{{WRAPPER}} .ifs-info-frame' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('info_fade', ['label' => 'Fade color (match card bg)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FBF8F4',
                'selectors' => ['{{WRAPPER}} .ifs-info-band' => '--ig-fade:{{VALUE}}'], 'condition' => ['info_lightbox' => 'yes']]);
            $this->add_control('info_band_border', ['label' => 'Border (hairline, optional)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '',
                'selectors' => ['{{WRAPPER}} .ifs-info-band' => 'box-shadow:inset 0 0 0 1px {{VALUE}}'],
                'description' => 'Off by default — just the rounded image. Set a color for a thin inset outline if ever needed.']);
            $this->add_control('info_btn_h', ['label' => 'Button', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before', 'condition' => ['info_lightbox' => 'yes']]);
            $this->add_control('info_btn_label', ['label' => 'Button label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'View full infographic',
                'description' => 'Neutral by default; you can use the infographic title instead.',
                'condition' => ['info_lightbox' => 'yes']]);
            $this->add_control('info_btn_bg', ['label' => 'Button background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5a3d2b',
                'selectors' => ['{{WRAPPER}} .ifs-info-btn' => 'background:{{VALUE}}'], 'condition' => ['info_lightbox' => 'yes']]);
            $this->add_control('info_btn_color', ['label' => 'Button text', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f6efe7',
                'selectors' => ['{{WRAPPER}} .ifs-info-btn' => 'color:{{VALUE}}'], 'condition' => ['info_lightbox' => 'yes']]);
            $this->add_control('info_title_h', ['label' => 'Title & caption', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('info_title_color', ['label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .ifs-info-title' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'info_title_typo', 'selector' => '{{WRAPPER}} .ifs-info-title']);
            $this->add_control('info_cap_color', ['label' => 'Caption color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#7a6a5c',
                'selectors' => ['{{WRAPPER}} .ifs-info-cap' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'info_cap_typo', 'selector' => '{{WRAPPER}} .ifs-info-cap']);
            $this->add_control('info_gap', ['label' => 'Space around', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 80]],
                'default' => ['size' => 8, 'unit' => 'px'], 'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .ifs-info' => 'margin-top:{{SIZE}}{{UNIT}};margin-bottom:{{SIZE}}{{UNIT}}']]);
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
              {{WRAPPER}} .ifs{display:flex;flex-direction:column;gap:40px;--open:900px}
              {{WRAPPER}} .ifs-row{background:#FBF8F4;border:1px solid rgba(100,64,44,.14);border-radius:16px;box-shadow:0 10px 30px rgba(60,40,25,.10);overflow:hidden;display:flex;flex-direction:column}
              {{WRAPPER}} .ifs-top{display:grid;grid-template-columns:42% 1fr;gap:32px;align-items:center;padding:28px}
              {{WRAPPER}} .ifs-row.rev .ifs-top{grid-template-columns:1fr 42%}
              {{WRAPPER}} .ifs-row.noimg .ifs-top{grid-template-columns:1fr!important}
              {{WRAPPER}} .ifs-row.rev .ifs-top .ifs-img{order:2}
              {{WRAPPER}} .ifs-row.has-table .ifs-top{padding-bottom:4px}
              {{WRAPPER}} .ifs-img{height:300px;border-radius:12px;background:#e3d6c8 center/cover no-repeat;box-shadow:0 8px 22px rgba(60,40,25,.10)}
              {{WRAPPER}} .ifs-eyebrow{margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9c7b4e}
              {{WRAPPER}} .ifs-title{margin:0 0 12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:26px;line-height:1.2;color:#64402C}
              {{WRAPPER}} .ifs-body{font-size:15px;line-height:1.7;color:#3A2A1E}{{WRAPPER}} .ifs-body p{margin:0 0 12px}{{WRAPPER}} .ifs-body :last-child{margin-bottom:0}
              {{WRAPPER}} .ifs.hx .ifs-body{position:relative;max-height:calc(var(--cl,5) * 1.75em);overflow:hidden;transition:max-height .45s ease}
              {{WRAPPER}} .ifs.hx .ifs-body::after{content:"";position:absolute;left:0;right:0;bottom:0;height:1.8em;background:linear-gradient(rgba(0,0,0,0),var(--fade,#FBF8F4));pointer-events:none;transition:opacity .3s ease}
              {{WRAPPER}} .ifs.hx .ifs-row:hover .ifs-body,{{WRAPPER}} .ifs.hx .ifs-row:focus-within .ifs-body,{{WRAPPER}} .ifs.hx .ifs-row.is-open .ifs-body{max-height:var(--open,900px)}
              {{WRAPPER}} .ifs.hx .ifs-row:hover .ifs-body::after,{{WRAPPER}} .ifs.hx .ifs-row:focus-within .ifs-body::after,{{WRAPPER}} .ifs.hx .ifs-row.is-open .ifs-body::after{opacity:0}
              @media(prefers-reduced-motion:reduce){{{WRAPPER}} .ifs.hx .ifs-body{transition:none}{{WRAPPER}} .ifs.hx .ifs-body::after{transition:none}}
              {{WRAPPER}} .ifs-btn{display:inline-block;margin-top:16px;font-size:13px;font-weight:600;text-decoration:none;color:#64402C;border:1px solid #D3BAA3;border-radius:7px;padding:10px 18px}
              {{WRAPPER}} .ifs-tbl{overflow-x:auto;margin:16px 28px 26px;border:1px solid rgba(100,64,44,.14);border-radius:12px}
              {{WRAPPER}} .ifs-tbl table{border-collapse:collapse;width:100%;min-width:520px;font-size:13px}
              {{WRAPPER}} .ifs-tbl thead th{background:#64402C;color:#F6EFE7;text-align:left;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;padding:13px 16px;white-space:nowrap}
              {{WRAPPER}} .ifs-tbl tbody td{padding:13px 16px;border-top:1px solid rgba(100,64,44,.14);vertical-align:top;color:#3A2A1E;font-variant-numeric:tabular-nums}
              {{WRAPPER}} .ifs-tbl tbody tr:nth-child(even){background:#F5EEE4}
              {{WRAPPER}} .ifs-tbl tbody tr:hover{background:rgba(100,64,44,.06)}
              {{WRAPPER}} .ifs-tbl td:first-child{font-weight:700;color:#64402C}
              {{WRAPPER}} .ifs-after{padding:0 28px 26px;font-size:15px;line-height:1.7;color:#3A2A1E}{{WRAPPER}} .ifs-after p{margin:0 0 12px}{{WRAPPER}} .ifs-after :last-child{margin-bottom:0}
              {{WRAPPER}} .ifs-info{width:100%;padding:20px 28px 26px;border-top:1px solid rgba(100,64,44,.10)}
              {{WRAPPER}} .ifs-info-title{margin:0 0 12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:400;font-size:19px;color:#64402C}
              {{WRAPPER}} .ifs-info-band{position:relative;display:block;width:100%;height:var(--ig-h,240px);border-radius:14px;overflow:hidden;cursor:zoom-in}
              {{WRAPPER}} .ifs-info-band img{width:100%;height:100%;object-fit:cover;object-position:top;display:block;border-radius:inherit}
              {{WRAPPER}} .ifs-info-band::after{content:"";position:absolute;left:0;right:0;bottom:0;height:84px;background:linear-gradient(rgba(251,248,244,0),var(--ig-fade,#FBF8F4));pointer-events:none}
              {{WRAPPER}} .ifs-info-btn{position:absolute;left:50%;bottom:16px;transform:translateX(-50%);z-index:2;white-space:nowrap;background:#5a3d2b;color:#f6efe7;font-size:12.5px;font-weight:700;padding:9px 18px;border-radius:22px;box-shadow:0 6px 16px rgba(60,40,25,.25);cursor:zoom-in}
              {{WRAPPER}} .ifs-info-frame{display:block;width:100%;border-radius:14px;overflow:hidden}
              {{WRAPPER}} .ifs-info-frame img{width:100%;height:auto;display:block}
              {{WRAPPER}} .ifs-info-cap{margin:12px 0 0;font-size:13px;line-height:1.6;color:#7a6a5c}
              @media(max-width:760px){{{WRAPPER}} .ifs-top,{{WRAPPER}} .ifs-row.rev .ifs-top{grid-template-columns:1fr!important}{{WRAPPER}} .ifs-row.rev .ifs-top .ifs-img{order:0}{{WRAPPER}} .ifs-tbl{margin:14px 16px 20px}}
              {{WRAPPER}} .ifs-scrollwrap{position:relative}
              {{WRAPPER}} .ifs-scroll{overflow-y:auto;padding-right:10px;scrollbar-width:thin;scrollbar-color:#c8ad82 transparent}
              {{WRAPPER}} .ifs-scroll::-webkit-scrollbar{width:8px}
              {{WRAPPER}} .ifs-scroll::-webkit-scrollbar-thumb{background:#c8ad82;border-radius:999px}
              {{WRAPPER}} .ifs-scroll::-webkit-scrollbar-track{background:transparent}
              {{WRAPPER}} .ifs-fade{position:absolute;left:0;right:10px;bottom:0;height:54px;background:linear-gradient(rgba(0,0,0,0),var(--ifs-fade,#efe7dd));pointer-events:none;opacity:0;transition:opacity .2s}
              {{WRAPPER}} .ifs-scrollwrap.is-overflow .ifs-fade{opacity:1}
              @media(max-width:820px){{{WRAPPER}} .ifs-scroll{max-height:none!important;overflow:visible;padding-right:0}{{WRAPPER}} .ifs-fade{display:none}}
            </style>';
            $hx = ($s['hover_expand'] ?? 'yes') === 'yes' ? ' hx' : '';
            $lb = ($s['info_lightbox'] ?? 'yes') === 'yes';
            $bind = ($s['bind_height'] ?? '') === 'yes';
            $info_any = false;
            if ($bind) {
                $bmin = max(160, (int) ($s['bind_min'] ?? 300));
                echo '<div class="ifs-scrollwrap" data-ifs-bind="1" data-ifs-min="' . $bmin . '"><div class="ifs-scroll">';
            }
            echo '<div class="ifs' . $hx . '">';
            $i = 0;
            foreach ($rows as $r) {
                $img = $showimg ? island_ew_image_src($r['image'] ?? '') : '';
                $rev = ($alt && ($i % 2 === 1)) ? ' rev' : '';
                $noimg = $img ? '' : ' noimg';
                // A section that carries a table renders the table full-width below
                // the image+text; everything else keeps the zig-zag.
                $content = $r['content'] ?? '';
                $table = '';
                $before = $content;
                $after = '';
                if (stripos($content, '<table') !== false
                    && preg_match('/<table\b[\s\S]*?<\/table>/i', $content, $mm)) {
                    $table = $mm[0];
                    $p = strpos($content, $table);
                    $before = substr($content, 0, $p);
                    $after = substr($content, $p + strlen($table));
                }
                $has_table = $table !== '';
                $btn = !empty($r['button_url'])
                    ? '<a class="ifs-btn" href="' . esc_url($r['button_url']) . '">' . esc_html($r['button_label'] ?: 'Read more') . ' &rarr;</a>'
                    : '';
                echo '<article class="ifs-row' . $rev . $noimg . ($has_table ? ' has-table' : '') . '" tabindex="0">';
                echo '<div class="ifs-top">';
                if ($img) {
                    echo '<div class="ifs-img" style="background-image:url(\'' . esc_url($img) . '\')"></div>';
                }
                echo '<div class="ifs-tx">';
                if (!empty($r['subtitle'])) {
                    echo '<p class="ifs-eyebrow">' . esc_html($r['subtitle']) . '</p>';
                }
                echo '<' . $tag . ' class="ifs-title">' . esc_html($r['title'] ?? '') . '</' . $tag . '>';
                if (trim(wp_strip_all_tags($before)) !== '') {
                    echo '<div class="ifs-body">' . wp_kses_post($before) . '</div>';
                }
                if (!$has_table && $btn) {
                    echo $btn;
                }
                echo '</div></div>';  // .ifs-tx .ifs-top
                if ($has_table) {
                    echo '<div class="ifs-tbl">' . wp_kses_post($table) . '</div>';
                    if (trim(wp_strip_all_tags($after)) !== '') {
                        echo '<div class="ifs-after">' . wp_kses_post($after) . '</div>';
                    }
                    if ($btn) {
                        echo '<div style="padding:0 28px 26px">' . $btn . '</div>';
                    }
                }
                // Infographic: rendered INSIDE the card (after the text/table),
                // as a controlled-height banner that opens the full image in a
                // lightbox on click. Heading = infographic_title.
                $info = island_ew_image_src($r['infographic'] ?? '');
                if ($info) {
                    $ititle = trim((string) ($r['infographic_title'] ?? ''));
                    $cap = trim((string) ($r['infographic_caption'] ?? ''));
                    $alt = esc_attr($ititle ?: $cap);
                    echo '<div class="ifs-info">';
                    if ($ititle !== '') {
                        echo '<p class="ifs-info-title">' . esc_html($ititle) . '</p>';
                    }
                    if ($lb) {
                        $blabel = trim((string) ($s['info_btn_label'] ?? '')) ?: 'View full infographic';
                        echo '<div class="ifs-info-band" data-ifs-full="' . esc_url($info) . '" role="button" tabindex="0">'
                            . '<img src="' . esc_url($info) . '" alt="' . $alt . '" loading="lazy">'
                            . '<span class="ifs-info-btn">' . esc_html($blabel) . ' &#8599;</span>'
                            . '</div>';
                        $info_any = true;
                    } else {
                        echo '<div class="ifs-info-frame"><img src="' . esc_url($info) . '" alt="' . $alt . '" loading="lazy"></div>';
                    }
                    if ($cap !== '') {
                        echo '<p class="ifs-info-cap">' . esc_html($cap) . '</p>';
                    }
                    echo '</div>';
                }
                echo '</article>';
                $i++;
            }
            echo '</div>';  // .ifs
            if ($bind) {
                echo '</div><div class="ifs-fade"></div></div>';  // close .ifs-scroll, fade, .ifs-scrollwrap
            }
            // Open on hover. Driven by JS (mouseenter/leave) so it never depends
            // on the CSS :hover firing — which Elementor's editor overlay can
            // swallow. Touch: tap toggles it (and :focus-within via tabindex).
            // MUST print right after the .ifs container: it binds via
            // currentScript.previousElementSibling, so nothing may sit between.
            if ($hx) {
                echo '<script>(function(){var w=document.currentScript&&document.currentScript.previousElementSibling;'
                    . 'if(!w||!w.querySelectorAll)return;w.querySelectorAll(".ifs-row").forEach(function(c){'
                    . 'c.addEventListener("mouseenter",function(){c.classList.add("is-open");});'
                    . 'c.addEventListener("mouseleave",function(){c.classList.remove("is-open");});'
                    . 'c.addEventListener("touchstart",function(e){if(!e.target.closest("a"))c.classList.toggle("is-open");},{passive:true});});})();</script>';
            }
            // Lightbox assets come AFTER the hover script so they don't break the
            // previousElementSibling lookup above.
            if ($info_any) {
                $this->fs_lightbox();
            }
            // Height-binding script LAST — must not sit between the .ifs container
            // and the hover script (which uses previousElementSibling).
            if ($bind) {
                $this->fs_bind_script();
            }
        }

        /** Match this widget's height to its neighbouring column (e.g. the
         * "At a Glance" card) and scroll the feature list internally so the two
         * columns stay level. Printed once per request; desktop only. Works with
         * both classic columns (.elementor-column) and flexbox containers (.e-con). */
        private function fs_bind_script()
        {
            static $done = false;
            if ($done) {
                return;
            }
            $done = true;
            echo '<script>(function(){if(window.__islandIfsBind)return;window.__islandIfsBind=1;'
                . 'function colOf(w){var g=w.closest(".elementor-widget")||w;var c=g.closest(".elementor-column");if(c)return{col:c,sel:".elementor-column"};c=g.closest(".e-con.e-child")||g.closest(".e-con");return c?{col:c,sel:".e-con"}:null;}'
                . 'function sync(){var W=document.querySelectorAll(\'.ifs-scrollwrap[data-ifs-bind="1"]\');Array.prototype.forEach.call(W,function(wrap){'
                . 'var sc=wrap.querySelector(".ifs-scroll");if(!sc)return;'
                . 'if(window.innerWidth<=820){sc.style.maxHeight="";wrap.classList.remove("is-overflow");return;}'
                . 'var info=colOf(wrap);if(!info||!info.col.parentElement){sc.style.maxHeight="";return;}'
                . 'var col=info.col,sel=info.sel;var sibs=Array.prototype.filter.call(col.parentElement.children,function(x){return x!==col&&x.matches&&x.matches(sel);});'
                . 'if(!sibs.length){sc.style.maxHeight="";wrap.classList.remove("is-overflow");return;}'
                . 'var target=0;sibs.forEach(function(x){target=Math.max(target,x.offsetHeight);});if(target<=0){sc.style.maxHeight="";return;}'
                . 'sc.style.maxHeight="none";var above=sc.getBoundingClientRect().top-col.getBoundingClientRect().top;'
                . 'var mn=parseInt(wrap.getAttribute("data-ifs-min"),10)||300;var h=Math.max(mn,target-above-6);sc.style.maxHeight=h+"px";'
                . 'wrap.classList.toggle("is-overflow",sc.scrollHeight>sc.clientHeight+2);});}'
                . 'var t;function later(){clearTimeout(t);t=setTimeout(sync,60);}'
                . 'window.addEventListener("resize",later);'
                . 'window.addEventListener("load",function(){sync();setTimeout(sync,300);setTimeout(sync,900);});'
                . 'if(document.readyState!=="loading"){sync();setTimeout(sync,300);}else{document.addEventListener("DOMContentLoaded",function(){sync();setTimeout(sync,300);});}'
                . 'if("ResizeObserver" in window){var ro=new ResizeObserver(later);setTimeout(function(){'
                . 'document.querySelectorAll(\'.ifs-scrollwrap[data-ifs-bind="1"]\').forEach(function(wrap){var info=colOf(wrap);'
                . 'if(info&&info.col.parentElement){Array.prototype.forEach.call(info.col.parentElement.children,function(x){if(x!==info.col)ro.observe(x);});}});},200);}'
                . '})();</script>';
        }

        /** Shared infographic lightbox (printed once per request): a full-screen
         * overlay that shows the full image when a banner is clicked. */
        private function fs_lightbox()
        {
            static $done = false;
            if ($done) {
                return;
            }
            $done = true;
            echo '<style>'
                . '.ifs-lb{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(30,20,12,.82)}'
                . '.ifs-lb.on{display:flex}'
                . '.ifs-lb img{max-width:1000px;width:100%;height:auto;max-height:88vh;object-fit:contain;border-radius:12px;display:block}'
                . '.ifs-lb .ifs-lb-x{position:absolute;top:14px;right:22px;color:#fff;font-size:32px;line-height:1;cursor:pointer;opacity:.85}'
                . '</style>'
                . '<div class="ifs-lb" id="ifs-lb"><span class="ifs-lb-x">&times;</span><img alt=""></div>';
            echo '<script>(function(){if(window.__ifsLb)return;window.__ifsLb=1;'
                . 'var lb=document.getElementById("ifs-lb"),im=lb.querySelector("img");'
                . 'document.addEventListener("click",function(e){'
                . 'var t=e.target.closest("[data-ifs-full]");'
                . 'if(t){im.src=t.getAttribute("data-ifs-full");lb.classList.add("on");return;}'
                . 'if(e.target===lb||e.target.classList.contains("ifs-lb-x"))lb.classList.remove("on");});'
                . 'document.addEventListener("keydown",function(e){if(e.key==="Escape")lb.classList.remove("on");});'
                . '})();</script>';
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
            $this->add_control('faq_schema', ['label' => 'Output FAQ schema (JSON-LD)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Adds an FAQPage structured-data block built from these Q&As (like Elementor\'s Toggle). Use only ONE FAQ widget per page with this ON to avoid duplicate schema.']);
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

            // FAQPage structured data, built from the same Q&As (like Elementor's
            // Toggle). Slashes stay escaped so an answer can never break out of
            // the <script>. Keep ONE emitting widget per page (control above).
            if (($s['faq_schema'] ?? 'yes') === 'yes') {
                $entities = [];
                foreach ($rows as $r) {
                    $q = trim(wp_strip_all_tags((string) ($r['question'] ?? '')));
                    $a = trim((string) ($r['answer'] ?? ''));
                    if ($q === '' || $a === '') {
                        continue;
                    }
                    $entities[] = [
                        '@type' => 'Question',
                        'name' => $q,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                    ];
                }
                if ($entities) {
                    $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities];
                    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE) . '</script>';
                }
            }
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

            /* Heading — show/hide + optional override, so you can control the
             * title from the widget without editing ACF. */
            $this->add_control('show_title', ['label' => 'Show heading', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'separator' => 'before']);
            $this->add_control('title_tag', ['label' => 'Heading tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h2',
                'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'], 'condition' => ['show_title' => 'yes']]);
            $this->add_control('title_text', ['label' => 'Heading text (override)', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true,
                'placeholder' => 'Blank = this page’s Plan Your Visit heading',
                'description' => 'Leave blank to use each island\'s own heading (varies per page). Type here to force a heading — but that text is SHARED by every island in this template.',
                'condition' => ['show_title' => 'yes', 'content_source' => 'auto']]);
            $this->add_control('show_intro', ['label' => 'Show intro / description', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Turn OFF to keep this widget as CTA cards only (no title, no description) — use it when you already placed the heading/description above with other widgets, so nothing is duplicated.']);

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

            /* Background image (per card) — plain full-card photo; you compose
             * the image and arrange the content yourself. */
            $this->add_control('bg_h', ['label' => 'Background image (per card)', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_responsive_control('card_minh', ['label' => 'Card min height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 640]],
                'default' => ['size' => 0, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .icta-card' => 'min-height:{{SIZE}}{{UNIT}}'],
                'description' => 'Give the cards a taller minimum height so the photo has room to show.']);
            $this->add_control('img_fit', ['label' => 'Image fit', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'cover',
                'options' => ['cover' => 'Cover (fill)', 'contain' => 'Contain (whole image)'],
                'selectors' => ['{{WRAPPER}} .icta-bg' => 'background-size:{{VALUE}}']]);
            $this->add_responsive_control('img_pos', ['label' => 'Image position', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'center center',
                'options' => ['center center' => 'Center', 'top center' => 'Top', 'bottom center' => 'Bottom', 'center left' => 'Left', 'center right' => 'Right'],
                'selectors' => ['{{WRAPPER}} .icta-bg' => 'background-position:{{VALUE}}']]);
            $this->add_control('ov_color', ['label' => 'Overlay color (over the photo)', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .icta-card' => '--ov:{{VALUE}}'],
                'description' => 'A colour laid over the photo (for readable text). Leave blank for none; use the opacity below to control strength.']);
            $this->add_responsive_control('ov_opacity', ['label' => 'Overlay opacity', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['%' => ['min' => 0, 'max' => 100]],
                'default' => ['size' => 100, 'unit' => '%'], 'selectors' => ['{{WRAPPER}} .icta-card' => '--ovo:calc({{SIZE}}/100)']]);
            $this->add_responsive_control('content_w', ['label' => 'Text max width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['%' => ['min' => 30, 'max' => 100]],
                'default' => ['size' => 58, 'unit' => '%'], 'selectors' => ['{{WRAPPER}} .icta-card' => '--cw:{{SIZE}}%'],
                'description' => 'Keeps the badge/title/text on one side so long copy never runs over the photo. Lower = narrower text column. (The button width is set separately below.)']);
            $this->add_control('txt_side', ['label' => 'Text side', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'left',
                'options' => ['left' => 'Left', 'right' => 'Right'],
                'description' => 'Which side the text column sits on (put the photo’s subject on the opposite side).']);
            $this->add_control('btn_width', ['label' => 'Button width', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'full',
                'options' => ['full' => 'Full width', 'auto' => 'Fit to text'],
                'selectors_dictionary' => ['full' => 'align-self:stretch;text-align:center', 'auto' => 'align-self:flex-start;text-align:left'],
                'selectors' => ['{{WRAPPER}} .icta-btn' => '{{VALUE}}'],
                'description' => 'Full width spans the whole card; the text column stays constrained above.']);
            $this->end_controls_section();

            /* PER-CARD colors — override the shared colors above for card 1
             * (left) and card 2 (right) independently. Blank = use the shared
             * color. Cards beyond 2 keep the shared colors. */
            $this->start_controls_section('percard', ['label' => 'Per-card colors', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            foreach ([['c1', 'Card 1 (left)'], ['c2', 'Card 2 (right)']] as [$p, $lbl]) {
                $sel = '{{WRAPPER}} .icta-' . $p;
                $this->add_control($p . '_h', ['label' => $lbl, 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
                $this->add_control($p . '_bg', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel => 'background:{{VALUE}}']]);
                $this->add_control($p . '_title', ['label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel . ' .icta-t' => 'color:{{VALUE}}']]);
                $this->add_control($p . '_text', ['label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel . ' .icta-x' => 'color:{{VALUE}}', $sel . ' .icta-x p' => 'color:{{VALUE}}']]);
                $this->add_control($p . '_link', ['label' => 'Link color', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel . ' .icta-x a' => 'color:{{VALUE}}']]);
                $this->add_control($p . '_link_hover', ['label' => 'Link hover color', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel . ' .icta-x a:hover' => 'color:{{VALUE}}']]);
                $this->add_control($p . '_link_ul', ['label' => 'Underline links', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '',
                    'options' => ['' => 'Default', 'underline' => 'Always', 'none' => 'Never'],
                    'selectors' => [$sel . ' .icta-x a' => 'text-decoration:{{VALUE}}']]);
                $this->add_control($p . '_badge', ['label' => 'Badge color', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel . ' .icta-badge' => 'color:{{VALUE}};border-color:{{VALUE}}']]);
                $this->add_control($p . '_btn_bg', ['label' => 'Button background', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel . ' .icta-btn' => 'background:{{VALUE}}']]);
                $this->add_control($p . '_btn_color', ['label' => 'Button text', 'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [$sel . ' .icta-btn' => 'color:{{VALUE}}']]);
            }
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
            // Widget-level heading override (shared across islands) + show/hide.
            if (($s['content_source'] ?? 'auto') === 'auto' && !empty($s['title_text'])) {
                $title = $s['title_text'];
            }
            if (($s['show_title'] ?? 'yes') !== 'yes') {
                $title = '';
            }
            if (($s['show_intro'] ?? 'yes') !== 'yes') {
                $intro = '';
            }
            $htag = in_array($s['title_tag'] ?? 'h2', ['h2', 'h3', 'h4', 'div'], true) ? $s['title_tag'] : 'h2';
            if (!$title && !$intro && !$rows) {
                return;
            }
            echo '<style>
              {{WRAPPER}} .icta-head{margin:0 0 8px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:24px;color:#64402C}
              {{WRAPPER}} .icta-intro{margin:0 0 20px;font-size:15px;line-height:1.6;color:#4a3a2c;max-width:70ch}
              {{WRAPPER}} .icta-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
              {{WRAPPER}} .icta-card{position:relative;overflow:hidden;background:#64402C;border-radius:12px;padding:24px 26px;display:flex;flex-direction:column;box-shadow:0 8px 22px rgba(60,40,25,.14)}
              {{WRAPPER}} .icta-card>:not(.icta-bg):not(.icta-ov):not(.icta-btn){position:relative;z-index:1;max-width:var(--cw,100%)}
              {{WRAPPER}} .icta-card.txt-right>:not(.icta-bg):not(.icta-ov):not(.icta-btn){margin-left:auto;text-align:right}
              {{WRAPPER}} .icta-bg{position:absolute;inset:0;z-index:0;width:100%;height:100%;background:center/cover no-repeat}
              {{WRAPPER}} .icta-ov{position:absolute;inset:0;z-index:0;pointer-events:none;background:var(--ov,transparent);opacity:var(--ovo,1)}
              {{WRAPPER}} .icta-badge{align-self:flex-start;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#ECE5DE;border:1px solid rgba(236,229,222,.4);padding:3px 10px;border-radius:20px;margin-bottom:12px}
              {{WRAPPER}} .icta-t{margin:0 0 8px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:19px;color:#fff}
              {{WRAPPER}} .icta-x{font-size:14px;line-height:1.6;color:#f3e9df}{{WRAPPER}} .icta-x p{margin:0 0 10px}{{WRAPPER}} .icta-x :last-child{margin-bottom:0}
              {{WRAPPER}} .icta-btn{align-self:stretch;text-align:center;position:relative;z-index:1;margin-top:16px;font-size:13.5px;font-weight:700;text-decoration:none;background:#ECE5DE;color:#64402C;border-radius:8px;padding:11px 18px}
              @media(max-width:680px){{{WRAPPER}} .icta-grid{grid-template-columns:1fr!important}}
            </style>';
            if ($title) {
                echo '<' . $htag . ' class="icta-head">' . esc_html($title) . '</' . $htag . '>';
            }
            if ($intro) {
                echo '<div class="icta-intro">' . wp_kses_post($intro) . '</div>';
            }
            if ($rows) {
                echo '<div class="icta-grid">';
                $ci = 0;
                foreach ($rows as $r) {
                    $ci++;
                    // Automatic mode returns the image as an attachment ID; Manual
                    // already resolved it. Resolve both to a URL here.
                    $imgurl = island_ew_image_src($r['image'] ?? '');
                    $has_bg = $imgurl !== '';
                    $tside = ($s['txt_side'] ?? 'left') === 'right' ? ' txt-right' : '';
                    echo '<article class="icta-card icta-c' . $ci . ($has_bg ? ' has-bg' : '') . $tside . '">';
                    if ($has_bg) {
                        echo '<div class="icta-bg" style="background-image:url(\'' . esc_url($imgurl) . '\')"></div>';
                        echo '<div class="icta-ov"></div>';
                    }
                    if ($s['show_badge'] === 'yes' && !empty($r['audience'])) {
                        echo '<span class="icta-badge">' . esc_html($r['audience']) . '</span>';
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

            /* Visibility — condition the whole block, each sub-section, or hide
             * it on specific island pages. */
            $this->add_control('vis_h', ['label' => 'Show / hide', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('show_block', ['label' => 'Show this block', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Turn off to hide the whole Travel Information block here.']);
            $this->add_control('show_getting', ['label' => 'Getting There', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => ['show_block' => 'yes']]);
            $this->add_control('show_best', ['label' => 'When to Visit', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => ['show_block' => 'yes']]);
            $this->add_control('show_stay', ['label' => 'Where to Stay', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => ['show_block' => 'yes']]);
            $this->add_control('show_notes', ['label' => 'Good to Know', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => ['show_block' => 'yes']]);
            $this->add_control('hide_on_ids', ['label' => 'Hide on these page IDs', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true,
                'placeholder' => 'e.g. 12482, 1197',
                'description' => 'Comma-separated island page IDs where this whole block should NOT show.']);
            $this->add_control('hide_section_id', ['label' => 'Hide this section ID when empty', 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true,
                'placeholder' => 'e.g. travel-section',
                'description' => 'Optional. Put the SAME id on the wrapping section (Advanced → CSS ID). When this island has no travel info, only that one section is hidden — precise, no page-wide effect. Leave blank to just render nothing.']);

            /* Link the "When to Visit" block to the on-page Wildlife Calendar
             * (the seasonal table lives in that widget). */
            $this->add_control('wc_h', ['label' => 'Wildlife Calendar link', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('show_wc_link', ['label' => 'Add a button to the wildlife calendar', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Shown in "When to Visit" only when this island actually has a wildlife calendar.']);
            $this->add_control('wc_label', ['label' => 'Button label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'See the seasonal wildlife calendar',
                'condition' => ['show_wc_link' => 'yes']]);
            $this->add_control('wc_anchor', ['label' => 'Wildlife Calendar anchor ID', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'wildlife-calendar',
                'condition' => ['show_wc_link' => 'yes'],
                'description' => 'Set this same ID as the CSS ID / anchor on your Wildlife Calendar section (Elementor → Advanced → CSS ID).']);
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

            // Sub-headings that live INSIDE the text (e.g. "Logistics Note").
            $bh = '{{WRAPPER}} .itr-body h2,{{WRAPPER}} .itr-body h3,{{WRAPPER}} .itr-body h4,{{WRAPPER}} .itr-body h5,{{WRAPPER}} .itr-body h6';
            $this->add_control('bh_h', ['label' => 'Sub-headings inside text (e.g. “Logistics Note”)', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('bh_color', ['label' => 'Sub-heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => [$bh => 'color:{{VALUE}}']]);
            $this->add_responsive_control('bh_size', ['label' => 'Sub-heading size', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 12, 'max' => 40]],
                'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => [$bh => 'font-size:{{SIZE}}{{UNIT}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'bh_typo', 'selector' => $bh]);
            $this->add_responsive_control('bh_align', ['label' => 'Sub-heading alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => $align,
                'selectors' => [$bh => 'text-align:{{VALUE}}']]);

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
        private function block($title, $html, $blabel, $burl, $extra = '')
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
            return $out . $extra . '</div>';
        }
        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            // When empty, optionally hide one specific section by its CSS ID
            // (the user sets the same ID on the section → Advanced → CSS ID).
            // Precise: one element, no :has(), no ancestor guessing. Never in
            // the editor so the section stays editable.
            $hide = '';
            $hid = preg_replace('/[^A-Za-z0-9_-]/', '', ltrim((string) ($s['hide_section_id'] ?? ''), '#'));
            if ($hid !== '' && !island_ew_is_editing()) {
                $hide = '<style>#' . $hid . '{display:none!important}</style>';
            }
            if (($s['show_block'] ?? 'yes') !== 'yes') {
                echo $hide;
                return;
            }
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (int) get_the_ID();
            $hidden = array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($s['hide_on_ids'] ?? ''))));
            if (in_array((int) $pid, $hidden, true)) {
                echo $hide;
                return;
            }
            $t = get_field('travel_information', $pid);
            if (!$t || !is_array($t)) {
                echo $hide;
                return;
            }
            echo '<style>
              .itr{display:flex;flex-direction:column;gap:16px}
              .itr-block{background:#faf9f7;border:1px solid #DBCEC4;border-radius:11px;padding:22px 24px}
              .itr-h{margin:0 0 10px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:19px;color:#64402C}
              .itr-body{font-size:14.5px;line-height:1.65;color:#333}.itr-body p{margin:0 0 10px}.itr-body :last-child{margin-bottom:0}
              .itr-body h2,.itr-body h3,.itr-body h4,.itr-body h5,.itr-body h6{margin:14px 0 6px;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:700;font-size:16px;line-height:1.3;color:#64402C}
              .itr-btn{display:inline-block;margin:12px 10px 0 0;font-size:13px;font-weight:600;text-decoration:none;color:#64402C;border:1px solid #D3BAA3;border-radius:7px;padding:9px 16px}
              .itr-wc{border-color:#64402C}
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
            // A button into the on-page Wildlife Calendar, shown in When-to-Visit
            // only when this island actually has calendar data.
            $wc_link = '';
            if (($s['show_wc_link'] ?? 'yes') === 'yes' && get_field('wildlife_calendar', $pid)) {
                $anchor = ltrim(trim((string) ($s['wc_anchor'] ?? 'wildlife-calendar')), '#');
                if ($anchor !== '') {
                    $wc_link = '<a class="itr-btn itr-wc" href="#' . esc_attr($anchor) . '">'
                        . esc_html($s['wc_label'] ?: 'See the seasonal wildlife calendar') . ' &darr;</a>';
                }
            }
            $blocks = $meta
                . (($s['show_getting'] ?? 'yes') === 'yes' ? $this->block($t['getting_there_title'] ?? 'Getting There', $t['getting_there'] ?? '', $t['getting_there_button_label'] ?? '', $t['getting_there_button_url'] ?? '') : '')
                . (($s['show_best'] ?? 'yes') === 'yes' ? $this->block($t['stay_visit_title'] ?? 'When to Visit', $t['best_time'] ?? '', $t['best_time_button_label'] ?? '', $t['best_time_button_url'] ?? '', $wc_link) : '')
                . (($s['show_stay'] ?? 'yes') === 'yes' ? $this->block('Where to Stay', $t['accommodation'] ?? '', $t['accommodation_button_label'] ?? '', $t['accommodation_button_url'] ?? '') : '')
                . (($s['show_notes'] ?? 'yes') === 'yes' ? $this->block('Good to Know', $t['travel_notes'] ?? '', '', '') : '');
            if (trim($blocks) === '') {
                echo $hide;
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
                'options' => ['1' => '1', '2' => '2', '3' => '3'],
                'selectors' => ['{{WRAPPER}} .isrc-list,{{WRAPPER}} .isrc-groups' => 'grid-template-columns:repeat({{VALUE}},1fr)']]);
            $this->add_control('gh_color', ['label' => 'Group heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .isrc-gh' => 'color:{{VALUE}}']]);
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
        private function has_groups($rows)
        {
            foreach ((array) $rows as $r) {
                if (trim((string) ($r['group'] ?? '')) !== '') {
                    return true;
                }
            }
            return false;
        }
        private function grouped_links($rows, $heading)
        {
            // Bucket the links by their "group" heading, preserving first-seen order.
            $groups = [];
            $order = [];
            foreach ($rows as $r) {
                $g = trim((string) ($r['group'] ?? ''));
                if (!isset($groups[$g])) {
                    $groups[$g] = [];
                    $order[] = $g;
                }
                $groups[$g][] = $r;
            }
            echo '<div class="isrc-block">';
            if ($heading) {
                echo '<h3 class="isrc-h">' . esc_html($heading) . '</h3>';
            }
            echo '<div class="isrc-groups">';
            foreach ($order as $g) {
                echo '<div class="isrc-group">';
                if ($g !== '') {
                    echo '<h4 class="isrc-gh">' . esc_html($g) . '</h4>';
                }
                echo '<ul>';
                foreach ($groups[$g] as $r) {
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
            echo '</div></div>';
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
              {{WRAPPER}} .isrc-groups{display:grid;grid-template-columns:repeat(2,1fr);gap:26px 40px}
              {{WRAPPER}} .isrc-gh{margin:0 0 12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:20px;color:#64402C}
              {{WRAPPER}} .isrc-group ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
              {{WRAPPER}} .isrc-group li{font-size:14px;line-height:1.5;color:#5a4636;padding-left:16px;position:relative}
              {{WRAPPER}} .isrc-group li::before{content:"\2192";position:absolute;left:0;color:#9c7b4e}
              {{WRAPPER}} .isrc-group a{color:#3a5a8c;text-decoration:none}{{WRAPPER}} .isrc-group a:hover{text-decoration:underline}
              @media(max-width:680px){{{WRAPPER}} .isrc-list,{{WRAPPER}} .isrc-groups{grid-template-columns:1fr!important}}
            </style>';
            $this->links($sources, $s['sources_title']);
            if ($this->has_groups($related)) {
                $this->grouped_links($related, $s['related_title']);
            } else {
                $this->links($related, $s['related_title']);
            }
        }
    }

    /**
     * Related Links (SEO footer) — renders ONLY the ACF `related_links` repeater
     * as a grouped "Explore More" block (ornament + heading + intro, one titled
     * column per link "group", optional "Need Help?" contact card). It ignores
     * the `sources` field on purpose, even though both share the same ACF tab.
     * Defaults suit a dark (brown) footer; every color/size is a control.
     */
    class Island_RelatedLinks_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_related_links';
        }
        public function get_title()
        {
            return 'Island Related Links';
        }
        public function get_icon()
        {
            return 'eicon-editor-link';
        }
        public function get_categories()
        {
            return ['general'];
        }
        public function get_keywords()
        {
            return ['related', 'links', 'internal', 'seo', 'footer', 'explore'];
        }

        protected function register_controls()
        {
            /* ─── CONTENT ─── */
            $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('hide_on_ids', ['label' => 'Hide on these page IDs', 'type' => \Elementor\Controls_Manager::TEXT,
                'description' => 'Comma-separated. Leave blank to always show.']);
            $this->add_responsive_control('columns', ['label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'selectors' => ['{{WRAPPER}} .irl-cols' => 'grid-template-columns:repeat({{VALUE}},1fr)']]);
            $this->end_controls_section();

            /* ─── HEADER ─── */
            $this->start_controls_section('hd', ['label' => 'Header', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('show_ornament', ['label' => 'Show top ornament', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('ornament_icon', ['label' => 'Ornament icon', 'type' => \Elementor\Controls_Manager::ICONS,
                'default' => ['value' => 'far fa-bell', 'library' => 'fa-regular'], 'condition' => ['show_ornament' => 'yes']]);
            $this->add_control('show_title', ['label' => 'Show heading', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'separator' => 'before']);
            $this->add_control('related_title', ['label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Explore More',
                'condition' => ['show_title' => 'yes']]);
            $this->add_control('title_tag', ['label' => 'Heading tag', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h3',
                'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'], 'condition' => ['show_title' => 'yes']]);
            $this->add_control('show_intro', ['label' => 'Show intro line', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'separator' => 'before']);
            $this->add_control('intro_text', ['label' => 'Intro', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2,
                'default' => 'Discover more islands, plan your journey, and learn everything you need for the perfect Galápagos trip.',
                'condition' => ['show_intro' => 'yes']]);
            $this->end_controls_section();

            /* ─── CONTACT CARD ─── */
            $this->start_controls_section('ct', ['label' => 'Contact card', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('show_contact', ['label' => 'Show contact card', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Renders as the last column, after the link groups.']);
            $this->add_control('contact_icon', ['label' => 'Icon', 'type' => \Elementor\Controls_Manager::ICONS,
                'default' => ['value' => 'far fa-comments', 'library' => 'fa-regular'], 'condition' => ['show_contact' => 'yes']]);
            $this->add_control('contact_title', ['label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Need Help?',
                'condition' => ['show_contact' => 'yes']]);
            $this->add_control('contact_text', ['label' => 'Text', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2,
                'default' => 'Our Galapagos specialists are here to help you plan the perfect adventure.',
                'condition' => ['show_contact' => 'yes']]);
            $this->add_control('contact_btn_label', ['label' => 'Button label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Contact Us',
                'condition' => ['show_contact' => 'yes']]);
            $this->add_control('contact_form_shortcode', ['label' => 'Form shortcode (opens a modal)', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2,
                'placeholder' => '[contact_form_vue form="contact"]',
                'description' => 'Paste a form shortcode here and the button opens it in a built-in modal (no Elementor Pro needed). Highest priority.',
                'condition' => ['show_contact' => 'yes']]);
            $this->add_control('contact_modal_title', ['label' => 'Modal title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Contact Us',
                'condition' => ['show_contact' => 'yes', 'contact_form_shortcode!' => '']]);
            $this->add_control('popup_id', ['label' => 'Elementor Popup ID (modal)', 'type' => \Elementor\Controls_Manager::NUMBER,
                'description' => 'Alternative to the shortcode: open this Elementor popup. Used only when no shortcode is set.',
                'condition' => ['show_contact' => 'yes']]);
            $this->add_control('contact_btn_url', ['label' => 'Button URL (fallback)', 'type' => \Elementor\Controls_Manager::URL, 'default' => ['url' => '/contact/'],
                'description' => 'Used only when neither a shortcode nor a Popup ID is set.', 'condition' => ['show_contact' => 'yes']]);
            $this->end_controls_section();

            /* ─── STYLE: layout ─── */
            $this->start_controls_section('sl', ['label' => 'Layout & spacing', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('bg_color', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .irl-wrap' => 'background:{{VALUE}}']]);
            $this->add_responsive_control('pad', ['label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em', '%'],
                'default' => ['top' => '48', 'right' => '24', 'bottom' => '48', 'left' => '24', 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-wrap' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->add_control('max_w', ['label' => 'Content max width', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 600, 'max' => 1400]], 'default' => ['size' => 1120, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-inner' => 'max-width:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('col_gap', ['label' => 'Column gap', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 120]], 'default' => ['size' => 48, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-cols' => 'column-gap:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('row_gap', ['label' => 'Link row gap', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 30]], 'default' => ['size' => 10, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-group ul' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->end_controls_section();

            /* ─── STYLE: ornament & header ─── */
            $this->start_controls_section('sh', ['label' => 'Ornament & header', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('orn_color', ['label' => 'Ornament color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f3ead9',
                'selectors' => ['{{WRAPPER}} .irl-orn' => 'color:{{VALUE}}']]);
            $this->add_control('orn_size', ['label' => 'Ornament icon size', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 16, 'max' => 64]], 'default' => ['size' => 30, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-orn-i' => 'font-size:{{SIZE}}{{UNIT}}', '{{WRAPPER}} .irl-orn-i svg' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}']]);
            $this->add_control('orn_line', ['label' => 'Ornament line color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(243,234,217,.55)',
                'selectors' => ['{{WRAPPER}} .irl-line' => 'background:{{VALUE}}']]);
            $this->add_control('orn_line_thick', ['label' => 'Ornament line thickness', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 1, 'max' => 8]], 'default' => ['size' => 1, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-line' => 'height:{{SIZE}}{{UNIT}}']]);
            $this->add_control('orn_line_len', ['label' => 'Ornament line length', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 40, 'max' => 400]], 'default' => ['size' => 190, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-line' => 'max-width:{{SIZE}}{{UNIT}}']]);
            $this->add_control('h_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7efe1', 'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .irl-h' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'h_typo', 'selector' => '{{WRAPPER}} .irl-h']);
            $this->add_control('intro_color', ['label' => 'Intro color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#d9cebc', 'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .irl-intro' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'intro_typo', 'selector' => '{{WRAPPER}} .irl-intro']);
            $this->end_controls_section();

            /* ─── STYLE: groups & links ─── */
            $this->start_controls_section('sg', ['label' => 'Groups & links', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('gh_color', ['label' => 'Group heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7efe1',
                'selectors' => ['{{WRAPPER}} .irl-gh' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'gh_typo', 'selector' => '{{WRAPPER}} .irl-gh']);
            $this->add_control('accent_color', ['label' => 'Heading underline accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(201,169,126,.7)',
                'selectors' => ['{{WRAPPER}} .irl-gh::after' => 'background:{{VALUE}}']]);
            $this->add_control('accent_w', ['label' => 'Accent width', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 120]], 'default' => ['size' => 64, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-gh::after' => 'width:{{SIZE}}{{UNIT}}']]);
            $this->add_control('link_color', ['label' => 'Link color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ece2d1', 'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .irl-item a' => 'color:{{VALUE}}']]);
            $this->add_control('link_hover', ['label' => 'Link hover color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff',
                'selectors' => ['{{WRAPPER}} .irl-item a:hover' => 'color:{{VALUE}}']]);
            $this->add_control('marker_color', ['label' => 'Chevron color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#c9a97e',
                'selectors' => ['{{WRAPPER}} .irl-item::before' => 'color:{{VALUE}}']]);
            $this->add_control('show_marker', ['label' => 'Show chevron', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'selectors' => ['{{WRAPPER}} .irl-item::before' => 'content:"\203A"'], 'return_value' => 'yes']);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'link_typo', 'selector' => '{{WRAPPER}} .irl-item']);
            $this->add_control('link_underline', ['label' => 'Underline links', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'hover',
                'options' => ['none' => 'Never', 'hover' => 'On hover', 'always' => 'Always']]);
            $this->add_control('nofollow', ['label' => 'rel="nofollow"', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '', 'separator' => 'before',
                'description' => 'Internal SEO links should usually NOT be nofollow.']);
            $this->add_control('new_tab', ['label' => 'Open in new tab', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '']);
            $this->end_controls_section();

            /* ─── STYLE: contact card ─── */
            $this->start_controls_section('sc', ['label' => 'Contact card style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_contact' => 'yes']]);
            $this->add_control('ct_icon_color', ['label' => 'Icon color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f3ead9',
                'selectors' => ['{{WRAPPER}} .irl-ct-i' => 'color:{{VALUE}}']]);
            $this->add_control('ct_title_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7efe1',
                'selectors' => ['{{WRAPPER}} .irl-ct-h' => 'color:{{VALUE}}']]);
            $this->add_control('ct_text_color', ['label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#d9cebc',
                'selectors' => ['{{WRAPPER}} .irl-ct-t' => 'color:{{VALUE}}']]);
            $this->add_control('ct_accent_color', ['label' => 'Heading underline accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(201,169,126,.7)',
                'selectors' => ['{{WRAPPER}} .irl-ct-head::after' => 'background:{{VALUE}}']]);
            $this->add_control('ct_accent_w', ['label' => 'Accent width', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 160]], 'default' => ['size' => 64, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-ct-head::after' => 'width:{{SIZE}}{{UNIT}}']]);
            $this->add_control('ct_accent_h', ['label' => 'Accent thickness', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 1, 'max' => 8]], 'default' => ['size' => 2, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-ct-head::after' => 'height:{{SIZE}}{{UNIT}}']]);
            $this->add_control('ct_btn_color', ['label' => 'Button text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7efe1',
                'selectors' => ['{{WRAPPER}} .irl-ct-btn' => 'color:{{VALUE}}']]);
            $this->add_control('ct_btn_border', ['label' => 'Button border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(201,169,126,.9)',
                'selectors' => ['{{WRAPPER}} .irl-ct-btn' => 'border-color:{{VALUE}}']]);
            $this->add_control('ct_btn_bw', ['label' => 'Button border width', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 4]], 'default' => ['size' => 1.5, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-ct-btn' => 'border-width:{{SIZE}}{{UNIT}}']]);
            $this->add_control('ct_btn_radius', ['label' => 'Button radius', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 40]], 'default' => ['size' => 4, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .irl-ct-btn' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('ct_btn_hover', ['label' => 'Button hover text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3a2a1e',
                'selectors' => ['{{WRAPPER}} .irl-ct-btn:hover' => 'color:{{VALUE}}']]);
            $this->add_control('ct_btn_hover_bg', ['label' => 'Button hover background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#c9a97e',
                'selectors' => ['{{WRAPPER}} .irl-ct-btn:hover' => 'background:{{VALUE}};border-color:{{VALUE}}']]);
            $this->end_controls_section();
        }

        private function has_groups($rows)
        {
            foreach ((array) $rows as $r) {
                if (trim((string) ($r['group'] ?? '')) !== '') {
                    return true;
                }
            }
            return false;
        }

        private function anchor($r, $s)
        {
            $label = trim((string) ($r['label'] ?? ''));
            $url = trim((string) ($r['url'] ?? ''));
            if ($label === '' && $url === '') {
                return '';
            }
            if ($url === '') {
                return '<li class="irl-item">' . esc_html($label) . '</li>';
            }
            $rel = ['noopener'];
            if (($s['nofollow'] ?? '') === 'yes') {
                $rel[] = 'nofollow';
            }
            $target = (($s['new_tab'] ?? '') === 'yes') ? ' target="_blank"' : '';
            return '<li class="irl-item"><a href="' . esc_url($url) . '" rel="' . esc_attr(implode(' ', $rel)) . '"' . $target . '>'
                . esc_html($label ?: $url) . '</a></li>';
        }

        private function columns($rows, $s)
        {
            if ($this->has_groups($rows)) {
                $groups = [];
                $order = [];
                foreach ($rows as $r) {
                    $g = trim((string) ($r['group'] ?? ''));
                    if (!isset($groups[$g])) {
                        $groups[$g] = [];
                        $order[] = $g;
                    }
                    $groups[$g][] = $r;
                }
                foreach ($order as $g) {
                    echo '<div class="irl-group">';
                    if ($g !== '') {
                        echo '<h4 class="irl-gh">' . esc_html($g) . '</h4>';
                    }
                    echo '<ul>';
                    foreach ($groups[$g] as $r) {
                        echo $this->anchor($r, $s);
                    }
                    echo '</ul></div>';
                }
            } else {
                echo '<div class="irl-group"><ul>';
                foreach ($rows as $r) {
                    echo $this->anchor($r, $s);
                }
                echo '</ul></div>';
            }
        }

        private function contact_card($s)
        {
            if (($s['show_contact'] ?? '') !== 'yes') {
                return;
            }
            $icon = '';
            if (!empty($s['contact_icon']['value'])) {
                ob_start();
                \Elementor\Icons_Manager::render_icon($s['contact_icon'], ['aria-hidden' => 'true']);
                $icon = ob_get_clean();
            }
            echo '<div class="irl-group irl-contact">';

            // Icon + heading share one row (icon left, heading right).
            $title = trim((string) ($s['contact_title'] ?? ''));
            if ($icon || $title !== '') {
                echo '<div class="irl-ct-head">';
                if ($icon) {
                    echo '<span class="irl-ct-i">' . $icon . '</span>';
                }
                if ($title !== '') {
                    echo '<h4 class="irl-ct-h">' . esc_html($title) . '</h4>';
                }
                echo '</div>';
            }
            if (trim((string) ($s['contact_text'] ?? '')) !== '') {
                echo '<p class="irl-ct-t">' . esc_html($s['contact_text']) . '</p>';
            }

            // Button. Priority: form shortcode (built-in modal) > Elementor popup > URL.
            $label = trim((string) ($s['contact_btn_label'] ?? ''));
            if ($label !== '') {
                $shortcode = trim((string) ($s['contact_form_shortcode'] ?? ''));
                $popup = trim((string) ($s['popup_id'] ?? ''));
                if ($shortcode !== '') {
                    $mid = 'irl-modal-' . $this->get_id();
                    echo '<button type="button" class="irl-ct-btn irl-open" data-irl-open="' . esc_attr($mid) . '">'
                        . esc_html($label) . ' <span class="irl-ct-arw">&rarr;</span></button>';
                    $mtitle = trim((string) ($s['contact_modal_title'] ?? ''));
                    echo '<div class="irl-modal" id="' . esc_attr($mid) . '" hidden>'
                        . '<div class="irl-modal-ov" data-irl-close="' . esc_attr($mid) . '"></div>'
                        . '<div class="irl-modal-box" role="dialog" aria-modal="true">'
                        . '<button type="button" class="irl-modal-x" data-irl-close="' . esc_attr($mid) . '" aria-label="Close">&times;</button>';
                    if ($mtitle !== '') {
                        echo '<h4 class="irl-modal-h">' . esc_html($mtitle) . '</h4>';
                    }
                    echo '<div class="irl-modal-body">' . do_shortcode($shortcode) . '</div>'
                        . '</div></div>';
                    $this->modal_script();
                } elseif ($popup !== '') {
                    // Elementor Pro popup trigger: #elementor-action ... settings is
                    // base64 of {"id":"<popup>","toggle":false}.
                    $settings = base64_encode(wp_json_encode(['id' => $popup, 'toggle' => false]));
                    $href = '#elementor-action:action=popup:open&settings=' . $settings;
                    echo '<a class="irl-ct-btn" href="' . esc_attr($href) . '">'
                        . esc_html($label) . ' <span class="irl-ct-arw">&rarr;</span></a>';
                } else {
                    $link = $s['contact_btn_url'] ?? [];
                    $href = trim((string) ($link['url'] ?? ''));
                    if ($href !== '') {
                        $target = !empty($link['is_external']) ? ' target="_blank"' : '';
                        $nofollow = !empty($link['nofollow']) ? ' rel="nofollow noopener"' : '';
                        echo '<a class="irl-ct-btn" href="' . esc_url($href) . '"' . $target . $nofollow . '>'
                            . esc_html($label) . ' <span class="irl-ct-arw">&rarr;</span></a>';
                    }
                }
            }
            echo '</div>';
        }

        /** Print the tiny open/close modal script once per request. */
        private function modal_script()
        {
            static $done = false;
            if ($done) {
                return;
            }
            $done = true;
            echo '<script>(function(){if(window.__irlModal)return;window.__irlModal=1;'
                . 'function set(m,open){if(!m)return;m.hidden=!open;document.body.style.overflow=open?"hidden":"";}'
                . 'document.addEventListener("click",function(e){'
                . 'var o=e.target.closest("[data-irl-open]");if(o){e.preventDefault();set(document.getElementById(o.getAttribute("data-irl-open")),true);return;}'
                . 'var c=e.target.closest("[data-irl-close]");if(c){e.preventDefault();set(document.getElementById(c.getAttribute("data-irl-close")),false);}'
                . '});'
                . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"){document.querySelectorAll(".irl-modal:not([hidden])").forEach(function(m){set(m,false);});}});'
                . '})();</script>';
        }

        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (int) get_the_ID();

            $hide_ids = array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) ($s['hide_on_ids'] ?? ''))));
            if ($pid && in_array($pid, $hide_ids, true)) {
                return;
            }

            $related = get_field('related_links', $pid) ?: [];
            $related = array_values(array_filter((array) $related, function ($r) {
                return trim((string) ($r['label'] ?? '')) !== '' || trim((string) ($r['url'] ?? '')) !== '';
            }));
            $has_contact = (($s['show_contact'] ?? '') === 'yes');
            if (!$related && !$has_contact) {
                return;
            }

            $ul = $s['link_underline'] ?? 'hover';
            $ul_base = ($ul === 'always') ? 'underline' : 'none';
            $ul_hover = ($ul === 'none') ? 'none' : 'underline';

            echo '<style>
              {{WRAPPER}} .irl-wrap{width:100%}
              {{WRAPPER}} .irl-inner{margin:0 auto}
              {{WRAPPER}} .irl-head{text-align:center}
              {{WRAPPER}} .irl-orn{display:flex;align-items:center;justify-content:center;gap:26px;color:#f3ead9;margin-bottom:14px}
              {{WRAPPER}} .irl-line{height:1px;flex:1;max-width:190px;background:rgba(243,234,217,.55)}
              {{WRAPPER}} .irl-orn-i{display:inline-flex;line-height:1;font-size:30px}
              {{WRAPPER}} .irl-orn-i svg{width:30px;height:30px;fill:currentColor}
              {{WRAPPER}} .irl-h{margin:0 0 12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:30px;font-weight:400;color:#f7efe1}
              {{WRAPPER}} .irl-intro{margin:0 auto 40px;max-width:760px;font-size:16px;line-height:1.6;color:#d9cebc}
              {{WRAPPER}} .irl-cols{display:grid;grid-template-columns:repeat(3,1fr);gap:34px 48px;align-items:start}
              {{WRAPPER}} .irl-gh{position:relative;margin:0 0 22px;padding-bottom:12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:400;font-size:22px;color:#f7efe1}
              {{WRAPPER}} .irl-gh::after{content:"";position:absolute;left:0;bottom:0;width:64px;height:2px;background:rgba(201,169,126,.7)}
              {{WRAPPER}} .irl-group ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
              {{WRAPPER}} .irl-item{position:relative;padding-left:22px;font-size:15px;line-height:1.5}
              {{WRAPPER}} .irl-item::before{content:"\203A";position:absolute;left:2px;top:-1px;color:#c9a97e;font-size:16px}
              {{WRAPPER}} .irl-item a{color:#ece2d1;text-decoration:' . $ul_base . ';transition:color .18s ease}
              {{WRAPPER}} .irl-item a:hover{color:#fff;text-decoration:' . $ul_hover . '}
              {{WRAPPER}} .irl-ct-head{display:flex;align-items:center;gap:14px;position:relative;padding-bottom:14px;margin-bottom:18px}
              {{WRAPPER}} .irl-ct-head::after{content:"";position:absolute;left:0;bottom:0;width:64px;height:2px;background:rgba(201,169,126,.7)}
              {{WRAPPER}} .irl-contact .irl-ct-i{display:inline-flex;line-height:1;font-size:32px;color:#f3ead9}
              {{WRAPPER}} .irl-contact .irl-ct-i svg{width:32px;height:32px;fill:currentColor}
              {{WRAPPER}} .irl-ct-h{margin:0;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:400;font-size:22px;color:#f7efe1}
              {{WRAPPER}} .irl-ct-t{margin:0 0 20px;font-size:15px;line-height:1.6;color:#d9cebc}
              {{WRAPPER}} .irl-ct-btn{display:inline-flex;align-items:center;gap:10px;padding:11px 24px;border:1.5px solid rgba(201,169,126,.9);border-radius:4px;font-weight:600;font-size:15px;font-family:inherit;line-height:1.2;color:#f7efe1;background:transparent;cursor:pointer;text-decoration:none;transition:background .2s ease,color .2s ease,border-color .2s ease}
              {{WRAPPER}} .irl-ct-btn:hover{background:#c9a97e;border-color:#c9a97e;color:#3a2a1e}
              {{WRAPPER}} .irl-ct-btn .irl-ct-arw{transition:transform .2s ease}
              {{WRAPPER}} .irl-ct-btn:hover .irl-ct-arw{transform:translateX(3px)}
              {{WRAPPER}} .irl-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px}
              {{WRAPPER}} .irl-modal[hidden]{display:none}
              {{WRAPPER}} .irl-modal-ov{position:absolute;inset:0;background:rgba(0,0,0,.55)}
              {{WRAPPER}} .irl-modal-box{position:relative;width:100%;max-width:540px;max-height:88vh;overflow:auto;background:#fff;color:#3a2a1e;border-radius:8px;padding:34px 30px;box-shadow:0 20px 60px rgba(0,0,0,.35)}
              {{WRAPPER}} .irl-modal-h{margin:0 0 16px;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:400;font-size:24px;color:#3a2a1e}
              {{WRAPPER}} .irl-modal-x{position:absolute;top:8px;right:14px;width:auto;padding:4px;border:0;background:none;font-size:28px;line-height:1;color:#8a7a6a;cursor:pointer}
              {{WRAPPER}} .irl-modal-x:hover{color:#3a2a1e}
              @media(max-width:900px){{{WRAPPER}} .irl-cols{grid-template-columns:1fr 1fr}}
              @media(max-width:600px){{{WRAPPER}} .irl-cols{grid-template-columns:1fr!important}{{WRAPPER}} .irl-line{max-width:90px}}
            </style>';

            echo '<div class="irl-wrap"><div class="irl-inner">';

            $has_orn = (($s['show_ornament'] ?? '') === 'yes') && !empty($s['ornament_icon']['value']);
            $has_title = (($s['show_title'] ?? '') === 'yes') && trim((string) ($s['related_title'] ?? '')) !== '';
            $has_intro = (($s['show_intro'] ?? '') === 'yes') && trim((string) ($s['intro_text'] ?? '')) !== '';
            if ($has_orn || $has_title || $has_intro) {
                echo '<div class="irl-head">';
                if ($has_orn) {
                    ob_start();
                    \Elementor\Icons_Manager::render_icon($s['ornament_icon'], ['aria-hidden' => 'true']);
                    $orn = ob_get_clean();
                    echo '<div class="irl-orn"><span class="irl-line"></span><span class="irl-orn-i">' . $orn . '</span><span class="irl-line"></span></div>';
                }
                if ($has_title) {
                    $tag = in_array($s['title_tag'] ?? 'h3', ['h2', 'h3', 'h4', 'div'], true) ? $s['title_tag'] : 'h3';
                    echo '<' . $tag . ' class="irl-h">' . esc_html($s['related_title']) . '</' . $tag . '>';
                }
                if ($has_intro) {
                    echo '<p class="irl-intro">' . esc_html($s['intro_text']) . '</p>';
                }
                echo '</div>';
            }

            echo '<div class="irl-cols">';
            if ($related) {
                $this->columns($related, $s);
            }
            $this->contact_card($s);
            echo '</div>';

            echo '</div></div>';
        }
    }

    /**
     * Island Schema (JSON-LD) — prints the page-specific schema.org markup stored
     * in the ACF `seo_schema` field as a <script type="application/ld+json"> tag.
     * Drop it ONCE in the Theme Builder template: every page then outputs its own
     * schema (not a shared/general one). A manual override box is also provided.
     */
    class Island_Schema_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_schema';
        }
        public function get_title()
        {
            return 'Island Schema (JSON-LD)';
        }
        public function get_icon()
        {
            return 'eicon-code';
        }
        public function get_categories()
        {
            return ['general'];
        }
        public function get_keywords()
        {
            return ['schema', 'json-ld', 'jsonld', 'structured', 'seo', 'rich', 'ld+json'];
        }

        protected function register_controls()
        {
            $this->start_controls_section('c', ['label' => 'Schema', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('field_name', ['label' => 'ACF field name', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'seo_schema',
                'description' => 'The ACF field holding this page\'s JSON-LD. Default: seo_schema.']);
            $this->add_control('manual', ['label' => 'Manual override', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 8,
                'description' => 'Optional. Paste JSON-LD (or a full <script> block) here to override the ACF field for this placement.']);
            $this->add_control('note', ['type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => 'Renders an invisible &lt;script type="application/ld+json"&gt; tag. Place ONCE in the island template; each page prints its own <code>seo_schema</code>.',
                'content_classes' => 'elementor-descriptor']);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $raw = trim((string) ($s['manual'] ?? ''));
            if ($raw === '') {
                // Inside a Theme Builder template on the front end, get_the_ID()
                // can be 0 (outside the loop); fall back to the queried object so
                // the right page's seo_schema is read.
                $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (int) get_the_ID();
                if (!$pid) {
                    $pid = (int) get_queried_object_id();
                }
                $field = trim((string) ($s['field_name'] ?? 'seo_schema')) ?: 'seo_schema';
                $raw = trim((string) (get_field($field, $pid) ?: ''));
            }

            if ($raw === '') {
                if (island_ew_is_editing()) {
                    echo '<div style="padding:10px 14px;border:1px dashed #b9a48c;border-radius:6px;color:#8a7a6a;font:13px/1.4 sans-serif">Island Schema: no <code>seo_schema</code> set for this page yet — nothing will render on the front end.</div>';
                }
                return;
            }

            // Accept either raw JSON ({…}/[…]) or an already-wrapped <script> block.
            if ($raw[0] === '<') {
                $out = $raw;                       // already contains <script>…</script>
            } else {
                $out = '<script type="application/ld+json">' . $raw . '</script>';
            }

            if (island_ew_is_editing()) {
                echo '<div style="padding:10px 14px;border:1px dashed #7fae7f;border-radius:6px;color:#4d774d;font:13px/1.4 sans-serif">✓ Island Schema active — JSON-LD will print here on the front end.</div>';
            }
            echo $out;
        }
    }

    /**
     * Island Infographic — a standalone widget: drop it anywhere, upload the
     * image right in Elementor, add a caption. Clean rounded image by default
     * (no label / frame); background, border, shadow are optional.
     */
    class Island_Infographic_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'island_infographic';
        }
        public function get_title()
        {
            return 'Island Infographic';
        }
        public function get_icon()
        {
            return 'eicon-image';
        }
        public function get_categories()
        {
            return ['general'];
        }
        public function get_keywords()
        {
            return ['infographic', 'image', 'illustration', 'diagram', 'map'];
        }

        protected function register_controls()
        {
            $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('image', ['label' => 'Infographic image', 'type' => \Elementor\Controls_Manager::MEDIA,
                'default' => ['url' => \Elementor\Utils::get_placeholder_image_src()]]);
            $this->add_control('caption', ['label' => 'Caption', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2]);
            $this->add_control('link', ['label' => 'Link (optional)', 'type' => \Elementor\Controls_Manager::URL,
                'description' => 'Make the infographic clickable (e.g. open the full-size image).']);
            $this->add_control('alt', ['label' => 'Alt text (optional)', 'type' => \Elementor\Controls_Manager::TEXT,
                'description' => 'Falls back to the caption when blank.']);
            $this->add_control('show_label', ['label' => 'Show "Infographic" label', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '', 'separator' => 'before']);
            $this->add_control('label_text', ['label' => 'Label text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Infographic',
                'condition' => ['show_label' => 'yes']]);
            $this->end_controls_section();

            $this->start_controls_section('s', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('align', ['label' => 'Alignment', 'type' => \Elementor\Controls_Manager::CHOOSE, 'default' => 'center',
                'options' => [
                    'left' => ['title' => 'Left', 'icon' => 'eicon-text-align-left'],
                    'center' => ['title' => 'Center', 'icon' => 'eicon-text-align-center'],
                    'right' => ['title' => 'Right', 'icon' => 'eicon-text-align-right'],
                ],
                'selectors_dictionary' => ['left' => 'margin:0 auto 0 0', 'center' => 'margin:0 auto', 'right' => 'margin:0 0 0 auto'],
                'selectors' => ['{{WRAPPER}} .iig' => '{{VALUE}}']]);
            $this->add_responsive_control('maxw', ['label' => 'Max width', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['%', 'px'],
                'range' => ['%' => ['min' => 30, 'max' => 100], 'px' => ['min' => 300, 'max' => 1400]], 'default' => ['size' => 100, 'unit' => '%'],
                'selectors' => ['{{WRAPPER}} .iig' => 'max-width:{{SIZE}}{{UNIT}}']]);
            $this->add_control('radius', ['label' => 'Image radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 20, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .iig-frame' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('bg', ['label' => 'Background (optional)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '',
                'selectors' => ['{{WRAPPER}} .iig-frame' => 'background:{{VALUE}}']]);
            $this->add_control('pad', ['label' => 'Inner padding', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 48]],
                'default' => ['size' => 0, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .iig-frame' => 'padding:{{SIZE}}{{UNIT}}'],
                'description' => 'Only useful when a background is set.']);
            $this->add_control('border', ['label' => 'Border (optional)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '',
                'selectors' => ['{{WRAPPER}} .iig-frame' => 'border:1px solid {{VALUE}}']]);
            $this->add_control('shadow', ['label' => 'Shadow (optional)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes',
                'selectors' => ['{{WRAPPER}} .iig-frame' => 'box-shadow:0 14px 34px rgba(80,55,35,.12)']]);
            $this->add_control('label_color', ['label' => 'Label color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#b08a55', 'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .iig-eyebrow' => 'color:{{VALUE}}'], 'condition' => ['show_label' => 'yes']]);
            $this->add_control('cap_color', ['label' => 'Caption color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#7a6a5c',
                'selectors' => ['{{WRAPPER}} .iig-cap' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'cap_typo', 'selector' => '{{WRAPPER}} .iig-cap']);
            $this->add_control('cap_align', ['label' => 'Caption align', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'center',
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                'selectors' => ['{{WRAPPER}} .iig-cap' => 'text-align:{{VALUE}}']]);
            $this->end_controls_section();
        }

        protected function render()
        {
            $s = $this->get_settings_for_display();
            $url = $s['image']['url'] ?? '';
            if (!$url) {
                if (island_ew_is_editing()) {
                    echo '<div style="padding:10px 14px;border:1px dashed #b9a48c;border-radius:6px;color:#8a7a6a;font:13px/1.4 sans-serif">Island Infographic: choose an image.</div>';
                }
                return;
            }
            $cap = trim((string) ($s['caption'] ?? ''));
            $alt = trim((string) ($s['alt'] ?? '')) ?: $cap;

            echo '<style>
              {{WRAPPER}} .iig{width:100%}
              {{WRAPPER}} .iig-eyebrow{display:inline-flex;align-items:center;gap:10px;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#b08a55;font-weight:700;margin-bottom:14px}
              {{WRAPPER}} .iig-eyebrow::before,{{WRAPPER}} .iig-eyebrow::after{content:"";width:34px;height:1px;background:currentColor;opacity:.55}
              {{WRAPPER}} .iig-frame{border-radius:20px;overflow:hidden;line-height:0}
              {{WRAPPER}} .iig-frame img{width:100%;height:auto;display:block}
              {{WRAPPER}} .iig-cap{margin:14px 0 0;font-size:13.5px;line-height:1.6;color:#7a6a5c;text-align:center}
            </style>';

            $img = '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy">';
            $link = $s['link'] ?? [];
            $href = trim((string) ($link['url'] ?? ''));
            if ($href !== '') {
                $target = !empty($link['is_external']) ? ' target="_blank"' : '';
                $rel = !empty($link['nofollow']) ? ' rel="nofollow noopener"' : '';
                $img = '<a href="' . esc_url($href) . '"' . $target . $rel . '>' . $img . '</a>';
            }

            echo '<figure class="iig">';
            if (($s['show_label'] ?? '') === 'yes' && trim((string) ($s['label_text'] ?? '')) !== '') {
                echo '<span class="iig-eyebrow">' . esc_html($s['label_text']) . '</span>';
            }
            echo '<div class="iig-frame">' . $img . '</div>';
            if ($cap !== '') {
                echo '<figcaption class="iig-cap">' . esc_html($cap) . '</figcaption>';
            }
            echo '</figure>';
        }
    }

    } // end: declare widget classes once

    // Declared in its OWN guard (independent of the shared guard above),
    // so it is registered even if another copy of the plugin already
    // declared the other classes and skipped the shared block.
    if (!class_exists('Island_AtAGlance_Widget')) {

    /* ===================================================================
     *  WILDLIFE · AT A GLANCE — a soft card with the IUCN status badge
     *  (auto-coloured) plus a hairline label/value list. Reads ONLY the
     *  species-facts fields: scientific_name, conservation_status,
     *  population, endemic, and the quick_facts repeater.
     * =================================================================== */
    class Island_AtAGlance_Widget extends \Elementor\Widget_Base
    {
        public function get_name()
        {
            return 'wildlife_at_a_glance';
        }

        public function get_title()
        {
            return 'Wildlife · At a Glance';
        }

        public function get_icon()
        {
            return 'eicon-table-of-contents';
        }

        public function get_categories()
        {
            return ['general'];
        }

        protected function register_controls()
        {
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('heading', ['label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'At a Glance']);
            $this->add_control('show_badge', ['label' => 'IUCN status badge', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_scientific', ['label' => 'Scientific name row', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_population', ['label' => 'Population row', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('show_endemic', ['label' => 'Endemic row', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('endemic_text', ['label' => 'Endemic "yes" text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Yes — endemic to Galápagos', 'condition' => ['show_endemic' => 'yes']]);
            $this->add_control('include_quick_facts', [
                'label' => 'Append "At a Glance" facts', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Adds the quick_facts rows (Lifespan, Weight…), skipping any already shown above (scientific name, population, IUCN).',
            ]);
            $this->end_controls_section();

            /* CARD */
            $this->start_controls_section('card', ['label' => 'Card', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('card_bg', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FCF9F5',
                'selectors' => ['{{WRAPPER}} .wag-card' => 'background:{{VALUE}}']]);
            $this->add_control('card_radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 36]], 'default' => ['size' => 18, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .wag-card' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('border_color', ['label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(90,61,43,.16)',
                'selectors' => ['{{WRAPPER}} .wag-card' => 'border-color:{{VALUE}}']]);
            $this->add_control('border_width', ['label' => 'Border width', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 0, 'max' => 6, 'step' => 0.5]], 'default' => ['size' => 1, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .wag-card' => 'border-width:{{SIZE}}{{UNIT}};border-style:solid']]);
            $this->add_control('shadow', ['label' => 'Soft shadow', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'return_value' => 'yes', 'selectors' => ['{{WRAPPER}} .wag-card' => 'box-shadow:0 16px 44px rgba(60,40,25,.14)']]);
            $this->add_responsive_control('pad', ['label' => 'Padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 24, 'right' => 26, 'bottom' => 24, 'left' => 26, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .wag-card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->end_controls_section();

            /* BADGE */
            $this->start_controls_section('badge_s', ['label' => 'IUCN badge', 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => ['show_badge' => 'yes']]);
            $this->add_control('badge_auto', ['label' => 'Colour by IUCN status', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Automatic: green (Least Concern) → red (Critically Endangered).']);
            $this->add_control('badge_bg', ['label' => 'Badge background', 'type' => \Elementor\Controls_Manager::COLOR, 'condition' => ['badge_auto' => ''],
                'selectors' => ['{{WRAPPER}} .wag-badge' => 'background:{{VALUE}};border-color:{{VALUE}}']]);
            $this->add_control('badge_fg', ['label' => 'Badge text', 'type' => \Elementor\Controls_Manager::COLOR, 'condition' => ['badge_auto' => ''],
                'selectors' => ['{{WRAPPER}} .wag-badge' => 'color:{{VALUE}}', '{{WRAPPER}} .wag-dot' => 'background:{{VALUE}}']]);
            $this->end_controls_section();

            /* TEXT */
            $this->start_controls_section('text', ['label' => 'Text', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('eyebrow_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#a07a44',
                'selectors' => ['{{WRAPPER}} .wag-eyebrow' => 'color:{{VALUE}}']]);
            $this->add_control('label_color', ['label' => 'Label color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#8a7360',
                'selectors' => ['{{WRAPPER}} .wag-l' => 'color:{{VALUE}}']]);
            $this->add_control('value_color', ['label' => 'Value color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5A3D2B',
                'selectors' => ['{{WRAPPER}} .wag-v' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'value_typo', 'selector' => '{{WRAPPER}} .wag-v']);
            $this->add_control('divider_color', ['label' => 'Divider color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(90,61,43,.13)',
                'selectors' => ['{{WRAPPER}} .wag-row' => 'border-color:{{VALUE}}']]);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) {
                return;
            }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (get_the_ID() ?: get_queried_object_id());

            $sci = trim((string) get_field('scientific_name', $pid));
            $pop = trim((string) get_field('population', $pid));
            $status = trim((string) get_field('conservation_status', $pid));
            $endemic = (bool) get_field('endemic', $pid);
            $facts = get_field('quick_facts', $pid) ?: [];

            // Rows: dedicated species facts first, then the quick_facts repeater
            // (de-duped against what is already shown). Values are pre-escaped.
            $rows = [];
            if (($s['show_scientific'] ?? 'yes') === 'yes' && $sci !== '') {
                $rows[] = ['Scientific name', '<span class="wag-sci">' . esc_html($sci) . '</span>'];
            }
            if (($s['show_population'] ?? 'yes') === 'yes' && $pop !== '') {
                $rows[] = ['Population', esc_html($pop)];
            }
            if (($s['show_endemic'] ?? 'yes') === 'yes' && $endemic) {
                $rows[] = ['Endemic', esc_html(($s['endemic_text'] ?? '') ?: 'Yes — endemic to Galápagos')];
            }
            if (($s['include_quick_facts'] ?? 'yes') === 'yes' && is_array($facts)) {
                $skip = ['scientific name', 'common name', 'iucn status', 'iucn', 'conservation status', 'status', 'population', 'population estimate', 'endemic'];
                foreach ($facts as $f) {
                    $lab = trim((string) ($f['label'] ?? ''));
                    $val = trim((string) ($f['value'] ?? ''));
                    if ($lab === '' || $val === '' || in_array(strtolower($lab), $skip, true)) {
                        continue;
                    }
                    $rows[] = [$lab, esc_html($val)];
                }
            }

            $hasBadge = ($s['show_badge'] ?? 'yes') === 'yes' && $status !== '';
            if (!$rows && !$hasBadge) {
                return;
            }

            // Auto badge colour by IUCN category (background, text/dot).
            $map = [
                'least concern' => ['#e4ede0', '#3f6a2f'],
                'near threatened' => ['#eef0d6', '#6a7a1c'],
                'vulnerable' => ['#f4e6cf', '#9a6a1c'],
                'endangered' => ['#f6ddc9', '#b5591f'],
                'critically endangered' => ['#f0dcd8', '#9a3b2e'],
                'data deficient' => ['#e6e0da', '#6a5e52'],
            ];
            $auto = ($s['badge_auto'] ?? 'yes') === 'yes';
            $bc = $map[strtolower($status)] ?? ['#f4e6cf', '#9a6a1c'];
            $badgeStyle = $auto ? 'background:' . $bc[0] . ';color:' . $bc[1] . ';border-color:' . $bc[1] . '40' : '';
            $dotStyle = $auto ? 'background:' . $bc[1] : '';

            echo '<style>
              {{WRAPPER}} .wag-card{background:#FCF9F5;border:1px solid rgba(90,61,43,.16);border-radius:18px;padding:24px 26px}
              {{WRAPPER}} .wag-eyebrow{display:block;text-transform:uppercase;letter-spacing:.2em;font-size:11px;font-weight:700;color:#a07a44;margin:0 0 14px}
              {{WRAPPER}} .wag-badge{display:inline-flex;align-items:center;gap:7px;font-weight:700;font-size:12px;padding:6px 13px;border-radius:999px;border:1px solid transparent;background:#f4e6cf;color:#9a6a1c}
              {{WRAPPER}} .wag-dot{width:8px;height:8px;border-radius:50%;background:#9a6a1c;flex:0 0 auto}
              {{WRAPPER}} .wag-list{margin:12px 0 0;padding:0;display:flex;flex-direction:column}
              {{WRAPPER}} .wag-row{padding:13px 0;border-bottom:1px solid rgba(90,61,43,.13)}
              {{WRAPPER}} .wag-row:last-child{border-bottom:0;padding-bottom:2px}
              {{WRAPPER}} .wag-l{margin:0 0 2px;font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;color:#8a7360;font-weight:700}
              {{WRAPPER}} .wag-v{margin:0;font-family:Merriweather,Georgia,serif;font-size:18px;line-height:1.25;color:#5A3D2B;font-variant-numeric:tabular-nums}
              {{WRAPPER}} .wag-sci{font-style:italic}
            </style>';

            echo '<div class="wag-card">';
            $head = trim((string) ($s['heading'] ?? ''));
            if ($head !== '') {
                echo '<span class="wag-eyebrow">' . esc_html($head) . '</span>';
            }
            if ($hasBadge) {
                echo '<div><span class="wag-badge" style="' . esc_attr($badgeStyle) . '"><span class="wag-dot" style="' . esc_attr($dotStyle) . '"></span>IUCN &middot; ' . esc_html($status) . '</span></div>';
            }
            if ($rows) {
                echo '<dl class="wag-list">';
                foreach ($rows as $r) {
                    echo '<div class="wag-row"><dt class="wag-l">' . esc_html($r[0]) . '</dt><dd class="wag-v">' . $r[1] . '</dd></div>';
                }
                echo '</dl>';
            }
            echo '</div>';
        }
    }

    } // end: Island_AtAGlance_Widget guard

    /* ===================================================================
     *  WILDLIFE · SUBSPECIES — title + intro (narratives) + the subspecies
     *  repeater in one of three layouts: A data table, B island cards,
     *  C accordion. Own guard (independent of the shared block).
     * =================================================================== */
    if (!class_exists('Island_Subspecies_Widget')) {
    class Island_Subspecies_Widget extends \Elementor\Widget_Base
    {
        public function get_name() { return 'wildlife_subspecies'; }
        public function get_title() { return 'Wildlife · Subspecies'; }
        public function get_icon() { return 'eicon-table'; }
        public function get_categories() { return ['general']; }

        protected function register_controls()
        {
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('heading', ['label' => 'Fallback heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Subspecies',
                'description' => 'Used when the page has no Subspecies heading.']);
            $this->add_control('show_intro', ['label' => 'Show intro (narratives)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('layout', ['label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'table',
                'options' => ['table' => 'A · Data table', 'cards' => 'B · Island cards', 'accordion' => 'C · Accordion']]);
            $this->end_controls_section();

            $this->start_controls_section('style', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('accent', ['label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#a07a44',
                'selectors' => ['{{WRAPPER}} .wss-eyebrow,{{WRAPPER}} .wss-isl' => 'color:{{VALUE}}']]);
            $this->add_control('heading_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5A3D2B',
                'selectors' => ['{{WRAPPER}} .wss-title,{{WRAPPER}} .wss-sci,{{WRAPPER}} .wss-accnm' => 'color:{{VALUE}}']]);
            $this->add_control('surface', ['label' => 'Surface', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FCF9F5',
                'selectors' => ['{{WRAPPER}} .wss-surface,{{WRAPPER}} .wss-tbl' => 'background:{{VALUE}}']]);
            $this->add_control('radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 28]],
                'default' => ['size' => 14, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wss-surface' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typo', 'selector' => '{{WRAPPER}} .wss-title']);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) { return; }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (get_the_ID() ?: get_queried_object_id());
            $rows = get_field('subspecies', $pid) ?: [];
            $title = trim((string) get_field('subspecies_title', $pid));
            if ($title === '') { $title = trim((string) ($s['heading'] ?? '')); }
            $intro = get_field('subspecies_intro', $pid);
            if (!$rows && !$intro) { return; }
            $layout = $s['layout'] ?? 'table';

            $pill = function ($status) {
                $sl = strtolower((string) $status);
                if ($sl === '') { return ''; }
                $c = 'ok';
                if (strpos($sl, 'extinct') !== false || strpos($sl, 'critically') !== false) { $c = 'bad'; }
                elseif (strpos($sl, 'vulnerable') !== false || strpos($sl, 'recover') !== false || strpos($sl, 'reintroduc') !== false || strpos($sl, 'endangered') !== false) { $c = 'warn'; }
                return '<span class="wss-pill wss-' . $c . '">' . esc_html($status) . '</span>';
            };

            echo '<style>
              {{WRAPPER}}{width:100%}
              {{WRAPPER}} .wss{width:100%;box-sizing:border-box}
              {{WRAPPER}} .wss-eyebrow{display:block;text-transform:uppercase;letter-spacing:.18em;font-size:11px;font-weight:700;color:#a07a44;margin:0 0 6px}
              {{WRAPPER}} .wss-title{font-family:Merriweather,Georgia,serif;font-style:italic;font-size:26px;color:#5A3D2B;margin:0 0 10px}
              {{WRAPPER}} .wss-intro{max-width:70ch;color:#3A2A1E;margin:0 0 22px;font-size:15px;line-height:1.65}
              {{WRAPPER}} .wss-intro h3{font-family:Merriweather,Georgia,serif;font-style:italic;color:#5A3D2B;font-size:19px;margin:22px 0 6px}
              {{WRAPPER}} .wss-sci{font-style:italic;font-family:Merriweather,Georgia,serif}
              {{WRAPPER}} .wss-num{font-variant-numeric:tabular-nums}
              {{WRAPPER}} .wss-pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px}
              {{WRAPPER}} .wss-ok{background:#e4ede0;color:#3f6a2f}{{WRAPPER}} .wss-warn{background:#f4e6cf;color:#9a6a1c}{{WRAPPER}} .wss-bad{background:#f0dcd8;color:#9a3b2e}
              {{WRAPPER}} .wss-tblwrap{overflow-x:auto;box-shadow:0 16px 44px rgba(60,40,25,.14)}
              {{WRAPPER}} .wss-tbl{width:100%;border-collapse:collapse;min-width:540px;font-size:14px;background:#FCF9F5}
              {{WRAPPER}} .wss-tbl th{background:rgba(90,61,43,.10);text-align:left;font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:#5A3D2B;padding:12px 14px;font-weight:700}
              {{WRAPPER}} .wss-tbl td{padding:11px 14px;border-top:1px solid rgba(90,61,43,.14);vertical-align:top}
              {{WRAPPER}} .wss-tbl tr:nth-child(even) td{background:rgba(90,61,43,.04)}
              {{WRAPPER}} .wss-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
              {{WRAPPER}} .wss-card{padding:16px 18px;border:1px solid rgba(90,61,43,.16);box-shadow:0 10px 26px rgba(60,40,25,.10)}
              {{WRAPPER}} .wss-isl{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#a07a44;font-weight:700}
              {{WRAPPER}} .wss-nm{font-size:16px;color:#5A3D2B;margin:3px 0 10px}
              {{WRAPPER}} .wss-foot{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:13px;color:#8a7360}
              {{WRAPPER}} .wss-acc{box-shadow:0 16px 44px rgba(60,40,25,.14);overflow:hidden;border-radius:14px}
              {{WRAPPER}} .wss-acc details{border-bottom:1px solid rgba(90,61,43,.14);background:#FCF9F5}
              {{WRAPPER}} .wss-acc details:last-child{border-bottom:0}
              {{WRAPPER}} .wss-acc summary{cursor:pointer;list-style:none;padding:15px 20px;display:flex;justify-content:space-between;gap:14px;align-items:center}
              {{WRAPPER}} .wss-acc summary::-webkit-details-marker{display:none}
              {{WRAPPER}} .wss-accnm{font-family:Merriweather,Georgia,serif;font-size:17px;color:#5A3D2B}
              {{WRAPPER}} .wss-accin{padding:0 20px 16px;color:#8a7360;font-size:14px}
            </style>';

            echo '<div class="wss">';
            echo '<span class="wss-eyebrow">Island by island</span>';
            if ($title !== '') { echo '<h2 class="wss-title">' . esc_html($title) . '</h2>'; }
            if (($s['show_intro'] ?? 'yes') === 'yes' && $intro) { echo '<div class="wss-intro">' . wp_kses_post($intro) . '</div>'; }

            if ($rows && $layout === 'cards') {
                echo '<div class="wss-grid">';
                foreach ($rows as $r) {
                    $meta = trim(((string) ($r['trait'] ?? '')) . ' · ' . ((string) ($r['population'] ?? '')), " ·");
                    echo '<div class="wss-surface wss-card"><div class="wss-isl">' . esc_html($r['island'] ?? '') . '</div>'
                        . '<div class="wss-nm wss-sci">' . esc_html($r['name'] ?? '') . '</div>'
                        . '<div class="wss-foot"><span>' . esc_html($meta) . '</span>' . $pill($r['status'] ?? '') . '</div></div>';
                }
                echo '</div>';
            } elseif ($rows && $layout === 'accordion') {
                echo '<div class="wss-acc">';
                foreach ($rows as $r) {
                    $meta = trim(((string) ($r['trait'] ?? '')) . ' · ' . ((string) ($r['population'] ?? '')), " ·");
                    echo '<details><summary><span class="wss-accnm">' . esc_html(trim(($r['island'] ?? '') . ' — ' . ($r['name'] ?? ''), " —")) . '</span>' . $pill($r['status'] ?? '') . '</summary>'
                        . '<div class="wss-accin">' . esc_html($meta) . '</div></details>';
                }
                echo '</div>';
            } elseif ($rows) {
                echo '<div class="wss-tblwrap"><table class="wss-tbl"><thead><tr><th>Island</th><th>Species</th><th>Shell</th><th>Pop.</th><th>Status</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    echo '<tr><td>' . esc_html($r['island'] ?? '') . '</td><td class="wss-sci">' . esc_html($r['name'] ?? '') . '</td><td>' . esc_html($r['trait'] ?? '')
                        . '</td><td class="wss-num">' . esc_html($r['population'] ?? '') . '</td><td>' . $pill($r['status'] ?? '') . '</td></tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '</div>';
        }
    }
    } // end: Island_Subspecies_Widget guard

    /* ===================================================================
     *  WILDLIFE · WHERE TO SEE — title + intro + the where_to_see repeater
     *  in one of three layouts: A numbered cards, B timeline, C list.
     * =================================================================== */
    if (!class_exists('Island_WhereToSee_Widget')) {
    class Island_WhereToSee_Widget extends \Elementor\Widget_Base
    {
        public function get_name() { return 'wildlife_where_to_see'; }
        public function get_title() { return 'Wildlife · Where to See'; }
        public function get_icon() { return 'eicon-map-pin'; }
        public function get_categories() { return ['general']; }

        protected function register_controls()
        {
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('heading', ['label' => 'Fallback heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Where and How to See Them']);
            $this->add_control('show_intro', ['label' => 'Show intro', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('layout', ['label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'cards',
                'options' => ['cards' => 'A · Numbered cards', 'timeline' => 'B · Timeline', 'list' => 'C · Compact list']]);
            $this->add_responsive_control('columns', ['label' => 'Card columns', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '2', 'tablet_default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2'], 'condition' => ['layout' => 'cards'],
                'selectors' => ['{{WRAPPER}} .wts-cards' => 'grid-template-columns:repeat({{VALUE}},1fr)']]);
            $this->end_controls_section();

            $this->start_controls_section('style', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('accent', ['label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#a07a44',
                'selectors' => ['{{WRAPPER}} .wts-eyebrow,{{WRAPPER}} .wts-isl' => 'color:{{VALUE}}']]);
            $this->add_control('brown', ['label' => 'Heading / chip', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5A3D2B',
                'selectors' => ['{{WRAPPER}} .wts-title,{{WRAPPER}} .wts-site' => 'color:{{VALUE}}', '{{WRAPPER}} .wts-no' => 'background:{{VALUE}}']]);
            $this->add_control('surface', ['label' => 'Card surface', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FCF9F5',
                'selectors' => ['{{WRAPPER}} .wts-surface' => 'background:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typo', 'selector' => '{{WRAPPER}} .wts-title']);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) { return; }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (get_the_ID() ?: get_queried_object_id());
            $rows = get_field('where_to_see', $pid) ?: [];
            $title = trim((string) get_field('where_to_see_title', $pid));
            if ($title === '') { $title = trim((string) ($s['heading'] ?? '')); }
            $intro = get_field('where_to_see_intro', $pid);
            if (!$rows && !$intro) { return; }
            $layout = $s['layout'] ?? 'cards';

            echo '<style>
              {{WRAPPER}}{width:100%}
              {{WRAPPER}} .wts{width:100%;box-sizing:border-box}
              {{WRAPPER}} .wts-eyebrow{display:block;text-transform:uppercase;letter-spacing:.18em;font-size:11px;font-weight:700;color:#a07a44;margin:0 0 6px}
              {{WRAPPER}} .wts-title{font-family:Merriweather,Georgia,serif;font-style:italic;font-size:26px;color:#5A3D2B;margin:0 0 10px}
              {{WRAPPER}} .wts-intro{max-width:70ch;color:#3A2A1E;margin:0 0 22px;font-size:15px;line-height:1.65}
              {{WRAPPER}} .wts-isl{font-size:11px;letter-spacing:.13em;text-transform:uppercase;color:#a07a44;font-weight:700}
              {{WRAPPER}} .wts-site{font-family:Merriweather,Georgia,serif;font-style:italic;color:#5A3D2B;font-size:18px;margin:2px 0 5px}
              {{WRAPPER}} .wts-desc{margin:0;font-size:13.5px;color:#8a7360;line-height:1.6}
              {{WRAPPER}} .wts-desc p{margin:0 0 6px}
              {{WRAPPER}} .wts-cards{display:grid;grid-template-columns:1fr 1fr;gap:14px}
              {{WRAPPER}} .wts-card{display:grid;grid-template-columns:auto 1fr;gap:15px;padding:18px;border:1px solid rgba(90,61,43,.16);border-radius:16px;box-shadow:0 12px 30px rgba(60,40,25,.12)}
              {{WRAPPER}} .wts-no{width:36px;height:36px;border-radius:50%;background:#5A3D2B;color:#f6efe4;display:flex;align-items:center;justify-content:center;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:16px}
              {{WRAPPER}} .wts-tl{border-left:2px solid #c8ad82;margin-left:12px;padding-left:26px}
              {{WRAPPER}} .wts-tlrow{position:relative;padding:0 0 22px}
              {{WRAPPER}} .wts-tlrow .wts-dot{position:absolute;left:-35px;top:2px;width:15px;height:15px;border-radius:50%;background:#a07a44;border:3px solid #efe9e1}
              {{WRAPPER}} .wts-rows{border-radius:14px;box-shadow:0 16px 44px rgba(60,40,25,.14);overflow:hidden}
              {{WRAPPER}} .wts-r{display:grid;grid-template-columns:130px 1fr;gap:16px;padding:16px 20px;background:#FCF9F5;border-top:1px solid rgba(90,61,43,.14)}
              {{WRAPPER}} .wts-r:first-child{border-top:0}
              {{WRAPPER}} .wts-r .wts-isl{padding-top:3px}
              @media(max-width:720px){ {{WRAPPER}} .wts-cards{grid-template-columns:1fr} {{WRAPPER}} .wts-r{grid-template-columns:1fr} }
            </style>';

            echo '<div class="wts">';
            echo '<span class="wts-eyebrow">Plan the encounter</span>';
            if ($title !== '') { echo '<h2 class="wts-title">' . esc_html($title) . '</h2>'; }
            if (($s['show_intro'] ?? 'yes') === 'yes' && $intro) { echo '<div class="wts-intro">' . wp_kses_post($intro) . '</div>'; }

            if ($rows && $layout === 'timeline') {
                echo '<div class="wts-tl">';
                foreach ($rows as $r) {
                    echo '<div class="wts-tlrow"><span class="wts-dot"></span><div class="wts-isl">' . esc_html($r['island'] ?? '') . '</div>'
                        . '<h4 class="wts-site">' . esc_html($r['site'] ?? '') . '</h4><div class="wts-desc">' . wp_kses_post($r['description'] ?? '') . '</div></div>';
                }
                echo '</div>';
            } elseif ($rows && $layout === 'list') {
                echo '<div class="wts-rows">';
                foreach ($rows as $r) {
                    echo '<div class="wts-r"><div class="wts-isl">' . esc_html($r['island'] ?? '') . '</div><div>'
                        . '<h4 class="wts-site">' . esc_html($r['site'] ?? '') . '</h4><div class="wts-desc">' . wp_kses_post($r['description'] ?? '') . '</div></div></div>';
                }
                echo '</div>';
            } elseif ($rows) {
                echo '<div class="wts-cards">';
                $i = 0;
                foreach ($rows as $r) {
                    $i++;
                    echo '<div class="wts-surface wts-card"><div class="wts-no">' . (int) $i . '</div><div><div class="wts-isl">' . esc_html($r['island'] ?? '') . '</div>'
                        . '<h4 class="wts-site">' . esc_html($r['site'] ?? '') . '</h4><div class="wts-desc">' . wp_kses_post($r['description'] ?? '') . '</div></div></div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }
    } // end: Island_WhereToSee_Widget guard

    /* ===================================================================
     *  WILDLIFE · SEASONALITY — title + intro + the seasonality repeater
     *  in one of three layouts: A calendar strip, B rows, C year bar.
     * =================================================================== */
    if (!class_exists('Island_Seasonality_Widget')) {
    class Island_Seasonality_Widget extends \Elementor\Widget_Base
    {
        public function get_name() { return 'wildlife_seasonality'; }
        public function get_title() { return 'Wildlife · Seasonality'; }
        public function get_icon() { return 'eicon-calendar'; }
        public function get_categories() { return ['general']; }

        protected function register_controls()
        {
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('heading', ['label' => 'Fallback heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Best Time to See']);
            $this->add_control('show_intro', ['label' => 'Show intro', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('layout', ['label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'calendar',
                'options' => ['calendar' => 'A · Calendar strip', 'rows' => 'B · Rows (with notes)', 'bar' => 'C · Year bar']]);
            $this->end_controls_section();

            $this->start_controls_section('style', ['label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('accent', ['label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#a07a44',
                'selectors' => ['{{WRAPPER}} .wsn-eyebrow' => 'color:{{VALUE}}']]);
            $this->add_control('brown', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5A3D2B',
                'selectors' => ['{{WRAPPER}} .wsn-title,{{WRAPPER}} .wsn-mo' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typo', 'selector' => '{{WRAPPER}} .wsn-title']);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) { return; }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (get_the_ID() ?: get_queried_object_id());
            $rows = get_field('seasonality', $pid) ?: [];
            $title = trim((string) get_field('seasonality_title', $pid));
            if ($title === '') { $title = trim((string) ($s['heading'] ?? '')); }
            $intro = get_field('seasonality_intro', $pid);
            if (!$rows && !$intro) { return; }
            $layout = $s['layout'] ?? 'calendar';

            $cls = function ($label) {
                $l = strtolower((string) $label);
                if (strpos($l, 'present') !== false) { return 'pr'; }
                if (strpos($l, 'depart') !== false) { return 'de'; }
                if (strpos($l, 'absent') !== false) { return 'ab'; }
                return 'nu';
            };

            echo '<style>
              {{WRAPPER}}{width:100%}
              {{WRAPPER}} .wsn{width:100%;box-sizing:border-box}
              {{WRAPPER}} .wsn-eyebrow{display:block;text-transform:uppercase;letter-spacing:.18em;font-size:11px;font-weight:700;color:#a07a44;margin:0 0 6px}
              {{WRAPPER}} .wsn-title{font-family:Merriweather,Georgia,serif;font-style:italic;font-size:26px;color:#5A3D2B;margin:0 0 10px}
              {{WRAPPER}} .wsn-intro{max-width:70ch;color:#3A2A1E;margin:0 0 22px;font-size:15px;line-height:1.65}
              {{WRAPPER}} .wsn-cal{display:grid;grid-template-columns:repeat(auto-fit,minmax(64px,1fr));gap:5px}
              {{WRAPPER}} .wsn-mo{text-align:center;padding:12px 4px;border-radius:10px;font-size:12px;font-weight:700}
              {{WRAPPER}} .wsn-mo small{display:block;font-weight:600;font-size:9.5px;letter-spacing:.03em;margin-top:4px;opacity:.9}
              {{WRAPPER}} .wsn-pr{background:#dfe9d0;color:#3f6a2f}{{WRAPPER}} .wsn-ab{background:#eee7dd;color:#9a8a76}{{WRAPPER}} .wsn-de{background:#f4e6cf;color:#9a6a1c}{{WRAPPER}} .wsn-nu{background:#efe9e1;color:#6a5c4e}
              {{WRAPPER}} .wsn-legend{display:flex;gap:16px;margin-top:14px;font-size:12.5px;color:#8a7360;flex-wrap:wrap}
              {{WRAPPER}} .wsn-legend span{display:inline-flex;align-items:center;gap:6px}
              {{WRAPPER}} .wsn-legend i{width:12px;height:12px;border-radius:3px;display:inline-block}
              {{WRAPPER}} .wsn-tbl{border-radius:14px;box-shadow:0 16px 44px rgba(60,40,25,.14);overflow:hidden}
              {{WRAPPER}} .wsn-r{display:grid;grid-template-columns:120px 110px 1fr;gap:14px;align-items:center;padding:12px 18px;background:#FCF9F5;border-top:1px solid rgba(90,61,43,.14);font-size:14px}
              {{WRAPPER}} .wsn-r:first-child{border-top:0}
              {{WRAPPER}} .wsn-per{font-family:Merriweather,Georgia,serif;color:#5A3D2B;font-size:15px}
              {{WRAPPER}} .wsn-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px}
              {{WRAPPER}} .wsn-note{color:#8a7360;font-size:13px}
              {{WRAPPER}} .wsn-bar{display:flex;border-radius:12px;overflow:hidden;box-shadow:0 16px 44px rgba(60,40,25,.14)}
              {{WRAPPER}} .wsn-seg{flex:1;min-width:0;padding:16px 4px;text-align:center;font-size:11px;font-weight:700}
              {{WRAPPER}} .wsn-seg small{display:block;font-size:9px;opacity:.85;margin-top:3px}
              @media(max-width:720px){ {{WRAPPER}} .wsn-r{grid-template-columns:78px 92px 1fr} }
            </style>';

            echo '<div class="wsn">';
            echo '<span class="wsn-eyebrow">Best time</span>';
            if ($title !== '') { echo '<h2 class="wsn-title">' . esc_html($title) . '</h2>'; }
            if (($s['show_intro'] ?? 'yes') === 'yes' && $intro) { echo '<div class="wsn-intro">' . wp_kses_post($intro) . '</div>'; }

            $legend = '<div class="wsn-legend"><span><i style="background:#3f6a2f"></i>Present</span><span><i style="background:#c8ad82"></i>Absent</span><span><i style="background:#9a6a1c"></i>Departing</span></div>';

            if ($rows && $layout === 'rows') {
                echo '<div class="wsn-tbl">';
                foreach ($rows as $r) {
                    $c = $cls($r['label'] ?? '');
                    echo '<div class="wsn-r"><span class="wsn-per">' . esc_html($r['period'] ?? '') . '</span>'
                        . '<span><span class="wsn-badge wsn-' . $c . '">' . esc_html($r['label'] ?? '') . '</span></span>'
                        . '<span class="wsn-note">' . esc_html($r['notes'] ?? '') . '</span></div>';
                }
                echo '</div>';
            } elseif ($rows && $layout === 'bar') {
                echo '<div class="wsn-bar">';
                foreach ($rows as $r) {
                    $c = $cls($r['label'] ?? '');
                    $lab = trim((string) ($r['label'] ?? ''));
                    echo '<div class="wsn-seg wsn-' . $c . '">' . esc_html($r['period'] ?? '') . '<small>' . esc_html($lab !== '' ? mb_substr($lab, 0, 3) : '') . '</small></div>';
                }
                echo '</div>' . $legend;
            } elseif ($rows) {
                echo '<div class="wsn-cal">';
                foreach ($rows as $r) {
                    $c = $cls($r['label'] ?? '');
                    echo '<div class="wsn-mo wsn-' . $c . '">' . esc_html($r['period'] ?? '') . '<small>' . esc_html($r['label'] ?? '') . '</small></div>';
                }
                echo '</div>' . $legend;
            }
            echo '</div>';
        }
    }
    } // end: Island_Seasonality_Widget guard

    /* ===================================================================
     *  WILDLIFE · AT A GLANCE + FEATURES — ONE widget, two columns.
     *  Left: the At a Glance soft card (defines the height). Right: the
     *  Feature Sections, scrolling internally so it never grows past the
     *  card's height. Because both live in the same widget, the height
     *  sync is exact — no cross-column Elementor guessing.
     * =================================================================== */
    if (!class_exists('Island_GlanceFeatures_Widget')) {
    class Island_GlanceFeatures_Widget extends \Elementor\Widget_Base
    {
        public function get_name() { return 'wildlife_glance_features'; }
        public function get_title() { return 'Wildlife · At a Glance + Features'; }
        public function get_icon() { return 'eicon-column'; }
        public function get_categories() { return ['general']; }

        protected function register_controls()
        {
            $this->start_controls_section('content', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
            $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
            $this->add_control('glance_heading', ['label' => 'At a Glance heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'At a Glance']);
            $this->add_control('show_badge', ['label' => 'IUCN status badge', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('endemic_text', ['label' => 'Endemic "yes" text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Yes — endemic to Galápagos']);
            $this->add_control('include_quick_facts', ['label' => 'Append "At a Glance" facts', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Adds quick_facts rows (Lifespan, Weight…), skipping any already shown (scientific name, population, IUCN).']);
            $this->add_control('feat_eyebrow', ['label' => 'Features eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'The species', 'separator' => 'before']);
            $this->add_control('feat_heading', ['label' => 'Features heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Feature Sections',
                'description' => 'Blank = use the page’s feature_sections_title if present.']);
            $this->add_control('alternate', ['label' => 'Alternate image side', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
            $this->add_control('hover_expand', ['label' => 'Clamp text, expand on hover', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
                'description' => 'Show a few lines per feature; the full text opens on hover (tap on mobile) and closes on leave. A soft fade hints there is more.']);
            $this->add_control('clamp_lines', ['label' => 'Lines when collapsed', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 5, 'min' => 2, 'max' => 20,
                'condition' => ['hover_expand' => 'yes']]);
            $this->add_control('open_h', ['label' => 'Open height (max)', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 300, 'max' => 2000]],
                'default' => ['size' => 900, 'unit' => 'px'], 'condition' => ['hover_expand' => 'yes'],
                'description' => 'Max height when opened on hover.']);
            $this->end_controls_section();

            /* LAYOUT */
            $this->start_controls_section('layout_s', ['label' => 'Layout', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_responsive_control('rail_w', ['label' => 'At a Glance width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 240, 'max' => 460]],
                'default' => ['size' => 320, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wgf' => 'grid-template-columns:{{SIZE}}{{UNIT}} 1fr']]);
            $this->add_control('gap', ['label' => 'Column gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 12, 'max' => 80]],
                'default' => ['size' => 40, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wgf' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_control('feat_gap', ['label' => 'Feature card gap', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 8, 'max' => 48]],
                'default' => ['size' => 18, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wgf-feats' => 'gap:{{SIZE}}{{UNIT}}']]);
            $this->add_control('min_h', ['label' => 'Minimum scroll height (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 320, 'min' => 200,
                'description' => 'The feature list never shrinks below this even if the card is short.']);
            $this->add_control('fade', ['label' => 'Fade color (match page bg)', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#efe7dd',
                'selectors' => ['{{WRAPPER}} .wgf-col' => '--wgf-fade:{{VALUE}}']]);
            $this->end_controls_section();

            /* CARD (left) */
            $this->start_controls_section('card_s', ['label' => 'At a Glance card', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('card_bg', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FCF9F5',
                'selectors' => ['{{WRAPPER}} .wgf-glance' => 'background:{{VALUE}}']]);
            $this->add_control('card_radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 36]],
                'default' => ['size' => 18, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wgf-glance' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_control('card_border', ['label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(90,61,43,.16)',
                'selectors' => ['{{WRAPPER}} .wgf-glance' => 'border-color:{{VALUE}}']]);
            $this->add_control('accent', ['label' => 'Eyebrow / accent', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#a07a44',
                'selectors' => ['{{WRAPPER}} .wgf-eyebrow,{{WRAPPER}} .wgf-kicker' => 'color:{{VALUE}}']]);
            $this->add_control('heading_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#5A3D2B',
                'selectors' => ['{{WRAPPER}} .wgf-v,{{WRAPPER}} .wgf-rhead h2,{{WRAPPER}} .wgf-feat h3' => 'color:{{VALUE}}']]);
            $this->end_controls_section();

            /* FEATURE CARDS (right) */
            $this->start_controls_section('feat_s', ['label' => 'Feature cards', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('feat_bg', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FCF9F5',
                'selectors' => ['{{WRAPPER}} .wgf-feat' => 'background:{{VALUE}}', '{{WRAPPER}} .wgf' => '--wgf-card:{{VALUE}}']]);
            $this->add_control('feat_border', ['label' => 'Card border', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(90,61,43,.16)',
                'selectors' => ['{{WRAPPER}} .wgf-feat' => 'border-color:{{VALUE}}']]);
            $this->add_control('feat_radius', ['label' => 'Card radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 32]],
                'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wgf-feat' => 'border-radius:{{SIZE}}{{UNIT}}']]);
            $this->add_responsive_control('feat_pad', ['label' => 'Card padding', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px'],
                'default' => ['top' => 22, 'right' => 24, 'bottom' => 22, 'left' => 24, 'unit' => 'px', 'isLinked' => false],
                'selectors' => ['{{WRAPPER}} .wgf-tx' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
            $this->add_responsive_control('img_w', ['label' => 'Image width', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['%' => ['min' => 25, 'max' => 60]],
                'default' => ['size' => 43, 'unit' => '%'], 'selectors' => [
                    '{{WRAPPER}} .wgf-feat' => 'grid-template-columns:{{SIZE}}% 1fr',
                    '{{WRAPPER}} .wgf-feat.rev' => 'grid-template-columns:1fr {{SIZE}}%']]);
            $this->add_control('media_h', ['label' => 'Image min height', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 100, 'max' => 400]],
                'default' => ['size' => 150, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .wgf-media' => 'min-height:{{SIZE}}{{UNIT}}']]);
            $this->end_controls_section();

            /* TYPOGRAPHY — sizes / fonts for every text bit */
            $this->start_controls_section('type_s', ['label' => 'Typography', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
            $this->add_control('h_rhead', ['label' => 'Features heading (H2)', 'type' => \Elementor\Controls_Manager::HEADING]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'rhead_typo', 'selector' => '{{WRAPPER}} .wgf-rhead h2']);
            $this->add_control('h_kicker', ['label' => 'Feature eyebrow / kicker', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'kicker_typo', 'selector' => '{{WRAPPER}} .wgf-kicker,{{WRAPPER}} .wgf-eyebrow']);
            $this->add_control('h_ftitle', ['label' => 'Feature title (H3)', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'ftitle_typo', 'selector' => '{{WRAPPER}} .wgf-feat h3']);
            $this->add_control('h_body', ['label' => 'Feature body text', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('body_color', ['label' => 'Body color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#3A2A1E',
                'selectors' => ['{{WRAPPER}} .wgf-body,{{WRAPPER}} .wgf-body p' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'body_typo', 'selector' => '{{WRAPPER}} .wgf-body,{{WRAPPER}} .wgf-body p']);
            $this->add_control('h_glance', ['label' => 'At a Glance value', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'glance_typo', 'selector' => '{{WRAPPER}} .wgf-v']);
            $this->add_control('h_glabel', ['label' => 'At a Glance label', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('glabel_color', ['label' => 'Label color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#8a7360',
                'selectors' => ['{{WRAPPER}} .wgf-l' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'glabel_typo', 'selector' => '{{WRAPPER}} .wgf-l']);
            $this->end_controls_section();
        }

        protected function render()
        {
            if (!function_exists('get_field')) { return; }
            $s = $this->get_settings_for_display();
            $pid = !empty($s['source_id']) ? (int) $s['source_id'] : (get_the_ID() ?: get_queried_object_id());

            // ---- LEFT: At a Glance ----
            $sci = trim((string) get_field('scientific_name', $pid));
            $pop = trim((string) get_field('population', $pid));
            $status = trim((string) get_field('conservation_status', $pid));
            $endemic = (bool) get_field('endemic', $pid);
            $facts = get_field('quick_facts', $pid) ?: [];
            $rows = [];
            if ($sci !== '') { $rows[] = ['Scientific name', '<span class="wgf-sci">' . esc_html($sci) . '</span>']; }
            if ($pop !== '') { $rows[] = ['Population', esc_html($pop)]; }
            if ($endemic) { $rows[] = ['Endemic', esc_html(($s['endemic_text'] ?? '') ?: 'Yes — endemic to Galápagos')]; }
            if (($s['include_quick_facts'] ?? 'yes') === 'yes' && is_array($facts)) {
                $skip = ['scientific name', 'common name', 'iucn status', 'iucn', 'conservation status', 'status', 'population', 'population estimate', 'endemic'];
                foreach ($facts as $f) {
                    $lab = trim((string) ($f['label'] ?? ''));
                    $val = trim((string) ($f['value'] ?? ''));
                    if ($lab === '' || $val === '' || in_array(strtolower($lab), $skip, true)) { continue; }
                    $rows[] = [$lab, esc_html($val)];
                }
            }
            $hasBadge = ($s['show_badge'] ?? 'yes') === 'yes' && $status !== '';
            $hasGlance = (bool) ($rows || $hasBadge);

            // ---- RIGHT: Feature Sections ----
            $feats = get_field('feature_sections', $pid) ?: [];
            if (!$hasGlance && !$feats) { return; }

            $map = [
                'least concern' => ['#e4ede0', '#3f6a2f'], 'near threatened' => ['#eef0d6', '#6a7a1c'],
                'vulnerable' => ['#f4e6cf', '#9a6a1c'], 'endangered' => ['#f6ddc9', '#b5591f'],
                'critically endangered' => ['#f0dcd8', '#9a3b2e'], 'data deficient' => ['#e6e0da', '#6a5e52'],
            ];
            $bc = $map[strtolower($status)] ?? ['#f4e6cf', '#9a6a1c'];
            $badgeStyle = 'background:' . $bc[0] . ';color:' . $bc[1] . ';border-color:' . $bc[1] . '40';
            $dotStyle = 'background:' . $bc[1];

            $feat_head = trim((string) ($s['feat_heading'] ?? ''));
            if ($feat_head === '') { $feat_head = trim((string) get_field('feature_sections_title', $pid)); }
            $mn = max(200, (int) ($s['min_h'] ?? 320));
            $alt = ($s['alternate'] ?? 'yes') === 'yes';
            $hx = ($s['hover_expand'] ?? 'yes') === 'yes';
            $cl = max(2, (int) ($s['clamp_lines'] ?? 5));
            $open = (int) ($s['open_h']['size'] ?? 900);

            echo '<style>
              {{WRAPPER}}{width:100%}
              {{WRAPPER}} .wgf{width:100%;box-sizing:border-box;display:grid;grid-template-columns:320px 1fr;gap:40px;align-items:start}
              {{WRAPPER}} .wgf.wgf-solo{grid-template-columns:1fr}
              {{WRAPPER}} .wgf-solo .wgf-scroll{overflow:visible;max-height:none!important;padding-right:0}
              {{WRAPPER}} .wgf-solo .wgf-fade{display:none}
              {{WRAPPER}} .wgf-glance{background:#FCF9F5;border:1px solid rgba(90,61,43,.16);border-radius:18px;box-shadow:0 16px 44px rgba(60,40,25,.14);padding:24px 26px;align-self:start}
              {{WRAPPER}} .wgf-eyebrow{display:block;text-transform:uppercase;letter-spacing:.2em;font-size:11px;font-weight:700;color:#a07a44;margin:0 0 14px}
              {{WRAPPER}} .wgf-badge{display:inline-flex;align-items:center;gap:7px;font-weight:700;font-size:12px;padding:6px 13px;border-radius:999px;border:1px solid transparent}
              {{WRAPPER}} .wgf-dot{width:8px;height:8px;border-radius:50%;flex:0 0 auto}
              {{WRAPPER}} .wgf-list{margin:12px 0 0;padding:0;display:flex;flex-direction:column}
              {{WRAPPER}} .wgf-row{padding:13px 0;border-bottom:1px solid rgba(90,61,43,.13)}
              {{WRAPPER}} .wgf-row:last-child{border-bottom:0;padding-bottom:2px}
              {{WRAPPER}} .wgf-l{margin:0 0 2px;font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;color:#8a7360;font-weight:700}
              {{WRAPPER}} .wgf-v{margin:0;font-family:Merriweather,Georgia,serif;font-size:18px;line-height:1.25;color:#5A3D2B;font-variant-numeric:tabular-nums}
              {{WRAPPER}} .wgf-sci{font-style:italic}
              {{WRAPPER}} .wgf-col{min-width:0;position:relative}
              {{WRAPPER}} .wgf-rhead{margin:2px 0 14px}
              {{WRAPPER}} .wgf-rhead .wgf-eyebrow{margin-bottom:4px}
              {{WRAPPER}} .wgf-rhead h2{margin:0;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:26px;color:#5A3D2B}
              {{WRAPPER}} .wgf-scroll{overflow-y:auto;padding-right:10px;scrollbar-width:thin;scrollbar-color:#c8ad82 transparent}
              {{WRAPPER}} .wgf-scroll::-webkit-scrollbar{width:8px}
              {{WRAPPER}} .wgf-scroll::-webkit-scrollbar-thumb{background:#c8ad82;border-radius:999px}
              {{WRAPPER}} .wgf-scroll::-webkit-scrollbar-track{background:transparent}
              {{WRAPPER}} .wgf-feats{display:flex;flex-direction:column;gap:18px}
              {{WRAPPER}} .wgf-feat{background:#FCF9F5;border:1px solid rgba(90,61,43,.16);border-radius:16px;box-shadow:0 10px 26px rgba(60,40,25,.10);overflow:hidden;display:grid;grid-template-columns:1.3fr 1fr}
              {{WRAPPER}} .wgf-feat.rev{grid-template-columns:1fr 1.3fr}
              {{WRAPPER}} .wgf-feat.noimg{grid-template-columns:1fr!important}
              {{WRAPPER}} .wgf-tx{padding:22px 24px;display:flex;flex-direction:column;justify-content:center}
              {{WRAPPER}} .wgf-feat.rev .wgf-tx{order:2}
              {{WRAPPER}} .wgf-kicker{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#a07a44;font-weight:700;margin:0 0 8px}
              {{WRAPPER}} .wgf-feat h3{margin:0 0 9px;font-family:Merriweather,Georgia,serif;font-style:italic;font-size:20px;line-height:1.2;color:#5A3D2B}
              {{WRAPPER}} .wgf-body{margin:0;color:#3A2A1E;font-size:14px;line-height:1.6}{{WRAPPER}} .wgf-body p{margin:0 0 10px}{{WRAPPER}} .wgf-body :last-child{margin-bottom:0}
              {{WRAPPER}} .wgf.hx .wgf-body{position:relative;max-height:calc(var(--cl,5) * 1.7em);overflow:hidden;transition:max-height .45s ease}
              {{WRAPPER}} .wgf.hx .wgf-body::after{content:"";position:absolute;left:0;right:0;bottom:0;height:1.7em;background:linear-gradient(rgba(0,0,0,0),var(--wgf-card,#FCF9F5));pointer-events:none;transition:opacity .3s ease}
              {{WRAPPER}} .wgf.hx .wgf-feat:hover .wgf-body,{{WRAPPER}} .wgf.hx .wgf-feat:focus-within .wgf-body,{{WRAPPER}} .wgf.hx .wgf-feat.is-open .wgf-body{max-height:var(--wgf-open,900px)}
              {{WRAPPER}} .wgf.hx .wgf-feat:hover .wgf-body::after,{{WRAPPER}} .wgf.hx .wgf-feat:focus-within .wgf-body::after,{{WRAPPER}} .wgf.hx .wgf-feat.is-open .wgf-body::after{opacity:0}
              @media(prefers-reduced-motion:reduce){{{WRAPPER}} .wgf.hx .wgf-body,{{WRAPPER}} .wgf.hx .wgf-body::after{transition:none}}
              {{WRAPPER}} .wgf-media{min-height:150px;background:#e3d6c8 center/cover no-repeat}
              {{WRAPPER}} .wgf-feat.rev .wgf-media{order:1}
              {{WRAPPER}} .wgf-fade{position:absolute;left:0;right:10px;bottom:0;height:54px;background:linear-gradient(rgba(0,0,0,0),var(--wgf-fade,#efe7dd));pointer-events:none;opacity:0;transition:opacity .2s}
              {{WRAPPER}} .wgf-col.is-of .wgf-fade{opacity:1}
              @media(max-width:820px){{{WRAPPER}} .wgf{grid-template-columns:1fr}{{WRAPPER}} .wgf-scroll{max-height:none!important;overflow:visible;padding-right:0}{{WRAPPER}} .wgf-fade{display:none}{{WRAPPER}} .wgf-feat,{{WRAPPER}} .wgf-feat.rev{grid-template-columns:1fr}{{WRAPPER}} .wgf-feat.rev .wgf-media{order:0}}
            </style>';

            // When the page has no At a Glance data, the features take the full
            // width (single column, natural flow — no rail, no internal scroll).
            $wgf_vars = '--cl:' . $cl . ';--wgf-open:' . $open . 'px';
            echo '<div class="wgf' . ($hasGlance ? '' : ' wgf-solo') . ($hx ? ' hx' : '') . '" data-min="' . $mn . '" style="' . esc_attr($wgf_vars) . '">';

            // LEFT card (only when there is glance data)
            if ($hasGlance) {
                echo '<aside><div class="wgf-glance">';
                $gh = trim((string) ($s['glance_heading'] ?? ''));
                if ($gh !== '') { echo '<span class="wgf-eyebrow">' . esc_html($gh) . '</span>'; }
                if ($hasBadge) {
                    echo '<div><span class="wgf-badge" style="' . esc_attr($badgeStyle) . '"><span class="wgf-dot" style="' . esc_attr($dotStyle) . '"></span>IUCN &middot; ' . esc_html($status) . '</span></div>';
                }
                if ($rows) {
                    echo '<dl class="wgf-list">';
                    foreach ($rows as $r) {
                        echo '<div class="wgf-row"><dt class="wgf-l">' . esc_html($r[0]) . '</dt><dd class="wgf-v">' . $r[1] . '</dd></div>';
                    }
                    echo '</dl>';
                }
                echo '</div></aside>';
            }

            // RIGHT column
            echo '<div class="wgf-col">';
            $eb = trim((string) ($s['feat_eyebrow'] ?? ''));
            if ($eb !== '' || $feat_head !== '') {
                echo '<div class="wgf-rhead">';
                if ($eb !== '') { echo '<span class="wgf-eyebrow">' . esc_html($eb) . '</span>'; }
                if ($feat_head !== '') { echo '<h2>' . esc_html($feat_head) . '</h2>'; }
                echo '</div>';
            }
            echo '<div class="wgf-scroll"><div class="wgf-feats">';
            $i = 0;
            foreach ($feats as $r) {
                $img = island_ew_image_src($r['image'] ?? '');
                $rev = ($alt && ($i % 2 === 1)) ? ' rev' : '';
                $noimg = $img ? '' : ' noimg';
                echo '<article class="wgf-feat' . $rev . $noimg . '"' . ($hx ? ' tabindex="0"' : '') . '><div class="wgf-tx">';
                if (!empty($r['subtitle'])) { echo '<span class="wgf-kicker">' . esc_html($r['subtitle']) . '</span>'; }
                echo '<h3>' . esc_html($r['title'] ?? '') . '</h3>';
                $content = (string) ($r['content'] ?? '');
                // Drop any table markup — the combined view is a compact reading
                // column, not the place for wide data tables.
                $content = preg_replace('/<table\b[\s\S]*?<\/table>/i', '', $content);
                if (trim(wp_strip_all_tags($content)) !== '') {
                    echo '<div class="wgf-body">' . wp_kses_post($content) . '</div>';
                }
                echo '</div>';
                if ($img) { echo '<div class="wgf-media" style="background-image:url(\'' . esc_url($img) . '\')"></div>'; }
                echo '</article>';
                $i++;
            }
            echo '</div></div><div class="wgf-fade"></div></div>';  // .wgf-feats .wgf-scroll .wgf-fade
            echo '</div>';  // .wgf

            // Hover / tap to expand each feature (JS-driven so it never depends
            // on CSS :hover, which the Elementor overlay can swallow). Binds via
            // previousElementSibling, so it must print right after the .wgf.
            if ($hx) {
                echo '<script>(function(){var w=document.currentScript&&document.currentScript.previousElementSibling;'
                    . 'if(!w||!w.querySelectorAll)return;w.querySelectorAll(".wgf-feat").forEach(function(c){'
                    . 'c.addEventListener("mouseenter",function(){c.classList.add("is-open");});'
                    . 'c.addEventListener("mouseleave",function(){c.classList.remove("is-open");});'
                    . 'c.addEventListener("touchstart",function(e){if(!e.target.closest("a"))c.classList.toggle("is-open");},{passive:true});});})();</script>';
            }

            $this->gf_sync_script();
        }

        /** Height-sync: the right scroll area matches the left card's height so
         * the two columns end level; printed once per request, desktop only. */
        private function gf_sync_script()
        {
            static $done = false;
            if ($done) { return; }
            $done = true;
            echo '<script>(function(){if(window.__islandGfSync)return;window.__islandGfSync=1;'
                . 'function sync(){document.querySelectorAll(".wgf").forEach(function(w){'
                . 'var g=w.querySelector(".wgf-glance"),sc=w.querySelector(".wgf-scroll"),rh=w.querySelector(".wgf-rhead"),col=w.querySelector(".wgf-col");'
                . 'if(!g||!sc)return;'
                . 'if(window.innerWidth<=820){sc.style.maxHeight="";if(col)col.classList.remove("is-of");return;}'
                . 'sc.style.maxHeight="none";var head=rh?rh.offsetHeight:0;'
                . 'var mn=parseInt(w.getAttribute("data-min"),10)||320;var h=Math.max(mn,g.offsetHeight-head-14);'
                . 'sc.style.maxHeight=h+"px";if(col)col.classList.toggle("is-of",sc.scrollHeight>sc.clientHeight+2);});}'
                . 'var t;function later(){clearTimeout(t);t=setTimeout(sync,60);}'
                . 'window.addEventListener("resize",later);'
                . 'window.addEventListener("load",function(){sync();setTimeout(sync,300);setTimeout(sync,900);});'
                . 'if(document.readyState!=="loading"){sync();setTimeout(sync,300);}else{document.addEventListener("DOMContentLoaded",function(){sync();setTimeout(sync,300);});}'
                . 'if("ResizeObserver" in window){var ro=new ResizeObserver(later);setTimeout(function(){'
                . 'document.querySelectorAll(".wgf .wgf-glance").forEach(function(g){ro.observe(g);});},200);}'
                . '})();</script>';
        }
    }
    } // end: Island_GlanceFeatures_Widget guard

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
    $widgets_manager->register(new Island_RelatedLinks_Widget());
    $widgets_manager->register(new Island_Schema_Widget());
    $widgets_manager->register(new Island_Infographic_Widget());
    // New widgets are registered defensively: if one ever throws while building
    // its controls (e.g. an Elementor build missing a group-control class), the
    // site stays up and only that widget is skipped — never a white screen.
    foreach ([
        'Island_AtAGlance_Widget',
        'Island_GlanceFeatures_Widget',
        'Island_Subspecies_Widget',
        'Island_WhereToSee_Widget',
        'Island_Seasonality_Widget',
    ] as $island_ew_new) {
        try {
            if (class_exists($island_ew_new)) {
                $widgets_manager->register(new $island_ew_new());
            }
        } catch (\Throwable $e) {
            error_log('[island-widgets] ' . $island_ew_new . ' skipped: ' . $e->getMessage());
        }
    }
    // One-time cleanup of the temporary diagnostic option (harmless if absent).
    if (get_option('island_ew_atglance_status') !== false) {
        delete_option('island_ew_atglance_status');
    }
});
