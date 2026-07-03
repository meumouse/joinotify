<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

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
    const DB_VERSION = '1.0.0';

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
     * @var string[]
     */
    const MESSAGE_TYPES = array( 'text', 'media', 'audio' );

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
     * @since 2.0.0
     * @var string[]
     */
    const STATUSES = array( 'sent', 'failed', 'queued' );

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
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY receiver (receiver),
            KEY sender (sender),
            KEY status (status),
            KEY source (source),
            KEY workflow_id (workflow_id)
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
        );

        $formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' );

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
     * Build the WHERE clause and prepared args from query filters.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Filter args.
     * @return array{0:string,1:array} SQL fragment and prepare args.
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
            return (int) $wpdb->get_var( $sql );
        }

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
        $counts = array( 'all' => 0, 'sent' => 0, 'failed' => 0, 'queued' => 0 );

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

        return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
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

        return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $threshold ) );
    }
}
