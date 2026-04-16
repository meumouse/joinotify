<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Phone_Manager;
use MeuMouse\Joinotify\Validations\Otp_Validation;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Register a phone sender and send the OTP challenge.
 */
class Phone_Register extends Abstract_Route {

    /**
     * Route path for phone registration.
     *
     * @var string
     */
    protected $route = '/admin/settings/phones/register';

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

        if ( empty( $phone ) ) {
            return $this->error_response( esc_html__( 'Invalid phone number.', 'joinotify' ) );
        }

        if ( ! Otp_Validation::generate_and_send_otp( $phone ) ) {
            return $this->error_response( esc_html__( 'Could not send the verification code.', 'joinotify' ) );
        }

        return $this->success_response( array(
            'message'   => esc_html__( 'Code sent successfully.', 'joinotify' ),
            'phone'     => $phone,
            'countdown' => 60,
        ) );
    }
}
