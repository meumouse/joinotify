<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Read the customer service window between one of the account numbers and a contact.
 *
 * The test message modal asks this when a recipient is chosen: free text only
 * reaches a contact whose window is open, so a closed one is the moment to
 * suggest a template instead.
 *
 * @since 2.8.0
 */
class Phone_Window extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/settings/phones/window';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * Answers `state` (`open`, `closed` or `unknown`), `expires_at` and
     * `remaining_seconds`. `unknown` means the platform could not be asked.
     *
     * @since 2.8.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $sender = preg_replace( '/\D/', '', sanitize_text_field( (string) $request->get_param('sender') ) );
        $receiver = preg_replace( '/\D/', '', sanitize_text_field( (string) $request->get_param('receiver') ) );

        if ( '' === $receiver ) {
            return $this->error_response( __( 'Enter the recipient number.', 'joinotify' ) );
        }

        return $this->success_response( array(
            'window' => joinotify_get_whatsapp_window( $receiver, $sender ),
        ) );
    }
}
