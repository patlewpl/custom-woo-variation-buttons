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
     *
     * Elementor builds the control stack of every registered widget on each
     * editor load, so this runs even when nobody places this widget. Asking for
     * IDs and priming the post cache once keeps it to two queries; the default
     * return would build 200 WC_Product_Variable objects to read 200 names.
     */
    private function get_product_options(): array {
        if ( ! CWVB_Elementor::is_editor_request() || ! function_exists( 'wc_get_products' ) ) {
            return array();
        }

        $product_ids = wc_get_products(
            array(
                'type'    => 'variable',
                'status'  => 'publish',
                'limit'   => 200,
                'orderby' => 'title',
                'order'   => 'ASC',
                'return'  => 'ids',
            )
        );

        if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
            return array();
        }

        // Titles only: no term or meta cache needed for a dropdown label.
        _prime_post_caches( $product_ids, false, false );

        $options = array();

        foreach ( $product_ids as $product_id ) {
            $product_id = (int) $product_id;

            $options[ (string) $product_id ] = sprintf(
                '%s (#%d)',
                get_the_title( $product_id ),
                $product_id
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
            'price_prefix',
            array(
                'label'       => 'Tekst przed ceną',
                'type'        => \Elementor\Controls_Manager::TEXT,
                'label_block' => true,
                'placeholder' => 'Cena:',
                'description' => 'Pokazuje się po lewej stronie ceny, dopiero po wybraniu wariantu.',
            )
        );

        $this->add_control(
            'step_numbers',
            array(
                'label'       => 'Numeruj atrybuty',
                'type'        => \Elementor\Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'description' => 'Dodaje numer kroku przed etykietą każdego atrybutu, licząc od 1.',
            )
        );

        $this->add_control(
            'step_format',
            array(
                'label'       => 'Format numeru',
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '{n}.',
                'placeholder' => '{n}.',
                'description' => '{n} zostanie zastąpione numerem, np. "Krok {n}:".',
                'condition'   => array( 'step_numbers' => 'yes' ),
            )
        );

        $this->add_control(
            'show_quantity',
            array(
                'label'       => 'Pole ilości',
                'type'        => \Elementor\Controls_Manager::SWITCHER,
                'label_on'    => 'Pokaż',
                'label_off'   => 'Ukryj',
                'default'     => 'yes',
                'separator'   => 'before',
                'description' => 'Po ukryciu do koszyka trafia ilość początkowa.',
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

        $this->start_controls_tabs( 'select_tabs' );

        $this->start_controls_tab( 'select_tab_normal', array( 'label' => 'Normalny' ) );

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

        $this->end_controls_tab();

        /*
         * The field after a choice was made. The script adds .is-selected, so
         * this styles the closed select, which is the part every browser renders
         * the same way.
         */
        $this->start_controls_tab( 'select_tab_selected', array( 'label' => 'Wybrany' ) );

        $this->add_control(
            'select_color_selected',
            array(
                'label'     => 'Tekst',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select.is-selected' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'select_background_selected',
            array(
                'label'     => 'Tło',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select.is-selected' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'select_border_color_selected',
            array(
                'label'     => 'Obramowanie',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select.is-selected' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab( 'select_tab_list', array( 'label' => 'Lista' ) );

        $this->add_control(
            'select_option_color_selected',
            array(
                'label'     => 'Tekst zaznaczonej pozycji',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select option:checked' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'select_option_background_selected',
            array(
                'label'     => 'Tło zaznaczonej pozycji',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__select option:checked' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'select_list_note',
            array(
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => 'Rozwiniętą listę rysuje system operacyjny. Na macOS, iOS i w Safari te kolory zostaną zignorowane — działają w Chrome/Edge i Firefox na Windows. Stan wybrany ustaw w zakładce "Wybrany", bo ta działa wszędzie.',
                'content_classes' => 'elementor-descriptor',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

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

        $this->add_control(
            'price_prefix_heading',
            array(
                'label'     => 'Tekst przed ceną',
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => array( 'price_prefix!' => '' ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'      => 'price_prefix_typography',
                'selector'  => '{{WRAPPER}} .custom-wvb__price-prefix',
                'condition' => array( 'price_prefix!' => '' ),
            )
        );

        $this->add_control(
            'price_prefix_color',
            array(
                'label'     => 'Kolor',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'condition' => array( 'price_prefix!' => '' ),
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__price-prefix' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'price_prefix_gap',
            array(
                'label'      => 'Odstęp od ceny',
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', 'em' ),
                'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
                'condition'  => array( 'price_prefix!' => '' ),
                'selectors'  => array(
                    '{{WRAPPER}} .custom-wvb' => '--cwvb-price-gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'price_prefix_stack',
            array(
                'label'     => 'Tekst nad ceną',
                'type'      => \Elementor\Controls_Manager::SWITCHER,
                'condition' => array( 'price_prefix!' => '' ),
                'selectors' => array(
                    '{{WRAPPER}} .custom-wvb__price-row' => 'flex-direction: column; align-items: flex-start;',
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
                'label'     => 'Ilość',
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
                // Nothing to style when the field is not rendered.
                'condition' => array( 'show_quantity' => 'yes' ),
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
                'show_quantity'    => $settings['show_quantity'] ?? 'yes',
                'price_prefix'     => $settings['price_prefix'] ?? '',
                'step_numbers'     => $settings['step_numbers'] ?? 'yes',
                // A cleared format field falls back to "{n}." in the renderer.
                'step_format'      => $settings['step_format'] ?? '{n}.',
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