<?php
/**
 * Plugin Name: Island Related Links (SEO footer)
 * Description: Standalone Elementor widget that renders ONLY the ACF
 *              "related_links" repeater as a grouped "Explore More" SEO footer —
 *              ornament (icon + rules), italic heading, intro line, one titled
 *              column per link "group", and an optional "Need Help?" contact
 *              card. It intentionally ignores the "sources" field even though
 *              both share the same ACF tab.
 * Version:     0.2.0
 * Author:      Galápagos Islands Travel
 *
 * Install like any plugin (Plugins → Add New → Upload → Activate). Requires
 * Elementor + ACF. In Elementor the widget "Island Related Links" appears under
 * the "General" category. Defaults are tuned for a dark (brown) footer section;
 * every color/size is a control. Safe to run alongside the main widgets plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('elementor/widgets/register', function ($widgets_manager) {
    if (!did_action('elementor/loaded')) {
        return;
    }

    // Declare once — Elementor fires this hook multiple times (editor + preview);
    // re-declaring a class is a fatal error.
    if (!class_exists('Island_RelatedLinks_Widget')) {

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
                /* ─────────────── CONTENT ─────────────── */
                $this->start_controls_section('c', ['label' => 'Content', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
                $this->add_control('source_id', ['label' => 'Page ID (blank = current)', 'type' => \Elementor\Controls_Manager::NUMBER]);
                $this->add_control('hide_on_ids', ['label' => 'Hide on these page IDs', 'type' => \Elementor\Controls_Manager::TEXT,
                    'description' => 'Comma-separated. Leave blank to always show.']);
                $this->add_responsive_control('columns', ['label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'mobile_default' => '1',
                    'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                    'selectors' => ['{{WRAPPER}} .irl-cols' => 'grid-template-columns:repeat({{VALUE}},1fr)']]);
                $this->end_controls_section();

                /* ─────────────── HEADER (ornament + heading + intro) ─────────────── */
                $this->start_controls_section('hd', ['label' => 'Header', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT]);
                $this->add_control('show_ornament', ['label' => 'Show top ornament', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
                $this->add_control('ornament_icon', ['label' => 'Ornament icon', 'type' => \Elementor\Controls_Manager::ICONS,
                    'default' => ['value' => 'far fa-bell', 'library' => 'fa-regular'],
                    'condition' => ['show_ornament' => 'yes']]);
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

                /* ─────────────── CONTACT / "Need Help?" card ─────────────── */
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
                $this->add_control('contact_btn_url', ['label' => 'Button URL', 'type' => \Elementor\Controls_Manager::URL, 'default' => ['url' => '/contact/'],
                    'condition' => ['show_contact' => 'yes']]);
                $this->end_controls_section();

                /* ─────────────── STYLE: layout ─────────────── */
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

                /* ─────────────── STYLE: ornament & header ─────────────── */
                $this->start_controls_section('sh', ['label' => 'Ornament & header', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
                $this->add_control('orn_color', ['label' => 'Ornament color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f3ead9',
                    'selectors' => ['{{WRAPPER}} .irl-orn' => 'color:{{VALUE}}']]);
                $this->add_control('orn_size', ['label' => 'Ornament icon size', 'type' => \Elementor\Controls_Manager::SLIDER,
                    'range' => ['px' => ['min' => 16, 'max' => 64]], 'default' => ['size' => 30, 'unit' => 'px'],
                    'selectors' => ['{{WRAPPER}} .irl-orn-i' => 'font-size:{{SIZE}}{{UNIT}}', '{{WRAPPER}} .irl-orn-i svg' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}']]);
                $this->add_control('orn_line', ['label' => 'Ornament line color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => 'rgba(243,234,217,.55)',
                    'selectors' => ['{{WRAPPER}} .irl-line' => 'background:{{VALUE}}']]);
                $this->add_control('h_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7efe1', 'separator' => 'before',
                    'selectors' => ['{{WRAPPER}} .irl-h' => 'color:{{VALUE}}']]);
                $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'h_typo', 'selector' => '{{WRAPPER}} .irl-h']);
                $this->add_control('intro_color', ['label' => 'Intro color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#d9cebc', 'separator' => 'before',
                    'selectors' => ['{{WRAPPER}} .irl-intro' => 'color:{{VALUE}}']]);
                $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'intro_typo', 'selector' => '{{WRAPPER}} .irl-intro']);
                $this->end_controls_section();

                /* ─────────────── STYLE: groups & links ─────────────── */
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

                /* ─────────────── STYLE: contact card ─────────────── */
                $this->start_controls_section('sc', ['label' => 'Contact card style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                    'condition' => ['show_contact' => 'yes']]);
                $this->add_control('ct_icon_color', ['label' => 'Icon color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f3ead9',
                    'selectors' => ['{{WRAPPER}} .irl-ct-i' => 'color:{{VALUE}}']]);
                $this->add_control('ct_title_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7efe1',
                    'selectors' => ['{{WRAPPER}} .irl-ct-h' => 'color:{{VALUE}}']]);
                $this->add_control('ct_text_color', ['label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#d9cebc',
                    'selectors' => ['{{WRAPPER}} .irl-ct-t' => 'color:{{VALUE}}']]);
                $this->add_control('ct_btn_color', ['label' => 'Button color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7efe1',
                    'selectors' => ['{{WRAPPER}} .irl-ct-btn' => 'color:{{VALUE}}']]);
                $this->add_control('ct_btn_hover', ['label' => 'Button hover color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#c9a97e',
                    'selectors' => ['{{WRAPPER}} .irl-ct-btn:hover' => 'color:{{VALUE}}']]);
                $this->end_controls_section();
            }

            /** True when at least one row carries a non-empty "group" heading. */
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
                $url   = trim((string) ($r['url'] ?? ''));
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

            /** Render the link columns (grouped when any row has a "group"). */
            private function columns($rows, $s)
            {
                if ($this->has_groups($rows)) {
                    $groups = [];
                    $order  = [];
                    foreach ($rows as $r) {
                        $g = trim((string) ($r['group'] ?? ''));
                        if (!isset($groups[$g])) {
                            $groups[$g] = [];
                            $order[]    = $g;
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
                    // No groups: a single column of links.
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
                $link = $s['contact_btn_url'] ?? [];
                $href = trim((string) ($link['url'] ?? ''));
                $target = !empty($link['is_external']) ? ' target="_blank"' : '';
                $nofollow = !empty($link['nofollow']) ? ' rel="nofollow noopener"' : '';

                $icon = '';
                if (!empty($s['contact_icon']['value'])) {
                    ob_start();
                    \Elementor\Icons_Manager::render_icon($s['contact_icon'], ['aria-hidden' => 'true']);
                    $icon = ob_get_clean();
                }
                echo '<div class="irl-group irl-contact">';
                if ($icon) {
                    echo '<span class="irl-ct-i">' . $icon . '</span>';
                }
                if (trim((string) ($s['contact_title'] ?? '')) !== '') {
                    echo '<h4 class="irl-ct-h">' . esc_html($s['contact_title']) . '</h4>';
                }
                if (trim((string) ($s['contact_text'] ?? '')) !== '') {
                    echo '<p class="irl-ct-t">' . esc_html($s['contact_text']) . '</p>';
                }
                if (trim((string) ($s['contact_btn_label'] ?? '')) !== '' && $href !== '') {
                    echo '<a class="irl-ct-btn" href="' . esc_url($href) . '"' . $target . $nofollow . '>'
                        . esc_html($s['contact_btn_label']) . ' <span class="irl-ct-arw">&rarr;</span></a>';
                }
                echo '</div>';
            }

            protected function render()
            {
                if (!function_exists('get_field')) {
                    return;
                }
                $s   = $this->get_settings_for_display();
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
                $ul_base  = ($ul === 'always') ? 'underline' : 'none';
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
                  {{WRAPPER}} .irl-contact .irl-ct-i{display:inline-flex;font-size:34px;color:#f3ead9;margin-bottom:14px}
                  {{WRAPPER}} .irl-contact .irl-ct-i svg{width:34px;height:34px;fill:currentColor}
                  {{WRAPPER}} .irl-ct-h{position:relative;margin:0 0 22px;padding-bottom:12px;font-family:Merriweather,Georgia,serif;font-style:italic;font-weight:400;font-size:22px;color:#f7efe1}
                  {{WRAPPER}} .irl-ct-h::after{content:"";position:absolute;left:0;bottom:0;width:64px;height:2px;background:rgba(201,169,126,.7)}
                  {{WRAPPER}} .irl-ct-t{margin:0 0 18px;font-size:15px;line-height:1.6;color:#d9cebc}
                  {{WRAPPER}} .irl-ct-btn{display:inline-flex;align-items:center;gap:8px;font-weight:600;font-size:15px;color:#f7efe1;text-decoration:none;transition:color .18s ease}
                  {{WRAPPER}} .irl-ct-btn:hover{color:#c9a97e}
                  @media(max-width:900px){{{WRAPPER}} .irl-cols{grid-template-columns:1fr 1fr}}
                  @media(max-width:600px){{{WRAPPER}} .irl-cols{grid-template-columns:1fr!important}{{WRAPPER}} .irl-line{max-width:90px}}
                </style>';

                echo '<div class="irl-wrap"><div class="irl-inner">';

                // Header: ornament + heading + intro.
                $has_orn   = (($s['show_ornament'] ?? '') === 'yes') && !empty($s['ornament_icon']['value']);
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

                // Columns: link groups + optional contact card.
                echo '<div class="irl-cols">';
                if ($related) {
                    $this->columns($related, $s);
                }
                $this->contact_card($s);
                echo '</div>';

                echo '</div></div>';
            }
        }

    } // end class guard

    $widgets_manager->register(new Island_RelatedLinks_Widget());
});
