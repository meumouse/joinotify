<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Connect;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Connect this site to Joinotify Cloud using the license it already has.
 *
 * The shortest path for a customer who bought the plugin: no account to create, no
 * panel to visit, no key to paste. The license is traded server to server for a key
 * scoped to this site, and the connected numbers come back in the same answer.
 *
 * @since 2.3.1
 */
class Cloud_Connect_License extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud/connect/license';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.3.1
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $result = Connect::connect_with_license();

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message() );
        }

        return $this->success_response( array(
            'message' => __( 'Your Joinotify account is connected.', 'joinotify' ),
            // The ids are echoed back so the settings form does not overwrite the
            // freshly stored values with the ones it loaded before connecting. The
            // key itself is write-only, so only its public prefix travels: a blank
            // token in the next save is preserved by Repository::preserve_write_only().
            'connection' => Cloud_Connect_Key::connection_state(),
            'waba_id' => $result['waba_id'],
            'phone_number_id' => $result['phone_number_id'],
            'account' => $result['account'],
            'sync_error' => $result['sync_error'],
            'phones' => Registry::get_phone_state(),
        ) );
    }
}
