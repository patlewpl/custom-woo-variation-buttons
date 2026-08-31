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
                'instance_id' => 'custom-wvb-' . wp_unique_id(),
                'attributes'  => $attribute_meta,
                'variations'  => $payload['variations'],
                'order'       => array_column( $attribute_meta, 'name' ),
                'quantity'    => max( 1, absint( $atts['quantity'] ) ),
                'button_text' => (string) $atts['button_text'],
                'config'      => self::build_config( (string) $atts['button_text'] ),
            )
        );
    }

    /**
     * Everything the script reads from the wrapper's data-config attribute.
     */
    private static function build_config( string $button_text ): array {
        return array(
            'ajaxUrl' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
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