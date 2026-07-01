<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Debug_Log;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the structured debug log entries (filterable + paginated).
 *
 * @since 1.1.0
 * @version 2.0.0
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
        $args = array(
            'level' => (string) $request->get_param( 'level' ),
            'channel' => (string) $request->get_param( 'channel' ),
            'search' => (string) $request->get_param( 'search' ),
            'date_from' => (string) $request->get_param( 'date_from' ),
            'date_to' => (string) $request->get_param( 'date_to' ),
            'page' => max( 1, (int) $request->get_param( 'page' ) ),
            'per_page' => (int) $request->get_param( 'per_page' ) ?: 50,
        );

        $items = Debug_Log::get_items( $args );
        $total = Debug_Log::count_items( $args );

        // Backwards-compatible flat lines for the previous text viewer.
        $content = array();

        foreach ( $items as $row ) {
            $content[] = Debug_Log::format_line( $row );
        }

        if ( empty( $items ) ) {
            return $this->error_response( esc_html__( 'The debug log is empty.', 'joinotify' ), array(
                'items' => array(),
                'content' => array(),
                'total' => 0,
                'page' => $args['page'],
                'per_page' => $args['per_page'],
                'counts' => Debug_Log::get_counts_by_level(),
                'channels' => Debug_Log::get_channels(),
            ) );
        }

        return $this->success_response( array(
            'items' => array_map( array( $this, 'format_item' ), $items ),
            'content' => $content,
            'total' => $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'counts' => Debug_Log::get_counts_by_level(),
            'channels' => Debug_Log::get_channels(),
        ) );
    }


    /**
     * Shape a raw row for the frontend table.
     *
     * @param array<string,mixed> $row Raw DB row.
     * @return array<string,mixed>
     */
    protected function format_item( $row ) {
        return array(
            'id' => (int) ( $row['id'] ?? 0 ),
            'created_at' => (string) ( $row['created_at'] ?? '' ),
            'level' => (string) ( $row['level'] ?? 'info' ),
            'channel' => (string) ( $row['channel'] ?? 'general' ),
            'message' => (string) ( $row['message'] ?? '' ),
            'context' => (string) ( $row['context'] ?? '' ),
            'code' => (string) ( $row['code'] ?? '' ),
            'hook' => (string) ( $row['hook'] ?? '' ),
            'request_url' => (string) ( $row['request_url'] ?? '' ),
            'response_code' => (int) ( $row['response_code'] ?? 0 ),
            'source' => trim( (string) ( $row['source_file'] ?? '' ) . ( ! empty( $row['source_line'] ) ? ':' . (int) $row['source_line'] : '' ) ),
        );
    }
}
