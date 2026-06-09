<?php

namespace MeuMouse\Joinotify\Otp_Login\Channels;

use MeuMouse\Joinotify\Otp_Login\Channel_Interface;
use MeuMouse\Joinotify\Otp_Login\Otp_Message;
use MeuMouse\Joinotify\Otp_Login\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Deliver OTP codes over WhatsApp through Joinotify's messaging pipeline.
 *
 * This is the only channel shipped by core. E-mail and Telegram channels can be
 * added later by implementing Channel_Interface and registering them on the
 * `Joinotify/Otp_Login/Channels` filter, with no change to the login flow.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login\Channels
 * @author MeuMouse.com
 */
class Whatsapp_Channel implements Channel_Interface {

    /**
     * Channel identifier.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_id() {
        return 'whatsapp';
    }


    /**
     * Channel label.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_label() {
        return __( 'WhatsApp', 'joinotify' );
    }


    /**
     * Whether a Joinotify sender is available to deliver the code.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_configured() {
        if ( ! function_exists( 'joinotify_send_whatsapp_message_text' ) ) {
            return false;
        }

        return '' !== (string) $this->resolve_sender();
    }


    /**
     * WhatsApp delivery needs a recipient phone number.
     *
     * @since 2.0.0
     * @param Otp_Message $message | OTP message to deliver.
     * @return bool
     */
    public function supports( Otp_Message $message ) {
        return '' !== trim( (string) $message->phone );
    }


    /**
     * Send the OTP message over WhatsApp.
     *
     * @since 2.0.0
     * @param Otp_Message $message | OTP message to deliver.
     * @return bool|\WP_Error
     */
    public function send( Otp_Message $message ) {
        if ( ! function_exists( 'joinotify_send_whatsapp_message_text' ) ) {
            return new \WP_Error( 'joinotify_otp_helpers_missing', __( 'Joinotify messaging helpers are unavailable.', 'joinotify' ) );
        }

        $sender = $this->resolve_sender();

        if ( empty( $sender ) ) {
            return new \WP_Error( 'joinotify_otp_no_sender', __( 'No Joinotify sender is configured to deliver the code.', 'joinotify' ) );
        }

        $receiver = function_exists( 'joinotify_prepare_receiver' )
            ? joinotify_prepare_receiver( preg_replace( '/\s+/', '', (string) $message->phone ) )
            : preg_replace( '/\D+/', '', (string) $message->phone );

        $result = joinotify_send_whatsapp_message_text( $sender, $receiver, $message->body );

        return ( true === $result || 201 === $result || '201' === $result );
    }


    /**
     * Resolve the sender phone, preferring the OTP Login setting.
     *
     * @since 2.0.0
     * @return string
     */
    protected function resolve_sender() {
        $selected = Settings::get_selected_sender();

        if ( ! empty( $selected ) ) {
            $sender = $selected;
        } else {
            $sender = function_exists( 'joinotify_get_first_sender' ) ? joinotify_get_first_sender() : '';
        }

        /**
         * Filter the sender used to deliver OTP login codes over WhatsApp.
         *
         * @since 2.0.0
         * @param string $sender Sender phone number.
         */
        return (string) apply_filters( 'Joinotify/Otp_Login/Sender', $sender );
    }
}
