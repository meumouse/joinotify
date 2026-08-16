<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * WhatsApp transport switch.
 *
 * Single chokepoint that routes every outbound WhatsApp message to either the
 * legacy Evolution relay (Api\Controller) or the official Cloud API
 * (Api\Cloud_Client), based on the `whatsapp_transport` setting. This is the
 * "chave de virada" that lets a site run on Evolution until it is shut down and
 * then flip to the Cloud API without touching call sites.
 *
 * The send methods mirror the Controller/Cloud_Client signatures and return
 * contract (int response code, or the normalized details array when
 * $return_details is true), so existing callers migrate by swapping the class.
 *
 * @since 1.4.8
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Transport {

    /**
     * Resolve the active transport: 'evolution' or 'cloud'.
     *
     * @since 1.4.8
     * @return string
     */
    public static function active_transport() {
        $mode = Admin::get_setting('whatsapp_transport');
        $mode = is_string( $mode ) ? $mode : 'auto';

        if ( 'cloud' === $mode ) {
            $transport = 'cloud';
        } elseif ( 'evolution' === $mode ) {
            $transport = 'evolution';
        } else {
            // auto: prefer the Cloud API whenever a token is available.
            $transport = Helpers::cloud_api_ready() ? 'cloud' : 'evolution';
        }

        /**
         * Filter the resolved WhatsApp transport ('evolution' or 'cloud').
         *
         * @since 1.4.8
         * @param string $transport
         * @param string $mode | Raw setting value.
         */
        $transport = apply_filters( 'Joinotify/Transport/Active', $transport, $mode );

        return in_array( $transport, array( 'evolution', 'cloud' ), true ) ? $transport : 'evolution';
    }


    /**
     * Whether the Cloud API transport is active.
     *
     * @since 1.4.8
     * @return bool
     */
    public static function is_cloud() {
        return 'cloud' === self::active_transport();
    }


    /**
     * Notification channel id backing the active transport.
     *
     * @since 1.4.8
     * @return string
     */
    public static function active_channel_id() {
        return self::is_cloud() ? 'whatsapp_cloud' : 'whatsapp';
    }


    /**
     * Send a text message through the active transport.
     *
     * @since 1.4.8
     * @return int|array
     */
    public static function send_message_text( $sender, $receiver, $message, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        if ( self::is_cloud() ) {
            return Cloud_Client::send_message_text( $sender, $receiver, $message, $timestamp_delay, $queue_on_failure, $return_details );
        }

        return Controller::send_message_text( $sender, $receiver, $message, $timestamp_delay, $queue_on_failure, $return_details );
    }


    /**
     * Send a media message through the active transport.
     *
     * @since 1.4.8
     * @return int|array
     */
    public static function send_message_media( $sender, $receiver, $media_type, $media, $caption = '', $timestamp_delay = 0, $queue_on_failure = true, $return_details = false, $file_name = '' ) {
        if ( self::is_cloud() ) {
            return Cloud_Client::send_message_media( $sender, $receiver, $media_type, $media, $caption, $timestamp_delay, $queue_on_failure, $return_details, $file_name );
        }

        return Controller::send_message_media( $sender, $receiver, $media_type, $media, $caption, $timestamp_delay, $queue_on_failure, $return_details, $file_name );
    }


    /**
     * Send an audio message through the active transport.
     *
     * @since 1.4.8
     * @return int|array
     */
    public static function send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        if ( self::is_cloud() ) {
            return Cloud_Client::send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay, $queue_on_failure, $return_details );
        }

        return Controller::send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay, $queue_on_failure, $return_details );
    }


    /**
     * Send an approved template message.
     *
     * Templates are a Cloud API concept with no Evolution equivalent, so this
     * always routes to the Cloud client. When the Cloud API is not configured
     * the client records an actionable failure.
     *
     * @since 1.4.8
     * @return int|array
     */
    public static function send_message_template( $sender, $receiver, $template_name, $language = 'pt_BR', $components = array(), $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        return Cloud_Client::send_message_template( $sender, $receiver, $template_name, $language, $components, $timestamp_delay, $queue_on_failure, $return_details );
    }


    /**
     * Send a raw Meta message payload (interactive, location, contact, reaction,
     * sticker).
     *
     * Like templates, these have no Evolution equivalent, so this always routes
     * to the Cloud client.
     *
     * @since 2.3.0
     * @return int|array
     */
    public static function send_raw_message( $sender, $receiver, $type, $content, $preview = '', $queue_on_failure = true, $return_details = false ) {
        return Cloud_Client::send_raw_message( $sender, $receiver, $type, $content, $preview, $queue_on_failure, $return_details );
    }


    /**
     * Send one of the richer message kinds by name.
     *
     * Single entry point for the builder actions that cover buttons, lists, link
     * buttons, locations, contact cards, stickers and reactions, so callers name
     * what they want instead of assembling Meta payloads themselves.
     *
     * @since 2.3.0
     * @param string $kind | buttons|list|cta|location|contact|sticker|reaction.
     * @param string $sender | Origin phone number.
     * @param string $receiver | Recipient phone number.
     * @param array  $args | Kind-specific arguments.
     * @return int|array
     */
    public static function send_rich_message( $kind, $sender, $receiver, $args = array() ) {
        switch ( $kind ) {
            case 'buttons':
                return Cloud_Client::send_interactive_buttons(
                    $sender,
                    $receiver,
                    $args['body'] ?? '',
                    $args['buttons'] ?? array(),
                    $args['header'] ?? '',
                    $args['footer'] ?? ''
                );

            case 'list':
                return Cloud_Client::send_interactive_list(
                    $sender,
                    $receiver,
                    $args['body'] ?? '',
                    $args['button_label'] ?? '',
                    $args['sections'] ?? array(),
                    $args['header'] ?? '',
                    $args['footer'] ?? ''
                );

            case 'cta':
                return Cloud_Client::send_interactive_cta(
                    $sender,
                    $receiver,
                    $args['body'] ?? '',
                    $args['display_text'] ?? '',
                    $args['url'] ?? '',
                    $args['header'] ?? '',
                    $args['footer'] ?? ''
                );

            case 'location':
                return Cloud_Client::send_location(
                    $sender,
                    $receiver,
                    $args['latitude'] ?? '',
                    $args['longitude'] ?? '',
                    $args['name'] ?? '',
                    $args['address'] ?? ''
                );

            case 'contact':
                return Cloud_Client::send_contact( $sender, $receiver, $args['name'] ?? '', $args['phone'] ?? '' );

            case 'sticker':
                return Cloud_Client::send_sticker( $sender, $receiver, $args['url'] ?? '' );

            case 'reaction':
                return Cloud_Client::send_reaction( $sender, $receiver, $args['message_id'] ?? '', $args['emoji'] ?? '' );
        }

        return 0;
    }
}
