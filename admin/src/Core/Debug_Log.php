<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Structured debug logging backed by a dedicated database table.
 *
 * Replaces the flat-file logging as the primary store: every error, warning,
 * failed request, fired plugin hook and informational event is persisted as a
 * queryable row (level, channel, message, context, source) so it can be
 * filtered and audited from the admin screen. The legacy file log
 * (uploads/joinotify/logs.txt) is still written by {@see Logger} for external
 * tailing and backwards compatibility.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Debug_Log {

    /**
     * Table base name (without the WordPress table prefix).
     *
     * @since 2.1.0
     * @var string
     */
    const TABLE = 'joinotify_debug_logs';

    /**
     * Schema version. Bump to trigger a dbDelta migration.
     *
     * @since 2.1.0
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Option key that stores the installed schema version.
     *
     * @since 2.1.0
     * @var string
     */
    const DB_VERSION_OPTION = 'joinotify_debug_logs_db_version';

    /**
     * Cron hook for the retention purge.
     *
     * @since 2.1.0
     * @var string
     */
    const PURGE_HOOK = 'joinotify_purge_debug_logs_event';

    /**
     * Allowed log levels, ordered from least to most severe.
     *
     * @since 2.1.0
     * @var string[]
     */
    const LEVELS = array( 'debug', 'info', 'notice', 'warning', 'error', 'critical' );

    /**
     * Numeric severity map used by the persistence threshold.
     *
     * @since 2.1.0
     * @var array<string,int>
     */
    const SEVERITY = array(
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
    );

    /**
     * Hook names already recorded during the current request.
     *
     * Keeps the "hooks used" capture bounded to one row per distinct hook per
     * request instead of one row per fire.
     *
     * @since 2.1.0
     * @var array<string,bool>
     */
    private static $recorded_hooks = array();


    /**
     * Construct function.
     *
     * @since 2.1.0
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

        // Capture fired plugin hooks and PHP errors only while debugging.
        if ( self::debug_mode_enabled() ) {
            add_action( 'all', array( __CLASS__, 'capture_hook' ) );
            self::register_error_handlers();
        }
    }


    /**
     * Get the fully-qualified debug log table name.
     *
     * @since 2.1.0
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }


    /**
     * Whether the plugin debug mode is currently enabled.
     *
     * @since 2.1.0
     * @return bool
     */
    public static function debug_mode_enabled() {
        return Admin::get_setting('enable_debug_mode') === 'yes';
    }


    /**
     * Create or upgrade the debug log table, guarded by the stored schema version.
     *
     * @since 2.1.0
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
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            channel VARCHAR(40) NOT NULL DEFAULT 'general',
            message TEXT NULL,
            context LONGTEXT NULL,
            code VARCHAR(100) NOT NULL DEFAULT '',
            hook VARCHAR(191) NOT NULL DEFAULT '',
            request_url TEXT NULL,
            response_code SMALLINT(6) NOT NULL DEFAULT 0,
            source_file VARCHAR(255) NOT NULL DEFAULT '',
            source_line INT(11) NOT NULL DEFAULT 0,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY level (level),
            KEY channel (channel)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }


    /**
     * Whether a record at the given level should be persisted right now.
     *
     * The master switch (`enable_debug_logs`) gates everything. When debug mode
     * is off only warning-and-above events are kept, so production stays quiet
     * while still capturing problems; with debug mode on everything is stored.
     *
     * @since 2.1.0
     * @param string $level Normalized log level.
     * @return bool
     */
    public static function should_persist( $level ) {
        if ( Admin::get_setting('enable_debug_logs') !== 'yes' ) {
            return false;
        }

        if ( self::debug_mode_enabled() ) {
            return true;
        }

        $severity = self::SEVERITY[ $level ] ?? self::SEVERITY['info'];

        return $severity >= self::SEVERITY['warning'];
    }


    /**
     * Record a structured debug entry.
     *
     * @since 2.1.0
     * @param array<string,mixed> $entry Recognized keys: level, channel,
     *        message, context, code, hook, request_url, response_code, file,
     *        line. Missing source file/line/channel are auto-detected from the
     *        caller.
     * @return int|false Inserted row ID, or false when skipped/failed.
     */
    public static function record( $entry ) {
        if ( ! is_array( $entry ) ) {
            return false;
        }

        $level = isset( $entry['level'] ) ? strtolower( (string) $entry['level'] ) : 'info';

        if ( ! in_array( $level, self::LEVELS, true ) ) {
            $level = 'info';
        }

        if ( ! self::should_persist( $level ) ) {
            return false;
        }

        // Normalize a WP_Error message into message + code + context.
        $message = $entry['message'] ?? '';
        $code = isset( $entry['code'] ) ? (string) $entry['code'] : '';
        $context = $entry['context'] ?? null;

        if ( $message instanceof WP_Error ) {
            $error = $message;
            $message = $error->get_error_message();
            $code = '' !== $code ? $code : (string) $error->get_error_code();
            $context = null === $context ? $error->get_error_data() : $context;
        }

        if ( ! is_string( $message ) ) {
            $message = self::stringify( $message );
        }

        // Auto-detect the caller's file/line/channel when not supplied.
        $file = isset( $entry['file'] ) ? (string) $entry['file'] : '';
        $line = isset( $entry['line'] ) ? (int) $entry['line'] : 0;
        $channel = isset( $entry['channel'] ) ? sanitize_key( $entry['channel'] ) : '';

        if ( '' === $file || '' === $channel ) {
            $caller = self::detect_caller();

            if ( '' === $file ) {
                $file = $caller['file'];
                $line = $caller['line'];
            }

            if ( '' === $channel ) {
                $channel = $caller['channel'];
            }
        }

        $data = array(
            'level' => $level,
            'channel' => '' !== $channel ? $channel : 'general',
            'message' => $message,
            'context' => null !== $context ? self::stringify( $context ) : null,
            'code' => substr( $code, 0, 100 ),
            'hook' => substr( sanitize_text_field( $entry['hook'] ?? '' ), 0, 191 ),
            'request_url' => isset( $entry['request_url'] ) ? esc_url_raw( $entry['request_url'] ) : '',
            'response_code' => isset( $entry['response_code'] ) ? (int) $entry['response_code'] : 0,
            'file' => $file,
            'line' => $line,
        );

        /**
         * Allow short-circuiting a debug record before it is written.
         *
         * @since 2.1.0
         * @param bool $should_record Whether to record this entry.
         * @param array $data Normalized entry data.
         */
        if ( ! apply_filters( 'Joinotify/Debug_Log/Should_Record', true, $data ) ) {
            return false;
        }

        global $wpdb;

        $row = array(
            'created_at' => current_time( 'mysql', true ),
            'level' => $data['level'],
            'channel' => $data['channel'],
            'message' => $data['message'],
            'context' => $data['context'],
            'code' => $data['code'],
            'hook' => $data['hook'],
            'request_url' => $data['request_url'],
            'response_code' => $data['response_code'],
            'source_file' => substr( self::relative_path( $data['file'] ), 0, 255 ),
            'source_line' => $data['line'],
            'user_id' => get_current_user_id(),
        );

        $formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d' );

        $inserted = $wpdb->insert( self::get_table_name(), $row, $formats );

        if ( ! $inserted ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }


    /**
     * Record an entry at the "error" level.
     *
     * @since 2.1.0
     * @param mixed $message Message or WP_Error.
     * @param array $entry Additional fields (channel, context, code, ...).
     * @return int|false
     */
    public static function error( $message, $entry = array() ) {
        return self::record( array_merge( $entry, array( 'message' => $message, 'level' => 'error' ) ) );
    }


    /**
     * Record an entry at the "warning" level.
     *
     * @since 2.1.0
     * @param mixed $message Message or WP_Error.
     * @param array $entry Additional fields.
     * @return int|false
     */
    public static function warning( $message, $entry = array() ) {
        return self::record( array_merge( $entry, array( 'message' => $message, 'level' => 'warning' ) ) );
    }


    /**
     * Record an entry at the "info" level.
     *
     * @since 2.1.0
     * @param mixed $message Message or WP_Error.
     * @param array $entry Additional fields.
     * @return int|false
     */
    public static function info( $message, $entry = array() ) {
        return self::record( array_merge( $entry, array( 'message' => $message, 'level' => 'info' ) ) );
    }


    /**
     * Map a legacy Logger level label to a normalized level.
     *
     * @since 2.1.0
     * @param string $level Legacy level (INFO, WARNING, ERROR, ...).
     * @return string Normalized level.
     */
    public static function normalize_level( $level ) {
        $level = strtolower( trim( (string) $level ) );

        $aliases = array(
            'warn' => 'warning',
            'err' => 'error',
            'fatal' => 'critical',
            'emergency' => 'critical',
            'alert' => 'critical',
        );

        $level = $aliases[ $level ] ?? $level;

        return in_array( $level, self::LEVELS, true ) ? $level : 'info';
    }


    /**
     * Record a fired plugin hook once per request (debug mode only).
     *
     * Hooked to WordPress' global `all` action; cheaply filters down to the
     * plugin's own namespaced hooks and skips its own recording hooks to avoid
     * recursion. The `all` callback receives the original hook's arguments, not
     * its name, so the current hook is resolved via current_filter().
     *
     * @since 2.1.0
     * @return void
     */
    public static function capture_hook() {
        $hook = current_filter();

        if ( ! is_string( $hook ) || strpos( $hook, 'Joinotify/' ) !== 0 ) {
            return;
        }

        // Never capture our own bookkeeping hooks.
        if ( strpos( $hook, 'Joinotify/Debug_Log/' ) === 0 ) {
            return;
        }

        if ( isset( self::$recorded_hooks[ $hook ] ) ) {
            return;
        }

        self::$recorded_hooks[ $hook ] = true;

        self::record( array(
            'level' => 'debug',
            'channel' => 'hook',
            'message' => $hook,
            'hook' => $hook,
        ) );
    }


    /**
     * Register PHP error and shutdown handlers scoped to plugin files.
     *
     * Only runs while debug mode is on. Existing handlers are preserved so the
     * normal WordPress/PHP error flow is never short-circuited.
     *
     * @since 2.1.0
     * @return void
     */
    public static function register_error_handlers() {
        set_error_handler( array( __CLASS__, 'handle_php_error' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
    }


    /**
     * Capture plugin-originated PHP warnings/notices without suppressing them.
     *
     * @since 2.1.0
     * @param int $errno Error level.
     * @param string $errstr Error message.
     * @param string $errfile File where the error occurred.
     * @param int $errline Line number.
     * @return bool Always false so PHP's standard handler still runs.
     */
    public static function handle_php_error( $errno, $errstr, $errfile = '', $errline = 0 ) {
        if ( self::is_plugin_file( $errfile ) ) {
            $map = array(
                E_WARNING => 'warning',
                E_USER_WARNING => 'warning',
                E_NOTICE => 'notice',
                E_USER_NOTICE => 'notice',
                E_DEPRECATED => 'notice',
                E_USER_DEPRECATED => 'notice',
                E_USER_ERROR => 'error',
            );

            self::record( array(
                'level' => $map[ $errno ] ?? 'warning',
                'channel' => 'php',
                'message' => $errstr,
                'code' => 'php_' . $errno,
                'file' => $errfile,
                'line' => (int) $errline,
            ) );
        }

        return false;
    }


    /**
     * Capture a fatal error originating in plugin code on shutdown.
     *
     * @since 2.1.0
     * @return void
     */
    public static function handle_shutdown() {
        $error = error_get_last();

        if ( ! is_array( $error ) ) {
            return;
        }

        $fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );

        if ( in_array( $error['type'], $fatal, true ) && self::is_plugin_file( $error['file'] ?? '' ) ) {
            self::record( array(
                'level' => 'critical',
                'channel' => 'php',
                'message' => $error['message'] ?? '',
                'code' => 'php_fatal',
                'file' => $error['file'] ?? '',
                'line' => (int) ( $error['line'] ?? 0 ),
            ) );
        }
    }


    /**
     * Build the WHERE clause and prepared args from query filters.
     *
     * @since 2.1.0
     * @param array<string,mixed> $args Filter args.
     * @return array{0:string,1:array} SQL fragment and prepare args.
     */
    private static function build_where( $args ) {
        global $wpdb;

        $where = array( '1=1' );
        $values = array();

        $level = isset( $args['level'] ) ? strtolower( sanitize_key( $args['level'] ) ) : '';
        if ( in_array( $level, self::LEVELS, true ) ) {
            $where[] = 'level = %s';
            $values[] = $level;
        }

        $channel = isset( $args['channel'] ) ? sanitize_key( $args['channel'] ) : '';
        if ( '' !== $channel ) {
            $where[] = 'channel = %s';
            $values[] = $channel;
        }

        $search = isset( $args['search'] ) ? trim( sanitize_text_field( $args['search'] ) ) : '';
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '( message LIKE %s OR context LIKE %s OR hook LIKE %s )';
            $values[] = $like;
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
     * Get a paginated list of log items.
     *
     * @since 2.1.0
     * @param array<string,mixed> $args Filter + pagination args.
     * @return array<int,array<string,mixed>>
     */
    public static function get_items( $args = array() ) {
        global $wpdb;

        $table = self::get_table_name();
        list( $where, $values ) = self::build_where( $args );

        $per_page = isset( $args['per_page'] ) ? max( 1, min( 500, (int) $args['per_page'] ) ) : 50;
        $page = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
        $offset = ( $page - 1 ) * $per_page;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

        return is_array( $rows ) ? $rows : array();
    }


    /**
     * Count log items matching the given filters.
     *
     * @since 2.1.0
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
     * Count log items grouped by level.
     *
     * @since 2.1.0
     * @return array<string,int>
     */
    public static function get_counts_by_level() {
        global $wpdb;

        $table = self::get_table_name();
        $counts = array( 'all' => 0 );

        foreach ( self::LEVELS as $level ) {
            $counts[ $level ] = 0;
        }

        $rows = $wpdb->get_results( "SELECT level, COUNT(*) AS total FROM {$table} GROUP BY level", ARRAY_A );

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $level = isset( $row['level'] ) ? (string) $row['level'] : '';
                $total = isset( $row['total'] ) ? (int) $row['total'] : 0;

                if ( isset( $counts[ $level ] ) ) {
                    $counts[ $level ] = $total;
                }

                $counts['all'] += $total;
            }
        }

        return $counts;
    }


    /**
     * Distinct channels currently present in the table.
     *
     * @since 2.1.0
     * @return string[]
     */
    public static function get_channels() {
        global $wpdb;

        $table = self::get_table_name();
        $rows = $wpdb->get_col( "SELECT DISTINCT channel FROM {$table} ORDER BY channel ASC" );

        return is_array( $rows ) ? array_values( array_filter( $rows ) ) : array();
    }


    /**
     * Render the whole table (newest last) as flat log lines for export.
     *
     * @since 2.1.0
     * @return string
     */
    public static function render_text() {
        global $wpdb;

        $table = self::get_table_name();
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return '';
        }

        $lines = array();

        foreach ( $rows as $row ) {
            $lines[] = self::format_line( $row );
        }

        return implode( PHP_EOL, $lines ) . PHP_EOL;
    }


    /**
     * Format a single row as a flat log line.
     *
     * @since 2.1.0
     * @param array<string,mixed> $row Row data.
     * @return string
     */
    public static function format_line( $row ) {
        $timestamp = $row['created_at'] ?? '';
        $level = strtoupper( (string) ( $row['level'] ?? 'info' ) );
        $channel = (string) ( $row['channel'] ?? 'general' );
        $message = (string) ( $row['message'] ?? '' );

        $line = "[{$timestamp}] [{$level}] [{$channel}] {$message}";

        if ( ! empty( $row['context'] ) ) {
            $line .= ' | context: ' . preg_replace( '/\s+/', ' ', (string) $row['context'] );
        }

        if ( ! empty( $row['source_file'] ) ) {
            $line .= ' | at ' . $row['source_file'] . ':' . (int) ( $row['source_line'] ?? 0 );
        }

        return $line;
    }


    /**
     * Delete log rows by ID.
     *
     * @since 2.1.0
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
     * Delete every log row.
     *
     * @since 2.1.0
     * @return int Number of rows deleted.
     */
    public static function clear_all() {
        global $wpdb;

        $table = self::get_table_name();

        return (int) $wpdb->query( "DELETE FROM {$table}" );
    }


    /**
     * Whether the table currently holds any rows.
     *
     * @since 2.1.0
     * @return bool
     */
    public static function has_logs() {
        global $wpdb;

        $table = self::get_table_name();

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) > 0;
    }


    /**
     * Purge records older than the configured retention window.
     *
     * @since 2.1.0
     * @return int Number of rows deleted.
     */
    public static function purge_old() {
        global $wpdb;

        $days = (int) Admin::get_setting('debug_logs_retention_days');

        /**
         * Filter the debug log retention window (in days). 0 disables auto-purge.
         *
         * @since 2.1.0
         * @param int $days Retention window in days.
         */
        $days = (int) apply_filters( 'Joinotify/Debug_Log/Retention_Days', $days );

        if ( $days <= 0 ) {
            return 0;
        }

        $table = self::get_table_name();
        $threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $threshold ) );
    }


    /**
     * Convert any value to a storable string (JSON for structured data).
     *
     * @since 2.1.0
     * @param mixed $value Value to stringify.
     * @return string
     */
    private static function stringify( $value ) {
        if ( is_string( $value ) ) {
            return $value;
        }

        if ( is_scalar( $value ) ) {
            return (string) $value;
        }

        $json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        return false !== $json ? $json : print_r( $value, true );
    }


    /**
     * Inspect the backtrace to find the first non-logger caller.
     *
     * @since 2.1.0
     * @return array{file:string,line:int,channel:string}
     */
    private static function detect_caller() {
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ); // phpcs:ignore
        $internal = array( __CLASS__, 'MeuMouse\\Joinotify\\Core\\Logger' );

        foreach ( $trace as $frame ) {
            $class = $frame['class'] ?? '';

            if ( in_array( $class, $internal, true ) ) {
                continue;
            }

            return array(
                'file' => $frame['file'] ?? '',
                'line' => (int) ( $frame['line'] ?? 0 ),
                'channel' => self::channel_from_class( $class ),
            );
        }

        return array( 'file' => '', 'line' => 0, 'channel' => 'general' );
    }


    /**
     * Derive a channel from a fully-qualified class name.
     *
     * @since 2.1.0
     * @param string $class Class name.
     * @return string
     */
    private static function channel_from_class( $class ) {
        if ( strpos( $class, 'MeuMouse\\Joinotify\\' ) !== 0 ) {
            return 'general';
        }

        $relative = substr( $class, strlen( 'MeuMouse\\Joinotify\\' ) );
        $segment = strtolower( strtok( $relative, '\\' ) );

        $map = array(
            'api' => 'api',
            'integrations' => 'integration',
            'cron' => 'cron',
            'builder' => 'workflow',
            'otp_login' => 'otp',
            'notifications' => 'notification',
            'ai' => 'ai',
            'rest' => 'rest',
        );

        return $map[ $segment ] ?? 'general';
    }


    /**
     * Whether a file path lives inside the plugin directory.
     *
     * @since 2.1.0
     * @param string $file Absolute file path.
     * @return bool
     */
    private static function is_plugin_file( $file ) {
        if ( ! is_string( $file ) || '' === $file ) {
            return false;
        }

        if ( ! defined( 'JOINOTIFY_DIR' ) ) {
            return false;
        }

        $file = str_replace( '\\', '/', $file );
        $dir = str_replace( '\\', '/', JOINOTIFY_DIR );

        return strpos( $file, $dir ) === 0;
    }


    /**
     * Reduce an absolute plugin path to a path relative to the plugin root.
     *
     * @since 2.1.0
     * @param string $file Absolute file path.
     * @return string
     */
    private static function relative_path( $file ) {
        if ( ! is_string( $file ) || '' === $file ) {
            return '';
        }

        if ( defined( 'JOINOTIFY_DIR' ) ) {
            $file = str_replace( '\\', '/', $file );
            $dir = str_replace( '\\', '/', JOINOTIFY_DIR );

            if ( strpos( $file, $dir ) === 0 ) {
                return ltrim( substr( $file, strlen( $dir ) ), '/' );
            }
        }

        return $file;
    }
}
