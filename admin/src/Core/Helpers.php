<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Admin\Default_Options;
use MeuMouse\Joinotify\Validations\Country_Codes;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class to provide helper functions for general formatting and validation
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Helpers {

    /**
     * Format time unit with singular/plural
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $time_unit
     * @param bool $plural
     * @return string
     */
    public static function format_time_unit( $time_unit, $plural ) {
        $units = apply_filters( 'Joinotify/Helpers/Format_Time_Unit', array(
            'seconds' => $plural ? __( 'seconds', 'joinotify' ) : __( 'second', 'joinotify' ),
            'minute' => $plural ? __( 'minutes', 'joinotify' ) : __( 'minute', 'joinotify' ),
            'hours' => $plural ? __( 'hours', 'joinotify' ) : __( 'hour', 'joinotify' ),
            'day' => $plural ? __( 'days', 'joinotify' ) : __( 'day', 'joinotify' ),
            'week' => $plural ? __( 'weeks', 'joinotify' ) : __( 'week', 'joinotify' ),
            'month' => $plural ? __( 'months', 'joinotify' ) : __( 'month', 'joinotify' ),
            'year' => $plural ? __( 'years', 'joinotify' ) : __( 'year', 'joinotify' ),
        ));

        return isset( $units[$time_unit] ) ? $units[$time_unit] : $time_unit;
    }


    /**
     * Validate and format a phone number, adding the default country code if missing.
     *
     * @since 1.0.0
     * @version 2.2.0
     * @param string $phone | Raw phone number
     * @return string Formatted phone number with country code
     */
    public static function validate_and_format_phone( $phone ) {
        // Get the default dial code from admin settings (e.g., "55" for Brazil).
        $default_dial_code = Admin::get_setting('joinotify_default_country_code');

        // The setting stores a numeric dial code ("55"), but libphonenumber's
        // parse() expects an ISO 3166-1 alpha-2 region code ("BR"). Convert it
        // so the fallback region is actually applied when the number has no
        // country code; passing the raw dial code made parse() throw and the
        // number was returned without the country prefix.
        $default_region = self::dial_code_to_region( $default_dial_code );

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

        try {
            // The number has no country code, so parse it as a NATIONAL number for
            // the configured default region (e.g. "BR"). libphonenumber then applies
            // the correct dialing prefix. The previous approach prepended the ISO
            // region letters ("BR") to a digit string and parsed "+BR55..." — which
            // always threw and returned the number without a country code.
            $numberProto = $phoneUtil->parse( $phone, $default_region );

            // Return the formatted phone number in INTERNATIONAL format
            return $phoneUtil->format( $numberProto, PhoneNumberFormat::INTERNATIONAL );
        } catch ( NumberParseException $e ) {
            // If parsing fails again, return the original digits
            return $phone;
        }
    }


    /**
     * Convert a numeric dial code (e.g. "55") into an ISO 3166-1 alpha-2 region
     * code (e.g. "BR"), which is what libphonenumber expects as a default region.
     *
     * @since 2.2.0
     * @param string $dial_code | Numeric dial code (e.g. "55")
     * @return string|null ISO2 region code (e.g. "BR"), or null when it cannot be resolved
     */
    public static function dial_code_to_region( $dial_code ) {
        $dial_code = preg_replace( '/\D/', '', (string) $dial_code );

        // "0" is the "None" option and an empty value means "no default region".
        if ( '' === $dial_code || '0' === $dial_code ) {
            return null;
        }

        $countries = Country_Codes::get_country_codes_with_names();

        if ( ! isset( $countries[ $dial_code ] ) ) {
            return null;
        }

        // Each dial code maps to one or more ISO2 regions; use the first one as
        // the default (e.g. "55" => "BR", "1" => "US").
        $region = array_key_first( $countries[ $dial_code ] );

        return ( is_string( $region ) && '' !== $region ) ? strtoupper( $region ) : null;
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
     * Resolve a WhatsApp Cloud API credential.
     *
     * Prefers the manual override saved in the settings screen; when empty,
     * falls back to the value provisioned by the license activation (stored on
     * the public license response object). Mirrors slots_manager_api_key() as
     * the single accessor for the current relay key.
     *
     * @since 1.4.8
     * @param string $setting_key | Settings key holding the manual override.
     * @param string $license_key | Field name on the license response object.
     * @return string
     */
    private static function cloud_credential( $setting_key, $license_key ) {
        $manual = \MeuMouse\Joinotify\Admin\Admin::get_setting( $setting_key );

        if ( is_string( $manual ) && '' !== trim( $manual ) ) {
            return trim( $manual );
        }

        $license = get_option( 'joinotify_license_response_object' );

        if ( is_object( $license ) && isset( $license->$license_key ) && is_string( $license->$license_key ) ) {
            return trim( $license->$license_key );
        }

        if ( is_array( $license ) && isset( $license[ $license_key ] ) && is_string( $license[ $license_key ] ) ) {
            return trim( $license[ $license_key ] );
        }

        return '';
    }


    /**
     * WhatsApp Cloud API bearer token (sk_live_... / sk_test_...).
     *
     * @since 1.4.8
     * @return string
     */
    public static function cloud_api_token() {
        return self::cloud_credential( 'whatsapp_cloud_api_token', 'api_token' );
    }


    /**
     * Default Cloud API phone_number_id used as the message origin ('from').
     *
     * @since 1.4.8
     * @return string
     */
    public static function cloud_phone_number_id() {
        return self::cloud_credential( 'whatsapp_phone_number_id', 'phone_number_id' );
    }


    /**
     * WhatsApp Business Account id (waba_id) that owns the templates.
     *
     * @since 1.4.8
     * @return string
     */
    public static function cloud_waba_id() {
        return self::cloud_credential( 'whatsapp_waba_id', 'waba_id' );
    }


    /**
     * Whether the WhatsApp Cloud API is usable (a bearer token is available).
     *
     * @since 1.4.8
     * @return bool
     */
    public static function cloud_api_ready() {
        return '' !== self::cloud_api_token();
    }


    /**
     * Get switch options dynamically from default options
     *
     * @since 1.1.0
     * @version 1.4.7
     * @return array List of switch options keys
     */
    public static function get_switch_options() {
        $default_options = Default_Options::set_default_options();

        // filter only the indices that have 'yes' or 'no' as value
        $keys = array_keys( array_filter( $default_options, function( $value ) {
            return in_array( $value, ['yes', 'no'], true );
        }));

        // Also include toggle keys declared only in the (filterable) settings schema — e.g.
        // third-party integration toggles that are not in Default_Options — so they get the
        // same "unchecked => 'no'" reset semantics on save regardless of the payload shape.
        $registry = '\MeuMouse\Joinotify\Admin\Settings\Registry';

        if ( class_exists( $registry ) && method_exists( $registry, 'get_field_definitions' ) ) {
            foreach ( $registry::get_field_definitions() as $field_key => $field ) {
                $field_type = is_array( $field ) && isset( $field['type'] ) ? $field['type'] : '';

                if ( in_array( $field_type, array( 'toggle', 'switch' ), true ) && ! in_array( $field_key, $keys, true ) ) {
                    $keys[] = (string) $field_key;
                }
            }
        }

        return $keys;
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
     * Remove recursively any object from an array
     *
     * @param mixed $data | Data to be cleaned
     * @return mixed
     */
    public static function strip_objects( $data ) {
        if ( is_array( $data ) ) {
            $clean = array();

            foreach ( $data as $key => $value ) {
                if ( is_object( $value ) ) {
                    // skip objects
                    continue;
                } elseif ( is_array( $value ) ) {
                    // recursively for sub-arrays
                    $clean[ $key ] = self::strip_objects( $value );
                } else {
                    $clean[ $key ] = $value;
                }
            }
            return $clean;
        }

        // is not array, return as is (scalar, null, etc)
        return $data;
    }


    /**
     * Encode emoji characters recursively to avoid database charset issues.
     *
     * @since 1.4.3
     * @param mixed $data | Data to be encoded
     * @return mixed
     */
    public static function encode_emoji_deep( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = self::encode_emoji_deep( $value );
            }

            return $data;
        }

        return is_string( $data ) ? self::encode_emoji( $data ) : $data;
    }


    /**
     * Decode emoji entities recursively for rendering.
     *
     * @since 1.4.3
     * @param mixed $data | Data to be decoded
     * @return mixed
     */
    public static function decode_emoji_deep( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = self::decode_emoji_deep( $value );
            }

            return $data;
        }

        return is_string( $data ) ? self::decode_emoji( $data ) : $data;
    }


    /**
     * Update workflow content metadata encoding emoji beforehand.
     *
     * @since 1.4.3
     * @param int   $post_id | Post ID
     * @param array $workflow_content | Workflow content data
     * @return bool|int
     */
    public static function update_workflow_content_meta( $post_id, $workflow_content ) {
        $prepared_content = self::encode_emoji_deep( $workflow_content );

        return update_post_meta( $post_id, 'joinotify_workflow_content', $prepared_content );
    }


    /**
     * Encode emoji characters to HTML entities
     *
     * @since 1.4.3
     * @param string $content | Text content
     * @return string
     */
    public static function encode_emoji( $content ) {
        if ( ! function_exists( '_wp_emoji_list' ) ) {
            require_once ABSPATH . WPINC . '/formatting.php';
        }
        
        $emoji = _wp_emoji_list( 'partials' );

        foreach ( $emoji as $emojum ) {
            $emoji_char = html_entity_decode( $emojum );
            if ( str_contains( $content, $emoji_char ) ) {
                $content = preg_replace( "/$emoji_char/", $emojum, $content );
            }
        }

        return $content;
    }

    /**
     * Decode emoji HTML entities back to characters
     *
     * @since 1.4.3
     * @param string $content | Text content with HTML entities
     * @return string
     */
    public static function decode_emoji( $content ) {
        // Decode HTML entities (including emoji entities)
        $decoded = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        
        // Additional check for any remaining numeric HTML entities
        $decoded = preg_replace_callback(
            '/&#(\d+);/',
            function( $matches ) {
                return chr( $matches[1] );
            },
            $decoded
        );
        
        // Check for hex entities
        $decoded = preg_replace_callback(
            '/&#x([0-9A-F]+);/i',
            function( $matches ) {
                return chr( hexdec( $matches[1] ) );
            },
            $decoded
        );
        
        return $decoded;
    }


    /**
     * Get workflow content metadata decoding emoji entities.
     *
     * @since 1.4.3
     * @param int $post_id | Post ID
     * @return mixed
     */
    public static function get_workflow_content_meta( $post_id ) {
        $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );

        return self::decode_emoji_deep( $workflow_content );
    }


    /**
     * Update the visual-only canvas sticky notes (editor_notes) metadata.
     *
     * These notes document the flow and never affect execution.
     *
     * @since 2.0.0
     * @param int   $post_id | Post ID
     * @param array $editor_notes | Sticky notes data
     * @return bool|int
     */
    public static function update_workflow_editor_notes_meta( $post_id, $editor_notes ) {
        $prepared_notes = self::encode_emoji_deep( is_array( $editor_notes ) ? $editor_notes : array() );

        return update_post_meta( $post_id, 'joinotify_editor_notes', $prepared_notes );
    }


    /**
     * Get the visual-only canvas sticky notes (editor_notes) metadata.
     *
     * @since 2.0.0
     * @param int $post_id | Post ID
     * @return array
     */
    public static function get_workflow_editor_notes_meta( $post_id ) {
        $editor_notes = get_post_meta( $post_id, 'joinotify_editor_notes', true );
        $editor_notes = self::decode_emoji_deep( $editor_notes );

        return is_array( $editor_notes ) ? $editor_notes : array();
    }
}