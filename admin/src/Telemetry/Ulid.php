<?php

namespace MeuMouse\Joinotify\Telemetry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * ULID generator — the identity that makes a retry harmless.
 *
 * The plugin already retries: a batch that times out stays in the buffer and goes out
 * again on the next run. Without a stable id per event, the service would count the same
 * feature twice, and an adoption metric that overcounts is worse than one that is missing,
 * because it looks right.
 *
 * ULID rather than a plain random id because the first ten characters encode the
 * millisecond it was created: the buffer sorts chronologically without a separate field,
 * and a batch read straight from the database is already in the order things happened.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Ulid {

    /**
     * Crockford base32 — no I, L, O or U, so a value read aloud in a support ticket
     * cannot be transcribed into a different one.
     *
     * @since 2.3.0
     * @var string
     */
    const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';


    /**
     * Generate an identifier for one event.
     *
     * @since 2.3.0
     * @param int|null $timestamp_ms | Milliseconds, for tests. Defaults to now.
     * @return string 26 characters.
     */
    public static function generate( $timestamp_ms = null ) {
        if ( null === $timestamp_ms ) {
            $timestamp_ms = (int) round( microtime( true ) * 1000 );
        }

        return self::encode_time( (int) $timestamp_ms ) . self::encode_randomness();
    }


    /**
     * Encode the timestamp into the first 10 characters.
     *
     * @since 2.3.0
     * @param int $timestamp_ms | Milliseconds since the epoch.
     * @return string
     */
    private static function encode_time( $timestamp_ms ) {
        $out = '';

        for ( $i = 0; $i < 10; $i++ ) {
            $out = self::ALPHABET[ $timestamp_ms % 32 ] . $out;
            $timestamp_ms = (int) ( $timestamp_ms / 32 );
        }

        return $out;
    }


    /**
     * Encode 16 random characters.
     *
     * `wp_rand()` when WordPress is loaded, `mt_rand()` when it is not — this class is
     * exercised by a standalone test harness that never boots WordPress.
     *
     * @since 2.3.0
     * @return string
     */
    private static function encode_randomness() {
        $out = '';

        for ( $i = 0; $i < 16; $i++ ) {
            $index = function_exists('wp_rand') ? wp_rand( 0, 31 ) : mt_rand( 0, 31 );
            $out .= self::ALPHABET[ $index ];
        }

        return $out;
    }
}
