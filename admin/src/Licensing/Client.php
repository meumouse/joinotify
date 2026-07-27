<?php

namespace MeuMouse\Joinotify\Licensing;

use MeuMouse\Joinotify\Licensing\Contracts\Driver;
use MeuMouse\Joinotify\Licensing\Drivers\Legacy_Driver;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Routes licensing calls to whichever backend is answering.
 *
 * Sites run against the legacy server until it stops responding, then move to
 * MDS and stay there. The rule that makes this safe is narrow on purpose: a
 * second backend is only ever consulted after a transport failure, meaning the
 * first one never answered at all. A refusal is an answer, and ends the call —
 * otherwise a key revoked on the old server would come back to life simply by
 * being asked again somewhere else.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Client {

    /**
     * License key these calls are about.
     *
     * @since 2.1.0
     * @var string
     */
    protected $license_key;

    /**
     * Drivers by identifier, built lazily.
     *
     * @since 2.1.0
     * @var array
     */
    protected $drivers = array();

    /**
     * Construct the client.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @param array $drivers | Pre-built drivers keyed by identifier, for tests
     * @return void
     */
    public function __construct( $license_key = '', $drivers = array() ) {
        $this->license_key = is_string( $license_key ) ? $license_key : '';

        foreach ( $drivers as $driver ) {
            if ( $driver instanceof Driver ) {
                $this->drivers[ $driver->id() ] = $driver;
            }
        }
    }


    /**
     * Activate the license.
     *
     * @since 2.1.0
     * @param string $license_key | License key, defaults to the client's own
     * @return License_Result
     */
    public function activate( $license_key = null ) {
        return $this->dispatch( 'activate', $license_key );
    }


    /**
     * Re-check an activated license.
     *
     * @since 2.1.0
     * @param string $license_key | License key, defaults to the client's own
     * @return License_Result
     */
    public function validate( $license_key = null ) {
        return $this->dispatch( 'validate', $license_key );
    }


    /**
     * Release this site's activation.
     *
     * @since 2.1.0
     * @param string $license_key | License key, defaults to the client's own
     * @return License_Result
     */
    public function deactivate( $license_key = null ) {
        return $this->dispatch( 'deactivate', $license_key );
    }


    /**
     * Expiration timestamp for the license.
     *
     * @since 2.1.0
     * @param string $license_key | License key, defaults to the client's own
     * @return int|null
     */
    public function expires_at( $license_key = null ) {
        $license_key = null === $license_key ? $this->license_key : $license_key;

        foreach ( $this->candidates() as $driver ) {
            $timestamp = $driver->expires_at( $license_key );

            if ( null !== $timestamp ) {
                $this->elect( $driver, 'expiry lookup answered' );

                return $timestamp;
            }
        }

        return null;
    }


    /**
     * Identifier of the driver this site is currently bound to.
     *
     * @since 2.1.0
     * @return string
     */
    public function current_driver_id() {
        return Driver_State::current();
    }


    /**
     * Run an operation against the elected driver, falling back only when the
     * server could not be reached.
     *
     * @since 2.1.0
     * @param string $operation | Driver method to call
     * @param string|null $license_key | License key
     * @return License_Result
     */
    protected function dispatch( $operation, $license_key ) {
        $license_key = null === $license_key ? $this->license_key : $license_key;
        $result = null;

        foreach ( $this->candidates() as $driver ) {
            $result = $driver->$operation( $license_key );

            if ( ! $result->is_transport_failure() ) {
                $this->elect( $driver, sprintf( '%s answered', $operation ) );

                return $result;
            }
        }

        // Every backend was unreachable. Reporting this as a transport failure
        // is what lets the caller fall back on the last known good answer
        // instead of revoking a license over a network problem.
        return $result instanceof License_Result
            ? $result
            : License_Result::transport_failure( __( 'No licensing backend is available.', 'joinotify' ) );
    }


    /**
     * Drivers to try, in order: the elected one first, then anything newer that
     * has not been ruled out.
     *
     * @since 2.1.0
     * @return Driver[]
     */
    protected function candidates() {
        $current = Driver_State::current();
        $order = array( $current );

        // Only the legacy driver has somewhere to fall back to. Once a site is
        // on MDS there is nothing newer, and nothing older worth revisiting.
        if ( Legacy_Driver::ID === $current ) {
            $order[] = Mds_Driver::ID;
        }

        /**
         * Filters the order in which licensing drivers are tried.
         *
         * @since 2.1.0
         * @param array $order | Driver identifiers, most preferred first
         * @param string $current | Currently elected driver
         */
        $order = apply_filters( 'Joinotify/Licensing/Driver_Order', $order, $current );

        $drivers = array();

        foreach ( $order as $driver_id ) {
            $driver = $this->driver( $driver_id );

            if ( $driver instanceof Driver ) {
                $drivers[] = $driver;
            }
        }

        return $drivers;
    }


    /**
     * Build (and remember) a driver by identifier.
     *
     * @since 2.1.0
     * @param string $driver_id | Driver identifier
     * @return Driver|null
     */
    protected function driver( $driver_id ) {
        if ( isset( $this->drivers[ $driver_id ] ) ) {
            return $this->drivers[ $driver_id ];
        }

        if ( Legacy_Driver::ID === $driver_id ) {
            $this->drivers[ $driver_id ] = new Legacy_Driver( $this->license_key );
        } elseif ( Mds_Driver::ID === $driver_id ) {
            $this->drivers[ $driver_id ] = new Mds_Driver();
        } else {
            return null;
        }

        return $this->drivers[ $driver_id ];
    }


    /**
     * Bind the site to the driver that answered.
     *
     * @since 2.1.0
     * @param Driver $driver | Driver that answered
     * @param string $reason | Why the previous driver was abandoned
     * @return void
     */
    protected function elect( Driver $driver, $reason ) {
        Driver_State::elect( $driver->id(), $reason );
    }
}
