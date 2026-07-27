<?php

namespace MeuMouse\Joinotify\Licensing;

use MeuMouse\Joinotify\Licensing\Drivers\Legacy_Driver;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Which licensing backend this site is bound to.
 *
 * A site starts on the legacy server and moves to MDS the first time the legacy
 * server stops answering. That move is one-way: the legacy endpoint is being
 * retired, so re-probing it would only reintroduce its timeout on every call
 * once it is gone for good.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Driver_State {

    /**
     * Option holding the elected driver and why it was elected.
     *
     * @since 2.1.0
     * @var string
     */
    const OPTION = 'joinotify_license_driver';

    /**
     * Read the elected driver.
     *
     * @since 2.1.0
     * @return string One of the driver identifiers
     */
    public static function current() {
        $override = self::override();

        if ( '' !== $override ) {
            return $override;
        }

        $state = get_option( self::OPTION );

        if ( is_array( $state ) && ! empty( $state['driver'] ) && self::is_known( $state['driver'] ) ) {
            return $state['driver'];
        }

        return Legacy_Driver::ID;
    }


    /**
     * Record that a driver answered, so later requests go straight to it.
     *
     * Electing the legacy driver is a no-op: it is the default, and writing it
     * back would let a transient MDS outage undo a completed migration.
     *
     * @since 2.1.0
     * @param string $driver_id | Driver identifier
     * @param string $reason | Why the election happened, for support
     * @return bool Whether the election changed anything
     */
    public static function elect( $driver_id, $reason = '' ) {
        if ( ! self::is_known( $driver_id ) || Legacy_Driver::ID === $driver_id ) {
            return false;
        }

        if ( $driver_id === self::current() ) {
            return false;
        }

        update_option( self::OPTION, array(
            'driver' => $driver_id,
            'decided_at' => time(),
            'reason' => (string) $reason,
        ));

        /**
         * Fires when a site moves to a different licensing backend.
         *
         * @since 2.1.0
         * @param string $driver_id | Driver that answered
         * @param string $reason | Why the previous driver was abandoned
         */
        do_action( 'Joinotify/Licensing/Driver_Elected', $driver_id, $reason );

        return true;
    }


    /**
     * Full election record, for the settings screen and support.
     *
     * @since 2.1.0
     * @return array
     */
    public static function details() {
        $state = get_option( self::OPTION );
        $state = is_array( $state ) ? $state : array();

        return array(
            'driver' => self::current(),
            'decided_at' => isset( $state['decided_at'] ) ? (int) $state['decided_at'] : 0,
            'reason' => isset( $state['reason'] ) ? (string) $state['reason'] : '',
            'forced' => '' !== self::override(),
        );
    }


    /**
     * Forget the election. Only meant for uninstall and for support tooling.
     *
     * @since 2.1.0
     * @return void
     */
    public static function reset() {
        delete_option( self::OPTION );
    }


    /**
     * Driver forced by configuration, bypassing the election.
     *
     * @since 2.1.0
     * @return string Empty string when nothing is forced
     */
    protected static function override() {
        $forced = defined('JOINOTIFY_LICENSE_DRIVER') ? JOINOTIFY_LICENSE_DRIVER : '';

        /**
         * Filters the licensing driver, bypassing the automatic election.
         *
         * Lets a site be pointed at one backend for testing, or pinned to the
         * legacy server if the migration has to be paused.
         *
         * @since 2.1.0
         * @param string $forced | Driver identifier, or an empty string
         */
        $forced = apply_filters( 'Joinotify/Licensing/Force_Driver', $forced );

        return self::is_known( $forced ) ? $forced : '';
    }


    /**
     * Whether an identifier names a driver we ship.
     *
     * @since 2.1.0
     * @param mixed $driver_id | Candidate identifier
     * @return bool
     */
    protected static function is_known( $driver_id ) {
        return in_array( $driver_id, array( Legacy_Driver::ID, Mds_Driver::ID ), true );
    }
}
