<?php

namespace MeuMouse\Joinotify\Telemetry;

use MeuMouse\Joinotify\Core\Telemetry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The only entry point for recording an event — and the one that runs inside page loads.
 *
 * Everything here is shaped by a single constraint: a site owner must never be able to
 * measure the difference between telemetry on and telemetry off. So `record()` does no
 * database work, opens no connection and writes nothing. It validates against an array of
 * constants and appends to a static array; the one read and one write happen once, at
 * `shutdown`, after the response has already been produced.
 *
 * The dispatch itself never happens here. That belongs to cron, where nobody is waiting.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Recorder {

    /**
     * Events collected during this request.
     *
     * @since 2.3.0
     * @var array<int,array<string,mixed>>
     */
    private static $pending = array();


    /**
     * Whether the shutdown flush is already hooked.
     *
     * @since 2.3.0
     * @var bool
     */
    private static $hooked = false;


    /**
     * Cached consent, so a workflow firing forty actions reads the setting once.
     *
     * @since 2.3.0
     * @var bool|null
     */
    private static $enabled = null;


    /**
     * Whether this request may record anything.
     *
     * Only consent is checked here. The service's own opt-out lives in an option that is
     * not autoloaded, and reading it would be the database round trip this class exists
     * to avoid — it is applied at flush time instead, which costs nothing extra because
     * the flush is already touching options.
     *
     * @since 2.3.0
     * @return bool
     */
    public static function is_recording() {
        if ( null === self::$enabled ) {
            self::$enabled = Telemetry::is_enabled();
        }

        return self::$enabled;
    }


    /**
     * Forget the cached consent, after the setting changes within the same request.
     *
     * @since 2.3.0
     * @return void
     */
    public static function refresh() {
        self::$enabled = null;
    }


    /**
     * Record one event.
     *
     * Silently ignores anything the service would not accept. That is not laziness: a
     * name or property the catalog does not know is a bug on this side, and the place it
     * should surface is the mirror test, not a site owner's error log.
     *
     * @since 2.3.0
     * @param string $name | Event name from the catalog.
     * @param array $props | Properties; anything outside the allow-list is dropped.
     * @param int|null $timestamp | Unix timestamp, defaults to now.
     * @return bool Whether the event was accepted into the buffer.
     */
    public static function record( $name, $props = array(), $timestamp = null ) {
        if ( ! self::is_recording() || ! Event_Catalog::has( $name ) ) {
            return false;
        }

        $timestamp = null === $timestamp ? time() : (int) $timestamp;

        $event = array(
            'id' => Ulid::generate( $timestamp * 1000 ),
            'name' => (string) $name,
            'at' => gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ),
        );

        $clean = Event_Catalog::filter_props( $name, is_array( $props ) ? $props : array() );

        // Omitted rather than sent empty: an empty PHP array encodes as `[]`, not `{}`,
        // and the service expects an object there. It survives that today, and relying on
        // it would be relying on an accident.
        if ( ! empty( $clean ) ) {
            $event['props'] = $clean;
        }

        self::$pending[] = $event;

        if ( ! self::$hooked ) {
            self::$hooked = true;

            // Priority 0: before anything else that might run long on shutdown, and long
            // before a fatal in unrelated shutdown code could cost us the batch.
            add_action( 'shutdown', array( __CLASS__, 'flush' ), 0 );
        }

        return true;
    }


    /**
     * Write what this request collected into the buffer.
     *
     * One read, one write, and only when there is something to write.
     *
     * @since 2.3.0
     * @return int Number of events persisted.
     */
    public static function flush() {
        if ( empty( self::$pending ) ) {
            return 0;
        }

        $pending = self::$pending;
        self::$pending = array();

        $state = Policy::load();

        // The account asked the service to stop counting it. Dropping the events here,
        // rather than at dispatch, means an opted-out site never accumulates anything to
        // begin with.
        if ( ! empty( $state['opted_out'] ) ) {
            return 0;
        }

        $buffer = Buffer::merge( Buffer::load(), $pending, array(), time() );

        Buffer::save( $buffer );

        return count( $pending );
    }


    /**
     * Persist immediately, outside the shutdown hook.
     *
     * Used by deactivation, which has no next request to flush in.
     *
     * @since 2.3.0
     * @return int
     */
    public static function flush_now() {
        return self::flush();
    }


    /**
     * Drop whatever this request collected without persisting it.
     *
     * @since 2.3.0
     * @return void
     */
    public static function discard() {
        self::$pending = array();
    }
}
