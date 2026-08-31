<?php
/**
 * Hook wiring. Everything else lives in its own class.
 */

defined( 'ABSPATH' ) || exit;

final class Custom_Woo_Variation_Buttons {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', array( 'CWVB_Assets', 'register' ) );
        add_shortcode( CWVB_Shortcode::TAG, array( 'CWVB_Shortcode', 'render' ) );

        // No-op when Elementor is not installed; the shortcode works regardless.
        CWVB_Elementor::init();
    }
}