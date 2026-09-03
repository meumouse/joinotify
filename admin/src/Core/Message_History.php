<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/*
 * This class is the sole gateway to the plugin's own `joinotify_message_history`
 * table, which no WordPress API covers, so every read and write here is a direct
 * query by design. The rows are admin-screen audit data read behind a capability
 * check and invalidated on every send, so an object cache would serve stale
 * counts more often than it would save a query.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Persist and query a history of dispatched WhatsApp messages.
 *
 * Every message that flows through the API controller (immediate workflow
 * sends, retry-queue dispatches, test messages and proxy/API sends) is
 * recorded in a dedicated table so it can be audited from the admin screen.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Message_History {

    /**
     * Table base name (without the WordPress table prefix).
     *
     * @since 2.0.0
     * @var string
     */
    const TABLE = 'joinotify_message_history';

    /**
     * Schema version. Bump to trigger a dbDelta migration.
     *
     * @since 2.0.0
     * @var string
     */
    const DB_VERSION = '1.2.0';

    /**
     * Option key that stores the installed schema version.
     *
     * @since 2.0.0
     * @var string
     */
    const DB_VERSION_OPTION = 'joinotify_message_history_db_version';

    /**
     * Cron hook for the retention purge.
     *
     * @since 2.0.0
     * @var string
     */
    const PURGE_HOOK = 'joinotify_purge_message_history_event';

    /**
     * Allowed message types.
     *
     * @since 2.0.0
     * @version 2.3.0
     * @var string[]
     */
    const MESSAGE_TYPES = array( 'text', 'media', 'audio', 'template' );

    /**
     * Allowed dispatch sources.
     *
     * @since 2.0.0
     * @var string[]
     */
    const SOURCES = array( 'workflow', 'queue', 'test', 'otp', 'api' );

    /**
     * Allowed delivery statuses.
     *
     * `sent` only means the API accepted the message. `delivered`, `read` and
     * the `failed` that follows a rejection arrive later, by webhook.
     * `cancelled` is the one nobody's server decides: it is a `queued` row whose
     * resend was called off from the history screen.
     *
     * @since 2.0.0
     * @version 2.4.0
     * @var string[]
     */
    const STATUSES = array( 'sent', 'failed', 'queued', 'cancelled', 'delivered', 'read' );

    /**
     * Context shared by the current dispatch (workflow id / source).
     *
     * Mirrors the static-flag pattern used by Workflow_Processor so call sites
     * can attach origin metadata without changing the static Controller method
     * signatures.
     *
     * @since 2.0.0
     * @var array<string,mixed>
     */
    private static $context = array();


    /**
     * Construct function.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        // Ensure the table exists (cheap version-guarded check) and after upgrades.
        add_action( 'admin_init', array( __CLASS__, 'maybe_create_table' ), 5 );
        add_action( 'Joinotify/Upgraded', array( __CLASS__, 'maybe_create_table' ) );

        // Daily retention purge.
        add_action( self::PURGE_HOOK, array( __CLASS__, 'purge_old' ) );

        if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
        }
    }


    /**
     * Get the fully-qualified history table name.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }


    /**
     * Create or upgrade the history table, guarded by the stored schema version.
     *
     * @since 2.0.0
     * @return void
     */
    public static function maybe_create_table() {
        if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        global $wpdb;

        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            workflow_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(20) NOT NULL DEFAULT 'api',
            sender VARCHAR(20) NOT NULL DEFAULT '',
            receiver VARCHAR(32) NOT NULL DEFAULT '',
            message_type VARCHAR(20) NOT NULL DEFAULT 'text',
            media_type VARCHAR(20) NOT NULL DEFAULT '',
            content LONGTEXT NULL,
            media_url TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'failed',
            response_code SMALLINT(6) NOT NULL DEFAULT 0,
            error VARCHAR(191) NOT NULL DEFAULT '',
            attempts SMALLINT(6) NOT NULL DEFAULT 0,
            wamid VARCHAR(128) NOT NULL DEFAULT '',
            queue_id VARCHAR(64) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY receiver (receiver),
            KEY sender (sender),
            KEY status (status),
            KEY source (source),
            KEY workflow_id (workflow_id),
            KEY wamid (wamid),
            KEY queue_id (queue_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }


    /**
     * Set the dispatch context for the next recorded message(s).
     *
     * @since 2.0.0
     * @param array<string,mixed> $context Keys: workflow_id, source.
     * @return void
     */
    public static function set_context( $context ) {
        self::$context = is_array( $context ) ? $context : array();
    }


    /**
     * Clear the dispatch context.
     *
     * @since 2.0.0
     * @return void
     */
    public static function clear_context() {
        self::$context = array();
    }


    /**
     * Record a dispatched message.
     *
     * @since 2.0.0
     * @param array<string,mixed> $entry Message fields. Recognized keys:
     *        sender, receiver, message_type, media_type, content, media_url,
     *        status, response_code, error, attempts, source, workflow_id.
     * @return int|false Inserted row ID, or false when skipped/failed.
     */
    public static function record( $entry ) {
        if ( Admin::get_setting('enable_message_history') !== 'yes' ) {
            return false;
        }

        if ( ! is_array( $entry ) ) {
            return false;
        }

        // Merge the shared dispatch context (workflow_id / source).
        $entry = array_merge( self::$context, $entry );

        /**
         * Allow short-circuiting a history record before it is written.
         *
         * @since 2.0.0
         * @param bool $should_record Whether to record this entry.
         * @param array $entry Normalized-ish entry data.
         */
        if ( ! apply_filters( 'Joinotify/Message_History/Should_Record', true, $entry ) ) {
            return false;
        }

        global $wpdb;

        $source = isset( $entry['source'] ) ? sanitize_key( $entry['source'] ) : 'api';
        $message_type = isset( $entry['message_type'] ) ? sanitize_key( $entry['message_type'] ) : 'text';
        $status = isset( $entry['status'] ) ? sanitize_key( $entry['status'] ) : 'failed';

        $data = array(
            'created_at' => current_time( 'mysql', true ),
            'workflow_id' => isset( $entry['workflow_id'] ) ? absint( $entry['workflow_id'] ) : 0,
            'source' => in_array( $source, self::SOURCES, true ) ? $source : 'api',
            'sender' => sanitize_text_field( $entry['sender'] ?? '' ),
            'receiver' => sanitize_text_field( $entry['receiver'] ?? '' ),
            'message_type' => in_array( $message_type, self::MESSAGE_TYPES, true ) ? $message_type : 'text',
            'media_type' => sanitize_key( $entry['media_type'] ?? '' ),
            'content' => wp_kses_post( (string) ( $entry['content'] ?? '' ) ),
            'media_url' => esc_url_raw( $entry['media_url'] ?? '' ),
            'status' => in_array( $status, self::STATUSES, true ) ? $status : 'failed',
            'response_code' => isset( $entry['response_code'] ) ? (int) $entry['response_code'] : 0,
            'error' => substr( sanitize_text_field( $entry['error'] ?? '' ), 0, 191 ),
            'attempts' => isset( $entry['attempts'] ) ? (int) $entry['attempts'] : 0,
            // The WhatsApp message id is what later delivery webhooks key on.
            'wamid' => substr( sanitize_text_field( $entry['wamid'] ?? '' ), 0, 128 ),
            // Set only on a `queued` row: the retry-queue item this record can
            // still call off, which is what makes "cancel resend" possible.
            'queue_id' => substr( sanitize_text_field( $entry['queue_id'] ?? '' ), 0, 64 ),
        );

        $formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' );

        $inserted = $wpdb->insert( self::get_table_name(), $data, $formats );

        if ( ! $inserted ) {
            return false;
        }

        $id = (int) $wpdb->insert_id;

        /**
         * Fires after a message history record is written.
         *
         * @since 2.0.0
         * @param int $id Inserted row ID.
         * @param array $data Stored row data.
         */
        do_action( 'Joinotify/Message_History/Recorded', $id, $data );

        return $id;
    }


    /**
     * Update the delivery outcome of a message identified by its WhatsApp id.
     *
     * A `201` at send time only means the API accepted the message; whether it
     * reached the device, was opened or was rejected arrives minutes later on
     * the status webhook.
     *
     * @since 2.3.0
     * @param string $wamid | WhatsApp message id (wamid...).
     * @param string $status | New delivery status.
     * @param string $error | Failure reason, when the status is a failure.
     * @return bool True when a row was updated.
     */
    public static function update_status_by_wamid( $wamid, $status, $error = '' ) {
        $wamid = sanitize_text_field( (string) $wamid );
        $status = sanitize_key( (string) $status );

        if ( '' === $wamid || ! in_array( $status, self::STATUSES, true ) ) {
            return false;
        }

        global $wpdb;

        $data = array( 'status' => $status );
        $formats = array( '%s' );

        if ( '' !== $error ) {
            $data['error'] = substr( sanitize_text_field( $error ), 0, 191 );
            $formats[] = '%s';
        }

        $updated = $wpdb->update( self::get_table_name(), $data, array( 'wamid' => $wamid ), $formats, array( '%s' ) );

        if ( ! $updated ) {
            return false;
        }

        /**
         * Fires after a delivery status update lands on a history record.
         *
         * @since 2.3.0
         * @param string $wamid WhatsApp message id.
         * @param string $status New delivery status.
         * @param string $error Failure reason, when any.
         */
        do_action( 'Joinotify/Message_History/Status_Updated', $wamid, $status, $error );

        return true;
    }


    /**
     * Build the WHERE clause and prepared args from query filters.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Filter args.
     * @return array{0:string,1:array} SQL fragment and prepare args.
     */
    /*
     * The fragment returned below is assembled only from literal SQL written in this
     * method — every caller-supplied value becomes a %s/%d placeholder whose value is
     * appended to the returned args array. Callers therefore interpolate the fragment
     * into the query and pass the args to $wpdb->prepare().
     */
    private static function build_where( $args ) {
        global $wpdb;

        $where = array( '1=1' );
        $values = array();

        $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $source = isset( $args['source'] ) ? sanitize_key( $args['source'] ) : '';
        if ( in_array( $source, self::SOURCES, true ) ) {
            $where[] = 'source = %s';
            $values[] = $source;
        }

        $search = isset( $args['search'] ) ? trim( sanitize_text_field( $args['search'] ) ) : '';
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '( receiver LIKE %s OR sender LIKE %s )';
            $values[] = $like;
            $values[] = $like;
        }

        $date_from = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : '';
        if ( '' !== $date_from ) {
            $where[] = 'created_at >= %s';
            $values[] = $date_from . ' 00:00:00';
        }

        $date_to = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : '';
        if ( '' !== $date_to ) {
            $where[] = 'created_at <= %s';
            $values[] = $date_to . ' 23:59:59';
        }

        return array( implode( ' AND ', $where ), $values );
    }


    /**
     * Get a paginated list of history items.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Filter + pagination args.
     * @return array<int,array<string,mixed>>
     */
    public static function get_items( $args = array() ) {
        global $wpdb;

        $table = self::get_table_name();
        list( $where, $values ) = self::build_where( $args );

        $per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 20;
        $page = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
        $offset = ( $page - 1 ) * $per_page;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql holds only the literal query, the $wpdb->prefix table name and the placeholder-only fragment from build_where(); all values are bound here.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

        return is_array( $rows ) ? $rows : array();
    }


    /**
     * Count history items matching the given filters.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Filter args.
     * @return int
     */
    public static function count_items( $args = array() ) {
        global $wpdb;

        $table = self::get_table_name();
        list( $where, $values ) = self::build_where( $args );

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        if ( empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Unfiltered count: $sql is the literal query plus the $wpdb->prefix table name, with no caller input to bind.
            return (int) $wpdb->get_var( $sql );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql holds only the literal query, the $wpdb->prefix table name and the placeholder-only fragment from build_where(); all values are bound here.
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }


    /**
     * Count history items grouped by status.
     *
     * @since 2.0.0
     * @return array<string,int>
     */
    public static function get_counts_by_status() {
        global $wpdb;

        $table = self::get_table_name();
        $counts = array( 'all' => 0, 'sent' => 0, 'failed' => 0, 'queued' => 0, 'cancelled' => 0 );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix and a class constant; the query takes no input.
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $status = isset( $row['status'] ) ? (string) $row['status'] : '';
                $total = isset( $row['total'] ) ? (int) $row['total'] : 0;

                if ( isset( $counts[ $status ] ) ) {
                    $counts[ $status ] = $total;
                }

                $counts['all'] += $total;
            }
        }

        return $counts;
    }


    /**
     * Delete history rows by ID.
     *
     * @since 2.0.0
     * @param int[] $ids Row IDs.
     * @return int Number of rows deleted.
     */
    public static function delete_items( $ids ) {
        global $wpdb;

        $ids = array_filter( array_map( 'absint', (array) $ids ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        $table = self::get_table_name();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table comes from $wpdb->prefix and $placeholders is a generated list of %d tokens, one per absint()-cast ID, all bound here.
        return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
    }


    /**
     * Close out the row that parked a message in the retry queue.
     *
     * The row written when the send first failed stays `queued` until the queue
     * item it points at is resolved — delivered on a later attempt, or dropped
     * for running out of them. Without this it would sit there forever claiming
     * a retry that is no longer coming, and the history screen would offer to
     * cancel a resend that does not exist.
     *
     * @since 2.4.0
     * @param string $queue_id | Retry-queue item id.
     * @param string $status | Status to settle the row on.
     * @param string $error | Failure reason, when any.
     * @return bool True when a row was updated.
     */
    public static function resolve_queue_item( $queue_id, $status, $error = '' ) {
        $queue_id = sanitize_text_field( (string) $queue_id );
        $status = sanitize_key( (string) $status );

        if ( '' === $queue_id || ! in_array( $status, self::STATUSES, true ) ) {
            return false;
        }

        global $wpdb;

        $data = array( 'status' => $status, 'queue_id' => '' );
        $formats = array( '%s', '%s' );

        if ( '' !== $error ) {
            $data['error'] = substr( sanitize_text_field( $error ), 0, 191 );
            $formats[] = '%s';
        }

        $updated = $wpdb->update(
            self::get_table_name(),
            $data,
            array( 'queue_id' => $queue_id, 'status' => 'queued' ),
            $formats,
            array( '%s', '%s' )
        );

        return (bool) $updated;
    }


    /**
     * Fetch the queue item ids of rows still waiting for a retry.
     *
     * Only `queued` rows carry one, so the result is exactly the subset of the
     * selection whose resend can still be called off.
     *
     * @since 2.4.0
     * @param int[] $ids Row IDs.
     * @return array<int,string> Queue item id keyed by history row id.
     */
    public static function get_pending_queue_ids( $ids ) {
        global $wpdb;

        $ids = array_filter( array_map( 'absint', (array) $ids ) );

        if ( empty( $ids ) ) {
            return array();
        }

        $table = self::get_table_name();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table comes from $wpdb->prefix and $placeholders is a generated list of %d tokens, one per absint()-cast ID, all bound here.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, queue_id FROM {$table} WHERE status = 'queued' AND queue_id <> '' AND id IN ({$placeholders})", $ids ), ARRAY_A );

        $map = array();

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $map[ (int) $row['id'] ] = (string) $row['queue_id'];
            }
        }

        return $map;
    }


    /**
     * Mark rows as having had their resend cancelled.
     *
     * @since 2.4.0
     * @param int[] $ids Row IDs.
     * @param string $error Failure code stored on the row.
     * @return int Number of rows updated.
     */
    public static function mark_cancelled( $ids, $error = 'retry_cancelled' ) {
        global $wpdb;

        $ids = array_filter( array_map( 'absint', (array) $ids ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        $table = self::get_table_name();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $values = array_merge( array( substr( sanitize_text_field( $error ), 0, 191 ) ), $ids );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table comes from $wpdb->prefix and $placeholders is a generated list of %d tokens, one per absint()-cast ID; every value is bound here.
        $updated = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'cancelled', queue_id = '', error = %s WHERE status = 'queued' AND id IN ({$placeholders})", $values ) );

        if ( $updated > 0 ) {
            /**
             * Fires after queued rows had their resend cancelled.
             *
             * @since 2.4.0
             * @param int[] $ids Row IDs that were asked to cancel.
             * @param int $updated Number of rows actually updated.
             */
            do_action( 'Joinotify/Message_History/Retry_Cancelled', $ids, $updated );
        }

        return $updated;
    }


    /**
     * Delete every history row.
     *
     * @since 2.0.0
     * @return int Number of rows deleted.
     */
    public static function clear_all() {
        global $wpdb;

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix and a class constant; the query takes no input.
        return (int) $wpdb->query( "DELETE FROM {$table}" );
    }


    /**
     * Purge records older than the configured retention window.
     *
     * @since 2.0.0
     * @return int Number of rows deleted.
     */
    public static function purge_old() {
        global $wpdb;

        $days = (int) Admin::get_setting('message_history_retention_days');

        /**
         * Filter the retention window (in days). 0 disables auto-purge.
         *
         * @since 2.0.0
         * @param int $days Retention window in days.
         */
        $days = (int) apply_filters( 'Joinotify/Message_History/Retention_Days', $days );

        if ( $days <= 0 ) {
            return 0;
        }

        $table = self::get_table_name();
        $threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix and a class constant; $threshold is bound as %s.
        return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $threshold ) );
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
