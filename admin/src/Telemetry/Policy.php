<?php

namespace MeuMouse\Joinotify\Telemetry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * What the service told us last time, and what to do about it.
 *
 * The service answers every batch with three directives — how often to come back, what
 * fraction of installations it wants to hear from, and whether this account asked to be
 * left alone. Obeying them is the whole reason the dispatch is blocking: a fire-and-forget
 * request cannot read a reply, and a client that cannot read a reply is one the service
 * has no way to slow down except by refusing it.
 *
 * That matters at the scale this runs at. Turning the tap down for ten thousand sites is
 * a number changed in an admin screen, not a plugin release followed by weeks of waiting
 * for people to update.
 *
 * The decisions are pure functions over the stored state, so the backoff curve and the
 * sampling cut are covered by tests instead of by reasoning.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Policy {

    /**
     * Option holding the last known server directives. Autoload disabled.
     *
     * @since 2.5.0
     * @var string
     */
    const OPTION = 'joinotify_telemetry_state';


    /**
     * Fallback interval until the service says otherwise.
     *
     * @since 2.5.0
     * @var int
     */
    const DEFAULT_INTERVAL = 21600;


    /**
     * Longest a backoff can grow to.
     *
     * @since 2.5.0
     * @var int
     */
    const MAX_BACKOFF = 86400;


    /**
     * Base of the backoff curve.
     *
     * @since 2.5.0
     * @var int
     */
    const BASE_BACKOFF = 1800;


    /**
     * How long to wait before asking again after the service said this account opted out.
     *
     * @since 2.5.0
     * @var int
     */
    const OPT_OUT_RECHECK = 604800;


    /**
     * Starting state.
     *
     * @since 2.5.0
     * @return array<string,mixed>
     */
    public static function defaults() {
        return array(
            'interval' => self::DEFAULT_INTERVAL,
            'sample_rate' => 100,
            'opted_out' => false,
            'next_at' => 0,
            'failures' => 0,
            'paused' => false,
            'last_status' => 0,
            'last_at' => 0,
        );
    }


    /**
     * Fold a successful response into the state.
     *
     * @since 2.5.0
     * @param array $state | Current state.
     * @param array $data | The `data` object of the 202.
     * @param int $now | Unix timestamp.
     * @return array<string,mixed>
     */
    public static function apply( $state, $data, $now ) {
        $state = self::normalize( $state );
        $data = is_array( $data ) ? $data : array();

        if ( isset( $data['intervalSeconds'] ) ) {
            $interval = (int) $data['intervalSeconds'];

            // Clamped to the range the service itself validates. A bad value here would
            // either hammer the service or silence this site for a year, and neither
            // failure announces itself.
            if ( $interval >= 300 && $interval <= 86400 ) {
                $state['interval'] = $interval;
            }
        }

        if ( isset( $data['sampleRate'] ) ) {
            $rate = (int) $data['sampleRate'];
            $state['sample_rate'] = max( 0, min( 100, $rate ) );
        }

        $state['opted_out'] = ! empty( $data['optedOut'] );
        $state['failures'] = 0;
        $state['paused'] = false;
        $state['last_status'] = 202;
        $state['last_at'] = (int) $now;

        return $state;
    }


    /**
     * When to try again, given how the last attempt went.
     *
     * @since 2.5.0
     * @param int $status | HTTP status, or 0 for a transport failure.
     * @param int $failures | Consecutive failures INCLUDING this one.
     * @param int $interval | Normal interval in seconds.
     * @param int $now | Unix timestamp.
     * @param int $retry_after | Seconds from a Retry-After header, when present.
     * @return int
     */
    public static function next_attempt( $status, $failures, $interval, $now, $retry_after = 0 ) {
        $status = (int) $status;
        $now = (int) $now;

        if ( 202 === $status ) {
            return $now + max( 300, (int) $interval );
        }

        // The service understood the request and refused it. Repeating it changes
        // nothing, so this is not a failure to back off from — it is a bug to fix, and
        // the next batch should go out on the normal schedule.
        if ( 422 === $status ) {
            return $now + max( 300, (int) $interval );
        }

        if ( self::pauses( $status ) ) {
            return 0;
        }

        if ( $retry_after > 0 ) {
            return $now + min( self::MAX_BACKOFF, (int) $retry_after );
        }

        // Six doublings, so the curve actually reaches MAX_BACKOFF and the clamp does
        // real work: 30min, 1h, 2h, 4h, 8h, 16h, then a day. Capping the exponent one
        // step earlier would have left the documented ceiling unreachable — two limits
        // where only one ever applies, which is the kind of thing that reads correct in
        // review and is wrong in production.
        $step = min( 6, max( 0, (int) $failures - 1 ) );

        return $now + min( self::MAX_BACKOFF, self::BASE_BACKOFF * (int) pow( 2, $step ) );
    }


    /**
     * Whether the batch stays in the buffer after this status.
     *
     * @since 2.5.0
     * @param int $status | HTTP status.
     * @return bool
     */
    public static function keeps_batch( $status ) {
        $status = (int) $status;

        // 422 means the service parsed the body and rejected it. Sending it again would
        // fail identically forever, and the buffer would never drain past it.
        if ( 422 === $status ) {
            return false;
        }

        return 202 !== $status;
    }


    /**
     * Whether this status stops dispatching until something else wakes it up.
     *
     * @since 2.5.0
     * @param int $status | HTTP status.
     * @return bool
     */
    public static function pauses( $status ) {
        $status = (int) $status;

        // The key is gone or was revoked. Retrying cannot fix it; reconnecting can, and
        // that fires `Joinotify/Cloud_Api/Connected`, which is what resumes this.
        return 401 === $status || 403 === $status;
    }


    /*
     * Sampling is deliberately NOT decided here.
     *
     * The obvious optimization is to skip the request when this installation falls
     * outside `sample_rate`, saving the round trip. It was rejected: the service decides
     * membership by hashing the installation id, and reproducing that hash in PHP means
     * two implementations of one rule in two languages. They would agree in testing and
     * drift the first time either side changed a digest or a modulus — and the symptom
     * would be a set of sites silently counted twice, or not at all, with nothing in
     * either log saying so.
     *
     * So the rate is stored for observability and acted on in exactly one place: the
     * service. A site outside the sample gets a 202 with `accepted: 0`, which drains its
     * buffer without recording anything — the outcome the sampling wanted, decided once.
     */


    /**
     * Coerce anything read from the option into the expected shape.
     *
     * @since 2.5.0
     * @param mixed $state | Whatever came back from `get_option()`.
     * @return array<string,mixed>
     */
    public static function normalize( $state ) {
        $defaults = self::defaults();

        if ( ! is_array( $state ) ) {
            return $defaults;
        }

        return array(
            'interval' => isset( $state['interval'] ) ? (int) $state['interval'] : $defaults['interval'],
            'sample_rate' => isset( $state['sample_rate'] ) ? (int) $state['sample_rate'] : 100,
            'opted_out' => ! empty( $state['opted_out'] ),
            'next_at' => isset( $state['next_at'] ) ? (int) $state['next_at'] : 0,
            'failures' => isset( $state['failures'] ) ? (int) $state['failures'] : 0,
            'paused' => ! empty( $state['paused'] ),
            'last_status' => isset( $state['last_status'] ) ? (int) $state['last_status'] : 0,
            'last_at' => isset( $state['last_at'] ) ? (int) $state['last_at'] : 0,
        );
    }


    /**
     * Read the stored state.
     *
     * @since 2.5.0
     * @return array<string,mixed>
     */
    public static function load() {
        return self::normalize( get_option( self::OPTION, array() ) );
    }


    /**
     * Persist the state, creating the option with autoload disabled the first time.
     *
     * @since 2.5.0
     * @param array $state | State to store.
     * @return void
     */
    public static function save( $state ) {
        if ( false === get_option( self::OPTION, false ) ) {
            add_option( self::OPTION, $state, '', 'no' );

            return;
        }

        update_option( self::OPTION, $state );
    }


    /**
     * Forget everything except the service's opt-out, which has to outlive a reset.
     *
     * @since 2.5.0
     * @return void
     */
    public static function reset() {
        $state = self::load();
        $fresh = self::defaults();
        $fresh['opted_out'] = $state['opted_out'];

        self::save( $fresh );
    }
}
