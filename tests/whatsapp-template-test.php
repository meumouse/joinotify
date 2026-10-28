<?php
/**
 * Standalone test harness for the WhatsApp Cloud API message templates.
 *
 * Covers the two halves that must agree for a template to be delivered: the
 * read side (Template_Repository normalizing what the API returns and listing
 * the variables each template expects, in both Meta dialects) and the write
 * side (Workflow_Processor turning the builder variable map into Meta's
 * `components` payload, grouped per component, with buttons addressed one by
 * one and named variables labelled with their `parameter_name`). No WordPress
 * bootstrap is required.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/whatsapp-template-test.php
 *
 * @since 2.3.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_DEV_MODE', false );
define( 'JOINOTIFY_DEBUG_MODE', false );
define( 'MB_IN_BYTES', 1024 * 1024 );

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

$GLOBALS['transients'] = array();

function get_transient( $key ) { global $transients; return array_key_exists( $key, $transients ) ? $transients[ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { global $transients; $transients[ $key ] = $value; return true; }
function delete_transient( $key ) { global $transients; unset( $transients[ $key ] ); return true; }
function get_option( $key, $default = false ) { return $default; }
function update_option( $key, $value ) { return true; }
function apply_filters( $hook, $value = null ) { return $value; }
function do_action() {}
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = 'default' ) { return $text; }
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

// The template variable values go through the same placeholder engine as any
// other message, so a literal echo is enough to assert the wiring.
function joinotify_prepare_message( $message, $payload = array() ) { return is_scalar( $message ) ? (string) $message : ''; }
function joinotify_prepare_receiver( $receiver, $payload = array() ) { return preg_replace( '/\D+/', '', (string) $receiver ); }

// Mirrors the real helper: tags out, entities decoded, non-breaking spaces flattened.
function joinotify_format_plain_text( $content ) {
	return (string) preg_replace( '/\x{00A0}|\x{202F}/u', ' ', html_entity_decode( strip_tags( (string) $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

}

// ---------------------------------------------------------------------------
// Namespaced collaborator fakes (must exist before the real classes load)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Helpers {
		public static $ready = true;

		public static function cloud_api_ready() { return self::$ready; }
	}

	class Logger { public static function register_log( $m, $l = 'INFO' ) {} }
}

namespace MeuMouse\Joinotify\Builder {
	class Placeholders {
		public static function replace_placeholders( $message, $payload = array(), $mode = 'production' ) {
			return 'sandbox' === $mode ? '[' . $message . ']' : (string) $message;
		}
	}
}

namespace MeuMouse\Joinotify\Api {
	/**
	 * Answers template listings from a scripted response.
	 */
	class Cloud_Client {
		public static $response = array();
		public static $calls = array();

		public static function list_templates( $args = array() ) {
			self::$calls[] = $args;

			return self::$response;
		}

		public static function sync_templates() { return array( 'data' => array() ); }
	}
}

namespace {

require_once __DIR__ . '/../admin/src/Api/Template_Repository.php';
require_once __DIR__ . '/../admin/src/Core/Workflow_Processor.php';

use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Api\Template_Repository;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Workflow_Processor;

/**
 * Reset every stubbed store between scenarios.
 */
function reset_state() {
	$GLOBALS['transients'] = array();
	Cloud_Client::$response = array();
	Cloud_Client::$calls = array();
	Helpers::$ready = true;
}

echo "\nTemplate_Repository normalization\n";
reset_state();

Cloud_Client::$response = array(
	'wabaId' => 'waba_1',
	'syncedAt' => '2026-08-14T10:00:00Z',
	'data' => array(
		array(
			'id' => 'tpl_1',
			'name' => 'confirmacao_pedido',
			'language' => 'pt_BR',
			'status' => 'approved',
			'category' => 'utility',
			'quality_score' => array( 'score' => 'GREEN' ),
			'components' => array(
				array( 'type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Pedido {{1}} confirmado' ),
				array( 'type' => 'BODY', 'text' => 'Olá {{1}}, seu pedido {{2}} chega em {{3}}.' ),
				array( 'type' => 'FOOTER', 'text' => 'Joinotify' ),
				array(
					'type' => 'BUTTONS',
					'buttons' => array(
						array( 'type' => 'QUICK_REPLY', 'text' => 'Ok' ),
						array( 'type' => 'URL', 'text' => 'Acompanhar', 'url' => 'https://exemplo.com/pedidos/{{1}}' ),
					),
				),
			),
		),
	),
);

$result = Template_Repository::get_templates();
$template = $result['templates'][0] ?? array();

check( 'the listing is returned', ! is_wp_error( $result ) && 1 === count( $result['templates'] ) );
check( 'status is upper-cased', 'APPROVED' === $template['status'] );
check( 'category is upper-cased', 'UTILITY' === $template['category'] );
check( 'quality score is flattened', 'GREEN' === $template['quality'] );
check( 'header text is extracted', 'Pedido {{1}} confirmado' === $template['header'] );
check( 'body text is extracted', 'Olá {{1}}, seu pedido {{2}} chega em {{3}}.' === $template['body'] );
check( 'footer text is extracted', 'Joinotify' === $template['footer'] );
check( 'waba id is carried over', 'waba_1' === $result['waba_id'] );
check( 'synced_at is carried over', '2026-08-14T10:00:00Z' === $result['synced_at'] );

$variables = $template['variables'];

check( 'every variable slot is listed', 5 === count( $variables ) );
check( 'the header variable comes first', 'header' === $variables[0]['component'] && '1' === $variables[0]['key'] );
check( 'body variables keep their order', 'body' === $variables[1]['component'] && '1' === $variables[1]['key'] && '3' === $variables[3]['key'] );
check( 'only URL buttons contribute a variable', 'button' === $variables[4]['component'] && 'url' === $variables[4]['sub_type'] );
check( 'the button variable carries its position', 1 === $variables[4]['index'] );

echo "\nTemplate_Repository named variables and edge cases\n";
reset_state();

Cloud_Client::$response = array(
	'data' => array(
		array(
			'name' => 'nomeado',
			'components' => array(
				array( 'type' => 'BODY', 'text' => 'Olá {{ nome }}, pedido {{numero}} e de novo {{nome}}.' ),
			),
		),
	),
);

$variables = Template_Repository::get_templates()['templates'][0]['variables'];

check( 'named variables are read', 2 === count( $variables ) );
check( 'whitespace inside the braces is tolerated', 'nome' === $variables[0]['key'] );
check( 'a repeated variable is listed once', 'numero' === $variables[1]['key'] );

reset_state();
Cloud_Client::$response = array( 'data' => array( array( 'name' => 'sem_variaveis', 'components' => array( array( 'type' => 'BODY', 'text' => 'Tudo certo!' ) ) ) ) );
check( 'a template without variables yields an empty map', array() === Template_Repository::get_templates()['templates'][0]['variables'] );

reset_state();
Cloud_Client::$response = array( 'error' => array( 'message' => 'No business account.', 'type' => 'no_waba' ) );
check( 'an API error surfaces as WP_Error', is_wp_error( Template_Repository::get_templates() ) );

reset_state();
Helpers::$ready = false;
check( 'listing without a token reports it', is_wp_error( Template_Repository::get_templates() ) );

echo "\nTemplate_Repository caching\n";
reset_state();

Cloud_Client::$response = array( 'data' => array( array( 'name' => 'a', 'components' => array() ) ) );

Template_Repository::get_templates();
Template_Repository::get_templates();
check( 'a successful listing is served from cache', 1 === count( Cloud_Client::$calls ) );

Template_Repository::get_templates( array( 'force' => true ) );
check( 'force bypasses the cache', 2 === count( Cloud_Client::$calls ) );
check( 'force asks the API to refresh its mirror', true === Cloud_Client::$calls[1]['refresh'] );
check( 'a plain listing serves the mirror', false === Cloud_Client::$calls[0]['refresh'] );

reset_state();
Cloud_Client::$response = array( 'data' => array() );
Template_Repository::get_templates();
Template_Repository::get_templates();
check( 'an empty listing is never cached', 2 === count( Cloud_Client::$calls ) );

reset_state();
Cloud_Client::$response = array( 'data' => array( array( 'name' => 'a', 'components' => array() ) ) );
Template_Repository::get_templates( array( 'waba_id' => 'waba_1' ) );
Template_Repository::get_templates( array( 'waba_id' => 'waba_2' ) );
check( 'each business account gets its own cache entry', 2 === count( Cloud_Client::$calls ) );
check( 'the business account is forwarded to the API', 'waba_2' === Cloud_Client::$calls[1]['waba_id'] );

echo "\nWorkflow_Processor::build_template_components\n";

$components = Workflow_Processor::build_template_components( array(
	array( 'component' => 'header', 'key' => '1', 'index' => 0, 'value' => 'Pedido 1042' ),
	array( 'component' => 'body', 'key' => '1', 'index' => 0, 'value' => 'Maria' ),
	array( 'component' => 'body', 'key' => '2', 'index' => 0, 'value' => '1042' ),
	array( 'component' => 'button', 'sub_type' => 'url', 'key' => '1', 'index' => 1, 'value' => '1042' ),
), array() );

check( 'one entry per component bucket', 3 === count( $components ) );
check( 'component types are lower-cased for Meta', 'header' === $components[0]['type'] && 'body' === $components[1]['type'] );
check( 'body parameters are grouped together in order', 2 === count( $components[1]['parameters'] ) && 'Maria' === $components[1]['parameters'][0]['text'] && '1042' === $components[1]['parameters'][1]['text'] );
check( 'parameters carry the text type', 'text' === $components[1]['parameters'][0]['type'] );
check( 'buttons declare sub_type and index', 'url' === $components[2]['sub_type'] && '1' === $components[2]['index'] );
check( 'the header keeps its own bucket', 1 === count( $components[0]['parameters'] ) );
check( 'non-button components carry no index', ! array_key_exists( 'index', $components[1] ) );
check(
	'positional parameters carry no parameter_name',
	! array_key_exists( 'parameter_name', $components[0]['parameters'][0] )
		&& ! array_key_exists( 'parameter_name', $components[1]['parameters'][0] )
		&& ! array_key_exists( 'parameter_name', $components[2]['parameters'][0] )
);

// A template created with named variables is refused by Meta unless every
// header and body parameter names the variable it fills.
$components = Workflow_Processor::build_template_components( array(
	array( 'component' => 'header', 'key' => 'pedido', 'index' => 0, 'value' => '1042' ),
	array( 'component' => 'body', 'key' => 'nome', 'index' => 0, 'value' => 'Maria' ),
	array( 'component' => 'body', 'key' => 'data_entrega', 'index' => 0, 'value' => '15/09' ),
	array( 'component' => 'button', 'sub_type' => 'url', 'key' => '1', 'index' => 0, 'value' => '1042' ),
), array() );

check( 'named body parameters carry their name', 'nome' === ( $components[1]['parameters'][0]['parameter_name'] ?? null ) && 'data_entrega' === ( $components[1]['parameters'][1]['parameter_name'] ?? null ) );
check( 'a named header parameter carries its name', 'pedido' === ( $components[0]['parameters'][0]['parameter_name'] ?? null ) );
check( 'a named parameter keeps the Meta shape', array( 'type' => 'text', 'parameter_name' => 'nome', 'text' => 'Maria' ) === $components[1]['parameters'][0] );
check( 'button parameters never carry a name', ! array_key_exists( 'parameter_name', $components[2]['parameters'][0] ) );

$components = Workflow_Processor::build_template_components( array(
	array( 'component' => 'body', 'value' => 'no key' ),
	array( 'component' => 'body', 'key' => '', 'value' => 'empty key' ),
), array() );

check( 'a variable without a key is sent positionally', ! array_key_exists( 'parameter_name', $components[0]['parameters'][0] ) && ! array_key_exists( 'parameter_name', $components[0]['parameters'][1] ) );

// Two URL buttons are addressed one by one, never merged.
$components = Workflow_Processor::build_template_components( array(
	array( 'component' => 'button', 'sub_type' => 'url', 'key' => '1', 'index' => 0, 'value' => 'a' ),
	array( 'component' => 'button', 'sub_type' => 'url', 'key' => '1', 'index' => 1, 'value' => 'b' ),
), array() );

check( 'each button index gets its own component', 2 === count( $components ) );
check( 'button indexes are preserved', '0' === $components[0]['index'] && '1' === $components[1]['index'] );

check( 'an empty variable map yields no components', array() === Workflow_Processor::build_template_components( array(), array() ) );
check( 'a non-array variable map is tolerated', array() === Workflow_Processor::build_template_components( 'nope', array() ) );

// Footers cannot carry variables, so an unknown component is dropped instead of
// being sent to Meta as an invalid payload.
$components = Workflow_Processor::build_template_components( array(
	array( 'component' => 'footer', 'key' => '1', 'value' => 'x' ),
	array( 'component' => 'body', 'key' => '1', 'value' => 'ok' ),
), array() );

check( 'unsupported components are dropped', 1 === count( $components ) && 'body' === $components[0]['type'] );

$components = Workflow_Processor::build_template_components( array(
	array( 'component' => 'body', 'key' => '1', 'value' => '{{ wc_billing_first_name }}' ),
), array(), 'sandbox' );

check( 'sandbox mode resolves through the sandbox placeholder path', '[{{ wc_billing_first_name }}]' === $components[0]['parameters'][0]['text'] );

echo "\nWorkflow_Processor template parameter text\n";

/**
 * Text of the single body parameter built from one resolved value.
 */
function parameter_text( $value ) {
	$components = Workflow_Processor::build_template_components( array(
		array( 'component' => 'body', 'key' => 'valor', 'value' => $value ),
	), array() );

	return $components[0]['parameters'][0]['text'] ?? null;
}

check( 'an integer value goes out as a digit string', '1042' === parameter_text( 1042 ) );
check( 'a decimal value goes out as a string', '19.9' === parameter_text( 19.9 ) );
check( 'a numeric string is kept as is', '0042' === parameter_text( '0042' ) );
check( 'a numeric value keeps the text type', array( 'type' => 'text', 'parameter_name' => 'valor', 'text' => '1042' ) === Workflow_Processor::build_template_components( array(
	array( 'component' => 'body', 'key' => 'valor', 'value' => 1042 ),
), array() )[0]['parameters'][0] );
check( 'a plain single-line value is untouched', 'Maria da Silva' === parameter_text( 'Maria da Silva' ) );

// Meta refuses line breaks, tabs and more than four spaces in a row (132018).
check( 'line breaks are joined with a comma', 'Rua das Flores, 123, Centro, Alagoa - MG' === parameter_text( "Rua das Flores, 123\nCentro\r\nAlagoa - MG" ) );
check( 'a line ending in a comma gets no second one', 'Rua A, 1, Centro' === parameter_text( "Rua A, 1,\nCentro" ) );
check( 'blank lines are dropped', 'a, b' === parameter_text( "a\n\n\nb\n" ) );
check( 'tabs and runs of spaces collapse to one space', 'a b c' === parameter_text( "a\t\tb      c" ) );
check( 'markup line breaks become separators', 'Maria, Rua A, 1, Alagoa - MG' === parameter_text( 'Maria<br/>Rua A, 1<br>Alagoa - MG' ) );
check( 'tags and entities are removed', 'R$ 20,00 via SEDEX' === parameter_text( '<span class="amount"><bdi><span>&#82;&#36;</span>&nbsp;20,00</bdi></span>&nbsp;<small class="shipped_via">via SEDEX</small>' ) );
check( 'an empty value stays empty', '' === parameter_text( '' ) );
check( 'a button value is flattened too', 'a, b' === Workflow_Processor::build_template_components( array(
	array( 'component' => 'button', 'sub_type' => 'url', 'key' => '1', 'index' => 0, 'value' => "a\nb" ),
), array() )[0]['parameters'][0]['text'] );

echo "\nTemplate_Repository header format and button links\n";
reset_state();

Cloud_Client::$response = array(
	'data' => array(
		array(
			'name' => 'carrinho_foto',
			'language' => 'pt_BR',
			'status' => 'APPROVED',
			'components' => array(
				array( 'type' => 'HEADER', 'format' => 'IMAGE' ),
				array( 'type' => 'BODY', 'text' => 'Oi {{1}}' ),
				array( 'type' => 'BUTTONS', 'buttons' => array( array( 'type' => 'URL', 'text' => 'Voltar', 'url' => 'https://loja.test/?sb_recover={{1}}' ) ) ),
			),
		),
		array( 'name' => 'texto', 'language' => 'pt_BR', 'components' => array( array( 'type' => 'HEADER', 'text' => 'Olá' ), array( 'type' => 'BODY', 'text' => 'x' ) ) ),
		array( 'name' => 'sem_cabecalho', 'language' => 'pt_BR', 'components' => array( array( 'type' => 'BODY', 'text' => 'x' ) ) ),
	),
);

$templates = Template_Repository::get_templates()['templates'];

check( 'a media header reports its format', 'IMAGE' === $templates[0]['header_format'] );
check( 'a header without a format reads as text', 'TEXT' === $templates[1]['header_format'] );
check( 'a template without a header has no format', '' === $templates[2]['header_format'] );
check( 'the media header comes before the body', 'image' === $templates[0]['variables'][0]['format'] && 'body' === $templates[0]['variables'][1]['component'] );
check( 'a button variable carries the button url', 'https://loja.test/?sb_recover={{1}}' === $templates[0]['variables'][2]['url'] );
check( 'a body variable has an empty url', '' === $templates[0]['variables'][1]['url'] );

echo "\nWorkflow_Processor::components_from_values\n";

$components = Workflow_Processor::components_from_values( array(
	array( 'component' => 'header', 'format' => 'image', 'key' => '', 'value' => ' https://loja.test/foto.jpg ' ),
	array( 'component' => 'body', 'key' => 'nome', 'value' => 'Ana {{ first_name }}' ),
	array( 'component' => 'body', 'key' => 'itens', 'value' => "Camisa\nCalça" ),
	array( 'component' => 'button', 'sub_type' => 'url', 'index' => 0, 'key' => '1', 'value' => 'abc123' ),
) );

check( 'a media header goes out as a link', array( 'type' => 'header', 'parameters' => array( array( 'type' => 'image', 'image' => array( 'link' => 'https://loja.test/foto.jpg' ) ) ) ) === $components[0] );
check( 'values are not run through the placeholders', 'Ana {{ first_name }}' === $components[1]['parameters'][0]['text'] );
check( 'named body variables keep their parameter_name', 'nome' === $components[1]['parameters'][0]['parameter_name'] );
check( 'resolved values are still brought to one line', 'Camisa, Calça' === $components[1]['parameters'][1]['text'] );
check( 'button values keep their index', '0' === $components[2]['index'] && 'abc123' === $components[2]['parameters'][0]['text'] );
check( 'the workflow builder resolves and packs the same way', Workflow_Processor::build_template_components( array(
	array( 'component' => 'body', 'key' => '1', 'value' => 'x' ),
), array() ) === Workflow_Processor::components_from_values( array( array( 'component' => 'body', 'key' => '1', 'value' => 'x' ) ) ) );

echo "\nTemplate_Repository values without a token\n";
reset_state();

Cloud_Client::$response = array(
	'data' => array(
		array(
			'name' => 'promo_video',
			'language' => 'pt_BR',
			'category' => 'MARKETING',
			'components' => array(
				array( 'type' => 'HEADER', 'format' => 'VIDEO' ),
				array( 'type' => 'BODY', 'text' => 'Oi {{1}}, use o cupom abaixo.' ),
				array(
					'type' => 'BUTTONS',
					'buttons' => array(
						array( 'type' => 'COPY_CODE', 'text' => 'Copiar código' ),
						array( 'type' => 'URL', 'text' => 'Comprar', 'url' => 'https://loja.test/' ),
						array( 'type' => 'QUICK_REPLY', 'text' => 'Parar' ),
					),
				),
			),
		),
		array(
			'name' => 'codigo_login',
			'language' => 'pt_BR',
			'category' => 'AUTHENTICATION',
			'components' => array(
				array( 'type' => 'BODY', 'text' => '*{{1}}* é seu código de verificação.' ),
				array( 'type' => 'BUTTONS', 'buttons' => array( array( 'type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => 'Copiar código' ) ) ),
			),
		),
	),
);

$templates = Template_Repository::get_templates()['templates'];
$variables = $templates[0]['variables'];

check( 'a media header is listed as a variable', 'header' === $variables[0]['component'] && 'video' === $variables[0]['format'] && '' === $variables[0]['key'] );
check( 'a text variable has an empty format', '' === $variables[1]['format'] && '1' === $variables[1]['key'] );
check( 'a copy-code button is listed with its sub-type', 'button' === $variables[2]['component'] && 'copy_code' === $variables[2]['sub_type'] && 0 === $variables[2]['index'] );
check( 'a URL button without a token and a quick reply add nothing', 3 === count( $variables ) );
check( 'the buttons are listed for the preview', 3 === count( $templates[0]['buttons'] ) && 'COPY_CODE' === $templates[0]['buttons'][0]['type'] && 'Comprar' === $templates[0]['buttons'][1]['text'] );

$variables = $templates[1]['variables'];

check( 'an OTP button is listed as a URL button holding the code', 'button' === $variables[1]['component'] && 'url' === $variables[1]['sub_type'] && 'otp' === $variables[1]['format'] );

echo "\nWorkflow_Processor values without a token\n";

$components = Workflow_Processor::components_from_values( array(
	array( 'component' => 'header', 'format' => 'document', 'key' => '', 'value' => 'https://loja.test/notas/nota%20fiscal.pdf?v=2' ),
	array( 'component' => 'body', 'key' => '1', 'value' => 'Ana' ),
	array( 'component' => 'button', 'sub_type' => 'copy_code', 'index' => 0, 'key' => '', 'value' => ' BLACK20 ' ),
) );

check( 'a document header carries its file name', array( 'link' => 'https://loja.test/notas/nota%20fiscal.pdf?v=2', 'filename' => 'nota fiscal.pdf' ) === $components[0]['parameters'][0]['document'] );
check( 'a copy-code button sends the coupon', array( 'type' => 'button', 'parameters' => array( array( 'type' => 'coupon_code', 'coupon_code' => 'BLACK20' ) ), 'sub_type' => 'copy_code', 'index' => '0' ) === $components[2] );

$components = Workflow_Processor::components_from_values( array(
	array( 'component' => 'header', 'format' => 'image', 'key' => '', 'value' => ' 1234567890 ' ),
) );

check( 'a media id goes out as an id', array( 'id' => '1234567890' ) === $components[0]['parameters'][0]['image'] );

$components = Workflow_Processor::components_from_values( array(
	array( 'component' => 'body', 'key' => '1', 'value' => '482913' ),
	array( 'component' => 'button', 'sub_type' => 'url', 'format' => 'otp', 'index' => 0, 'key' => '', 'value' => '' ),
) );

check( 'an empty OTP button repeats the body code', array( array( 'type' => 'text', 'text' => '482913' ) ) === $components[1]['parameters'] );

$components = Workflow_Processor::components_from_values( array(
	array( 'component' => 'body', 'key' => '1', 'value' => '482913' ),
	array( 'component' => 'button', 'sub_type' => 'url', 'format' => 'otp', 'index' => 0, 'key' => '', 'value' => '111222' ),
) );

check( 'a filled OTP button keeps its own value', '111222' === $components[1]['parameters'][0]['text'] );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions failed\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
