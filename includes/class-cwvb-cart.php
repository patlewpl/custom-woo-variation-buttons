<?php
/**
 * Adding a variation to the cart.
 *
 * WooCommerce's own endpoint (WC_AJAX::add_to_cart) reads product_id and
 * quantity and nothing else: hand it a variation ID and it rebuilds the
 * attributes with WC_Product_Variation::get_variation_attributes(). For an
 * attribute the variation leaves as "Any" that returns an empty string, and
 * WC_Cart::add_to_cart then refuses the whole thing with "<attribute> is a
 * required field" - the customer's choice had no way to travel with the
 * request.
 *
 * So this endpoint exists to pass the parent product, the variation and the
 * values the customer actually picked, which is what WC_Cart wants.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Cart {

    public const ACTION = 'cwvb_add_to_cart';

    /**
     * wc_ajax_{action} covers guests and logged-in customers alike - unlike
     * admin-ajax there is no separate nopriv hook - and skips loading wp-admin.
     */
    public static function init(): void {
        add_action( 'wc_ajax_' . self::ACTION, array( __CLASS__, 'add_to_cart' ) );
    }

    /**
     * The URL the script posts to, or '' when WooCommerce is not loaded.
     */
    public static function endpoint(): string {
        return class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( self::ACTION ) : '';
    }

    /**
     * No nonce, deliberately: the widget markup is part of a page that can be
     * served from a full-page cache, and a cached nonce is a broken nonce.
     * WooCommerce's own add-to-cart endpoint omits it for the same reason, and
     * the action only ever writes to the caller's own cart.
     */
    public static function add_to_cart(): void {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            self::fail( 'Koszyk jest niedostępny.' );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
        $quantity     = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;
        $attributes   = isset( $_POST['attributes'] ) ? (array) wp_unslash( $_POST['attributes'] ) : array();
        // phpcs:enable

        if ( $quantity <= 0 ) {
            $quantity = 1;
        }

        $variation = $variation_id ? wc_get_product( $variation_id ) : null;

        // A variation ID that belongs to a different product would otherwise let
        // a caller add anything to the cart at this product's price.
        if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product_id ) {
            self::fail( 'Wybrany wariant jest nieprawidłowy.' );
        }

        $added = WC()->cart->add_to_cart(
            $product_id,
            $quantity,
            $variation_id,
            self::clean_attributes( $attributes )
        );

        if ( false === $added ) {
            // WC_Cart catches its own exceptions and leaves the reason in the
            // notices, which is a far better message than anything generic.
            self::fail( self::take_error_notice() );
        }

        do_action( 'woocommerce_ajax_added_to_cart', $product_id );

        wp_send_json(
            array(
                'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
                'cart_hash' => WC()->cart->get_cart_hash(),
            )
        );
    }

    /**
     * Keys the cart understands, values it can compare.
     *
     * Only the attribute_* keys are kept. The values are not validated here on
     * purpose: WC_Cart checks each one against the parent product's own list and
     * reports a better error than this could.
     */
    private static function clean_attributes( array $attributes ): array {
        $clean = array();

        foreach ( $attributes as $key => $value ) {
            $key = (string) $key;

            if ( 0 !== strpos( $key, 'attribute_' ) ) {
                continue;
            }

            $clean[ sanitize_text_field( $key ) ] = is_scalar( $value ) ? wc_clean( (string) $value ) : '';
        }

        return $clean;
    }

    /**
     * The first error WooCommerce recorded, cleared so it cannot resurface on
     * the next page the customer loads.
     */
    private static function take_error_notice(): string {
        if ( ! function_exists( 'wc_get_notices' ) ) {
            return '';
        }

        $notices = wc_get_notices( 'error' );

        if ( function_exists( 'wc_clear_notices' ) ) {
            wc_clear_notices( 'error' );
        }

        foreach ( $notices as $notice ) {
            // WooCommerce 3.9+ stores an array per notice, older versions a string.
            $message = is_array( $notice ) ? ( $notice['notice'] ?? '' ) : $notice;
            $message = trim( wp_strip_all_tags( (string) $message ) );

            if ( '' !== $message ) {
                return $message;
            }
        }

        return '';
    }

    /**
     * @param string $message Shown to the customer; '' lets the script fall back
     *                        to its own wording.
     */
    private static function fail( string $message ): void {
        wp_send_json(
            array(
                'error'   => true,
                'message' => $message,
            )
        );
    }
}