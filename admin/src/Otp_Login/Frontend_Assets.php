<?php

namespace MeuMouse\Joinotify\Otp_Login;

use MeuMouse\Joinotify\Core\Scripts;

defined('ABSPATH') || exit;

/**
 * Register and enqueue the public Vite bundle for the login widget.
 *
 * Assets are registered on every front-end request but only enqueued on demand
 * (by the shortcode or the WooCommerce login templates) so the bundle never
 * loads on pages that do not render the form.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Frontend_Assets {

    /**
     * Vite entry source for the public login widget.
     *
     * @since 2.0.0
     * @var string
     */
    const ENTRY = 'src/entries/otp-login.js';

    /**
     * Script/style handle base.
     *
     * @since 2.0.0
     * @var string
     */
    const HANDLE = 'joinotify-otp-login';

    /**
     * Whether the assets have already been registered.
     *
     * @since 2.0.0
     * @var bool
     */
    private static $registered = false;


    /**
     * Register hooks.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
        add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 3 );
    }


    /**
     * Register the script and styles without enqueuing them yet.
     *
     * @since 2.0.0
     * @return void
     */
    public function register_assets() {
        if ( self::$registered || ! Settings::is_enabled() ) {
            return;
        }

        $assets = Scripts::get_entry_assets( self::ENTRY );

        if ( empty( $assets['script'] ) ) {
            return;
        }

        $version = ! empty( $assets['version'] ) ? $assets['version'] : null;

        if ( ! empty( $assets['styles'] ) && is_array( $assets['styles'] ) ) {
            foreach ( $assets['styles'] as $index => $style_url ) {
                wp_register_style( self::HANDLE . '-' . $index, $style_url, array(), $version );
            }
        }

        wp_register_script( self::HANDLE, $assets['script'], array( 'wp-i18n' ), $version, true );
        wp_set_script_translations( self::HANDLE, 'joinotify', JOINOTIFY_DIR . 'languages' );
        wp_localize_script( self::HANDLE, 'joinotifyOtpLogin', self::get_localized_config() );

        self::$registered = true;
    }


    /**
     * Enqueue the previously registered assets on demand.
     *
     * @since 2.0.0
     * @return void
     */
    public static function enqueue() {
        if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
            return;
        }

        $assets = Scripts::get_entry_assets( self::ENTRY );

        if ( ! empty( $assets['styles'] ) && is_array( $assets['styles'] ) ) {
            foreach ( array_keys( $assets['styles'] ) as $index ) {
                wp_enqueue_style( self::HANDLE . '-' . $index );
            }
        }

        wp_enqueue_script( self::HANDLE );
    }


    /**
     * Build the configuration object exposed to the login widget.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    private static function get_localized_config() {
        return array(
            'restUrl' => untrailingslashit( rest_url( 'joinotify/v1/otp' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'siteLocale' => str_replace( '_', '-', get_locale() ),
            'lostPasswordUrl' => wp_lostpassword_url(),
            'i18n' => array(),
            'theme' => array(
                'primaryColor' => Settings::get_primary_color(),
                'borderRadius' => Settings::get_border_radius(),
                'palette' => Settings::get_palette_map(),
            ),
        );
    }


    /**
     * Force the public bundle to load as an ES module.
     *
     * @since 2.0.0
     * @param string $tag Generated script tag.
     * @param string $handle Registered script handle.
     * @param string $src Script source URL.
     * @return string
     */
    public function add_module_type_attribute( $tag, $handle, $src ) {
        if ( self::HANDLE !== $handle ) {
            return $tag;
        }

        return sprintf( '<script type="module" src="%s" id="%s-js"></script>', esc_url( $src ), esc_attr( $handle ) );
    }
}
