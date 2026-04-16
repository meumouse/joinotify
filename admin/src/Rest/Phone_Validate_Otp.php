<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Core\Phone_Manager;
use MeuMouse\Joinotify\Validations\Otp_Validation;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Validate the OTP and persist the phone sender.
 */
class Phone_Validate_Otp extends Abstract_Route {

    /**
     * Route path for OTP validation.
     *
     * @var string
     */
    protected $route = '/admin/settings/phones/validate-otp';

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
        $otp     = isset( $payload['otp'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( $payload['otp'] ) ) : '';

        if ( empty( $phone ) || empty( $otp ) ) {
            return $this->error_response( esc_html__( 'Fill in the phone number and OTP code.', 'joinotify' ) );
        }

        if ( ! Otp_Validation::validate_otp( $phone, $otp ) ) {
            return $this->error_response( esc_html__( 'Invalid or expired OTP code.', 'joinotify' ) );
        }

        Phone_Manager::add_sender( $phone );
        Controller::get_connection_state( $phone );

        return $this->success_response( array(
            'message' => esc_html__( 'Your WhatsApp was verified successfully!', 'joinotify' ),
            'phones'  => Registry::get_phone_state(),
        ) );
    }
}
