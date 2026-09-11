<?php

namespace MeuMouse\Joinotify\Admin\History;

use MeuMouse\Joinotify\Api\Send_Error;
use MeuMouse\Joinotify\Core\Message_History;
use MeuMouse\Joinotify\Core\Notification_Queue;
use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Admin\Export;

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
     * Default per-page used by the list screen. One of the page sizes the
     * screen offers (10, 25, 50, 100, 200).
     *
     * @since 2.0.0
     * @version 2.4.2
     * @var int
     */
    const PER_PAGE = 25;

    /**
     * Most records a single "export all" writes, newest first. Keeps the
     * request inside the memory a shared host gives PHP.
     *
     * @since 2.4.2
     * @var int
     */
    const EXPORT_LIMIT = 5000;


    /**
     * Build a normalized list item from a stored row.
     *
     * The stored `error` is the transport's machine code; `error_label` is the
     * same failure written for whoever is reading the screen — which is how the
     * closed 24-hour window stops looking like an opaque slug.
     *
     * @since 2.0.0
     * @version 2.4.1
     * @param array<string,mixed> $row Raw DB row.
     * @return array<string,mixed>
     */
    public static function build_item( $row ) {
        $created_gmt = (string) ( $row['created_at'] ?? '' );
        $created_local = $created_gmt ? get_date_from_gmt( $created_gmt ) : '';
        $workflow_id = (int) ( $row['workflow_id'] ?? 0 );
        $error = (string) ( $row['error'] ?? '' );

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
            // Name, language and parameters of a template send; null for any
            // other message, and for rows recorded before schema 1.3.0.
            'template' => self::build_template( $row['meta'] ?? '' ),
            'status' => (string) ( $row['status'] ?? 'failed' ),
            'response_code' => (int) ( $row['response_code'] ?? 0 ),
            'error' => $error,
            // An unmapped code falls back to itself: better a raw slug on screen
            // than a generic sentence that hides which failure this was.
            'error_label' => '' !== $error ? Send_Error::describe( $error, $error ) : '',
            'attempts' => (int) ( $row['attempts'] ?? 0 ),
            // True only while a resend is actually still on the books, which is
            // what the "Cancel resend" action acts on.
            'can_cancel_retry' => 'queued' === (string) ( $row['status'] ?? '' ) && '' !== (string) ( $row['queue_id'] ?? '' ),
        );
    }


    /**
     * Read the template details out of a row's `meta` column.
     *
     * @since 2.4.1
     * @param mixed $meta Raw `meta` column value.
     * @return array<string,mixed>|null
     */
    public static function build_template( $meta ) {
        $template = Message_History::decode_meta( $meta )['template'] ?? null;

        if ( ! is_array( $template ) || empty( $template['name'] ) ) {
            return null;
        }

        $parameters = array();

        foreach ( (array) ( $template['parameters'] ?? array() ) as $parameter ) {
            if ( ! is_array( $parameter ) ) {
                continue;
            }

            $parameters[] = array(
                'component' => (string) ( $parameter['component'] ?? 'body' ),
                'index' => (int) ( $parameter['index'] ?? 0 ),
                'key' => (string) ( $parameter['key'] ?? '' ),
                'value' => (string) ( $parameter['value'] ?? '' ),
            );
        }

        return array(
            'name' => (string) $template['name'],
            'language' => (string) ( $template['language'] ?? '' ),
            'rendered_from' => (string) ( $template['rendered_from'] ?? '' ),
            'masked' => ! empty( $template['masked'] ),
            'parameters' => $parameters,
            'components' => is_array( $template['components'] ?? null ) ? $template['components'] : array(),
        );
    }


    /**
     * Cancel the pending resend of the given history rows.
     *
     * Only rows still parked in the retry queue are touched: one whose queue
     * item has already been delivered or exhausted has nothing left to call off,
     * and is reported back as such rather than being mislabelled as cancelled.
     *
     * @since 2.4.0
     * @param int[] $ids History row IDs.
     * @return array{cancelled:int,skipped:int}
     */
    public static function cancel_retry( $ids ) {
        $ids = array_filter( array_map( 'absint', (array) $ids ) );
        $queue_ids = Message_History::get_pending_queue_ids( $ids );

        if ( empty( $queue_ids ) ) {
            return array( 'cancelled' => 0, 'skipped' => count( $ids ) );
        }

        $pending = Notification_Queue::filter_pending_ids( array_values( $queue_ids ) );

        $rows = array();

        foreach ( $queue_ids as $row_id => $queue_id ) {
            if ( in_array( $queue_id, $pending, true ) ) {
                $rows[] = (int) $row_id;
            }
        }

        if ( empty( $rows ) ) {
            return array( 'cancelled' => 0, 'skipped' => count( $ids ) );
        }

        Notification_Queue::cancel( $pending );

        $cancelled = Message_History::mark_cancelled( $rows );

        return array(
            'cancelled' => $cancelled,
            'skipped' => max( 0, count( $ids ) - $cancelled ),
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
     * Build an exported record from a stored row.
     *
     * The list item minus what only the screen needs (the link back into the
     * builder and the "Cancel resend" flag), plus the WhatsApp message id and
     * the retry-queue item that the list leaves out.
     *
     * @since 2.4.2
     * @param array<string,mixed> $row Raw DB row.
     * @return array<string,mixed>
     */
    public static function build_export_item( $row ) {
        $item = self::build_item( $row );

        unset( $item['workflow_edit_url'], $item['can_cancel_retry'] );

        $item['wamid'] = (string) ( $row['wamid'] ?? '' );
        $item['queue_id'] = (string) ( $row['queue_id'] ?? '' );

        return $item;
    }


    /**
     * Build the JSON export of history records.
     *
     * `ids` exports those rows. `all` exports every row matching the filters
     * sent next to it, newest first, up to the export limit; the payload then
     * carries those filters, the matching total and whether it was cut short.
     *
     * @since 2.4.2
     * @param array<string,mixed> $params Request body: `ids`, or `all` plus the list filters.
     * @return array{filename:string,payload:array<string,mixed>}|null Null when no row matches.
     */
    public static function export_items( $params ) {
        $params = is_array( $params ) ? $params : array();
        $data = array();

        if ( ! empty( $params['all'] ) ) {
            $args = self::normalize_args( $params );
            $total = Message_History::count_items( $args );
            $rows = self::get_rows_for_export( $args );

            $data['filters'] = array(
                'status' => $args['status'],
                'source' => $args['source'],
                'search' => $args['search'],
                'date_from' => $args['date_from'],
                'date_to' => $args['date_to'],
            );
        } else {
            $rows = Message_History::get_items_by_ids( $params['ids'] ?? array() );
            $total = count( $rows );
        }

        if ( empty( $rows ) ) {
            return null;
        }

        $items = array_map( array( __CLASS__, 'build_export_item' ), $rows );

        $data['total'] = $total;
        $data['truncated'] = count( $items ) < $total;
        $data['items'] = $items;

        return array(
            'filename' => Export::build_filename('message-history'),
            'payload' => Export::build_payload( 'joinotify_message_history_export', $data ),
        );
    }


    /**
     * Read every row matching the filters, newest first, up to the export limit.
     *
     * @since 2.4.2
     * @param array<string,mixed> $args Normalized filter args.
     * @return array<int,array<string,mixed>>
     */
    protected static function get_rows_for_export( $args ) {
        /**
         * Filter how many history records a single "export all" writes.
         *
         * @since 2.4.2
         * @param int $limit Maximum number of records.
         */
        $limit = max( 1, (int) apply_filters( 'Joinotify/Admin/History/Export_Limit', self::EXPORT_LIMIT ) );
        $batch_size = 200;
        $rows = array();
        $page = 1;

        while ( count( $rows ) < $limit ) {
            $batch = Message_History::get_items( array_merge( $args, array(
                'page' => $page,
                'per_page' => $batch_size,
            ) ) );

            $rows = array_merge( $rows, $batch );

            if ( count( $batch ) < $batch_size ) {
                break;
            }

            $page++;
        }

        return array_slice( $rows, 0, $limit );
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
