<?php
/**
 * Standalone test harness for the licensing driver layer.
 *
 * Exercises the legacy driver against a fake transport, plus the crypto and site
 * helpers it depends on. No WordPress bootstrap is required: the handful of WP
 * functions these classes touch are stubbed below, and the Logger is replaced by
 * a no-op so the assertions stay on the protocol handling itself.
 *
 * The failure classification is the point of most of these cases. A driver that
 * reports "server refused" when it actually never reached the server would let
 * the orchestrator strand a paying customer; one that reports the opposite would
 * let a revoked key survive by falling through to another server.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/licensing-driver-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_VERSION', '2.1.0' );
define( 'JOINOTIFY_ADMIN_EMAIL', 'admin@example.com' );

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

function apply_filters( $hook, $value ) { return $value; }
function wp_rand( $min, $max ) { return random_int( $min, $max ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_option( $name, $default = false ) { return $default; }
function site_url() { return 'https://example.com'; }
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }
function maybe_unserialize( $value ) {
	if ( ! is_string( $value ) ) {
		return $value;
	}

	$data = @unserialize( $value );

	return ( false === $data && 'b:0;' !== $value ) ? $value : $data;
}
function maybe_serialize( $value ) {
	return ( is_array( $value ) || is_object( $value ) ) ? serialize( $value ) : $value;
}

class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

// Filled in per test; the driver's transport is injected, so these only matter
// for expires_at(), which calls wp_remote_post directly.
$GLOBALS['remote_post_response'] = null;

function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['last_remote_post'] = array( 'url' => $url, 'args' => $args );

	return $GLOBALS['remote_post_response'];
}
function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }
function wp_remote_retrieve_response_code( $response ) { return isset( $response['response']['code'] ) ? $response['response']['code'] : 0; }

}

// ---------------------------------------------------------------------------
// Collaborator fakes
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static function register_log( $message, $level = 'INFO' ) {}
	}
}

namespace {

require __DIR__ . '/../admin/src/Licensing/Support/Crypto.php';
require __DIR__ . '/../admin/src/Licensing/Support/Site.php';
require __DIR__ . '/../admin/src/Licensing/Dto/License_Result.php';
require __DIR__ . '/../admin/src/Licensing/Contracts/Driver.php';
require __DIR__ . '/../admin/src/Licensing/Http/Transport.php';
require __DIR__ . '/../admin/src/Licensing/Drivers/Legacy_Driver.php';

use MeuMouse\Joinotify\Licensing\Drivers\Legacy_Driver;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;
use MeuMouse\Joinotify\Licensing\Http\Transport;
use MeuMouse\Joinotify\Licensing\Support\Crypto;
use MeuMouse\Joinotify\Licensing\Support\Site;

/**
 * Transport stand-in that returns a scripted outcome and records the request.
 */
class Fake_Transport extends Transport {

	public $outcome;
	public $last_url = '';
	public $last_body = '';
	public $calls = 0;

	public function __construct( $outcome ) {
		$this->outcome = $outcome;
	}

	public function post( $url, $body, $headers = array() ) {
		$this->calls++;
		$this->last_url = $url;
		$this->last_body = $body;

		return $this->outcome;
	}
}

/** Build the transport outcome for a successful HTTP exchange. */
function ok_body( $body ) {
	return array( 'body' => $body, 'code' => 200, 'error' => '', 'transport_error' => false );
}

/** Build the transport outcome for a failed HTTP exchange. */
function failed( $error, $code = 0 ) {
	return array( 'body' => '', 'code' => $code, 'error' => $error, 'transport_error' => true );
}

const PRODUCT_KEY = 'E63390D3F50B70F0';

/**
 * Encode a server envelope the way the legacy API does: the license object is
 * encrypted under the site URL, and the whole envelope under the product key.
 */
function server_envelope( array $envelope, $license = null ) {
	if ( null !== $license ) {
		$envelope['data'] = Crypto::encrypt( maybe_serialize( (object) $license ), site_url() );
	}

	return Crypto::encrypt( json_encode( $envelope ), PRODUCT_KEY );
}

function valid_license( array $overrides = array() ) {
	return array_merge( array(
		'is_valid' => true,
		'license_title' => 'Joinotify Pro',
		'expire_date' => '2030-01-01',
		'support_end' => '2029-01-01',
		'renew_link' => 'https://meumouse.com/renew',
		'request_duration' => 12,
	), $overrides );
}

echo "== Crypto ==\n";

$round_trip = Crypto::decrypt( Crypto::encrypt( 'hello world', 'secret' ), 'secret' );
check( 'encrypt/decrypt round trip', 'hello world' === $round_trip );
check( 'wrong password yields empty string', '' === Crypto::decrypt( Crypto::encrypt( 'x', 'a' ), 'b' ) );
check( 'non-string input yields empty string', '' === Crypto::decrypt( array( 'nope' ), 'secret' ) );
check( 'garbage input yields empty string', '' === Crypto::decrypt( 'not-encrypted-at-all', 'secret' ) );

$first = Crypto::encrypt( 'same', 'secret' );
$second = Crypto::encrypt( 'same', 'secret' );
check( 'identical payloads encrypt differently', $first !== $second );

echo "\n== Site ==\n";

check( 'url returns the site url', 'https://example.com' === Site::url() );
check( 'domain strips scheme', 'example.com' === Site::domain('https://example.com') );
check( 'domain strips www', 'example.com' === Site::domain('https://www.example.com') );
check( 'domain strips port and path', 'example.com' === Site::domain('http://example.com:8080/loja/?x=1') );
check( 'domain lowercases', 'example.com' === Site::domain('HTTPS://Example.COM') );
check( 'domain accepts a bare host', 'example.com' === Site::domain('example.com') );

echo "\n== Product resolution ==\n";

$joinotify = Legacy_Driver::resolve_product('ABCD-1234');
check( 'plain key resolves to joinotify', '8' === $joinotify['id'] && 'joinotify' === $joinotify['base'] );

$clube = Legacy_Driver::resolve_product('CM-ABCD-1234');
check( 'CM- prefix resolves to clube-m', '7' === $clube['id'] && 'clube-m' === $clube['base'] );
check( 'clube-m uses its own key', $clube['key'] !== $joinotify['key'] );
check( 'non-string key resolves to joinotify', '8' === Legacy_Driver::resolve_product( null )['id'] );
check( 'CM in the middle does not match', '8' === Legacy_Driver::resolve_product('X-CM-1')['id'] );

echo "\n== activate: valid license ==\n";

$transport = new Fake_Transport( ok_body( server_envelope(
	array( 'status' => true, 'msg' => 'License activated.' ),
	valid_license()
) ) );

$driver = new Legacy_Driver( 'ABCD-1234', $transport );
$result = $driver->activate('ABCD-1234');

check( 'reports valid', $result->is_valid() );
check( 'reports success', $result->succeeded() );
check( 'carries the title', 'Joinotify Pro' === $result->get('license_title') );
check( 'carries the expiry', '2030-01-01' === $result->get('expire_date') );
check( 'carries the support end', '2029-01-01' === $result->get('support_end') );
check( 'carries the renew link', 'https://meumouse.com/renew' === $result->get('renew_link') );
check( 'carries the request duration as int', 12 === $result->get('request_duration') );
check( 'echoes the license key back', 'ABCD-1234' === $result->get('license_key') );
check( 'carries the server message', 'License activated.' === $result->message() );
check( 'posts to the activation endpoint', false !== strpos( $transport->last_url, '/product/active/8' ) );

$sent = json_decode( Crypto::decrypt( $transport->last_body, PRODUCT_KEY ), true );
check( 'sends the license key', 'ABCD-1234' === $sent['license_key'] );
check( 'sends the site url as domain', 'https://example.com' === $sent['domain'] );
check( 'sends the product id', '8' === $sent['product_id'] );
check( 'sends the app version', '2.1.0' === $sent['app_version'] );

echo "\n== activate: clube-m routing ==\n";

$cm_transport = new Fake_Transport( ok_body(
	Crypto::encrypt( json_encode( array( 'status' => false, 'msg' => 'nope' ) ), 'B729F2659393EE27' )
) );
$cm_result = ( new Legacy_Driver( 'CM-1', $cm_transport ) )->activate('CM-1');

check( 'clube-m posts to its own product id', false !== strpos( $cm_transport->last_url, '/product/active/7' ) );
check( 'clube-m envelope decrypts with its own key', $cm_result->is_business_failure() );

echo "\n== activate: business failures ==\n";

$refused = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body( server_envelope(
	array( 'status' => false, 'msg' => 'License key not found.' )
) ) ) ) )->activate('K');

check( 'status false is a business failure', $refused->is_business_failure() );
check( 'not a transport failure', ! $refused->is_transport_failure() );
check( 'surfaces the server message', 'License key not found.' === $refused->message() );
check( 'is not valid', ! $refused->is_valid() );

$coded = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body( server_envelope(
	array( 'code' => 'rest_forbidden', 'message' => 'Forbidden.' )
) ) ) ) )->activate('K');

check( 'error code is a business failure', $coded->is_business_failure() );
check( 'surfaces the coded message', 'Forbidden.' === $coded->message() );

$no_data = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body( server_envelope(
	array( 'status' => true, 'msg' => 'ok' )
) ) ) ) )->activate('K');

check( 'missing data payload is a business failure', $no_data->is_business_failure() );

$invalid = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body( server_envelope(
	array( 'status' => true, 'msg' => 'Expired.' ),
	valid_license( array( 'is_valid' => false ) )
) ) ) ) )->activate('K');

check( 'is_valid false is a business failure', $invalid->is_business_failure() );
check( 'invalid license still carries its fields', '2030-01-01' === $invalid->get('expire_date') );

echo "\n== activate: transport failures ==\n";

$unreachable = ( new Legacy_Driver( 'K', new Fake_Transport( failed('cURL error 28') ) ) )->activate('K');

check( 'network error is a transport failure', $unreachable->is_transport_failure() );
check( 'not a business failure', ! $unreachable->is_business_failure() );
check( 'surfaces the transport error', 'cURL error 28' === $unreachable->message() );

$undecryptable = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body('<html>parked domain</html>') ) ) )->activate('K');

// The decisive case for the migration: the old host answers with something that
// is not the licensing API any more. Treating that as "license refused" would
// wrongly deactivate every site the day the endpoint is retired.
check( 'undecryptable body is a transport failure', $undecryptable->is_transport_failure() );

$not_json = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body(
	Crypto::encrypt( 'this is not json', PRODUCT_KEY )
) ) ) )->activate('K');

check( 'non-JSON payload is a transport failure', $not_json->is_transport_failure() );

$scalar_json = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body(
	Crypto::encrypt( '"just a string"', PRODUCT_KEY )
) ) ) )->activate('K');

check( 'non-object JSON is a transport failure', $scalar_json->is_transport_failure() );

$bad_license = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body(
	Crypto::encrypt( json_encode( array( 'status' => true, 'msg' => 'ok', 'data' => 'garbage' ) ), PRODUCT_KEY )
) ) ) )->activate('K');

check( 'undecryptable license object is a transport failure', $bad_license->is_transport_failure() );

echo "\n== validate mirrors activate ==\n";

$validate_transport = new Fake_Transport( ok_body( server_envelope(
	array( 'status' => true, 'msg' => 'ok' ),
	valid_license()
) ) );
$validated = ( new Legacy_Driver( 'K', $validate_transport ) )->validate('K');

check( 'validate reports valid', $validated->is_valid() );
check( 'validate uses the activation endpoint', false !== strpos( $validate_transport->last_url, '/product/active/' ) );

echo "\n== deactivate ==\n";

$deactivate_transport = new Fake_Transport( ok_body( server_envelope(
	array( 'status' => true, 'msg' => 'License released.' )
) ) );
$deactivated = ( new Legacy_Driver( 'K', $deactivate_transport ) )->deactivate('K');

check( 'reports success', $deactivated->succeeded() );
check( 'does not claim validity', ! $deactivated->is_valid() );
check( 'surfaces the message', 'License released.' === $deactivated->message() );
check( 'posts to the deactivation endpoint', false !== strpos( $deactivate_transport->last_url, '/product/deactive/8' ) );

$deactivate_refused = ( new Legacy_Driver( 'K', new Fake_Transport( ok_body( server_envelope(
	array( 'code' => 'error', 'message' => 'Unknown activation.' )
) ) ) ) )->deactivate('K');

check( 'coded response fails', ! $deactivate_refused->succeeded() );
check( 'surfaces the refusal', 'Unknown activation.' === $deactivate_refused->message() );

$deactivate_offline = ( new Legacy_Driver( 'K', new Fake_Transport( failed('timeout') ) ) )->deactivate('K');
check( 'offline deactivation is a transport failure', $deactivate_offline->is_transport_failure() );

echo "\n== expires_at ==\n";

$GLOBALS['remote_post_response'] = array(
	'body' => json_encode( array( 'data' => array( 'expiry_time' => '2030-06-01 12:00:00' ) ) ),
	'response' => array( 'code' => 200 ),
);

$expires = ( new Legacy_Driver( 'K' ) )->expires_at('K');
check( 'returns a timestamp', strtotime('2030-06-01 12:00:00') === $expires );
check( 'calls the license viewer', false !== strpos( $GLOBALS['last_remote_post']['url'], 'license/view' ) );
check( 'sends the license code', 'K' === $GLOBALS['last_remote_post']['args']['body']['license_code'] );

$GLOBALS['remote_post_response'] = array( 'body' => json_encode( array( 'data' => array() ) ), 'response' => array( 'code' => 200 ) );
check( 'missing expiry returns null', null === ( new Legacy_Driver( 'K' ) )->expires_at('K') );

$GLOBALS['remote_post_response'] = array( 'body' => 'not json', 'response' => array( 'code' => 200 ) );
check( 'unparseable body returns null', null === ( new Legacy_Driver( 'K' ) )->expires_at('K') );

$GLOBALS['remote_post_response'] = new WP_Error( 'http_request_failed', 'down' );
check( 'network error returns null', null === ( new Legacy_Driver( 'K' ) )->expires_at('K') );

echo "\n== License_Result ==\n";

$valid_result = License_Result::valid( array( 'plan' => 'pro' ), 'ok' );
check( 'valid is valid', $valid_result->is_valid() );
check( 'valid succeeded', $valid_result->succeeded() );
check( 'valid exposes data', 'pro' === $valid_result->get('plan') );
check( 'absent field returns the default', 'x' === $valid_result->get( 'nope', 'x' ) );

$success = License_Result::success('done');
check( 'success succeeded without validity', $success->succeeded() && ! $success->is_valid() );

$business = License_Result::business_failure('no');
check( 'business failure is classified', License_Result::FAILURE_BUSINESS === $business->failure_kind() );
check( 'business failure did not succeed', ! $business->succeeded() );

$transport_failure = License_Result::transport_failure('down');
check( 'transport failure is classified', License_Result::FAILURE_TRANSPORT === $transport_failure->failure_kind() );
check( 'transport and business are exclusive', ! $transport_failure->is_business_failure() );

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions\n"
	: "OK: {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
