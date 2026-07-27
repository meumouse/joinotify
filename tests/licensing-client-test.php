<?php
/**
 * Standalone test harness for the licensing driver orchestration.
 *
 * Exercises Licensing\Client and Licensing\Driver_State: which backend a call
 * goes to, when a second one may be tried, and when that choice is written down
 * for good.
 *
 * The rule under test is the one the whole migration rests on. A backend that
 * answers "no" has answered, and the call ends there — trying somewhere else
 * would let a key revoked on one server be honoured by another. A backend that
 * never answered has not, and only then may the next one be asked.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/licensing-client-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );

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
$GLOBALS['filters'] = array();
$GLOBALS['actions'] = array();

function reset_state() {
	$GLOBALS['options'] = array();
	$GLOBALS['filters'] = array();
	$GLOBALS['actions'] = array();
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }
function __( $text, $domain = '' ) { return $text; }

function apply_filters( $hook, $value ) {
	if ( isset( $GLOBALS['filters'][ $hook ] ) ) {
		return call_user_func( $GLOBALS['filters'][ $hook ], $value );
	}

	return $value;
}
function add_filter_stub( $hook, $callback ) { $GLOBALS['filters'][ $hook ] = $callback; }
function do_action( $hook, $a = null, $b = null ) { $GLOBALS['actions'][] = array( $hook, $a, $b ); }

}

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static function register_log( $message, $level = 'INFO' ) {}
	}
}

namespace {

require __DIR__ . '/../admin/src/Licensing/Dto/License_Result.php';
require __DIR__ . '/../admin/src/Licensing/Contracts/Driver.php';
require __DIR__ . '/../admin/src/Licensing/Drivers/Mds_Driver.php';

use MeuMouse\Joinotify\Licensing\Client;
use MeuMouse\Joinotify\Licensing\Contracts\Driver;
use MeuMouse\Joinotify\Licensing\Driver_State;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;

/**
 * Driver stand-in returning a scripted result and counting its calls.
 */
class Scripted_Driver implements Driver {

	private $id;
	public $result;
	public $calls = array();
	public $expires = null;

	public function __construct( $id, $result = null ) {
		$this->id = $id;
		$this->result = $result;
	}

	public function id() { return $this->id; }

	public function activate( $license_key ) { return $this->record( 'activate', $license_key ); }
	public function validate( $license_key ) { return $this->record( 'validate', $license_key ); }
	public function deactivate( $license_key ) { return $this->record( 'deactivate', $license_key ); }

	public function expires_at( $license_key ) {
		$this->calls[] = array( 'expires_at', $license_key );

		return $this->expires;
	}

	private function record( $operation, $license_key ) {
		$this->calls[] = array( $operation, $license_key );

		return $this->result;
	}
}

}

// The Legacy_Driver class is only referenced for its ID constant, so a stub
// avoids dragging the whole legacy protocol into these tests.
namespace MeuMouse\Joinotify\Licensing\Drivers {
	class Legacy_Driver implements \MeuMouse\Joinotify\Licensing\Contracts\Driver {
		const ID = 'legacy';
		public function __construct( $license_key = '', $transport = null ) {}
		public function id() { return self::ID; }
		public function activate( $k ) { return \MeuMouse\Joinotify\Licensing\Dto\License_Result::transport_failure('stub'); }
		public function validate( $k ) { return \MeuMouse\Joinotify\Licensing\Dto\License_Result::transport_failure('stub'); }
		public function deactivate( $k ) { return \MeuMouse\Joinotify\Licensing\Dto\License_Result::transport_failure('stub'); }
		public function expires_at( $k ) { return null; }
	}
}

namespace {

require __DIR__ . '/../admin/src/Licensing/Driver_State.php';
require __DIR__ . '/../admin/src/Licensing/Client.php';

use MeuMouse\Joinotify\Licensing\Client;
use MeuMouse\Joinotify\Licensing\Driver_State;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;
use MeuMouse\Joinotify\Licensing\Drivers\Legacy_Driver;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;

/** Build a client over two scripted drivers. */
function client_with( $legacy_result, $mds_result, &$legacy = null, &$mds = null ) {
	$legacy = new Scripted_Driver( Legacy_Driver::ID, $legacy_result );
	$mds = new Scripted_Driver( Mds_Driver::ID, $mds_result );

	return new Client( 'KEY', array( $legacy, $mds ) );
}

echo "== Driver_State ==\n";

reset_state();
check( 'defaults to legacy', Legacy_Driver::ID === Driver_State::current() );

reset_state();
Driver_State::elect( Mds_Driver::ID, 'legacy gone' );
check( 'electing mds sticks', Mds_Driver::ID === Driver_State::current() );
check( 'records the reason', 'legacy gone' === Driver_State::details()['reason'] );
check( 'records when', Driver_State::details()['decided_at'] > 0 );
check( 'announces the move', 'Joinotify/Licensing/Driver_Elected' === $GLOBALS['actions'][0][0] );

// Once a site is on MDS the legacy endpoint is being retired; going back would
// only reintroduce its timeout on every call.
check( 'electing legacy afterwards is refused', false === Driver_State::elect( Legacy_Driver::ID, 'x' ) );
check( 'still on mds', Mds_Driver::ID === Driver_State::current() );
check( 're-electing mds is a no-op', false === Driver_State::elect( Mds_Driver::ID, 'again' ) );

reset_state();
check( 'unknown driver is refused', false === Driver_State::elect( 'something-else', 'x' ) );
check( 'still legacy', Legacy_Driver::ID === Driver_State::current() );

reset_state();
$GLOBALS['options']['joinotify_license_driver'] = array( 'driver' => 'bogus' );
check( 'a corrupt election falls back to legacy', Legacy_Driver::ID === Driver_State::current() );

reset_state();
Driver_State::elect( Mds_Driver::ID, 'x' );
Driver_State::reset();
check( 'reset clears the election', Legacy_Driver::ID === Driver_State::current() );

reset_state();
add_filter_stub( 'Joinotify/Licensing/Force_Driver', function () { return Mds_Driver::ID; } );
check( 'a forced driver wins', Mds_Driver::ID === Driver_State::current() );
check( 'and is reported as forced', true === Driver_State::details()['forced'] );

reset_state();
Driver_State::elect( Mds_Driver::ID, 'x' );
add_filter_stub( 'Joinotify/Licensing/Force_Driver', function () { return Legacy_Driver::ID; } );
// Pinning back to legacy is how a migration gets paused without losing the
// recorded election underneath.
check( 'forcing legacy overrides a recorded election', Legacy_Driver::ID === Driver_State::current() );

reset_state();
add_filter_stub( 'Joinotify/Licensing/Force_Driver', function () { return 'nonsense'; } );
check( 'an unknown forced driver is ignored', Legacy_Driver::ID === Driver_State::current() );

echo "\n== Client: legacy answering ==\n";

reset_state();
$client = client_with( License_Result::valid( array( 'license_title' => 'Pro' ) ), null, $legacy, $mds );
$result = $client->validate();

check( 'returns the legacy answer', $result->is_valid() );
check( 'legacy was called', 1 === count( $legacy->calls ) );
check( 'mds was not called', 0 === count( $mds->calls ) );
check( 'stays on legacy', Legacy_Driver::ID === Driver_State::current() );
check( 'passes the license key through', 'KEY' === $legacy->calls[0][1] );

echo "\n== Client: legacy refusing ==\n";

reset_state();
$client = client_with( License_Result::business_failure('Key revoked.'), License_Result::valid(), $legacy, $mds );
$result = $client->validate();

// The case that must never fall through: a revoked key would otherwise be
// honoured by whichever backend is asked next.
check( 'a refusal is returned as-is', $result->is_business_failure() );
check( 'mds is never consulted', 0 === count( $mds->calls ) );
check( 'stays on legacy', Legacy_Driver::ID === Driver_State::current() );
check( 'keeps the refusal message', 'Key revoked.' === $result->message() );

echo "\n== Client: legacy unreachable ==\n";

reset_state();
$client = client_with( License_Result::transport_failure('cURL error 28'), License_Result::valid( array( 'plan' => 'pro' ) ), $legacy, $mds );
$result = $client->validate();

check( 'falls through to mds', $result->is_valid() );
check( 'legacy was tried first', 1 === count( $legacy->calls ) );
check( 'mds was tried second', 1 === count( $mds->calls ) );
check( 'carries the mds payload', 'pro' === $result->get('plan') );
check( 'elects mds', Mds_Driver::ID === Driver_State::current() );

// The election is what stops every later call paying the dead server's timeout.
$second = new Scripted_Driver( Legacy_Driver::ID, License_Result::valid() );
$third = new Scripted_Driver( Mds_Driver::ID, License_Result::valid() );
( new Client( 'KEY', array( $second, $third ) ) )->validate();
check( 'later calls skip legacy entirely', 0 === count( $second->calls ) );
check( 'and go straight to mds', 1 === count( $third->calls ) );

echo "\n== Client: mds refusing after legacy is gone ==\n";

reset_state();
$client = client_with( License_Result::transport_failure('gone'), License_Result::business_failure('Not found.'), $legacy, $mds );
$result = $client->validate();

check( 'returns the mds refusal', $result->is_business_failure() );
// A refusal proves the backend is alive, which is what the election records.
check( 'still elects mds', Mds_Driver::ID === Driver_State::current() );

echo "\n== Client: nothing answering ==\n";

reset_state();
$client = client_with( License_Result::transport_failure('legacy down'), License_Result::transport_failure('mds down'), $legacy, $mds );
$result = $client->validate();

check( 'reports a transport failure', $result->is_transport_failure() );
check( 'both were tried', 1 === count( $legacy->calls ) && 1 === count( $mds->calls ) );
// Electing here would strand the site on a backend that never answered.
check( 'elects nothing', Legacy_Driver::ID === Driver_State::current() );

echo "\n== Client: every operation ==\n";

foreach ( array( 'activate', 'validate', 'deactivate' ) as $operation ) {
	reset_state();
	$client = client_with( License_Result::transport_failure('down'), License_Result::success('ok'), $legacy, $mds );
	$result = $client->$operation();

	check( "{$operation} falls through", $result->succeeded() );
	check( "{$operation} reaches mds", $operation === $mds->calls[0][0] );
}

echo "\n== Client: expires_at ==\n";

reset_state();
$legacy = new Scripted_Driver( Legacy_Driver::ID );
$legacy->expires = 1893456000;
$mds = new Scripted_Driver( Mds_Driver::ID );
$client = new Client( 'KEY', array( $legacy, $mds ) );

check( 'returns the legacy timestamp', 1893456000 === $client->expires_at() );
check( 'does not consult mds', 0 === count( $mds->calls ) );

reset_state();
$legacy = new Scripted_Driver( Legacy_Driver::ID );
$mds = new Scripted_Driver( Mds_Driver::ID );
$mds->expires = 1893456000;
$client = new Client( 'KEY', array( $legacy, $mds ) );

check( 'falls through when legacy has no answer', 1893456000 === $client->expires_at() );
check( 'elects mds', Mds_Driver::ID === Driver_State::current() );

reset_state();
$client = new Client( 'KEY', array( new Scripted_Driver( Legacy_Driver::ID ), new Scripted_Driver( Mds_Driver::ID ) ) );
check( 'returns null when nobody knows', null === $client->expires_at() );

echo "\n== Mds_Driver placeholder ==\n";

reset_state();
$stub = new Mds_Driver();
// Until the SDK is bundled the honest answer is "unreachable", which also keeps
// the fallback inert because only a driver that answered gets elected.
check( 'reports unreachable', $stub->validate('KEY')->is_transport_failure() );
check( 'never claims validity', ! $stub->activate('KEY')->is_valid() );
check( 'has no expiry', null === $stub->expires_at('KEY') );
check( 'identifies itself', 'mds' === $stub->id() );

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions\n"
	: "OK: {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
