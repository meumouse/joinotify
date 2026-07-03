<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Admin\Admin;
use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Shared base for proxy-API message endpoints.
 *
 * Both Send_Text_Message and Send_Media_Message share identical
 * registration conditions and API-key authentication. This class
 * centralises that logic so each concrete endpoint only needs to
 * declare its route suffix and implement handle().
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Rest
 */
abstract class Proxy_Message_Route extends Abstract_Route {

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Register the route only when proxy messaging is enabled and the license is valid.
     *
     * @return bool
     */
    protected function should_register() {
        return Admin::get_setting( 'enable_proxy_api' ) === 'yes' && License::is_valid();
    }


    /**
     * Validate the proxy API key sent in the X-API-Key request header.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool|WP_Error
     */
    public function permission( WP_REST_Request $request ) {
        $api_key = $request->get_header( 'X-API-Key' );

        if ( $api_key && $api_key === Admin::get_setting( 'proxy_api_key' ) ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            esc_html__( 'Invalid or missing API key.', 'joinotify' ),
            array( 'status' => 403 )
        );
    }
}
