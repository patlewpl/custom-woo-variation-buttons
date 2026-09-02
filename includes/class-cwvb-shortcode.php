<?php
/**
 * The [woo_variations_buttons] shortcode.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Shortcode {

    public const TAG = 'woo_variations_buttons';

    /**
     * A fatal inside a shortcode takes down the whole page render, not just the
     * widget, so nothing from here is allowed to escape. Visitors get nothing,
     * editors get a hint, and the error still reaches the log.
     */
    public static function render( $atts ): string {
        $buffer_level = ob_get_level();

        try {
            return self::build( $atts );
        } catch ( Throwable $error ) {
            self::log_error( $error );

            if ( current_user_can( 'edit_posts' ) ) {
                return '<p>Nie udało się wyświetlić wariantów produktu. Szczegóły w logu błędów PHP.</p>';
            }

            return '';
        } finally {
            // A throw mid-template would otherwise leave the output buffer open
            // and swallow everything the theme prints after the shortcode.
            while ( ob_get_level() > $buffer_level ) {
                ob_end_clean();
            }
        }
    }

    private static function build( $atts ): string {
        // class_exists() alone can be true while WooCommerce is only half loaded.
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'product_id'       => 0,
                'select_attribute' => '',
                'quantity'         => 1,
                'button_text'      => 'Dodaj do koszyka',
                'show_quantity'    => 'yes',
                'price_prefix'     => '',
                'step_numbers'     => 'yes',
                'step_format'      => '{n}.',
                'benefits_title'   => '',
                'benefits'         => '',
                'benefits_marker'  => '✓',
                'benefits_note'    => '',
            ),
            $atts,
            self::TAG
        );

        $product_id = absint( $atts['product_id'] );

        if ( ! $product_id ) {
            return '<p>Brak ID produktu.</p>';
        }

        $product = wc_get_product( $product_id );

        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return '<p>Podany produkt nie jest produktem wariantowym.</p>';
        }

        $payload = CWVB_Product_Data::get( $product );

        if ( empty( $payload['attributes'] ) || empty( $payload['variations'] ) ) {
            return '<p>Brak dostępnych wariantów.</p>';
        }

        CWVB_Assets::enqueue();

        $select_attributes = CWVB_Attributes::resolve_selects(
            $payload['attributes'],
            (string) $atts['select_attribute']
        );

        $attribute_meta = CWVB_Attributes::build_meta( $payload, $select_attributes );

        return self::render_template(
            array(
                'instance_id'   => 'custom-wvb-' . wp_unique_id(),
                'attributes'    => $attribute_meta,
                'variations'    => $payload['variations'],
                'order'         => array_column( $attribute_meta, 'name' ),
                'quantity'      => max( 1, absint( $atts['quantity'] ) ),
                'button_text'   => (string) $atts['button_text'],
                'show_quantity' => self::is_on( $atts['show_quantity'] ),
                'price_prefix'  => trim( (string) $atts['price_prefix'] ),
                // Empty format = no numbering, so the template needs one check only.
                'step_format'   => self::step_format( $atts ),

                'benefits_title'  => trim( (string) $atts['benefits_title'] ),
                'benefits'        => self::benefits( $atts['benefits'] ),
                'benefits_marker' => (string) $atts['benefits_marker'],
                'benefits_note'   => trim( (string) $atts['benefits_note'] ),

                'config'        => self::build_config( $product_id, (string) $atts['button_text'] ),
            )
        );
    }

    /**
     * The benefit lines, whatever they arrived as.
     *
     * The Elementor widget passes its repeater rows (one array per row); the
     * shortcode has only strings to work with, so there it is one benefit per
     * line, or separated by a pipe. Blank lines are dropped, which is what makes
     * a trailing newline in the shortcode harmless.
     *
     * @param mixed $value
     */
    private static function benefits( $value ): array {
        if ( is_string( $value ) ) {
            $value = preg_split( '/\r\n|\r|\n|\|/', $value );
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $benefits = array();

        foreach ( $value as $benefit ) {
            if ( is_array( $benefit ) ) {
                $benefit = $benefit['text'] ?? '';
            }

            $benefit = trim( (string) $benefit );

            if ( '' !== $benefit ) {
                $benefits[] = $benefit;
            }
        }

        return $benefits;
    }

    /**
     * '' when the attributes are not numbered. A blank format with numbering on
     * would otherwise turn the numbers off by accident.
     */
    private static function step_format( array $atts ): string {
        if ( ! self::is_on( $atts['step_numbers'] ) ) {
            return '';
        }

        $format = (string) $atts['step_format'];

        return '' !== trim( $format ) ? $format : '{n}.';
    }

    /**
     * Shortcode booleans arrive as strings ("yes", "1", "true"), Elementor
     * switchers as "yes" or "", and a filter could hand over a real bool.
     */
    private static function is_on( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }

        return function_exists( 'wc_string_to_bool' )
            ? wc_string_to_bool( $value )
            : in_array( strtolower( trim( (string) $value ) ), array( 'yes', '1', 'true', 'on' ), true );
    }

    /**
     * Everything the script reads from the wrapper's data-config attribute.
     */
    private static function build_config( int $product_id, string $button_text ): array {
        return array(
            // The parent product; the variation travels separately, together
            // with the attribute values the customer chose.
            'productId' => $product_id,
            'ajaxUrl'   => CWVB_Cart::endpoint(),
            'i18n'    => array(
                'selectAll'   => 'Wybierz wszystkie opcje produktu.',
                'unavailable' => 'Wybrana kombinacja jest niedostępna.',
                'outOfStock'  => 'Ten wariant jest obecnie niedostępny (brak w magazynie).',
                'adding'      => 'Dodawanie...',
                'added'       => 'Dodano do koszyka',
                'addToCart'   => $button_text,
                'error'       => 'Nie udało się dodać tego wariantu do koszyka. Spróbuj ponownie.',
            ),
        );
    }

    /**
     * Themes can override the markup by dropping their own copy at
     * yourtheme/custom-wvb/variation-buttons.php.
     *
     * The template reads everything from a single $cwvb array.
     */
    private static function render_template( array $cwvb ): string {
        $template = locate_template( array( 'custom-wvb/variation-buttons.php' ) );

        if ( ! $template ) {
            $template = CWVB_PATH . 'templates/variation-buttons.php';
        }

        $template = (string) apply_filters( 'custom_wvb_template', $template, $cwvb );

        if ( ! $template || ! is_readable( $template ) ) {
            return '';
        }

        ob_start();
        include $template;

        return (string) ob_get_clean();
    }

    private static function log_error( Throwable $error ): void {
        $message = sprintf(
            '[%s] %s in %s:%d',
            self::TAG,
            $error->getMessage(),
            $error->getFile(),
            $error->getLine()
        );

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->error( $message, array( 'source' => CWVB_Assets::HANDLE ) );
            return;
        }

        error_log( $message );
    }
}