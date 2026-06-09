<?php

namespace MeuMouse\Joinotify\Notifications;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Facade/dispatcher for notification delivery.
 *
 * Single entry point for sending a notification through any channel. It resolves
 * the target channel (from the message or the default), validates it can deliver,
 * dispatches, and logs failures — keeping the rest of the plugin agnostic to
 * whether the message travels over WhatsApp, Telegram, e-mail, SMS, etc.
 *
 * Mirrors Otp_Login\Channel_Manager.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Notifications
 * @author MeuMouse.com
 */
class Channel_Manager {

    /**
     * Get the default channel id.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_default_channel_id() {
        /**
         * Filter the default notification channel id used when a message does
         * not specify one.
         *
         * @since 2.0.0
         * @param string $channel_id
         */
        $id = apply_filters( 'Joinotify/Notifications/Default_Channel', 'whatsapp' );

        return ( is_string( $id ) && '' !== trim( $id ) ) ? trim( $id ) : 'whatsapp';
    }


    /**
     * Resolve the channel that should handle the given message.
     *
     * @since 2.0.0
     * @param Notification_Message $message | Message to deliver.
     * @return Channel_Interface|null
     */
    public static function resolve_channel( Notification_Message $message ) {
        $id = ( '' !== trim( (string) $message->channel ) ) ? trim( (string) $message->channel ) : self::get_default_channel_id();

        return Channel_Registry::get_channel( $id );
    }


    /**
     * Deliver a notification through the resolved channel.
     *
     * Always returns a Channel_Result (never throws), so callers can branch on
     * the normalized outcome regardless of the channel.
     *
     * @since 2.0.0
     * @param Notification_Message $message | Message to deliver.
     * @return Channel_Result
     */
    public static function dispatch( Notification_Message $message ) {
        $channel = self::resolve_channel( $message );

        if ( ! $channel instanceof Channel_Interface ) {
            $requested = ( '' !== trim( (string) $message->channel ) ) ? $message->channel : self::get_default_channel_id();

            Logger::register_log( 'Joinotify Notifications: no channel resolved for "' . $requested . '".', 'ERROR' );

            return Channel_Result::failure( (string) $requested, 'channel_unavailable' );
        }

        if ( ! $channel->is_configured() ) {
            Logger::register_log( 'Joinotify Notifications: channel "' . $channel->get_id() . '" is not configured.', 'WARNING' );

            return Channel_Result::failure( $channel->get_id(), 'channel_unconfigured' );
        }

        if ( ! $channel->supports( $message ) ) {
            Logger::register_log( 'Joinotify Notifications: channel "' . $channel->get_id() . '" cannot deliver this message.', 'WARNING' );

            return Channel_Result::failure( $channel->get_id(), 'channel_unsupported' );
        }

        /**
         * Filter the message right before delivery.
         *
         * @since 2.0.0
         * @param Notification_Message $message
         * @param Channel_Interface    $channel
         */
        $message = apply_filters( 'Joinotify/Notifications/Message_Before_Send', $message, $channel );

        $result = $channel->send( $message );

        // normalize defensively: a channel must return a Channel_Result
        if ( ! $result instanceof Channel_Result ) {
            $result = Channel_Result::failure( $channel->get_id(), 'invalid_channel_result' );
        }

        if ( ! $result->is_success() ) {
            Logger::register_log( 'Joinotify Notifications: delivery via "' . $channel->get_id() . '" failed (' . $result->error . ').', 'ERROR' );
        }

        /**
         * Fires after a notification dispatch attempt (success or failure).
         *
         * @since 2.0.0
         * @param Channel_Result       $result
         * @param Notification_Message $message
         * @param Channel_Interface    $channel
         */
        do_action( 'Joinotify/Notifications/Message_Sent', $result, $message, $channel );

        return $result;
    }
}
