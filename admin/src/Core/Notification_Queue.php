<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Api\Controller;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Queue and retry failed WhatsApp notifications.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Notification_Queue {

    /**
     * Option key used to persist queue items.
     *
     * @since 1.4.7
     * @var string
     */
    const OPTION_KEY = 'joinotify_notification_retry_queue';

    /**
     * Cron hook name for queue processing.
     *
     * @since 1.4.7
     * @var string
     */
    const CRON_HOOK = 'joinotify_process_notification_queue_event';

    /**
     * Custom cron interval key.
     *
     * @since 1.4.7
     * @var string
     */
    const CRON_INTERVAL = 'joinotify_every_five_minutes';

    /**
     * Lock transient key used for request-based processing fallback.
     *
     * @since 1.4.7
     * @var string
     */
    const PROCESS_LOCK_KEY = 'joinotify_notification_queue_lock';

    /**
     * Construct function.
     *
     * @since 1.4.7
     * @return void
     */
    public function __construct() {
        add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_interval' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
        add_action( 'init', array( __CLASS__, 'maybe_process_due_items' ), 99 );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_INTERVAL, self::CRON_HOOK );
        }
    }


    /**
     * Register queue processing cron interval.
     *
     * @since 1.4.7
     * @param array $schedules Existing schedules.
     * @return array
     */
    public static function register_cron_interval( $schedules ) {
        $schedules[ self::CRON_INTERVAL ] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __( 'A cada 5 minutos', 'joinotify' ),
        );

        return $schedules;
    }


    /**
     * Enqueue a failed notification for retry.
     *
     * @since 1.4.7
     * @param string $type Supported values: text, media, audio.
     * @param array $payload Notification payload.
     * @param string $reason Failure reason.
     * @return string|false Queue item ID on success, false on failure.
     */
    public static function enqueue( $type, $payload, $reason = '' ) {
        $type = sanitize_key( $type );
        $payload = self::sanitize_payload( $type, $payload );

        if ( empty( $payload ) ) {
            return false;
        }

        $queue = self::get_queue();
        $max_attempts = (int) apply_filters( 'Joinotify/Notification_Queue/Max_Attempts', 120, $type, $payload );
        $next_attempt_at = time() + self::get_next_delay( 0, $reason );
        $id = uniqid( 'joinotify_queue_', true );

        $queue[] = array(
            'id' => $id,
            'type' => $type,
            'payload' => $payload,
            'attempts' => 0,
            'max_attempts' => max( 1, $max_attempts ),
            'created_at' => time(),
            'updated_at' => time(),
            'next_attempt_at' => $next_attempt_at,
            'last_error' => (string) $reason,
        );

        self::save_queue( $queue );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK );
        }

        return $id;
    }


    /**
     * Process all queue items that are due for retry.
     *
     * @since 1.4.7
     * @return void
     */
    public static function process_queue() {
        $queue = self::get_queue();

        if ( empty( $queue ) ) {
            return;
        }

        $now = time();
        $updated_queue = array();

        foreach ( $queue as $item ) {
            if ( ! is_array( $item ) || empty( $item['type'] ) || empty( $item['payload'] ) ) {
                continue;
            }

            $next_attempt_at = (int) ( $item['next_attempt_at'] ?? 0 );

            if ( $next_attempt_at > $now ) {
                $updated_queue[] = $item;
                continue;
            }

            $result = self::dispatch_item( $item );

            if ( ! empty( $result['success'] ) ) {
                continue;
            }

            $item['attempts'] = (int) ( $item['attempts'] ?? 0 ) + 1;
            $item['updated_at'] = $now;
            $item['last_error'] = (string) ( $result['error'] ?? '' );

            $max_attempts = (int) ( $item['max_attempts'] ?? 1 );

            if ( $item['attempts'] >= max( 1, $max_attempts ) ) {
                continue;
            }

            $item['next_attempt_at'] = $now + self::get_next_delay( $item['attempts'], $item['last_error'] );
            $updated_queue[] = $item;
        }

        self::save_queue( $updated_queue );
    }


    /**
     * Process queue on normal requests when WP-Cron is delayed.
     *
     * @since 1.4.7
     * @return void
     */
    public static function maybe_process_due_items() {
        if ( wp_doing_cron() || wp_doing_ajax() || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
            return;
        }

        $queue = self::get_queue();

        if ( empty( $queue ) ) {
            return;
        }

        $now = time();
        $has_due_items = false;

        foreach ( $queue as $item ) {
            if ( (int) ( $item['next_attempt_at'] ?? 0 ) <= $now ) {
                $has_due_items = true;
                break;
            }
        }

        if ( ! $has_due_items || get_transient( self::PROCESS_LOCK_KEY ) ) {
            return;
        }

        set_transient( self::PROCESS_LOCK_KEY, '1', MINUTE_IN_SECONDS );
        self::process_queue();
    }


    /**
     * Dispatch one queue item.
     *
     * @since 1.4.7
     * @param array $item Queue item.
     * @return array
     */
    private static function dispatch_item( $item ) {
        $type = $item['type'];
        $payload = $item['payload'];

        switch ( $type ) {
            case 'text':
                $result = Controller::send_message_text(
                    $payload['sender'] ?? '',
                    $payload['receiver'] ?? '',
                    $payload['message'] ?? '',
                    (int) ( $payload['delay'] ?? 0 ),
                    false,
                    true
                );
                break;

            case 'media':
                $result = Controller::send_message_media(
                    $payload['sender'] ?? '',
                    $payload['receiver'] ?? '',
                    $payload['media_type'] ?? '',
                    $payload['media'] ?? '',
                    $payload['caption'] ?? '',
                    (int) ( $payload['delay'] ?? 0 ),
                    false,
                    true
                );
                break;

            case 'audio':
                $result = Controller::send_whatsapp_audio(
                    $payload['sender'] ?? '',
                    $payload['receiver'] ?? '',
                    $payload['audio'] ?? '',
                    (int) ( $payload['delay'] ?? 0 ),
                    false,
                    true
                );
                break;

            default:
                return array(
                    'success' => false,
                    'error' => 'invalid_queue_type',
                );
        }

        if ( is_array( $result ) ) {
            return $result;
        }

        return array(
            'success' => ( 201 === (int) $result ),
            'error' => 'unknown_response',
        );
    }


    /**
     * Normalize payload for each queue type.
     *
     * @since 1.4.7
     * @param string $type Queue type.
     * @param array $payload Queue payload.
     * @return array
     */
    private static function sanitize_payload( $type, $payload ) {
        if ( ! is_array( $payload ) ) {
            return array();
        }

        switch ( $type ) {
            case 'text':
                return array(
                    'sender' => sanitize_text_field( $payload['sender'] ?? '' ),
                    'receiver' => sanitize_text_field( $payload['receiver'] ?? '' ),
                    'message' => wp_kses_post( $payload['message'] ?? '' ),
                    'delay' => max( 0, (int) ( $payload['delay'] ?? 0 ) ),
                );

            case 'media':
                return array(
                    'sender' => sanitize_text_field( $payload['sender'] ?? '' ),
                    'receiver' => sanitize_text_field( $payload['receiver'] ?? '' ),
                    'media_type' => sanitize_key( $payload['media_type'] ?? '' ),
                    'media' => esc_url_raw( $payload['media'] ?? '' ),
                    'caption' => wp_kses_post( $payload['caption'] ?? '' ),
                    'delay' => max( 0, (int) ( $payload['delay'] ?? 0 ) ),
                );

            case 'audio':
                return array(
                    'sender' => sanitize_text_field( $payload['sender'] ?? '' ),
                    'receiver' => sanitize_text_field( $payload['receiver'] ?? '' ),
                    'audio' => esc_url_raw( $payload['audio'] ?? '' ),
                    'delay' => max( 0, (int) ( $payload['delay'] ?? 0 ) ),
                );
        }

        return array();
    }


    /**
     * Get retry delay in seconds based on attempts and failure reason.
     *
     * @since 1.4.7
     * @param int $attempts Number of retries already made.
     * @param string $reason Failure reason.
     * @return int
     */
    private static function get_next_delay( $attempts, $reason = '' ) {
        $reason = strtolower( (string) $reason );

        if ( strpos( $reason, 'license' ) !== false ) {
            $delay = HOUR_IN_SECONDS;
        } else {
            $delay = 5 * MINUTE_IN_SECONDS * max( 1, (int) pow( 2, min( 6, max( 0, $attempts ) ) ) );
            $delay = min( 6 * HOUR_IN_SECONDS, $delay );
        }

        return (int) apply_filters( 'Joinotify/Notification_Queue/Retry_Delay', $delay, $attempts, $reason );
    }


    /**
     * Retrieve queue list from database.
     *
     * @since 1.4.7
     * @return array
     */
    private static function get_queue() {
        $queue = get_option( self::OPTION_KEY, array() );

        return is_array( $queue ) ? $queue : array();
    }


    /**
     * Persist queue in database.
     *
     * @since 1.4.7
     * @param array $queue Queue items.
     * @return void
     */
    private static function save_queue( $queue ) {
        update_option( self::OPTION_KEY, array_values( $queue ) );
    }
}
