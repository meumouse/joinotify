<?php

namespace MeuMouse\Joinotify\Telemetry;

use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Core\Debug_Log;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Telemetry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The one place a telemetry request leaves the site.
 *
 * Two decisions are worth stating, because both look wrong at first glance.
 *
 * The request is **blocking**, unlike the fire-and-forget the class it replaces used. A
 * non-blocking request cannot read the reply, and the reply is the entire control channel:
 * which events were accepted (so the buffer can drain), how often to come back, and
 * whether this account asked to be left alone. Without it the buffer would either grow
 * forever or drop events it never confirmed had arrived.
 *
 * The schedule is a **single event rescheduled after every run**, not a recurring one.
 * WP-Cron only offers named intervals, and the service hands back a number of seconds; a
 * recurring event would have to ignore it. The cost is that a lost single event never
 * comes back on its own, which is what `ensure_scheduled()` is for.
 *
 * Where it may run is deliberately narrower than the retry queue's fallback: cron and
 * WP-CLI always, `admin_init` only when cron is visibly broken, and never in a front-end
 * request. Failing to deliver a customer's message is the site owner's problem; failing
 * to deliver telemetry is ours.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Dispatcher {

    /**
     * Cron hook that carries the dispatch.
     *
     * @since 2.5.0
     * @var string
     */
    const HOOK = 'joinotify_telemetry_dispatch_event';


    /**
     * Transient guarding the `admin_init` fallback.
     *
     * @since 2.5.0
     * @var string
     */
    const LOCK = 'joinotify_telemetry_lock';


    /**
     * Seconds allowed for the request.
     *
     * @since 2.5.0
     * @var int
     */
    const TIMEOUT = 10;


    /**
     * Make sure a dispatch is on the calendar.
     *
     * Self-healing: a single event lost to a database restore or a plugin conflict is put
     * back the next time an admin page loads.
     *
     * @since 2.5.0
     * @return void
     */
    public static function ensure_scheduled() {
        if ( ! Telemetry::is_enabled() ) {
            return;
        }

        $state = Policy::load();

        if ( ! empty( $state['paused'] ) || ! empty( $state['opted_out'] ) ) {
            return;
        }

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            self::schedule_next( HOUR_IN_SECONDS );
        }
    }


    /**
     * Put the next dispatch on the calendar, spread out.
     *
     * The jitter is not cosmetic. Every site that updated on the same day would otherwise
     * inherit the same schedule and arrive within the same minute — a self-inflicted
     * thundering herd that grows with adoption.
     *
     * @since 2.5.0
     * @param int $seconds | Delay from now.
     * @return void
     */
    public static function schedule_next( $seconds ) {
        $seconds = max( 60, (int) $seconds );
        $jitter = wp_rand( 0, (int) min( 1800, $seconds / 10 ) );

        wp_clear_scheduled_hook( self::HOOK );
        wp_schedule_single_event( time() + $seconds + $jitter, self::HOOK );
    }


    /**
     * Take the dispatch off the calendar.
     *
     * @since 2.5.0
     * @return void
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::HOOK );
    }


    /**
     * Send one batch.
     *
     * @since 2.5.0
     * @param bool $opt_out_notice | Send an empty batch flagged as an opt-out and stop.
     * @return bool Whether a request was made.
     */
    public static function dispatch( $opt_out_notice = false ) {
        if ( ! $opt_out_notice && ! Telemetry::is_enabled() ) {
            return false;
        }

        $endpoint = Telemetry::endpoint();

        // An empty endpoint is the documented kill switch, and it stays that way.
        if ( '' === $endpoint ) {
            return false;
        }

        $state = Policy::load();

        if ( ! $opt_out_notice && ( ! empty( $state['paused'] ) || ! empty( $state['opted_out'] ) ) ) {
            return false;
        }

        // Without a key there is nothing to authenticate with, and the client would hand
        // back a WP_Error that looks exactly like a network failure — burning the backoff
        // on a condition no amount of waiting fixes.
        if ( ! Helpers::cloud_api_ready() ) {
            return false;
        }

        if ( $opt_out_notice ) {
            return self::send_opt_out_notice();
        }

        $buffer = Buffer::load();
        list( $batch, $ids ) = Buffer::take_batch( $buffer['events'], Buffer::MAX_BATCH_EVENTS, Buffer::MAX_BATCH_BYTES );

        $body = array(
            'installation' => Installation::snapshot(),
            'events' => $batch,
        );

        $response = Cloud_Client::request( 'POST', '/telemetry', $body, self::TIMEOUT );

        return self::handle( $response, $buffer, $ids, $state );
    }


    /**
     * Tell the service to stop counting this installation, then go quiet.
     *
     * Fired by switching the setting off. It is the one request the plugin makes *because*
     * the owner said no, which reads backwards until you consider the alternative: without
     * it the installation stays alive in the service's counts forever, and "off" would
     * only mean "no new data".
     *
     * @since 2.5.0
     * @return bool
     */
    private static function send_opt_out_notice() {
        /**
         * Filter whether switching telemetry off notifies the service.
         *
         * @since 2.5.0
         * @param bool $send Whether to send the notice.
         */
        if ( ! apply_filters( 'Joinotify/Telemetry/Send_Opt_Out', true ) ) {
            return false;
        }

        $body = array(
            'installation' => Installation::snapshot( true ),
            'events' => array(),
        );

        $response = Cloud_Client::request( 'POST', '/telemetry', $body, 5 );

        return ! is_wp_error( $response );
    }


    /**
     * Apply whatever the service answered.
     *
     * @since 2.5.0
     * @param mixed $response | Raw `wp_remote_request()` result.
     * @param array $buffer | Buffer as it was before the request.
     * @param array $ids | Event ids that went out.
     * @param array $state | Policy state before the request.
     * @return bool
     */
    private static function handle( $response, $buffer, $ids, $state ) {
        $now = time();
        $status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
        $data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();

        if ( 202 === $status ) {
            $state = Policy::apply( $state, $data, $now );

            $buffer['events'] = Buffer::remove_ids( $buffer['events'], $ids );
            Buffer::save( $buffer );

            // A non-zero discard means the local mirror and the service catalog drifted.
            // It is never routine — the mirror exists precisely so this number is zero.
            if ( ! empty( $data['discarded'] ) ) {
                Debug_Log::warning(
                    sprintf( 'Telemetry service discarded %d event(s).', (int) $data['discarded'] ),
                    array( 'channel' => 'telemetry', 'code' => 'catalog_drift' )
                );
            }

            if ( ! empty( $state['opted_out'] ) ) {
                Buffer::clear();
                $state['next_at'] = $now + Policy::OPT_OUT_RECHECK;
                Policy::save( $state );
                self::schedule_next( Policy::OPT_OUT_RECHECK );

                return true;
            }

            $state['next_at'] = Policy::next_attempt( 202, 0, $state['interval'], $now );
            Policy::save( $state );
            self::schedule_next( $state['interval'] );

            return true;
        }

        if ( ! Policy::keeps_batch( $status ) ) {
            // 422: the service parsed the body and refused it. Sending it again fails
            // identically, so the batch is dropped and the body is logged — this is a bug
            // to read, not a condition to wait out.
            $buffer['events'] = Buffer::remove_ids( $buffer['events'], $ids );
            Buffer::save( $buffer );

            Debug_Log::error(
                is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response ),
                array( 'channel' => 'telemetry', 'code' => 'batch_rejected', 'response_code' => $status )
            );

            $state['failures'] = 0;
            $state['last_status'] = $status;
            $state['last_at'] = $now;
            $state['next_at'] = Policy::next_attempt( $status, 0, $state['interval'], $now );
            Policy::save( $state );
            self::schedule_next( $state['interval'] );

            return true;
        }

        $state['failures'] = (int) $state['failures'] + 1;
        $state['last_status'] = $status;
        $state['last_at'] = $now;

        if ( Policy::pauses( $status ) ) {
            // The key died or was revoked. No notice to the owner: the same key is
            // already failing to send messages, and that warning exists.
            $state['paused'] = true;
            $state['next_at'] = 0;
            Policy::save( $state );
            self::unschedule();

            return true;
        }

        $retry_after = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_header( $response, 'retry-after' );
        $state['next_at'] = Policy::next_attempt( $status, $state['failures'], $state['interval'], $now, $retry_after );
        Policy::save( $state );
        self::schedule_next( max( 60, $state['next_at'] - $now ) );

        return true;
    }


    /**
     * Run a dispatch from an admin request when cron is clearly not running.
     *
     * @since 2.5.0
     * @return void
     */
    public static function maybe_dispatch_late() {
        if ( wp_doing_ajax() || wp_doing_cron() || ! Telemetry::is_enabled() ) {
            return;
        }

        $state = Policy::load();

        if ( empty( $state['next_at'] ) || ! empty( $state['paused'] ) || ! empty( $state['opted_out'] ) ) {
            return;
        }

        // Twice the interval late, not merely late: a busy site running cron normally
        // must never take this path.
        if ( time() < ( (int) $state['next_at'] + ( 2 * (int) $state['interval'] ) ) ) {
            return;
        }

        if ( get_transient( self::LOCK ) ) {
            return;
        }

        set_transient( self::LOCK, 1, 15 * MINUTE_IN_SECONDS );

        self::dispatch();
    }


    /**
     * Resume after a reconnection, when a dead key was what stopped us.
     *
     * @since 2.5.0
     * @return void
     */
    public static function resume() {
        $state = Policy::load();

        if ( empty( $state['paused'] ) ) {
            return;
        }

        $state['paused'] = false;
        $state['failures'] = 0;
        Policy::save( $state );

        self::schedule_next( MINUTE_IN_SECONDS );
    }
}
