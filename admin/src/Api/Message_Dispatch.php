<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Debug_Log;
use MeuMouse\Joinotify\Core\Message_History;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Shared message-dispatch plumbing for WhatsApp transports.
 *
 * Both the legacy Evolution transport (Api\Controller) and the official Cloud
 * API transport (Api\Cloud_Client) normalize their result the same way, record
 * every dispatch in the message history, and capture failures in the structured
 * debug log. This trait holds that shared logic so a new transport only has to
 * perform the HTTP request and hand the outcome to record_and_return().
 *
 * @since 1.4.8
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
trait Message_Dispatch {

    /**
     * Build normalized response details.
     *
     * @since 1.4.8
     * @param int $response_code | HTTP response code.
     * @param bool $success | Operation status.
     * @param bool $retryable | If failure can be retried.
     * @param string $error | Failure reason.
     * @param bool $queued | If item was enqueued.
     * @return array
     */
    protected static function build_response_details( $response_code, $success, $retryable = false, $error = '', $queued = false ) {
        return array(
            'response_code' => (int) $response_code,
            'success' => (bool) $success,
            'retryable' => (bool) $retryable,
            'error' => (string) $error,
            'queued' => (bool) $queued,
        );
    }


    /**
     * Check if a response code should be retried.
     *
     * Conservative default shared by both transports; a transport with stricter
     * rules (e.g. the Cloud API, where 4xx config errors never resolve on retry)
     * may override this method in the using class.
     *
     * @since 1.4.8
     * @param int $response_code | HTTP response code.
     * @return bool
     */
    protected static function should_retry_response_code( $response_code ) {
        $response_code = (int) $response_code;

        if ( 0 === $response_code ) {
            return true;
        }

        if ( $response_code >= 500 ) {
            return true;
        }

        return in_array( $response_code, array( 408, 409, 425, 429 ), true );
    }


    /**
     * Record a dispatch in the message history and return the original value.
     *
     * Centralizes history logging across every return path of the send methods,
     * so success, queued and failed dispatches are all captured uniformly while
     * preserving each method's original return contract (details array or code).
     *
     * @since 1.4.8
     * @param array $fields | Message fields (sender, receiver, message_type, media_type, content, media_url, attempts).
     * @param array $details | Normalized response details from build_response_details().
     * @param bool $return_details | Whether the caller expects the details array.
     * @return int|array
     */
    protected static function record_and_return( $fields, $details, $return_details ) {
        if ( ! empty( $details['success'] ) ) {
            $status = 'sent';
        } elseif ( ! empty( $details['queued'] ) ) {
            $status = 'queued';
        } else {
            $status = 'failed';
        }

        $response_code = (int) ( $details['response_code'] ?? 0 );

        Message_History::record( array_merge( $fields, array(
            'status' => $status,
            'response_code' => $response_code,
            'error' => (string) ( $details['error'] ?? '' ),
        )));

        // Capture failed dispatches in the structured debug log (queued retries
        // are warnings, definitive failures are errors).
        if ( 'sent' !== $status ) {
            Debug_Log::record( array(
                'level' => 'queued' === $status ? 'warning' : 'error',
                'channel' => 'api',
                'message' => sprintf(
                    'WhatsApp dispatch %s for %s (HTTP %d)',
                    $status,
                    $fields['receiver'] ?? '',
                    $response_code
                ),
                'code' => (string) ( $details['error'] ?? '' ),
                'response_code' => $response_code,
                'context' => array(
                    'sender' => $fields['sender'] ?? '',
                    'receiver' => $fields['receiver'] ?? '',
                    'message_type' => $fields['message_type'] ?? '',
                    'queued' => ! empty( $details['queued'] ),
                    'retryable' => ! empty( $details['retryable'] ),
                ),
            ));
        }

        return $return_details ? $details : $response_code;
    }
}
