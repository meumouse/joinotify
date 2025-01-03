<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Handle logs class
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Logger {

    // log file path
    private static $log_file;

    /**
     * Construct function
     * 
     * @since 1.1.0
     * @return void
     */
    public function __construct() {
        // set file path on uploads folder
        $upload_dir = wp_upload_dir();
        self::$log_file = trailingslashit( $upload_dir['basedir'] ) . 'joinotify/logs.txt';
        $this->ensure_log_directory_exists();
    }


    /**
     * Ensures the logs directory exists
     * 
     * @since 1.1.0
     * @return void
     */
    private function ensure_log_directory_exists() {
        $dir = dirname( self::$log_file );

        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
    }


    /**
     * Register message on log file
     *
     * @since 1.1.0
     * @param string $message | Message for register
     * @param string $level | Log level (INFO, WARNING, ERROR)
     */
    public static function register_log( $message, $level = 'INFO' ) {
        // Ensure the message is a string
        if ( ! is_string( $message ) ) {
            $message = print_r( $message, true );
        }

        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[$timestamp] [$level] $message" . PHP_EOL;

        error_log( $formatted_message, 3, self::$log_file );
    }


    /**
     * Read the log content
     *
     * @since 1.1.0
     * @return string log content
     */
    public static function read_log() {
        if ( file_exists( self::$log_file ) ) {
            return file_get_contents( self::$log_file );
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
        if ( file_exists( self::$log_file ) ) {
            file_put_contents( self::$log_file, '' );
        }
    }


    /**
     * Check if log has messages
     *
     * @since 1.1.0
     * @return bool
     */
    public static function has_logs() {
        return file_exists( self::$log_file ) && filesize( self::$log_file ) > 0;
    }
}