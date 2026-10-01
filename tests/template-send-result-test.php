<?php
/**
 * Standalone test harness for the outcome of joinotify_send_whatsapp_template().
 *
 * The helper hands the details of a template send to
 * Transport::template_send_result(), which turns them into
 * `{ message_id, status }` or a WP_Error whose data keeps the HTTP status of
 * the Joinotify API and the reason — what an integration (a login code over
 * WhatsApp, say) needs to decide whether to fall back to another channel or to
 * wait. The method calls nothing from WordPress beyond `__()` and WP_Error,
 * both stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/template-send-result-test.php
 *
 * @since 2.6.0
 */

// The class file guards with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = 'default' ) { return $text; }

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

require __DIR__ . '/../admin/src/Api/Transport.php';

use MeuMouse\Joinotify\Api\Transport;

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

/**
 * Details as Cloud_Client returns them with `$return_details = true`.
 */
function details( $overrides = array() ) {
	return array_merge( array(
		'response_code' => 0,
		'success' => false,
		'retryable' => false,
		'error' => '',
		'queued' => false,
		'queue_id' => '',
	), $overrides );
}

echo "\n== delivered and queued ==\n";

$result = Transport::template_send_result( details( array( 'response_code' => 201, 'success' => true, 'wamid' => 'wamid.HBg1', 'meta_code' => 0, 'retry_after' => 0 ) ) );
check( 'a delivered send answers the message id', is_array( $result ) && 'wamid.HBg1' === $result['message_id'] );
check( 'a delivered send answers status sent', is_array( $result ) && 'sent' === $result['status'] );
check( 'the answer has exactly message_id and status', is_array( $result ) && array( 'message_id', 'status' ) === array_keys( $result ) );

$result = Transport::template_send_result( details( array( 'response_code' => 503, 'retryable' => true, 'queued' => true, 'queue_id' => 'q_1', 'error' => 'service_unavailable', 'wamid' => '' ) ) );
check( 'a send put in the retry queue answers status queued', is_array( $result ) && 'queued' === $result['status'] && '' === $result['message_id'] );

echo "\n== refusals by the API ==\n";

$result = Transport::template_send_result( details( array( 'response_code' => 402, 'error' => 'payment_required', 'error_detail' => 'Billing blocked', 'wamid' => '', 'meta_code' => 0, 'retry_after' => 0 ) ) );
$data = $result instanceof WP_Error ? $result->get_error_data() : array();
check( '402 is joinotify_payment_required', $result instanceof WP_Error && 'joinotify_payment_required' === $result->get_error_code() );
check( '402 keeps the HTTP status', 402 === $data['status'] && 402 === $data['response_code'] );
check( '402 keeps the Joinotify reason', 'payment_required' === $data['reason'] );
check( "402 keeps WhatsApp's explanation in the data and the message", 'Billing blocked' === $data['detail'] && false !== strpos( $result->get_error_message(), 'Billing blocked' ) );

$result = Transport::template_send_result( details( array( 'response_code' => 429, 'retryable' => true, 'error' => 'rate_limited', 'wamid' => '', 'meta_code' => 0, 'retry_after' => 30 ) ) );
$data = $result instanceof WP_Error ? $result->get_error_data() : array();
check( '429 is joinotify_rate_limited', $result instanceof WP_Error && 'joinotify_rate_limited' === $result->get_error_code() );
check( '429 carries retry_after in seconds', 30 === ( $data['retry_after'] ?? null ) );
check( '429 is retryable', true === $data['retryable'] );

$result = Transport::template_send_result( details( array( 'response_code' => 401, 'error' => 'authentication', 'wamid' => '' ) ) );
check( '401 is joinotify_unauthorized', $result instanceof WP_Error && 'joinotify_unauthorized' === $result->get_error_code() );

$result = Transport::template_send_result( details( array( 'response_code' => 400, 'error' => 'template_error_132001', 'wamid' => '', 'meta_code' => 132001 ) ) );
$data = $result instanceof WP_Error ? $result->get_error_data() : array();
check( 'a template Meta refused is joinotify_template_rejected', $result instanceof WP_Error && 'joinotify_template_rejected' === $result->get_error_code() );
check( "Meta's error code is kept", 132001 === $data['meta_code'] );

$result = Transport::template_send_result( details( array( 'response_code' => 422, 'error' => 'invalid_request', 'wamid' => '' ) ) );
check( 'any other refusal is joinotify_send_failed with its status', $result instanceof WP_Error && 'joinotify_send_failed' === $result->get_error_code() && 422 === $result->get_error_data()['status'] );

echo "\n== failures before the API ==\n";

$result = Transport::template_send_result( details( array( 'error' => 'invalid_sender' ) ) );
check( 'a sender that is not connected is joinotify_invalid_sender (400)', $result instanceof WP_Error && 'joinotify_invalid_sender' === $result->get_error_code() && 400 === $result->get_error_data()['status'] );

$result = Transport::template_send_result( details( array( 'error' => 'missing_template_name' ) ) );
check( 'a missing template name is joinotify_template_missing (400)', $result instanceof WP_Error && 'joinotify_template_missing' === $result->get_error_code() && 400 === $result->get_error_data()['status'] );

$result = Transport::template_send_result( details( array( 'error' => 'cloud_no_token', 'retryable' => true ) ) );
check( 'a site without a key is joinotify_not_connected (503)', $result instanceof WP_Error && 'joinotify_not_connected' === $result->get_error_code() && 503 === $result->get_error_data()['status'] );

$result = Transport::template_send_result( details( array( 'error' => 'cURL error 28: timed out', 'retryable' => true ) ) );
check( 'an unreachable API is joinotify_unreachable (503, response_code 0)', $result instanceof WP_Error && 'joinotify_unreachable' === $result->get_error_code() && 0 === $result->get_error_data()['response_code'] );

$result = Transport::template_send_result( 0 );
check( 'a non-array answer is an error, never a success', $result instanceof WP_Error );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
