<?php

namespace MeuMouse\Joinotify\Validations;

use MeuMouse\Joinotify\API\Controller;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for handler with OTP validations
 * 
 * @since 1.0.0
 * @version 1.0.3
 * @package MeuMouse.com
 */
class Otp_Validation {

    public static $otp_length = 4;
    public static $otp_expiry_time = 300; // 5 minutes

    /**
     * Generate OTP code, storage for future validation and send message via WhatsApp
     *
     * @since 1.0.0
     * @param string $phone | Phone number
     * @return bool Returns true if the OTP was generated and sent successfully, false otherwise.
     */
    public static function generate_and_send_otp( $phone ) {
        $otp = self::generate_otp();
        $expiration_time = time() + self::$otp_expiry_time;

        // Save OTP and Expiry Time
        self::store_otp( $phone, $otp, $expiration_time );
        $send_otp = Controller::send_validation_otp( $phone, $otp );

        return 201 === $send_otp;
    }


    /**
     * Generate a random OTP code
     *
     * @since 1.0.0
     * @return string The generated OTP code
     */
    static function generate_otp() {
        return str_pad( random_int( 0, 9999 ), self::$otp_length, '0', STR_PAD_LEFT) ;
    }


    /**
     * Stores the generated OTP and its expiration time for future validation
     *
     * @since 1.0.0
     * @param string $phone | Phone number
     * @param string $otp | The generated OTP code
     * @param int $expiration_time | OTP Expiry Time in Unix Timestamp
     */
    static function store_otp( $phone, $otp, $expiration_time ) {
        set_transient( 'joinotify_otp_' . md5( $phone ), ['otp' => $otp, 'expires' => $expiration_time], self::$otp_expiry_time );
    }


    /**
     * Validates the OTP provided by the user
     *
     * @since 1.0.0
     * @param string $phone | Phone number
     * @param string $user_provided_otp | The OTP provided by the user
     * @return bool Returns true if the OTP is valid, false otherwise
     */
    public static function validate_otp( $phone, $user_provided_otp ) {
        $stored_data = get_transient( 'joinotify_otp_' . md5( $phone ) );

        if ( $stored_data && $stored_data['otp'] === $user_provided_otp ) {
            if ( time() <= $stored_data['expires'] ) {
                // OTP valid and within expiration time
                delete_transient( 'joinotify_otp_' . md5( $phone ) ); // Remove OTP after validation to prevent reuse

                return true;
            } else {
                // OTP expired
                delete_transient( 'joinotify_otp_' . md5( $phone ) );
            }
        }

        return false;
    }
}