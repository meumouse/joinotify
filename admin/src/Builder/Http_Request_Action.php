<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Core\Token_Vault;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * "HTTP request": a workflow action that calls any API and keeps its answer for the actions
 * that run after it.
 *
 * Where "Send webhook" only posts and forgets, this one sends any method, with query
 * parameters, headers and an authentication of its own, and stores the response on the payload
 * under a name: `{{ http:NAME.status }}`, `{{ http:NAME.ok }}`, `{{ http:NAME.body.customer.id }}`
 * and `{{ http:NAME.error }}` then work in any later field, through delays too, since the
 * payload travels with the scheduled continuation.
 *
 * Sent with `wp_safe_remote_request`, which refuses local and private addresses, also on each
 * redirect it follows: a workflow editor must not be a way to reach the server's own network.
 * The credential is sealed at rest with the Token_Vault when the workflow is saved.
 *
 * Registered through the public extension API, with a settings component of its own in the
 * builder.
 *
 * @since 2.7.0
 * @package MeuMouse\Joinotify\Builder
 * @author MeuMouse.com
 */
class Http_Request_Action {

	/**
	 * The action's slug.
	 *
	 * @since 2.7.0
	 * @var string
	 */
	const ACTION = 'http_request';

	/**
	 * Methods the action sends.
	 *
	 * @since 2.7.0
	 * @var array
	 */
	const METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	/**
	 * Seconds to wait for the other side when the action sets none, and the most it may set.
	 *
	 * @since 2.7.0
	 * @var int
	 */
	const TIMEOUT = 10;
	const MAX_TIMEOUT = 30;

	/**
	 * Bytes read from the response. The rest is dropped by the HTTP API.
	 *
	 * @since 2.7.0
	 * @var int
	 */
	const MAX_RESPONSE_BYTES = 262144;

	/**
	 * Bytes of response body kept on the payload, JSON encoded. The payload goes into the
	 * scheduler's arguments when a delay follows, so a larger body is dropped (the status is
	 * kept) — the `Joinotify/Builder/Http_Request_Action/Max_Stored_Bytes` filter changes it.
	 *
	 * @since 2.7.0
	 * @var int
	 */
	const MAX_STORED_BYTES = 4096;

	/**
	 * The action's icon: Boxicons' Globe, the set the builder draws its interface with.
	 *
	 * @since 2.7.0
	 * @var string
	 */
	const ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm7.931 9h-2.764a14.67 14.67 0 0 0-1.792-6.243A8.013 8.013 0 0 1 19.931 11zM12.53 4.027c1.035 1.364 2.427 3.78 2.627 6.973H9.03c.139-2.596.994-5.028 2.451-6.974.172-.01.344-.026.519-.026.179 0 .354.016.53.027zm-3.842.7C7.704 6.618 7.136 8.762 7.03 11H4.069a8.013 8.013 0 0 1 4.619-6.273zM4.069 13h2.974c.136 2.379.665 4.478 1.556 6.23A8.01 8.01 0 0 1 4.069 13zm7.381 6.973C10.049 18.275 9.222 15.896 9.041 13h6.113c-.208 2.773-1.117 5.196-2.603 6.972-.182.012-.364.028-.551.028-.186 0-.367-.016-.55-.027zm4.011-.772c.955-1.794 1.538-3.901 1.691-6.201h2.778a8.005 8.005 0 0 1-4.469 6.201z"/></svg>';


	/**
	 * Register the action.
	 *
	 * @since 2.7.0
	 * @return void
	 */
	public function __construct() {
		if ( ! function_exists( 'joinotify_register_action' ) ) {
			return;
		}

		joinotify_register_action( array(
			'action' => self::ACTION,
			'title' => __( 'HTTP request', 'joinotify' ),
			'description' => __( 'Call any API and use its response in the next actions.', 'joinotify' ),
			'category' => 'general',
			'icon' => self::ICON,
			'has_settings' => true,
			'is_expansible' => true,
			'priority' => 79,
			'context' => array(),
			'default_data' => self::default_data(),
			'handler' => array( __CLASS__, 'handle' ),
			'writes_payload' => true,
		) );

		if ( function_exists( 'joinotify_register_action_description' ) ) {
			joinotify_register_action_description( self::ACTION, array( __CLASS__, 'describe' ) );
		}

		add_filter( 'Joinotify/Builder/Sealed_Action_Fields', array( __CLASS__, 'sealed_fields' ) );
	}


	/**
	 * The data of a new node.
	 *
	 * @since 2.7.0
	 * @return array
	 */
	public static function default_data() {
		return array(
			'action' => self::ACTION,
			'title' => __( 'HTTP request', 'joinotify' ),
			'method' => 'GET',
			'url' => '',
			'query' => array(),
			'headers' => array(),
			'auth_type' => 'none',
			'auth_user' => '',
			'auth_header' => 'X-API-Key',
			'auth_value' => '',
			'body_mode' => 'none',
			'body' => '',
			'body_fields' => array(),
			'content_type' => 'text/plain',
			'timeout' => (string) self::TIMEOUT,
			'response_var' => '',
			'on_error' => 'continue',
		);
	}


	/**
	 * Fields sealed with the Token_Vault when a workflow is saved.
	 *
	 * @since 2.7.0
	 * @param array $fields Map of action slug => field keys.
	 * @return array
	 */
	public static function sealed_fields( $fields ) {
		$fields = is_array( $fields ) ? $fields : array();
		$fields[ self::ACTION ] = array( 'auth_value' );

		return $fields;
	}


	/**
	 * The node's line on the canvas: the method, where it goes and the name of its response.
	 *
	 * @since 2.7.0
	 * @param array $data
	 * @return string
	 */
	public static function describe( $data ) {
		$host = (string) wp_parse_url( (string) ( $data['url'] ?? '' ), PHP_URL_HOST );

		if ( '' === $host ) {
			return esc_html__( 'Set the URL', 'joinotify' );
		}

		$line = sprintf( '%s %s', self::method( $data['method'] ?? '' ), $host );
		$name = sanitize_key( (string) ( $data['response_var'] ?? '' ) );

		if ( '' !== $name ) {
			$line .= ' → {{ http:' . $name . ' }}';
		}

		return esc_html( $line );
	}


	/**
	 * A method the action sends, GET for anything else. Pure, for the harness.
	 *
	 * @since 2.7.0
	 * @param mixed $method
	 * @return string
	 */
	public static function method( $method ) {
		$method = strtoupper( trim( (string) $method ) );

		return in_array( $method, self::METHODS, true ) ? $method : 'GET';
	}


	/**
	 * The name/value rows of a list field ({ key, value }), as a map. A row without a name is
	 * left out; with $header_names, so is a row whose name is not a valid header name. Pure,
	 * for the harness.
	 *
	 * @since 2.7.0
	 * @param mixed $rows
	 * @param bool $header_names
	 * @return array<string,string>
	 */
	public static function rows( $rows, $header_names = false ) {
		$map = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = trim( (string) ( $row['key'] ?? '' ) );

			if ( '' === $name || ( $header_names && ! preg_match( '/^[A-Za-z0-9-]+$/', $name ) ) ) {
				continue;
			}

			$map[ $name ] = is_scalar( $row['value'] ?? '' ) ? (string) ( $row['value'] ?? '' ) : '';
		}

		return $map;
	}


	/**
	 * The URL with the query parameters added, each name and value encoded, before any
	 * fragment. Pure, for the harness.
	 *
	 * @since 2.7.0
	 * @param string $url
	 * @param array<string,string> $query
	 * @return string
	 */
	public static function build_url( $url, $query ) {
		$url = (string) $url;

		if ( empty( $query ) ) {
			return $url;
		}

		$fragment = '';
		$hash = strpos( $url, '#' );

		if ( false !== $hash ) {
			$fragment = substr( $url, $hash );
			$url = substr( $url, 0, $hash );
		}

		$pairs = array();

		foreach ( $query as $name => $value ) {
			$pairs[] = rawurlencode( (string) $name ) . '=' . rawurlencode( (string) $value );
		}

		$separator = false === strpos( $url, '?' ) ? '?' : ( in_array( substr( $url, -1 ), array( '?', '&' ), true ) ? '' : '&' );

		return $url . $separator . implode( '&', $pairs ) . $fragment;
	}


	/**
	 * Fill a JSON template with placeholders.
	 *
	 * A template that is valid JSON as typed has the placeholders replaced inside each string,
	 * keys included, and is encoded again — so a value carrying quotes or a line break can never
	 * break the body. A template that only becomes JSON once replaced (a bare
	 * `{{ wc_order_total }}` as a number) is replaced as text and must be valid JSON after.
	 * Null when the result is not JSON. Pure given $replace, for the harness.
	 *
	 * @since 2.7.0
	 * @param string $template
	 * @param callable $replace Text => text with the placeholders replaced.
	 * @return string|null
	 */
	public static function fill_json( $template, $replace ) {
		$template = trim( (string) $template );

		if ( '' === $template ) {
			return null;
		}

		$decoded = json_decode( $template, true );

		if ( JSON_ERROR_NONE === json_last_error() ) {
			$encoded = wp_json_encode( self::fill_tree( $decoded, $replace ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

			return is_string( $encoded ) ? $encoded : null;
		}

		$filled = (string) call_user_func( $replace, $template );
		json_decode( $filled );

		return JSON_ERROR_NONE === json_last_error() ? $filled : null;
	}


	/**
	 * Replace the placeholders in every string of a decoded JSON value.
	 *
	 * @since 2.7.0
	 * @param mixed $value
	 * @param callable $replace
	 * @return mixed
	 */
	protected static function fill_tree( $value, $replace ) {
		if ( is_string( $value ) ) {
			return false === strpos( $value, '{{' ) ? $value : (string) call_user_func( $replace, $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$filled = array();

		foreach ( $value as $key => $item ) {
			$key = is_string( $key ) && false !== strpos( $key, '{{' ) ? (string) call_user_func( $replace, $key ) : $key;
			$filled[ $key ] = self::fill_tree( $item, $replace );
		}

		return $filled;
	}


	/**
	 * The body, and the content type it goes with, for a body mode. Null body when the mode
	 * sends none; false when a JSON body is not valid. Pure given $replace, for the harness.
	 *
	 * @since 2.7.0
	 * @param array $action_data The action's settings.
	 * @param callable $replace Text => text with the placeholders replaced.
	 * @param callable|null $trigger_body () => the trigger's data, for the "trigger" mode.
	 * @return array{0:string|null|false,1:string}
	 */
	public static function build_body( $action_data, $replace, $trigger_body = null ) {
		$mode = (string) ( $action_data['body_mode'] ?? 'none' );

		if ( 'GET' === self::method( $action_data['method'] ?? '' ) ) {
			return array( null, '' );
		}

		switch ( $mode ) {
			case 'trigger':
				$data = is_callable( $trigger_body ) ? call_user_func( $trigger_body ) : array();

				return array( (string) wp_json_encode( $data ), 'application/json' );

			case 'json':
				$body = self::fill_json( (string) ( $action_data['body'] ?? '' ), $replace );

				return array( null === $body ? false : $body, 'application/json' );

			case 'form':
				$pairs = array();

				foreach ( self::rows( $action_data['body_fields'] ?? array() ) as $name => $value ) {
					$pairs[] = rawurlencode( (string) call_user_func( $replace, $name ) ) . '=' . rawurlencode( (string) call_user_func( $replace, $value ) );
				}

				return array( implode( '&', $pairs ), 'application/x-www-form-urlencoded' );

			case 'raw':
				$type = trim( (string) ( $action_data['content_type'] ?? '' ) );

				return array( (string) call_user_func( $replace, (string) ( $action_data['body'] ?? '' ) ), '' !== $type ? $type : 'text/plain' );
		}

		return array( null, '' );
	}


	/**
	 * The header the authentication adds, if any. Pure, for the harness.
	 *
	 * @since 2.7.0
	 * @param string $type none, bearer, basic or api_key.
	 * @param string $secret The token, password or key, in the clear.
	 * @param string $user The basic user.
	 * @param string $header_name The api_key header.
	 * @return array<string,string>
	 */
	public static function auth_header( $type, $secret, $user = '', $header_name = '' ) {
		switch ( $type ) {
			case 'bearer':
				return array( 'Authorization' => 'Bearer ' . $secret );

			case 'basic':
				return array( 'Authorization' => 'Basic ' . base64_encode( $user . ':' . $secret ) );

			case 'api_key':
				$header_name = trim( (string) $header_name );

				return preg_match( '/^[A-Za-z0-9-]+$/', $header_name ) ? array( $header_name => $secret ) : array();
		}

		return array();
	}


	/**
	 * What the payload keeps of a response: { status, ok, body, error }. The body is the decoded
	 * JSON when it is JSON, the text otherwise, and null (with `truncated`) above the stored size.
	 * Pure, for the harness.
	 *
	 * @since 2.7.0
	 * @param int $status 0 when the request did not complete.
	 * @param string $raw_body
	 * @param string $error
	 * @param int $max_bytes
	 * @return array
	 */
	public static function response_record( $status, $raw_body, $error = '', $max_bytes = self::MAX_STORED_BYTES ) {
		$status = (int) $status;
		$raw_body = (string) $raw_body;
		$body = $raw_body;

		if ( '' !== trim( $raw_body ) ) {
			$decoded = json_decode( $raw_body, true );

			if ( JSON_ERROR_NONE === json_last_error() ) {
				$body = $decoded;
			}
		}

		$record = array(
			'status' => $status,
			'ok' => $status >= 200 && $status < 300,
			'body' => $body,
			'error' => (string) $error,
		);

		$size = strlen( (string) wp_json_encode( $body ) );

		if ( $size > (int) $max_bytes ) {
			$record['body'] = null;
			$record['truncated'] = true;
		}

		return $record;
	}


	/**
	 * Read a path such as "body.items.0.sku" from a stored response. Pure, for the harness.
	 *
	 * @since 2.7.0
	 * @param array $record
	 * @param string[] $path
	 * @return mixed Null when the path leads nowhere.
	 */
	public static function read_path( $record, $path ) {
		$value = $record;

		foreach ( $path as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return null;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}


	/**
	 * Replace the {{ http:NAME.path }} tokens of a text with the responses on the payload.
	 *
	 * `{{ http:NAME }}` is the body; a list or object comes out as JSON, true and false as
	 * "true" and "false". A path that leads nowhere comes out empty; a name no request stored is
	 * left as typed, as an unknown token is elsewhere. Pure, for the harness.
	 *
	 * @since 2.7.0
	 * @param string $message
	 * @param array $payload
	 * @return string
	 */
	public static function replace_tokens( $message, $payload = array() ) {
		$message = is_scalar( $message ) ? (string) $message : '';

		if ( false === strpos( $message, 'http:' ) ) {
			return $message;
		}

		$vars = isset( $payload['http_vars'] ) && is_array( $payload['http_vars'] ) ? $payload['http_vars'] : array();

		$replaced = preg_replace_callback( '/\{\{\s*http:([a-zA-Z0-9_-]+)((?:\.[^\s.{}]+)*)\s*\}\}/', function( $matches ) use ( $vars ) {
			$name = strtolower( $matches[1] );

			if ( ! isset( $vars[ $name ] ) || ! is_array( $vars[ $name ] ) ) {
				return $matches[0];
			}

			$path = '' === $matches[2] ? array( 'body' ) : explode( '.', substr( $matches[2], 1 ) );
			$value = self::read_path( $vars[ $name ], $path );

			if ( is_bool( $value ) ) {
				return $value ? 'true' : 'false';
			}

			if ( is_array( $value ) ) {
				return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}

			return is_scalar( $value ) ? (string) $value : '';
		}, $message );

		return is_string( $replaced ) ? $replaced : $message;
	}


	/**
	 * Run the action.
	 *
	 * @since 2.7.0
	 * @param array $action_data The action's settings.
	 * @param array $action The workflow node.
	 * @param int $post_id The workflow.
	 * @param array $event_data The trigger's payload, by reference: the response is stored on it.
	 * @return bool
	 */
	public static function handle( $action_data, $action, $post_id, &$event_data ) {
		$event_data = is_array( $event_data ) ? $event_data : array();
		$post_id = (int) $post_id;
		$name = sanitize_key( (string) ( $action_data['response_var'] ?? '' ) );
		$method = self::method( $action_data['method'] ?? '' );
		$payload = $event_data;

		$replace = function( $text ) use ( $payload ) {
			return joinotify_prepare_message( (string) $text, $payload );
		};

		$query = array();

		foreach ( self::rows( $action_data['query'] ?? array() ) as $key => $value ) {
			$query[ $replace( $key ) ] = $replace( $value );
		}

		$url = self::build_url( trim( $replace( (string) ( $action_data['url'] ?? '' ) ) ), $query );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			Logger::register_log( sprintf( 'HTTP request (workflow %d): the URL "%s" is not a public address.', $post_id, (string) wp_parse_url( $url, PHP_URL_HOST ) ), 'ERROR' );

			return self::fail( $action_data, $event_data, $name, 0, 'invalid_url' );
		}

		list( $body, $content_type ) = self::build_body( $action_data, $replace, function() use ( $payload ) {
			return Webhook_Action::trigger_body( $payload );
		} );

		if ( false === $body ) {
			Logger::register_log( sprintf( 'HTTP request (workflow %d): the body is not valid JSON once placeholders are replaced.', $post_id ), 'ERROR' );

			return self::fail( $action_data, $event_data, $name, 0, 'invalid_body' );
		}

		$headers = array();

		foreach ( self::rows( $action_data['headers'] ?? array(), true ) as $key => $value ) {
			$headers[ $key ] = $replace( $value );
		}

		$auth_type = (string) ( $action_data['auth_type'] ?? 'none' );

		if ( 'none' !== $auth_type ) {
			$secret = Token_Vault::decrypt( $action_data['auth_value'] ?? '', Token_Vault::WORKFLOW_CONTEXT );

			if ( '' === $secret ) {
				Logger::register_log( sprintf( 'HTTP request (workflow %d): the credential is empty or could not be read on this site — type it again in the action.', $post_id ), 'ERROR' );

				return self::fail( $action_data, $event_data, $name, 0, 'missing_credential' );
			}

			$headers = array_merge( $headers, self::auth_header( $auth_type, $replace( $secret ), $replace( (string) ( $action_data['auth_user'] ?? '' ) ), (string) ( $action_data['auth_header'] ?? '' ) ) );
		}

		// the action's own headers win over the defaults, whatever their case
		$typed = array_change_key_case( $headers, CASE_LOWER );
		$defaults = array(
			'Accept' => 'application/json, */*;q=0.8',
			'User-Agent' => 'Joinotify/' . ( defined( 'JOINOTIFY_VERSION' ) ? JOINOTIFY_VERSION : '' ) . '; ' . home_url(),
		);

		if ( null !== $body && '' !== $content_type ) {
			$defaults['Content-Type'] = $content_type;
		}

		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $typed[ strtolower( $key ) ] ) ) {
				$headers[ $key ] = $value;
			}
		}

		$timeout = (int) ( $action_data['timeout'] ?? self::TIMEOUT );
		$args = array(
			'method' => $method,
			'headers' => $headers,
			'timeout' => min( self::MAX_TIMEOUT, max( 1, $timeout > 0 ? $timeout : self::TIMEOUT ) ),
			'redirection' => 3,
			'limit_response_size' => self::MAX_RESPONSE_BYTES,
		);

		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = wp_safe_remote_request( $url, $args );
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( is_wp_error( $response ) ) {
			Logger::register_log( sprintf( 'HTTP request (workflow %d) to %s failed: %s', $post_id, $host, $response->get_error_message() ), 'ERROR' );

			return self::fail( $action_data, $event_data, $name, 0, $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		/**
		 * Filter the bytes of response body the "HTTP request" action keeps on the payload.
		 *
		 * The payload goes into the scheduler's arguments when a delay follows the action, so
		 * a larger value can stop the continuation from being scheduled.
		 *
		 * @since 2.7.0
		 * @param int $max_bytes
		 * @param array $action_data
		 */
		$max_bytes = (int) apply_filters( 'Joinotify/Builder/Http_Request_Action/Max_Stored_Bytes', self::MAX_STORED_BYTES, $action_data );
		$record = self::response_record( $status, (string) wp_remote_retrieve_body( $response ), $status >= 200 && $status < 300 ? '' : 'HTTP ' . $status, $max_bytes );

		if ( ! empty( $record['truncated'] ) ) {
			Logger::register_log( sprintf( 'HTTP request (workflow %d) to %s: the response body is above %d bytes and was not kept; only its status is available.', $post_id, $host, $max_bytes ), 'WARNING' );
		}

		self::store( $event_data, $name, $record );

		if ( ! $record['ok'] ) {
			Logger::register_log( sprintf( 'HTTP request (workflow %d) to %s failed: HTTP %d', $post_id, $host, $status ), 'ERROR' );

			return self::stop_on_error( $action_data );
		}

		return true;
	}


	/**
	 * Keep a response on the payload under its name.
	 *
	 * @since 2.7.0
	 * @param array $event_data By reference.
	 * @param string $name
	 * @param array $record
	 * @return void
	 */
	protected static function store( &$event_data, $name, $record ) {
		if ( '' === $name ) {
			return;
		}

		if ( ! isset( $event_data['http_vars'] ) || ! is_array( $event_data['http_vars'] ) ) {
			$event_data['http_vars'] = array();
		}

		$event_data['http_vars'][ $name ] = $record;
	}


	/**
	 * Record a request that did not complete, then stop the workflow if the action says so.
	 *
	 * @since 2.7.0
	 * @param array $action_data
	 * @param array $event_data By reference.
	 * @param string $name
	 * @param int $status
	 * @param string $error
	 * @return bool
	 */
	protected static function fail( $action_data, &$event_data, $name, $status, $error ) {
		self::store( $event_data, $name, self::response_record( $status, '', $error ) );

		return self::stop_on_error( $action_data );
	}


	/**
	 * A failed request returns false; with "Stop the workflow" set, the actions after it do
	 * not run either.
	 *
	 * @since 2.7.0
	 * @param array $action_data
	 * @return bool
	 */
	protected static function stop_on_error( $action_data ) {
		if ( 'stop' === ( $action_data['on_error'] ?? 'continue' ) ) {
			Workflow_Processor::stop_funnel();
		}

		return false;
	}
}
