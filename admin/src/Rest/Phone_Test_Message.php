<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Core\Message_History;
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

        Message_History::set_context( array( 'source' => 'test' ) );

        $result = Controller::send_message_text( $sender, $receiver, $message );

        Message_History::clear_context();

        if ( 201 === $result ) {
            return rest_ensure_response( array(
                'status' => 'success',
                'message' => __( 'The test message was sent successfully!', 'joinotify' ),
            ) );
        }

        Controller::get_connection_state( $sender );

        return rest_ensure_response( array(
            'status' => 'error',
            'message' => __( 'Could not send the test message.', 'joinotify' ),
        ) );
    }
}

