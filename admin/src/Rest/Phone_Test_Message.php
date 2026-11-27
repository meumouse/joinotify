<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Send_Error;
use MeuMouse\Joinotify\Api\Template_Repository;
use MeuMouse\Joinotify\Api\Transport;
use MeuMouse\Joinotify\Core\Message_History;
use MeuMouse\Joinotify\Core\Workflow_Processor;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Send a test WhatsApp message from the admin interface.
 *
 * The message is either free text or an approved template with its variables
 * filled in. A template is what reaches a contact whose 24-hour window is
 * closed, which is exactly when a free-text test fails.
 *
 * A test never goes to the retry queue: it is there to answer right away
 * whether the number can send, and a queued retry would hide that answer.
 *
 * @since 1.4.7
 * @version 2.8.0
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
     * Most variables a template can carry, carousel cards included.
     *
     * @since 2.8.0
     * @var int
     */
    const MAX_VARIABLES = 100;


    /**
     * Handle the request.
     *
     * `type` picks the message: `text` (the default, with `message`) or
     * `template` (with `template_name`, `language` and `variables`, in the
     * same shape the builder's template action stores). Template values may
     * hold placeholders; they resolve to their sample values, as in every
     * other test send.
     *
     * @since 1.4.7
     * @version 2.8.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $sender = isset( $payload['sender'] ) ? preg_replace( '/\D/', '', sanitize_text_field( (string) $payload['sender'] ) ) : '';
        $receiver = isset( $payload['receiver'] ) ? preg_replace( '/\D/', '', sanitize_text_field( (string) $payload['receiver'] ) ) : '';
        $type = isset( $payload['type'] ) && 'template' === $payload['type'] ? 'template' : 'text';

        if ( '' === $sender || '' === $receiver ) {
            return $this->error_response( __( 'Choose the sender and the recipient of the test message.', 'joinotify' ), array( 'error_code' => 'missing_phone' ) );
        }

        if ( 'template' === $type ) {
            return rest_ensure_response( $this->send_template( $sender, $receiver, $payload ) );
        }

        $message = isset( $payload['message'] ) ? sanitize_textarea_field( (string) $payload['message'] ) : '';

        if ( '' === trim( $message ) ) {
            return $this->error_response( __( 'Write the test message before sending it.', 'joinotify' ), array( 'error_code' => 'missing_message' ) );
        }

        Message_History::set_context( array( 'source' => 'test' ) );

        try {
            $details = Transport::send_message_text( $sender, $receiver, $message, 0, false, true );
        } finally {
            Message_History::clear_context();
        }

        return rest_ensure_response( self::text_response( $details ) );
    }


    /**
     * Send the approved template a test asked for.
     *
     * The language is the one the template was approved in. When the request
     * leaves it empty it is read from the synced list, since sending another
     * language code is an error.
     *
     * @since 2.8.0
     * @param string $sender | Sender number in digits.
     * @param string $receiver | Recipient number in digits.
     * @param array $payload | Request body.
     * @return array
     */
    protected function send_template( $sender, $receiver, array $payload ) {
        $template_name = isset( $payload['template_name'] ) ? sanitize_text_field( (string) $payload['template_name'] ) : '';
        $language = isset( $payload['language'] ) ? sanitize_text_field( (string) $payload['language'] ) : '';

        if ( '' === $template_name ) {
            return array(
                'status' => 'error',
                'message' => __( 'Choose the approved template to send.', 'joinotify' ),
                'error_code' => 'missing_template_name',
            );
        }

        if ( '' === $language ) {
            $template = Template_Repository::find( $template_name );
            $language = is_array( $template ) ? (string) ( $template['language'] ?? '' ) : '';
        }

        $variables = self::sanitize_variables( $payload['variables'] ?? array() );
        $components = Workflow_Processor::build_template_components( $variables, array(), 'sandbox' );

        $result = joinotify_send_whatsapp_template( $receiver, $template_name, '' !== $language ? $language : 'pt_BR', $components, array(
            'sender' => $sender,
            'queue_on_failure' => false,
            'context' => array( 'source' => 'test' ),
        ) );

        return self::template_response( $result );
    }


    /**
     * Keep only the fields a template variable is built from.
     *
     * The shape is the one the builder's template action stores: `card`,
     * `component`, `sub_type`, `index`, `key`, `format` and `value`.
     *
     * @since 2.8.0
     * @param mixed $variables | Variables from the request body.
     * @return array
     */
    public static function sanitize_variables( $variables ) {
        if ( ! is_array( $variables ) ) {
            return array();
        }

        $clean = array();

        foreach ( array_slice( array_values( $variables ), 0, self::MAX_VARIABLES ) as $variable ) {
            if ( ! is_array( $variable ) || empty( $variable['component'] ) || ! is_scalar( $variable['component'] ) ) {
                continue;
            }

            $clean[] = array(
                'card' => isset( $variable['card'] ) && is_numeric( $variable['card'] ) ? (int) $variable['card'] : -1,
                'component' => sanitize_key( (string) $variable['component'] ),
                'sub_type' => isset( $variable['sub_type'] ) && is_scalar( $variable['sub_type'] ) ? sanitize_key( (string) $variable['sub_type'] ) : '',
                'index' => isset( $variable['index'] ) && is_numeric( $variable['index'] ) ? max( 0, (int) $variable['index'] ) : 0,
                'key' => isset( $variable['key'] ) && is_scalar( $variable['key'] ) ? sanitize_text_field( (string) $variable['key'] ) : '',
                'format' => isset( $variable['format'] ) && is_scalar( $variable['format'] ) ? sanitize_key( (string) $variable['format'] ) : '',
                'value' => isset( $variable['value'] ) && is_scalar( $variable['value'] ) ? sanitize_text_field( (string) $variable['value'] ) : '',
            );
        }

        return $clean;
    }


    /**
     * Turn the details of a free-text test send into the route's answer.
     *
     * A refusal names its cause: Meta's own code first (test number, display
     * name, closed window), then the client's failure code, and WhatsApp's own
     * explanation when neither says more than "could not send".
     *
     * @since 2.8.0
     * @param mixed $details | Details returned by Transport::send_message_text().
     * @return array
     */
    public static function text_response( $details ) {
        $details = is_array( $details ) ? $details : array();

        if ( ! empty( $details['success'] ) ) {
            return array(
                'status' => 'success',
                'message' => __( 'The test message was sent successfully!', 'joinotify' ),
                'message_id' => (string) ( $details['wamid'] ?? '' ),
                'delivery_status' => 'sent',
            );
        }

        $error_code = (string) ( $details['error'] ?? '' );
        $meta_code = (int) ( $details['meta_code'] ?? 0 );
        $fallback = __( 'Could not send the test message.', 'joinotify' );
        $message = Send_Error::describe_meta_code( $meta_code );

        if ( '' === $message ) {
            $message = Send_Error::describe( $error_code, $fallback );
        }

        $detail = (string) ( $details['error_detail'] ?? '' );

        if ( $fallback === $message && '' !== $detail ) {
            $message .= ' ' . $detail;
        }

        return array(
            'status' => 'error',
            'message' => $message,
            'error_code' => $error_code,
            'meta_code' => $meta_code,
        );
    }


    /**
     * Turn the result of a template test send into the route's answer.
     *
     * @since 2.8.0
     * @param array|\WP_Error $result | Result of joinotify_send_whatsapp_template().
     * @return array
     */
    public static function template_response( $result ) {
        if ( is_wp_error( $result ) ) {
            $data = (array) $result->get_error_data();
            $meta_code = (int) ( $data['meta_code'] ?? 0 );
            $message = Send_Error::describe_meta_code( $meta_code );

            return array(
                'status' => 'error',
                'message' => '' !== $message ? $message : $result->get_error_message(),
                'error_code' => (string) ( $data['reason'] ?? $result->get_error_code() ),
                'meta_code' => $meta_code,
            );
        }

        $result = is_array( $result ) ? $result : array();

        return array(
            'status' => 'success',
            'message' => __( 'The test template was sent successfully!', 'joinotify' ),
            'message_id' => (string) ( $result['message_id'] ?? '' ),
            'delivery_status' => (string) ( $result['status'] ?? 'sent' ),
        );
    }
}
