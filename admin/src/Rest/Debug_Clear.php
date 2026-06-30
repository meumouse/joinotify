<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Debug_Log;
use MeuMouse\Joinotify\Core\Logger;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Clear the debug logs (structured table + flat file).
 *
 * @since 1.1.0
 * @version 2.1.0
 */
class Debug_Clear extends Abstract_Route {

    /**
     * Route path for clearing logs.
     *
     * @var string
     */
    protected $route = '/admin/settings/debug/clear';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        Debug_Log::clear_all();
        Logger::clear_log();

        if ( Debug_Log::has_logs() ) {
            return $this->error_response( esc_html__( 'Could not clear the debug logs.', 'joinotify' ) );
        }

        return $this->success_response( array(
            'message' => __( 'Debug logs cleared successfully!', 'joinotify' ),
        ) );
    }
}
