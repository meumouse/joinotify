<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Builder\Custom_Variables;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Delete a custom builder variable.
 *
 * @since 2.0.0
 */
class Builder_Variables_Delete extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder-variables/delete';

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
        $id = isset( $payload['id'] ) ? (string) $payload['id'] : '';

        if ( ! Custom_Variables::delete( $id ) ) {
            return $this->error_response( esc_html__( 'Could not find the variable to remove.', 'joinotify' ) );
        }

        return $this->success_response( array(
            'message' => esc_html__( 'Variable removed successfully.', 'joinotify' ),
            'items'   => Custom_Variables::get_all(),
        ) );
    }
}
