<?php
/**
 * Standalone harness: external content -> AI model -> workflow actions.
 *
 * Feeds a hostile document (tests/fixtures/prompt-injection-document.txt) into a
 * workflow as customer data and replaces the AI provider with a model that OBEYS
 * the injection: whatever the document asks for, the model returns. The system
 * prompt is therefore irrelevant here — every assertion checks what the code does
 * with a compromised model output, which is the only barrier that counts.
 *
 * PASS lines are barriers enforced by code. LIMIT lines are paths where the model
 * output reaches an action argument with nothing but workflow configuration in
 * the way; they print the observed behavior and do not fail the run.
 *
 * The real Functions.php, Placeholders, AI_Request, AI_Response and
 * Workflow_Processor are loaded; only I/O collaborators (transport, channels,
 * AI provider, logger) are stubbed and record their arguments.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/ai-prompt-injection-test.php
 *
 * @since 2.5.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_DEV_MODE', false );
define( 'JOINOTIFY_DEBUG_MODE', false );

$failures = 0;
$assertions = 0;
$limits = 0;

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

/**
 * Record a known limitation: prints LIMIT when the weakness is present.
 */
function limit( $label, $weakness_present ) {
	global $limits;

	if ( $weakness_present ) {
		$limits++;
		echo "  LIMIT {$label}\n";
	} else {
		echo "  OK    (limitation closed) {$label}\n";
	}
}

// ---------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------

$GLOBALS['filter_overrides'] = array();

function apply_filters( $hook, $value = null ) {
	$args = func_get_args();

	if ( isset( $GLOBALS['filter_overrides'][ $hook ] ) ) {
		return call_user_func_array( $GLOBALS['filter_overrides'][ $hook ], array_slice( $args, 1 ) );
	}

	return $value;
}

function do_action() {}
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function __( $text, $domain = 'default' ) { return $text; }
function wc_get_order( $id ) { return false; }

class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

// Placeholders are filter-driven: register the trigger fields this workflow uses.
// "order_note" carries the hostile document, exactly like a WooCommerce customer note.
$GLOBALS['filter_overrides']['Joinotify/Builder/Placeholders_List'] = function( $list, $context ) {
	$field = function( $key ) {
		return array(
			'triggers' => array(),
			'replacement' => array(
				'production' => function( $payload ) use ( $key ) { return $payload[ $key ] ?? ''; },
			),
		);
	};

	return array(
		'test' => array(
			'{{ order_note }}' => $field( 'order_note' ),
			'{{ billing_phone }}' => $field( 'billing_phone' ),
			'{{ billing_email }}' => $field( 'billing_email' ),
		),
	);
};

}

// ---------------------------------------------------------------------------
// Namespaced collaborator fakes (must exist before the real classes load)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static function register_log( $m, $l = 'INFO' ) {}
		public static function stringify( $v ) { return json_encode( $v ); }
	}

	class Helpers {
		public static function validate_and_format_phone( $phone ) { return preg_replace( '/\D/', '', (string) $phone ); }
	}

	class Message_History {
		public static function set_context( $c ) {}
		public static function clear_context() {}
	}
}

namespace MeuMouse\Joinotify\Admin {
	class Admin { public static function get_setting( $key ) { return ''; } }
}

namespace MeuMouse\Joinotify\Integrations {
	class Woocommerce {}
}

namespace MeuMouse\Joinotify\Builder {
	class Attachments { public static function resolve( $a, $p ) { return array(); } }
}

namespace MeuMouse\Joinotify\Api {
	// Records every WhatsApp send the workflow attempts.
	class Transport {
		public static function send_message_text( $sender, $receiver, $message, $delay = 0 ) {
			$GLOBALS['whatsapp_sends'][] = compact( 'sender', 'receiver', 'message' );
			return 201;
		}

		public static function active_channel_id() { return 'whatsapp'; }
	}
}

namespace MeuMouse\Joinotify\Notifications {
	class Notification_Message {
		public $data;
		public static function from_array( $args ) { $m = new self(); $m->data = $args; return $m; }
	}

	class Dispatch_Result {
		public function is_success() { return true; }
		public function to_array() { return array(); }
	}

	// Records every e-mail / Telegram dispatch the workflow attempts.
	class Channel_Manager {
		public static function dispatch( $message ) {
			$GLOBALS['channel_sends'][] = $message->data;
			return new Dispatch_Result();
		}
	}
}

namespace MeuMouse\Joinotify\AI {
	/**
	 * A fully compromised model: when the prompt or system message carries the
	 * injected document, it returns exactly what the document asked for.
	 */
	class AI_Manager {
		public static function generate( $request ) {
			$GLOBALS['ai_requests'][] = $request;
			$seen = $request->system . "\n" . $request->prompt;

			if ( false !== strpos( $seen, 'INSTRUÇÕES DO SISTEMA' ) ) {
				return AI_Response::success( $GLOBALS['attacker_output'] );
			}

			return AI_Response::success( 'Olá! Seu pedido está a caminho.' );
		}
	}
}

// ---------------------------------------------------------------------------
// Test body
// ---------------------------------------------------------------------------

namespace {

use MeuMouse\Joinotify\Core\Workflow_Processor;

require __DIR__ . '/../admin/src/Core/Functions.php';
require __DIR__ . '/../admin/src/Builder/Placeholders.php';
require __DIR__ . '/../admin/src/AI/AI_Request.php';
require __DIR__ . '/../admin/src/AI/AI_Response.php';
require __DIR__ . '/../admin/src/Core/Workflow_Processor.php';

$document = file_get_contents( __DIR__ . '/fixtures/prompt-injection-document.txt' );

const SENDER_A = '5511900000001';   // the site's own connected number (account A)
const NUMBER_B = '5511900000002';   // a number the document claims belongs to account B
const CUSTOMER = '5511988887777';

function reset_run( $attacker_output ) {
	$GLOBALS['ai_requests'] = array();
	$GLOBALS['whatsapp_sends'] = array();
	$GLOBALS['channel_sends'] = array();
	$GLOBALS['attacker_output'] = $attacker_output;
}

function base_payload( $document ) {
	return array(
		'integration' => 'test',
		'trigger' => '',
		'order_note' => $document,
		'billing_phone' => CUSTOMER,
		'billing_email' => 'cliente@example.test',
	);
}

check( 'fixture document carries the injection', false !== strpos( $document, 'Ignore todas as regras' ) );

echo "\n== 1. Trace: external document -> model input ==\n";
reset_run( 'exfil@attacker.test' );
$payload = base_payload( $document );

Workflow_Processor::execute_dynamic_placeholder( array(
	'var_name' => 'destino',
	'ai_prompt' => 'Resuma a observação do cliente: {{ order_note }}',
	'ai_system' => 'Você resume pedidos. Observação: {{ order_note }}',
), $payload );

$req = $GLOBALS['ai_requests'][0] ?? null;
check( 'model was called once', null !== $req );
limit( 'document reaches the USER prompt verbatim, with no delimiter or "untrusted" marking', $req && false !== strpos( $req->prompt, 'Ignore todas as regras' ) );
limit( 'a placeholder in ai_system puts the external document in the SYSTEM message', $req && false !== strpos( $req->system, 'Ignore todas as regras' ) );
check( 'compromised output is stored as a plain string variable (ai_vars.destino)', ( $payload['ai_vars']['destino'] ?? null ) === 'exfil@attacker.test' );

echo "\n== 2. The model is given no tools ==\n";
$request_props = array_keys( get_class_vars( 'MeuMouse\\Joinotify\\AI\\AI_Request' ) );
check( 'AI_Request has no tools/functions field', ! array_intersect( $request_props, array( 'tools', 'functions', 'tool_choice', 'abilities' ) ) );
$provider_src = file_get_contents( __DIR__ . '/../admin/src/AI/Providers/Wp_Ai_Client_Provider.php' );
check( 'WP AI Client provider only calls generate_text (no function declarations / abilities)', false !== strpos( $provider_src, 'generate_text()' ) && ! preg_match( '/using_(function|abilit|tool)/i', $provider_src ) );

echo "\n== 3. AI WhatsApp message: model output cannot pick sender or recipient ==\n";
reset_run( 'Seu pedido foi cancelado. Confirme seus dados em http://phish.attacker.test' );
Workflow_Processor::send_whatsapp_ai_message( array(
	'sender' => SENDER_A,
	'receiver' => '{{ billing_phone }}',
	'ai_prompt' => 'Escreva um aviso sobre o pedido. Observação: {{ order_note }}',
), base_payload( $document ) );

$send = $GLOBALS['whatsapp_sends'][0] ?? array();
check( 'sender is the configured account-A number', ( $send['sender'] ?? '' ) === SENDER_A );
check( 'receiver is the trigger customer, not a number from the document', ( $send['receiver'] ?? '' ) === CUSTOMER );
limit( 'message BODY is whatever the compromised model wrote (sent without review)', false !== strpos( $send['message'] ?? '', 'phish.attacker.test' ) );

echo "\n== 4. WhatsApp recipient/sender wired to {{ ai:... }} ==\n";
reset_run( NUMBER_B );
$payload = base_payload( $document );
Workflow_Processor::execute_dynamic_placeholder( array( 'var_name' => 'destino', 'ai_prompt' => '{{ order_note }}' ), $payload );

Workflow_Processor::send_whatsapp_message_text( array(
	'sender' => '{{ ai:destino }}',
	'receiver' => '{{ ai:destino }}',
	'message' => 'teste',
), $payload );

$send = $GLOBALS['channel_sends'][0] ?? array();
check( 'phone recipient never resolves {{ ai:* }} (joinotify_prepare_receiver skips AI vars)', false === strpos( (string) ( $send['receiver'] ?? '' ), NUMBER_B ) );
check( 'sender is never placeholder-resolved, so the model cannot switch to account B\'s number', ( $send['sender'] ?? '' ) !== NUMBER_B );

echo "\n== 5. E-mail / Telegram recipient wired to {{ ai:... }} ==\n";
reset_run( 'exfil@attacker.test' );
$payload = base_payload( $document );
Workflow_Processor::execute_dynamic_placeholder( array( 'var_name' => 'destino', 'ai_prompt' => '{{ order_note }}' ), $payload );

Workflow_Processor::send_resend_email( array(
	'receiver' => '{{ ai:destino }}',
	'subject' => 'Resumo',
	'message' => 'Pedido de {{ billing_email }}',
), $payload );

$mail = $GLOBALS['channel_sends'][0] ?? array();
limit( 'Resend recipient resolves {{ ai:* }} -> the model chose ' . ( $mail['receiver'] ?? '?' ), ( $mail['receiver'] ?? '' ) === 'exfil@attacker.test' );

Workflow_Processor::send_telegram_message_text( array( 'receiver' => '{{ ai:destino }}', 'message' => 'x' ), $payload );
$tg = $GLOBALS['channel_sends'][1] ?? array();
limit( 'Telegram chat id resolves {{ ai:* }} as well', ( $tg['receiver'] ?? '' ) === 'exfil@attacker.test' );

echo "\n== 6. Second-order: external text smuggling an {{ ai:* }} token ==\n";
reset_run( 'segredo gerado pela IA' );
$payload = base_payload( 'Obrigado! {{ ai:destino }}' );
$payload['ai_vars'] = array( 'destino' => 'segredo gerado pela IA' );
$resolved = joinotify_prepare_message( 'Nota: {{ order_note }}', $payload );
limit( 'a token inside customer data is expanded after substitution ("' . $resolved . '")', false !== strpos( $resolved, 'segredo gerado pela IA' ) );

echo "\n== 7. Loop list fed by the model ==\n";
$payload = base_payload( $document );
$payload['ai_vars'] = array( 'lista' => "https://attacker.test/a\nhttps://attacker.test/b" );
$ref = new \ReflectionMethod( 'MeuMouse\\Joinotify\\Core\\Workflow_Processor', 'resolve_loop_items' );
$ref->setAccessible( true );
$items = $ref->invoke( null, array( 'loop_source' => 'placeholder_list', 'loop_list' => '{{ ai:lista }}' ), $payload );
limit( 'loop items come from model output (' . count( $items ) . ' items); {{ loop_item }} then resolves in any field, including the webhook URL', 2 === count( $items ) );

echo "\n----\n{$assertions} assertions, {$failures} failures, {$limits} known limitations\n";
exit( $failures > 0 ? 1 : 0 );

}
