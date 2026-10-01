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
        'joinotify-onboarding'       => 'src/entries/onboarding.js',
        'joinotify-workflows-builder' => 'src/entries/builder.js',
        'joinotify-workflows'         => 'src/entries/workflows.js',
        'joinotify-history'           => 'src/entries/history.js',
        'joinotify-queue'             => 'src/entries/queue.js',
        'joinotify-contacts'          => 'src/entries/contacts.js',
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
        add_filter( 'load_script_translation_file', array( __CLASS__, 'resolve_script_translation_file' ), 10, 3 );
        add_filter( 'pre_load_script_translations', array( __CLASS__, 'merge_language_pack_translations' ), 10, 4 );
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
     * Each JSON now carries only the strings its own bundle can ask for, so there
     * is no longer a full-table file to fall back on when a handle has no JSON.
     * The pipeline derives its handle list from `app/src/entries/*`, which is where
     * a new admin page has to register its entry anyway, so every enqueued handle
     * gets a file without a second list to keep in step.
     *
     * @since 2.0.0
     * @version 2.4.0
     * @param string|false $file   Translation file path resolved by core.
     * @param string       $handle Script handle being translated.
     * @param string       $domain Text domain.
     * @return string|false
     */
    public static function resolve_script_translation_file( $file, $handle, $domain ) {
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
     * Assemble a script's translations from the WordPress.org language pack.
     *
     * translate.wordpress.org emits one JSON per built file that holds strings,
     * named "{domain}-{locale}-{md5(path)}.json". Core only looks for the file of
     * the enqueued entry, but a Vite entry is a thin loader and its strings live in
     * the chunks it imports (`chunks/PageHeader-<hash>.js`, …), which are never
     * enqueued. Without this merge, a site running on language packs would see
     * every Vue screen in English.
     *
     * Bundled handle-named JSONs (every package but a --pot-only build) still win:
     * when one exists this steps aside and core loads it as before.
     *
     * @since 2.4.3
     * @param string|false|null $translations Translations short-circuited by an earlier filter.
     * @param string|false      $file         Translation file core is about to read.
     * @param string            $handle       Script handle being translated.
     * @param string            $domain       Text domain.
     * @return string|false|null JSON translations, or the untouched value to let core continue.
     */
    public static function merge_language_pack_translations( $translations, $file, $handle, $domain ) {
        static $merged = array();

        if ( null !== $translations || 'joinotify' !== $domain ) {
            return $translations;
        }

        $locale = determine_locale();
        $key = $locale . '|' . $handle;

        if ( array_key_exists( $key, $merged ) ) {
            return $merged[ $key ];
        }

        $merged[ $key ] = null;

        if ( 'en_US' === $locale || is_readable( trailingslashit( JOINOTIFY_DIR ) . "languages/joinotify-{$locale}-{$handle}.json" ) ) {
            return null;
        }

        $script = wp_scripts()->query( $handle );
        $dist_url = trailingslashit( JOINOTIFY_URL ) . Scripts::DIST_URL_PATH;

        if ( ! $script || ! is_string( $script->src ) || 0 !== strpos( $script->src, $dist_url ) ) {
            return null;
        }

        $messages = array();

        foreach ( Scripts::get_entry_script_files( substr( $script->src, strlen( $dist_url ) ) ) as $script_file ) {
            $pack = WP_LANG_DIR . "/plugins/joinotify-{$locale}-" . md5( Scripts::DIST_URL_PATH . $script_file ) . '.json';

            if ( ! is_readable( $pack ) ) {
                continue;
            }

            $data = json_decode( (string) file_get_contents( $pack ), true );

            if ( ! empty( $data['locale_data']['messages'] ) && is_array( $data['locale_data']['messages'] ) ) {
                // The first file's "" header (plural forms, language) is kept.
                $messages += $data['locale_data']['messages'];
            }
        }

        if ( empty( $messages ) ) {
            return null;
        }

        $merged[ $key ] = wp_json_encode( array(
            'domain' => 'messages',
            'locale_data' => array( 'messages' => (object) $messages ),
        ) );

        return $merged[ $key ];
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
     * @version 2.5.0
     * @param string $page Admin page slug.
     * @return array<string,mixed>|null
     */
    private function build_bootstrap_config( $page ) {
        $map = array(
            'joinotify-settings'          => array( 'page' => 'settings', 'endpoint' => 'admin/settings' ),
            'joinotify-onboarding'        => array( 'page' => 'onboarding', 'endpoint' => 'admin/onboarding' ),
            'joinotify-workflows-builder' => array( 'page' => 'builder', 'endpoint' => 'admin/builder' ),
            'joinotify-workflows'         => array( 'page' => 'workflows', 'endpoint' => 'admin/workflows/bootstrap' ),
            'joinotify-history'           => array( 'page' => 'history', 'endpoint' => 'admin/history/bootstrap' ),
            'joinotify-queue'             => array( 'page' => 'queue', 'endpoint' => 'admin/queue/bootstrap' ),
            'joinotify-contacts'          => array( 'page' => 'contacts', 'endpoint' => 'admin/contacts/bootstrap' ),
        );

        if ( ! isset( $map[ $page ] ) ) {
            return null;
        }

        $endpoint = $map[ $page ]['endpoint'];

        if ( 'joinotify-workflows-builder' === $page ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which workflow the builder screen was opened for; nothing is acted on.
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See the note below.
        if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
            return '';
        }

        // Nonce verification is not applicable: this only reads which admin
        // screen WordPress is already rendering, it never acts on a request.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return sanitize_text_field( wp_unslash( $_GET['page'] ) );
    }


    /**
     * Build a stable script handle for each admin page.
     *
     * @since 1.4.7
     * @version 2.5.0
     * @param string $page Admin page slug.
     * @return string
     */
    private function get_script_handle( $page ) {
        $handles = array(
            'joinotify-settings'         => 'joinotify-settings-app',
            'joinotify-onboarding'       => 'joinotify-onboarding-app',
            'joinotify-workflows-builder' => 'joinotify-builder-app',
            'joinotify-workflows'         => 'joinotify-workflows-app',
            'joinotify-history'           => 'joinotify-history-app',
            'joinotify-queue'             => 'joinotify-queue-app',
            'joinotify-contacts'          => 'joinotify-contacts-app',
        );

        return isset( $handles[ $page ] ) ? $handles[ $page ] : 'joinotify-vite-app';
    }


    /**
     * Mark Vite entry scripts as ES modules.
     *
     * WordPress passes the whole printed block for this handle through
     * `script_loader_tag` — the main `<script>` tag PLUS any translation/inline
     * scripts it prepended (notably the `wp.i18n.setLocaleData` block emitted by
     * `wp_set_script_translations`). Rebuilding the tag from scratch dropped those
     * inline scripts, so JS translations never reached `wp.i18n` and the Vue apps
     * rendered untranslated. Instead, inject `type="module"` only into this
     * handle's own `<script>` tag (matched by its exact `id`), leaving the
     * surrounding inline scripts intact.
     *
     * @since 1.4.7
     * @version 2.5.0
     * @param string $tag Script tag HTML (may include translation/inline scripts).
     * @param string $handle Script handle.
     * @param string $src Script URL.
     * @return string
     */
    public function add_module_type_attribute( $tag, $handle, $src ) {
        $tag = is_scalar( $tag ) ? (string) $tag : '';
        $module_handles = array(
            'joinotify-settings-app',
            'joinotify-onboarding-app',
            'joinotify-builder-app',
            'joinotify-workflows-app',
            'joinotify-history-app',
            'joinotify-queue-app',
            'joinotify-contacts-app',
            'joinotify-vite-app',
        );

        if ( ! in_array( $handle, $module_handles, true ) ) {
            return $tag;
        }

        // Match only this handle's main tag (id="{handle}-js"), and only when it
        // doesn't already declare a type. The trailing quote backreference keeps
        // sibling scripts (…-js-translations, …-js-before/after) from matching.
        $pattern = '#<script\b(?![^>]*\stype=)([^>]*\sid=(["\'])'
            . preg_quote( $handle, '#' ) . '-js\2[^>]*)>#';

        $result = preg_replace( $pattern, '<script type="module"$1>', $tag, 1 );

        return null === $result ? $tag : $result;
    }
}