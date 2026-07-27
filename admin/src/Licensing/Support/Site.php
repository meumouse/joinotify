<?php

namespace MeuMouse\Joinotify\Licensing\Support;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * How this installation identifies itself to a licensing server.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Site {

    /**
     * Site URL used as the activation identity.
     *
     * Licenses issued before the migration were bound to this exact value, so it
     * must keep returning the same string as long as the legacy driver is in
     * play, even where a newer identity would be a better fit.
     *
     * @since 2.1.0
     * @return string
     */
    public static function url() {
        if ( function_exists('site_url') ) {
            return site_url();
        }

        if ( defined('WPINC') && function_exists('get_bloginfo') ) {
            return get_bloginfo('url');
        }

        $base_url = ( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) ? 'https' : 'http' );
        $base_url .= '://' . ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' );

        if ( isset( $_SERVER['SCRIPT_NAME'] ) ) {
            $base_url .= str_replace( basename( $_SERVER['SCRIPT_NAME'] ), '', $_SERVER['SCRIPT_NAME'] );
        }

        return $base_url;
    }


    /**
     * Bare hostname, normalised the way the MDS API normalises it: lowercase, no
     * scheme, no port, no path, no leading "www.".
     *
     * @since 2.1.0
     * @param string $url | URL to normalise, defaults to this site
     * @return string
     */
    public static function domain( $url = null ) {
        $url = null === $url ? self::url() : (string) $url;
        $url = strtolower( trim( $url ) );

        // Strip scheme, then credentials, path, port and the www prefix.
        $url = preg_replace( '#^[a-z][a-z0-9+.\-]*://#', '', $url );
        $url = preg_replace( '#[/?\#].*$#', '', $url );
        $url = preg_replace( '#^[^@]*@#', '', $url );
        $url = preg_replace( '#:\d+$#', '', $url );
        $url = preg_replace( '#^www\.#', '', $url );

        return $url;
    }
}
