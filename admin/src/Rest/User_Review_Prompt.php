<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Review_Prompt;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Persist the user's answer to the WordPress.org review prompt.
 *
 * @since 2.5.0
 */
class User_Review_Prompt extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/user/review-prompt';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle request.
     *
     * @since 2.5.0
     * @param WP_REST_Request $request Request instance.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $status = isset( $payload['status'] ) ? sanitize_key( $payload['status'] ) : '';

        if ( ! Review_Prompt::update_user_state( get_current_user_id(), $status ) ) {
            return new \WP_Error( 'joinotify_invalid_review_status', __( 'Invalid answer.', 'joinotify' ), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'status' => 'success',
            'review_prompt' => Review_Prompt::get_client_payload(),
        ) );
    }
}
