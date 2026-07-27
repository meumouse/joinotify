<?php
/**
 * Standalone test harness for the licensing migration.
 *
 * Exercises Licensing\Migrator: when the background re-check is queued, how a
 * confirmed license is adopted, how an unreachable server is retried, and — the
 * case with real money behind it — what happens when the new server disagrees
 * with a license that is active locally.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/licensing-migrator-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
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

$GLOBALS['options'] = array();
$GLOBALS['scheduled'] = array();
$GLOBALS['mds_result'] = null;
$GLOBALS['check_license_calls'] = array();

function reset_state() {
	$GLOBALS['options'] = array();
	$GLOBALS['scheduled'] = array();
	$GLOBALS['mds_result'] = null;
	$GLOBALS['check_license_calls'] = array();
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }
function apply_filters( $hook, $value ) { return $value; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function do_action( $hook, $a = null, $b = null ) {}
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function site_url() { return 'https://example.com'; }
function wp_rand( $min, $max ) { return $min; }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['scheduled'][ $hook ] ) ? $GLOBALS['scheduled'][ $hook ] : false; }
function wp_schedule_single_event( $timestamp, $hook ) { $GLOBALS['scheduled'][ $hook ] = $timestamp; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['scheduled'][ $hook ] ); }

}

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static function register_log( $message, $level = 'INFO' ) {}
	}
}

// Stand in for the whole license facade: the migrator only asks it to re-run
// the ordinary validation path and persist the outcome.
namespace MeuMouse\Joinotify\Api {
	class License {
		public static function check_license( $key, &$error = '', &$response = null, $file = '' ) {
			$GLOBALS['check_license_calls'][] = $key;
			$response = (object) array( 'is_valid' => true );

			return true;
		}

		public static function persist_status_from_response( $response ) {
			$GLOBALS['options']['joinotify_license_status'] = ( $response && ! empty( $response->is_valid ) ) ? 'valid' : 'invalid';

			return true;
		}
	}
}

// Scripted MDS driver: the migrator builds one directly, so the class itself is
// replaced rather than injected.
namespace MeuMouse\Joinotify\Licensing\Drivers {
	class Mds_Driver {
		const ID = 'mds';

		public function id() { return self::ID; }

		public function validate( $license_key ) {
			$GLOBALS['mds_validate_calls'][] = $license_key;

			return $GLOBALS['mds_result'];
		}
	}

	class Legacy_Driver {
		const ID = 'legacy';
	}
}

namespace {

require __DIR__ . '/../admin/src/Licensing/Dto/License_Result.php';
require __DIR__ . '/../admin/src/Licensing/Driver_State.php';
require __DIR__ . '/../admin/src/Licensing/Migrator.php';

use MeuMouse\Joinotify\Licensing\Driver_State;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;
use MeuMouse\Joinotify\Licensing\Migrator;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;
use MeuMouse\Joinotify\Licensing\Drivers\Legacy_Driver;

/** Put the site in the state an upgraded, licensed install would be in. */
function licensed_site() {
	$GLOBALS['options']['joinotify_license_key'] = 'ABCD-1234';
	$GLOBALS['options']['joinotify_license_response_object'] = (object) array(
		'is_valid' => true,
		'expire_date' => 'No expiry',
		'license_title' => 'Joinotify Pro',
	);
}

echo "== scheduling ==\n";

reset_state();
licensed_site();
Migrator::schedule_migration();

check( 'queues the re-check', false !== wp_next_scheduled( Migrator::EVENT ) );
check( 'snapshots the license first', isset( $GLOBALS['options'][ Migrator::BACKUP_OPTION ] ) );
check( 'the snapshot is the stored object', 'Joinotify Pro' === $GLOBALS['options'][ Migrator::BACKUP_OPTION ]->license_title );
// Every site on a shared host updates in the same window; firing immediately
// would have them all call the licensing server at once.
check( 'the first attempt is delayed', wp_next_scheduled( Migrator::EVENT ) > time() );

reset_state();
Migrator::schedule_migration();
check( 'an unlicensed site queues nothing', false === wp_next_scheduled( Migrator::EVENT ) );

reset_state();
licensed_site();
Driver_State::elect( Mds_Driver::ID, 'already there' );
Migrator::schedule_migration();
check( 'a site already on mds queues nothing', false === wp_next_scheduled( Migrator::EVENT ) );

reset_state();
licensed_site();
$GLOBALS['options'][ Migrator::BACKUP_OPTION ] = (object) array( 'license_title' => 'Original' );
Migrator::schedule_migration();
// A second run must not overwrite the pre-migration copy with post-migration
// state, or the snapshot stops being a way back.
check( 'an existing snapshot is preserved', 'Original' === $GLOBALS['options'][ Migrator::BACKUP_OPTION ]->license_title );

echo "\n== the new server confirms the license ==\n";

reset_state();
licensed_site();
$GLOBALS['mds_result'] = License_Result::valid( array( 'license_title' => 'Pro' ), 'License is valid.' );
Migrator::run();

check( 'switches to mds', Mds_Driver::ID === Driver_State::current() );
check( 'records why', false !== strpos( Driver_State::details()['reason'], 'migrated' ) );
// Re-running the ordinary validation path keeps the stored object, caches and
// expiry schedule written by one code path rather than two.
check( 're-runs the normal validation', array( 'ABCD-1234' ) === $GLOBALS['check_license_calls'] );
check( 'persists the status', 'valid' === get_option('joinotify_license_status') );
check( 'stops retrying', false === wp_next_scheduled( Migrator::EVENT ) );
check( 'clears the attempt counter', false === get_option( Migrator::ATTEMPTS_OPTION ) );
check( 'flags nothing for review', null === Migrator::pending_notice() );

echo "\n== the server cannot be reached ==\n";

reset_state();
licensed_site();
$GLOBALS['mds_result'] = License_Result::transport_failure('Connection timed out');
Migrator::run();

check( 'stays on legacy', Legacy_Driver::ID === Driver_State::current() );
check( 'counts the attempt', 1 === (int) get_option( Migrator::ATTEMPTS_OPTION ) );
check( 'schedules another try', false !== wp_next_scheduled( Migrator::EVENT ) );
// Nothing about the license is known, so nothing about it may change.
check( 'leaves the stored license alone', isset( $GLOBALS['options']['joinotify_license_response_object'] ) );
check( 'flags nothing for review', null === Migrator::pending_notice() );

check( 'the first retry waits an hour', ( wp_next_scheduled( Migrator::EVENT ) - time() ) === HOUR_IN_SECONDS );

$GLOBALS['scheduled'] = array();
Migrator::run();
check( 'counts the second attempt', 2 === (int) get_option( Migrator::ATTEMPTS_OPTION ) );
check( 'backs off on the second retry', ( wp_next_scheduled( Migrator::EVENT ) - time() ) === 6 * HOUR_IN_SECONDS );

$GLOBALS['scheduled'] = array();
for ( $i = 0; $i < 10; $i++ ) {
	$GLOBALS['scheduled'] = array();
	Migrator::run();
}
// The retry never gives up: an outage that outlasts the backoff table must not
// leave a site permanently stranded on a server that is being switched off.
check( 'keeps retrying at the longest interval', ( wp_next_scheduled( Migrator::EVENT ) - time() ) === DAY_IN_SECONDS );
check( 'still on legacy', Legacy_Driver::ID === Driver_State::current() );

echo "\n== the new server disagrees ==\n";

reset_state();
licensed_site();
$GLOBALS['mds_result'] = License_Result::business_failure( 'License key not found.', array( 'reason' => 'license_not_found' ) );
Migrator::run();

// The decisive policy: a record missing from the server migration is far more
// likely than a customer who should lose access, and guessing wrong means a
// silent outage for someone who paid.
check( 'does not deactivate', isset( $GLOBALS['options']['joinotify_license_response_object'] ) );
check( 'does not clear the key', 'ABCD-1234' === get_option('joinotify_license_key') );
check( 'does not mark the license invalid', false === get_option('joinotify_license_status') );
check( 'does not switch backends', Legacy_Driver::ID === Driver_State::current() );

$pending = Migrator::pending_notice();
check( 'flags it for review', is_array( $pending ) );
check( 'records the reason', 'license_not_found' === $pending['reason'] );
check( 'records the message', 'License key not found.' === $pending['message'] );
check( 'records when', $pending['flagged_at'] > 0 );
check( 'stops retrying', false === wp_next_scheduled( Migrator::EVENT ) );

Migrator::clear_pending();
check( 'the flag can be cleared', null === Migrator::pending_notice() );

echo "\n== run guards ==\n";

reset_state();
$GLOBALS['scheduled'][ Migrator::EVENT ] = time() + 60;
Migrator::run();
check( 'an unlicensed site cancels the event', false === wp_next_scheduled( Migrator::EVENT ) );

reset_state();
licensed_site();
Driver_State::elect( Mds_Driver::ID, 'done' );
$GLOBALS['scheduled'][ Migrator::EVENT ] = time() + 60;
$GLOBALS['mds_validate_calls'] = array();
Migrator::run();
check( 'a migrated site cancels the event', false === wp_next_scheduled( Migrator::EVENT ) );
check( 'and does not call the server again', empty( $GLOBALS['mds_validate_calls'] ) );

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions\n"
	: "OK: {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
