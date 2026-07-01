<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Debug_Log;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the debug log content for download, rendered from the structured table.
 *
 * @since 1.4.7
 * @version 2.0.0
 */
class Debug_Download extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/settings/debug/download';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle request.
     *
     * @param WP_REST_Request $request Request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $content = Debug_Log::render_text();

        if ( '' === $content ) {
            return $this->error_response( esc_html__( 'The debug log is empty.', 'joinotify' ) );
        }

        return $this->success_response( array(
            'message' => __( 'Log file ready for download.', 'joinotify' ),
            'filename' => 'joinotify-debug-logs.txt',
            'content' => $content,
        ) );
    }
}
