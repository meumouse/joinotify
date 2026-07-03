<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * REST endpoint that authenticates users using email/username and password.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Rest
 * @author MeuMouse.com
 */
class Otp_Password_Login extends Otp_Route {

    /**
     * Route path.
     *
     * @since 2.0.0
     * @var string
     */
    protected $route = '/otp/password-login';


    /**
     * Handle the password-login route.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Current REST request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle( WP_REST_Request $request ) {
        $nonce_check = $this->verify_nonce( $request );

        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $result = $this->auth_flow->login_with_password(
            sanitize_text_field( (string) $request->get_param( 'identifier' ) ),
            (string) $request->get_param( 'password' ),
            ! empty( $request->get_param( 'remember' ) )
        );

        if ( ! is_wp_error( $result ) ) {
            $result['redirect'] = $this->get_redirect_url( $request );
        }

        return $this->to_rest_response( $result );
    }
}
