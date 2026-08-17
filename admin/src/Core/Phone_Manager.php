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
     * WordPress option key that stores the per sender metadata map.
     *
     * Kept apart from OPTION_KEY so the sender list stays a flat array of phone
     * strings and every existing consumer keeps working untouched.
     *
     * @since 2.3.0
     * @var string
     */
    const META_OPTION_KEY = 'joinotify_sender_meta';


    /**
     * Metadata keys accepted for a sender, mapped to their sanitize callback.
     *
     * @since 2.3.0
     * @var array
     */
    const META_FIELDS = array(
        'phone_number_id' => 'sanitize_text_field',
        'waba_id' => 'sanitize_text_field',
        'display_number' => 'sanitize_text_field',
        'verified_name' => 'sanitize_text_field',
        'quality_rating' => 'sanitize_text_field',
        'messaging_limit' => 'sanitize_text_field',
        'mode' => 'sanitize_text_field',
        'verified' => 'boolval',
    );


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
     * @version 2.3.0
     * @param string $phone Digits-only phone number.
     * @param array  $meta  Optional Cloud API metadata for the sender.
     * @return void
     */
    public static function add_sender( $phone, $meta = array() ) {
        $senders = self::get_senders();

        if ( ! in_array( $phone, $senders, true ) ) {
            $senders[] = $phone;
        }

        update_option( self::OPTION_KEY, array_values( $senders ) );

        if ( ! empty( $meta ) ) {
            self::set_sender_meta( $phone, $meta );
        }

        /** This action is documented in admin/src/Api/Connect.php */
        do_action( 'Joinotify/Sender_Selected', 'manual' );
    }


    /**
     * Remove a phone number from the senders list, along with its metadata.
     *
     * @since 1.4.7
     * @version 2.3.0
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

        $all_meta = self::get_all_sender_meta();

        if ( isset( $all_meta[ $phone ] ) ) {
            unset( $all_meta[ $phone ] );
            update_option( self::META_OPTION_KEY, $all_meta );
        }

        return true;
    }


    /**
     * Replace the whole senders list at once, pruning metadata of dropped numbers.
     *
     * Used by the Cloud API sync, which mirrors the numbers connected on the
     * Joinotify panel instead of appending them one by one.
     *
     * @since 2.3.0
     * @param array $senders List of phone entries. Each item is either a digits-only phone
     *                       string or an array with a `phone` key plus metadata fields.
     * @return string[] The stored sender list.
     */
    public static function set_senders( $senders ) {
        $phones = array();
        $meta_map = array();

        foreach ( (array) $senders as $sender ) {
            $phone = is_array( $sender ) ? ( $sender['phone'] ?? '' ) : $sender;
            $phone = self::sanitize_phone( $phone );

            if ( '' === $phone || in_array( $phone, $phones, true ) ) {
                continue;
            }

            $phones[] = $phone;

            if ( is_array( $sender ) ) {
                $meta = self::sanitize_meta( $sender );

                if ( ! empty( $meta ) ) {
                    $meta_map[ $phone ] = $meta;
                }
            }
        }

        update_option( self::OPTION_KEY, $phones );

        // keep the metadata of numbers that came back without one, drop the rest
        $previous = self::get_all_sender_meta();

        foreach ( $phones as $phone ) {
            if ( ! isset( $meta_map[ $phone ] ) && isset( $previous[ $phone ] ) ) {
                $meta_map[ $phone ] = $previous[ $phone ];
            }
        }

        update_option( self::META_OPTION_KEY, $meta_map );

        return $phones;
    }


    /**
     * Retrieve the metadata map for every sender.
     *
     * @since 2.3.0
     * @return array Map of phone => metadata array.
     */
    public static function get_all_sender_meta() {
        $meta = get_option( self::META_OPTION_KEY, array() );

        return is_array( $meta ) ? $meta : array();
    }


    /**
     * Retrieve the metadata stored for a single sender.
     *
     * @since 2.3.0
     * @param string $phone Digits-only phone number.
     * @return array Metadata array, empty when the sender has none.
     */
    public static function get_sender_meta( $phone ) {
        $all_meta = self::get_all_sender_meta();
        $phone = self::sanitize_phone( $phone );

        return isset( $all_meta[ $phone ] ) && is_array( $all_meta[ $phone ] ) ? $all_meta[ $phone ] : array();
    }


    /**
     * Merge metadata into the entry of a single sender.
     *
     * @since 2.3.0
     * @param string $phone Digits-only phone number.
     * @param array  $meta  Metadata fields to store. Unknown keys are discarded.
     * @return array The resulting metadata for the sender.
     */
    public static function set_sender_meta( $phone, $meta ) {
        $phone = self::sanitize_phone( $phone );

        if ( '' === $phone ) {
            return array();
        }

        $all_meta = self::get_all_sender_meta();
        $current = isset( $all_meta[ $phone ] ) && is_array( $all_meta[ $phone ] ) ? $all_meta[ $phone ] : array();
        $merged = array_merge( $current, self::sanitize_meta( $meta ) );

        $all_meta[ $phone ] = $merged;

        update_option( self::META_OPTION_KEY, $all_meta );

        return $merged;
    }


    /**
     * Resolve the Meta phone number ID of a sender.
     *
     * This is what lets the Cloud API pick the right origin number when the account
     * has more than one connected. Empty means "let the API use the account default".
     *
     * @since 2.3.0
     * @param string $phone Digits-only phone number.
     * @return string
     */
    public static function get_phone_number_id( $phone ) {
        $meta = self::get_sender_meta( $phone );

        return isset( $meta['phone_number_id'] ) ? (string) $meta['phone_number_id'] : '';
    }


    /**
     * Resolve the WhatsApp Business Account ID a sender belongs to.
     *
     * @since 2.3.0
     * @param string $phone Digits-only phone number.
     * @return string
     */
    public static function get_waba_id( $phone ) {
        $meta = self::get_sender_meta( $phone );

        return isset( $meta['waba_id'] ) ? (string) $meta['waba_id'] : '';
    }


    /**
     * Retrieve every distinct WABA ID known from the stored senders.
     *
     * @since 2.3.0
     * @return string[]
     */
    public static function get_known_waba_ids() {
        $waba_ids = array();

        foreach ( self::get_all_sender_meta() as $meta ) {
            if ( ! is_array( $meta ) || empty( $meta['waba_id'] ) ) {
                continue;
            }

            $waba_id = (string) $meta['waba_id'];

            if ( ! in_array( $waba_id, $waba_ids, true ) ) {
                $waba_ids[] = $waba_id;
            }
        }

        return $waba_ids;
    }


    /**
     * Keep only the known metadata fields and run each through its sanitize callback.
     *
     * @since 2.3.0
     * @param array $meta Raw metadata.
     * @return array
     */
    protected static function sanitize_meta( $meta ) {
        $sanitized = array();

        foreach ( self::META_FIELDS as $key => $callback ) {
            if ( ! isset( $meta[ $key ] ) ) {
                continue;
            }

            $sanitized[ $key ] = call_user_func( $callback, $meta[ $key ] );
        }

        return $sanitized;
    }
}
