<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Encryption at rest for the Joinotify API key.
 *
 * The key is a live sending credential, and the options table travels: it is in every
 * backup, every staging copy and every SQL dump handed to support. Stored through here
 * it reads `enc:v1:` followed by base64( nonce . ciphertext ), sealed with libsodium's
 * `crypto_secretbox` under a key derived from the site's `AUTH_KEY`/`AUTH_SALT`
 * (`wp_salt( 'auth' )`), which live in `wp-config.php` and not in the database. A dump
 * alone no longer hands the credential over.
 *
 * Reading stays transparent: a value without the prefix is a key stored by an earlier
 * version, and is returned as it is. Without the sodium extension the value is kept in
 * the clear, as before, rather than refusing to connect. Changing the auth salts makes
 * a sealed value unreadable — the site then reads as disconnected and the key has to be
 * pasted again.
 *
 * @since 2.6.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Token_Vault {

	/**
	 * Prefix of a sealed value. The version leaves room for another scheme later.
	 *
	 * @since 2.6.0
	 * @var string
	 */
	const PREFIX = 'enc:v1:';

	/**
	 * Context mixed into the key, so the same salts never yield the same key elsewhere.
	 *
	 * @since 2.6.0
	 * @var string
	 */
	const CONTEXT = 'joinotify-cloud-token';


	/**
	 * Whether libsodium's secretbox is available on this PHP.
	 *
	 * @since 2.6.0
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'sodium_crypto_secretbox' )
			&& function_exists( 'sodium_crypto_secretbox_open' )
			&& function_exists( 'sodium_crypto_generichash' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' );
	}


	/**
	 * Whether a stored value is sealed.
	 *
	 * @since 2.6.0
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		return is_string( $value ) && str_starts_with( trim( $value ), self::PREFIX );
	}


	/**
	 * Seal a value for storage.
	 *
	 * An empty value stays empty and a value that is already sealed is returned untouched,
	 * so a settings save that hands back what it read never seals it twice. Without sodium,
	 * or if sealing fails, the value is returned in the clear.
	 *
	 * @since 2.6.0
	 * @param mixed $plain Value to seal.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		$plain = is_scalar( $plain ) ? trim( (string) $plain ) : '';

		if ( '' === $plain || self::is_encrypted( $plain ) || ! self::is_available() ) {
			return $plain;
		}

		try {
			$key = self::key();
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$sealed = sodium_crypto_secretbox( $plain, $nonce, $key );

			if ( function_exists( 'sodium_memzero' ) ) {
				sodium_memzero( $key );
			}

			return self::PREFIX . base64_encode( $nonce . $sealed );
		} catch ( \Throwable $e ) {
			return $plain;
		}
	}


	/**
	 * Read a stored value back.
	 *
	 * A value without the prefix is returned as it is (a key stored before 2.6.0). A sealed
	 * value that cannot be opened — sodium missing, the auth salts changed, the value
	 * tampered with — reads as empty, which the plugin treats as "not connected".
	 *
	 * @since 2.6.0
	 * @param mixed $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = is_scalar( $stored ) ? trim( (string) $stored ) : '';

		if ( ! self::is_encrypted( $stored ) ) {
			return $stored;
		}

		if ( ! self::is_available() ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		try {
			$key = self::key();
			$plain = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				$key
			);

			if ( function_exists( 'sodium_memzero' ) ) {
				sodium_memzero( $key );
			}
		} catch ( \Throwable $e ) {
			return '';
		}

		return is_string( $plain ) ? $plain : '';
	}


	/**
	 * Derive the secretbox key from the site's auth salts.
	 *
	 * @since 2.6.0
	 * @return string Raw key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES long.
	 */
	protected static function key() {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . self::CONTEXT, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
