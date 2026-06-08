<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Logger;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the current debug log entries.
 */
class Debug_Logs extends Abstract_Route {

    /**
     * Route path for debug logs.
     *
     * @var string
     */
    protected $route = '/admin/settings/debug/logs';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $log_content = Logger::read_log();

        if ( empty( $log_content ) ) {
            return $this->error_response( esc_html__( 'The debug log is empty.', 'joinotify' ), array(
                'content' => '',
            ) );
        }

        $lines = explode( "\n", $log_content );
        $content = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( '' !== $line ) {
                $content[] = $line;
            }
        }

        return $this->success_response( array(
            'content' => $content,
        ) );
    }
}
