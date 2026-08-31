<?php
/**
 * Elementor integration.
 *
 * The widget class extends \Elementor\Widget_Base, so it must only be loaded
 * once Elementor itself is in memory. Everything here stays lazy.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Elementor {

    /**
     * elementor/widgets/register and $widgets_manager->register() are
     * Elementor 3.5+. Older versions simply get no widget; the shortcode
     * keeps working either way.
     */
    public static function init(): void {
        add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
    }

    public static function register_widget( $widgets_manager ): void {
        if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) ) {
            return;
        }

        if ( ! method_exists( $widgets_manager, 'register' ) ) {
            return;
        }

        $file = CWVB_PATH . 'includes/widgets/class-cwvb-elementor-widget.php';

        if ( ! is_readable( $file ) ) {
            return;
        }

        require_once $file;

        if ( class_exists( 'CWVB_Elementor_Widget' ) ) {
            $widgets_manager->register( new CWVB_Elementor_Widget() );
        }
    }

    /**
     * Product lists and other admin-only queries must never run on a normal
     * front-end request.
     */
    public static function is_editor_request(): bool {
        if ( is_admin() ) {
            return true;
        }

        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance;

        if ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) {
            return true;
        }

        return isset( $plugin->preview ) && $plugin->preview->is_preview_mode();
    }
}