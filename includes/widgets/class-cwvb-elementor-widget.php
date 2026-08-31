<?php
/**
 * Elementor widget wrapping the same renderer the shortcode uses.
 *
 * Style controls mostly write to the CSS custom properties declared in
 * assets/variations-buttons.css, so the panel and the stylesheet stay in sync
 * instead of duplicating rules.
 *
 * Only loaded from CWVB_Elementor::register_widget(), i.e. never without
 * Elementor in memory.
 */

defined( 'ABSPATH' ) || exit;

class CWVB_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'cwvb-variation-buttons';
    }

    public function get_title() {
        return 'Warianty produktu (przyciski)';
    }

    public function get_icon() {
        return 'eicon-product-add-to-cart';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'woocommerce', 'wariant', 'variation', 'produkt', 'koszyk' );
    }

    public function get_style_depends() {
        return array( CWVB_Assets::HANDLE );
    }

    public function get_script_depends() {
        return array( CWVB_Assets::HANDLE );
    }

    /**
     * Variable products for the picker. Runs in the editor only - a shop with
     * thousands of products must not pay for this on every front-end render.
     */
    private function get_product_options(): array {
        if ( ! CWVB_Elementor::is_editor_request() || ! function_exists( 'wc_get_products' ) ) {
            return array();
        }

        $products = wc_get_products(
            array(
                'type'    => 'variable',
                'status'  => 'publish',
                'limit'   => 200,
                'orderby' => 'title',
                'order'   => 'ASC',
            )
        );

        $options = array();

        foreach ( $products as $product ) {
            $options[ (string) $product->get_id() ] = sprintf(
                '%s (#%d)',
                $product->get_name(),
                $product->get_id()
            );
        }

        return $options;
    }

    protected function register_controls() {
        $this->register_content_controls();
        $this->register_layout_controls();
        $this->register_label_controls();
        $this->register_option_controls();
        $this->register_select_controls();
        $this->register_price_controls();
        $this->register_message_controls();
        $this->register_quantity_controls();
        $this->register_cart_controls();
    }

    private function register_content_controls(): void {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => 'Produkt',
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'product_id',
            array(
                'label'       => 'Produkt wariantowy',
                'type'        => \Elementor\Controls_Manager::SELECT2,
                'options'     => $this->get_product_options(),
                'label_block' => true,
                'description' => 'Lista pokazuje do 200 opublikowanych produktów wariantowych.',
            )
        );

        $this->add_control(
            'select_attribute',
            array(
                'label'       => 'Atrybuty jako lista rozwijana',
                'type'        => \Elementor\Controls_Manager::TEXT,
                'label_block' => true,
                'placeholder' => 'pa_egzamin,pa_termin',
                'description' => 'Slugi atrybutów po przecinku. Pozostałe będą przyciskami. Puste = pierwszy atrybut produktu.',
            )
        );

        $this->add_control(
            'button_text',
            array(
                'label'   => 'Tekst przycisku',
                'type'    => \Elementor\Controls_Manager::TEXT,
                'default' => 'Dodaj do koszyka',
            )
        );

        $this->add_control(
            'quantity',
            array(
                'label'   => 'Ilość początkowa',
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'min'     => 1,
                'step'    => 1,
                'default' => 1,
            )
        );

        $this->end_controls_section();
    }

    private function register_layout_controls(): void {
        $this->start_controls_section(
            'section_layout',
            array(
                'label' => 'Układ',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'attribute_spacing',
            array(
                'label'      => 'Odstęp między atrybutami',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', 'em' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-attribute-spacing: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'option_gap',
            array(
                'label'      => 'Odstęp między przyciskami',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', 'em' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'radius',
            array(
                'label'      => 'Zaokrąglenie',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', '%' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'border_width',
            array(
                'label'      => 'Grubość obramowania',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 8 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-border-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    private function register_label_controls(): void {
        $this->start_controls_section(
            'section_labels',
            array(
                'label' => 'Etykiety atrybutów',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'label_typography',
                'selector' => '{{WRAPPER}} .custom-wvb__label',
            )
        );

        $this->add_control(
            'label_color',
            array(
                'label'     => 'Kolor',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'label_spacing',
            array(
                'label'      => 'Odstęp pod etykietą',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', 'em' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-label-spacing: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    private function register_option_controls(): void {
        $this->start_controls_section(
            'section_options',
            array(
                'label' => 'Przyciski opcji',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'option_typography',
                'selector' => '{{WRAPPER}} .custom-wvb__option',
            )
        );

        $this->add_responsive_control(
            'option_padding',
            array(
                'label'      => 'Wewnętrzne odstępy',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em' ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb__option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->start_controls_tabs( 'option_tabs' );

        $this->start_controls_tab( 'option_tab_normal', array( 'label' => 'Normalny' ) );

        $this->add_control(
            'option_color',
            array(
                'label'     => 'Tekst',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'option_background',
            array(
                'label'     => 'Tło',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'option_border_color',
            array(
                'label'     => 'Obramowanie',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab( 'option_tab_hover', array( 'label' => 'Hover' ) );

        $this->add_control(
            'option_color_hover',
            array(
                'label'     => 'Tekst',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option:hover:not(:disabled)' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'option_background_hover',
            array(
                'label'     => 'Tło',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option:hover:not(:disabled)' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'option_border_color_hover',
            array(
                'label'     => 'Obramowanie',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option:hover:not(:disabled)' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab( 'option_tab_selected', array( 'label' => 'Wybrany' ) );

        $this->add_control(
            'option_color_selected',
            array(
                'label'     => 'Tekst',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option.is-selected' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'option_background_selected',
            array(
                'label'     => 'Tło',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option.is-selected' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'option_border_color_selected',
            array(
                'label'     => 'Obramowanie',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__option.is-selected' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab( 'option_tab_disabled', array( 'label' => 'Niedostępny' ) );

        $this->add_control(
            'option_disabled_opacity',
            array(
                'label'     => 'Przezroczystość',
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-disabled-opacity: {{SIZE}};',
                ),
            )
        );

        $this->add_control(
            'option_disabled_note',
            array(
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => 'Dotyczy kombinacji, których nie da się wybrać przy obecnym zaznaczeniu.',
                'content_classes' => 'elementor-descriptor',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function register_select_controls(): void {
        $this->start_controls_section(
            'section_select',
            array(
                'label' => 'Lista rozwijana',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'select_typography',
                'selector' => '{{WRAPPER}} .custom-wvb__select',
            )
        );

        $this->add_control(
            'select_color',
            array(
                'label'     => 'Tekst',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'select_background',
            array(
                'label'     => 'Tło',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'select_border_color',
            array(
                'label'     => 'Obramowanie',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'select_height',
            array(
                'label'      => 'Wysokość',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array( 'px' => array( 'min' => 30, 'max' => 90 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-field-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'select_max_width',
            array(
                'label'      => 'Maksymalna szerokość',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', '%' ),
                'range'      => array(
                    'px' => array( 'min' => 120, 'max' => 900 ),
                    '%'  => array( 'min' => 10, 'max' => 100 ),
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb__select' => 'max-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    private function register_price_controls(): void {
        $this->start_controls_section(
            'section_price',
            array(
                'label' => 'Cena',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'price_typography',
                'selector' => '{{WRAPPER}} .custom-wvb__price',
            )
        );

        $this->add_control(
            'price_color',
            array(
                'label'     => 'Kolor',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__price' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .custom-wvb__price .woocommerce-Price-amount' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    private function register_message_controls(): void {
        $this->start_controls_section(
            'section_message',
            array(
                'label' => 'Komunikaty',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'message_typography',
                'selector' => '{{WRAPPER}} .custom-wvb__message',
            )
        );

        $this->add_control(
            'message_color',
            array(
                'label'     => 'Kolor',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__message' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    private function register_quantity_controls(): void {
        $this->start_controls_section(
            'section_quantity',
            array(
                'label' => 'Ilość',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'quantity_typography',
                'selector' => '{{WRAPPER}} .custom-wvb__quantity',
            )
        );

        $this->add_control(
            'quantity_color',
            array(
                'label'     => 'Kolor tekstu',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__quantity' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .custom-wvb__qty'      => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'quantity_background',
            array(
                'label'     => 'Tło pola',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__qty' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'quantity_border_color',
            array(
                'label'     => 'Obramowanie pola',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__qty' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'quantity_width',
            array(
                'label'      => 'Szerokość pola',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array( 'px' => array( 'min' => 50, 'max' => 200 ) ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb__qty' => 'width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    private function register_cart_controls(): void {
        $this->start_controls_section(
            'section_cart',
            array(
                'label' => 'Przycisk koszyka',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'cart_typography',
                'selector' => '{{WRAPPER}} .custom-wvb__add',
            )
        );

        $this->add_responsive_control(
            'cart_padding',
            array(
                'label'      => 'Wewnętrzne odstępy',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em' ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb__add' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'cart_full_width',
            array(
                'label'     => 'Pełna szerokość',
                'type'      => \Elementor\Controls_Manager::SWITCHER,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add'      => 'width: 100%;',
                    '{{WRAPPER}} .custom-wvb__purchase' => 'align-items: stretch; flex-direction: column;',
                ),
            )
        );

        $this->start_controls_tabs( 'cart_tabs' );

        $this->start_controls_tab( 'cart_tab_normal', array( 'label' => 'Normalny' ) );

        $this->add_control(
            'cart_color',
            array(
                'label'     => 'Tekst',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cart_background',
            array(
                'label'     => 'Tło',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cart_border_color',
            array(
                'label'     => 'Obramowanie',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab( 'cart_tab_hover', array( 'label' => 'Hover' ) );

        $this->add_control(
            'cart_color_hover',
            array(
                'label'     => 'Tekst',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add:hover:not(:disabled)' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cart_background_hover',
            array(
                'label'     => 'Tło',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add:hover:not(:disabled)' => 'background: {{VALUE}}; opacity: 1;',
                ),
            )
        );

        $this->add_control(
            'cart_border_color_hover',
            array(
                'label'     => 'Obramowanie',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add:hover:not(:disabled)' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab( 'cart_tab_disabled', array( 'label' => 'Nieaktywny' ) );

        $this->add_control(
            'cart_opacity_disabled',
            array(
                'label'     => 'Przezroczystość',
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__add:disabled' => 'opacity: {{SIZE}};',
                ),
            )
        );

        $this->add_control(
            'cart_disabled_note',
            array(
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => 'Przycisk jest nieaktywny, dopóki nie wybrano kompletnego, dostępnego wariantu.',
                'content_classes' => 'elementor-descriptor',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function render() {
        if ( ! class_exists( 'CWVB_Shortcode' ) ) {
            return;
        }

        $settings = $this->get_settings_for_display();

        $html = CWVB_Shortcode::render(
            array(
                'product_id'       => $settings['product_id'] ?? 0,
                'select_attribute' => $settings['select_attribute'] ?? '',
                'quantity'         => $settings['quantity'] ?? 1,
                'button_text'      => $settings['button_text'] ?? 'Dodaj do koszyka',
            )
        );

        if ( '' === trim( $html ) && CWVB_Elementor::is_editor_request() ) {
            echo '<p>Wybierz produkt wariantowy w ustawieniach widgetu.</p>';
            return;
        }

        // Already escaped by the renderer and its template.
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}