<?php
/**
 * Stylesheet and script registration.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Assets {

    public const HANDLE = 'custom-woo-variation-buttons';

    public static function register(): void {
        wp_register_style(
            self::HANDLE,
            CWVB_URL . 'assets/variations-buttons.css',
            array(),
            self::version( 'assets/variations-buttons.css' )
        );

        wp_register_script(
            self::HANDLE,
            CWVB_URL . 'assets/variations-buttons.js',
            array( 'jquery' ),
            self::version( 'assets/variations-buttons.js' ),
            true
        );
    }

    /**
     * CWVB_VERSION comes from the plugin header and changes with every release,
     * so it already busts the browser cache for everyone who updates.
     *
     * register() runs on wp_enqueue_scripts for every page of the site, widget
     * or not, so stat'ing both asset files there costs four filesystem calls per
     * request and buys nothing in production. Only a debug install, where files
     * change without the version moving, pays for filemtime().
     */
    private static function version( string $relative_path ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return CWVB_VERSION;
        }

        $file = CWVB_PATH . $relative_path;

        return file_exists( $file ) ? (string) filemtime( $file ) : CWVB_VERSION;
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