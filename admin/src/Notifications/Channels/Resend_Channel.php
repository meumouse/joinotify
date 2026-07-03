<?php

namespace MeuMouse\Joinotify\Notifications\Channels;

use MeuMouse\Joinotify\Notifications\Channel_Interface;
use MeuMouse\Joinotify\Notifications\Notification_Message;
use MeuMouse\Joinotify\Notifications\Channel_Result;
use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Deliver notifications by e-mail through the Resend API.
 *
 * The API key and verified sender (from address/name) live in the global
 * integration settings; the recipient e-mail and subject travel with each
 * message (receiver + meta['subject']). Registered on the
 * `Joinotify/Notifications/Channels` filter by Integrations\Resend.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Notifications\Channels
 * @author MeuMouse.com
 */
class Resend_Channel implements Channel_Interface {

    /**
     * Channel identifier.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_id() {
        return 'resend';
    }


    /**
     * Channel label.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_label() {
        return __( 'Resend', 'joinotify' );
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
     * Whether the channel has an API key and a sender address configured.
     *
     * @since 2.1.0
     * @return bool
     */
    public function is_configured() {
        return '' !== trim( (string) Admin::get_setting('resend_api_key') )
            && '' !== trim( (string) Admin::get_setting('resend_from_email') );
    }


    /**
     * Whether the message has a valid recipient e-mail and a supported type.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return bool
     */
    public function supports( Notification_Message $message ) {
        if ( ! is_email( trim( (string) $message->receiver ) ) ) {
            return false;
        }

        return in_array( $message->type, $this->get_capabilities(), true );
    }


    /**
     * Deliver the message through the Resend API.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return Channel_Result
     */
    public function send( Notification_Message $message ) {
        $api_key = trim( (string) Admin::get_setting('resend_api_key') );
        $from_email = trim( (string) Admin::get_setting('resend_from_email') );
        $from_name = trim( (string) Admin::get_setting('resend_from_name') );

        // Compose the RFC 5322 "from" header: "Name <email>" when a name is set.
        $from = '' !== $from_name ? sprintf( '%s <%s>', $from_name, $from_email ) : $from_email;

        $subject = (string) $message->get_meta( 'subject', __( 'Notification', 'joinotify' ) );

        $response = wp_remote_post( 'https://api.resend.com/emails', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'from' => $from,
                'to' => array( $message->receiver ),
                'subject' => $subject,
                'html' => $message->content,
            ) ),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            return Channel_Result::failure( $this->get_id(), $response->get_error_message(), true );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );

        if ( $status >= 200 && $status < 300 ) {
            return Channel_Result::success( $this->get_id(), $status );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $error = is_array( $body ) && isset( $body['message'] ) ? (string) $body['message'] : 'resend_api_error';

        // 429 (rate limit) and 5xx are transient; 4xx (bad key/payload) are not.
        $retryable = ( 429 === $status || $status >= 500 );

        return Channel_Result::failure( $this->get_id(), $error, $retryable, $status );
    }
}
