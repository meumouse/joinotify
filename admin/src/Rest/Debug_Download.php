<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Logger;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the raw debug log file content for download.
 *
 * @since 1.4.7
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
        $upload_dir = wp_upload_dir();
        $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'joinotify/logs.txt';

        if ( ! file_exists( $log_file ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => esc_html__( 'The log file was not found.', 'joinotify' ),
            ) );
        }

        $content = file_get_contents( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        return rest_ensure_response( array(
            'status'   => 'success',
            'message'  => esc_html__( 'Log file ready for download.', 'joinotify' ),
            'filename' => 'joinotify-debug-logs.txt',
            'content'  => $content !== false ? $content : '',
        ) );
    }
}
