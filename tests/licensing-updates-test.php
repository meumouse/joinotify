<?php
/**
 * Standalone test harness for MDS-served plugin updates.
 *
 * Exercises Licensing\Updates: when MDS takes over update delivery, and what it
 * copies into the options the SDK's updater reads. Two update handlers on the
 * same core filters would fight over the update transient, so the gate deciding
 * which one runs is the thing worth pinning down.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/licensing-updates-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_VERSION', '2.1.0' );
define( 'JOINOTIFY_BASENAME', 'joinotify/joinotify.php' );
define( 'JOINOTIFY_MDS_API_URL', 'https://api.meumouse.com' );
define( 'JOINOTIFY_MDS_API_KEY', 'mds_live_test' );
define( 'JOINOTIFY_MDS_PUBLIC_KEY', 'cHVibGljLWtleQ==' );

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
$GLOBALS['license_valid'] = true;

function reset_state() {
	$GLOBALS['options'] = array();
	$GLOBALS['license_valid'] = true;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }
function update_site_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function is_multisite() { return false; }
function apply_filters( $hook, $value ) { return $value; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; }
function do_action( $hook, $a = null, $b = null ) {}
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function site_url() { return 'https://example.com'; }

}

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static function register_log( $message, $level = 'INFO' ) {}
	}
}

namespace MeuMouse\Joinotify\Api {
	class License {
		public static function is_valid() { return (bool) $GLOBALS['license_valid']; }
	}
}

// Minimal SDK surface: only what Updates touches.
namespace MeuMouse\MDS\SDK\Config {
	class Product {
		private $args;
		public function __construct( array $args ) { $this->args = $args; }
		public function slug() { return $this->args['product_slug']; }
		public function public_key() { return $this->args['public_key']; }
		public function key( $suffix = '' ) { return 'mds_joinotify' . ( '' === $suffix ? '' : '_' . $suffix ); }
	}
}

namespace MeuMouse\MDS\SDK\Api {
	class Client { public function __construct( $p, $v, $l ) {} }
}

namespace MeuMouse\MDS\SDK\Security {
	class SignatureVerifier {
		public function __construct( $k ) {}
		public static function is_supported() { return true; }
	}
}

namespace MeuMouse\MDS\SDK\Support {
	class Logger { public function __construct( $s ) {} }
	class Cache { public function __construct( $p ) {} }
}

namespace MeuMouse\MDS\SDK\License {
	class LicenseStatus {
		const STATUS_ACTIVE = 'active';
		const STATUS_INVALID = 'invalid';
		private $state;
		public function __construct( array $state = array() ) { $this->state = $state; }
		public function to_array() { return $this->state; }
	}

	class Manager { public function __construct( $p, $c, $l ) {} }
}

namespace MeuMouse\MDS\SDK\Updates {
	class PluginUpdater {
		public static $registered = 0;
		public function __construct( $p, $c, $lm, $cache, $logger ) {}
		public function register() { self::$registered++; }
	}
}

namespace {

require __DIR__ . '/../admin/src/Licensing/Support/Site.php';
require __DIR__ . '/../admin/src/Licensing/Dto/License_Result.php';
require __DIR__ . '/../admin/src/Licensing/Contracts/Driver.php';
require __DIR__ . '/../admin/src/Licensing/Drivers/Mds_Driver.php';
require __DIR__ . '/../admin/src/Licensing/Drivers/Legacy_Driver.php';
require __DIR__ . '/../admin/src/Licensing/Driver_State.php';
require __DIR__ . '/../admin/src/Licensing/Updates.php';

use MeuMouse\Joinotify\Licensing\Driver_State;
use MeuMouse\Joinotify\Licensing\Updates;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;
use MeuMouse\Joinotify\Licensing\Drivers\Legacy_Driver;
use MeuMouse\MDS\SDK\Updates\PluginUpdater;

echo "== who serves updates ==\n";

reset_state();
// A site still on the legacy server gets its updates from the version manifest,
// which needs no license token.
check( 'legacy sites are not served by mds', false === Updates::is_active() );

reset_state();
Driver_State::elect( Mds_Driver::ID, 'migrated' );
check( 'migrated sites are served by mds', true === Updates::is_active() );

echo "\n== license mirror ==\n";

reset_state();
Driver_State::elect( Mds_Driver::ID, 'migrated' );
$GLOBALS['options']['joinotify_license_key'] = 'ABCD-1234';
$GLOBALS['options']['joinotify_license_response_object'] = (object) array(
	'is_valid' => true,
	'expire_date' => '2030-01-01',
);

Updates::sync_license_state();

// The SDK's updater asks its own license manager whether the site is entitled.
// Mirroring rather than duplicating keeps the plugin the single source of truth.
check( 'copies the license key', 'ABCD-1234' === get_option('mds_joinotify_license_key') );

$state = get_option('mds_joinotify_license_state');
check( 'writes a state array', is_array( $state ) );
check( 'marks the license valid', true === $state['valid'] );
check( 'marks the status active', 'active' === $state['status'] );
check( 'carries the expiry', '2030-01-01' === $state['expires_at'] );
check( 'records the domain', 'example.com' === $state['domain'] );
// The SDK keeps a cached "valid" alive during an outage only while a successful
// check is recent, so the mirror has to look like a fresh success.
check( 'records a recent success', $state['last_success_at'] > 0 );

reset_state();
Driver_State::elect( Mds_Driver::ID, 'migrated' );
$GLOBALS['options']['joinotify_license_key'] = 'ABCD-1234';
$GLOBALS['options']['joinotify_license_response_object'] = (object) array( 'is_valid' => true, 'expire_date' => 'No expiry' );
Updates::sync_license_state();

$state = get_option('mds_joinotify_license_state');
// A perpetual license must not be mirrored as a date, or the SDK would read the
// sentinel as an expiry in 1970 and refuse every update.
check( 'a lifetime license has no expiry', null === $state['expires_at'] );

reset_state();
Driver_State::elect( Mds_Driver::ID, 'migrated' );
$GLOBALS['license_valid'] = false;
Updates::sync_license_state();

$state = get_option('mds_joinotify_license_state');
check( 'an invalid license mirrors as invalid', false === $state['valid'] );
check( 'and as an invalid status', 'invalid' === $state['status'] );
check( 'with no recorded success', 0 === $state['last_success_at'] );

echo "\n== registration ==\n";

reset_state();
PluginUpdater::$registered = 0;
Driver_State::elect( Mds_Driver::ID, 'migrated' );
new Updates();

check( 'registers the sdk updater', 1 === PluginUpdater::$registered );
check( 'and syncs the mirror on boot', array_key_exists( 'mds_joinotify_license_state', $GLOBALS['options'] ) );

reset_state();
PluginUpdater::$registered = 0;
new Updates();

// Two handlers on the same core filters would fight over the update transient.
check( 'registers nothing on legacy sites', 0 === PluginUpdater::$registered );
check( 'and writes no mirror', ! array_key_exists( 'mds_joinotify_license_state', $GLOBALS['options'] ) );

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions\n"
	: "OK: {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
