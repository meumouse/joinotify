<?php

namespace MeuMouse\Joinotify\Assets;

defined('ABSPATH') || exit;

/**
 * Shared helpers for Joinotify asset loaders.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Assets
 * @author MeuMouse.com
 */
abstract class Abstract_Assets {

    /**
     * Base URL for the plugin assets directory.
     *
     * @since 1.4.7
     * @var string
     */
    protected $assets_url = '';

    /**
     * Suffix used to resolve minified files.
     *
     * @since 1.4.7
     * @var string
     */
    protected $min_file = '';

    /**
     * Current plugin version.
     *
     * @since 1.4.7
     * @var string
     */
    protected $version = '';

    /**
     * Whether debug mode is enabled.
     *
     * @since 1.4.7
     * @var bool
     */
    protected $debug_mode = false;

    /**
     * Whether developer mode is enabled.
     *
     * @since 1.4.7
     * @var bool
     */
    protected $dev_mode = false;


    /**
     * Load the shared asset context from plugin constants.
     *
     * @since 1.4.7
     * @return void
     */
    public function __construct() {
        $this->assets_url = defined( 'JOINOTIFY_ASSETS' ) ? JOINOTIFY_ASSETS : '';
        $this->version = defined( 'JOINOTIFY_VERSION' ) ? JOINOTIFY_VERSION : '';
        $this->dev_mode = defined( 'JOINOTIFY_DEV_MODE' ) ? (bool) JOINOTIFY_DEV_MODE : false;
        $this->debug_mode = defined( 'JOINOTIFY_DEBUG_MODE' ) ? (bool) JOINOTIFY_DEBUG_MODE : false;
        $this->min_file = $this->debug_mode ? '' : '.min';
    }


    /**
     * Resolve a public asset URL from a relative path.
     *
     * @since 1.4.7
     * @param string $relative_path Relative path inside the assets directory.
     * @return string
     */
    protected function build_asset_url( $relative_path ) {
        if ( is_string( $relative_path ) && preg_match( '#^https?://#i', $relative_path ) ) {
            return $relative_path;
        }

        return trailingslashit( $this->assets_url ) . ltrim( $relative_path, '/' );
    }


    /**
     * Enqueue a stylesheet using a relative asset path.
     *
     * @since 1.4.7
     * @param string $handle Style handle.
     * @param string $relative_path Relative asset path.
     * @param array  $deps Optional style dependencies.
     * @param string $version Optional asset version.
     * @return void
     */
    protected function enqueue_style_asset( $handle, $relative_path, $deps = array(), $version = null ) {
        wp_enqueue_style(
            $handle,
            $this->build_asset_url( $relative_path ),
            $deps,
            null === $version ? $this->version : $version
        );
    }


    /**
     * Enqueue a script using a relative asset path.
     *
     * @since 1.4.7
     * @param string $handle Script handle.
     * @param string $relative_path Relative asset path.
     * @param array  $deps Optional script dependencies.
     * @param string $version Optional asset version.
     * @param bool   $in_footer Whether to print the script in the footer.
     * @return void
     */
    protected function enqueue_script_asset( $handle, $relative_path, $deps = array(), $version = null, $in_footer = true ) {
        wp_enqueue_script(
            $handle,
            $this->build_asset_url( $relative_path ),
            $deps,
            null === $version ? $this->version : $version,
            $in_footer
        );
    }


    /**
     * Localize a script with a data payload.
     *
     * @since 1.4.7
     * @param string $handle Script handle.
     * @param string $object_name Localized object name.
     * @param array  $data Localization payload.
     * @return void
     */
    protected function localize_script_asset( $handle, $object_name, $data ) {
        wp_localize_script( $handle, $object_name, $data );
    }
}