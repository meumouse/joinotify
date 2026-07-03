<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Controller;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Re-check the connection state of a sender phone number.
 */
class Phone_Check_Connection extends Abstract_Route {

    /**
     * Route path for connection checks.
     *
     * @var string
     */
    protected $route = '/admin/settings/phones/check-connection';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $phone = isset( $payload['phone'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( $payload['phone'] ) ) : '';

        if ( empty( $phone ) ) {
            return rest_ensure_response( array(
                'status' => 'error',
                'message' => __( 'Invalid phone number.', 'joinotify' ),
            ) );
        }

        delete_transient( 'joinotify_server_details_' . md5( $phone ) );
        $state = Controller::get_connection_state( $phone );
        $connected = isset( $state['connection'] ) && $state['connection'] === 'connected';

        return rest_ensure_response( array(
            'status' => $connected ? 'success' : 'error',
            'message' => $connected
                ? esc_html__( 'The phone is connected.', 'joinotify' )
                : esc_html__( 'The phone is disconnected.', 'joinotify' ),
            'connection' => $state,
            'phone' => $phone,
        ) );
    }
}

