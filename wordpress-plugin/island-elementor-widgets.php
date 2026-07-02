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
            return 'eicon-post-list';
        }

        public function get_categories()
        {
            return ['general'];
        }

        protected function register_controls()
        {
            /* ---------- CONTENT ---------- */
            $this->start_controls_section('content', [
                'label' => 'Content',
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]);
            $this->add_control('source_id', [
                'label'       => 'Page ID (blank = current)',
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'description' => 'Leave empty to read the island being viewed.',
            ]);
            $this->add_responsive_control('columns', [
                'label'          => 'Columns',
                'type'           => \Elementor\Controls_Manager::SELECT,
                'default'        => '2',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options'        => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'selectors'      => ['{{WRAPPER}} .iw-grid' => 'grid-template-columns:repeat({{VALUE}},1fr)'],
            ]);
            $this->add_control('show_sci', [
                'label' => 'Show scientific name', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
            ]);
            $this->add_control('show_meta', [
                'label' => 'Show where / season', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
            ]);
            $this->add_control('show_btn', [
                'label' => 'Show button', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes',
            ]);
            $this->end_controls_section();

            /* ---------- CARD STYLE ---------- */
            $this->start_controls_section('card_style', [
                'label' => 'Card',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]);
            $this->add_responsive_control('gap', [
                'label'      => 'Gap',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .iw-grid' => 'gap:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_control('card_bg', [
                'label'     => 'Background',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#faf9f7',
                'selectors' => ['{{WRAPPER}} .iw-card' => 'background:{{VALUE}}'],
            ]);
            $this->add_control('card_border', [
                'label'     => 'Border color',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#DBCEC4',
                'selectors' => ['{{WRAPPER}} .iw-card' => 'border-color:{{VALUE}}'],
            ]);
            $this->add_control('card_radius', [
                'label'      => 'Border radius',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 12, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .iw-card' => 'border-radius:{{SIZE}}{{UNIT}}'],
            ]);
            $this->add_responsive_control('card_pad', [
                'label'      => 'Body padding',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'default'    => ['top' => 20, 'right' => 22, 'bottom' => 20, 'left' => 22, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .iw-body' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}'],
            ]);
            $this->end_controls_section();

            /* ---------- IMAGE ---------- */
            $this->start_controls_section('img_style', [
                'label' => 'Image', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);
            $this->add_responsive_control('img_h', [
                'label'      => 'Height',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'range'      => ['px' => ['min' => 80, 'max' => 400]],
                'default'    => ['size' => 170, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .iw-img,{{WRAPPER}} .iw-ph' => 'height:{{SIZE}}{{UNIT}}'],
            ]);
            $this->end_controls_section();

            /* ---------- TITLE ---------- */
            $this->start_controls_section('title_style', [
                'label' => 'Title', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);
            $this->add_control('title_color', [
                'label'     => 'Color',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#64402c',
                'selectors' => ['{{WRAPPER}} .iw-title' => 'color:{{VALUE}}'],
            ]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name'     => 'title_typo',
                'selector' => '{{WRAPPER}} .iw-title',
            ]);
            $this->end_controls_section();

            /* ---------- TEXT ---------- */
            $this->start_controls_section('text_style', [
                'label' => 'Text', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);
            $this->add_control('text_color', [
                'label'     => 'Body color',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#202020',
                'selectors' => ['{{WRAPPER}} .iw-desc,{{WRAPPER}} .iw-meta' => 'color:{{VALUE}}'],
            ]);
            $this->end_controls_section();

            /* ---------- BUTTON ---------- */
            $this->start_controls_section('btn_style', [
                'label' => 'Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);
            $this->add_control('btn_color', [
                'label'     => 'Text color',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#64402c',
                'selectors' => ['{{WRAPPER}} .iw-btn' => 'color:{{VALUE}}'],
            ]);
            $this->add_control('btn_bg', [
                'label'     => 'Background',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => 'rgba(0,0,0,0)',
                'selectors' => ['{{WRAPPER}} .iw-btn' => 'background:{{VALUE}}'],
            ]);
            $this->add_control('btn_bd', [
                'label'     => 'Border color',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#D3BAA3',
                'selectors' => ['{{WRAPPER}} .iw-btn' => 'border-color:{{VALUE}}'],
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
            $rows = get_field('wildlife', $pid) ?: [];
            if (!$rows) {
                return;
            }
            echo '<style>
              {{WRAPPER}} .iw-grid{display:grid;gap:24px}
              {{WRAPPER}} .iw-card{border:1px solid #DBCEC4;border-radius:12px;overflow:hidden;display:flex;flex-direction:column}
              {{WRAPPER}} .iw-img,{{WRAPPER}} .iw-ph{width:100%;object-fit:cover;display:block}
              {{WRAPPER}} .iw-ph{background:repeating-linear-gradient(45deg,#e3d6c8,#e3d6c8 12px,#dccdbb 12px,#dccdbb 24px);display:flex;align-items:center;justify-content:center;color:#8a7058;font-size:13px}
              {{WRAPPER}} .iw-body{display:flex;flex-direction:column;gap:10px}
              {{WRAPPER}} .iw-title{margin:0;font-size:20px;font-style:italic}
              {{WRAPPER}} .iw-sci{display:block;font-style:italic;font-size:13px;color:#8a7058;font-weight:400}
              {{WRAPPER}} .iw-meta{list-style:none;padding:0;margin:4px 0 0;font-size:13.5px}
              {{WRAPPER}} .iw-btn{align-self:flex-start;text-decoration:none;padding:9px 16px;border-radius:6px;font-size:14px;font-weight:600;border:1px solid #D3BAA3}
            </style>';
            echo '<div class="iw-grid">';
            foreach ($rows as $w) {
                echo '<article class="iw-card">';
                $src = island_ew_image_src($w['image'] ?? '');
                if ($src) {
                    echo '<img class="iw-img" src="' . esc_url($src) . '" alt="' . esc_attr($w['common_name'] ?? '') . '">';
                } else {
                    echo '<div class="iw-ph"><span>Image</span></div>';
                }
                echo '<div class="iw-body">';
                $sci = ($s['show_sci'] === 'yes' && !empty($w['scientific_name']))
                    ? '<span class="iw-sci">' . esc_html($w['scientific_name']) . '</span>' : '';
                echo '<h3 class="iw-title">' . esc_html($w['common_name'] ?? '') . ' ' . $sci . '</h3>';
                echo '<div class="iw-desc">' . wp_kses_post($w['description'] ?? '') . '</div>';
                if ($s['show_meta'] === 'yes') {
                    $meta = '';
                    if (!empty($w['where_seen'])) {
                        $meta .= '<li><b>Where:</b> ' . esc_html($w['where_seen']) . '</li>';
                    }
                    if (!empty($w['best_season'])) {
                        $meta .= '<li><b>Season:</b> ' . esc_html($w['best_season']) . '</li>';
                    }
                    if ($meta) {
                        echo '<ul class="iw-meta">' . $meta . '</ul>';
                    }
                }
                if ($s['show_btn'] === 'yes' && !empty($w['button_url'])) {
                    echo '<a class="iw-btn" href="' . esc_url($w['button_url']) . '">'
                        . esc_html($w['button_label'] ?: 'Learn more') . ' &rarr;</a>';
                }
                echo '</div></article>';
            }
            echo '</div>';
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
            $this->add_responsive_control('columns', [
                'label' => 'Columns', 'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1',
                'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
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
            $this->add_control('h_head', ['label' => 'Group heading', 'type' => \Elementor\Controls_Manager::HEADING]);
            $this->add_control('head_color', ['label' => 'Heading color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vs-gh' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'head_typo', 'selector' => '{{WRAPPER}} .vs-gh']);
            $this->add_control('h_intro', ['label' => 'Intro', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('intro_color', ['label' => 'Intro color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#4a3a2c',
                'selectors' => ['{{WRAPPER}} .vs-intro' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'intro_typo', 'selector' => '{{WRAPPER}} .vs-intro']);
            $this->add_control('h_title', ['label' => 'Site title', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('title_color', ['label' => 'Title color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#64402C',
                'selectors' => ['{{WRAPPER}} .vs-title' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typo', 'selector' => '{{WRAPPER}} .vs-title']);
            $this->add_control('h_meta', ['label' => 'Meta (Access / Wildlife)', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('meta_color', ['label' => 'Meta color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#4a3a2c',
                'selectors' => ['{{WRAPPER}} .vs-meta' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'meta_typo', 'selector' => '{{WRAPPER}} .vs-meta']);
            $this->add_control('h_desc', ['label' => 'Description', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
            $this->add_control('desc_color', ['label' => 'Description color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#333333',
                'selectors' => ['{{WRAPPER}} .vs-desc' => 'color:{{VALUE}}']]);
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'desc_typo', 'selector' => '{{WRAPPER}} .vs-desc']);
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
