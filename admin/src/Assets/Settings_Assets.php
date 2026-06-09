<?php

namespace MeuMouse\Joinotify\Assets;

use MeuMouse\Joinotify\Core\Scripts;

defined('ABSPATH') || exit;

/**
 * Load Vite-built admin assets for Joinotify settings pages.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Assets
 * @author MeuMouse.com
 */
class Settings_Assets extends Abstract_Assets {

    /**
     * Page-to-entry map for the Vite build.
     *
     * @since 1.4.7
     * @var array<string,string>
     */
    private $entries = array(
        'joinotify-settings'         => 'src/entries/settings.js',
        'joinotify-license'          => 'src/entries/license.js',
        'joinotify-workflows-builder' => 'src/entries/builder.js',
        'joinotify-workflows'         => 'src/entries/workflows.js',
        'joinotify-history'           => 'src/entries/history.js',
    );


    /**
     * Register hooks for the Vite-driven admin pages.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        parent::__construct();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
        add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 3 );
        add_filter( 'load_script_translation_file', array( $this, 'resolve_script_translation_file' ), 10, 3 );
    }


    /**
     * Point WordPress at our handle-named JSON translation files.
     *
     * Core resolves script translations as "{domain}-{locale}-{md5(src)}.json",
     * but the languages pipeline emits "{domain}-{locale}-{handle}.json". Without
     * this remap WP never finds the JSON, wp.i18n keeps the original strings, and
     * the Vue apps render untranslated. Vite hashes the entry paths, so a stable
     * md5-based name cannot be generated ahead of the build.
     *
     * @since 2.0.0
     * @param string|false $file   Translation file path resolved by core.
     * @param string       $handle Script handle being translated.
     * @param string       $domain Text domain.
     * @return string|false
     */
    public function resolve_script_translation_file( $file, $handle, $domain ) {
        if ( 'joinotify' !== $domain ) {
            return $file;
        }

        $locale = determine_locale();
        $candidate = trailingslashit( JOINOTIFY_DIR ) . "languages/joinotify-{$locale}-{$handle}.json";

        if ( is_readable( $candidate ) ) {
            return $candidate;
        }

        return $file;
    }


    /**
     * Enqueue the assets produced by Vite for the current admin page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function enqueue_assets() {
        $page = $this->get_current_page();

        if ( empty( $page ) || ! isset( $this->entries[ $page ] ) ) {
            return;
        }

        $assets = Scripts::get_entry_assets( $this->entries[ $page ] );

        if ( empty( $assets['script'] ) ) {
            return;
        }

        // The builder relies on the WordPress media modal for media pickers.
        if ( 'joinotify-workflows-builder' === $page ) {
            wp_enqueue_media();
        }

        // Vite emits fixed entry/style file names, so version the URLs by build
        // mtime to bust the browser cache after every rebuild.
        $asset_version = ! empty( $assets['version'] ) ? $assets['version'] : null;

        if ( ! empty( $assets['styles'] ) && is_array( $assets['styles'] ) ) {
            foreach ( $assets['styles'] as $index => $style_url ) {
                wp_enqueue_style(
                    'joinotify-vue-' . sanitize_key( $page ) . '-' . $index,
                    $style_url,
                    array(),
                    $asset_version
                );
            }
        }

        $handle = $this->get_script_handle( $page );

        wp_enqueue_script(
            $handle,
            $assets['script'],
            array( 'wp-i18n' ),
            $asset_version,
            true
        );

        wp_set_script_translations(
            $handle,
            'joinotify',
            JOINOTIFY_DIR . 'languages'
        );

        $config = $this->build_bootstrap_config( $page );

        if ( ! empty( $config ) ) {
            wp_localize_script( $handle, 'joinotifyBootstrapConfig', $config );
        }
    }


    /**
     * Build the minimal bootstrap config the Vue app needs to fetch its payload.
     *
     * Instead of embedding the full page payload in a data-bootstrap attribute,
     * the client receives only the REST root, a nonce, the page slug, and the
     * endpoint it should request to hydrate itself.
     *
     * @since 2.0.0
     * @param string $page Admin page slug.
     * @return array<string,mixed>|null
     */
    private function build_bootstrap_config( $page ) {
        $map = array(
            'joinotify-settings'          => array( 'page' => 'settings', 'endpoint' => 'admin/settings' ),
            'joinotify-license'           => array( 'page' => 'license', 'endpoint' => 'admin/settings' ),
            'joinotify-workflows-builder' => array( 'page' => 'builder', 'endpoint' => 'admin/builder' ),
            'joinotify-workflows'         => array( 'page' => 'workflows', 'endpoint' => 'admin/workflows/bootstrap' ),
            'joinotify-history'           => array( 'page' => 'history', 'endpoint' => 'admin/history/bootstrap' ),
        );

        if ( ! isset( $map[ $page ] ) ) {
            return null;
        }

        $endpoint = $map[ $page ]['endpoint'];

        if ( 'joinotify-workflows-builder' === $page ) {
            $post_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
            $endpoint = add_query_arg( 'id', $post_id, $endpoint );
        }

        return array(
            'restUrl'  => esc_url_raw( rest_url( 'joinotify/v1' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'page'     => $map[ $page ]['page'],
            'endpoint' => $endpoint,
        );
    }


    /**
     * Resolve the current Joinotify admin page slug.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return string
     */
    private function get_current_page() {
        if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( $_GET['page'] ) );
    }


    /**
     * Build a stable script handle for each admin page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @param string $page Admin page slug.
     * @return string
     */
    private function get_script_handle( $page ) {
        $handles = array(
            'joinotify-settings'         => 'joinotify-settings-app',
            'joinotify-license'          => 'joinotify-license-app',
            'joinotify-workflows-builder' => 'joinotify-builder-app',
            'joinotify-workflows'         => 'joinotify-workflows-app',
            'joinotify-history'           => 'joinotify-history-app',
        );

        return isset( $handles[ $page ] ) ? $handles[ $page ] : 'joinotify-vite-app';
    }


    /**
     * Mark Vite entry scripts as ES modules.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @param string $tag Script tag HTML.
     * @param string $handle Script handle.
     * @param string $src Script URL.
     * @return string
     */
    public function add_module_type_attribute( $tag, $handle, $src ) {
        $tag = is_scalar( $tag ) ? (string) $tag : '';
        $module_handles = array(
            'joinotify-settings-app',
            'joinotify-license-app',
            'joinotify-builder-app',
            'joinotify-workflows-app',
            'joinotify-history-app',
            'joinotify-vite-app',
        );

        if ( ! in_array( $handle, $module_handles, true ) ) {
            return $tag;
        }

        if ( false !== strpos( $tag, 'type=' ) ) {
            return $tag;
        }

        return sprintf(
            '<script type="module" src="%s" id="%s-js"></script>' . "\n",
            esc_url( $src ),
            esc_attr( $handle )
        );
    }
}