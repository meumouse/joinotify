<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry as Settings_Registry;
use MeuMouse\Joinotify\Api\Connect;
use MeuMouse\Joinotify\Core\Logger;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Validate and store the Joinotify API key pasted in the setup wizard.
 *
 * @since 2.4.0
 */
class Onboarding_Connect extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/onboarding/connect';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.4.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $token = isset( $payload['api_key'] ) ? (string) $payload['api_key'] : '';

        $result = Connect::connect_with_key( $token );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message() );
        }

        if ( '' !== $result['webhook_error'] ) {
            Logger::register_log( 'Joinotify: webhook registration failed during setup: ' . $result['webhook_error'], 'WARNING' );
        }

        return $this->success_response( array(
            'message' => esc_html__( 'Your Joinotify account is connected.', 'joinotify' ),
            'account' => $result['account'],
            'senders' => $result['senders'],
            'sender_count' => count( $result['senders'] ),
            'webhook_error' => $result['webhook_error'],
            'phones' => Settings_Registry::get_phone_state(),
        ) );
    }
}
