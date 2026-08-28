<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Connect;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Complete the panel connection handshake with the code returned by the panel.
 *
 * @since 2.3.0
 */
class Cloud_Connect_Finish extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud/connect/finish';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();

        $result = Connect::finish( $payload['code'] ?? '', $payload['state'] ?? '' );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message() );
        }

        return $this->success_response( array(
            'message' => __( 'Your Joinotify account is connected.', 'joinotify' ),
            // Echoed back so the settings form does not overwrite the freshly
            // stored credentials with the values it loaded before connecting.
            'token' => $result['token'],
            'waba_id' => $result['waba_id'],
            'phone_number_id' => $result['phone_number_id'],
            'sync_error' => $result['sync_error'],
            'webhook_error' => $result['webhook_error'],
            'phones' => Registry::get_phone_state(),
        ) );
    }
}
