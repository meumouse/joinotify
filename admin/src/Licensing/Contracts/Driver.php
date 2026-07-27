<?php

namespace MeuMouse\Joinotify\Licensing\Contracts;

use MeuMouse\Joinotify\Licensing\Dto\License_Result;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Contract every licensing backend implements.
 *
 * A driver owns one protocol and nothing else: build the request, talk to the
 * server, translate the answer into a License_Result. Where the resulting state
 * gets stored, how long it is cached and when to retry are decided one layer up,
 * so those rules stay identical no matter which server answered.
 *
 * Implementations must never throw for an expected outcome. An unreachable
 * server is a transport failure, a refused key is a business failure, and both
 * are returned as results.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
interface Driver {

    /**
     * Short identifier used to record which driver a site is bound to.
     *
     * @since 2.1.0
     * @return string
     */
    public function id();


    /**
     * Activate a license key for this site.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function activate( $license_key );


    /**
     * Release this site's activation.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function deactivate( $license_key );


    /**
     * Re-check a key that is already activated, without consuming a seat.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function validate( $license_key );


    /**
     * Expiration timestamp for a key, or null when it cannot be determined.
     *
     * Used by the scheduled expiration check, which needs the date on its own
     * without disturbing the stored license state.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return int|null
     */
    public function expires_at( $license_key );
}
