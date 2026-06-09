<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Admin\Admin;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Send a media message through the proxy API.
 *
 * Authentication and registration conditions are inherited from
 * Proxy_Message_Route; this class only declares the route suffix
 * and the media-specific argument schema.
 */
class Send_Media_Message extends Proxy_Message_Route {

    /**
     * REST argument schema.
     *
     * @var array
     */
    protected $args = array(
        'sender' => array(
            'required'          => true,
            'validate_callback' => 'is_string',
        ),
        'receiver' => array(
            'required'          => true,
            'validate_callback' => 'is_string',
        ),
        'media_type' => array(
            'required'          => true,
            'validate_callback' => 'is_string',
        ),
        'media_url' => array(
            'required'          => true,
            'validate_callback' => 'is_string',
        ),
    );


    /**
     * Register the route name dynamically from the saved settings.
     *
     * @return void
     */
    public function register_route() {
        $this->route = '/' . Admin::get_setting( 'send_media_proxy_api_route' );
        parent::register_route();
    }


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $sender     = $request->get_param( 'sender' );
        $receiver   = $request->get_param( 'receiver' );
        $media_type = $request->get_param( 'media_type' );
        $media_url  = $request->get_param( 'media_url' );
        $caption    = $request->get_param( 'caption' );
        $delay      = $request->get_param( 'delay' );
        $delay      = is_numeric( $delay ) ? (int) $delay : 0;

        if ( 201 === Controller::send_message_media( $sender, $receiver, $media_type, $media_url, $caption, $delay ) ) {
            return $this->success_response( array(
                'message' => __( 'Media message sent successfully.', 'joinotify' ),
            ) );
        }

        return $this->error_response( esc_html__( 'Failed to send media message.', 'joinotify' ) );
    }
}
