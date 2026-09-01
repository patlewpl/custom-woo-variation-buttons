<?php
/**
 * Reading a variable product once, and caching the result.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Product_Data {

    /**
     * Two widgets for the same product on one page, or a shortcode next to a
     * widget, would otherwise repeat the whole lookup. Safe even when caching is
     * filtered off: currency, tax display and stock cannot change mid-request.
     *
     * @var array<int, array>
     */
    private static $memo = array();

    /**
     * get_available_variations() loads every variation object, which is by far
     * the most expensive call in this plugin. Do it once per product state.
     *
     * Return 0 from the custom_wvb_cache_ttl filter to disable caching.
     */
    public static function get( WC_Product $product ): array {
        $product_id = $product->get_id();

        if ( isset( self::$memo[ $product_id ] ) ) {
            return self::$memo[ $product_id ];
        }

        $ttl = (int) apply_filters( 'custom_wvb_cache_ttl', DAY_IN_SECONDS, $product );
        $key = self::cache_key( $product );

        if ( $ttl > 0 ) {
            $cached = get_transient( $key );

            if ( self::is_valid_payload( $cached ) ) {
                self::$memo[ $product_id ] = $cached;

                return $cached;
            }
        }

        $payload = self::build( $product );

        if ( $ttl > 0 && ! empty( $payload['variations'] ) ) {
            set_transient( $key, $payload, $ttl );
        }

        self::$memo[ $product_id ] = $payload;

        return $payload;
    }

    /**
     * A shape check, not just is_array(): a transient written by an older
     * version of this plugin must not reach the renderer.
     */
    private static function is_valid_payload( $payload ): bool {
        return is_array( $payload )
            && isset( $payload['attributes'], $payload['labels'], $payload['variations'] )
            && is_array( $payload['attributes'] )
            && is_array( $payload['labels'] )
            && is_array( $payload['variations'] );
    }

    /**
     * WooCommerce bumps its own "product" transient version whenever a product
     * or variation is saved, including stock changes, so the cache invalidates
     * itself. Currency and tax display are part of the key because they change
     * the rendered price_html.
     */
    private static function cache_key( WC_Product $product ): string {
        $parts = array(
            CWVB_VERSION,
            $product->get_id(),
            class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '',
            get_woocommerce_currency(),
            (string) get_option( 'woocommerce_tax_display_shop' ),
        );

        return 'cwvb_' . md5( implode( '|', $parts ) );
    }

    private static function build( WC_Product $product ): array {
        $payload = array(
            'attributes' => array(),
            'labels'     => array(),
            'variations' => array(),
        );

        $attributes           = $product->get_variation_attributes();
        $available_variations = $product->get_available_variations();

        if ( empty( $attributes ) || empty( $available_variations ) ) {
            return $payload;
        }

        foreach ( $attributes as $attribute_name => $options ) {
            $payload['labels'][ $attribute_name ] = wc_attribute_label( $attribute_name, $product );

            foreach ( $options as $option ) {
                $normalized = CWVB_Attributes::normalize_value( $attribute_name, $option );

                $payload['attributes'][ $attribute_name ][] = array(
                    'value' => $normalized,
                    'label' => CWVB_Attributes::display_value( $attribute_name, $normalized ),
                );
            }
        }

        // WooCommerce leaves price_html empty when every variation costs the same.
        $fallback_price_html = (string) $product->get_price_html();

        foreach ( $available_variations as $variation ) {
            $normalized_attributes = array();

            foreach ( $attributes as $attribute_name => $_options ) {
                $attribute_key = CWVB_Attributes::key( $attribute_name );
                $raw_value     = $variation['attributes'][ $attribute_key ] ?? '';

                $normalized_attributes[ $attribute_key ] = CWVB_Attributes::normalize_value(
                    $attribute_name,
                    $raw_value
                );
            }

            $price_html = (string) $variation['price_html'];

            $payload['variations'][] = array(
                'id'          => (int) $variation['variation_id'],
                'attributes'  => $normalized_attributes,
                'price_html'  => '' !== $price_html ? $price_html : $fallback_price_html,
                'in_stock'    => (bool) $variation['is_in_stock'],
                'purchasable' => (bool) $variation['is_purchasable'],
                'active'      => (bool) $variation['variation_is_active'],
                'visible'     => (bool) $variation['variation_is_visible'],
                'max_qty'     => isset( $variation['max_qty'] ) ? (float) $variation['max_qty'] : 0,
            );
        }

        return $payload;
    }
}