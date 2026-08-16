<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Sender_Sync;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List the WhatsApp numbers connected on the Joinotify panel.
 *
 * @since 2.3.0
 */
class Cloud_Numbers extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud/numbers';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $force = 'true' === (string) $request->get_param('refresh');
        $numbers = Sender_Sync::fetch_numbers( $force );

        if ( is_wp_error( $numbers ) ) {
            return $this->error_response( $numbers->get_error_message(), array( 'numbers' => array() ) );
        }

        return $this->success_response( array(
            'numbers' => $numbers,
            'last_sync' => Sender_Sync::last_sync_time(),
            'panel_url' => JOINOTIFY_PANEL_URL,
        ) );
    }
}
