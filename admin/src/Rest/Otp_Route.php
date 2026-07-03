<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Otp_Login\Auth_Flow_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * Shared base for the public OTP login REST endpoints.
 *
 * These routes are reachable by logged-out visitors, so the permission gate is
 * open and each request is instead protected by the WordPress REST nonce plus
 * the per-flow rate limiting enforced in the service layer. Responses use the
 * `{ success, data }` envelope the login widget consumes.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Rest
 * @author MeuMouse.com
 */
abstract class Otp_Route extends Abstract_Route {

    /**
     * Allowed HTTP method.
     *
     * @since 2.0.0
     * @var string
     */
    protected $methods = 'POST';

    /**
     * Authentication flow service.
     *
     * @since 2.0.0
     * @var Auth_Flow_Service
     */
    protected $auth_flow;


    /**
     * Build the route with the shared authentication service.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        $this->auth_flow = new Auth_Flow_Service();

        parent::__construct();
    }


    /**
     * Public endpoint: anyone can reach it, nonce is checked in the handler.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permission( WP_REST_Request $request ) {
        return true;
    }


    /**
     * Validate the WordPress REST nonce sent by the frontend.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Current REST request.
     * @return true|WP_Error Returns true when the nonce is valid or a WP_Error otherwise.
     */
    protected function verify_nonce( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            $error = new WP_Error( 'rest_forbidden', __( 'Invalid request nonce.', 'joinotify' ) );
            $error->add_data( array( 'status' => 403 ) );

            return $error;
        }

        return true;
    }


    /**
     * Transform a service result into the widget's `{ success, data }` envelope.
     *
     * @since 2.0.0
     * @param array|WP_Error $result Service response payload.
     * @return WP_REST_Response REST response wrapper.
     */
    protected function to_rest_response( $result ) {
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'data' => array(
                        'message' => $result->get_error_message(),
                        'code' => $result->get_error_code(),
                    ),
                ),
                200
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'data' => $result,
            ),
            200
        );
    }


    /**
     * Resolve the redirect URL after a successful authentication event.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Current REST request.
     * @return string Redirect URL.
     */
    protected function get_redirect_url( WP_REST_Request $request ) {
        $redirect = esc_url_raw( (string) $request->get_param( 'redirect' ) );

        if ( ! empty( $redirect ) ) {
            return $redirect;
        }

        if ( function_exists( 'wc_get_page_permalink' ) ) {
            return wc_get_page_permalink( 'myaccount' );
        }

        return home_url( '/' );
    }
}
