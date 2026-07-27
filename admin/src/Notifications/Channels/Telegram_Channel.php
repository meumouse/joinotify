<?php

namespace MeuMouse\Joinotify\Notifications\Channels;

use MeuMouse\Joinotify\Notifications\Channel_Interface;
use MeuMouse\Joinotify\Notifications\Notification_Message;
use MeuMouse\Joinotify\Notifications\Channel_Result;
use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Deliver notifications over Telegram through the Bot API.
 *
 * The bot token lives in the global integration settings; the destination chat
 * id travels with each message (Notification_Message::receiver), so a single bot
 * can notify any number of chats/groups/channels. Registered on the
 * `Joinotify/Notifications/Channels` filter by Integrations\Telegram.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Notifications\Channels
 * @author MeuMouse.com
 */
class Telegram_Channel implements Channel_Interface {

    /**
     * Channel identifier.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_id() {
        return 'telegram';
    }


    /**
     * Channel label.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_label() {
        return __( 'Telegram', 'joinotify' );
    }


    /**
     * Message types this channel can deliver.
     *
     * @since 2.1.0
     * @return array<int,string>
     */
    public function get_capabilities() {
        return array( 'text' );
    }


    /**
     * Whether the channel has a bot token configured.
     *
     * @since 2.1.0
     * @return bool
     */
    public function is_configured() {
        return '' !== trim( (string) Admin::get_setting('telegram_bot_token') );
    }


    /**
     * Whether the message has a recipient (chat id) and a supported type.
     *
     * Falls back to the default chat id from settings when the message carries
     * no receiver, so a node can leave the field blank and still deliver.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return bool
     */
    public function supports( Notification_Message $message ) {
        if ( '' === trim( (string) $this->resolve_chat_id( $message ) ) ) {
            return false;
        }

        return in_array( $message->type, $this->get_capabilities(), true );
    }


    /**
     * Deliver the message through the Telegram Bot API.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return Channel_Result
     */
    public function send( Notification_Message $message ) {
        $token = trim( (string) Admin::get_setting('telegram_bot_token') );
        $chat_id = $this->resolve_chat_id( $message );

        $response = wp_remote_post( 'https://api.telegram.org/bot' . $token . '/sendMessage', array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'chat_id' => $chat_id,
                'text' => $message->content,
                'parse_mode' => 'HTML',
            ) ),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            return Channel_Result::failure( $this->get_id(), $response->get_error_message(), true );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $ok = is_array( $body ) && ! empty( $body['ok'] );

        if ( $status >= 200 && $status < 300 && $ok ) {
            return Channel_Result::success( $this->get_id(), $status );
        }

        $error = is_array( $body ) && isset( $body['description'] ) ? (string) $body['description'] : 'telegram_api_error';

        // 429 (rate limit) and 5xx are transient; 4xx (bad token/chat) are not.
        $retryable = ( 429 === $status || $status >= 500 );

        return Channel_Result::failure( $this->get_id(), $error, $retryable, $status );
    }


    /**
     * Resolve the destination chat id: message receiver first, settings fallback.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return string
     */
    protected function resolve_chat_id( Notification_Message $message ) {
        $chat_id = trim( (string) $message->receiver );

        if ( '' === $chat_id ) {
            $chat_id = trim( (string) Admin::get_setting('telegram_default_chat_id') );
        }

        return $chat_id;
    }
}
