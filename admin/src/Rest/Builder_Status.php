<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Update the publish status of a workflow post.
 *
 * @since 1.4.7
 */
class Builder_Status extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder/status';

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
        $payload = $request->get_json_params();
        $post_id = absint( $payload['post_id'] ?? 0 );
        $status  = isset( $payload['status'] ) ? sanitize_key( $payload['status'] ) : 'draft';
        $status  = in_array( $status, array( 'publish', 'draft' ), true ) ? $status : 'draft';

        if ( ! $post_id || get_post_type( $post_id ) !== 'joinotify-workflow' ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => __( 'Workflow not found or invalid post type.', 'joinotify' ),
            ) );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => __( 'You do not have permission to edit workflows.', 'joinotify' ),
            ) );
        }

        $updated = wp_update_post( array(
            'ID'          => $post_id,
            'post_status' => $status,
        ), true );

        if ( is_wp_error( $updated ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => $updated->get_error_message(),
            ) );
        }

        return rest_ensure_response( array(
            'status'          => 'success',
            'message'         => __( 'Workflow status updated successfully.', 'joinotify' ),
            'workflow_status' => get_post_status( $post_id ),
        ) );
    }
}
