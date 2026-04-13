<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Admin\Admin;
use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Send a text message through the proxy API.
 */
class Send_Text_Message extends Abstract_Route {

    /**
     * Route path for proxy text messages.
     *
     * @var string
     */
    protected $route = '';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';

    /**
     * REST argument schema.
     *
     * @var array
     */
    protected $args = array(
        'sender' => array(
            'required' => true,
            'validate_callback' => 'is_string',
        ),
        'receiver' => array(
            'required' => true,
            'validate_callback' => 'is_string',
        ),
        'message' => array(
            'required' => true,
            'validate_callback' => 'is_string',
        ),
    );


    /**
     * Register the route only when proxy messaging is enabled and the license is valid.
     *
     * @return bool
     */
    protected function should_register() {
        return Admin::get_setting( 'enable_proxy_api' ) === 'yes' && License::is_valid();
    }


    /**
     * Register the route name dynamically from the saved settings.
     *
     * @return void
     */
    public function register_route() {
        $this->route = '/' . Admin::get_setting( 'send_text_proxy_api_route' );
        parent::register_route();
    }


    /**
     * Validate the proxy API key.
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
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $sender = $request->get_param( 'sender' );
        $receiver = $request->get_param( 'receiver' );
        $message = $request->get_param( 'message' );
        $delay = $request->get_param( 'delay' );
        $delay = is_numeric( $delay ) ? (int) $delay : 0;
        $response_code = Controller::send_message_text( $sender, $receiver, $message, $delay );

        if ( 201 === $response_code ) {
            return rest_ensure_response( array(
                'status' => 'success',
                'message' => esc_html__( 'Text message sent successfully.', 'joinotify' ),
            ) );
        }

        return rest_ensure_response( array(
            'status' => 'error',
            'message' => esc_html__( 'Failed to send text message.', 'joinotify' ),
        ) );
    }
}

