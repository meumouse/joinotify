<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Api\Sender_Sync;
use MeuMouse\Joinotify\Api\Transport;
use MeuMouse\Joinotify\Core\Phone_Manager;
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

        // On the Cloud API there is no per-number connection to poll: the number
        // is connected on the panel, so refreshing means re-importing it.
        if ( Transport::is_cloud() ) {
            $sync = Sender_Sync::sync();

            if ( is_wp_error( $sync ) ) {
                return rest_ensure_response( array(
                    'status' => 'error',
                    'message' => $sync->get_error_message(),
                    'phone' => $phone,
                ) );
            }

            $connected = '' !== Phone_Manager::get_phone_number_id( $phone );

            return rest_ensure_response( array(
                'status' => $connected ? 'success' : 'error',
                'message' => $connected
                    ? esc_html__( 'The number is connected to your Joinotify account.', 'joinotify' )
                    : esc_html__( 'This number is no longer connected on your Joinotify account.', 'joinotify' ),
                'connection' => array( 'connection' => $connected ? 'connected' : 'disconnected' ),
                // Re-importing refreshes quality, limits and ids for every number,
                // so hand the whole state back instead of a single flag.
                'phones' => Registry::get_phone_state(),
                'phone' => $phone,
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

