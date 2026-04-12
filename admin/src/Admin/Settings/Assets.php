<?php
/**
 * Assets source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

namespace MeuMouse\Joinotify\Admin\Settings;

use MeuMouse\Joinotify\Core\Scripts;

defined( 'ABSPATH' ) || exit;

/**
 * Load the Vite-built admin app assets for Joinotify pages.
 */
class Assets {

    /**
     * Page-to-entry map.
     *
     * @var array<string,string>
     */
    private $entries = array(
        'joinotify-settings' => 'src/entries/settings.js',
        'joinotify-license' => 'src/entries/license.js',
        'joinotify-workflows-builder' => 'src/entries/builder.js',
    );


    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
        add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 3 );
    }


    /**
     * Enqueue the assets produced by Vite, falling back to the legacy shell
     * when the manifest is unavailable.
     *
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

        $this->dequeue_legacy_assets();

        foreach ( $assets['styles'] as $index => $style_url ) {
            wp_enqueue_style(
                'joinotify-vue-' . sanitize_key( $page ) . '-' . $index,
                $style_url,
                array(),
                null
            );
        }

        $handle = $this->get_script_handle( $page );

        wp_enqueue_script(
            $handle,
            $assets['script'],
            array( 'wp-i18n' ),
            null,
            true
        );

        wp_set_script_translations(
            $handle,
            'joinotify',
            JOINOTIFY_DIR . 'languages'
        );
    }


    /**
     * Determine the current Joinotify page slug.
     *
     * @return string
     */
    private function get_current_page() {
        if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( $_GET['page'] ) );
    }


    /**
     * Remove the legacy asset handles when the Vite build is available.
     *
     * @return void
     */
    private function dequeue_legacy_assets() {
        wp_dequeue_style( 'joinotify-styles' );
        wp_dequeue_script( 'joinotify-scripts' );
        wp_dequeue_style( 'bootstrap-grid' );
        wp_dequeue_style( 'bootstrap-utilities' );

        wp_deregister_style( 'joinotify-styles' );
        wp_deregister_script( 'joinotify-scripts' );
    }


    /**
     * Get a stable script handle for a page.
     *
     * @param string $page Admin page slug.
     * @return string
     */
    private function get_script_handle( $page ) {
        $handles = array(
            'joinotify-settings' => 'joinotify-settings-app',
            'joinotify-license' => 'joinotify-license-app',
            'joinotify-workflows-builder' => 'joinotify-builder-app',
        );

        return isset( $handles[ $page ] ) ? $handles[ $page ] : 'joinotify-vite-app';
    }


    /**
     * Mark Vite entry scripts as ES modules so browser import statements work.
     *
     * @param string $tag    Script tag HTML.
     * @param string $handle Script handle.
     * @param string $src    Script URL.
     * @return string
     */
    public function add_module_type_attribute( $tag, $handle, $src ) {
        $module_handles = array(
            'joinotify-settings-app',
            'joinotify-license-app',
            'joinotify-builder-app',
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
