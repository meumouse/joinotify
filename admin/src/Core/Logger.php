<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Handle logs class
 * 
 * @since 1.1.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Logger {

    // log file path
    private static $log_file;

    /**
     * Construct function
     * 
     * @since 1.1.0
     * @version 2.3.4
     * @return void
     */
    public function __construct() {
        self::get_log_file();
    }


    /**
     * Resolve the log file path, lazily.
     *
     * The class is bootstrapped on `wp_loaded`, but static call sites such as
     * {@see self::register_log()} run much earlier (`plugins_loaded`, `init`,
     * cron, REST and CLI). Resolving the path on demand keeps those calls safe
     * instead of writing to an empty path, which raises a fatal ValueError on
     * PHP 8.
     *
     * @since 2.3.4
     * @return string Absolute path to the log file, or an empty string when the
     *                uploads folder is unavailable.
     */
    private static function get_log_file() {
        if ( ! empty( self::$log_file ) ) {
            return self::$log_file;
        }

        if ( ! function_exists('wp_upload_dir') ) {
            return '';
        }

        $upload_dir = wp_upload_dir( null, false );

        if ( ! empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) ) {
            return '';
        }

        self::$log_file = trailingslashit( $upload_dir['basedir'] ) . 'joinotify/logs.txt';

        return self::$log_file;
    }


    /**
     * Ensures the logs directory exists
     * 
     * @since 1.1.0
     * @version 2.3.4
     * @param string $file | Absolute path to the log file
     * @return bool True when the directory is available for writing
     */
    private static function ensure_log_directory_exists( $file ) {
        if ( empty( $file ) ) {
            return false;
        }

        $dir = dirname( $file );

        if ( ! is_dir( $dir ) ) {
            return wp_mkdir_p( $dir );
        }

        return true;
    }


    /**
     * Register message on both the structured debug table and the log file.
     *
     * The dedicated table (see {@see Debug_Log}) is the primary, queryable
     * store; the flat file is still written for external tailing and backwards
     * compatibility. Existing call sites keep working unchanged.
     *
     * @since 1.1.0
     * @version 2.3.0
     * @param mixed  $message | Message (string, array or WP_Error) for register
     * @param string $level | Log level (INFO, WARNING, ERROR)
     */
    public static function register_log( $message, $level = 'INFO' ) {
        // Forward to the structured store first (it can normalize WP_Error/arrays).
        if ( class_exists( Debug_Log::class ) ) {
            Debug_Log::record( array(
                'message' => $message,
                'level' => Debug_Log::normalize_level( $level ),
            ) );
        }

        // Ensure the message is a string for the flat file.
        if ( ! is_string( $message ) ) {
            $message = self::stringify( $message );
        }

        $log_file = self::get_log_file();

        // Bail out silently when the uploads folder is unavailable; the
        // structured store above already kept the entry.
        if ( ! self::ensure_log_directory_exists( $log_file ) ) {
            return;
        }

        // Timestamps are stored in UTC; the reader renders them in the site timezone.
        $timestamp = gmdate('Y-m-d H:i:s');
        $formatted_message = "[$timestamp] [$level] $message" . PHP_EOL;

        file_put_contents( $log_file, $formatted_message, FILE_APPEND );
    }


    /**
     * Render an arbitrary value as a log-friendly string.
     *
     * Scalars are returned as-is so simple traces stay readable; anything else is
     * encoded as pretty-printed JSON.
     *
     * @since 2.3.0
     * @param mixed $value | Value to render
     * @return string
     */
    public static function stringify( $value ) {
        if ( is_string( $value ) ) {
            return $value;
        }

        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }

        if ( is_null( $value ) ) {
            return 'null';
        }

        if ( is_scalar( $value ) ) {
            return (string) $value;
        }

        if ( $value instanceof \WP_Error ) {
            $value = $value->errors;
        }

        $encoded = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        return false !== $encoded ? $encoded : '';
    }


    /**
     * Read the log content
     *
     * @since 1.1.0
     * @return string log content
     */
    public static function read_log() {
        $log_file = self::get_log_file();

        if ( $log_file && file_exists( $log_file ) ) {
            return file_get_contents( $log_file );
        }

        return '';
    }


    /**
     * Clear the log file
     * 
     * @since 1.1.0
     * @return void
     */
    public static function clear_log() {
        $log_file = self::get_log_file();

        if ( $log_file && file_exists( $log_file ) ) {
            file_put_contents( $log_file, '' );
        }
    }


    /**
     * Check if log has messages
     *
     * @since 1.1.0
     * @return bool
     */
    public static function has_logs() {
        $log_file = self::get_log_file();

        return $log_file && file_exists( $log_file ) && filesize( $log_file ) > 0;
    }
}