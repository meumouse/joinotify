<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * REST endpoint that validates an OTP code and authenticates the user.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Rest
 * @author MeuMouse.com
 */
class Otp_Verify_Code extends Otp_Route {

    /**
     * Route path.
     *
     * @since 2.0.0
     * @var string
     */
    protected $route = '/otp/verify-code';


    /**
     * Handle the verify-code route.
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

        $result = $this->auth_flow->verify_otp_login(
            sanitize_text_field( (string) $request->get_param( 'phone' ) ),
            sanitize_text_field( (string) $request->get_param( 'otp' ) ),
            ! empty( $request->get_param( 'remember' ) )
        );

        if ( ! is_wp_error( $result ) ) {
            $result['redirect'] = $this->get_redirect_url( $request );
        }

        return $this->to_rest_response( $result );
    }
}
