<?php
/**
 * Translating WooCommerce attribute data into values the UI can use.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Attributes {

    /**
     * The key WooCommerce uses for an attribute in variation data and in POST.
     */
    public static function key( string $attribute_name ): string {
        return 'attribute_' . sanitize_title( $attribute_name );
    }

    public static function display_value( string $attribute_name, string $value ): string {
        if ( taxonomy_exists( $attribute_name ) ) {
            $term = get_term_by( 'slug', $value, $attribute_name );

            if ( $term && ! is_wp_error( $term ) ) {
                return (string) $term->name;
            }
        }

        return $value;
    }

    public static function normalize_value( string $attribute_name, $value ): string {
        $value = is_string( $value ) ? $value : (string) $value;

        // Global attributes use term slugs in WooCommerce variation data.
        if ( taxonomy_exists( $attribute_name ) ) {
            return sanitize_title( $value );
        }

        // Custom product attributes use their actual text values.
        return wc_clean( $value );
    }

    /**
     * Attributes rendered as a <select>. Everything else becomes buttons.
     *
     * Accepts a comma separated list, so a product can have more than one
     * select. Result keeps the product's own attribute order, not the order
     * they were listed in the shortcode, because that is also the DOM order.
     */
    public static function resolve_selects( array $attributes, string $requested ): array {
        $requested = trim( $requested );
        $fallback  = array( (string) array_key_first( $attributes ) );

        if ( '' === $requested ) {
            return $fallback;
        }

        $wanted = array_filter( array_map( 'sanitize_title', explode( ',', $requested ) ) );

        if ( empty( $wanted ) ) {
            return $fallback;
        }

        $resolved = array();

        foreach ( array_keys( $attributes ) as $attribute_name ) {
            if ( in_array( sanitize_title( $attribute_name ), $wanted, true ) ) {
                $resolved[] = (string) $attribute_name;
            }
        }

        // Nothing matched: fall back to the single-select behaviour.
        return empty( $resolved ) ? $fallback : $resolved;
    }

    /**
     * One entry per attribute, in product order, ready for the template.
     */
    public static function build_meta( array $payload, array $select_attributes ): array {
        $meta = array();
        $step = 0;

        foreach ( $payload['attributes'] as $attribute_name => $options ) {
            $step++;

            $meta[] = array(
                'name'    => self::key( $attribute_name ),
                'label'   => $payload['labels'][ $attribute_name ] ?? $attribute_name,
                'type'    => in_array( $attribute_name, $select_attributes, true ) ? 'select' : 'buttons',
                'options' => $options,
                // Position in the DOM, 1 based: the step number shown to the user.
                'step'    => $step,
            );
        }

        return $meta;
    }
}