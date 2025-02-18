<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class to provide helper functions for general formatting and validation
 * 
 * @since 1.0.0
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
     * Format phone number
     * 
     * @since 1.0.0
     * @param string $phone | Full phone number
     * @return string Formatted phone number
     */
    public static function format_phone_number( $phone ) {
        // check if country code is 55 (Brasil)
        if ( strpos( $phone, '55' ) === 0 ) {
            $phone = substr( $phone, 2 ); // remove DDI

            // extract DDD
            $ddd = substr( $phone, 0, 2 );
            $number = substr( $phone, 2 );

            // count phone number
            if ( strlen( $number ) === 9 ) {
                $formatted_number = sprintf( '+55 (%s) %s-%s', $ddd, substr( $number, 0, 5 ), substr( $number, 5 ) );
            } else {
                $formatted_number = sprintf( '+55 (%s) %s-%s', $ddd, substr( $number, 0, 4 ), substr( $number, 4 ) );
            }

            return $formatted_number;
        }

        // Returns the original number if it is not a Brazilian IDD
        return $phone;
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
     * API key for requests for WhatsApp API
     * 
     * @since 1.0.0
     * @return string
     */
    public static function whatsapp_api_key() {
        $key = 'Q600ZcRqVNBXFwoKZvuV3EhBU3M0Tml2d2NQS0VpdEsrQXBPVzdaNWRhaHAyV2o4cWplaVNIckVydDBLOHlrTkRGVDRvdGFwbkdxUlRTYk8=';

        return self::decrypt_data( $key, 'B729F2659393EE27' );
    }
}