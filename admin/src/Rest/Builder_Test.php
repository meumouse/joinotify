<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Builder\Actions;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Core\Helpers;
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
                $result  = Controller::send_message_text( $sender, $receiver, $message );

                if ( 201 !== $result ) {
                    Controller::get_connection_state( $sender );

                    return rest_ensure_response( array(
                        'status'  => 'error',
                        'message' => __( 'Could not send the test message.', 'joinotify' ),
                    ) );
                }
            } elseif ( $action === 'send_whatsapp_message_media' ) {
                $sender     = $item['data']['sender'] ?? '';
                $media_type = $item['data']['media_type'] ?? '';
                $media      = $item['data']['media_url'] ?? '';
                $caption    = Placeholders::replace_placeholders( $item['data']['caption'] ?? '', $payload_ctx, 'sandbox' );
                $result     = Controller::send_message_media( $sender, $receiver, $media_type, $media, $caption );

                if ( 201 !== $result ) {
                    Controller::get_connection_state( $sender );

                    return rest_ensure_response( array(
                        'status'  => 'error',
                        'message' => __( 'Could not send one or more test messages.', 'joinotify' ),
                    ) );
                }
            }
        }

        return rest_ensure_response( array(
            'status'  => 'success',
            'message' => __( 'All test messages were sent successfully.', 'joinotify' ),
        ) );
    }
}
