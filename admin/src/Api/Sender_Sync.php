<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Phone_Manager;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Mirror the phone numbers connected on the Joinotify panel into the plugin.
 *
 * On the Cloud API a number is not something the site registers: it is connected
 * on the panel through Meta's Embedded Signup and identified by a
 * `phone_number_id` inside a business account (`waba_id`). The plugin therefore
 * imports them read-only instead of running the old slot + OTP onboarding.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Sender_Sync {

    /**
     * Transient key holding the last normalized listing.
     *
     * @var string
     */
    const CACHE_KEY = 'joinotify_cloud_numbers';

    /**
     * How long a successful listing is served from cache.
     *
     * Query routes are capped at 60 requests per minute per account, so the
     * admin screens must not hit the API on every render.
     *
     * @var int
     */
    const CACHE_TTL = 600;


    /**
     * Fetch the numbers connected to the account, across every business account.
     *
     * @since 2.3.0
     * @param bool $force | Bypass the cache and query the API again.
     * @return array|\WP_Error List of normalized sender arrays.
     */
    public static function fetch_numbers( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( self::CACHE_KEY );

            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        if ( ! Helpers::cloud_api_ready() ) {
            return new \WP_Error( 'joinotify_cloud_no_token', __( 'Connect your Joinotify account before syncing numbers.', 'joinotify' ) );
        }

        // Preferred path: one call, answered from Joinotify's mirror.
        $senders = Cloud_Client::list_senders();

        if ( ! is_wp_error( $senders ) ) {
            $numbers = array();

            foreach ( $senders as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }

                $sender = self::normalize_number( $entry, $entry['wabaId'] ?? '' );

                if ( '' !== $sender['phone'] ) {
                    $numbers[ $sender['phone'] ] = $sender;
                }
            }

            $numbers = array_values( $numbers );

            if ( ! empty( $numbers ) ) {
                set_transient( self::CACHE_KEY, $numbers, self::CACHE_TTL );
            }

            return $numbers;
        }

        // Fallback: walk the business accounts and ask Meta. Kept because a plugin
        // update can reach an API that predates /account/senders — and an install
        // that stops listing numbers is worse than one that lists them slowly.
        $waba_ids = Cloud_Client::list_wabas();

        if ( empty( $waba_ids ) ) {
            return new \WP_Error( 'joinotify_cloud_no_waba', __( 'No WhatsApp Business Account is linked to this token yet.', 'joinotify' ) );
        }

        $numbers = array();
        $last_error = null;

        foreach ( $waba_ids as $waba_id ) {
            $response = Cloud_Client::list_numbers( $waba_id );

            if ( is_wp_error( $response ) ) {
                $last_error = $response;
                continue;
            }

            foreach ( self::extract_entries( $response ) as $entry ) {
                $sender = self::normalize_number( $entry, $waba_id );

                if ( '' !== $sender['phone'] ) {
                    $numbers[ $sender['phone'] ] = $sender;
                }
            }
        }

        if ( empty( $numbers ) ) {
            // Only a real failure should surface; an account with no numbers is
            // a valid, empty answer.
            return $last_error instanceof \WP_Error ? $last_error : array();
        }

        $numbers = array_values( $numbers );

        // Cache successful, non-empty results only, so an outage never poisons it.
        set_transient( self::CACHE_KEY, $numbers, self::CACHE_TTL );

        return $numbers;
    }


    /**
     * Import the numbers connected on the panel, replacing the stored senders.
     *
     * @since 2.3.0
     * @return array|\WP_Error The stored sender phone list.
     */
    public static function sync() {
        $numbers = self::fetch_numbers( true );

        if ( is_wp_error( $numbers ) ) {
            return $numbers;
        }

        $stored = Phone_Manager::set_senders( $numbers );

        update_option( 'joinotify_senders_last_sync', time() );

        do_action( 'Joinotify/Cloud_Api/Senders_Synced', $numbers );

        return $stored;
    }


    /**
     * Store a listing the caller already has, without asking for it again.
     *
     * The license bootstrap answers with the credentials and the numbers in one
     * response, because that is the moment the plugin needs both. Calling sync()
     * there would throw that listing away and fetch the same thing a second time —
     * with a key issued seconds earlier, over a connection that just proved it works.
     *
     * @since 2.3.1
     * @param array $entries | Raw sender entries as the API returned them.
     * @return array The stored sender phone list.
     */
    public static function store( $entries ) {
        $numbers = array();

        foreach ( (array) $entries as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $sender = self::normalize_number( $entry, $entry['wabaId'] ?? '' );

            if ( '' !== $sender['phone'] ) {
                $numbers[ $sender['phone'] ] = $sender;
            }
        }

        $numbers = array_values( $numbers );
        $stored = Phone_Manager::set_senders( $numbers );

        if ( ! empty( $numbers ) ) {
            set_transient( self::CACHE_KEY, $numbers, self::CACHE_TTL );
        }

        update_option( 'joinotify_senders_last_sync', time() );

        do_action( 'Joinotify/Cloud_Api/Senders_Synced', $numbers );

        return $stored;
    }


    /**
     * Drop the cached listing.
     *
     * @since 2.3.0
     * @return void
     */
    public static function flush_cache() {
        delete_transient( self::CACHE_KEY );
    }


    /**
     * Timestamp of the last successful sync.
     *
     * @since 2.3.0
     * @return int Unix timestamp, or 0 when the numbers were never synced.
     */
    public static function last_sync_time() {
        return (int) get_option( 'joinotify_senders_last_sync', 0 );
    }


    /**
     * Pull the list of phone entries out of either response envelope.
     *
     * @since 2.3.0
     * @param array $response | Decoded API response.
     * @return array
     */
    protected static function extract_entries( $response ) {
        if ( ! is_array( $response ) ) {
            return array();
        }

        // Both the Meta mirror and the simplified endpoint wrap the list in `data`.
        $entries = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;

        return is_array( $entries ) ? array_filter( $entries, 'is_array' ) : array();
    }


    /**
     * Normalize a phone entry coming from either the Meta mirror or the
     * simplified `/numbers` endpoint into the plugin sender shape.
     *
     * The mirror answers with Meta's raw snake_case fields; the simplified
     * endpoint answers with Joinotify's camelCase `PhoneNumber` schema. Reading
     * both keeps the sync working when `/numbers` leaves the planned state.
     *
     * @since 2.3.0
     * @param array  $entry | Single phone entry.
     * @param string $waba_id | Business account the entry belongs to.
     * @return array
     */
    protected static function normalize_number( $entry, $waba_id ) {
        $display = (string) ( $entry['displayNumber'] ?? $entry['display_phone_number'] ?? '' );

        return array(
            'phone' => Phone_Manager::sanitize_phone( $display ),
            'phone_number_id' => (string) ( $entry['phoneNumberId'] ?? $entry['id'] ?? '' ),
            // The flat listing carries the business account per entry; the legacy
            // per-WABA path only knows it from the loop.
            'waba_id' => (string) ( $entry['wabaId'] ?? $waba_id ),
            'display_number' => $display,
            'verified_name' => (string) ( $entry['verifiedName'] ?? $entry['verified_name'] ?? '' ),
            'quality_rating' => (string) ( $entry['quality'] ?? $entry['qualityRating'] ?? $entry['quality_rating'] ?? 'UNKNOWN' ),
            // dedicated | coexistence. In coexistence the number stays on the phone,
            // so replies also arrive as `smb_message_echoes` and this install is not
            // the only place the conversation happens.
            'mode' => (string) ( $entry['mode'] ?? '' ),
            'messaging_limit' => (string) ( $entry['messagingLimit'] ?? $entry['messaging_limit_tier'] ?? '' ),
            'verified' => self::is_verified( $entry ),
        );
    }


    /**
     * Decide whether a number is verified from either response dialect.
     *
     * @since 2.3.0
     * @param array $entry | Single phone entry.
     * @return bool
     */
    protected static function is_verified( $entry ) {
        if ( isset( $entry['verified'] ) ) {
            return (bool) $entry['verified'];
        }

        // Meta reports it as a verification status string.
        return isset( $entry['code_verification_status'] ) && 'VERIFIED' === strtoupper( (string) $entry['code_verification_status'] );
    }
}
