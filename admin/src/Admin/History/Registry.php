<?php

namespace MeuMouse\Joinotify\Admin\History;

use MeuMouse\Joinotify\Core\Message_History;
use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Message history registry.
 *
 * Centralizes the read logic and UI formatting used by both the history admin
 * bootstrap and the history REST endpoints, delegating storage to
 * Core\Message_History.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Admin\History
 * @author MeuMouse.com
 */
class Registry {

    /**
     * Default per-page used by the list screen.
     *
     * @var int
     */
    const PER_PAGE = 20;


    /**
     * Build a normalized list item from a stored row.
     *
     * @since 2.0.0
     * @param array<string,mixed> $row Raw DB row.
     * @return array<string,mixed>
     */
    public static function build_item( $row ) {
        $created_gmt = (string) ( $row['created_at'] ?? '' );
        $created_local = $created_gmt ? get_date_from_gmt( $created_gmt ) : '';
        $workflow_id = (int) ( $row['workflow_id'] ?? 0 );

        return array(
            'id' => (int) ( $row['id'] ?? 0 ),
            'created_at' => $created_local,
            'created_at_gmt' => $created_gmt,
            'workflow_id' => $workflow_id,
            'workflow_title' => $workflow_id ? get_the_title( $workflow_id ) : '',
            'workflow_edit_url' => $workflow_id ? admin_url( 'admin.php?page=joinotify-workflows-builder&id=' . $workflow_id ) : '',
            'source' => (string) ( $row['source'] ?? 'api' ),
            'sender' => (string) ( $row['sender'] ?? '' ),
            'receiver' => (string) ( $row['receiver'] ?? '' ),
            'message_type' => (string) ( $row['message_type'] ?? 'text' ),
            'media_type' => (string) ( $row['media_type'] ?? '' ),
            'content' => (string) ( $row['content'] ?? '' ),
            'media_url' => (string) ( $row['media_url'] ?? '' ),
            'status' => (string) ( $row['status'] ?? 'failed' ),
            'response_code' => (int) ( $row['response_code'] ?? 0 ),
            'error' => (string) ( $row['error'] ?? '' ),
            'attempts' => (int) ( $row['attempts'] ?? 0 ),
        );
    }


    /**
     * Normalize incoming filter/pagination args.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Raw args.
     * @return array<string,mixed>
     */
    public static function normalize_args( $args ) {
        return array(
            'status' => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '',
            'source' => isset( $args['source'] ) ? sanitize_key( $args['source'] ) : '',
            'search' => isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
            'date_from' => isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : '',
            'date_to' => isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : '',
            'page' => isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1,
            'per_page' => isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : self::PER_PAGE,
        );
    }


    /**
     * Build the full list state payload (items + counts + pagination).
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Filter + pagination args.
     * @return array<string,mixed>
     */
    public static function get_list_state( $args = array() ) {
        $args = self::normalize_args( $args );

        $rows = Message_History::get_items( $args );
        $items = array_map( array( __CLASS__, 'build_item' ), $rows );

        $total = Message_History::count_items( $args );
        $per_page = $args['per_page'];

        return array(
            'items' => $items,
            'counts' => Message_History::get_counts_by_status(),
            'pagination' => array(
                'current_page' => $args['page'],
                'per_page' => $per_page,
                'total_items' => $total,
                'total_pages' => (int) max( 1, ceil( $total / $per_page ) ),
            ),
        );
    }


    /**
     * Available source filter options.
     *
     * @since 2.0.0
     * @return array<int,array<string,string>>
     */
    public static function get_source_options() {
        return array(
            array( 'value' => '', 'label' => __( 'All sources', 'joinotify' ) ),
            array( 'value' => 'workflow', 'label' => __( 'Workflow', 'joinotify' ) ),
            array( 'value' => 'queue', 'label' => __( 'Retry queue', 'joinotify' ) ),
            array( 'value' => 'test', 'label' => __( 'Test message', 'joinotify' ) ),
            array( 'value' => 'otp', 'label' => __( 'OTP', 'joinotify' ) ),
            array( 'value' => 'api', 'label' => __( 'API', 'joinotify' ) ),
        );
    }


    /**
     * Build the bootstrap payload for the history Vue screen.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public static function get_bootstrap_data() {
        $list = self::get_list_state();

        return array(
            'page' => 'history',
            'title' => __( 'Message history', 'joinotify' ),
            'date_format' => get_option( 'date_format' ),
            'time_format' => get_option( 'time_format' ),
            'enabled' => Admin::get_setting( 'enable_message_history' ) === 'no' ? 'no' : 'yes',
            'sources' => self::get_source_options(),
            'items' => $list['items'],
            'counts' => $list['counts'],
            'pagination' => $list['pagination'],
            'rest' => array(
                'root' => esc_url_raw( rest_url( 'joinotify/v1' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ),
        );
    }
}
