<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * REST endpoint that registers a new account from the login widget.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Rest
 * @author MeuMouse.com
 */
class Otp_Register extends Otp_Route {

    /**
     * Route path.
     *
     * @since 2.0.0
     * @var string
     */
    protected $route = '/otp/register';


    /**
     * Handle the register route.
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

        $result = $this->auth_flow->register_user( array(
            'email' => sanitize_email( (string) $request->get_param( 'email' ) ),
            'username' => sanitize_user( (string) $request->get_param( 'username' ), true ),
            'password' => (string) $request->get_param( 'password' ),
            'phone' => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
        ) );

        if ( ! is_wp_error( $result ) ) {
            $result['redirect'] = $this->get_redirect_url( $request );
        }

        return $this->to_rest_response( $result );
    }
}
