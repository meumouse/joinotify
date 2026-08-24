<?php

namespace MeuMouse\Joinotify\Notifications\Channels;

use MeuMouse\Joinotify\Notifications\Channel_Interface;
use MeuMouse\Joinotify\Notifications\Notification_Message;
use MeuMouse\Joinotify\Notifications\Channel_Result;
use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Deliver notifications over the official WhatsApp Cloud API (via Joinotify).
 *
 * Implements Channel_Interface contract, but
 * the transport is Api\Cloud_Client (bearer token, phone_number_id) instead of
 * the Evolution/slots relay. It adds the `template` message type, which is the
 * only way to reach a recipient outside the 24h session window.
 *
 * Registered under the id `whatsapp_cloud`; Api\Transport::active_channel_id()
 * selects it when the transport switch is set to the Cloud API.
 *
 * @since 1.4.8
 * @package MeuMouse\Joinotify\Notifications\Channels
 * @author MeuMouse.com
 */
class Whatsapp_Cloud_Channel implements Channel_Interface {

    /**
     * Channel identifier.
     *
     * @since 1.4.8
     * @return string
     */
    public function get_id() {
        return 'whatsapp_cloud';
    }


    /**
     * Channel label.
     *
     * @since 1.4.8
     * @return string
     */
    public function get_label() {
        return __( 'WhatsApp (Cloud API)', 'joinotify' );
    }


    /**
     * Message types this channel can deliver.
     *
     * @since 1.4.8
     * @return array<int,string>
     */
    public function get_capabilities() {
        return array( 'text', 'media', 'audio', 'template' );
    }


    /**
     * Whether the channel can operate: a Cloud API token and a sender exist.
     *
     * @since 1.4.8
     * @return bool
     */
    public function is_configured() {
        if ( ! Helpers::cloud_api_ready() ) {
            return false;
        }

        return '' !== (string) ( function_exists( 'joinotify_get_first_sender' ) ? joinotify_get_first_sender() : '' );
    }


    /**
     * Whether the message has a recipient and a supported type.
     *
     * @since 1.4.8
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
     * Deliver the message, delegating to Api\Cloud_Client.
     *
     * @since 1.4.8
     * @param Notification_Message $message | Message to deliver.
     * @return Channel_Result
     */
    public function send( Notification_Message $message ) {
        if ( 'template' === $message->type ) {
            $details = Cloud_Client::send_message_template(
                $message->sender,
                $message->receiver,
                (string) $message->get_meta( 'template_name', '' ),
                (string) $message->get_meta( 'language', 'pt_BR' ),
                (array) $message->get_meta( 'components', array() ),
                $message->delay,
                true,
                true
            );
        } elseif ( 'media' === $message->type || 'audio' === $message->type ) {
            $media_type = ( 'audio' === $message->type && '' === $message->media_type ) ? 'audio' : $message->media_type;

            $details = Cloud_Client::send_message_media(
                $message->sender,
                $message->receiver,
                $media_type,
                $message->media_url,
                $message->caption,
                $message->delay,
                true,
                true,
                (string) $message->get_meta( 'file_name', '' )
            );
        } else {
            $details = Cloud_Client::send_message_text(
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
