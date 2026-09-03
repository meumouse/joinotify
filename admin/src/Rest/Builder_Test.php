<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Send_Error;
use MeuMouse\Joinotify\Api\Transport;
use MeuMouse\Joinotify\Builder\Actions;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Workflow_Processor;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Run a test pass of all messages in a workflow.
 *
 * @since 1.4.7
 */
class Builder_Test extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder/test';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle request.
     *
     * Every send asks for its details rather than the bare response code, so a
     * refusal can be reported with its cause — a closed 24-hour session window
     * above all, which is what a free-form step hits when the test number has
     * not written to the business first.
     *
     * @version 2.4.0
     * @param WP_REST_Request $request Request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload  = $request->get_json_params();
        $post_id  = absint( $payload['post_id'] ?? 0 );

        if ( ! $post_id || get_post_type( $post_id ) !== 'joinotify-workflow' ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => __( 'Workflow not found.', 'joinotify' ),
            ) );
        }

        $workflow_content = Helpers::get_workflow_content_meta( $post_id );
        $receiver         = Admin::get_setting( 'test_number_phone' );

        if ( empty( $receiver ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => __( 'No test phone number registered.', 'joinotify' ),
            ) );
        }

        if ( empty( $workflow_content ) || ! is_array( $workflow_content ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => __( 'The workflow has no content to test.', 'joinotify' ),
            ) );
        }

        $all_actions = Actions::extract_all_actions( $workflow_content );
        $context     = $workflow_content[0]['data']['context'] ?? '';
        $trigger     = $workflow_content[0]['data']['trigger'] ?? '';

        $payload_ctx = array(
            'integration' => $context,
            'trigger'     => $trigger,
        );

        foreach ( $all_actions as $item ) {
            if ( ! isset( $item['type'] ) || $item['type'] !== 'action' ) {
                continue;
            }

            $action = $item['data']['action'] ?? '';

            if ( $action === 'send_whatsapp_message_text' ) {
                $sender  = $item['data']['sender'] ?? '';
                $message = Placeholders::replace_placeholders( $item['data']['message'] ?? '', $payload_ctx, 'sandbox' );
                $result  = Transport::send_message_text( $sender, $receiver, $message, 0, true, true );

                if ( empty( $result['success'] ) ) {
                    return self::failure_response( $result, __( 'Could not send the test message.', 'joinotify' ) );
                }
            } elseif ( $action === 'send_whatsapp_message_media' ) {
                $sender     = $item['data']['sender'] ?? '';
                $media_type = $item['data']['media_type'] ?? '';
                $media      = $item['data']['media_url'] ?? '';
                $caption    = Placeholders::replace_placeholders( $item['data']['caption'] ?? '', $payload_ctx, 'sandbox' );
                $result     = Transport::send_message_media( $sender, $receiver, $media_type, $media, $caption, 0, true, true );

                if ( empty( $result['success'] ) ) {
                    return self::failure_response( $result, __( 'Could not send one or more test messages.', 'joinotify' ) );
                }
            } elseif ( $action === 'send_whatsapp_message_template' ) {
                if ( ! Transport::is_cloud() ) {
                    return rest_ensure_response( array(
                        'status'  => 'error',
                        'message' => __( 'Template messages require the WhatsApp Cloud API transport.', 'joinotify' ),
                    ) );
                }

                $sender = $item['data']['sender'] ?? '';
                $components = Workflow_Processor::build_template_components( $item['data']['variables'] ?? array(), $payload_ctx, 'sandbox' );
                $result = Transport::send_message_template(
                    $sender,
                    $receiver,
                    $item['data']['template_name'] ?? '',
                    $item['data']['language'] ?? 'pt_BR',
                    $components,
                    0,
                    true,
                    true
                );

                if ( empty( $result['success'] ) ) {
                    return self::failure_response( $result, __( 'Could not send the test template message.', 'joinotify' ) );
                }
            }
        }

        return rest_ensure_response( array(
            'status'  => 'success',
            'message' => __( 'All test messages were sent successfully.', 'joinotify' ),
        ) );
    }


    /**
     * Build the error response for a refused test send.
     *
     * @since 2.4.0
     * @param array  $result | Normalized send details from the transport.
     * @param string $fallback | Message used when the failure has no description.
     * @return \WP_REST_Response
     */
    protected static function failure_response( $result, $fallback ) {
        $error_code = (string) ( $result['error'] ?? '' );

        return rest_ensure_response( array(
            'status'     => 'error',
            'message'    => Send_Error::describe( $error_code, $fallback ),
            'error_code' => $error_code,
        ) );
    }
}
