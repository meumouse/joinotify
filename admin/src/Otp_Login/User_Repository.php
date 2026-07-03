<?php

namespace MeuMouse\Joinotify\Otp_Login;

use WP_User;

defined('ABSPATH') || exit;

/**
 * Repository responsible for locating and updating users by phone metadata.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class User_Repository {

    /**
     * User meta keys used to persist the normalized phone number.
     *
     * @since 2.0.0
     * @var string[]
     */
    private $phone_meta_keys = array(
        'billing_phone',
        'joinotify_user_phone',
    );


    /**
     * Build the repository, allowing the meta key list to be filtered.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        /**
         * Filter the user meta keys searched when resolving an account by phone.
         *
         * @since 2.0.0
         * @param string[] $phone_meta_keys
         */
        $keys = apply_filters( 'Joinotify/Otp_Login/Phone_Meta_Keys', $this->phone_meta_keys );

        if ( is_array( $keys ) && ! empty( $keys ) ) {
            $this->phone_meta_keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
        }
    }


    /**
     * Find a user by comparing the normalized phone against supported meta keys.
     *
     * @since 2.0.0
     * @param string $phone Raw or normalized phone number.
     * @return WP_User|null Matching user object or null when not found.
     */
    public function find_by_phone( $phone ) {
        global $wpdb;

        $lookup_variants = Phone_Utils::lookup_variants( $phone );

        if ( empty( $lookup_variants ) ) {
            return null;
        }

        $meta_keys_placeholders = implode( ',', array_fill( 0, count( $this->phone_meta_keys ), '%s' ) );
        $value_placeholders = implode( ' OR meta_value = ', array_fill( 0, count( $lookup_variants ), '%s' ) );

        $sql = "
            SELECT user_id, meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key IN ({$meta_keys_placeholders})
            AND meta_value <> ''
            AND (
                meta_value = {$value_placeholders}
            )
        ";

        $prepared = array_merge( $this->phone_meta_keys, $lookup_variants );

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $prepared ) );

        if ( empty( $results ) ) {
            return null;
        }

        foreach ( $results as $result ) {
            if ( Phone_Utils::matches( $result->meta_value, $phone ) ) {
                $user = get_user_by( 'id', (int) $result->user_id );

                if ( $user instanceof WP_User ) {
                    return $user;
                }
            }
        }

        return null;
    }


    /**
     * Check whether a normalized phone number already belongs to another user.
     *
     * @since 2.0.0
     * @param string $phone Raw or normalized phone number.
     * @param int    $exclude_user_id Optional user ID to ignore during the lookup.
     * @return bool True when the phone is already assigned to another user.
     */
    public function phone_exists( $phone, $exclude_user_id = 0 ) {
        $user = $this->find_by_phone( $phone );

        if ( ! $user ) {
            return false;
        }

        return (int) $user->ID !== (int) $exclude_user_id;
    }


    /**
     * Save the normalized phone number to all supported user meta keys.
     *
     * @since 2.0.0
     * @param int    $user_id Target user ID.
     * @param string $phone Raw or normalized phone number.
     * @return void
     */
    public function save_phone( $user_id, $phone ) {
        $normalized = Phone_Utils::normalize( $phone );

        if ( empty( $normalized ) ) {
            return;
        }

        foreach ( $this->phone_meta_keys as $meta_key ) {
            update_user_meta( $user_id, $meta_key, $normalized );
        }
    }
}
