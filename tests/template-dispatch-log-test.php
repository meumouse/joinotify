<?php
/**
 * Standalone test harness for the template dispatch record.
 *
 * A template send has to leave behind what the recipient actually read: the
 * message history keeps the template text with the values in place plus the
 * name, language and each parameter, and the debug log keeps the same detail —
 * every send while debug mode is on, failures always. These assertions cover
 * the template rendering (positional, named, header/footer, buttons, fallback),
 * the long-lived snapshot that survives the 15-minute listing cache, the
 * masking of login codes, the redaction filter, the `meta` schema guard, and
 * the history and debug-log rows written by a real Cloud_Client send against
 * a fake $wpdb.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" -d extension_dir="C:\path\to\ext" -d extension=mbstring tests/template-dispatch-log-test.php
 *
 * @since 2.4.1
 */

namespace {

// Message_History::maybe_create_table() requires ABSPATH/wp-admin/includes/upgrade.php,
// so ABSPATH points at a scratch directory holding a fake dbDelta().
$abspath = rtrim( sys_get_temp_dir(), '/\\' ) . '/joinotify-template-dispatch-test/';
@mkdir( $abspath . 'wp-admin/includes', 0777, true );
file_put_contents( $abspath . 'wp-admin/includes/upgrade.php', '<?php function dbDelta( $sql ) { $GLOBALS[\'db_delta\'][] = $sql; return array(); }' );

define( 'ABSPATH', $abspath );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

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
$GLOBALS['filters'] = array();
$GLOBALS['http_responses'] = array();
$GLOBALS['http_requests'] = array();
$GLOBALS['db_delta'] = array();

function get_option( $key, $default = false ) { global $options; return array_key_exists( $key, $options ) ? $options[ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { global $options; $options[ $key ] = $value; return true; }
function get_transient( $key ) { global $transients; return array_key_exists( $key, $transients ) ? $transients[ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { global $transients; $transients[ $key ] = $value; return true; }
function do_action( $hook, ...$args ) {}
function apply_filters( $hook, $value = null, ...$args ) {
	global $filters;

	return isset( $filters[ $hook ] ) ? call_user_func( $filters[ $hook ], $value, ...$args ) : $value;
}
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function absint( $value ) { return abs( (int) $value ); }
function esc_url_raw( $url ) { return preg_match( '#^https?://#i', (string) $url ) ? (string) $url : ''; }
function wp_kses_post( $value ) { return (string) $value; }
function wp_check_invalid_utf8( $value, $strip = false ) { return (string) $value; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function get_current_user_id() { return 0; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function __( $text, $domain = 'default' ) { return $text; }
function joinotify_prepare_receiver( $receiver, $payload = array() ) { return preg_replace( '/\D/', '', (string) $receiver ); }
function joinotify_convert_html_to_whatsapp( $text ) { return (string) $text; }

function wp_remote_request( $url, $args = array() ) {
	global $http_responses, $http_requests;
	$http_requests[] = array( 'url' => $url, 'args' => $args );

	return array_shift( $http_responses ) ?? array( 'code' => 500, 'body' => '' );
}
function wp_remote_retrieve_response_code( $response ) { return $response['code'] ?? 0; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_remote_retrieve_header( $response, $header ) { return ''; }

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return null; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

/**
 * Minimal $wpdb: every insert lands in a per-table row list, and a data/format
 * count mismatch — the bug a new column invites — is recorded as a failure.
 */
class Fake_Wpdb {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $rows = array();
	public $format_mismatches = 0;

	public function insert( $table, $data, $formats ) {
		if ( count( $data ) !== count( $formats ) ) {
			$this->format_mismatches++;
		}

		$this->rows[ $table ][] = $data;
		$this->insert_id++;

		return 1;
	}

	public function get_charset_collate() { return ''; }

	public function table( $name ) { return $this->rows[ $this->prefix . $name ] ?? array(); }
}

$GLOBALS['wpdb'] = new Fake_Wpdb();

}

// ---------------------------------------------------------------------------
// Namespaced collaborator fakes (must exist before the real classes load)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Admin {
	class Admin {
		public static $settings = array();

		public static function get_setting( $key ) { return self::$settings[ $key ] ?? ''; }
	}
}

namespace MeuMouse\Joinotify\Core {
	class Logger { public static function register_log( $m, $l = 'INFO' ) {} }

	class Helpers {
		public static $allowed = true;

		public static function allowed_sender( $sender ) { return self::$allowed; }
		public static function cloud_api_token() { return 'sk_test'; }
		public static function cloud_api_ready() { return true; }
		public static function cloud_phone_number_id() { return 'pn_1'; }
	}

	class Notification_Queue {
		public static function enqueue( $type, $payload, $error = '', $retry_after = 0 ) { return 'q_1'; }
	}
}

namespace {

require_once __DIR__ . '/../admin/src/Core/Message_History.php';
require_once __DIR__ . '/../admin/src/Core/Debug_Log.php';
require_once __DIR__ . '/../admin/src/Api/Message_Dispatch.php';
require_once __DIR__ . '/../admin/src/Api/Template_Repository.php';
require_once __DIR__ . '/../admin/src/Api/Cloud_Client.php';
require_once __DIR__ . '/../admin/src/Admin/History/Registry.php';

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Admin\History\Registry;
use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Api\Template_Repository;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Message_History;

/**
 * Reset every stubbed store between scenarios.
 */
function reset_state() {
	$GLOBALS['options'] = array( Message_History::DB_VERSION_OPTION => Message_History::DB_VERSION );
	$GLOBALS['transients'] = array();
	$GLOBALS['filters'] = array();
	$GLOBALS['http_responses'] = array();
	$GLOBALS['http_requests'] = array();
	$GLOBALS['db_delta'] = array();
	$GLOBALS['wpdb'] = new Fake_Wpdb();

	Admin::$settings = array(
		'enable_message_history' => 'yes',
		'enable_debug_logs' => 'yes',
		'enable_debug_mode' => 'no',
	);

	Helpers::$allowed = true;
	Message_History::clear_context();
}

/**
 * Seed the listing cache with one normalized template.
 */
function seed_cache( $templates ) {
	set_transient( Template_Repository::CACHE_PREFIX . md5( '' ), array( 'templates' => $templates ) );
}

/**
 * Normalized "order paid" template: a header, a body with two positional
 * variables, a footer and one URL button variable.
 */
function order_template( $overrides = array() ) {
	return array_merge( array(
		'id' => 't1',
		'name' => 'pedido_pago',
		'language' => 'pt_BR',
		'status' => 'APPROVED',
		'category' => 'UTILITY',
		'header' => 'Pedido {{1}}',
		'body' => 'Olá {{1}}, seu pedido {{2}} foi pago.',
		'footer' => 'Loja Exemplo',
		'variables' => array(
			array( 'component' => 'header', 'sub_type' => '', 'index' => 0, 'key' => '1', 'label' => '' ),
			array( 'component' => 'body', 'sub_type' => '', 'index' => 0, 'key' => '1', 'label' => '' ),
			array( 'component' => 'body', 'sub_type' => '', 'index' => 0, 'key' => '2', 'label' => '' ),
			array( 'component' => 'button', 'sub_type' => 'url', 'index' => 0, 'key' => '1', 'label' => 'Rastrear' ),
		),
	), $overrides );
}

/**
 * Components payload as Workflow_Processor::build_template_components() packs it.
 */
function order_components() {
	return array(
		array( 'type' => 'header', 'parameters' => array( array( 'type' => 'text', 'text' => '#1234' ) ) ),
		array( 'type' => 'body', 'parameters' => array(
			array( 'type' => 'text', 'text' => 'João' ),
			array( 'type' => 'text', 'text' => '#1234' ),
		) ),
		array( 'type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => array( array( 'type' => 'text', 'text' => 'abc123' ) ) ),
	);
}

/**
 * Queue a Cloud API answer for the next request.
 */
function queue_response( $code, $body ) {
	$GLOBALS['http_responses'][] = array( 'code' => $code, 'body' => json_encode( $body ) );
}

/**
 * Rows written to one of the plugin tables.
 */
function rows( $table ) {
	return $GLOBALS['wpdb']->table( $table );
}

/**
 * Decode a stored debug-log context.
 */
function context_of( $row ) {
	return json_decode( (string) ( $row['context'] ?? '' ), true ) ?: array();
}

echo "\nTemplate_Repository::render\n";
reset_state();
seed_cache( array( order_template() ) );

$rendered = Template_Repository::render( 'pedido_pago', 'pt_BR', order_components() );

check( 'header, body and footer are filled in order', "Pedido #1234\n\nOlá João, seu pedido #1234 foi pago.\n\nLoja Exemplo" === $rendered['text'] );
check( 'a known template renders from the snapshot', 'snapshot' === $rendered['rendered_from'] );
check( 'one parameter entry per value', 4 === count( $rendered['parameters'] ) );
check( 'the header value is labelled with its component', 'header' === $rendered['parameters'][0]['component'] && '#1234' === $rendered['parameters'][0]['value'] );
check( 'body values keep the template keys', '1' === $rendered['parameters'][1]['key'] && '2' === $rendered['parameters'][2]['key'] );
check( 'the button value keeps its index', 'button' === $rendered['parameters'][3]['component'] && 0 === $rendered['parameters'][3]['index'] && 'abc123' === $rendered['parameters'][3]['value'] );
check( 'nothing is masked by default', false === $rendered['masked'] && 'João' === $rendered['components'][1]['parameters'][0]['text'] );

seed_cache( array( order_template( array(
	'header' => '',
	'body' => 'Oi {{ nome }}, total {{total}}.',
	'footer' => '',
	'variables' => array(
		array( 'component' => 'body', 'sub_type' => '', 'index' => 0, 'key' => 'nome', 'label' => '' ),
		array( 'component' => 'body', 'sub_type' => '', 'index' => 0, 'key' => 'total', 'label' => '' ),
	),
) ) ) );

$named = Template_Repository::render( 'pedido_pago', 'pt_BR', array(
	array( 'type' => 'body', 'parameters' => array(
		array( 'type' => 'text', 'text' => 'Ana' ),
		array( 'type' => 'text', 'text' => 'R$ 10,00 \\o/' ),
	) ),
) );

check( 'named variables are filled by name', 'Oi Ana, total R$ 10,00 \\o/.' === $named['text'] );
check( 'named variables keep their names', 'nome' === $named['parameters'][0]['key'] && 'total' === $named['parameters'][1]['key'] );

$missing = Template_Repository::render( 'pedido_pago', 'pt_BR', array(
	array( 'type' => 'body', 'parameters' => array( array( 'type' => 'text', 'text' => 'Ana' ) ) ),
) );
check( 'a variable with no value stays visible in the text', 'Oi Ana, total {{total}}.' === $missing['text'] );

reset_state();
$fallback = Template_Repository::render( 'desconhecido', 'en_US', order_components() );

check( 'an unknown template renders from the fallback', 'fallback' === $fallback['rendered_from'] );
check( 'the fallback lists the name, language and every value', "desconhecido (en_US)\nheader {{1}} = #1234\n{{1}} = João\n{{2}} = #1234\nbutton #1 {{1}} = abc123" === $fallback['text'] );
check( 'an empty name renders nothing', '' === Template_Repository::render( '', 'pt_BR', array() )['text'] );

echo "\nTemplate_Repository masking\n";
reset_state();
seed_cache( array( order_template() ) );

$masked = Template_Repository::render( 'pedido_pago', 'pt_BR', order_components(), true );

check( 'masking hides the values in the text', false === strpos( $masked['text'], 'João' ) && false !== strpos( $masked['text'], Template_Repository::MASK ) );
check( 'masking hides the values in the parameter list', Template_Repository::MASK === $masked['parameters'][1]['value'] );
check( 'masking hides the values in the components payload', Template_Repository::MASK === $masked['components'][1]['parameters'][0]['text'] );
check( 'a masked render says so', true === $masked['masked'] );

seed_cache( array( order_template( array( 'name' => 'codigo_login', 'category' => 'AUTHENTICATION', 'header' => '', 'footer' => '', 'body' => 'Seu código é {{1}}.', 'variables' => array(
	array( 'component' => 'body', 'sub_type' => '', 'index' => 0, 'key' => '1', 'label' => '' ),
) ) ) ) );

$auth = Template_Repository::render( 'codigo_login', 'pt_BR', array(
	array( 'type' => 'body', 'parameters' => array( array( 'type' => 'text', 'text' => '482913' ) ) ),
) );
check( 'an AUTHENTICATION template is always masked', true === $auth['masked'] && 'Seu código é ' . Template_Repository::MASK . '.' === $auth['text'] );

echo "\nTemplate_Repository snapshot\n";
reset_state();

queue_response( 200, array(
	'data' => array(
		array(
			'id' => 't1',
			'name' => 'pedido_pago',
			'language' => 'pt_BR',
			'status' => 'APPROVED',
			'category' => 'UTILITY',
			'components' => array(
				array( 'type' => 'BODY', 'text' => 'Olá {{1}}.' ),
			),
		),
		array(
			'id' => 't2',
			'name' => 'pedido_pago',
			'language' => 'en_US',
			'status' => 'APPROVED',
			'category' => 'UTILITY',
			'components' => array(
				array( 'type' => 'BODY', 'text' => 'Hello {{1}}.' ),
			),
		),
	),
) );

Template_Repository::get_templates();
$snapshots = get_option( Template_Repository::SNAPSHOT_OPTION, array() );

check( 'a listing stores a snapshot per name and language', isset( $snapshots['pedido_pago|pt_BR'], $snapshots['pedido_pago|en_US'] ) );

// The 15-minute listing cache expires; the snapshot must still answer.
$GLOBALS['transients'] = array();

check( 'find() falls back to the snapshot on a cold cache', 'Olá {{1}}.' === ( Template_Repository::find( 'pedido_pago', 'pt_BR' )['body'] ?? '' ) );
check( 'find() prefers the requested language', 'Hello {{1}}.' === ( Template_Repository::find( 'pedido_pago', 'en_US' )['body'] ?? '' ) );
check( 'find() without a language still answers', null !== Template_Repository::find( 'pedido_pago' ) );

$cold = Template_Repository::render( 'pedido_pago', 'en_US', array(
	array( 'type' => 'body', 'parameters' => array( array( 'type' => 'text', 'text' => 'Ana' ) ) ),
) );
check( 'a cold cache still renders the template text', 'Hello Ana.' === $cold['text'] && 'snapshot' === $cold['rendered_from'] );

queue_response( 200, array( 'data' => array(
	array( 'id' => 't3', 'name' => 'boas_vindas', 'language' => 'pt_BR', 'status' => 'APPROVED', 'category' => 'MARKETING', 'components' => array() ),
) ) );
Template_Repository::get_templates( array( 'force' => true ) );
$snapshots = get_option( Template_Repository::SNAPSHOT_OPTION, array() );

check( 'a later listing merges instead of replacing', isset( $snapshots['pedido_pago|pt_BR'], $snapshots['boas_vindas|pt_BR'] ) );

echo "\nCloud_Client::send_message_template — delivered\n";
reset_state();
seed_cache( array( order_template() ) );
Message_History::set_context( array( 'source' => 'workflow', 'workflow_id' => 42 ) );

queue_response( 201, array( 'data' => array( 'messages' => array( array( 'id' => 'wamid.OK' ) ) ) ) );
$details = Cloud_Client::send_message_template( '5541987111527', '+55 41 98888-7777', 'pedido_pago', 'pt_BR', order_components(), 0, true, true );

$history = rows( 'joinotify_message_history' );
$meta = Message_History::decode_meta( $history[0]['meta'] ?? '' );

check( 'the send succeeds', ! empty( $details['success'] ) );
check( 'the payload sent to WhatsApp is untouched', 'João' === json_decode( $GLOBALS['http_requests'][0]['args']['body'], true )['components'][1]['parameters'][0]['text'] );
check( 'one history row is written', 1 === count( $history ) );
check( 'every history column has a format', 0 === $GLOBALS['wpdb']->format_mismatches );
check( 'the history content is the message the recipient read', "Pedido #1234\n\nOlá João, seu pedido #1234 foi pago.\n\nLoja Exemplo" === $history[0]['content'] );
check( 'the history meta keeps the template name and language', 'pedido_pago' === ( $meta['template']['name'] ?? '' ) && 'pt_BR' === ( $meta['template']['language'] ?? '' ) );
check( 'the history meta keeps every parameter', 4 === count( $meta['template']['parameters'] ?? array() ) );
check( 'the history meta keeps the components payload', 'abc123' === ( $meta['template']['components'][2]['parameters'][0]['text'] ?? '' ) );
check( 'the rendered text is not duplicated in meta', ! isset( $meta['template']['text'] ) );
check( 'with debug mode off a delivered send leaves no debug entry', 0 === count( rows( 'joinotify_debug_logs' ) ) );

reset_state();
seed_cache( array( order_template() ) );
Admin::$settings['enable_debug_mode'] = 'yes';
Message_History::set_context( array( 'source' => 'workflow', 'workflow_id' => 42 ) );

queue_response( 201, array( 'data' => array( 'messages' => array( array( 'id' => 'wamid.OK' ) ) ) ) );
Cloud_Client::send_message_template( '5541987111527', '5541988887777', 'pedido_pago', 'pt_BR', order_components(), 0, true, true );

$logs = rows( 'joinotify_debug_logs' );
$context = context_of( $logs[0] ?? array() );

check( 'with debug mode on a delivered send is logged at info', 1 === count( $logs ) && 'info' === $logs[0]['level'] );
check( 'the entry names the template, language and recipient', 'WhatsApp template "pedido_pago" (pt_BR) sent to 5541988887777' === $logs[0]['message'] );
check( 'the entry is coded dispatch_sent on the api channel', 'dispatch_sent' === $logs[0]['code'] && 'api' === $logs[0]['channel'] );
check( 'the context carries the origin', 'workflow' === $context['source'] && 42 === $context['workflow_id'] );
check( 'the context carries the template parameters', 'João' === ( $context['template']['parameters'][1]['value'] ?? '' ) );
check( 'the context carries the rendered message', 0 === strpos( (string) ( $context['content'] ?? '' ), 'Pedido #1234' ) );
check( 'the context carries the WhatsApp message id', 'wamid.OK' === ( $context['wamid'] ?? '' ) );

echo "\nCloud_Client::send_message_template — refused\n";
reset_state();
seed_cache( array( order_template() ) );

queue_response( 400, array( 'error' => array( 'type' => 'invalid_parameter', 'code' => 132000 ) ) );
Cloud_Client::send_message_template( '5541987111527', '5541988887777', 'pedido_pago', 'pt_BR', order_components(), 0, true, true );

$logs = rows( 'joinotify_debug_logs' );
$context = context_of( $logs[0] ?? array() );

check( 'a refused template is logged as an error without debug mode', 1 === count( $logs ) && 'error' === $logs[0]['level'] );
check( 'the error context carries the template block', 'pedido_pago' === ( $context['template']['name'] ?? '' ) && 4 === count( $context['template']['parameters'] ?? array() ) );
check( 'without debug mode the message body stays out of the log', ! isset( $context['content'] ) );
check( 'the failed history row still has the rendered message', 0 === strpos( rows( 'joinotify_message_history' )[0]['content'], 'Pedido #1234' ) );

reset_state();
seed_cache( array( order_template() ) );
Helpers::$allowed = false;

Cloud_Client::send_message_template( '5541987111527', '5541988887777', 'pedido_pago', 'pt_BR', order_components(), 0, true, true );
$meta = Message_History::decode_meta( rows( 'joinotify_message_history' )[0]['meta'] ?? '' );

check( 'a send stopped before the request is still described', 'pedido_pago' === ( $meta['template']['name'] ?? '' ) && 0 === count( $GLOBALS['http_requests'] ) );

echo "\nCloud_Client::send_message_template — OTP and redaction\n";
reset_state();
Admin::$settings['enable_debug_mode'] = 'yes';
Message_History::set_context( array( 'source' => 'otp' ) );

queue_response( 201, array( 'data' => array( 'messages' => array( array( 'id' => 'wamid.OTP' ) ) ) ) );
Cloud_Client::send_message_template( '5541987111527', '5541988887777', 'login_code', 'pt_BR', array(
	array( 'type' => 'body', 'parameters' => array( array( 'type' => 'text', 'text' => '482913' ) ) ),
	array( 'type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => array( array( 'type' => 'text', 'text' => '482913' ) ) ),
), 0, false, true );

$history = rows( 'joinotify_message_history' );
$logs = rows( 'joinotify_debug_logs' );

check( 'the code still reaches WhatsApp', false !== strpos( $GLOBALS['http_requests'][0]['args']['body'], '482913' ) );
check( 'the OTP row is recorded under the otp source', 'otp' === $history[0]['source'] );
check( 'the code never reaches the history', false === strpos( $history[0]['content'] . $history[0]['meta'], '482913' ) );
check( 'the code never reaches the debug log', false === strpos( (string) $logs[0]['context'], '482913' ) );
check( 'the OTP meta says it was masked', true === ( Message_History::decode_meta( $history[0]['meta'] )['template']['masked'] ?? null ) );

reset_state();
seed_cache( array( order_template() ) );
$GLOBALS['filters']['Joinotify/Api/Template_Dispatch_Log'] = function ( $template, $context ) {
	$template['text'] = 'redacted';
	$template['parameters'] = array();
	$template['components'] = array();

	return $template;
};

queue_response( 201, array( 'data' => array( 'messages' => array( array( 'id' => 'wamid.OK' ) ) ) ) );
Cloud_Client::send_message_template( '5541987111527', '5541988887777', 'pedido_pago', 'pt_BR', order_components(), 0, true, true );

$history = rows( 'joinotify_message_history' );

check( 'the filter can redact the recorded text', 'redacted' === $history[0]['content'] );
check( 'the filter can redact the recorded parameters', false === strpos( (string) $history[0]['meta'], 'João' ) );
check( 'the filter never changes what is sent', 'João' === json_decode( $GLOBALS['http_requests'][0]['args']['body'], true )['components'][1]['parameters'][0]['text'] );

echo "\nCloud_Client::send_message_text — debug mode\n";
reset_state();
Admin::$settings['enable_debug_mode'] = 'yes';

queue_response( 201, array( 'data' => array( 'messages' => array( array( 'id' => 'wamid.TXT' ) ) ) ) );
Cloud_Client::send_message_text( '5541987111527', '5541988887777', 'Olá!', 0, true, true );

$logs = rows( 'joinotify_debug_logs' );
$context = context_of( $logs[0] ?? array() );

check( 'a delivered text message is logged at info in debug mode', 1 === count( $logs ) && 'WhatsApp text message sent to 5541988887777' === $logs[0]['message'] );
check( 'a text entry carries its body and no template block', 'Olá!' === ( $context['content'] ?? '' ) && ! isset( $context['template'] ) );
check( 'a text history row has no meta', null === rows( 'joinotify_message_history' )[0]['meta'] );

echo "\nMessage_History schema guard\n";
reset_state();
$GLOBALS['options'][ Message_History::DB_VERSION_OPTION ] = '1.2.0';

Message_History::record( array( 'message_type' => 'text', 'status' => 'sent' ) );

check( 'a stale schema is migrated before the first write', 1 === count( $GLOBALS['db_delta'] ) && false !== strpos( $GLOBALS['db_delta'][0], 'meta LONGTEXT NULL' ) );
check( 'the migrated schema version is stored', Message_History::DB_VERSION === get_option( Message_History::DB_VERSION_OPTION ) );

Message_History::record( array( 'message_type' => 'text', 'status' => 'sent' ) );
check( 'a current schema is not migrated again', 1 === count( $GLOBALS['db_delta'] ) );

echo "\nHistory Registry::build_template\n";

check( 'a row without meta has no template', null === Registry::build_template( null ) );
check( 'a meta without a template has no template', null === Registry::build_template( '{"other":1}' ) );

$built = Registry::build_template( json_encode( array( 'template' => array(
	'name' => 'pedido_pago',
	'language' => 'pt_BR',
	'rendered_from' => 'snapshot',
	'masked' => false,
	'parameters' => array( array( 'component' => 'button', 'index' => '0', 'key' => 1, 'value' => 'abc123' ) ),
	'components' => array(),
) ) ) );

check( 'a stored template is exposed to the screen', 'pedido_pago' === $built['name'] && 'pt_BR' === $built['language'] );
check( 'parameters are normalized for the screen', 0 === $built['parameters'][0]['index'] && '1' === $built['parameters'][0]['key'] );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions failed\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
