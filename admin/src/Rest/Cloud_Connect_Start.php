<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Connect;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Begin the panel connection handshake.
 *
 * @since 2.3.0
 */
class Cloud_Connect_Start extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud/connect/start';

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
        return $this->success_response( Connect::start() );
    }
}
