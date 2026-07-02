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
            $this->add_control('card_bg', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#faf9f7',
                'selectors' => ['{{WRAPPER}} .iw2-ed .iw2-tx,{{WRAPPER}} .iw2-ofc' => 'background:{{VALUE}};--fade:{{VALUE}}']]);
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
              {{WRAPPER}} .iw2-ph{background:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 12px,#d8c8b8 12px,#d8c8b8 24px) center/cover no-repeat}
              /* OVERLAY — image bg, text in normal flow at the bottom so expanding grows the card DOWN */
              {{WRAPPER}} .iw2-ov{position:relative;display:flex;flex-direction:column;justify-content:flex-end;min-height:340px;overflow:hidden;background:#6b4832 center/cover no-repeat;box-shadow:0 6px 18px rgba(60,40,25,.18)}
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
              /* OFFSET — card overlaps the photo */
              {{WRAPPER}} .iw2-of{display:flex;flex-direction:column}
              {{WRAPPER}} .iw2-of .iw2-ph{height:200px;border-radius:12px}
              {{WRAPPER}} .iw2-ofc{background:#faf9f7;--fade:#faf9f7;border:1px solid #D3BAA3;border-radius:12px;padding:18px 20px;margin:-46px 16px 0;position:relative;z-index:1;box-shadow:0 6px 16px rgba(60,40,25,.12);display:flex;flex-direction:column}
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
                'label' => 'Recolor icon',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => 'On: the icon takes the color below (best for single-color SVGs). Off: keep the SVG\'s own colors.',
            ]);
            $this->add_control('icon_color', [
                'label' => 'Icon color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#F1EAE4',
                'condition' => ['icon_recolor' => 'yes'],
                'selectors' => ['{{WRAPPER}} .qf-glyph' => 'background-color:{{VALUE}}'],
            ]);
            $this->add_responsive_control('icon_circle', [
                'label' => 'Circle size', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 30, 'max' => 90]], 'default' => ['size' => 46, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .qf-ic' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_responsive_control('icon_glyph', [
                'label' => 'Icon size', 'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => ['px' => ['min' => 12, 'max' => 50]], 'default' => ['size' => 22, 'unit' => 'px'],
                'selectors' => ['{{WRAPPER}} .qf-ic img,{{WRAPPER}} .qf-glyph' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}'],
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
              {{WRAPPER}} .qf-ic img{width:22px;height:22px;object-fit:contain}
              {{WRAPPER}} .qf-glyph{display:inline-block;width:22px;height:22px;background-color:#F1EAE4}
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
            $i = 0;
            foreach ($rows as $r) {
                $i++;
                [$title, $detail] = island_ew_split($r['value'] ?? '');
                $icon = island_ew_image_src($r['icon'] ?? '');
                $glyph = '';
                if ($icon) {
                    if ($s['icon_recolor'] === 'yes') {
                        $m = "url('" . esc_url($icon) . "') center/contain no-repeat";
                        $glyph = '<span class="qf-glyph" style="-webkit-mask:' . esc_attr($m) . ';mask:' . esc_attr($m) . '"></span>';
                    } else {
                        $glyph = '<img src="' . esc_url($icon) . '" alt="">';
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
                ],
                'description' => 'Auto = table when rows carry short Access/Key Wildlife/Notes columns (Santa Cruz), cards when rows carry long descriptions + buttons (Isabela, etc).',
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
            $this->add_control('card_radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 9, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vs-card' => 'border-radius:{{SIZE}}{{UNIT}}']]);
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
            $this->add_control('img_radius', ['label' => 'Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]],
                'default' => ['size' => 0, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .vs-ph' => 'border-radius:{{SIZE}}{{UNIT}}']]);
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

        private function render_table($rows, $s, $pid)
        {
            $title_tag = $this->tag($s['title_tag'], ['h2', 'h3', 'h4', 'h5', 'div'], 'h4');
            $pin = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s6-5 6-10a6 6 0 10-12 0c0 5 6 10 6 10z"/><circle cx="12" cy="11" r="2"/></svg>';
            $walk = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="13" cy="4" r="1.6"/><path d="M13 8l-3 4 2 2 1 5M13 12l3 2M10 12l-3 6"/></svg>';
            $paw = '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><circle cx="6" cy="11" r="2"/><circle cx="10" cy="7.5" r="2"/><circle cx="14" cy="7.5" r="2"/><circle cx="18" cy="11" r="2"/><path d="M8.5 14c-2 1.5-2 4 .5 4 1 0 1.8-.5 3-.5s2 .5 3 .5c2.5 0 2.5-2.5.5-4-1-.8-2.2-1.5-3.5-1.5S9.5 13.2 8.5 14z"/></svg>';
            $note = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>';

            $cols = ['minmax(190px,1.4fr)'];
            $head = ['<div class="vt-hc">' . $pin . ' Visitor Site</div>'];
            $show_access = $s['show_access'] === 'yes';
            $show_wild = $s['show_wildlife'] === 'yes';
            $show_notes = $s['show_desc'] === 'yes';
            if ($show_access) {
                $cols[] = '1fr';
                $head[] = '<div class="vt-hc">' . $walk . ' Access</div>';
            }
            if ($show_wild) {
                $cols[] = '1.3fr';
                $head[] = '<div class="vt-hc">' . $paw . ' Key Wildlife</div>';
            }
            if ($show_notes) {
                $cols[] = '1fr';
                $head[] = '<div class="vt-hc">' . $note . ' Notes</div>';
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
              {{WRAPPER}} .vt-head,{{WRAPPER}} .vt-row{grid-template-columns:' . $tpl . '}
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

            echo '<div class="vt-scroll"><div class="vt-wrap">';
            echo '<div class="vt-head">' . implode('', $head) . '</div>';
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
                echo '<div class="vt-row"><div class="vt-site">' . $thumb
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
            $layout = in_array($s['layout'] ?? 'auto', ['auto', 'table', 'cards'], true) ? $s['layout'] : 'auto';
            if ($layout === 'auto') {
                $layout = $this->is_tabular($rows) ? 'table' : 'cards';
            }
            if ($layout === 'table') {
                $this->render_table($rows, $s, $pid);
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
              {{WRAPPER}} .vs-grid{display:grid;gap:18px}
              {{WRAPPER}} .vs-card{background:#faf9f7;border:1px solid #D3BAA3;border-radius:9px;overflow:hidden;display:flex;flex-direction:column}
              {{WRAPPER}} .vs-ph{position:relative;height:150px;overflow:hidden;background:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 10px,#d8c8b8 10px,#d8c8b8 20px);display:flex;align-items:center;justify-content:center}
              {{WRAPPER}} .vs-img{width:100%;height:100%;object-fit:cover;display:block}
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

    $widgets_manager->register(new Island_Wildlife_Widget());
    $widgets_manager->register(new Island_QuickFacts_Widget());
    $widgets_manager->register(new Island_VisitorSites_Widget());
});
