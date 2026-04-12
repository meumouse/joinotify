<?php
/**
 * Phone_Register source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Controller;
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
        $phone = isset( $payload['phone'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( $payload['phone'] ) ) : '';

        if ( empty( $phone ) ) {
            return rest_ensure_response( array(
                'status' => 'error',
                'message' => esc_html__( 'Número de telefone inválido.', 'joinotify' ),
            ) );
        }

        if ( ! Otp_Validation::generate_and_send_otp( $phone ) ) {
            return rest_ensure_response( array(
                'status' => 'error',
                'message' => esc_html__( 'Não foi possível enviar o código de verificação.', 'joinotify' ),
            ) );
        }

        return rest_ensure_response( array(
            'status' => 'success',
            'message' => esc_html__( 'Código enviado com sucesso.', 'joinotify' ),
            'phone' => $phone,
            'countdown' => 60,
        ) );
    }
}

