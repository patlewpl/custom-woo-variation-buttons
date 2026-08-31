<?php
/**
 * Stylesheet and script registration.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Assets {

    public const HANDLE = 'custom-woo-variation-buttons';

    public static function register(): void {
        $css_file = CWVB_PATH . 'assets/variations-buttons.css';
        $js_file  = CWVB_PATH . 'assets/variations-buttons.js';

        wp_register_style(
            self::HANDLE,
            CWVB_URL . 'assets/variations-buttons.css',
            array(),
            file_exists( $css_file ) ? filemtime( $css_file ) : CWVB_VERSION
        );

        wp_register_script(
            self::HANDLE,
            CWVB_URL . 'assets/variations-buttons.js',
            array( 'jquery' ),
            file_exists( $js_file ) ? filemtime( $js_file ) : CWVB_VERSION,
            true
        );
    }

    public static function enqueue(): void {
        // The shortcode can be rendered in contexts where wp_enqueue_scripts never ran.
        if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
            self::register();
        }

        wp_enqueue_style( self::HANDLE );
        wp_enqueue_script( self::HANDLE );
    }
}