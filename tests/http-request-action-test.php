<?php
/**
 * Standalone test harness for the "HTTP request" workflow action (Http_Request_Action): the pure
 * parts (URL, rows, JSON filling, body modes, authentication, the kept response, the
 * {{ http:NAME.path }} tokens), the request it sends through a stubbed HTTP API, and the
 * Joinotify/Workflow_Processor/Payload_Actions contract that lets it write the response onto the
 * payload the next actions read.
 *
 * No WordPress bootstrap: add_filter() only records the callbacks, apply_filters() runs them, and
 * wp_safe_remote_request() answers with the response queued by the test.
 *
 * Run (Windows / Local), with sodium for the Token_Vault:
 *   & "C:\path\to\Local\php.exe" -d extension=sodium tests/http-request-action-test.php
 *
 * @since 2.7.0
 */

namespace MeuMouse\Joinotify\Core {

	class Logger {
		public static $lines = array();

		public static function register_log( $message, $level = 'INFO' ) {
			self::$lines[] = $level . ' ' . $message;
		}
	}
}

namespace {

define( 'ABSPATH', __DIR__ . '/' );

$failures = 0;
$assertions = 0;
$filters = array();
$http_requests = array();
$http_response = null;

function check( $label, $condition ) {
	global $failures, $assertions;
	$assertions++;

	if ( $condition ) {
		echo "  PASS  {$label}\n";
	} else {
		$failures++;
		echo "  FAIL  {$label}\n";
	}
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $filters;
	$filters[ $hook ][] = array( $callback, $accepted_args );
}

function apply_filters( $hook, $value, ...$args ) {
	global $filters;

	foreach ( isset( $filters[ $hook ] ) ? $filters[ $hook ] : array() as $filter ) {
		$value = call_user_func_array( $filter[0], array_slice( array_merge( array( $value ), $args ), 0, $filter[1] ) );
	}

	return $value;
}

function __( $text, $domain = '' ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text, $domain = '' ) {
	return esc_html( $text );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_json_encode( $data, $options = 0 ) {
	return json_encode( $data, $options );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function home_url() {
	return 'https://loja.test';
}

function wp_salt( $scheme = 'auth' ) {
	return 'salt-for-the-harness';
}

function wp_http_validate_url( $url ) {
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );

	return '' !== $host && 'localhost' !== $host && ! preg_match( '/^(127\.|10\.|192\.168\.)/', $host ) ? $url : false;
}

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function wp_safe_remote_request( $url, $args ) {
	global $http_requests, $http_response;
	$http_requests[] = array( 'url' => $url, 'args' => $args );

	return $http_response;
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

function respond( $code, $body ) {
	global $http_response, $http_requests;
	$http_requests = array();
	$http_response = array( 'response' => array( 'code' => $code ), 'body' => $body );
}

function reset_filters() {
	global $filters;
	$filters = array();
}

// The real joinotify_prepare_message() goes through Placeholders; here a short map and the
// response tokens are enough.
function joinotify_prepare_message( $message, $payload = array() ) {
	$message = strtr( (string) $message, array(
		'{{ wc_order_number }}' => '1234',
		'{{ wc_billing_first_name }}' => 'Ana "Bia"',
		'{{ wc_billing_email }}' => 'ana+loja@example.com',
		'{{ wc_order_total }}' => '99.9',
	) );

	return \MeuMouse\Joinotify\Builder\Http_Request_Action::replace_tokens( $message, $payload );
}

function joinotify_register_action( $definition ) {
	\MeuMouse\Joinotify\Api\Extensions::register_action( $definition );
}

function joinotify_register_action_description( $slug, $callback ) {
	\MeuMouse\Joinotify\Api\Extensions::register_action_description( $slug, $callback );
}

require __DIR__ . '/../admin/src/Core/Token_Vault.php';
require __DIR__ . '/../admin/src/Api/Extensions.php';
require __DIR__ . '/../admin/src/Core/Workflow_Processor.php';
require __DIR__ . '/../admin/src/Builder/Http_Request_Action.php';

use MeuMouse\Joinotify\Builder\Http_Request_Action;
use MeuMouse\Joinotify\Core\Token_Vault;
use MeuMouse\Joinotify\Core\Workflow_Processor;

$replace = function( $text ) {
	return joinotify_prepare_message( $text, array() );
};

echo "\nHttp_Request_Action::method\n";

check( 'a known method is kept, whatever its case', 'DELETE' === Http_Request_Action::method( ' delete ' ) );
check( 'anything else is GET', 'GET' === Http_Request_Action::method( 'TRACE' ) );

echo "\nHttp_Request_Action::rows\n";

$rows = Http_Request_Action::rows( array( array( 'key' => 'X-Store', 'value' => 'loja' ), array( 'key' => '', 'value' => 'x' ), array( 'key' => 'Bad Name', 'value' => 'y' ), 'junk' ), true );

check( 'reads the { key, value } rows as a map', array( 'X-Store' => 'loja' ) === $rows );
check( 'a query name may hold any character', array( 'a b' => '1' ) === Http_Request_Action::rows( array( array( 'key' => 'a b', 'value' => '1' ) ) ) );

echo "\nHttp_Request_Action::build_url\n";

check( 'adds the encoded query to a URL without one', 'https://api.test/c?email=ana%2Bloja%40example.com' === Http_Request_Action::build_url( 'https://api.test/c', array( 'email' => 'ana+loja@example.com' ) ) );
check( 'appends to a query already there, before the fragment', 'https://api.test/c?a=1&b=x%20y#top' === Http_Request_Action::build_url( 'https://api.test/c?a=1#top', array( 'b' => 'x y' ) ) );
check( 'no rows, the URL as typed', 'https://api.test/c?a=1' === Http_Request_Action::build_url( 'https://api.test/c?a=1', array() ) );

echo "\nHttp_Request_Action::fill_json\n";

$json = Http_Request_Action::fill_json( '{ "name": "{{ wc_billing_first_name }}", "order": "{{ wc_order_number }}", "n": 2, "{{ wc_order_number }}": true }', $replace );

check( 'a value with quotes cannot break the body', array( 'name' => 'Ana "Bia"', 'order' => '1234', 'n' => 2, '1234' => true ) === json_decode( (string) $json, true ) );
check( 'a bare placeholder that becomes a number is replaced as text', '{ "total": 99.9 }' === Http_Request_Action::fill_json( '{ "total": {{ wc_order_total }} }', $replace ) );
check( 'what is not JSON once replaced is refused', null === Http_Request_Action::fill_json( '{ "total": {{ wc_billing_first_name }} }', $replace ) );
check( 'nothing typed, no body', null === Http_Request_Action::fill_json( '  ', $replace ) );

echo "\nHttp_Request_Action::build_body\n";

check( 'GET never sends a body', array( null, '' ) === Http_Request_Action::build_body( array( 'method' => 'GET', 'body_mode' => 'json', 'body' => '{}' ), $replace ) );
check( 'an invalid JSON body is false', false === Http_Request_Action::build_body( array( 'method' => 'POST', 'body_mode' => 'json', 'body' => '{ "a": {{ wc_billing_first_name }} }' ), $replace )[0] );
check( 'form fields are URL encoded', array( 'email=ana%2Bloja%40example.com&n=1234', 'application/x-www-form-urlencoded' ) === Http_Request_Action::build_body( array( 'method' => 'POST', 'body_mode' => 'form', 'body_fields' => array( array( 'key' => 'email', 'value' => '{{ wc_billing_email }}' ), array( 'key' => 'n', 'value' => '{{ wc_order_number }}' ) ) ), $replace ) );
check( 'raw text keeps its content type', array( 'Pedido 1234', 'text/csv' ) === Http_Request_Action::build_body( array( 'method' => 'PUT', 'body_mode' => 'raw', 'body' => 'Pedido {{ wc_order_number }}', 'content_type' => 'text/csv' ), $replace ) );
$fields = function( $rows ) {
	return array( 'method' => 'POST', 'body_mode' => 'json', 'json_input' => 'fields', 'json_fields' => $rows );
};

check( 'a saved JSON body without json_input keeps its text', array( '{"n":"1234"}', 'application/json' ) === Http_Request_Action::build_body( array( 'method' => 'POST', 'body_mode' => 'json', 'body' => '{"n":"{{ wc_order_number }}"}', 'json_fields' => array( array( 'key' => 'x', 'value' => 'y' ) ) ), $replace ) );
check( 'fields build an object, each value as its type', array( '{"number":"1234","total":1234,"price":19.9,"linkPreview":false,"sent":true,"coupon":null,"customer":{"email":"ana+loja@example.com","tags":["a"]}}', 'application/json' ) === Http_Request_Action::build_body( $fields( array(
	array( 'key' => 'number', 'value' => '{{ wc_order_number }}' ),
	array( 'key' => 'total', 'value' => '{{ wc_order_number }}', 'type' => 'number' ),
	array( 'key' => 'price', 'value' => ' 19.90 ', 'type' => 'number' ),
	array( 'key' => 'linkPreview', 'value' => 'false', 'type' => 'boolean' ),
	array( 'key' => 'sent', 'value' => 'Yes', 'type' => 'boolean' ),
	array( 'key' => 'coupon', 'value' => 'ignored', 'type' => 'null' ),
	array( 'key' => 'customer', 'value' => '{ "email": "{{ wc_billing_email }}", "tags": ["a"] }', 'type' => 'json' ),
) ), $replace ) );
check( 'a text value with quotes and line breaks stays one string', '{"t":"say \"hi\"\nbye"}' === Http_Request_Action::build_body( $fields( array( array( 'key' => 't', 'value' => "say \"hi\"\nbye" ) ) ), $replace )[0] );
check( 'a row without a key is skipped and a repeated key keeps the last', '{"a":"2"}' === Http_Request_Action::build_body( $fields( array( array( 'key' => ' ', 'value' => 'x' ), array( 'key' => 'a', 'value' => '1' ), array( 'key' => 'a', 'value' => '2' ) ) ), $replace )[0] );
check( 'numeric keys and an empty table still send an object', '{"0":"x"}' === Http_Request_Action::build_body( $fields( array( array( 'key' => '0', 'value' => 'x' ) ) ), $replace )[0] && '{}' === Http_Request_Action::build_body( $fields( array() ), $replace )[0] );
check( 'a key takes placeholders', '{"order_1234":"x"}' === Http_Request_Action::build_body( $fields( array( array( 'key' => 'order_{{ wc_order_number }}', 'value' => 'x' ) ) ), $replace )[0] );
check( 'a number that does not become one fails the body', false === Http_Request_Action::build_body( $fields( array( array( 'key' => 'n', 'value' => '{{ wc_billing_first_name }}', 'type' => 'number' ) ) ), $replace )[0] );
check( 'a boolean that does not become one fails the body', false === Http_Request_Action::build_body( $fields( array( array( 'key' => 'b', 'value' => 'maybe', 'type' => 'boolean' ) ) ), $replace )[0] );
check( 'an invalid JSON value fails the body', false === Http_Request_Action::build_body( $fields( array( array( 'key' => 'j', 'value' => '{ "a": 1, }', 'type' => 'json' ) ) ), $replace )[0] );
check( 'an unknown type is sent as text', '{"a":"1"}' === Http_Request_Action::build_body( $fields( array( array( 'key' => 'a', 'value' => '1', 'type' => 'date' ) ) ), $replace )[0] );
check( 'the trigger mode sends the trigger\'s data',array( '{"order":{"id":7}}', 'application/json' ) === Http_Request_Action::build_body( array( 'method' => 'POST', 'body_mode' => 'trigger' ), $replace, function() { return array( 'order' => array( 'id' => 7 ) ); } ) );

echo "\nHttp_Request_Action::auth_header\n";

check( 'bearer', array( 'Authorization' => 'Bearer tok' ) === Http_Request_Action::auth_header( 'bearer', 'tok' ) );
check( 'basic', array( 'Authorization' => 'Basic ' . base64_encode( 'ana:pw' ) ) === Http_Request_Action::auth_header( 'basic', 'pw', 'ana' ) );
check( 'an API key in the header it names', array( 'X-API-Key' => 'k' ) === Http_Request_Action::auth_header( 'api_key', 'k', '', 'X-API-Key' ) );
check( 'an invalid header name sends nothing', array() === Http_Request_Action::auth_header( 'api_key', 'k', '', 'Bad Name' ) );

echo "\nHttp_Request_Action::response_record\n";

$record = Http_Request_Action::response_record( 200, '{"customer":{"id":42,"vip":true,"tags":["a","b"]}}' );

check( 'a JSON body is kept decoded, with the status', 200 === $record['status'] && true === $record['ok'] && 42 === $record['body']['customer']['id'] );
check( 'a text body is kept as text', 'pong' === Http_Request_Action::response_record( 200, 'pong' )['body'] );
check( 'a non-2xx status is not ok', false === Http_Request_Action::response_record( 404, '' )['ok'] );
check( 'a body above the stored size is dropped, the status kept', null === Http_Request_Action::response_record( 200, str_repeat( 'x', 50 ), '', 20 )['body'] && true === Http_Request_Action::response_record( 200, str_repeat( 'x', 50 ), '', 20 )['truncated'] );

echo "\nHttp_Request_Action::replace_tokens\n";

$payload = array( 'http_vars' => array( 'customer' => $record ) );

check( 'reads a nested field', 'ID 42' === Http_Request_Action::replace_tokens( 'ID {{ http:customer.body.customer.id }}', $payload ) );
check( 'reads a list position', 'b' === Http_Request_Action::replace_tokens( '{{http:customer.body.customer.tags.1}}', $payload ) );
check( 'true and false come out as words', 'true/true' === Http_Request_Action::replace_tokens( '{{ http:customer.ok }}/{{ http:customer.body.customer.vip }}', $payload ) );
check( 'the bare name is the body, as JSON', '{"customer":{"id":42,"vip":true,"tags":["a","b"]}}' === Http_Request_Action::replace_tokens( '{{ http:customer }}', $payload ) );
check( 'a path that leads nowhere is empty', '[]' === Http_Request_Action::replace_tokens( '[{{ http:customer.body.missing.x }}]', $payload ) );
check( 'a name no request stored is left as typed', '{{ http:other.status }}' === Http_Request_Action::replace_tokens( '{{ http:other.status }}', $payload ) );

echo "\nHttp_Request_Action::handle\n";

new Http_Request_Action();

check( 'the credential field is declared for sealing', array( 'http_request' => array( 'auth_value' ) ) === apply_filters( 'Joinotify/Builder/Sealed_Action_Fields', array() ) );

respond( 200, '{"id":42,"name":"Ana"}' );

$node = array(
	'id' => 'node_1',
	'type' => 'action',
	'data' => array(
		'action' => 'http_request',
		'method' => 'post',
		'url' => 'https://api.test/orders/{{ wc_order_number }}',
		'query' => array( array( 'key' => 'email', 'value' => '{{ wc_billing_email }}' ) ),
		'headers' => array( array( 'key' => 'accept', 'value' => 'application/vnd.test+json' ) ),
		'auth_type' => 'bearer',
		'auth_value' => Token_Vault::encrypt( 'secret-token', Token_Vault::WORKFLOW_CONTEXT ),
		'body_mode' => 'json',
		'body' => '{ "name": "{{ wc_billing_first_name }}" }',
		'timeout' => '90',
		'response_var' => 'Customer',
	),
);
$payload = array( 'integration' => 'woocommerce' );

check( 'the stored credential is sealed', Token_Vault::is_encrypted( $node['data']['auth_value'] ) );
check( 'dispatched through the engine, the action reports success', true === Workflow_Processor::handle_action( $node, 9, $payload ) );

$sent = $http_requests[0] ?? array( 'url' => '', 'args' => array( 'headers' => array() ) );

check( 'the URL carries the placeholders and the encoded query', 'https://api.test/orders/1234?email=ana%2Bloja%40example.com' === $sent['url'] );
check( 'the method is sent in upper case', 'POST' === $sent['args']['method'] );
check( 'the credential is opened and sent as a bearer token', 'Bearer secret-token' === $sent['args']['headers']['Authorization'] );
check( 'a header typed in the action wins over the default, whatever its case', 'application/vnd.test+json' === $sent['args']['headers']['accept'] && ! isset( $sent['args']['headers']['Accept'] ) );
check( 'the JSON body goes with its content type', '{"name":"Ana \"Bia\""}' === $sent['args']['body'] && 'application/json' === $sent['args']['headers']['Content-Type'] );
check( 'the timeout is held to 30 seconds', 30 === $sent['args']['timeout'] );
check( 'the response reaches the payload the next actions read, under its slug', 42 === ( $payload['http_vars']['customer']['body']['id'] ?? null ) );
check( 'so a later message can use it', 'Hi Ana' === joinotify_prepare_message( 'Hi {{ http:customer.body.name }}', $payload ) );

respond( 500, 'oops' );
$node['data']['response_var'] = 'failed';
$node['data']['auth_value'] = 'enc:v1:sealed-on-another-site';

check( 'a credential that cannot be opened fails the action', false === Workflow_Processor::handle_action( $node, 9, $payload ) );
check( 'and sends nothing', array() === $http_requests );
check( 'the failure is on the payload for the next actions', 'missing_credential' === $payload['http_vars']['failed']['error'] && 0 === $payload['http_vars']['failed']['status'] );

$node['data']['auth_type'] = 'none';
$node['data']['on_error'] = 'continue';

check( 'a 500 fails the action', false === Workflow_Processor::handle_action( $node, 9, $payload ) );
check( 'and keeps its status and error', 500 === $payload['http_vars']['failed']['status'] && 'HTTP 500' === $payload['http_vars']['failed']['error'] );

$stopped = new ReflectionProperty( Workflow_Processor::class, 'funnel_stopped' );
$stopped->setAccessible( true );

check( '"Continue with the next actions" leaves the workflow running', false === $stopped->getValue() );

$node['data']['on_error'] = 'stop';
Workflow_Processor::handle_action( $node, 9, $payload );

check( '"Stop the workflow" stops it', true === $stopped->getValue() );

$stopped->setValue( null, false );

$node['data']['url'] = 'http://192.168.0.10/admin';
respond( 200, '' );

check( 'a private address is refused', false === Workflow_Processor::handle_action( $node, 9, $payload ) && array() === $http_requests );

echo "\nPayload_Actions contract\n";

reset_filters();
joinotify_register_action( array(
	'action' => 'remember_me',
	'title' => 'Remember',
	'writes_payload' => true,
	'handler' => function( $action_data, $action, $post_id, &$event_data ) {
		$event_data['remembered'] = $action_data['value'];

		return true;
	},
) );

$payload = array();
$result = Workflow_Processor::handle_action( array( 'id' => 'n', 'data' => array( 'action' => 'remember_me', 'value' => 'yes' ) ), 1, $payload );

check( 'a writes_payload handler is not left in the catalog entry', ! array_key_exists( 'writes_payload', apply_filters( 'Joinotify/Builder/Actions', array() )[0] ) );
check( 'its handler runs and returns its result', true === $result );
check( 'what it stores reaches the caller\'s payload', 'yes' === ( $payload['remembered'] ?? null ) );

reset_filters();
joinotify_register_action( array(
	'action' => 'copy_only',
	'title' => 'Copy',
	'handler' => function( $action_data, $action, $post_id, $event_data ) {
		$event_data['changed'] = true;

		return true;
	},
) );

$payload = array();
Workflow_Processor::handle_action( array( 'id' => 'n', 'data' => array( 'action' => 'copy_only' ) ), 1, $payload );

check( 'a regular handler still works on a copy', ! isset( $payload['changed'] ) );

echo "\n{$assertions} assertions, {$failures} failure(s).\n";
exit( $failures > 0 ? 1 : 0 );

}
