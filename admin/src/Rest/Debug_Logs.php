<?php
/**
 * Debug_Logs source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

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
            return rest_ensure_response( array(
                'status' => 'error',
                'message' => esc_html__( 'O registro de depuração está vazio.', 'joinotify' ),
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

        return rest_ensure_response( array(
            'status' => 'success',
            'content' => $content,
        ) );
    }
}
