<?php

namespace MeuMouse\Joinotify\Otp_Login;

defined('ABSPATH') || exit;

/**
 * Small template renderer for the OTP login partials.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Templates {

    /**
     * Absolute path to the OTP login templates directory.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_base_path() {
        return trailingslashit( JOINOTIFY_DIR ) . 'templates/otp-login/';
    }


    /**
     * Render a template file with scoped arguments.
     *
     * @since 2.0.0
     * @param string              $template Relative template path.
     * @param array<string,mixed> $args Optional variables exposed to the template.
     * @return void
     */
    public static function render( $template, array $args = array() ) {
        $file = self::get_base_path() . ltrim( $template, '/' );

        if ( ! file_exists( $file ) ) {
            return;
        }

        extract( $args, EXTR_SKIP );

        include $file;
    }
}
