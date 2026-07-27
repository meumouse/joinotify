<?php
/**
 * Standalone test harness for the local license state rules.
 *
 * Exercises the read side of Api\License: whether a stored license counts as
 * valid, how long that answer may be cached, and what the settings screen is
 * told. Options and transients are held in memory by the stubs below, so the
 * assertions are about the rules rather than about WordPress.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/licensing-state-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'JOINOTIFY_VERSION', '2.1.0' );
define( 'JOINOTIFY_FILE', __DIR__ . '/joinotify.php' );

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
// WordPress stubs: in-memory options and transients
// ---------------------------------------------------------------------------

$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['scheduled'] = array();

function reset_state() {
	$GLOBALS['options'] = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['scheduled'] = array();
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; return true; }
function add_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }

function get_transient( $name ) {
	if ( ! array_key_exists( $name, $GLOBALS['transients'] ) ) {
		return false;
	}

	list( $value, $expires ) = $GLOBALS['transients'][ $name ];

	if ( $expires > 0 && $expires < time() ) {
		unset( $GLOBALS['transients'][ $name ] );

		return false;
	}

	return $value;
}
function set_transient( $name, $value, $ttl = 0 ) {
	$GLOBALS['transients'][ $name ] = array( $value, $ttl > 0 ? time() + $ttl : 0 );
	$GLOBALS['transient_ttls'][ $name ] = $ttl;

	return true;
}
function delete_transient( $name ) { unset( $GLOBALS['transients'][ $name ] ); return true; }

function apply_filters( $hook, $value ) { return $value; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function do_action( $hook ) {}
function esc_html__( $text, $domain = '' ) { return $text; }
function __( $text, $domain = '' ) { return $text; }
function site_url() { return 'https://example.com'; }
function wp_rand( $min, $max ) { return random_int( $min, $max ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function date_i18n( $format, $timestamp ) { return date( $format, $timestamp ); }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['scheduled'][ $hook ] ); }
function wp_schedule_single_event( $timestamp, $hook ) { $GLOBALS['scheduled'][ $hook ] = $timestamp; }
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
require __DIR__ . '/../admin/src/Api/License.php';

use MeuMouse\Joinotify\Api\License;

/** Store a license object the way a successful validation would. */
function store_license( array $fields = array() ) {
	$GLOBALS['options']['joinotify_license_response_object'] = (object) array_merge( array(
		'is_valid' => true,
		'expire_date' => 'No expiry',
		'license_title' => 'Joinotify Pro',
		'license_key' => 'ABCD-1234',
	), $fields );
}

echo "== is_valid: honours the stored verdict ==\n";

reset_state();
store_license();
check( 'a valid license is valid', License::is_valid() === true );
check( 'writes the status option', 'valid' === get_option('joinotify_license_status') );

reset_state();
store_license( array( 'is_valid' => false ) );
// The old implementation only checked that the field existed, so a license the
// server had explicitly refused still unlocked the plugin.
check( 'an explicitly invalid license is not valid', License::is_valid() === false );
check( 'writes the status option as invalid', 'invalid' === get_option('joinotify_license_status') );

reset_state();
check( 'no stored license is not valid', License::is_valid() === false );

reset_state();
$GLOBALS['options']['joinotify_license_response_object'] = 'not-an-object';
check( 'a corrupt stored license is not valid', License::is_valid() === false );

echo "\n== is_valid: expiry ==\n";

reset_state();
store_license( array( 'expire_date' => date( 'Y-m-d', time() - 86400 * 2 ) ) );
check( 'a lapsed license is not valid', License::is_valid() === false );

reset_state();
store_license( array( 'expire_date' => date( 'Y-m-d', time() + 86400 * 30 ) ) );
check( 'a future expiry is valid', License::is_valid() === true );

foreach ( array( 'No expiry', 'no expiry', 'Unlimited', 'lifetime' ) as $sentinel ) {
	reset_state();
	store_license( array( 'expire_date' => $sentinel ) );
	check( "'{$sentinel}' means never expires", License::is_valid() === true );
}

echo "\n== is_valid: caching ==\n";

reset_state();
store_license( array( 'is_valid' => false ) );
License::is_valid();
// A cached negative used to be indistinguishable from a cache miss, so every
// call re-ran the whole evaluation.
check( 'a negative answer is cached', 'invalid' === get_transient('joinotify_license_status_cached') );

reset_state();
store_license();
License::is_valid();
check( 'a positive answer is cached', 'valid' === get_transient('joinotify_license_status_cached') );

reset_state();
store_license();
License::is_valid();
unset( $GLOBALS['options']['joinotify_license_response_object'] );
check( 'the cached answer is reused', License::is_valid() === true );

reset_state();
store_license( array( 'expire_date' => date( 'Y-m-d H:i:s', time() + 2 * HOUR_IN_SECONDS ) ) );
License::is_valid();
check( 'cache never outlives the expiry', $GLOBALS['transient_ttls']['joinotify_license_status_cached'] <= 2 * HOUR_IN_SECONDS );

reset_state();
store_license( array( 'expire_date' => date( 'Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS ) ) );
License::is_valid();
check( 'cache is capped at a day', DAY_IN_SECONDS === $GLOBALS['transient_ttls']['joinotify_license_status_cached'] );

reset_state();
store_license();
$GLOBALS['transients']['joinotify_license_status_cached'] = array( true, 0 );
check( 'a legacy boolean cache is recomputed', License::is_valid() === true );
check( 'and rewritten in the new format', 'valid' === get_transient('joinotify_license_status_cached') );

echo "\n== expired_license ==\n";

reset_state();
store_license( array( 'expire_date' => date( 'Y-m-d', time() - 86400 ) ) );
// The old implementation returned false on every path, so nothing could ever
// detect an expired license through it.
check( 'reports a lapsed license as expired', License::expired_license() === true );

reset_state();
store_license( array( 'expire_date' => date( 'Y-m-d', time() + 86400 ) ) );
check( 'a future expiry is not expired', License::expired_license() === false );

reset_state();
store_license();
check( 'a lifetime license is not expired', License::expired_license() === false );

reset_state();
check( 'no license is not expired', License::expired_license() === false );

echo "\n== license_expire ==\n";

reset_state();
store_license();
check( 'lifetime reads as never expires', 'Never expires' === License::license_expire() );

reset_state();
store_license( array( 'expire_date' => date( 'Y-m-d', time() - 86400 ) ) );
check( 'a lapsed license says so', 'Expired license' === License::license_expire() );

// Rendering the settings screen must not revoke anything.
check( 'reading does not drop the stored license', isset( $GLOBALS['options']['joinotify_license_response_object'] ) );
check( 'reading does not flip the status option', ! isset( $GLOBALS['options']['joinotify_license_status'] ) );

reset_state();
$GLOBALS['options']['date_format'] = 'Y-m-d';
store_license( array( 'expire_date' => '2030-06-01' ) );
check( 'a future date is formatted', '2030-06-01' === License::license_expire() );

reset_state();
check( 'no license yields an empty string', '' === License::license_expire() );

echo "\n== license_title ==\n";

reset_state();
store_license();
check( 'returns the stored title', 'Joinotify Pro' === License::license_title() );

reset_state();
check( 'falls back when absent', 'Not available' === License::license_title() );

echo "\n== persist_status_from_response ==\n";

reset_state();
$GLOBALS['options']['joinotify_alternative_license_activation'] = 'yes';
check( 'a valid response persists valid', License::persist_status_from_response( (object) array( 'is_valid' => true ) ) === true );
check( 'and clears the alternative flag', ! isset( $GLOBALS['options']['joinotify_alternative_license_activation'] ) );

reset_state();
check( 'a null response persists invalid', License::persist_status_from_response( null ) === false );
check( 'status option is invalid', 'invalid' === get_option('joinotify_license_status') );

echo "\n== check_license_object ==\n";

reset_state();
$GLOBALS['options']['joinotify_alternative_license'] = 'active';
$license = License::get_instance( JOINOTIFY_FILE );
$error = '';
$response = null;
// Used to return null from a method documented to answer true or false.
check( 'alternative activation answers false', false === $license->check_license_object( 'KEY', $error, $response ) );

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions\n"
	: "OK: {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
