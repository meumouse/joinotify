<?php
/**
 * Standalone test harness for the test message of the settings.
 *
 * Rest\Phone_Test_Message sends free text or an approved template and has to
 * answer at once with what happened: the message id on success, and on a
 * refusal a sentence that names the cause. Meta's own codes come first (the
 * hello_world template outside the public test numbers, a display name still
 * under review, a closed 24-hour window), then the client's failure code, then
 * WhatsApp's own explanation. The template variables arrive from the browser
 * and keep only the fields a template component is built from.
 *
 * Only the static helpers run here; WordPress is stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/phone-test-message-test.php
 *
 * @since 2.8.0
 */

// The class files guard with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = 'default' ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_text_field( $text ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}

	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

require __DIR__ . '/../admin/src/Api/Send_Error.php';
require __DIR__ . '/../admin/src/Rest/Abstract_Route.php';
require __DIR__ . '/../admin/src/Rest/Phone_Test_Message.php';

use MeuMouse\Joinotify\Api\Send_Error;
use MeuMouse\Joinotify\Rest\Phone_Test_Message;

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

echo "\n== Meta codes ==\n";

check( '131058 names the public test numbers', false !== strpos( Send_Error::describe_meta_code( 131058 ), 'public test numbers' ) );
check( '131037 names the display name review', false !== strpos( Send_Error::describe_meta_code( 131037 ), 'display name' ) );
check( '131047 reuses the closed-window sentence', Send_Error::describe( Send_Error::WINDOW_CLOSED ) === Send_Error::describe_meta_code( 131047 ) );
check( '132001 names the account of the sending number', false !== strpos( Send_Error::describe_meta_code( 132001 ), 'account of the sending number' ) );
check( 'an unknown Meta code answers an empty string', '' === Send_Error::describe_meta_code( 100 ) );
check( 'no Meta code answers an empty string', '' === Send_Error::describe_meta_code( 0 ) );

echo "\n== free text ==\n";

$result = Phone_Test_Message::text_response( array( 'success' => true, 'wamid' => 'wamid.HBg1', 'error' => '' ) );
check( 'a delivered text answers success', 'success' === $result['status'] );
check( 'a delivered text answers the message id', 'wamid.HBg1' === $result['message_id'] );
check( 'a delivered text answers delivery_status sent', 'sent' === $result['delivery_status'] );

$result = Phone_Test_Message::text_response( array( 'success' => false, 'error' => 'http_400', 'meta_code' => 131058 ) );
check( 'a bare 400 with Meta code 131058 is described by the code', 'error' === $result['status'] && Send_Error::describe_meta_code( 131058 ) === $result['message'] );
check( 'the refusal keeps the client code and the Meta code', 'http_400' === $result['error_code'] && 131058 === $result['meta_code'] );

$result = Phone_Test_Message::text_response( array( 'success' => false, 'error' => Send_Error::WINDOW_CLOSED, 'meta_code' => 131047 ) );
check( 'a closed window names the window', Send_Error::describe( Send_Error::WINDOW_CLOSED ) === $result['message'] );

$result = Phone_Test_Message::text_response( array( 'success' => false, 'error' => 'invalid_sender', 'meta_code' => 0, 'error_detail' => 'ignored' ) );
check( 'a known client code wins without the detail', Send_Error::describe( 'invalid_sender' ) === $result['message'] );

$result = Phone_Test_Message::text_response( array( 'success' => false, 'error' => 'http_400', 'meta_code' => 100, 'error_detail' => 'Invalid parameter.' ) );
check( 'an unknown refusal adds WhatsApp\'s own words', 'Could not send the test message. Invalid parameter.' === $result['message'] );

$result = Phone_Test_Message::text_response( null );
check( 'a non-array answer is an error, never a success', 'error' === $result['status'] );

echo "\n== template ==\n";

$result = Phone_Test_Message::template_response( array( 'message_id' => 'wamid.T1', 'status' => 'sent' ) );
check( 'a delivered template answers success with its id', 'success' === $result['status'] && 'wamid.T1' === $result['message_id'] && 'sent' === $result['delivery_status'] );

$error = new WP_Error( 'joinotify_send_failed', 'WhatsApp did not accept the message. Detail.', array( 'reason' => 'http_400', 'meta_code' => 131037 ) );
$result = Phone_Test_Message::template_response( $error );
check( 'a template refused for the display name names it', 'error' === $result['status'] && Send_Error::describe_meta_code( 131037 ) === $result['message'] );
check( 'the template refusal keeps the reason as error_code', 'http_400' === $result['error_code'] && 131037 === $result['meta_code'] );

$error = new WP_Error( 'joinotify_template_rejected', 'WhatsApp refused the template. (#132001) Template name does not exist in the translation.', array( 'reason' => 'template_error_132001', 'meta_code' => 132001 ) );
$result = Phone_Test_Message::template_response( $error );
check( 'a template of another account is explained', Send_Error::describe_meta_code( 132001 ) === $result['message'] && 'template_error_132001' === $result['error_code'] );

$error = new WP_Error( 'joinotify_template_rejected', 'WhatsApp refused the template. Parameter mismatch.', array( 'reason' => 'template_error_132000', 'meta_code' => 132000 ) );
$result = Phone_Test_Message::template_response( $error );
check( 'any other template refusal keeps the message of the send', 'WhatsApp refused the template. Parameter mismatch.' === $result['message'] );

echo "\n== variables ==\n";

$clean = Phone_Test_Message::sanitize_variables( array(
	array( 'component' => 'body', 'key' => 'nome', 'index' => '0', 'value' => " Ana\n<b>Maria</b> ", 'label' => 'Body · {{nome}}', 'extra' => 'dropped' ),
	array( 'component' => 'header', 'format' => 'IMAGE', 'key' => '', 'value' => 'https://example.com/a.jpg' ),
	array( 'component' => 'button', 'sub_type' => 'url', 'index' => '1', 'card' => '2', 'key' => '1', 'value' => 'order/7' ),
	'not an array',
	array( 'key' => 'no component' ),
	array( 'component' => array( 'body' ) ),
) );

check( 'entries without a component are dropped', 3 === count( $clean ) );
check( 'only the fields of a slot are kept', array( 'card', 'component', 'sub_type', 'index', 'key', 'format', 'value' ) === array_keys( $clean[0] ) );
check( 'a value is cleaned to one line of text', 'Ana Maria' === $clean[0]['value'] );
check( 'a slot outside a carousel gets card -1', -1 === $clean[0]['card'] );
check( 'the index becomes an integer', 0 === $clean[0]['index'] && 1 === $clean[2]['index'] );
check( 'the format is lowercased', 'image' === $clean[1]['format'] );
check( 'a carousel card is kept as an integer', 2 === $clean[2]['card'] );
check( 'a named key is kept as written', 'nome' === $clean[0]['key'] );
check( 'a non-array list is empty', array() === Phone_Test_Message::sanitize_variables( 'x' ) );

$many = array_fill( 0, Phone_Test_Message::MAX_VARIABLES + 20, array( 'component' => 'body', 'value' => 'x' ) );
check( 'the list is capped', Phone_Test_Message::MAX_VARIABLES === count( Phone_Test_Message::sanitize_variables( $many ) ) );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
