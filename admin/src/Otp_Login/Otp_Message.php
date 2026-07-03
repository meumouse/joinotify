<?php

namespace MeuMouse\Joinotify\Otp_Login;

use WP_User;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Channel-agnostic OTP delivery message.
 *
 * Value object passed to any delivery channel. It carries every piece of data a
 * channel might need to reach the user (phone, e-mail, the resolved user) plus
 * the generated code and the already-composed body, so each channel only has to
 * decide how to transport it.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Otp_Message {

    /**
     * Generated OTP code.
     *
     * @since 2.0.0
     * @var string
     */
    public $code = '';

    /**
     * Normalized recipient phone number (E.164-like, may be empty).
     *
     * @since 2.0.0
     * @var string
     */
    public $phone = '';

    /**
     * Recipient e-mail address (may be empty).
     *
     * @since 2.0.0
     * @var string
     */
    public $email = '';

    /**
     * Resolved WordPress user, when the message targets an existing account.
     *
     * @since 2.0.0
     * @var WP_User|null
     */
    public $user = null;

    /**
     * OTP lifetime in seconds.
     *
     * @since 2.0.0
     * @var int
     */
    public $expiry_seconds = 300;

    /**
     * Pre-composed message body (already localized and filtered).
     *
     * @since 2.0.0
     * @var string
     */
    public $body = '';

    /**
     * Free-form metadata for logging/telemetry (e.g. context, channel hints).
     *
     * @since 2.0.0
     * @var array<string,mixed>
     */
    public $context = array();

    /**
     * Construct function.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args | Message properties.
     * @return void
     */
    public function __construct( $args = array() ) {
        if ( ! is_array( $args ) ) {
            return;
        }

        if ( isset( $args['code'] ) ) {
            $this->code = (string) $args['code'];
        }

        if ( isset( $args['phone'] ) ) {
            $this->phone = (string) $args['phone'];
        }

        if ( isset( $args['email'] ) ) {
            $this->email = (string) $args['email'];
        }

        if ( isset( $args['user'] ) && $args['user'] instanceof WP_User ) {
            $this->user = $args['user'];
        }

        if ( isset( $args['expiry_seconds'] ) && is_numeric( $args['expiry_seconds'] ) ) {
            $this->expiry_seconds = (int) $args['expiry_seconds'];
        }

        if ( isset( $args['body'] ) ) {
            $this->body = (string) $args['body'];
        }

        if ( isset( $args['context'] ) && is_array( $args['context'] ) ) {
            $this->context = $args['context'];
        }
    }


    /**
     * Build a message from an associative array.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args | Message properties.
     * @return self
     */
    public static function from_array( $args ) {
        return new self( $args );
    }
}
