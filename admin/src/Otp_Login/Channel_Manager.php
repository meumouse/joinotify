<?php

namespace MeuMouse\Joinotify\Otp_Login;

use MeuMouse\Joinotify\Core\Logger;
use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Facade for OTP code delivery.
 *
 * Picks the active channel from settings, validates it can reach the recipient,
 * delegates the actual transport, and logs failures. Every part of the login
 * flow that needs to deliver a code goes through this single entry point, which
 * keeps the rest of the module agnostic to whether the code travels over
 * WhatsApp, e-mail or Telegram.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Channel_Manager {

    /**
     * Get the active channel id from settings.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_active_channel_id() {
        $id = Settings::get( 'otp_login_channel', 'whatsapp' );

        return ( is_string( $id ) && '' !== trim( $id ) ) ? trim( $id ) : 'whatsapp';
    }


    /**
     * Resolve the active channel instance.
     *
     * @since 2.0.0
     * @return Channel_Interface|null
     */
    public static function get_active_channel() {
        return Channel_Registry::get_channel( self::get_active_channel_id() );
    }


    /**
     * Whether OTP delivery is available (active channel configured).
     *
     * @since 2.0.0
     * @return bool
     */
    public static function is_available() {
        $channel = self::get_active_channel();

        return $channel instanceof Channel_Interface && $channel->is_configured();
    }


    /**
     * Deliver an OTP message through the active channel.
     *
     * @since 2.0.0
     * @param Otp_Message $message | OTP message to deliver.
     * @return bool|WP_Error True on success, WP_Error otherwise.
     */
    public static function send( Otp_Message $message ) {
        $channel = self::get_active_channel();

        if ( ! $channel instanceof Channel_Interface ) {
            $error = new WP_Error(
                'joinotify_otp_no_channel',
                __( 'No OTP delivery channel is available.', 'joinotify' )
            );

            Logger::register_log( 'Joinotify OTP Login: ' . $error->get_error_message(), 'ERROR' );

            return $error;
        }

        if ( ! $channel->is_configured() ) {
            $error = new WP_Error(
                'joinotify_otp_channel_unconfigured',
                __( 'The selected OTP delivery channel is not configured.', 'joinotify' )
            );

            Logger::register_log( 'Joinotify OTP Login: channel "' . $channel->get_id() . '" is not configured.', 'ERROR' );

            return $error;
        }

        if ( ! $channel->supports( $message ) ) {
            $error = new WP_Error(
                'joinotify_otp_channel_unsupported',
                __( 'The selected channel cannot reach this recipient.', 'joinotify' )
            );

            Logger::register_log( 'Joinotify OTP Login: channel "' . $channel->get_id() . '" cannot reach the recipient.', 'WARNING' );

            return $error;
        }

        /**
         * Filter the OTP message right before delivery.
         *
         * @since 2.0.0
         * @param Otp_Message $message
         * @param Channel_Interface $channel
         */
        $message = apply_filters( 'Joinotify/Otp_Login/Message_Before_Send', $message, $channel );

        $result = $channel->send( $message );

        if ( is_wp_error( $result ) ) {
            Logger::register_log( 'Joinotify OTP Login delivery failed: ' . $result->get_error_message(), 'ERROR' );

            return $result;
        }

        if ( true !== $result ) {
            $error = new WP_Error(
                'joinotify_otp_delivery_failed',
                __( 'We could not deliver the verification code right now. Please try again.', 'joinotify' )
            );

            Logger::register_log( 'Joinotify OTP Login: channel "' . $channel->get_id() . '" returned a non-true result.', 'ERROR' );

            return $error;
        }

        /**
         * Fires after an OTP code has been delivered successfully.
         *
         * @since 2.0.0
         * @param Otp_Message $message
         * @param Channel_Interface $channel
         */
        do_action( 'Joinotify/Otp_Login/Code_Sent', $message, $channel );

        return true;
    }
}
