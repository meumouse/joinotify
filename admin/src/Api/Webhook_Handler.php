<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Debug_Log;
use MeuMouse\Joinotify\Core\Message_History;
use MeuMouse\Joinotify\Core\Phone_Manager;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Apply the events the Joinotify account delivers to this site.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Webhook_Handler {

    /**
     * Transient prefix marking a contact whose 24-hour window is open.
     *
     * @var string
     */
    const WINDOW_PREFIX = 'joinotify_window_';


    /**
     * Route one delivery to the right consumer.
     *
     * @since 2.3.0
     * @param array $event | Decoded delivery body.
     * @return void
     */
    public static function handle( $event ) {
        if ( ! is_array( $event ) ) {
            return;
        }

        $field = (string) ( $event['field'] ?? '' );
        $value = isset( $event['value'] ) && is_array( $event['value'] ) ? $event['value'] : array();

        switch ( $field ) {
            case 'messages':
            case 'smb_message_echoes':
                self::handle_messages( $value );
                break;

            case 'message_template_status_update':
            case 'message_template_quality_update':
            case 'message_template_components_update':
            case 'template_category_update':
                // Whatever changed, the cached catalogue no longer reflects it.
                Template_Repository::flush_cache();
                break;

            case 'phone_number_quality_update':
                self::handle_phone_quality( $value );
                break;
        }

        /**
         * Fires for every verified webhook delivery.
         *
         * @since 2.3.0
         * @param string $field Event name.
         * @param array  $value Event payload.
         */
        do_action( 'Joinotify/Cloud_Api/Webhook_Event', $field, $value );
    }


    /**
     * Apply delivery statuses and inbound messages.
     *
     * @since 2.3.0
     * @param array $value | `messages` event payload.
     * @return void
     */
    protected static function handle_messages( $value ) {
        foreach ( (array) ( $value['statuses'] ?? array() ) as $status ) {
            if ( is_array( $status ) ) {
                self::apply_status( $status );
            }
        }

        foreach ( (array) ( $value['messages'] ?? array() ) as $message ) {
            if ( ! is_array( $message ) || empty( $message['from'] ) ) {
                continue;
            }

            // A message from the contact opens a 24-hour window in which free
            // text and media are allowed without a template.
            self::open_window( (string) $message['from'] );
        }
    }


    /**
     * Record what actually happened to a message that was accepted earlier.
     *
     * @since 2.3.0
     * @param array $status | Single status entry.
     * @return void
     */
    protected static function apply_status( $status ) {
        $wamid = (string) ( $status['id'] ?? '' );
        $state = strtolower( (string) ( $status['status'] ?? '' ) );

        if ( '' === $wamid || '' === $state ) {
            return;
        }

        $error = '';

        if ( 'failed' === $state ) {
            $first = isset( $status['errors'][0] ) && is_array( $status['errors'][0] ) ? $status['errors'][0] : array();
            $error = (string) ( $first['title'] ?? $first['message'] ?? 'delivery_failed' );

            Debug_Log::record( array(
                'level' => 'error',
                'channel' => 'api',
                'message' => sprintf( 'WhatsApp delivery failed for %s', (string) ( $status['recipient_id'] ?? '' ) ),
                'code' => (string) ( $first['code'] ?? '' ),
                'context' => array(
                    'wamid' => $wamid,
                    'error' => $error,
                ),
            ) );
        }

        // `sent` is already the status the send path recorded, so only the later
        // transitions are worth writing back.
        if ( in_array( $state, array( 'delivered', 'read', 'failed' ), true ) ) {
            Message_History::update_status_by_wamid( $wamid, $state, $error );
        }
    }


    /**
     * Refresh the quality Meta assigns to one of the connected numbers.
     *
     * @since 2.3.0
     * @param array $value | `phone_number_quality_update` payload.
     * @return void
     */
    protected static function handle_phone_quality( $value ) {
        $display = (string) ( $value['display_phone_number'] ?? '' );
        $rating = strtoupper( (string) ( $value['event'] ?? $value['current_limit'] ?? '' ) );

        if ( '' === $display ) {
            return;
        }

        $phone = Phone_Manager::sanitize_phone( $display );

        if ( '' === $phone || '' === $rating ) {
            return;
        }

        Phone_Manager::set_sender_meta( $phone, array( 'quality_rating' => $rating ) );
    }


    /**
     * Mark the 24-hour session window of a contact as open.
     *
     * @since 2.3.0
     * @param string $phone | Contact phone number.
     * @return void
     */
    public static function open_window( $phone ) {
        $phone = Phone_Manager::sanitize_phone( $phone );

        if ( '' === $phone ) {
            return;
        }

        set_transient( self::WINDOW_PREFIX . md5( $phone ), time(), DAY_IN_SECONDS );
    }


    /**
     * Whether free-form text may still be delivered to a contact.
     *
     * Only meaningful once webhooks are registered: with no inbound events the
     * plugin has no way of knowing, so it answers false and callers fall back to
     * a template, which is always allowed.
     *
     * @since 2.3.0
     * @param string $phone | Contact phone number.
     * @return bool
     */
    public static function window_is_open( $phone ) {
        $phone = Phone_Manager::sanitize_phone( $phone );

        if ( '' === $phone ) {
            return false;
        }

        $opened_at = get_transient( self::WINDOW_PREFIX . md5( $phone ) );

        return is_numeric( $opened_at ) && ( time() - (int) $opened_at ) < DAY_IN_SECONDS;
    }
}
