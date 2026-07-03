<?php

namespace MeuMouse\Joinotify\Core;

defined('ABSPATH') || exit;

/**
 * Centralises all read/write operations for the registered phone senders option.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Phone_Manager {

    /**
     * WordPress option key that stores the array of registered sender phones.
     *
     * @var string
     */
    const OPTION_KEY = 'joinotify_get_phones_senders';


    /**
     * Strip all non-digit characters from a phone string and sanitize it.
     *
     * @since 1.4.7
     * @param string $phone Raw phone value (may include dashes, spaces, parentheses, etc.).
     * @return string Digits-only phone string.
     */
    public static function sanitize_phone( $phone ) {
        return preg_replace( '/\D+/', '', sanitize_text_field( $phone ) );
    }


    /**
     * Retrieve the full list of registered sender phone numbers.
     *
     * @since 1.4.7
     * @return string[]
     */
    public static function get_senders() {
        $senders = get_option( self::OPTION_KEY, array() );
        return is_array( $senders ) ? $senders : array();
    }


    /**
     * Add a phone number to the senders list if it is not already present.
     *
     * @since 1.4.7
     * @param string $phone Digits-only phone number.
     * @return void
     */
    public static function add_sender( $phone ) {
        $senders = self::get_senders();

        if ( ! in_array( $phone, $senders, true ) ) {
            $senders[] = $phone;
        }

        update_option( self::OPTION_KEY, array_values( $senders ) );
    }


    /**
     * Remove a phone number from the senders list.
     *
     * @since 1.4.7
     * @param string $phone Digits-only phone number.
     * @return bool True when the phone was found and removed; false when it was not in the list.
     */
    public static function remove_sender( $phone ) {
        $senders = self::get_senders();

        if ( ! in_array( $phone, $senders, true ) ) {
            return false;
        }

        $senders = array_values( array_filter( $senders, static function ( $item ) use ( $phone ) {
            return $item !== $phone;
        } ) );

        update_option( self::OPTION_KEY, $senders );

        return true;
    }
}
