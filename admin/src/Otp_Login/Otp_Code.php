<?php

namespace MeuMouse\Joinotify\Otp_Login;

use WP_User;

defined('ABSPATH') || exit;

/**
 * Handles OTP generation, storage, delivery and validation for the login flow.
 *
 * Delivery is channel-agnostic: this class only generates and persists the code
 * (with a bounded number of verification attempts) and hands the composed
 * message to the Channel_Manager, which routes it to the active channel
 * (WhatsApp today; e-mail/Telegram once registered).
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Otp_Code {

    /**
     * Default OTP length.
     *
     * @since 2.0.0
     * @var int
     */
    const OTP_LENGTH = 6;

    /**
     * Base transient key used to store OTP payloads.
     *
     * @since 2.0.0
     * @var string
     */
    const TRANSIENT_KEY = 'joinotify_otp_login_';

    /**
     * Maximum verification attempts allowed per generated code.
     *
     * @since 2.0.0
     * @var int
     */
    const MAX_ATTEMPTS = 5;

    /**
     * OTP code length.
     *
     * @since 2.0.0
     * @var int
     */
    public $otp_length;

    /**
     * OTP expiration time in seconds.
     *
     * @since 2.0.0
     * @var int
     */
    public $otp_expiry_time;

    /**
     * Initialize OTP defaults.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        $this->otp_length = (int) apply_filters( 'Joinotify/Otp_Login/Otp_Length', self::OTP_LENGTH );
        $this->otp_expiry_time = (int) apply_filters( 'Joinotify/Otp_Login/Otp_Expiry_Time', 300 );
    }


    /**
     * Generate, store and deliver an OTP code to the resolved user.
     *
     * @since 2.0.0
     * @param string       $phone Normalized phone number.
     * @param WP_User|null $user Resolved user, when available.
     * @return bool|\WP_Error True on success, WP_Error on delivery failure.
     */
    public function generate_and_send_otp( $phone, $user = null ) {
        $phone = Phone_Utils::normalize( $phone );
        $otp = $this->generate_otp();
        $expiration_time = time() + (int) $this->otp_expiry_time;

        $this->store_otp( $phone, $otp, $expiration_time );

        $message = new Otp_Message( array(
            'code' => $otp,
            'phone' => $phone,
            'email' => $user instanceof WP_User ? $user->user_email : '',
            'user' => $user instanceof WP_User ? $user : null,
            'expiry_seconds' => (int) $this->otp_expiry_time,
            'body' => $this->set_message( $otp ),
            'context' => array( 'flow' => 'otp_login' ),
        ) );

        return Channel_Manager::send( $message );
    }


    /**
     * Build the message body that carries the OTP code.
     *
     * @since 2.0.0
     * @param string $otp Generated OTP code.
     * @return string Message body.
     */
    public function set_message( $otp ) {
        $minutes = max( 1, (int) round( $this->otp_expiry_time / 60 ) );

        $message = sprintf(
            /* translators: 1: OTP code, 2: minutes until expiration. */
            __( 'Your access code is: %1$s. This code expires in %2$d minutes.', 'joinotify' ),
            $otp,
            $minutes
        );

        return apply_filters( 'Joinotify/Otp_Login/Message', $message, $otp, $this->otp_expiry_time );
    }


    /**
     * Generate a random numeric OTP string.
     *
     * @since 2.0.0
     * @return string Generated OTP code.
     */
    public function generate_otp() {
        $max = (int) str_repeat( '9', $this->otp_length );

        return str_pad( (string) random_int( 0, $max ), $this->otp_length, '0', STR_PAD_LEFT );
    }


    /**
     * Store the OTP payload in a transient for later validation.
     *
     * @since 2.0.0
     * @param string $phone Normalized phone number.
     * @param string $otp Generated OTP code.
     * @param int    $expiration_time Unix timestamp for expiration.
     * @return void
     */
    public function store_otp( $phone, $otp, $expiration_time ) {
        set_transient(
            self::TRANSIENT_KEY . md5( $phone ),
            array(
                'otp' => $otp,
                'expires' => $expiration_time,
                'attempts' => 0,
            ),
            $this->otp_expiry_time
        );
    }


    /**
     * Validate the submitted OTP against the stored transient payload.
     *
     * Each wrong attempt is counted; once the attempt budget is exhausted the
     * stored code is dropped so it cannot be brute-forced within the window.
     *
     * @since 2.0.0
     * @param string $phone Normalized phone number.
     * @param string $user_provided_otp Submitted OTP code.
     * @return bool True when the OTP is valid and not expired.
     */
    public function validate_otp( $phone, $user_provided_otp ) {
        $key = self::TRANSIENT_KEY . md5( $phone );
        $stored_data = get_transient( $key );

        if ( ! is_array( $stored_data ) || empty( $stored_data['otp'] ) ) {
            return false;
        }

        if ( time() > (int) $stored_data['expires'] ) {
            delete_transient( $key );

            return false;
        }

        $max_attempts = (int) apply_filters( 'Joinotify/Otp_Login/Max_Attempts', self::MAX_ATTEMPTS );

        if ( hash_equals( (string) $stored_data['otp'], (string) $user_provided_otp ) ) {
            delete_transient( $key );

            return true;
        }

        $attempts = (int) ( $stored_data['attempts'] ?? 0 ) + 1;

        if ( $attempts >= $max_attempts ) {
            delete_transient( $key );

            return false;
        }

        $stored_data['attempts'] = $attempts;
        $remaining_ttl = max( 1, (int) $stored_data['expires'] - time() );

        set_transient( $key, $stored_data, $remaining_ttl );

        return false;
    }
}
