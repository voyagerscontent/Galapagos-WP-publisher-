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

    $widgets_manager->register(new Island_Wildlife_Widget());
    $widgets_manager->register(new Island_QuickFacts_Widget());
});
