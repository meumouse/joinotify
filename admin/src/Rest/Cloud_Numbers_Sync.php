<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Sender_Sync;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Import the numbers connected on the Joinotify panel as senders.
 *
 * @since 2.3.0
 */
class Cloud_Numbers_Sync extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud/numbers/sync';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $result = Sender_Sync::sync();

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message(), array(
                'phones' => Registry::get_phone_state(),
            ) );
        }

        return $this->success_response( array(
            'message' => sprintf(
                /* translators: %d: number of imported WhatsApp numbers. */
                _n( '%d number imported from your Joinotify account.', '%d numbers imported from your Joinotify account.', count( $result ), 'joinotify' ),
                count( $result )
            ),
            'phones' => Registry::get_phone_state(),
        ) );
    }
}
