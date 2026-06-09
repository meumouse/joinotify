<?php

namespace MeuMouse\Joinotify\Notifications;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Normalized result of a notification dispatch.
 *
 * Every channel returns one of these, so callers get a uniform success/failure
 * contract regardless of the underlying service (Evolution returns 201, other
 * REST APIs return 200, some clients return a boolean). It also carries whether
 * the failure is retryable and whether the item was enqueued for retry.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Notifications
 * @author MeuMouse.com
 */
class Channel_Result {

    /**
     * Whether the dispatch succeeded.
     *
     * @since 2.0.0
     * @var bool
     */
    public $success = false;

    /**
     * Channel that produced this result.
     *
     * @since 2.0.0
     * @var string
     */
    public $channel = '';

    /**
     * Transport response code (HTTP status when available, 0 otherwise).
     *
     * @since 2.0.0
     * @var int
     */
    public $response_code = 0;

    /**
     * Whether a failed dispatch can be retried.
     *
     * @since 2.0.0
     * @var bool
     */
    public $retryable = false;

    /**
     * Whether the message was enqueued for a later retry.
     *
     * @since 2.0.0
     * @var bool
     */
    public $queued = false;

    /**
     * Failure reason (empty on success).
     *
     * @since 2.0.0
     * @var string
     */
    public $error = '';

    /**
     * Raw transport payload for debugging (optional).
     *
     * @since 2.0.0
     * @var mixed
     */
    public $raw = null;

    /**
     * Construct function.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args | Result properties.
     * @return void
     */
    public function __construct( $args = array() ) {
        if ( ! is_array( $args ) ) {
            return;
        }

        if ( isset( $args['success'] ) ) {
            $this->success = (bool) $args['success'];
        }

        if ( isset( $args['channel'] ) ) {
            $this->channel = (string) $args['channel'];
        }

        if ( isset( $args['response_code'] ) ) {
            $this->response_code = (int) $args['response_code'];
        }

        if ( isset( $args['retryable'] ) ) {
            $this->retryable = (bool) $args['retryable'];
        }

        if ( isset( $args['queued'] ) ) {
            $this->queued = (bool) $args['queued'];
        }

        if ( isset( $args['error'] ) ) {
            $this->error = (string) $args['error'];
        }

        if ( array_key_exists( 'raw', $args ) ) {
            $this->raw = $args['raw'];
        }
    }


    /**
     * Build a success result.
     *
     * @since 2.0.0
     * @param string $channel | Channel id.
     * @param int    $response_code | Transport response code.
     * @return self
     */
    public static function success( $channel = '', $response_code = 200 ) {
        return new self( array(
            'success' => true,
            'channel' => $channel,
            'response_code' => $response_code,
        ));
    }


    /**
     * Build a failure result.
     *
     * @since 2.0.0
     * @param string $channel | Channel id.
     * @param string $error | Failure reason.
     * @param bool   $retryable | Whether the failure can be retried.
     * @param int    $response_code | Transport response code.
     * @return self
     */
    public static function failure( $channel = '', $error = '', $retryable = false, $response_code = 0 ) {
        return new self( array(
            'success' => false,
            'channel' => $channel,
            'error' => $error,
            'retryable' => $retryable,
            'response_code' => $response_code,
        ));
    }


    /**
     * Build a result from the details array returned by Api\Controller send methods
     * (see Controller::build_response_details()).
     *
     * @since 2.0.0
     * @param array<string,mixed> $details | Controller details array.
     * @param string              $channel | Channel id.
     * @return self
     */
    public static function from_controller_details( $details, $channel = '' ) {
        $details = is_array( $details ) ? $details : array();

        return new self( array(
            'success' => ! empty( $details['success'] ),
            'channel' => $channel,
            'response_code' => isset( $details['response_code'] ) ? (int) $details['response_code'] : 0,
            'retryable' => ! empty( $details['retryable'] ),
            'queued' => ! empty( $details['queued'] ),
            'error' => isset( $details['error'] ) ? (string) $details['error'] : '',
            'raw' => $details,
        ));
    }


    /**
     * Whether the dispatch succeeded.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_success() {
        return (bool) $this->success;
    }


    /**
     * Export the result as an associative array.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public function to_array() {
        return array(
            'success' => $this->success,
            'channel' => $this->channel,
            'response_code' => $this->response_code,
            'retryable' => $this->retryable,
            'queued' => $this->queued,
            'error' => $this->error,
        );
    }
}
