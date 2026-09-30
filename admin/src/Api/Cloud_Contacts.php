<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Read and write the account's contact base on the Joinotify platform.
 *
 * The contact base lives on the platform, never in WordPress: the "Audiences & Contacts" screen
 * asks for it through the plugin's own REST routes, which reach the platform through this class
 * with the site's API key. The key never leaves the server.
 *
 * Every call answers with the same envelope, whatever happened on the way:
 *
 *     array(
 *         'ok'     => bool,
 *         'status' => int,         // HTTP status, 0 when the platform was not reached
 *         'data'   => mixed,       // the body's `data`, or the raw body of a non-JSON answer
 *         'meta'   => array,       // `total`, `limit` and `offset` of a list
 *         'error'  => array|null,  // `type`, `message`, `issues`, `retry_after`, `action_url`, …
 *     )
 *
 * Program against `error.type`, which mirrors the platform's stable codes; `error.message` is
 * already written for the person reading the screen.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Cloud_Contacts {

	/**
	 * Seconds a platform call may take.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const TIMEOUT = 20;

	/**
	 * Seconds the definitions (fields, tags, filter schema) are cached.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const CACHE_TTL = 600;

	/**
	 * Seconds the capability probe is trusted.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const MODE_TTL = 300;

	/**
	 * Prefix of every transient this class writes.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const CACHE_PREFIX = 'joinotify_contacts_';

	/**
	 * Cached definitions, flushed together.
	 *
	 * @since 2.5.0
	 * @var string[]
	 */
	const CACHE_KEYS = array( 'mode', 'fields', 'fields_archived', 'tags', 'schema' );

	/**
	 * Every operation is allowed.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const MODE_FULL = 'full';

	/**
	 * The key is restricted to some numbers: it lists contacts but cannot touch the base.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const MODE_READ_ONLY = 'read_only';

	/**
	 * The key has no access to the contact base at all.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const MODE_FORBIDDEN = 'forbidden';

	/**
	 * The account is blocked by billing (HTTP 402).
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const MODE_BLOCKED = 'blocked';

	/**
	 * The key was revoked or is invalid (HTTP 401).
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const MODE_UNAUTHORIZED = 'unauthorized';

	/**
	 * The platform could not be reached; try again later.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const MODE_UNREACHABLE = 'unreachable';

	/**
	 * The site is not connected to the platform.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const MODE_DISCONNECTED = 'disconnected';

	/**
	 * What a platform id looks like. Anything else never reaches a URL path.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';


	/**
	 * Call the platform and normalize the answer.
	 *
	 * @since 2.5.0
	 * @param string $method  HTTP method.
	 * @param string $path    Path beginning with a slash (e.g. '/contacts').
	 * @param array  $query   Query arguments; empty values are dropped.
	 * @param mixed  $body    JSON body, or null.
	 * @param array  $headers Extra headers (e.g. an `Idempotency-Key`).
	 * @return array The envelope described on the class.
	 */
	public static function call( $method, $path, $query = array(), $body = null, $headers = array() ) {
		if ( ! Helpers::cloud_api_ready() ) {
			return self::failure( 0, 'not_connected' );
		}

		$query_string = self::query_string( $query );

		if ( '' !== $query_string ) {
			$path .= '?' . $query_string;
		}

		$response = Cloud_Client::request( $method, $path, $body, self::TIMEOUT, $headers );

		if ( is_wp_error( $response ) ) {
			return self::failure( 0, 'network_error', $response->get_error_message() );
		}

		return self::parse(
			(int) wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_body( $response ),
			(string) wp_remote_retrieve_header( $response, 'retry-after' ),
			(string) wp_remote_retrieve_header( $response, 'content-type' )
		);
	}


	/**
	 * Turn a platform answer into the envelope.
	 *
	 * @since 2.5.0
	 * @param int    $status       HTTP status.
	 * @param string $raw          Raw body.
	 * @param string $retry_after  Retry-After header.
	 * @param string $content_type Content-Type header.
	 * @return array The envelope described on the class.
	 */
	public static function parse( $status, $raw, $retry_after = '', $content_type = '' ) {
		$status = (int) $status;
		$is_json = '' === $content_type || false !== stripos( $content_type, 'json' );
		$body = $is_json ? json_decode( (string) $raw, true ) : null;

		if ( $status >= 200 && $status < 300 ) {
			if ( ! $is_json ) {
				return self::success( $status, (string) $raw );
			}

			$body = is_array( $body ) ? $body : array();
			$meta = array();

			foreach ( array( 'total', 'limit', 'offset' ) as $key ) {
				if ( isset( $body[ $key ] ) && is_numeric( $body[ $key ] ) ) {
					$meta[ $key ] = (int) $body[ $key ];
				}
			}

			return self::success( $status, array_key_exists( 'data', $body ) ? $body['data'] : $body, $meta );
		}

		$error = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
		$type = isset( $error['type'] ) && is_string( $error['type'] ) && '' !== $error['type'] ? $error['type'] : self::type_for_status( $status );
		$message = isset( $error['message'] ) && is_string( $error['message'] ) ? $error['message'] : '';
		$envelope = self::failure( $status, $type, $message );

		$envelope['error']['issues'] = self::issues( isset( $error['issues'] ) ? $error['issues'] : array() );
		$envelope['error']['retry_after'] = is_numeric( $retry_after ) ? max( 0, (int) $retry_after ) : 0;

		if ( isset( $error['reason'] ) && is_string( $error['reason'] ) ) {
			$envelope['error']['reason'] = $error['reason'];
		}

		if ( isset( $error['action']['url'] ) && is_string( $error['action']['url'] ) ) {
			$envelope['error']['action_url'] = $error['action']['url'];
		}

		if ( isset( $error['contactId'] ) && is_string( $error['contactId'] ) ) {
			$envelope['error']['contact_id'] = $error['contactId'];
		}

		if ( isset( $error['limit'] ) && is_numeric( $error['limit'] ) ) {
			$envelope['error']['limit'] = (int) $error['limit'];
		}

		return $envelope;
	}


	/**
	 * Build a success envelope.
	 *
	 * @since 2.5.0
	 * @param int   $status HTTP status.
	 * @param mixed $data   Payload.
	 * @param array $meta   List metadata.
	 * @return array
	 */
	public static function success( $status, $data, $meta = array() ) {
		return array(
			'ok' => true,
			'status' => (int) $status,
			'data' => $data,
			'meta' => $meta,
			'error' => null,
		);
	}


	/**
	 * Build a failure envelope with a message a site owner can act on.
	 *
	 * @since 2.5.0
	 * @param int    $status  HTTP status, 0 when the platform was not reached.
	 * @param string $type    Stable error code.
	 * @param string $message The platform's message, used when the code has no better one.
	 * @return array
	 */
	public static function failure( $status, $type, $message = '' ) {
		return array(
			'ok' => false,
			'status' => (int) $status,
			'data' => null,
			'meta' => array(),
			'error' => array(
				'type' => (string) $type,
				'message' => self::message_for( (string) $type, (int) $status, (string) $message ),
				'issues' => array(),
				'retry_after' => 0,
			),
		);
	}


	/**
	 * The code to use when the platform sent none.
	 *
	 * @since 2.5.0
	 * @param int $status HTTP status.
	 * @return string
	 */
	public static function type_for_status( $status ) {
		$types = array(
			400 => 'invalid_request',
			401 => 'authentication',
			402 => 'payment_required',
			403 => 'forbidden',
			404 => 'not_found',
			409 => 'conflict',
			422 => 'invalid_request',
			429 => 'rate_limit',
		);

		if ( isset( $types[ $status ] ) ) {
			return $types[ $status ];
		}

		return $status >= 500 ? 'server_error' : 'unknown_error';
	}


	/**
	 * A message for the screen: our own for the codes a site owner meets, the platform's otherwise.
	 *
	 * @since 2.5.0
	 * @param string $type    Stable error code.
	 * @param int    $status  HTTP status.
	 * @param string $message The platform's message.
	 * @return string
	 */
	public static function message_for( $type, $status, $message = '' ) {
		switch ( $type ) {
			case 'not_connected':
				return __( 'Connect this site to Joinotify to manage your contacts.', 'joinotify' );
			case 'network_error':
				return __( 'Could not reach the Joinotify platform. Check your connection and try again.', 'joinotify' );
			case 'authentication':
			case 'unauthorized':
				return __( 'The API key of this site was revoked or is invalid. Connect the site to Joinotify again.', 'joinotify' );
			case 'payment_required':
				return __( 'Your Joinotify account needs attention before it can be used. Open the panel to resolve the billing.', 'joinotify' );
			case 'forbidden':
			case 'key_permission_denied':
			case 'site_key_required':
				return __( 'The API key of this site is not allowed to do this. Operations on the whole contact base need a key that is not restricted to some numbers.', 'joinotify' );
			case 'rate_limit':
				return __( 'Too many requests to the Joinotify platform. Wait a moment and try again.', 'joinotify' );
			case 'not_found':
				return __( 'This record no longer exists on the Joinotify platform.', 'joinotify' );
			case 'contact_exists':
				return __( 'A contact with this phone number already exists.', 'joinotify' );
			case 'contact_limit_reached':
				return __( 'Your plan reached its contact limit. Upgrade the plan to add more contacts.', 'joinotify' );
		}

		if ( '' !== $message ) {
			return $message;
		}

		if ( 'invalid_request' === $type ) {
			return __( 'The Joinotify platform refused the data sent. Review the fields and try again.', 'joinotify' );
		}

		/* translators: %d: HTTP status code */
		return sprintf( __( 'The Joinotify platform returned an error (HTTP %d).', 'joinotify' ), (int) $status );
	}


	/**
	 * Flatten the validation details into `{ field, message }` pairs.
	 *
	 * The platform reports each failing value with its path — an array of keys, or a dotted
	 * string — and a message. Both shapes land here as a dotted `field`.
	 *
	 * @since 2.5.0
	 * @param mixed $issues The body's `error.issues`.
	 * @return array<int,array{field:string,message:string}>
	 */
	public static function issues( $issues ) {
		if ( ! is_array( $issues ) ) {
			return array();
		}

		$flat = array();

		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}

			$field = '';

			if ( isset( $issue['path'] ) && is_array( $issue['path'] ) ) {
				$field = implode( '.', array_map( 'strval', $issue['path'] ) );
			} elseif ( isset( $issue['path'] ) && is_scalar( $issue['path'] ) ) {
				$field = (string) $issue['path'];
			} elseif ( isset( $issue['field'] ) && is_scalar( $issue['field'] ) ) {
				$field = (string) $issue['field'];
			}

			$flat[] = array(
				'field' => $field,
				'message' => isset( $issue['message'] ) && is_scalar( $issue['message'] ) ? (string) $issue['message'] : '',
			);
		}

		return $flat;
	}


	/**
	 * Build a query string from the arguments that carry a value.
	 *
	 * @since 2.5.0
	 * @param array $query Query arguments.
	 * @return string
	 */
	public static function query_string( $query ) {
		if ( ! is_array( $query ) ) {
			return '';
		}

		$clean = array();

		foreach ( $query as $key => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}

			if ( null === $value || '' === $value || ! is_scalar( $value ) ) {
				continue;
			}

			$clean[ $key ] = (string) $value;
		}

		return http_build_query( $clean, '', '&', PHP_QUERY_RFC3986 );
	}


	/**
	 * Whether a value can be used as a platform id in a URL path.
	 *
	 * @since 2.5.0
	 * @param mixed $id Candidate id.
	 * @return bool
	 */
	public static function is_id( $id ) {
		return is_string( $id ) && 1 === preg_match( self::ID_PATTERN, $id );
	}


	/**
	 * Convert a page number and size into the platform's offset.
	 *
	 * @since 2.5.0
	 * @param int $page     1-based page.
	 * @param int $per_page Page size, 1 to 200.
	 * @return array{limit:int,offset:int}
	 */
	public static function page_window( $page, $per_page ) {
		$per_page = max( 1, min( 200, (int) $per_page ) );
		$page = max( 1, (int) $page );

		return array(
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);
	}


	/**
	 * Build the pagination block the admin tables read from a list envelope.
	 *
	 * @since 2.5.0
	 * @param array $envelope List envelope.
	 * @param int   $page     Requested page.
	 * @param int   $per_page Requested size.
	 * @return array{current_page:int,per_page:int,total_items:int,total_pages:int}
	 */
	public static function pagination( $envelope, $page, $per_page ) {
		$window = self::page_window( $page, $per_page );
		$total = isset( $envelope['meta']['total'] ) ? (int) $envelope['meta']['total'] : 0;

		return array(
			'current_page' => (int) floor( $window['offset'] / $window['limit'] ) + 1,
			'per_page' => $window['limit'],
			'total_items' => $total,
			'total_pages' => max( 1, (int) ceil( $total / $window['limit'] ) ),
		);
	}


	/**
	 * Find out what the site's key may do with the contact base.
	 *
	 * A key restricted to some numbers lists only the contacts that talked to those numbers and
	 * gets 403 on everything that belongs to the whole base. The suppression list is one of those
	 * base-wide reads, so it tells a full key from a restricted one without writing anything.
	 *
	 * @since 2.5.0
	 * @param bool $force Skip the cached answer.
	 * @return array{mode:string,message:string,action_url:string}
	 */
	public static function capability( $force = false ) {
		if ( ! Helpers::cloud_api_ready() ) {
			return self::capability_result( self::MODE_DISCONNECTED, self::failure( 0, 'not_connected' ) );
		}

		$key = self::CACHE_PREFIX . 'mode';

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) && isset( $cached['mode'] ) ) {
				return $cached;
			}
		}

		$result = self::classify_probe( self::call( 'GET', '/suppressions', array( 'limit' => 1 ) ), function () {
			return self::call( 'GET', '/contacts', array( 'limit' => 1 ) );
		} );

		// An outage is not a verdict: ask again soon instead of remembering it.
		set_transient( $key, $result, self::MODE_UNREACHABLE === $result['mode'] ? 60 : self::MODE_TTL );

		return $result;
	}


	/**
	 * Decide the mode from the base-wide probe and, when it was refused, a plain listing.
	 *
	 * @since 2.5.0
	 * @param array    $probe   Envelope of the base-wide read.
	 * @param callable $listing Returns the envelope of a contact listing.
	 * @return array{mode:string,message:string,action_url:string}
	 */
	public static function classify_probe( $probe, $listing ) {
		if ( $probe['ok'] ) {
			return self::capability_result( self::MODE_FULL, $probe );
		}

		switch ( $probe['status'] ) {
			case 401:
				return self::capability_result( self::MODE_UNAUTHORIZED, $probe );
			case 402:
				return self::capability_result( self::MODE_BLOCKED, $probe );
			case 403:
				$list = call_user_func( $listing );

				return $list['ok'] ? self::capability_result( self::MODE_READ_ONLY, $probe ) : self::capability_result( self::MODE_FORBIDDEN, $list );
		}

		return self::capability_result( self::MODE_UNREACHABLE, $probe );
	}


	/**
	 * Shape a capability answer.
	 *
	 * @since 2.5.0
	 * @param string $mode     One of the MODE_* constants.
	 * @param array  $envelope Envelope that decided it.
	 * @return array{mode:string,message:string,action_url:string}
	 */
	private static function capability_result( $mode, $envelope ) {
		$message = '';

		if ( self::MODE_READ_ONLY === $mode ) {
			$message = __( 'The API key of this site is restricted to some numbers: it lists only the contacts that talked to them and cannot change the contact base.', 'joinotify' );
		} elseif ( self::MODE_FULL !== $mode && ! empty( $envelope['error']['message'] ) ) {
			$message = $envelope['error']['message'];
		}

		return array(
			'mode' => $mode,
			'message' => $message,
			'action_url' => isset( $envelope['error']['action_url'] ) ? (string) $envelope['error']['action_url'] : '',
		);
	}


	/**
	 * Read a definition through the cache. Only successful answers are kept.
	 *
	 * @since 2.5.0
	 * @param string   $name  Cache name, one of CACHE_KEYS.
	 * @param callable $fetch Returns the envelope.
	 * @param bool     $force Skip the cached answer.
	 * @return array
	 */
	public static function cached( $name, $fetch, $force = false ) {
		$key = self::CACHE_PREFIX . $name;

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) && isset( $cached['ok'] ) ) {
				return $cached;
			}
		}

		$envelope = call_user_func( $fetch );

		if ( $envelope['ok'] ) {
			set_transient( $key, $envelope, self::CACHE_TTL );
		}

		return $envelope;
	}


	/**
	 * Forget every cached definition, e.g. after a write or a new connection.
	 *
	 * @since 2.5.0
	 * @param string[] $names Names to forget; all of them when empty.
	 * @return void
	 */
	public static function flush_cache( $names = array() ) {
		foreach ( empty( $names ) ? self::CACHE_KEYS : (array) $names as $name ) {
			delete_transient( self::CACHE_PREFIX . $name );
		}
	}
}
