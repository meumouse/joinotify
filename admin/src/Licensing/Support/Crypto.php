<?php

namespace MeuMouse\Joinotify\Licensing\Support;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Symmetric payload encryption used by the legacy licensing protocol.
 *
 * Shared by the driver, which speaks this format on the wire, and by the local
 * state storage, which reuses it to keep the cached license object opaque in the
 * options table. Both sides derive the key and IV the same way, so the algorithm
 * lives in one place.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Crypto {

    /**
     * Cipher used on both ends of the legacy protocol.
     *
     * @since 2.1.0
     * @var string
     */
    const METHOD = 'aes-256-cbc';

    /**
     * Encrypt a payload.
     *
     * The plaintext is padded with random digits on both ends, mirroring the
     * server, so two identical payloads never produce the same ciphertext.
     *
     * @since 2.1.0
     * @param string $plaintext | Value to encrypt
     * @param string $password | Shared secret
     * @return string Base64 encoded ciphertext
     */
    public static function encrypt( $plaintext, $password ) {
        $plaintext = wp_rand( 10, 99 ) . $plaintext . wp_rand( 10, 99 );

        return base64_encode( openssl_encrypt( $plaintext, self::METHOD, self::key( $password ), OPENSSL_RAW_DATA, self::iv( $password ) ) );
    }


    /**
     * Decrypt a payload.
     *
     * @since 2.1.0
     * @param mixed $encrypted | Base64 encoded ciphertext
     * @param string $password | Shared secret
     * @return string Plaintext, or an empty string when decryption fails
     */
    public static function decrypt( $encrypted, $password ) {
        if ( ! is_string( $encrypted ) ) {
            if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
                Logger::register_log( 'Licensing decrypt: input is not a string: ' . print_r( $encrypted, true ), 'ERROR' );
            }

            return '';
        }

        $plaintext = openssl_decrypt( base64_decode( $encrypted ), self::METHOD, self::key( $password ), OPENSSL_RAW_DATA, self::iv( $password ) );

        if ( false === $plaintext ) {
            if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
                Logger::register_log( 'Licensing decrypt: failed to decrypt payload.', 'ERROR' );
            }

            return '';
        }

        // Strip the random padding digits the encrypt side added.
        return substr( $plaintext, 2, -2 );
    }


    /**
     * Derive the encryption key.
     *
     * @since 2.1.0
     * @param string $password | Shared secret
     * @return string
     */
    protected static function key( $password ) {
        return substr( hash( 'sha256', $password, true ), 0, 32 );
    }


    /**
     * Derive the initialization vector.
     *
     * @since 2.1.0
     * @param string $password | Shared secret
     * @return string
     */
    protected static function iv( $password ) {
        return substr( strtoupper( md5( $password ) ), 0, 16 );
    }
}
