<?php

namespace MeuMouse\Joinotify\Notifications\Channels;

use MeuMouse\Joinotify\Notifications\Channel_Interface;
use MeuMouse\Joinotify\Notifications\Notification_Message;
use MeuMouse\Joinotify\Notifications\Channel_Result;
use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Attachments;
use MeuMouse\Joinotify\Core\Logger;

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
        // attachments are a modifier of a text e-mail rather than a message type of
        // their own, so they do not appear here and never gate supports()
        return array( 'text' );
    }


    /**
     * Total size of the files this channel embeds in a single e-mail.
     *
     * Resend caps an e-mail at 40MB including the base64 overhead, so the raw budget is
     * deliberately lower. Files beyond it are replaced by their download link.
     *
     * @since 2.1.0
     * @return int Size in bytes
     */
    protected function get_attachments_size_limit() {
        /**
         * Filter the total attachment size embedded in a Resend e-mail
         *
         * @since 2.1.0
         * @param int $limit | Size in bytes
         */
        return (int) apply_filters( 'Joinotify/Notifications/Resend/Attachments_Size_Limit', 25 * MB_IN_BYTES );
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

        $prepared = $this->prepare_attachments( $message );

        $body = array(
            'from' => $from,
            'to' => array( $message->receiver ),
            'subject' => $subject,
            'html' => $message->content . $this->build_skipped_links( $prepared['skipped'] ),
        );

        if ( ! empty( $prepared['attachments'] ) ) {
            $body['attachments'] = $prepared['attachments'];
        }

        $response = wp_remote_post( 'https://api.resend.com/emails', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
            'timeout' => 60,
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


    /**
     * Split the resolved attachments into what the e-mail can carry and what it cannot.
     *
     * Remote files are handed to Resend as a URL so it fetches them itself, which keeps
     * them off this server's memory and outside the size budget. Local files are embedded
     * as base64 and counted, because their bytes travel in the request.
     *
     * @since 2.1.0
     * @param Notification_Message $message | Message to deliver.
     * @return array{attachments:array<int,array<string,string>>,skipped:array<int,array<string,mixed>>}
     */
    protected function prepare_attachments( Notification_Message $message ) {
        $attachments = array();
        $skipped = array();

        if ( empty( $message->attachments ) || ! is_array( $message->attachments ) ) {
            return array( 'attachments' => $attachments, 'skipped' => $skipped );
        }

        $limit = $this->get_attachments_size_limit();
        $used = 0;

        foreach ( $message->attachments as $file ) {
            if ( ! is_array( $file ) ) {
                continue;
            }

            $name = (string) ( $file['name'] ?? '' );

            if ( ! empty( $file['remote'] ) && ! empty( $file['url'] ) ) {
                $attachments[] = array(
                    'filename' => $name,
                    'path' => (string) $file['url'],
                );

                continue;
            }

            $size = absint( $file['size'] ?? 0 );

            // stop embedding once the budget is spent, instead of letting the API reject
            // the whole e-mail and losing the message body along with the files
            if ( $size > 0 && ( $used + $size ) > $limit ) {
                $skipped[] = $file;

                Logger::register_log( sprintf( 'Joinotify: attachment %s was not embedded because the e-mail size limit was reached.', $name ), 'WARNING' );

                continue;
            }

            $contents = Attachments::get_contents( $file );

            if ( false === $contents ) {
                $skipped[] = $file;

                continue;
            }

            $used += strlen( $contents );

            $attachments[] = array(
                'filename' => $name,
                'content' => base64_encode( $contents ),
            );
        }

        return array( 'attachments' => $attachments, 'skipped' => $skipped );
    }


    /**
     * Build the download links of the files that could not be embedded.
     *
     * Only files carrying a link produce output, so a skipped file without one is left to
     * the log rather than being announced to the customer as an empty link.
     *
     * @since 2.1.0
     * @param array<int,array<string,mixed>> $skipped | Files left out of the e-mail.
     * @return string HTML appended to the message body, empty when there is nothing to add.
     */
    protected function build_skipped_links( $skipped ) {
        $links = array();

        foreach ( $skipped as $file ) {
            $link = (string) ( $file['link'] ?? '' );

            if ( '' === $link ) {
                continue;
            }

            $links[] = sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url( $link ),
                esc_html( (string) ( $file['name'] ?? $link ) )
            );
        }

        if ( empty( $links ) ) {
            return '';
        }

        return sprintf(
            '<p>%s</p><ul>%s</ul>',
            esc_html__( 'The files below were too large to attach, so you can download them here:', 'joinotify' ),
            implode( '', $links )
        );
    }
}
