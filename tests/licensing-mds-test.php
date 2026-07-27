<?php
/**
 * Standalone test harness for the MDS licensing driver.
 *
 * Exercises how Drivers\Mds_Driver maps the API's answers onto the plugin's
 * license fields, and — the part that matters most — how it classifies failure.
 * An answer that cannot be verified must never be read as "the license is bad":
 * anything able to intercept a response could otherwise revoke a customer's
 * license, and the fallback would strand the site on a backend that never
 * really answered.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/licensing-mds-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_VERSION', '2.1.0' );
define( 'JOINOTIFY_BASENAME', 'joinotify/joinotify.php' );

$GLOBALS['wp_version'] = '6.5';

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

function apply_filters( $hook, $value ) { return $value; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function site_url() { return 'https://www.Example.com/loja/'; }

}

// ---------------------------------------------------------------------------
// Collaborator fakes
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static $entries = array();
		public static function register_log( $message, $level = 'INFO' ) { self::$entries[] = array( $level, $message ); }
	}
}

namespace MeuMouse\MDS\SDK\Api {

	class ApiException extends \Exception {
		private $error_code;
		private $is_transport;

		public function __construct( $message, $error_code = 'ERROR', $status = 0, $is_transport = false ) {
			parent::__construct( $message );
			$this->error_code = $error_code;
			$this->is_transport = (bool) $is_transport;
		}

		public function error_code() { return $this->error_code; }
		public function is_transport() { return $this->is_transport; }
	}

	class ApiResponse {
		private $data;
		public function __construct( array $data ) { $this->data = $data; }
		public function data( $key = null, $default = null ) { return null === $key ? $this->data : ( $this->data[ $key ] ?? $default ); }
		public function is_signed() { return true; }
	}

	/** Scripted stand-in for the SDK HTTP client. */
	class Client {
		public $scripted = array();
		public $calls = array();

		public function post( $path, array $body, $require_signature = true ) {
			$this->calls[] = array( 'path' => $path, 'body' => $body, 'signed' => $require_signature );

			$outcome = $this->scripted[ $path ] ?? new ApiResponse( array() );

			if ( $outcome instanceof \Exception ) {
				throw $outcome;
			}

			return $outcome;
		}
	}
}

namespace MeuMouse\MDS\SDK\Config {
	class Product {
		public function __construct( array $args ) {}
		public function public_key() { return 'x'; }
	}
}

namespace MeuMouse\MDS\SDK\Security {
	class SignatureVerifier {
		public function __construct( $public_key ) {}
		public static function is_supported() { return true; }
	}
}

namespace MeuMouse\MDS\SDK\Support {
	class Logger {
		public function __construct( $slug ) {}
	}
}

namespace {

require __DIR__ . '/../admin/src/Licensing/Support/Site.php';
require __DIR__ . '/../admin/src/Licensing/Dto/License_Result.php';
require __DIR__ . '/../admin/src/Licensing/Contracts/Driver.php';
require __DIR__ . '/../admin/src/Licensing/Drivers/Mds_Driver.php';

use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;
use MeuMouse\MDS\SDK\Api\ApiException;
use MeuMouse\MDS\SDK\Api\ApiResponse;
use MeuMouse\MDS\SDK\Api\Client as Fake_Client;

/** Build a driver whose client answers with the given payloads, keyed by path. */
function driver_with( array $scripted, &$client = null ) {
	$client = new Fake_Client();
	$client->scripted = $scripted;

	return new Mds_Driver( $client );
}

/** A full, valid validate payload. */
function valid_payload( array $overrides = array() ) {
	return array_merge( array(
		'valid' => true,
		'license_status' => 'active',
		'activation_status' => 'active',
		'reason' => null,
		'message' => 'License is valid.',
		'expires_at' => '2030-01-01T00:00:00.000Z',
		'support_expires_at' => '2029-01-01T00:00:00.000Z',
		'plan' => 'pro',
		'plan_name' => 'Pro',
		'max_activations' => 3,
		'used_activations' => 1,
		'renew_url' => 'https://meumouse.com/renovar',
	), $overrides );
}

echo "== validate: a valid license ==\n";

$driver = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload() ) ), $client );
$result = $driver->validate('KEY');

check( 'reports valid', $result->is_valid() );
check( 'carries the plan name as the title', 'Pro' === $result->get('license_title') );
check( 'carries the expiry', '2030-01-01T00:00:00.000Z' === $result->get('expire_date') );
check( 'carries the support end', '2029-01-01T00:00:00.000Z' === $result->get('support_end') );
check( 'carries the renewal link', 'https://meumouse.com/renovar' === $result->get('renew_link') );
check( 'carries the seat counts', 3 === $result->get('max_activations') && 1 === $result->get('used_activations') );
check( 'echoes the key', 'KEY' === $result->get('license_key') );
check( 'requires a signature', true === $client->calls[0]['signed'] );
check( 'sends the product slug', 'joinotify' === $client->calls[0]['body']['product_slug'] );
check( 'sends the normalised domain', 'example.com' === $client->calls[0]['body']['domain'] );
check( 'sends the raw site url too', 'https://www.Example.com/loja/' === $client->calls[0]['body']['site_url'] );
check( 'sends the plugin version', '2.1.0' === $client->calls[0]['body']['plugin_version'] );

echo "\n== validate: sentinels the settings screen expects ==\n";

$lifetime = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array( 'expires_at' => null ) ) ) ) )->validate('KEY');
// The stored license object is shared with the legacy path, which spells a
// perpetual license this way.
check( 'a null expiry becomes the never sentinel', 'No expiry' === $lifetime->get('expire_date') );

$no_support = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array( 'support_expires_at' => null ) ) ) ) )->validate('KEY');
check( 'a null support end becomes unlimited', 'Unlimited' === $no_support->get('support_end') );

$slug_only = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array( 'plan_name' => null ) ) ) ) )->validate('KEY');
check( 'falls back to a humanised plan slug', 'Pro' === $slug_only->get('license_title') );

$no_plan = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array( 'plan_name' => null, 'plan' => '' ) ) ) ) )->validate('KEY');
check( 'falls back again when there is no plan', 'Not available' === $no_plan->get('license_title') );

echo "\n== validate: refusals ==\n";

$refused = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array(
	'valid' => false,
	'reason' => 'license_expired',
	'message' => 'License has expired.',
) ) ) ) )->validate('KEY');

check( 'a refusal is a business failure', $refused->is_business_failure() );
check( 'not a transport failure', ! $refused->is_transport_failure() );
check( 'keeps the reason', 'license_expired' === $refused->get('reason') );
check( 'keeps the message', 'License has expired.' === $refused->message() );
// The renewal link is exactly what a lapsed customer needs to see.
check( 'still carries the renewal link', 'https://meumouse.com/renovar' === $refused->get('renew_link') );

$not_activated = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array(
	'valid' => false,
	'reason' => 'domain_not_activated',
) ) ) ) )->validate('KEY');

check( 'an unactivated domain is a business failure', $not_activated->is_business_failure() );
check( 'reason survives', 'domain_not_activated' === $not_activated->get('reason') );

echo "\n== failure classification ==\n";

$transport = driver_with( array(
	'/v2/updates/validate' => new ApiException( 'Connection timed out', 'TRANSPORT_ERROR', 0, true ),
) )->validate('KEY');

check( 'a network error is a transport failure', $transport->is_transport_failure() );

$unsigned = driver_with( array(
	'/v2/updates/validate' => new ApiException( 'Response signature missing or invalid.', 'INVALID_SIGNATURE', 200 ),
) )->validate('KEY');

// The SDK raises this as a non-transport error, but an answer we cannot verify
// is not an answer: reading it as a refusal would let anyone able to intercept
// the response revoke a license.
check( 'an unsigned response is a transport failure', $unsigned->is_transport_failure() );
check( 'never valid', ! $unsigned->is_valid() );

$malformed = driver_with( array(
	'/v2/updates/validate' => new ApiException( 'Malformed API response.', 'MALFORMED_RESPONSE', 200 ),
) )->validate('KEY');

check( 'a malformed response is a transport failure', $malformed->is_transport_failure() );

$rejected = driver_with( array(
	'/v2/updates/validate' => new ApiException( 'License is banned', 'FORBIDDEN', 403 ),
) )->validate('KEY');

check( 'a server refusal is a business failure', $rejected->is_business_failure() );

$exploded = driver_with( array(
	'/v2/updates/validate' => new \RuntimeException('something unexpected'),
) )->validate('KEY');

check( 'an unexpected error is a transport failure', $exploded->is_transport_failure() );

echo "\n== activate ==\n";

$client = null;
$activated = driver_with( array(
	'/v2/licenses/activate' => new ApiResponse( array( 'id' => 'act-1' ) ),
	'/v2/updates/validate' => new ApiResponse( valid_payload() ),
), $client )->activate('KEY');

check( 'reports valid', $activated->is_valid() );
check( 'activates first', '/v2/licenses/activate' === $client->calls[0]['path'] );
check( 'then reads the license', '/v2/updates/validate' === $client->calls[1]['path'] );
check( 'activation requires a signature', true === $client->calls[0]['signed'] );

$client = null;
$limit_reached = driver_with( array(
	'/v2/licenses/activate' => new ApiException( 'Activation limit reached (3).', 'UNPROCESSABLE', 422 ),
), $client )->activate('KEY');

check( 'a refused activation is a business failure', $limit_reached->is_business_failure() );
check( 'and does not go on to validate', 1 === count( $client->calls ) );
check( 'surfaces the reason', 'Activation limit reached (3).' === $limit_reached->message() );

echo "\n== deactivate ==\n";

$client = null;
$deactivated = driver_with( array( '/v2/licenses/deactivate' => new ApiResponse( array() ) ), $client )->deactivate('KEY');

check( 'reports success', $deactivated->succeeded() );
check( 'sends the normalised domain', 'example.com' === $client->calls[0]['body']['domain'] );
// Releasing a seat is not a claim about validity, so an unsigned answer is fine.
check( 'does not require a signature', false === $client->calls[0]['signed'] );

$deactivate_offline = driver_with( array(
	'/v2/licenses/deactivate' => new ApiException( 'down', 'TRANSPORT_ERROR', 0, true ),
) )->deactivate('KEY');

check( 'an unreachable server is a transport failure', $deactivate_offline->is_transport_failure() );

echo "\n== expires_at ==\n";

$expires = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload() ) ) )->expires_at('KEY');
check( 'returns the expiry timestamp', strtotime('2030-01-01T00:00:00.000Z') === $expires );

$lifetime = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array( 'expires_at' => null ) ) ) ) )->expires_at('KEY');
check( 'a lifetime license has no timestamp', null === $lifetime );

$offline = driver_with( array(
	'/v2/updates/validate' => new ApiException( 'down', 'TRANSPORT_ERROR', 0, true ),
) )->expires_at('KEY');
// Answering with a date here would let an outage look like an expiry.
check( 'an unreachable server has no timestamp', null === $offline );

$expired = driver_with( array( '/v2/updates/validate' => new ApiResponse( valid_payload( array(
	'valid' => false,
	'reason' => 'license_expired',
	'expires_at' => '2020-01-01T00:00:00.000Z',
) ) ) ) )->expires_at('KEY');

check( 'a refused license still reports its date', strtotime('2020-01-01T00:00:00.000Z') === $expired );

echo "\n== configuration guard ==\n";

// Built without an injected client and without the constants configured.
$unconfigured = new Mds_Driver();
$result = $unconfigured->validate('KEY');

check( 'an unconfigured driver reports unreachable', $result->is_transport_failure() );
check( 'and never claims validity', ! $result->is_valid() );
check( 'and has no expiry', null === $unconfigured->expires_at('KEY') );
// Reporting "unreachable" rather than "refused" is what keeps an unconfigured
// site on the legacy backend instead of losing its license.
check( 'is not configured', false === Mds_Driver::is_configured() );

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions\n"
	: "OK: {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
