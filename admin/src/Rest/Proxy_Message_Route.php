<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Logger;
use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Shared base for proxy-API message endpoints.
 *
 * Both Send_Text_Message and Send_Media_Message share identical
 * registration conditions and API-key authentication. This class
 * centralises that logic so each concrete endpoint only needs to
 * declare its route suffix and implement handle_message().
 *
 * @since 1.4.7
 * @deprecated 2.3.0 The Proxy API is deprecated and will be removed in an
 *             upcoming release. Deliver messages through the Joinotify API
 *             (official WhatsApp Cloud API) instead.
 * @package MeuMouse\Joinotify\Rest
 */
abstract class Proxy_Message_Route extends Abstract_Route {

    /**
     * Notice advertised to API consumers on every proxy response.
     *
     * Kept untranslated on purpose: HTTP header values must stay ASCII.
     *
     * @since 2.3.0
     * @var string
     */
    const DEPRECATION_NOTICE = 'The Joinotify Proxy API is deprecated and will be removed in an upcoming release. Migrate to the Joinotify API (official WhatsApp Cloud API).';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Register the route only when proxy messaging is explicitly enabled.
     *
     * The endpoint is off by default and still demands the `X-API-Key` header,
     * so turning it on is a deliberate act by the site owner.
     *
     * @since 1.4.7
     * @version 2.4.0
     * @return bool
     */
    protected function should_register() {
        return Admin::get_setting( 'enable_proxy_api' ) === 'yes';
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


    /**
     * Handle the request and stamp the deprecation notice on the response.
     *
     * Every call is logged so integrators still pointing at this endpoint can be
     * identified before the routes are removed.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    final public function handle( WP_REST_Request $request ) {
        Logger::register_log( sprintf( 'Joinotify: the deprecated Proxy API route "%s" was called. Migrate to the Joinotify API (official WhatsApp Cloud API) before the route is removed.', $this->route ), 'WARNING' );

        $response = rest_ensure_response( $this->handle_message( $request ) );

        $response->header( 'Deprecation', 'true' );
        $response->header( 'X-Joinotify-Deprecation', self::DEPRECATION_NOTICE );

        return $response;
    }


    /**
     * Deliver the message described by the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    abstract protected function handle_message( WP_REST_Request $request );
}
