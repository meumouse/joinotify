<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Cloud_Contacts;
use MeuMouse\Joinotify\Api\Connect;
use MeuMouse\Joinotify\Api\Sender_Sync;
use MeuMouse\Joinotify\Core\Helpers;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Import the numbers connected on the Joinotify panel as senders.
 *
 * @since 2.3.0
 * @version 2.8.0
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
     * @version 2.8.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $result = Sender_Sync::sync();

        // A sync is the "refresh" of the connection card, so it also asks again
        // what the key may do, and learns the account name a site connected
        // before 2.8.0 never stored.
        if ( Helpers::cloud_api_ready() ) {
            Cloud_Contacts::capability( true );

            if ( ! is_wp_error( $result ) && '' === Connect::account_name() ) {
                Connect::refresh_account_name();
            }
        }

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message(), array(
                'phones' => Registry::get_phone_state(),
                'connection' => Cloud_Connect_Key::connection_state(),
            ) );
        }

        return $this->success_response( array(
            'connection' => Cloud_Connect_Key::connection_state(),
            'message' => sprintf(
                /* translators: %d: number of imported WhatsApp numbers. */
                _n( '%d number imported from your Joinotify account.', '%d numbers imported from your Joinotify account.', count( $result ), 'joinotify' ),
                count( $result )
            ),
            'phones' => Registry::get_phone_state(),
        ) );
    }
}
