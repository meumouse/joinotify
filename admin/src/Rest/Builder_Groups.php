<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Controller;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Fetch all WhatsApp groups for a given sender.
 *
 * @since 1.4.7
 */
class Builder_Groups extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder/groups';

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
        $payload      = $request->get_json_params();
        $sender       = isset( $payload['sender'] ) ? sanitize_text_field( $payload['sender'] ) : '';
        $fetch_groups = Controller::fetch_all_groups( $sender );

        if ( ! $fetch_groups || ( isset( $fetch_groups['status'] ) && $fetch_groups['status'] === 404 ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => __( 'Could not retrieve group information.', 'joinotify' ),
                'groups'  => array(),
            ) );
        }

        $groups = array();

        foreach ( (array) $fetch_groups as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }

            $groups[] = array(
                'id'          => isset( $group['id'] ) ? sanitize_text_field( $group['id'] ) : '',
                'subject'     => isset( $group['subject'] ) ? sanitize_text_field( $group['subject'] ) : '',
                'owner'       => isset( $group['owner'] ) ? sanitize_text_field( $group['owner'] ) : '',
                'size'        => isset( $group['size'] ) ? absint( $group['size'] ) : 0,
                'desc'        => isset( $group['desc'] ) ? sanitize_text_field( $group['desc'] ) : '',
                'picture_url' => isset( $group['pictureUrl'] ) ? esc_url_raw( $group['pictureUrl'] ) : '',
            );
        }

        return rest_ensure_response( array(
            'status' => 'success',
            'groups' => $groups,
        ) );
    }
}
