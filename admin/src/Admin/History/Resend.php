<?php

namespace MeuMouse\Joinotify\Admin\History;

use MeuMouse\Joinotify\Api\Transport;
use MeuMouse\Joinotify\Core\Message_History;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Send a failed message again from the history screen.
 *
 * The history row is the only copy of the message left once a send gave up,
 * so the resend is rebuilt from it: the text, the media link, the template
 * with the components that went out, or the Meta object of an interactive,
 * location, contact, reaction or sticker message. A row that does not hold
 * enough to send the same message again is not offered a resend:
 *
 * - a local attachment, whose bytes the history never keeps;
 * - a login code, which has expired by then and whose values are masked;
 * - a template whose values were redacted from the history;
 * - a row already resent, which points at the row its resend recorded.
 *
 * Each resend is a single attempt, outside the retry queue, so the screen can
 * say right away how it went. It records a row of its own, with the source
 * and workflow of the original, and a link back to it.
 *
 * @since 2.7.0
 * @package MeuMouse\Joinotify\Admin\History
 * @author MeuMouse.com
 */
class Resend {

    /**
     * Statuses a resend is offered for: a send that failed, and a queued one
     * whose automatic retry was called off.
     *
     * @since 2.7.0
     * @var string[]
     */
    const STATUSES = array( 'failed', 'cancelled' );

    /**
     * Most messages a single resend request sends. They go out one after the
     * other inside the request, which has to finish before PHP times out.
     *
     * @since 2.7.0
     * @var int
     */
    const LIMIT = 50;


    /**
     * Rebuild the send of a history row.
     *
     * @since 2.7.0
     * @param array<string,mixed> $row Raw DB row.
     * @return array<string,mixed>|null The send to repeat, or null when the row cannot be resent.
     */
    public static function build_request( $row ) {
        if ( ! is_array( $row ) ) {
            return null;
        }

        $status = (string) ( $row['status'] ?? '' );
        $source = (string) ( $row['source'] ?? '' );
        $sender = (string) ( $row['sender'] ?? '' );
        $receiver = (string) ( $row['receiver'] ?? '' );
        $meta = Message_History::decode_meta( $row['meta'] ?? '' );

        if ( ! in_array( $status, self::STATUSES, true ) || 'otp' === $source || '' === $sender || '' === $receiver || ! empty( $meta['resent_as'] ) ) {
            return null;
        }

        $request = array(
            'sender' => $sender,
            'receiver' => $receiver,
        );

        $content = (string) ( $row['content'] ?? '' );
        $media_url = (string) ( $row['media_url'] ?? '' );

        switch ( (string) ( $row['message_type'] ?? '' ) ) {
            case 'text':
                // Interactive, location, contact, reaction and sticker messages
                // are recorded as text, with their Meta object kept aside.
                if ( isset( $meta['raw'] ) ) {
                    $raw_type = sanitize_key( (string) ( $meta['raw']['type'] ?? '' ) );
                    $raw_content = json_decode( (string) ( $meta['raw']['content'] ?? '' ), true );

                    if ( '' === $raw_type || ! is_array( $raw_content ) || empty( $raw_content ) ) {
                        return null;
                    }

                    return array_merge( $request, array(
                        'type' => 'raw',
                        'raw_type' => $raw_type,
                        'raw_content' => $raw_content,
                        'preview' => $content,
                    ) );
                }

                if ( '' === trim( $content ) ) {
                    return null;
                }

                return array_merge( $request, array(
                    'type' => 'text',
                    'message' => $content,
                ) );

            case 'media':
                $media_type = (string) ( $row['media_type'] ?? '' );

                if ( ! in_array( $media_type, array( 'image', 'video', 'document' ), true ) || ! self::is_link( $media_url ) ) {
                    return null;
                }

                return array_merge( $request, array(
                    'type' => 'media',
                    'media_type' => $media_type,
                    'media' => $media_url,
                    'caption' => $content,
                ) );

            case 'audio':
                if ( ! self::is_link( $media_url ) ) {
                    return null;
                }

                return array_merge( $request, array(
                    'type' => 'audio',
                    'audio' => $media_url,
                ) );

            case 'template':
                $template = is_array( $meta['template'] ?? null ) ? $meta['template'] : array();
                $components = $template['components'] ?? null;

                if ( empty( $template['name'] ) || ! empty( $template['masked'] ) || ! is_array( $components ) ) {
                    return null;
                }

                // Values recorded without the payload that carried them were
                // taken out by the dispatch-log filter: sending the template
                // without them would not be the same message.
                if ( empty( $components ) && ! empty( $template['parameters'] ) ) {
                    return null;
                }

                return array_merge( $request, array(
                    'type' => 'template',
                    'template_name' => (string) $template['name'],
                    'language' => (string) ( $template['language'] ?? '' ),
                    'components' => $components,
                ) );
        }

        return null;
    }


    /**
     * Whether a history row can be sent again.
     *
     * @since 2.7.0
     * @param array<string,mixed> $row Raw DB row.
     * @return bool
     */
    public static function can_resend( $row ) {
        return null !== self::build_request( $row );
    }


    /**
     * How many messages a single resend request may send.
     *
     * @since 2.7.0
     * @return int
     */
    public static function get_limit() {
        /**
         * Filter how many history messages a single resend request sends.
         *
         * @since 2.7.0
         * @param int $limit Maximum number of messages.
         */
        return max( 1, (int) apply_filters( 'Joinotify/Admin/History/Resend_Limit', self::LIMIT ) );
    }


    /**
     * Send the given history rows again.
     *
     * Rows that cannot be resent are skipped. Nothing is sent when the rows
     * that can outnumber the limit, so a selection never goes out halfway.
     *
     * @since 2.7.0
     * @param int[] $ids History row IDs.
     * @return array{resent:int,failed:int,skipped:int,over_limit:bool}
     */
    public static function resend( $ids ) {
        $rows = Message_History::get_items_by_ids( $ids );
        $requests = array();

        // Oldest first, so the recipient gets them in the order they were meant.
        foreach ( array_reverse( $rows ) as $row ) {
            $request = self::build_request( $row );

            if ( null !== $request ) {
                $requests[ (int) $row['id'] ] = array( $row, $request );
            }
        }

        $result = array(
            'resent' => 0,
            'failed' => 0,
            'skipped' => max( 0, count( array_filter( array_map( 'absint', (array) $ids ) ) ) - count( $requests ) ),
            'over_limit' => count( $requests ) > self::get_limit(),
        );

        if ( $result['over_limit'] ) {
            return $result;
        }

        foreach ( $requests as $id => list( $row, $request ) ) {
            Message_History::set_context( array(
                'source' => (string) ( $row['source'] ?? 'api' ),
                'workflow_id' => (int) ( $row['workflow_id'] ?? 0 ),
                'resent_from' => $id,
            ) );

            $details = self::dispatch( $request );

            Message_History::clear_context();

            if ( ! empty( $details['success'] ) ) {
                $result['resent']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }


    /**
     * Perform one rebuilt send, as a single attempt.
     *
     * @since 2.7.0
     * @param array<string,mixed> $request Send built by build_request().
     * @return array Normalized response details.
     */
    protected static function dispatch( $request ) {
        $sender = $request['sender'];
        $receiver = $request['receiver'];

        switch ( $request['type'] ) {
            case 'text':
                $result = Transport::send_message_text( $sender, $receiver, $request['message'], 0, false, true );
                break;

            case 'media':
                $result = Transport::send_message_media( $sender, $receiver, $request['media_type'], $request['media'], $request['caption'], 0, false, true );
                break;

            case 'audio':
                $result = Transport::send_whatsapp_audio( $sender, $receiver, $request['audio'], 0, false, true );
                break;

            case 'template':
                $result = Transport::send_message_template( $sender, $receiver, $request['template_name'], $request['language'], $request['components'], 0, false, true );
                break;

            case 'raw':
                $result = Transport::send_raw_message( $sender, $receiver, $request['raw_type'], $request['raw_content'], $request['preview'], false, true );
                break;

            default:
                $result = array();
        }

        return is_array( $result ) ? $result : array( 'success' => 201 === (int) $result );
    }


    /**
     * Whether a stored media URL is a link WhatsApp could fetch.
     *
     * Rows recorded before 2.7.0 kept a local attachment's base64 in the same
     * column, which the URL sanitizer turned into `http://<bytes>`: a host
     * without a single dot is how that tells itself apart from a real link.
     *
     * @since 2.7.0
     * @param string $url Stored media URL.
     * @return bool
     */
    protected static function is_link( $url ) {
        if ( ! preg_match( '#^https?://#i', $url ) ) {
            return false;
        }

        return str_contains( (string) wp_parse_url( $url, PHP_URL_HOST ), '.' );
    }
}
