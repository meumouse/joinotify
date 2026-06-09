<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Builder\Custom_Variables;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Create or update a custom builder variable.
 *
 * @since 2.0.0
 */
class Builder_Variables_Save extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder-variables';

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
        $variable = isset( $payload['variable'] ) && is_array( $payload['variable'] ) ? $payload['variable'] : array();

        $result = Custom_Variables::save( $variable );

        if ( empty( $result['success'] ) ) {
            return $this->error_response(
                isset( $result['message'] ) ? $result['message'] : esc_html__( 'Could not save the variable.', 'joinotify' )
            );
        }

        return $this->success_response( array(
            'message'  => $result['message'],
            'variable' => isset( $result['variable'] ) ? $result['variable'] : array(),
            'items'    => Custom_Variables::get_all(),
        ) );
    }
}
