<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Persist the user's choice to dismiss the placeholders tip.
 *
 * @since 1.4.7
 */
class User_Dismiss_Tip extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/user/dismiss-tip';

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
        $user_id  = get_current_user_id();
        $payload  = $request->get_json_params();
        $tip_key  = isset( $payload['tip'] ) ? sanitize_key( $payload['tip'] ) : 'placeholders';
        $meta_key = 'joinotify_dismiss_' . $tip_key . '_tip_user_meta';

        $updated = update_user_meta( $user_id, $meta_key, 'hidden' );

        if ( $updated ) {
            return rest_ensure_response( array(
                'status'   => 'success',
                'message'  => __( 'Tip dismissed.', 'joinotify' ),
                'meta_key' => $meta_key,
                'value'    => get_user_meta( $user_id, $meta_key, true ),
            ) );
        }

        return rest_ensure_response( array(
            'status'  => 'error',
            'message' => __( 'Could not dismiss the tip.', 'joinotify' ),
        ) );
    }
}
