<?php
/**
 * Phone_Remove source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
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
        $phone = isset( $payload['phone'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( $payload['phone'] ) ) : '';
        $phones_senders = get_option( 'joinotify_get_phones_senders', array() );
        $phones_senders = is_array( $phones_senders ) ? $phones_senders : array();

        if ( empty( $phone ) || ! in_array( $phone, $phones_senders, true ) ) {
            return rest_ensure_response( array(
                'status' => 'error',
                'message' => esc_html__( 'Não foi possível encontrar o telefone informado.', 'joinotify' ),
            ) );
        }

        $phones_senders = array_values( array_filter( $phones_senders, static function ( $item ) use ( $phone ) {
            return $item !== $phone;
        } ) );

        update_option( 'joinotify_get_phones_senders', $phones_senders );
        do_action( 'Joinotify/Remove_Phone/Success', $phone );

        return rest_ensure_response( array(
            'status' => 'success',
            'message' => esc_html__( 'O telefone remetente foi removido com sucesso!', 'joinotify' ),
            'phones' => Registry::get_phone_state(),
        ) );
    }
}
