<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Whether free-form text still reaches a contact: the 24-hour customer service window.
 *
 * WhatsApp delivers free text and media only while the window opened by the contact's last
 * message is still open (72 hours when the conversation came from a click-to-WhatsApp ad);
 * outside it, only an approved template goes out. The platform knows every inbound message, so
 * it answers for the window number by number (`GET /contacts/{id}/window?from=`), webhooks or
 * not — unlike Webhook_Handler::window_is_open(), which only knows what this site received.
 *
 * The platform addresses contacts by id, so the phone is looked up once in the contact base
 * (`GET /contacts?q=`, matched on the exact number, since `q` also matches partial digits) and
 * the id is kept. The answer itself is kept for a short while: an open window until shortly
 * before it closes, a closed one only for a couple of minutes, because the contact may write at
 * any moment. An inbound webhook message forgets it at once.
 *
 * Whatever goes wrong on the way — the site is not connected, the platform is unreachable, the
 * key is rate limited — the answer is `unknown`, never a guess: a caller that falls back to a
 * template on anything but `open` is always right.
 *
 * @since 2.6.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Contact_Window {

	/**
	 * Transient prefix holding the platform contact id of a phone.
	 *
	 * @since 2.6.0
	 * @var string
	 */
	const CONTACT_PREFIX = 'joinotify_window_contact_';

	/**
	 * Transient prefix holding the window answers of a phone, one per origin number.
	 *
	 * @since 2.6.0
	 * @var string
	 */
	const STATE_PREFIX = 'joinotify_window_state_';

	/**
	 * Seconds a contact id is kept (30 days).
	 *
	 * @since 2.6.0
	 * @var int
	 */
	const CONTACT_TTL = 2592000;

	/**
	 * Seconds a closed window is trusted.
	 *
	 * @since 2.6.0
	 * @var int
	 */
	const CLOSED_TTL = 120;

	/**
	 * Seconds before the window closes when an open answer stops being served from the cache.
	 *
	 * @since 2.6.0
	 * @var int
	 */
	const OPEN_MARGIN = 60;

	/**
	 * Contacts asked for when looking a phone up.
	 *
	 * `q` matches partial digits, so the exact number may not be the first hit.
	 *
	 * @since 2.6.0
	 * @var int
	 */
	const LOOKUP_LIMIT = 10;


	/**
	 * Read the window of a contact on one origin number.
	 *
	 * @since 2.6.0
	 * @param string $phone  | Contact phone, digits with the country code.
	 * @param string $sender | Origin number in digits; empty uses the first connected sender.
	 * @param int    $now    | Unix time, for tests.
	 * @return array{state:string,expires_at:string,remaining_seconds:int} `state` is `open`,
	 *         `closed` or `unknown`; `expires_at` is ISO 8601 (empty when the contact never wrote
	 *         or the state is unknown).
	 */
	public static function get( $phone, $sender = '', $now = 0 ) {
		$now = $now > 0 ? (int) $now : time();
		$phone = preg_replace( '/\D/', '', is_scalar( $phone ) ? (string) $phone : '' );

		if ( strlen( $phone ) < 8 || ! Helpers::cloud_api_ready() ) {
			return self::answer( 'unknown' );
		}

		$sender = preg_replace( '/\D/', '', is_scalar( $sender ) ? (string) $sender : '' );

		if ( '' === $sender && function_exists( 'joinotify_get_first_sender' ) ) {
			$sender = preg_replace( '/\D/', '', (string) joinotify_get_first_sender() );
		}

		$phone_number_id = '' !== $sender ? (string) Cloud_Client::resolve_phone_number_id( $sender ) : '';

		if ( '' === $phone_number_id ) {
			return self::answer( 'unknown' );
		}

		$cached = self::cached( $phone, $phone_number_id, $now );

		if ( null !== $cached ) {
			return $cached;
		}

		$contact_id = self::contact_id( $phone );

		if ( null === $contact_id ) {
			return self::answer( 'unknown' );
		}

		// A number missing from the contact base never wrote in: the window is closed.
		if ( '' === $contact_id ) {
			return self::remember( $phone, $phone_number_id, self::answer( 'closed' ), $now );
		}

		$envelope = Cloud_Contacts::call( 'GET', '/contacts/' . $contact_id . '/window', array( 'from' => $phone_number_id ) );

		if ( empty( $envelope['ok'] ) ) {
			$status = (int) ( $envelope['status'] ?? 0 );

			// The contact is gone, or out of reach of a key restricted to other numbers.
			if ( 404 === $status || 403 === $status ) {
				delete_transient( self::CONTACT_PREFIX . md5( $phone ) );

				return self::remember( $phone, $phone_number_id, self::answer( 'closed' ), $now );
			}

			return self::answer( 'unknown' );
		}

		return self::remember( $phone, $phone_number_id, self::parse_window( $envelope['data'] ?? array(), $phone_number_id, $now ), $now );
	}


	/**
	 * Forget the window answers of a phone, so the next read asks the platform.
	 *
	 * Called when the contact writes in; the contact id is kept.
	 *
	 * @since 2.6.0
	 * @param string $phone | Contact phone.
	 * @return void
	 */
	public static function forget( $phone ) {
		$phone = preg_replace( '/\D/', '', is_scalar( $phone ) ? (string) $phone : '' );

		if ( '' !== $phone ) {
			delete_transient( self::STATE_PREFIX . md5( $phone ) );
		}
	}


	/**
	 * Read the window of one origin number out of the platform's answer.
	 *
	 * With `from` the platform answers with that number alone; the summary at the top level is
	 * the fallback when the entry is missing.
	 *
	 * @since 2.6.0
	 * @param mixed  $data            | `data` of the window response.
	 * @param string $phone_number_id | Origin phone_number_id.
	 * @param int    $now             | Unix time.
	 * @return array{state:string,expires_at:string,remaining_seconds:int}
	 */
	public static function parse_window( $data, $phone_number_id, $now ) {
		if ( ! is_array( $data ) ) {
			return self::answer( 'unknown' );
		}

		$entry = $data;

		foreach ( (array) ( $data['windows'] ?? array() ) as $window ) {
			if ( is_array( $window ) && (string) ( $window['phoneNumberId'] ?? '' ) === (string) $phone_number_id ) {
				$entry = $window;
				break;
			}
		}

		if ( ! array_key_exists( 'open', $entry ) ) {
			return self::answer( 'unknown' );
		}

		$expires = is_string( $entry['expiresAt'] ?? null ) ? strtotime( $entry['expiresAt'] ) : false;
		$expires_at = false !== $expires ? gmdate( 'c', $expires ) : '';
		$remaining = false !== $expires ? max( 0, $expires - (int) $now ) : max( 0, (int) ( $entry['remainingSeconds'] ?? 0 ) );

		if ( empty( $entry['open'] ) || $remaining <= 0 ) {
			return self::answer( 'closed', $expires_at );
		}

		return self::answer( 'open', $expires_at, $remaining );
	}


	/**
	 * Pick the contact whose number is exactly the phone.
	 *
	 * The phone is compared with both the stored number and the WhatsApp id, which differ for
	 * the Brazilian mobiles WhatsApp still addresses without the ninth digit.
	 *
	 * @since 2.6.0
	 * @param mixed  $contacts | `data` of the contact listing.
	 * @param string $phone    | Phone in digits.
	 * @return string Contact id, or empty when no contact has the number.
	 */
	public static function match_contact( $contacts, $phone ) {
		foreach ( is_array( $contacts ) ? $contacts : array() as $contact ) {
			if ( ! is_array( $contact ) || ! Cloud_Contacts::is_id( $contact['id'] ?? null ) ) {
				continue;
			}

			foreach ( array( 'phone', 'waId' ) as $field ) {
				if ( $phone === preg_replace( '/\D/', '', (string) ( $contact[ $field ] ?? '' ) ) ) {
					return (string) $contact['id'];
				}
			}
		}

		return '';
	}


	/**
	 * Platform contact id of a phone, looked up once.
	 *
	 * @since 2.6.0
	 * @param string $phone | Phone in digits.
	 * @return string|null The id, empty when nobody has the number, null when the platform did not answer.
	 */
	protected static function contact_id( $phone ) {
		$key = self::CONTACT_PREFIX . md5( $phone );
		$known = get_transient( $key );

		if ( is_string( $known ) && Cloud_Contacts::is_id( $known ) ) {
			return $known;
		}

		$envelope = Cloud_Contacts::call( 'GET', '/contacts', array(
			'q' => $phone,
			'limit' => self::LOOKUP_LIMIT,
		) );

		if ( empty( $envelope['ok'] ) ) {
			return null;
		}

		$id = self::match_contact( $envelope['data'] ?? array(), $phone );

		// A missing contact is not kept: the number may write in a minute from now.
		if ( '' !== $id ) {
			set_transient( $key, $id, self::CONTACT_TTL );
		}

		return $id;
	}


	/**
	 * Answer kept for a phone and an origin number, while it is still fresh.
	 *
	 * @since 2.6.0
	 * @param string $phone           | Phone in digits.
	 * @param string $phone_number_id | Origin phone_number_id.
	 * @param int    $now             | Unix time.
	 * @return array|null
	 */
	protected static function cached( $phone, $phone_number_id, $now ) {
		$states = get_transient( self::STATE_PREFIX . md5( $phone ) );
		$state = is_array( $states ) ? ( $states[ $phone_number_id ] ?? null ) : null;

		if ( ! is_array( $state ) || (int) ( $state['until'] ?? 0 ) <= $now ) {
			return null;
		}

		$expires = '' !== (string) ( $state['expires_at'] ?? '' ) ? strtotime( (string) $state['expires_at'] ) : false;

		if ( 'open' === ( $state['state'] ?? '' ) && false !== $expires ) {
			return self::answer( 'open', (string) $state['expires_at'], max( 0, $expires - $now ) );
		}

		return self::answer( (string) ( $state['state'] ?? 'closed' ), (string) ( $state['expires_at'] ?? '' ) );
	}


	/**
	 * Keep an answer for a phone and an origin number, and return it.
	 *
	 * @since 2.6.0
	 * @param string $phone           | Phone in digits.
	 * @param string $phone_number_id | Origin phone_number_id.
	 * @param array  $answer          | Answer to keep.
	 * @param int    $now             | Unix time.
	 * @return array The answer.
	 */
	protected static function remember( $phone, $phone_number_id, $answer, $now ) {
		$ttl = 'open' === $answer['state'] ? $answer['remaining_seconds'] - self::OPEN_MARGIN : self::CLOSED_TTL;

		if ( $ttl <= 0 ) {
			return $answer;
		}

		$key = self::STATE_PREFIX . md5( $phone );
		$states = get_transient( $key );
		$states = is_array( $states ) ? $states : array();

		$states[ $phone_number_id ] = array(
			'state' => $answer['state'],
			'expires_at' => $answer['expires_at'],
			'until' => $now + $ttl,
		);

		$longest = 0;

		foreach ( $states as $state ) {
			$longest = max( $longest, (int) ( $state['until'] ?? 0 ) - $now );
		}

		set_transient( $key, $states, max( 1, $longest ) );

		return $answer;
	}


	/**
	 * Build an answer.
	 *
	 * @since 2.6.0
	 * @param string $state             | `open`, `closed` or `unknown`.
	 * @param string $expires_at        | ISO 8601 close time, or empty.
	 * @param int    $remaining_seconds | Seconds until it closes.
	 * @return array{state:string,expires_at:string,remaining_seconds:int}
	 */
	protected static function answer( $state, $expires_at = '', $remaining_seconds = 0 ) {
		return array(
			'state' => $state,
			'expires_at' => (string) $expires_at,
			'remaining_seconds' => 'open' === $state ? (int) $remaining_seconds : 0,
		);
	}
}
