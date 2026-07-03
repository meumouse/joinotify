<?php

namespace MeuMouse\Joinotify\Otp_Login;

defined('ABSPATH') || exit;

/**
 * Lightweight transient-based rate limiter for the OTP login endpoints.
 *
 * Used to throttle how often a code can be requested (per phone + client IP)
 * and to enforce a server-side resend cooldown, since the countdown shown in
 * the UI is purely cosmetic and can be bypassed.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Rate_Limiter {

    /**
     * Transient key prefix for hit counters.
     *
     * @since 2.0.0
     * @var string
     */
    const PREFIX = 'joinotify_otp_rl_';

    /**
     * Whether the given bucket still has budget, and consume one hit if so.
     *
     * @since 2.0.0
     * @param string $bucket Logical bucket name (e.g. 'request').
     * @param string $identity Caller identity (phone, IP, ...).
     * @param int    $limit Maximum hits allowed within the window.
     * @param int    $window Window length in seconds.
     * @return bool True when the hit is allowed, false when the limit is hit.
     */
    public static function consume( $bucket, $identity, $limit, $window ) {
        $key = self::build_key( $bucket, $identity );
        $hits = (int) get_transient( $key );

        if ( $hits >= (int) $limit ) {
            return false;
        }

        set_transient( $key, $hits + 1, (int) $window );

        return true;
    }


    /**
     * Whether a cooldown is currently active for the given identity.
     *
     * @since 2.0.0
     * @param string $bucket Logical bucket name.
     * @param string $identity Caller identity.
     * @return bool
     */
    public static function is_cooling_down( $bucket, $identity ) {
        return false !== get_transient( self::build_key( $bucket, $identity ) );
    }


    /**
     * Start a cooldown window for the given identity.
     *
     * @since 2.0.0
     * @param string $bucket Logical bucket name.
     * @param string $identity Caller identity.
     * @param int    $window Cooldown length in seconds.
     * @return void
     */
    public static function start_cooldown( $bucket, $identity, $window ) {
        set_transient( self::build_key( $bucket, $identity ), time(), (int) $window );
    }


    /**
     * Clear a bucket for the given identity.
     *
     * @since 2.0.0
     * @param string $bucket Logical bucket name.
     * @param string $identity Caller identity.
     * @return void
     */
    public static function reset( $bucket, $identity ) {
        delete_transient( self::build_key( $bucket, $identity ) );
    }


    /**
     * Resolve the client IP address for rate-limit identity.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
        $ip = filter_var( $ip, FILTER_VALIDATE_IP );

        return $ip ? $ip : 'unknown';
    }


    /**
     * Build the transient key for a bucket + identity pair.
     *
     * @since 2.0.0
     * @param string $bucket Logical bucket name.
     * @param string $identity Caller identity.
     * @return string
     */
    private static function build_key( $bucket, $identity ) {
        return self::PREFIX . sanitize_key( $bucket ) . '_' . md5( (string) $identity );
    }
}
