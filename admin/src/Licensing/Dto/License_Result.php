<?php

namespace MeuMouse\Joinotify\Licensing\Dto;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Normalized outcome of a licensing operation.
 *
 * Every driver answers in this shape, so the code that persists license state
 * never has to know which server it talked to.
 *
 * The field that carries the most weight is {@see self::failure_kind()}. A
 * business failure is a real answer from the server ("this key is revoked") and
 * ends the operation. A transport failure means we never got an answer, and is
 * the only condition under which the orchestrator may try another driver —
 * falling back on a business failure would let a revoked key come back to life
 * on a second server.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class License_Result {

    /**
     * The operation succeeded.
     *
     * @since 2.1.0
     * @var string
     */
    const FAILURE_NONE = 'none';

    /**
     * The server answered, and the answer was "no".
     *
     * @since 2.1.0
     * @var string
     */
    const FAILURE_BUSINESS = 'business';

    /**
     * We never reached the server, or could not make sense of what came back.
     *
     * @since 2.1.0
     * @var string
     */
    const FAILURE_TRANSPORT = 'transport';

    /**
     * Whether the license is currently valid.
     *
     * @since 2.1.0
     * @var bool
     */
    protected $is_valid = false;

    /**
     * Why the operation failed, if it did.
     *
     * @since 2.1.0
     * @var string
     */
    protected $failure_kind = self::FAILURE_NONE;

    /**
     * Human readable message, suitable for surfacing in the admin.
     *
     * @since 2.1.0
     * @var string
     */
    protected $message = '';

    /**
     * License payload fields, keyed as the plugin stores them.
     *
     * @since 2.1.0
     * @var array
     */
    protected $data = array();

    /**
     * Construct a result.
     *
     * @since 2.1.0
     * @param bool $is_valid | Whether the license is valid
     * @param string $failure_kind | One of the FAILURE_* constants
     * @param string $message | Human readable message
     * @param array $data | License payload fields
     * @return void
     */
    public function __construct( $is_valid = false, $failure_kind = self::FAILURE_NONE, $message = '', $data = array() ) {
        $this->is_valid = (bool) $is_valid;
        $this->failure_kind = (string) $failure_kind;
        $this->message = (string) $message;
        $this->data = is_array( $data ) ? $data : array();
    }


    /**
     * A license the server confirmed as valid.
     *
     * @since 2.1.0
     * @param array $data | License payload fields
     * @param string $message | Human readable message
     * @return self
     */
    public static function valid( $data = array(), $message = '' ) {
        return new self( true, self::FAILURE_NONE, $message, $data );
    }


    /**
     * The server answered and refused the license.
     *
     * @since 2.1.0
     * @param string $message | Reason given by the server
     * @param array $data | Any payload fields that still came through
     * @return self
     */
    public static function business_failure( $message = '', $data = array() ) {
        return new self( false, self::FAILURE_BUSINESS, $message, $data );
    }


    /**
     * The server could not be reached or its answer was unusable.
     *
     * @since 2.1.0
     * @param string $message | Reason the request failed
     * @param array $data | Any diagnostic fields worth keeping
     * @return self
     */
    public static function transport_failure( $message = '', $data = array() ) {
        return new self( false, self::FAILURE_TRANSPORT, $message, $data );
    }


    /**
     * An operation that succeeded without asserting validity, such as a
     * deactivation.
     *
     * @since 2.1.0
     * @param string $message | Human readable message
     * @return self
     */
    public static function success( $message = '' ) {
        return new self( false, self::FAILURE_NONE, $message );
    }


    /**
     * Whether the license is valid.
     *
     * @since 2.1.0
     * @return bool
     */
    public function is_valid() {
        return $this->is_valid;
    }


    /**
     * Whether the operation completed without failing.
     *
     * @since 2.1.0
     * @return bool
     */
    public function succeeded() {
        return self::FAILURE_NONE === $this->failure_kind;
    }


    /**
     * Whether the server was unreachable. The only condition that justifies
     * trying a different driver.
     *
     * @since 2.1.0
     * @return bool
     */
    public function is_transport_failure() {
        return self::FAILURE_TRANSPORT === $this->failure_kind;
    }


    /**
     * Whether the server explicitly refused.
     *
     * @since 2.1.0
     * @return bool
     */
    public function is_business_failure() {
        return self::FAILURE_BUSINESS === $this->failure_kind;
    }


    /**
     * Failure classification.
     *
     * @since 2.1.0
     * @return string
     */
    public function failure_kind() {
        return $this->failure_kind;
    }


    /**
     * Human readable message.
     *
     * @since 2.1.0
     * @return string
     */
    public function message() {
        return $this->message;
    }


    /**
     * Read a payload field.
     *
     * @since 2.1.0
     * @param string $key | Field name
     * @param mixed $default | Value returned when the field is absent
     * @return mixed
     */
    public function get( $key, $default = null ) {
        return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
    }


    /**
     * All payload fields.
     *
     * @since 2.1.0
     * @return array
     */
    public function data() {
        return $this->data;
    }
}
