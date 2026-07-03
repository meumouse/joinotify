<?php

namespace MeuMouse\Joinotify\Otp_Login;

defined('ABSPATH') || exit;

/**
 * Registers the [joinotify_otp_login] shortcode.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Shortcode {

    /**
     * Register the shortcode.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        add_shortcode( 'joinotify_otp_login', array( $this, 'render_shortcode' ) );
    }


    /**
     * Render the OTP login form shortcode.
     *
     * @since 2.0.0
     * @param array<string,mixed>|string $atts Shortcode attributes.
     * @return string
     */
    public function render_shortcode( $atts ) {
        if ( ! Settings::is_enabled() ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'redirect' => '',
                'show_header' => '0',
                'title' => '',
                'description' => '',
            ),
            (array) $atts,
            'joinotify_otp_login'
        );

        $redirect_url = ! empty( $atts['redirect'] ) ? esc_url_raw( $atts['redirect'] ) : home_url( '/' );
        $show_header = in_array( strtolower( (string) $atts['show_header'] ), array( '1', 'true', 'yes' ), true );

        Frontend_Assets::enqueue();

        ob_start();

        Templates::render(
            'shared/otp-login-form.php',
            array(
                'context' => 'shortcode',
                'redirect_url' => $redirect_url,
                'title' => $atts['title'],
                'description' => $atts['description'],
                'show_header' => $show_header,
            )
        );

        return (string) ob_get_clean();
    }
}
