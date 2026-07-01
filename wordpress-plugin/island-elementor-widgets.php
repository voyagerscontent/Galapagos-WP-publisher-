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

    $widgets_manager->register(new Island_Wildlife_Widget());
});
