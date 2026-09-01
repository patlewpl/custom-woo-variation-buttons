<?php
/**
 * Updates served from GitHub releases.
 *
 * WordPress only asks wordpress.org about plugins, so a plugin living outside
 * the directory has to answer for itself. Since WP 5.8 that is a documented
 * handshake rather than a hack: the "Update URI" header in the main file names
 * the host, WordPress skips wordpress.org for this plugin (so nobody can hijack
 * it by publishing the same slug there), and fires update_plugins_{host} during
 * its normal twice-daily update check.
 *
 * Nothing here may throw or print. A GitHub outage, a rate limit or a malformed
 * release has to leave the site exactly as it was: no update offered, no notice.
 */

defined( 'ABSPATH' ) || exit;

final class CWVB_Updater {

    /**
     * owner/repo. The repository has to be public: the update check runs
     * unauthenticated, from the visitor's server, with no token anywhere.
     */
    private const REPO = 'patlewpl/custom-woo-variation-buttons';

    /**
     * Must stay in sync with the host of the Update URI header, because that is
     * what WordPress builds the filter name from.
     */
    private const HOST = 'github.com';

    /**
     * The plugin folder name. Also the slug the details modal is keyed by.
     */
    private const SLUG = 'custom-woo-variation-buttons';

    private const CACHE_KEY = 'cwvb_release';

    /**
     * GitHub allows 60 unauthenticated requests per hour per IP, shared by every
     * site on that host, so this stays well clear of it. WordPress refreshes its
     * own update data about twice a day anyway.
     */
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Failures are cached too, or an unreachable GitHub would put an HTTP
     * timeout in front of every admin page load.
     */
    private const CACHE_TTL_FAILURE = 30 * MINUTE_IN_SECONDS;

    public static function init(): void {
        add_filter( 'update_plugins_' . self::HOST, array( __CLASS__, 'check' ), 10, 3 );
        add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder_name' ), 10, 4 );
    }

    /**
     * The update payload for one plugin.
     *
     * Returned even when the release is not newer than what is installed:
     * WordPress files that under "no_update", which is what makes the
     * auto-update toggle available for this plugin.
     */
    public static function check( $update, $plugin_data, $plugin_file ) {
        // The filter is keyed by hostname, so every github.com hosted plugin on
        // the site arrives here, not only this one.
        if ( plugin_basename( CWVB_FILE ) !== $plugin_file ) {
            return $update;
        }

        $release = self::get_release();

        if ( null === $release ) {
            return $update;
        }

        $payload = array(
            'id'          => 'https://github.com/' . self::REPO,
            'slug'        => self::SLUG,
            'plugin'      => $plugin_file,
            'version'     => $release['version'],
            // Core copies version into new_version, but the plugin row in
            // wp-admin reads new_version directly, so set both.
            'new_version' => $release['version'],
            'url'         => $release['url'],
            'package'     => $release['package'],
        );

        foreach ( array( 'requires' => 'RequiresWP', 'requires_php' => 'RequiresPHP' ) as $key => $header ) {
            if ( ! empty( $plugin_data[ $header ] ) ) {
                $payload[ $key ] = $plugin_data[ $header ];
            }
        }

        return $payload;
    }

    /**
     * The "View details" modal behind the update notice.
     */
    public static function details( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
            return $result;
        }

        $release = self::get_release();

        if ( null === $release ) {
            return $result;
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // No translation, no markup: this only feeds the modal.
        $plugin = get_plugin_data( CWVB_FILE, false, false );

        return (object) array(
            'name'          => $plugin['Name'],
            'slug'          => self::SLUG,
            'version'       => $release['version'],
            'author'        => $plugin['Author'],
            'homepage'      => 'https://github.com/' . self::REPO,
            'requires'      => $plugin['RequiresWP'],
            'requires_php'  => $plugin['RequiresPHP'],
            'download_link' => $release['package'],
            'last_updated'  => $release['published'],
            'sections'      => array(
                'description' => wpautop( esc_html( $plugin['Description'] ) ),
                'changelog'   => self::format_changelog( $release['changelog'] ),
            ),
        );
    }

    /**
     * GitHub's own source zip unpacks to owner-repo-<commit>/, and WordPress
     * names the installed folder after that directory - which would install a
     * second copy next to the old one and deactivate the plugin. Rename it back
     * to the folder the plugin actually lives in.
     *
     * A release with a built zip attached never reaches this; it is the safety
     * net for the zipball fallback in find_package().
     */
    public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        global $wp_filesystem;

        if ( ! is_string( $source ) || ! $wp_filesystem ) {
            return $source;
        }

        $plugin = is_array( $hook_extra ) && isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : '';

        if ( plugin_basename( CWVB_FILE ) !== $plugin ) {
            return $source;
        }

        $corrected = trailingslashit( $remote_source ) . self::SLUG;

        if ( untrailingslashit( $source ) === $corrected ) {
            return $source;
        }

        if ( ! $wp_filesystem->move( $source, $corrected, true ) ) {
            return new WP_Error(
                'cwvb_rename_failed',
                'Nie udało się przygotować katalogu aktualizacji wtyczki.'
            );
        }

        return trailingslashit( $corrected );
    }

    /**
     * The latest release, from cache when possible. null means "ask again
     * later", never "there is no update".
     */
    private static function get_release(): ?array {
        if ( ! self::is_forced_check() ) {
            $cached = get_transient( self::CACHE_KEY );

            if ( self::is_release( $cached ) ) {
                return $cached;
            }

            // Anything else stored is the cached failure marker.
            if ( false !== $cached ) {
                return null;
            }
        }

        $release = self::fetch_release();

        set_transient(
            self::CACHE_KEY,
            null === $release ? 'none' : $release,
            null === $release ? self::CACHE_TTL_FAILURE : self::CACHE_TTL
        );

        return $release;
    }

    /**
     * "Check again" on the updates screen has to reach GitHub, or the button
     * would appear to do nothing for this plugin for up to six hours.
     */
    private static function is_forced_check(): bool {
        // Read only, and it just bypasses a cache, so a capability check is the
        // whole of the protection needed here.
        return isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    private static function is_release( $value ): bool {
        return is_array( $value )
            && ! empty( $value['version'] )
            && ! empty( $value['package'] );
    }

    /**
     * /releases/latest already skips drafts and pre-releases, so tagging a beta
     * never offers itself to customers.
     */
    private static function fetch_release(): ?array {
        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::REPO . '/releases/latest',
            array(
                'timeout' => 6,
                'headers' => array( 'Accept' => 'application/vnd.github+json' ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            return null;
        }

        // Tags are written v1.4.0; the Version header is not.
        $release = array(
            'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
            'package'   => self::find_package( $body ),
            'url'       => (string) ( $body['html_url'] ?? 'https://github.com/' . self::REPO ),
            'changelog' => (string) ( $body['body'] ?? '' ),
            'published' => (string) ( $body['published_at'] ?? '' ),
        );

        return self::is_release( $release ) ? $release : null;
    }

    /**
     * Prefer a zip attached to the release: it unpacks under the right folder
     * name and carries no development files. GitHub's generated source zip is
     * the fallback.
     */
    private static function find_package( array $release ): string {
        $assets = isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array();

        foreach ( $assets as $asset ) {
            $url = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';

            if ( '' !== $url && '.zip' === strtolower( substr( $url, -4 ) ) ) {
                return $url;
            }
        }

        return isset( $release['zipball_url'] ) ? (string) $release['zipball_url'] : '';
    }

    /**
     * Release notes are Markdown typed on GitHub. Escaping first and adding
     * paragraphs after is enough for the modal, and means nothing written in a
     * release body can inject markup into wp-admin.
     */
    private static function format_changelog( string $body ): string {
        $body = trim( $body );

        if ( '' === $body ) {
            return '<p>Brak opisu zmian.</p>';
        }

        return wpautop( esc_html( $body ) );
    }
}