<?php

namespace MeuMouse\Joinotify\Core;

defined('ABSPATH') || exit;

/**
 * AJAX callbacks related to debug log operations.
 *
 * Handles: get logs, clear logs, download logs, force download.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Ajax_Debug {

    /**
     * Register AJAX actions.
     *
     * @since 1.4.7
     */
    public function __construct() {
        $actions = array(
            'joinotify_get_debug_logs'      => 'get_debug_logs_callback',
            'joinotify_clear_debug_logs'    => 'clear_debug_logs_callback',
            'joinotify_download_debug_logs' => 'download_debug_logs_callback',
            'joinotify_force_download'      => 'force_download_debug_logs',
        );

        foreach ( $actions as $action => $callback ) {
            add_action( "wp_ajax_{$action}", array( $this, $callback ) );
        }
    }


    /**
     * Get debug logs on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function get_debug_logs_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_get_debug_logs' ) {
            $log_content = Logger::read_log();

            if ( empty( $log_content ) ) {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'No record was found', 'joinotify' ),
                    'toast_body_title'   => __( 'The debug log is empty.', 'joinotify' ),
                ) );
            } else {
                $log_lines        = explode( "\n", $log_content );
                $log_content_html = '';

                foreach ( $log_lines as $line ) {
                    $line = trim( $line );

                    if ( ! empty( $line ) ) {
                        $log_content_html .= '<span class="joinotify-log-item">' . esc_html( $line ) . '</span><br>';
                    }
                }

                wp_send_json( array(
                    'status'      => 'success',
                    'log_content' => $log_content_html,
                ) );
            }
        }
    }


    /**
     * Clear debug logs on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function clear_debug_logs_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_clear_debug_logs' ) {
            Logger::clear_log();

            if ( ! Logger::has_logs() ) {
                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => __( 'The logs were cleared', 'joinotify' ),
                    'toast_body_title'   => __( 'Debug logs cleared successfully!', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Ops! An error occurred', 'joinotify' ),
                    'toast_body_title'   => __( 'Could not clear the debug logs.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Download debug logs on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function download_debug_logs_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_download_debug_logs' ) {
            $upload_dir = wp_upload_dir();
            $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'joinotify/logs.txt';

            if ( file_exists( $log_file ) ) {
                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => __( 'Download started', 'joinotify' ),
                    'toast_body_title'   => __( 'The log file was downloaded successfully!', 'joinotify' ),
                    'download_url'       => admin_url( 'admin-ajax.php?action=joinotify_force_download' ),
                );
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Ops! An error occurred', 'joinotify' ),
                    'toast_body_title'   => __( 'The log file was not found.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Force download the debug log file.
     *
     * @since 1.1.0
     * @return void
     */
    public function force_download_debug_logs() {
        $upload_dir = wp_upload_dir();
        $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'joinotify/logs.txt';

        if ( file_exists( $log_file ) ) {
            header( 'Content-Type: text/plain' );
            header( 'Content-Disposition: attachment; filename="joinotify-debug-logs.txt"' );
            header( 'Content-Length: ' . filesize( $log_file ) );
            readfile( $log_file );

            exit;
        } else {
            wp_die( __( 'The log file was not found.', 'joinotify' ) );
        }
    }
}
