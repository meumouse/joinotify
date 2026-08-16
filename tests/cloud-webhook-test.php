<?php
/**
 * Standalone test harness for the Cloud API webhook receiver.
 *
 * The callback URL is public by necessity, so the signature is the only thing
 * standing between the site and forged deliveries: these assertions cover the
 * HMAC itself, the replay window, the URLs the API refuses before a request is
 * even spent, and what each verified event actually changes (delivery status by
 * WAMID, the 24-hour window opened by an inbound message, the template cache,
 * and the quality rating of a number).
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/cloud-webhook-test.php
 *
 * @since 2.3.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$failures = 0;
$assertions = 0;

/**
 * Assert a condition, tracking pass/fail counts.
 */
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

// ---------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------

$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['actions'] = array();

function get_option( $key, $default = false ) { global $options; return array_key_exists( $key, $options ) ? $options[ $key ] : $default; }
function update_option( $key, $value ) { global $options; $options[ $key ] = $value; return true; }
function delete_option( $key ) { global $options; unset( $options[ $key ] ); return true; }
function get_transient( $key ) { global $transients; return array_key_exists( $key, $transients ) ? $transients[ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { global $transients; $transients[ $key ] = $value; return true; }
function delete_transient( $key ) { global $transients; unset( $transients[ $key ] ); return true; }
function do_action( $hook ) { global $actions; $actions[] = $hook; }
function apply_filters( $hook, $value = null ) { return $value; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function __( $text, $domain = 'default' ) { return $text; }
function home_url( $path = '' ) { return 'https://loja.exemplo.com' . $path; }
function rest_url( $path = '' ) { return 'https://loja.exemplo.com/wp-json/' . ltrim( $path, '/' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

}

// ---------------------------------------------------------------------------
// Namespaced collaborator fakes (must exist before the real classes load)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Logger { public static function register_log( $m, $l = 'INFO' ) {} }

	class Debug_Log {
		public static $records = array();

		public static function record( $entry ) { self::$records[] = $entry; }
	}

	class Message_History {
		public static $updates = array();

		public static function update_status_by_wamid( $wamid, $status, $error = '' ) {
			self::$updates[] = array( 'wamid' => $wamid, 'status' => $status, 'error' => $error );

			return true;
		}
	}
}

namespace MeuMouse\Joinotify\Api {
	class Template_Repository {
		public static $flushes = 0;

		public static function flush_cache() { self::$flushes++; }
	}

	class Cloud_Client {
		public static $response = null;
		public static $requests = array();

		public static function request( $method, $path, $body = null, $timeout = 30 ) {
			self::$requests[] = array( 'method' => $method, 'path' => $path, 'body' => $body );

			return self::$response;
		}
	}
}

namespace {

// wp_remote_* helpers only used by Webhooks::register().
function wp_remote_retrieve_response_code( $response ) { return $response['code'] ?? 0; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_json_encode( $value ) { return json_encode( $value ); }

require_once __DIR__ . '/../admin/src/Core/Phone_Manager.php';
require_once __DIR__ . '/../admin/src/Api/Webhooks.php';
require_once __DIR__ . '/../admin/src/Api/Webhook_Handler.php';

use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Api\Template_Repository;
use MeuMouse\Joinotify\Api\Webhook_Handler;
use MeuMouse\Joinotify\Api\Webhooks;
use MeuMouse\Joinotify\Core\Debug_Log;
use MeuMouse\Joinotify\Core\Message_History;
use MeuMouse\Joinotify\Core\Phone_Manager;

/**
 * Reset every stubbed store between scenarios.
 */
function reset_state() {
	$GLOBALS['options'] = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['actions'] = array();

	Cloud_Client::$response = null;
	Cloud_Client::$requests = array();
	Template_Repository::$flushes = 0;
	Message_History::$updates = array();
	Debug_Log::$records = array();
}

/**
 * Build a valid signature for a body/timestamp pair.
 */
function sign( $body, $timestamp, $secret ) {
	return 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
}

echo "\nWebhooks::verify_signature\n";
reset_state();

update_option( Webhooks::SECRET_OPTION, 'whsec_abc123' );

$body = '{"field":"messages","value":{}}';
$now = (string) time();

check( 'a valid signature is accepted', Webhooks::verify_signature( $body, sign( $body, $now, 'whsec_abc123' ), $now ) );
check( 'a tampered body is refused', ! Webhooks::verify_signature( $body . ' ', sign( $body, $now, 'whsec_abc123' ), $now ) );
check( 'a signature from another secret is refused', ! Webhooks::verify_signature( $body, sign( $body, $now, 'whsec_other' ), $now ) );
check( 'a replayed delivery outside the tolerance is refused', ! Webhooks::verify_signature( $body, sign( $body, (string) ( time() - 400 ), 'whsec_abc123' ), (string) ( time() - 400 ) ) );
check( 'a delivery just inside the tolerance is accepted', Webhooks::verify_signature( $body, sign( $body, (string) ( time() - 120 ), 'whsec_abc123' ), (string) ( time() - 120 ) ) );
check( 'a signature reused with a different timestamp is refused', ! Webhooks::verify_signature( $body, sign( $body, $now, 'whsec_abc123' ), (string) ( time() - 10 ) ) );
check( 'an empty signature is refused', ! Webhooks::verify_signature( $body, '', $now ) );

reset_state();
check( 'nothing is accepted before an endpoint is registered', ! Webhooks::verify_signature( $body, sign( $body, $now, 'whsec_abc123' ), $now ) );

echo "\nWebhooks registration guards\n";
reset_state();

check( 'is_registered() is false without an endpoint', ! Webhooks::is_registered() );

update_option( Webhooks::ENDPOINT_OPTION, 'we_1' );
update_option( Webhooks::SECRET_OPTION, 'whsec_abc123' );
check( 'is_registered() is true once both are stored', Webhooks::is_registered() );

check( 'https public hosts are allowed', true === Webhooks::validate_url( 'https://loja.exemplo.com/wp-json/joinotify/v1/cloud/webhook' ) );
check( 'plain http is refused', is_wp_error( Webhooks::validate_url( 'http://loja.exemplo.com/hook' ) ) );
check( 'localhost is refused', is_wp_error( Webhooks::validate_url( 'https://localhost/hook' ) ) );
check( 'private ranges are refused', is_wp_error( Webhooks::validate_url( 'https://192.168.0.10/hook' ) ) );
check( 'a .local host is refused', is_wp_error( Webhooks::validate_url( 'https://joinotify.local/hook' ) ) );

reset_state();
Cloud_Client::$response = array( 'code' => 201, 'body' => json_encode( array( 'data' => array( 'id' => 'we_9', 'secret' => 'whsec_new' ) ) ) );
$registered = Webhooks::register();

check( 'a successful registration stores the endpoint id', 'we_9' === get_option( Webhooks::ENDPOINT_OPTION ) );
check( 'a successful registration stores the secret', 'whsec_new' === Webhooks::get_secret() );
check( 'the subscribed events are sent', in_array( 'messages', Cloud_Client::$requests[0]['body']['events'], true ) );

reset_state();
Cloud_Client::$response = array( 'code' => 422, 'body' => json_encode( array( 'error' => array( 'message' => 'Invalid URL.' ) ) ) );
check( 'a refused registration surfaces the API message', 'Invalid URL.' === Webhooks::register()->get_error_message() );
check( 'a refused registration stores nothing', '' === Webhooks::get_secret() );

reset_state();
Cloud_Client::$response = array( 'code' => 201, 'body' => json_encode( array( 'data' => array( 'id' => 'we_9' ) ) ) );
check( 'a registration without a secret is an error', is_wp_error( Webhooks::register() ) );

echo "\nWebhook_Handler delivery statuses\n";
reset_state();

Webhook_Handler::handle( array(
	'field' => 'messages',
	'value' => array(
		'statuses' => array(
			array( 'id' => 'wamid.A', 'status' => 'delivered', 'recipient_id' => '5541987111527' ),
			array( 'id' => 'wamid.B', 'status' => 'read' ),
			array( 'id' => 'wamid.C', 'status' => 'sent' ),
			array(
				'id' => 'wamid.D',
				'status' => 'failed',
				'recipient_id' => '5541987111527',
				'errors' => array( array( 'code' => 131047, 'title' => 'Re-engagement message' ) ),
			),
		),
	),
) );

check( 'delivered and read are written back', 'delivered' === Message_History::$updates[0]['status'] && 'read' === Message_History::$updates[1]['status'] );
check( 'the WAMID identifies the row', 'wamid.A' === Message_History::$updates[0]['wamid'] );
check( 'sent is not written back again', 3 === count( Message_History::$updates ) );
check( 'a failure carries its reason', 'failed' === Message_History::$updates[2]['status'] && 'Re-engagement message' === Message_History::$updates[2]['error'] );
check( 'a failure is logged for the operator', 1 === count( Debug_Log::$records ) && 'error' === Debug_Log::$records[0]['level'] );
check( 'the Meta error code is kept', '131047' === (string) Debug_Log::$records[0]['code'] );

echo "\nWebhook_Handler 24-hour window\n";
reset_state();

check( 'the window starts closed', ! Webhook_Handler::window_is_open( '5541987111527' ) );

Webhook_Handler::handle( array(
	'field' => 'messages',
	'value' => array( 'messages' => array( array( 'from' => '5541987111527', 'type' => 'text' ) ) ),
) );

check( 'an inbound message opens the window', Webhook_Handler::window_is_open( '5541987111527' ) );
check( 'the window is per contact', ! Webhook_Handler::window_is_open( '5511988887777' ) );
check( 'formatting differences do not miss the window', Webhook_Handler::window_is_open( '+55 (41) 98711-1527' ) );

echo "\nWebhook_Handler template and number events\n";
reset_state();

Webhook_Handler::handle( array( 'field' => 'message_template_status_update', 'value' => array( 'event' => 'APPROVED' ) ) );
Webhook_Handler::handle( array( 'field' => 'template_category_update', 'value' => array() ) );
check( 'template events invalidate the cached catalogue', 2 === Template_Repository::$flushes );

reset_state();
Phone_Manager::add_sender( '5541987111527', array( 'phone_number_id' => 'id_1', 'quality_rating' => 'GREEN' ) );

Webhook_Handler::handle( array(
	'field' => 'phone_number_quality_update',
	'value' => array( 'display_phone_number' => '+55 41 98711-1527', 'event' => 'YELLOW' ),
) );

check( 'a quality update reaches the sender metadata', 'YELLOW' === Phone_Manager::get_sender_meta( '5541987111527' )['quality_rating'] );
check( 'the rest of the sender metadata survives', 'id_1' === Phone_Manager::get_phone_number_id( '5541987111527' ) );

reset_state();
Webhook_Handler::handle( array( 'field' => 'unknown_event', 'value' => array() ) );
check( 'an unknown event is ignored without side effects', 0 === Template_Repository::$flushes && 0 === count( Message_History::$updates ) );
check( 'every verified delivery fires the extension hook', in_array( 'Joinotify/Cloud_Api/Webhook_Event', $GLOBALS['actions'], true ) );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions failed\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
