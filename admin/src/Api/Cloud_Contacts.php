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
			case 'contact_field_exists':
				return __( 'A custom field with this key already exists, possibly archived.', 'joinotify' );
			case 'contact_field_limit_reached':
				return __( 'Your plan reached its custom field limit, archived fields included. Erase a field you no longer need or upgrade the plan.', 'joinotify' );
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


	// ── Contacts ────────────────────────────────────────────────────────────────────────────


	/**
	 * Values the listing filters accept.
	 *
	 * @since 2.5.0
	 * @var array<string,string[]>
	 */
	const FILTER_ENUMS = array(
		'optInStatus' => array( 'opted_in', 'opted_out', 'unknown' ),
		'source' => array( 'manual', 'import', 'api', 'inbound', 'flow', 'webhook', 'site' ),
		'sort' => array( 'recent', 'name', 'last_inbound' ),
	);


	/**
	 * List contacts, one page at a time.
	 *
	 * @since 2.5.0
	 * @param array $args `page`, `per_page`, `sort` and the filters read by list_filters().
	 * @return array Envelope; `data` holds the contacts and `meta` the total.
	 */
	public static function list_contacts( $args = array() ) {
		$query = self::page_window( isset( $args['page'] ) ? $args['page'] : 1, isset( $args['per_page'] ) ? $args['per_page'] : 25 );
		$sort = isset( $args['sort'] ) ? (string) $args['sort'] : '';

		if ( in_array( $sort, self::FILTER_ENUMS['sort'], true ) ) {
			$query['sort'] = $sort;
		}

		return self::call( 'GET', '/contacts', array_merge( $query, self::list_filters( $args ) ) );
	}


	/**
	 * Translate the screen's filters into the platform's query, dropping what is not valid.
	 *
	 * @since 2.5.0
	 * @param array $args `search`, `tag_id`, `opt_in_status`, `source`, `audience_id`, `filter`.
	 * @return array<string,string>
	 */
	public static function list_filters( $args ) {
		$filters = array();
		$search = isset( $args['search'] ) ? self::text( $args['search'], 120 ) : '';

		if ( '' !== $search ) {
			$filters['q'] = $search;
		}

		if ( isset( $args['tag_id'] ) && self::is_id( $args['tag_id'] ) ) {
			$filters['tagId'] = $args['tag_id'];
		}

		if ( isset( $args['opt_in_status'] ) && in_array( $args['opt_in_status'], self::FILTER_ENUMS['optInStatus'], true ) ) {
			$filters['optInStatus'] = $args['opt_in_status'];
		}

		if ( isset( $args['source'] ) && in_array( $args['source'], self::FILTER_ENUMS['source'], true ) ) {
			$filters['source'] = $args['source'];
		}

		if ( isset( $args['audience_id'] ) && self::is_id( $args['audience_id'] ) ) {
			$filters['audienceId'] = $args['audience_id'];
		}

		if ( ! empty( $args['filter'] ) ) {
			$filter = is_string( $args['filter'] ) ? json_decode( $args['filter'], true ) : $args['filter'];

			if ( is_array( $filter ) && isset( $filter['op'], $filter['rules'] ) ) {
				$json = json_encode( $filter );

				if ( is_string( $json ) && strlen( $json ) <= 20000 ) {
					$filters['filter'] = $json;
				}
			}
		}

		return $filters;
	}


	/**
	 * Read one contact, with the suppression rows that match it.
	 *
	 * @since 2.5.0
	 * @param string $id Contact id.
	 * @return array Envelope.
	 */
	public static function get_contact( $id ) {
		return self::call( 'GET', '/contacts/' . $id );
	}


	/**
	 * Read what happened to a contact, newest first.
	 *
	 * @since 2.5.0
	 * @param string $id       Contact id.
	 * @param int    $page     1-based page.
	 * @param int    $per_page Page size.
	 * @return array Envelope.
	 */
	public static function contact_activity( $id, $page = 1, $per_page = 25 ) {
		return self::call( 'GET', '/contacts/' . $id . '/activity', self::page_window( $page, $per_page ) );
	}


	/**
	 * Create a contact, or update the one that already has the phone when `$upsert` is set.
	 *
	 * @since 2.5.0
	 * @param array $data   Payload built by contact_payload().
	 * @param bool  $upsert Update an existing contact instead of answering 409.
	 * @return array Envelope.
	 */
	public static function create_contact( $data, $upsert = false ) {
		return self::call( 'POST', '/contacts', $upsert ? array( 'upsert' => true ) : array(), (object) $data );
	}


	/**
	 * Change a contact. Absent keys stay as they are, `null` clears a value and `tagIds` is the
	 * complete set of tags.
	 *
	 * @since 2.5.0
	 * @param string $id   Contact id.
	 * @param array  $data Payload built by contact_payload().
	 * @return array Envelope.
	 */
	public static function update_contact( $id, $data ) {
		return self::call( 'PATCH', '/contacts/' . $id, array(), (object) $data );
	}


	/**
	 * Erase a contact for good, with its conversations, tags and history.
	 *
	 * @since 2.5.0
	 * @param string $id Contact id.
	 * @return array Envelope.
	 */
	public static function delete_contact( $id ) {
		return self::call( 'DELETE', '/contacts/' . $id );
	}


	/**
	 * Record the contact's marketing consent, with how it was obtained.
	 *
	 * @since 2.5.0
	 * @param string $id       Contact id.
	 * @param string $evidence How the consent was obtained, 3 to 500 characters.
	 * @return array Envelope.
	 */
	public static function opt_in( $id, $evidence ) {
		return self::call( 'POST', '/contacts/' . $id . '/opt-in', array(), array( 'evidence' => self::text( $evidence, 500 ) ) );
	}


	/**
	 * Withdraw the contact's consent and add its phone to the suppression list.
	 *
	 * @since 2.5.0
	 * @param string $id     Contact id.
	 * @param string $reason Optional reason.
	 * @return array Envelope.
	 */
	public static function opt_out( $id, $reason = '' ) {
		$reason = self::text( $reason, 500 );

		return self::call( 'POST', '/contacts/' . $id . '/opt-out', array(), '' === $reason ? (object) array() : array( 'reason' => $reason ) );
	}


	/**
	 * The whole base, or what the filters select, as CSV.
	 *
	 * @since 2.5.0
	 * @param array $args Filters read by list_filters().
	 * @return array Envelope; `data` holds the CSV text.
	 */
	public static function export_contacts( $args = array() ) {
		return self::call( 'GET', '/contacts/export', self::list_filters( $args ) );
	}


	/**
	 * Everything the account keeps about a contact (the LGPD access request), as JSON.
	 *
	 * @since 2.5.0
	 * @param string $id Contact id.
	 * @return array Envelope.
	 */
	public static function export_contact( $id ) {
		return self::call( 'GET', '/contacts/' . $id . '/export' );
	}


	/**
	 * Keep only what a contact write may carry, in the platform's shape.
	 *
	 * On create, empty values are dropped. On update, a key that is present but empty becomes
	 * `null`, which is how the platform clears a value; keys that are absent stay absent.
	 *
	 * @since 2.5.0
	 * @param array  $input Values typed on the screen, in the platform's camelCase.
	 * @param string $mode  'create' or 'update'.
	 * @return array
	 */
	public static function contact_payload( $input, $mode = 'create' ) {
		$input = is_array( $input ) ? $input : array();
		$update = 'update' === $mode;
		$payload = array();
		$limits = array(
			'phone' => 40,
			'firstName' => 80,
			'lastName' => 80,
			'email' => 254,
			'timezone' => 64,
		);

		foreach ( $limits as $key => $limit ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = self::text( $input[ $key ], $limit );

			if ( '' !== $value ) {
				$payload[ $key ] = $value;
			} elseif ( $update ) {
				$payload[ $key ] = null;
			}
		}

		if ( array_key_exists( 'locale', $input ) ) {
			$locale = self::text( $input['locale'], 10 );

			if ( 1 === preg_match( '/^[a-z]{2}(-[A-Z]{2})?$/', $locale ) ) {
				$payload['locale'] = $locale;
			} elseif ( $update && '' === $locale ) {
				$payload['locale'] = null;
			}
		}

		if ( isset( $input['defaultCountry'] ) && is_string( $input['defaultCountry'] ) && 1 === preg_match( '/^[A-Z]{2}$/', $input['defaultCountry'] ) ) {
			$payload['defaultCountry'] = $input['defaultCountry'];
		}

		if ( isset( $input['attributes'] ) && is_array( $input['attributes'] ) ) {
			$attributes = self::attributes( $input['attributes'], $update );

			if ( ! empty( $attributes ) ) {
				$payload['attributes'] = $attributes;
			}
		}

		if ( array_key_exists( 'tagIds', $input ) && is_array( $input['tagIds'] ) ) {
			$tags = array_values( array_unique( array_filter( $input['tagIds'], array( __CLASS__, 'is_id' ) ) ) );

			// On update the list is the complete set, so an empty one removes every tag.
			if ( $update || ! empty( $tags ) ) {
				$payload['tagIds'] = array_slice( $tags, 0, 50 );
			}
		}

		if ( ! $update && isset( $input['optIn']['evidence'] ) ) {
			$evidence = self::text( $input['optIn']['evidence'], 500 );

			if ( strlen( $evidence ) >= 3 ) {
				$payload['optIn'] = array( 'evidence' => $evidence );
			}
		}

		return $payload;
	}


	/**
	 * Custom field values: a key per field, a scalar or a list of strings per value.
	 *
	 * @since 2.5.0
	 * @param array $attributes Raw values by field key.
	 * @param bool  $update     Empty values become `null` (clear) instead of being dropped.
	 * @return array
	 */
	public static function attributes( $attributes, $update = false ) {
		$clean = array();

		foreach ( $attributes as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z0-9_.-]{1,64}$/', $key ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$list = array();

				foreach ( $value as $item ) {
					if ( is_scalar( $item ) && '' !== self::text( $item, 200 ) ) {
						$list[] = self::text( $item, 200 );
					}
				}

				if ( ! empty( $list ) ) {
					$clean[ $key ] = array_values( array_unique( $list ) );
				} elseif ( $update ) {
					$clean[ $key ] = null;
				}

				continue;
			}

			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$clean[ $key ] = $value;
				continue;
			}

			$text = null === $value ? '' : self::text( $value, 1000 );

			if ( '' !== $text ) {
				$clean[ $key ] = $text;
			} elseif ( $update ) {
				$clean[ $key ] = null;
			}
		}

		return $clean;
	}


	/**
	 * Plain text: no markup, no control characters, trimmed and capped.
	 *
	 * @since 2.5.0
	 * @param mixed $value Raw value.
	 * @param int   $limit Maximum length in characters.
	 * @return string
	 */
	public static function text( $value, $limit = 255 ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return '';
		}

		// Like wp_strip_all_tags(): a script or style block goes with its content.
		$text = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', (string) $value );
		$text = trim( strip_tags( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}


	// ── Fields and tags ─────────────────────────────────────────────────────────────────────


	/**
	 * The account's custom fields, in their display order.
	 *
	 * @since 2.5.0
	 * @param bool $archived Include archived fields.
	 * @param bool $force    Skip the cache.
	 * @return array Envelope.
	 */
	public static function list_fields( $archived = false, $force = false ) {
		return self::cached( $archived ? 'fields_archived' : 'fields', function () use ( $archived ) {
			return self::call( 'GET', '/contacts/fields', $archived ? array( 'archived' => true ) : array() );
		}, $force );
	}


	/**
	 * Every contact tag of the account, with how many contacts carry it.
	 *
	 * The platform pages tags by 200; a base with more is read in up to five pages.
	 *
	 * @since 2.5.0
	 * @param bool $force Skip the cache.
	 * @return array Envelope.
	 */
	public static function list_tags( $force = false ) {
		return self::cached( 'tags', function () {
			$tags = array();

			for ( $page = 1; $page <= 5; $page++ ) {
				$envelope = self::call( 'GET', '/contacts/tags', self::page_window( $page, 200 ) );

				if ( ! $envelope['ok'] ) {
					return $envelope;
				}

				$rows = is_array( $envelope['data'] ) ? $envelope['data'] : array();
				$tags = array_merge( $tags, $rows );
				$total = isset( $envelope['meta']['total'] ) ? (int) $envelope['meta']['total'] : count( $tags );

				if ( count( $rows ) < 200 || count( $tags ) >= $total ) {
					break;
				}
			}

			return self::success( 200, $tags, array( 'total' => count( $tags ) ) );
		}, $force );
	}


	/**
	 * Types a custom field can have.
	 *
	 * @since 2.5.0
	 * @var string[]
	 */
	const FIELD_TYPES = array( 'text', 'number', 'date', 'datetime', 'boolean', 'select', 'multi_select', 'email', 'url', 'phone' );


	/**
	 * Create a custom field. Its key and type never change afterwards.
	 *
	 * @since 2.5.0
	 * @param array $data Payload built by field_payload().
	 * @return array Envelope.
	 */
	public static function create_field( $data ) {
		return self::after_write( self::call( 'POST', '/contacts/fields', array(), $data ), array( 'fields', 'fields_archived' ) );
	}


	/**
	 * Change a field's label, options (the whole list), position, or archive it.
	 *
	 * @since 2.5.0
	 * @param string $id   Field id.
	 * @param array  $data Payload built by field_payload().
	 * @return array Envelope.
	 */
	public static function update_field( $id, $data ) {
		return self::after_write( self::call( 'PATCH', '/contacts/fields/' . $id, array(), (object) $data ), array( 'fields', 'fields_archived' ) );
	}


	/**
	 * Erase a field and its value on every contact.
	 *
	 * @since 2.5.0
	 * @param string $id Field id.
	 * @return array Envelope.
	 */
	public static function delete_field( $id ) {
		return self::after_write( self::call( 'DELETE', '/contacts/fields/' . $id, array( 'confirm' => true ) ), array( 'fields', 'fields_archived' ) );
	}


	/**
	 * Keep only what a field write may carry.
	 *
	 * @since 2.5.0
	 * @param array  $input Values typed on the screen.
	 * @param string $mode  'create' or 'update'.
	 * @return array
	 */
	public static function field_payload( $input, $mode = 'create' ) {
		$input = is_array( $input ) ? $input : array();
		$payload = array();

		if ( 'create' === $mode ) {
			$key = isset( $input['key'] ) && is_string( $input['key'] ) ? strtolower( trim( $input['key'] ) ) : '';
			$type = isset( $input['type'] ) && in_array( $input['type'], self::FIELD_TYPES, true ) ? $input['type'] : '';

			if ( 1 === preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $key ) ) {
				$payload['key'] = $key;
			}

			if ( '' !== $type ) {
				$payload['type'] = $type;
			}
		}

		if ( isset( $input['label'] ) ) {
			$label = self::text( $input['label'], 80 );

			if ( '' !== $label ) {
				$payload['label'] = $label;
			}
		}

		$type = 'create' === $mode ? ( $payload['type'] ?? '' ) : ( isset( $input['type'] ) ? (string) $input['type'] : '' );

		// Only the choice types take options; the platform refuses them on any other type.
		if ( isset( $input['options'] ) && is_array( $input['options'] ) && in_array( $type, array( 'select', 'multi_select' ), true ) ) {
			$options = array();

			foreach ( $input['options'] as $option ) {
				$option = self::text( $option, 80 );

				if ( '' !== $option && ! in_array( $option, $options, true ) ) {
					$options[] = $option;
				}
			}

			$payload['options'] = array_slice( $options, 0, 100 );
		}

		if ( isset( $input['position'] ) && is_numeric( $input['position'] ) ) {
			$payload['position'] = max( 0, min( 10000, (int) $input['position'] ) );
		}

		if ( 'update' === $mode && isset( $input['archived'] ) && is_bool( $input['archived'] ) ) {
			$payload['archived'] = $input['archived'];
		}

		return $payload;
	}


	/**
	 * Create a contact tag.
	 *
	 * @since 2.5.0
	 * @param array $data Payload built by tag_payload().
	 * @return array Envelope.
	 */
	public static function create_tag( $data ) {
		return self::after_write( self::call( 'POST', '/contacts/tags', array(), $data ), array( 'tags' ) );
	}


	/**
	 * Rename a tag, or change its description or color.
	 *
	 * @since 2.5.0
	 * @param string $id   Tag id.
	 * @param array  $data Payload built by tag_payload().
	 * @return array Envelope.
	 */
	public static function update_tag( $id, $data ) {
		return self::after_write( self::call( 'PATCH', '/contacts/tags/' . $id, array(), (object) $data ), array( 'tags' ) );
	}


	/**
	 * Erase a tag and take it off every contact.
	 *
	 * @since 2.5.0
	 * @param string $id Tag id.
	 * @return array Envelope.
	 */
	public static function delete_tag( $id ) {
		return self::after_write( self::call( 'DELETE', '/contacts/tags/' . $id ), array( 'tags' ) );
	}


	/**
	 * Put a tag on, or take it off, many contacts at once: the listed ids (up to 1,000) or every
	 * contact the listing filters select. Starts no flow and sends no webhook.
	 *
	 * @since 2.5.0
	 * @param string   $tag_id      Tag id.
	 * @param string   $action      'add' or 'remove'.
	 * @param string[] $contact_ids Contact ids; when empty, `$filters` decide.
	 * @param array    $filters     Filters read by list_filters().
	 * @return array Envelope; `data.affected` counts the contacts that changed.
	 */
	public static function apply_tag( $tag_id, $action, $contact_ids = array(), $filters = array() ) {
		$body = array(
			'tagId' => $tag_id,
			'action' => 'remove' === $action ? 'remove' : 'add',
		);

		$ids = array_values( array_unique( array_filter( (array) $contact_ids, array( __CLASS__, 'is_id' ) ) ) );

		if ( ! empty( $ids ) ) {
			$body['contactIds'] = array_slice( $ids, 0, 1000 );
		} else {
			$query = self::list_filters( $filters );

			// The bulk route takes the audience filter as an object, not as the listing's JSON.
			if ( isset( $query['filter'] ) ) {
				$query['filter'] = json_decode( $query['filter'], true );
			}

			$body['query'] = (object) $query;
		}

		return self::after_write( self::call( 'POST', '/contacts/tags/apply', array(), $body ), array( 'tags' ) );
	}


	/**
	 * Keep only what a tag write may carry.
	 *
	 * @since 2.5.0
	 * @param array $input Values typed on the screen.
	 * @return array
	 */
	public static function tag_payload( $input ) {
		$input = is_array( $input ) ? $input : array();
		$payload = array();

		if ( isset( $input['name'] ) ) {
			$name = self::text( $input['name'], 60 );

			if ( '' !== $name ) {
				$payload['name'] = $name;
			}
		}

		if ( array_key_exists( 'description', $input ) ) {
			$description = self::text( $input['description'], 200 );
			$payload['description'] = '' === $description ? null : $description;
		}

		if ( array_key_exists( 'color', $input ) ) {
			$color = is_string( $input['color'] ) ? strtolower( trim( $input['color'] ) ) : '';
			$payload['color'] = 1 === preg_match( '/^[a-z0-9-]{1,32}$/', $color ) ? $color : null;
		}

		return $payload;
	}


	/**
	 * Drop the cached definitions a successful write made stale.
	 *
	 * @since 2.5.0
	 * @param array    $envelope Envelope of the write.
	 * @param string[] $names    Cache names to drop.
	 * @return array The same envelope.
	 */
	private static function after_write( $envelope, $names ) {
		if ( $envelope['ok'] && function_exists( 'delete_transient' ) ) {
			self::flush_cache( $names );
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
