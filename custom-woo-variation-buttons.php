<?php
/**
 * Plugin Name: WooCommerce Variation Buttons from patlew.pl
 * Description: Renders a WooCommerce variable product on any page with one configurable select attribute and button-based remaining attributes.
 * Version: 1.3.0
 * Author: patlew.pl
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

/*
 * A second copy of this plugin (a duplicated folder, an old backup left in
 * wp-content/plugins) would otherwise fatal with "Cannot declare class".
 */
if ( defined( 'CWVB_VERSION' ) || class_exists( 'Custom_Woo_Variation_Buttons', false ) ) {
    return;
}

/*
 * The Version header has to be a literal for WordPress to parse it, so read the
 * version back out of it instead of repeating the number in a define(). Keeps
 * the header the single source of truth. get_file_data() only reads the first
 * 8 KB of this file.
 */
$cwvb_headers = get_file_data( __FILE__, array( 'version' => 'Version' ) );

define( 'CWVB_VERSION', '' !== $cwvb_headers['version'] ? $cwvb_headers['version'] : '0' );
define( 'CWVB_FILE', __FILE__ );
define( 'CWVB_PATH', plugin_dir_path( __FILE__ ) );
define( 'CWVB_URL', plugin_dir_url( __FILE__ ) );

unset( $cwvb_headers );

/*
 * require_once on a missing file is fatal, and the likeliest way to get there
 * is an incomplete upload (main file replaced over FTP, includes/ left behind).
 * Bail out quietly instead of taking the site down.
 */
foreach (
    array(
        'includes/class-cwvb-assets.php',
        'includes/class-cwvb-attributes.php',
        'includes/class-cwvb-product-data.php',
        'includes/class-cwvb-shortcode.php',
        'includes/class-cwvb-elementor.php',
        'includes/class-custom-woo-variation-buttons.php',
    ) as $cwvb_file
) {
    $cwvb_file = CWVB_PATH . $cwvb_file;

    if ( ! is_readable( $cwvb_file ) ) {
        error_log( 'custom-woo-variation-buttons: brak pliku ' . $cwvb_file . ' - wtyczka nie zostala zaladowana.' );
        return;
    }

    require_once $cwvb_file;
}

unset( $cwvb_file );

Custom_Woo_Variation_Buttons::init();