<?php

namespace MeuMouse\Joinotify\Notifications\Channels;

use MeuMouse\Joinotify\Notifications\Channel_Interface;
use MeuMouse\Joinotify\Notifications\Notification_Message;
use MeuMouse\Joinotify\Notifications\Channel_Result;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Api\License;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Deliver notifications over WhatsApp through Joinotify's Evolution/slots proxy.
 *
 * This is the only channel shipped by core. It is a thin adapter: the actual
 * transport (server discovery, HTTP, retry queue and message history) stays in
 * Api\Controller, which already exposes a normalized details array via its
 * $return_details flag. WhatsApp Official, Telegram, e-mail and SMS channels can
 * be added later by implementing Channel_Interface and registering them on the
 * `Joinotify/Notifications/Channels` filter, with no change to the dispatcher.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Notifications\Channels
 * @author MeuMouse.com
 */
class Whatsapp_Evolution_Channel implements Channel_Interface {

    /**
     * Channel identifier.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_id() {
        return 'whatsapp';
    }


    /**
     * Channel label.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_label() {
        return __( 'WhatsApp', 'joinotify' );
    }


    /**
     * Message types this channel can deliver.
     *
     * @since 2.1.0
     * @return array<int,string>
     */
    public function get_capabilities() {
        return array( 'text', 'media', 'audio' );
    }


    /**
     * Whether the channel can operate: valid license and a registered sender.
     *
     * @since 2.1.0
     * @return bool
     */
    public function is_configured() {
        if ( ! class_exists( License::class ) || ! License::is_valid() ) {
            return false;
        }

        return '' !== (string) ( function_exists( 'joinotify_get_first_sender' ) ? joinotify_get_first_sender() : '' );
    }


    /**
     * Whether the message has a recipient and a supported type.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return bool
     */
    public function supports( Notification_Message $message ) {
        if ( '' === trim( (string) $message->receiver ) ) {
            return false;
        }

        return in_array( $message->type, $this->get_capabilities(), true );
    }


    /**
     * Deliver the message, delegating to Api\Controller.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return Channel_Result
     */
    public function send( Notification_Message $message ) {
        if ( 'media' === $message->type || 'audio' === $message->type ) {
            // audio is routed internally by Controller when media_type === 'audio'
            $media_type = ( 'audio' === $message->type && '' === $message->media_type ) ? 'audio' : $message->media_type;

            $details = Controller::send_message_media(
                $message->sender,
                $message->receiver,
                $media_type,
                $message->media_url,
                $message->caption,
                $message->delay,
                true,
                true
            );
        } else {
            $details = Controller::send_message_text(
                $message->sender,
                $message->receiver,
                $message->content,
                $message->delay,
                true,
                true
            );
        }

        return Channel_Result::from_controller_details( $details, $this->get_id() );
    }
}
