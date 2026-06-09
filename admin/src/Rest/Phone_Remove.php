<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Core\Phone_Manager;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Remove a registered sender phone number.
 */
class Phone_Remove extends Abstract_Route {

    /**
     * Route path for sender removal.
     *
     * @var string
     */
    protected $route = '/admin/settings/phones/remove';

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
        $phone   = isset( $payload['phone'] ) ? Phone_Manager::sanitize_phone( $payload['phone'] ) : '';

        if ( empty( $phone ) || ! Phone_Manager::remove_sender( $phone ) ) {
            return $this->error_response( esc_html__( 'Could not find the provided phone number.', 'joinotify' ) );
        }

        do_action( 'Joinotify/Remove_Phone/Success', $phone );

        return $this->success_response( array(
            'message' => __( 'The sender phone number was removed successfully!', 'joinotify' ),
            'phones'  => Registry::get_phone_state(),
        ) );
    }
}
