<?php

namespace MeuMouse\Joinotify\Telemetry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Where events wait between the request that produced them and the batch that sends them.
 *
 * The interesting decisions live in three pure functions — `merge()`, `take_batch()` and
 * `remove_ids()` — and that is on purpose. Sampling, coalescing and trimming could all
 * have happened at the moment an event was recorded, but that would mean reading state
 * from the database inside a page request. Deferring them to the flush keeps the hot path
 * free of I/O and, as a side effect, puts every rule that can actually be wrong into
 * functions a test can call without WordPress.
 *
 * Nothing leaves the buffer until the service answers 202. A batch that times out comes
 * back with the same event ids and the service de-duplicates it. That is the right way
 * round: counting an event twice is a smaller lie than losing the one failure that
 * explained an outage.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Buffer {

    /**
     * Option holding the pending events.
     *
     * Created with autoload 'no'. This matters more than it looks: `update_option()` does
     * not change the autoload flag of an option that already exists, so getting it wrong
     * once puts the buffer into every single request on the site — forever, and silently.
     *
     * @since 2.3.0
     * @var string
     */
    const OPTION = 'joinotify_telemetry_buffer';


    /**
     * Hard ceiling on stored events.
     *
     * @since 2.3.0
     * @var int
     */
    const MAX_EVENTS = 500;


    /**
     * Events per request to the service.
     *
     * @since 2.3.0
     * @var int
     */
    const MAX_BATCH_EVENTS = 200;


    /**
     * Byte ceiling of the events array, leaving room for the installation block inside
     * the service's 64 kb body limit.
     *
     * @since 2.3.0
     * @var int
     */
    const MAX_BATCH_BYTES = 56000;


    /**
     * How long two identical events collapse into one.
     *
     * A day, not an hour: the questions these events answer are "which features run" and
     * "on how many sites", and neither gets a better answer from the second occurrence.
     * A store with five thousand orders a day contributes about ten events instead of
     * five thousand, and the answer is the same.
     *
     * @since 2.3.0
     * @var int
     */
    const COALESCE_WINDOW = 86400;


    /**
     * Per-flush ceilings for the noisy families.
     *
     * @since 2.3.0
     * @var array<string,int>
     */
    const FAMILY_LIMITS = array(
        'plugin.error' => 20,
        'settings.changed' => 10,
    );


    /**
     * An empty buffer.
     *
     * @since 2.3.0
     * @return array<string,mixed>
     */
    public static function blank() {
        return array( 'v' => 1, 'events' => array(), 'seen' => array(), 'dropped' => 0 );
    }


    /**
     * Fold newly recorded events into the stored buffer.
     *
     * Pure: everything it needs arrives as an argument.
     *
     * @since 2.3.0
     * @param array $stored | Buffer as read from the option.
     * @param array $incoming | Events recorded during this request.
     * @param array $limits | Overrides for max_events, window and families.
     * @param int $now | Unix timestamp.
     * @return array<string,mixed>
     */
    public static function merge( $stored, $incoming, $limits, $now ) {
        $buffer = self::normalize( $stored );
        $now = (int) $now;

        $max_events = isset( $limits['max_events'] ) ? (int) $limits['max_events'] : self::MAX_EVENTS;
        $window = isset( $limits['window'] ) ? (int) $limits['window'] : self::COALESCE_WINDOW;
        $families = isset( $limits['families'] ) ? (array) $limits['families'] : self::FAMILY_LIMITS;

        // Forget coalescing keys older than the window, otherwise `seen` grows for as
        // long as the site runs and eventually costs more than the events it saves.
        foreach ( $buffer['seen'] as $key => $stamp ) {
            if ( ( $now - (int) $stamp ) >= $window ) {
                unset( $buffer['seen'][ $key ] );
            }
        }

        $per_family = array();

        foreach ( (array) $incoming as $event ) {
            if ( empty( $event['name'] ) || empty( $event['id'] ) ) {
                continue;
            }

            $name = (string) $event['name'];

            if ( isset( $families[ $name ] ) ) {
                $seen_count = isset( $per_family[ $name ] ) ? $per_family[ $name ] : 0;

                if ( $seen_count >= (int) $families[ $name ] ) {
                    $buffer['dropped']++;
                    continue;
                }

                $per_family[ $name ] = $seen_count + 1;
            }

            if ( Event_Catalog::coalesces( $name ) ) {
                $key = self::coalesce_key( $event );

                if ( isset( $buffer['seen'][ $key ] ) ) {
                    continue;
                }

                $buffer['seen'][ $key ] = $now;
            }

            $buffer['events'][] = $event;
        }

        $trimmed = self::trim( $buffer['events'], $max_events );
        $buffer['events'] = $trimmed['events'];
        $buffer['dropped'] += $trimmed['dropped'];

        return $buffer;
    }


    /**
     * Drop the oldest events until the buffer fits, sparing the ones that only happen
     * once.
     *
     * A burst of errors must not be able to bury `plugin.updated`: the service records
     * each milestone the first time it sees it and never again, so an evicted milestone
     * is a milestone lost for that installation forever.
     *
     * @since 2.3.0
     * @param array $events | Stored events, oldest first.
     * @param int $max | Ceiling.
     * @return array{events:array<int,array<string,mixed>>,dropped:int}
     */
    public static function trim( $events, $max ) {
        $events = array_values( (array) $events );
        $total = count( $events );

        if ( $total <= (int) $max ) {
            return array( 'events' => $events, 'dropped' => 0 );
        }

        $excess = $total - (int) $max;
        $dropped = 0;
        $kept = array();

        foreach ( $events as $event ) {
            if ( $excess > 0 && ! Event_Catalog::is_protected( $event['name'] ) ) {
                $excess--;
                $dropped++;
                continue;
            }

            $kept[] = $event;
        }

        return array( 'events' => array_values( $kept ), 'dropped' => $dropped );
    }


    /**
     * Identity of an event for coalescing: the name plus its properties, canonically
     * ordered so that the same event built in a different order still matches.
     *
     * @since 2.3.0
     * @param array $event | Event.
     * @return string
     */
    public static function coalesce_key( $event ) {
        $props = isset( $event['props'] ) && is_array( $event['props'] ) ? $event['props'] : array();
        ksort( $props );

        return (string) $event['name'] . '|' . md5( (string) json_encode( $props ) );
    }


    /**
     * Take as many events as fit in one request.
     *
     * Respects both ceilings. The byte one is the one that gets forgotten: forty events
     * carrying long error codes reach 64 kb well before they reach two hundred, and the
     * service answers 422 for the whole batch — which, without this, would repeat on
     * every run until someone looked.
     *
     * @since 2.3.0
     * @param array $events | Stored events.
     * @param int $max_events | Event ceiling.
     * @param int $max_bytes | Byte ceiling.
     * @return array{0:array<int,array<string,mixed>>,1:array<int,string>}
     */
    public static function take_batch( $events, $max_events, $max_bytes ) {
        $batch = array();
        $ids = array();
        $bytes = 2; // the enclosing brackets

        foreach ( (array) $events as $event ) {
            if ( count( $batch ) >= (int) $max_events ) {
                break;
            }

            $size = strlen( (string) json_encode( $event ) ) + 1;

            // Always take at least one, even an oversized one: leaving it behind would
            // block every event queued after it, and the service rejecting one event is
            // recoverable while a permanently stuck buffer is not.
            if ( ! empty( $batch ) && ( $bytes + $size ) > (int) $max_bytes ) {
                break;
            }

            $bytes += $size;
            $batch[] = $event;
            $ids[] = (string) $event['id'];
        }

        return array( $batch, $ids );
    }


    /**
     * Drop the events the service confirmed, keeping whatever arrived mid-flight.
     *
     * @since 2.3.0
     * @param array $events | Stored events.
     * @param array $ids | Confirmed event ids.
     * @return array<int,array<string,mixed>>
     */
    public static function remove_ids( $events, $ids ) {
        $ids = array_flip( array_map( 'strval', (array) $ids ) );
        $kept = array();

        foreach ( (array) $events as $event ) {
            if ( ! isset( $ids[ (string) $event['id'] ] ) ) {
                $kept[] = $event;
            }
        }

        return array_values( $kept );
    }


    /**
     * Coerce anything read from the option into the expected shape.
     *
     * @since 2.3.0
     * @param mixed $stored | Whatever came back from `get_option()`.
     * @return array<string,mixed>
     */
    private static function normalize( $stored ) {
        $blank = self::blank();

        if ( ! is_array( $stored ) ) {
            return $blank;
        }

        return array(
            'v' => 1,
            'events' => isset( $stored['events'] ) && is_array( $stored['events'] ) ? array_values( $stored['events'] ) : array(),
            'seen' => isset( $stored['seen'] ) && is_array( $stored['seen'] ) ? $stored['seen'] : array(),
            'dropped' => isset( $stored['dropped'] ) ? (int) $stored['dropped'] : 0,
        );
    }


    /**
     * Read the buffer.
     *
     * @since 2.3.0
     * @return array<string,mixed>
     */
    public static function load() {
        return self::normalize( get_option( self::OPTION, array() ) );
    }


    /**
     * Persist the buffer, creating the option with autoload disabled the first time.
     *
     * @since 2.3.0
     * @param array $buffer | Buffer to store.
     * @return void
     */
    public static function save( $buffer ) {
        if ( false === get_option( self::OPTION, false ) ) {
            add_option( self::OPTION, $buffer, '', 'no' );

            return;
        }

        update_option( self::OPTION, $buffer );
    }


    /**
     * Throw the buffer away.
     *
     * @since 2.3.0
     * @return void
     */
    public static function clear() {
        delete_option( self::OPTION );
    }
}
