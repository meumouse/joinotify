<?php

namespace MeuMouse\Joinotify\Licensing\Drivers;

use MeuMouse\Joinotify\Licensing\Contracts\Driver;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The Modular Distribution Service licensing backend.
 *
 * Placeholder while the SDK it wraps is not yet bundled. Every call reports a
 * transport failure, which is the honest answer: this backend is not reachable
 * from here yet. It also keeps the fallback inert, since the orchestrator only
 * elects a driver that actually answered.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Mds_Driver implements Driver {

    /**
     * Driver identifier.
     *
     * @since 2.1.0
     * @var string
     */
    const ID = 'mds';


    /**
     * Driver identifier.
     *
     * @since 2.1.0
     * @return string
     */
    public function id() {
        return self::ID;
    }


    /**
     * Activate a license key for this site.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function activate( $license_key ) {
        return $this->unavailable();
    }


    /**
     * Re-check an already activated key.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function validate( $license_key ) {
        return $this->unavailable();
    }


    /**
     * Release this site's activation.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function deactivate( $license_key ) {
        return $this->unavailable();
    }


    /**
     * Expiration timestamp for a key.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return int|null
     */
    public function expires_at( $license_key ) {
        return null;
    }


    /**
     * Standard "not wired up yet" answer.
     *
     * @since 2.1.0
     * @return License_Result
     */
    protected function unavailable() {
        return License_Result::transport_failure( __( 'The licensing service is not available.', 'joinotify' ) );
    }
}
