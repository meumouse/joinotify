<?php

namespace MeuMouse\Joinotify\Assets;

defined('ABSPATH') || exit;

/**
 * Shared context for Joinotify asset loaders.
 *
 * The Vite-built admin apps are enqueued by Settings_Assets through the
 * Scripts manifest resolver. This base only exposes the shared runtime
 * context (version, debug/dev flags) that asset loaders may rely on.
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
}
