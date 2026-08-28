<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * WhatsApp transport switch.
 *
 * Single chokepoint for every outbound WhatsApp message. Since 2.3.2 there is
 * one transport, the official Cloud API (Api\Cloud_Client): the Evolution relay
 * it used to fall back to was retired along with the servers behind it.
 *
 * The class stays because call sites talk to it rather than to the client, which
 * keeps a future transport a one-file change.
 *
 * @since 1.4.8
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Transport {

    /**
     * Resolve the active transport.
     *
     * Kept for the extensions that read it. There is only one transport now, so
     * it always answers 'cloud'.
     *
     * @since 1.4.8
     * @version 2.3.2
     * @return string
     */
    public static function active_transport() {
        return 'cloud';
    }


    /**
     * Whether the Cloud API transport is active.
     *
     * @since 1.4.8
     * @version 2.3.2
     * @return bool
     */
    public static function is_cloud() {
        return true;
    }


    /**
     * Whether the site can actually deliver a message.
     *
     * False until the Joinotify account is connected: without an API key there
     * is nothing to send through.
     *
     * @since 2.3.2
     * @return bool
     */
    public static function is_ready() {
        return Helpers::cloud_api_ready();
    }


    /**
     * Notification channel id backing the transport.
     *
     * @since 1.4.8
     * @version 2.3.2
     * @return string
     */
    public static function active_channel_id() {
        return 'whatsapp_cloud';
    }


    /**
     * Send a text message through the active transport.
     *
     * @since 1.4.8
     * @return int|array
     */
    public static function send_message_text( $sender, $receiver, $message, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        return Cloud_Client::send_message_text( $sender, $receiver, $message, $timestamp_delay, $queue_on_failure, $return_details );
    }


    /**
     * Send a media message through the active transport.
     *
     * @since 1.4.8
     * @return int|array
     */
    public static function send_message_media( $sender, $receiver, $media_type, $media, $caption = '', $timestamp_delay = 0, $queue_on_failure = true, $return_details = false, $file_name = '' ) {
        return Cloud_Client::send_message_media( $sender, $receiver, $media_type, $media, $caption, $timestamp_delay, $queue_on_failure, $return_details, $file_name );
    }


    /**
     * Send an audio message through the active transport.
     *
     * @since 1.4.8
     * @return int|array
     */
    public static function send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        return Cloud_Client::send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay, $queue_on_failure, $return_details );
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
