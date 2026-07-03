<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Activate an already-installed Joinotify extension/module plugin.
 *
 * @since 1.4.7
 */
class Modules_Activate extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/modules/activate';

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
        $payload     = $request->get_json_params();
        $plugin_slug = isset( $payload['plugin_slug'] ) ? sanitize_text_field( $payload['plugin_slug'] ) : '';

        if ( empty( $plugin_slug ) ) {
            return $this->error_response( esc_html__( 'Plugin slug is required.', 'joinotify' ) );
        }

        $activate = activate_plugin( $plugin_slug );

        if ( is_wp_error( $activate ) ) {
            return $this->error_response( $activate->get_error_message() );
        }

        return $this->success_response( array(
            'message' => __( 'Plugin activated successfully.', 'joinotify' ),
        ) );
    }
}
