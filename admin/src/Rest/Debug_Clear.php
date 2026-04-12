<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Logger;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Clear the current debug log file.
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
        Logger::clear_log();

        return rest_ensure_response( array(
            'status' => ! Logger::has_logs() ? 'success' : 'error',
            'message' => ! Logger::has_logs()
                ? esc_html__( 'Registros de depuração limpos com sucesso!', 'joinotify' )
                : esc_html__( 'Não foi possível limpar os registros de depuração.', 'joinotify' ),
        ) );
    }
}
