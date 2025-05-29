<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Admin\Default_Options;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class to provide helper functions for general formatting and validation
 * 
 * @since 1.0.0
 * @version 1.3.2
 * @package MeuMouse.com
 */
class Helpers {

    /**
     * Format time unit with singular/plural
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $time_unit
     * @param bool $plural
     * @return string
     */
    public static function format_time_unit( $time_unit, $plural ) {
        $units = apply_filters( 'Joinotify/Helpers/Format_Time_Unit', array(
            'seconds' => $plural ? __( 'segundos', 'joinotify' ) : __( 'segundo', 'joinotify' ),
            'minute' => $plural ? __( 'minutos', 'joinotify' ) : __( 'minuto', 'joinotify' ),
            'hours' => $plural ? __( 'horas', 'joinotify' ) : __( 'hora', 'joinotify' ),
            'day' => $plural ? __( 'dias', 'joinotify' ) : __( 'dia', 'joinotify' ),
            'week' => $plural ? __( 'semanas', 'joinotify' ) : __( 'semana', 'joinotify' ),
            'month' => $plural ? __( 'meses', 'joinotify' ) : __( 'mês', 'joinotify' ),
            'year' => $plural ? __( 'anos', 'joinotify' ) : __( 'ano', 'joinotify' ),
        ));

        return isset( $units[$time_unit] ) ? $units[$time_unit] : $time_unit;
    }


    /**
     * Validate and format a phone number, adding the default country code if missing.
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $phone | Raw phone number
     * @return string Formatted phone number with country code
     */
    public static function validate_and_format_phone( $phone ) {
        // Get the default country code from admin settings (e.g., "BR" for Brazil)
        $default_country_code = Admin::get_setting('joinotify_default_country_code');

        // Ensure country code is uppercase (as required by libphonenumber)
        $default_country_code = strtoupper( $default_country_code );

        // Instance of the phone number utility class
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            // Try parsing the phone number as an international number
            $numberProto = $phoneUtil->parse( $phone, null );

            // If the number already has a valid country code, format and return it in INTERNATIONAL format
            if ( $numberProto->hasCountryCode() ) {
                return $phoneUtil->format( $numberProto, PhoneNumberFormat::INTERNATIONAL );
            }
        } catch ( NumberParseException $e ) {
            // If parsing fails, assume the number is missing a country code
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // Check if the number starts with the default country code
        if ( ! preg_match( '/^' . preg_quote( $default_country_code ) . '/', $phone ) ) {
            $phone = $default_country_code . $phone;
        }

        try {
            // Parse again, now with the country code added
            $numberProto = $phoneUtil->parse('+' . $phone, null);

            // Return the formatted phone number in INTERNATIONAL format
            return $phoneUtil->format( $numberProto, PhoneNumberFormat::INTERNATIONAL );
        } catch ( NumberParseException $e ) {
            // If parsing fails again, return the original phone number
            return $phone;
        }
    }

    
    /**
     * Encrypt data
     * 
     * @since 1.0.0
     * @param string $data | Data for encrypt
     * @param string $key | Key for build encrypt
     * @return string
     */
    public static function encrypt_data( $data, $key ) {
        $cipher_method = 'AES-256-CBC';
    
        // Adjust the key to be 32 characters long (required for AES-256)
        $key = str_pad( $key, 32, '0' );
        
        // Generate a secure, random IV (Initialization Vector)
        $iv_lenght = openssl_cipher_iv_length( $cipher_method );
        $iv = openssl_random_pseudo_bytes( $iv_lenght );
    
        // encrypt data
        $encrypted_data = openssl_encrypt( $data, $cipher_method, $key, 0, $iv );
    
        // Return the encrypted data and IV, encoded in base64
        return base64_encode( $iv . $encrypted_data );
    }
    

    /**
     * Decrypt data
     * 
     * @since 1.0.0
     * @param string $encrypted_data | Encrypted data for decrypt
     * @param string $key | Key for decrypt data
     * @return string
     */
    public static function decrypt_data( $encrypted_data, $key ) {
        $cipher_method = 'AES-256-CBC';
    
        // Adjust the key to be 32 characters long (required for AES-256)
        $key = str_pad( $key, 32, '0' );
    
        // Decode encrypted data from base64 encrypted value
        $encrypted_data = base64_decode( $encrypted_data );
    
        // Separate the IV from the encrypted value
        $iv_lenght = openssl_cipher_iv_length( $cipher_method );
        $iv = substr( $encrypted_data, 0, $iv_lenght );
        $encrypted_data = substr( $encrypted_data, $iv_lenght );
    
        // return decrypted data
        return openssl_decrypt( $encrypted_data, $cipher_method, $key, 0, $iv );
    }


    /**
     * API key for requests on Slots Manager API
     * 
     * @since 1.3.0
     * @return string
     */
    public static function slots_manager_api_key() {
        $key = 'F5clS9xxRMwaDveTH4fS/WxnNVVBRVpHUnI3OTdvRlFpL0lZaGhBN2s2RDlRMDdkYmgrWnVZMnMxTXg2d1d5SkVkN3pEWndmeTg4d2ZMb1A=';

        return self::decrypt_data( $key, 'B729F2659393EE27' );
    }


    /**
     * Get switch options dynamically from default options
     *
     * @since 1.1.0
     * @version 1.3.2
     * @return array List of switch options keys
     */
    public static function get_switch_options() {
        $default_options = Default_Options::set_default_options();
        
        // filter only the indices that have 'yes' or 'no' as value
        return array_keys( array_filter( $default_options, function( $value ) {
            return in_array( $value, ['yes', 'no'], true );
        }));
    }


    /**
     * Check if the sender is allowed to send messages
     * 
     * @since 1.3.0
     * @param string $sender | Sender phone number
     * @return bool
     */
    public static function allowed_sender( $sender ) {
        $current_senders = get_option( 'joinotify_get_phones_senders', array() );

        return in_array( $sender, $current_senders );
    }


    /**
     * Validate if the given parameter is a string
     *
     * @since 1.0.0
     * @version 1.3.0
     * @param mixed $param
     * @return bool
     */
    public function validate_string( $param ) {
        return is_string( $param );
    }
}