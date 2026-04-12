<?php
/**
 * Phone_Candidates source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Core\Helpers;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the available phone candidates for registration.
 */
class Phone_Candidates extends Abstract_Route {

    /**
     * Route path for phone candidates.
     *
     * @var string
     */
    protected $route = '/admin/settings/phones/candidates';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $response_data = Controller::get_numbers();
        $phone_numbers = isset( $response_data['slots'] ) && is_array( $response_data['slots'] ) ? $response_data['slots'] : array();
        $registered_phones = get_option( 'joinotify_get_phones_senders', array() );
        $registered_phones = is_array( $registered_phones ) ? $registered_phones : array();
        $filtered = array();

        foreach ( $phone_numbers as $value ) {
            if ( ! is_array( $value ) || empty( $value['phone'] ) ) {
                continue;
            }

            if ( in_array( $value['phone'], $registered_phones, true ) ) {
                continue;
            }

            $filtered[] = array(
                'phone' => $value['phone'],
                'formatted' => Helpers::validate_and_format_phone( $value['phone'] ),
            );
        }

        if ( empty( $filtered ) ) {
            return rest_ensure_response( array(
                'status' => 'success',
                'empty_message' => sprintf(
                    esc_html__( 'Não foi encontrado nenhum telefone disponível para cadastro. Faça o cadastro pelo link: %s', 'joinotify' ),
                    esc_url( JOINOTIFY_REGISTER_PHONE_URL )
                ),
                'candidates' => array(),
            ) );
        }

        return rest_ensure_response( array(
            'status' => 'success',
            'candidates' => $filtered,
        ) );
    }
}

