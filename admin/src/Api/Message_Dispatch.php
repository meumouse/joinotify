<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Debug_Log;
use MeuMouse\Joinotify\Core\Message_History;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Shared message-dispatch plumbing for WhatsApp transports.
 *
 * The Cloud
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
     * @version 2.4.0
     * @param int $response_code | HTTP response code.
     * @param bool $success | Operation status.
     * @param bool $retryable | If failure can be retried.
     * @param string $error | Failure reason.
     * @param bool|string $queued | Queue item id when enqueued, false otherwise.
     * @return array
     */
    protected static function build_response_details( $response_code, $success, $retryable = false, $error = '', $queued = false ) {
        // Callers pass whatever Notification_Queue::enqueue() returned — an item
        // id or false — so the id is kept alongside the boolean the rest of the
        // codebase reads. It is what lets the history row cancel its own resend.
        $queue_id = is_string( $queued ) ? $queued : '';

        return array(
            'response_code' => (int) $response_code,
            'success' => (bool) $success,
            'retryable' => (bool) $retryable,
            'error' => (string) $error,
            'queued' => (bool) $queued,
            'queue_id' => $queue_id,
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
     * Successful dispatches are written to the debug log at the `info` level,
     * which only persists while debug mode is on; failed and queued ones are
     * always captured as errors and warnings.
     *
     * @since 1.4.8
     * @version 2.4.1
     * @param array $fields | Message fields (sender, receiver, message_type, media_type, content, media_url, meta, wamid, attempts).
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
            'queue_id' => (string) ( $details['queue_id'] ?? '' ),
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
                'context' => array_merge( self::dispatch_log_context( $fields, $details ), array(
                    'queued' => ! empty( $details['queued'] ),
                    'retryable' => ! empty( $details['retryable'] ),
                )),
            ));
        } elseif ( Debug_Log::should_persist( 'info' ) ) {
            // Checked up front so a production site does not build the context of
            // an entry that `info` would never persist anyway.
            $template = $fields['meta']['template'] ?? array();

            Debug_Log::record( array(
                'level' => 'info',
                'channel' => 'api',
                'message' => ! empty( $template['name'] )
                    ? sprintf( 'WhatsApp template "%s" (%s) sent to %s', $template['name'], $template['language'] ?? '', $fields['receiver'] ?? '' )
                    : sprintf( 'WhatsApp %s message sent to %s', $fields['message_type'] ?? 'text', $fields['receiver'] ?? '' ),
                'code' => 'dispatch_sent',
                'response_code' => $response_code,
                'context' => self::dispatch_log_context( $fields, $details ),
            ));
        }

        return $return_details ? $details : $response_code;
    }


    /**
     * Build the debug-log context of a dispatch.
     *
     * The template block (name, language, parameters) is always included, so a
     * refused template can be diagnosed without turning debug mode on. The
     * message body, media link and WhatsApp id are only added while debug mode
     * is on, keeping what production logs hold about recipients unchanged.
     *
     * @since 2.4.1
     * @version 2.4.2
     * @param array $fields | Message fields.
     * @param array $details | Normalized response details.
     * @return array
     */
    protected static function dispatch_log_context( $fields, $details ) {
        $origin = Message_History::get_context();

        $context = array(
            'source' => (string) ( $origin['source'] ?? 'api' ),
            'workflow_id' => (int) ( $origin['workflow_id'] ?? 0 ),
            'sender' => $fields['sender'] ?? '',
            'receiver' => $fields['receiver'] ?? '',
            'message_type' => $fields['message_type'] ?? '',
        );

        if ( ! empty( $origin['attempts'] ) ) {
            $context['attempts'] = (int) $origin['attempts'];
        }

        if ( ! empty( $fields['meta']['template'] ) ) {
            $context['template'] = $fields['meta']['template'];
        }

        // WhatsApp's explanation of a refusal, kept regardless of debug mode:
        // it is what tells a malformed parameter from a missing payment method.
        if ( ! empty( $details['error_detail'] ) ) {
            $context['error_detail'] = (string) $details['error_detail'];
        }

        if ( Debug_Log::debug_mode_enabled() ) {
            $context['content'] = (string) ( $fields['content'] ?? '' );

            if ( ! empty( $fields['media_type'] ) ) {
                $context['media_type'] = (string) $fields['media_type'];
            }

            // A local attachment travels as base64 in the same field; only a real
            // link is worth a place in the log.
            if ( preg_match( '#^https?://#i', (string) ( $fields['media_url'] ?? '' ) ) ) {
                $context['media_url'] = (string) $fields['media_url'];
            }

            if ( ! empty( $details['wamid'] ) ) {
                $context['wamid'] = (string) $details['wamid'];
            }
        }

        return $context;
    }
}
