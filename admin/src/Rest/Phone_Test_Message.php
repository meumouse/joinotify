<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Controller;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Send a test WhatsApp message from the admin interface.
 */
class Phone_Test_Message extends Abstract_Route {

    /**
     * Route path for test messages.
     *
     * @var string
     */
    protected $route = '/admin/settings/phones/test-message';

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
        $sender = isset( $payload['sender'] ) ? sanitize_text_field( $payload['sender'] ) : '';
        $receiver = isset( $payload['receiver'] ) ? sanitize_text_field( $payload['receiver'] ) : '';
        $message = isset( $payload['message'] ) ? sanitize_textarea_field( $payload['message'] ) : '';

        $result = Controller::send_message_text( $sender, $receiver, $message );

        if ( 201 === $result ) {
            return rest_ensure_response( array(
                'status' => 'success',
                'message' => esc_html__( 'A mensagem teste foi enviada com sucesso!', 'joinotify' ),
            ) );
        }

        Controller::get_connection_state( $sender );

        return rest_ensure_response( array(
            'status' => 'error',
            'message' => esc_html__( 'Não foi possível enviar a mensagem de teste.', 'joinotify' ),
        ) );
    }
}

