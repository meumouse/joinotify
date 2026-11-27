<?php
/**
 * Standalone test harness for the central upgrade manager's pure logic.
 *
 * Exercises Upgrader::resolve_previous_version() and ::get_pending_routines(),
 * which decide whether a site is a fresh install or a legacy upgrade and which
 * version-gated routines still need to run. These methods take their inputs as
 * arguments (no WordPress calls), so no WP bootstrap is required: get_routines()
 * degrades to the raw core list when apply_filters() is undefined.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/upgrader-test.php
 *
 * @since 2.0.0
 * @version 2.8.0
 */

// The class file guards with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../admin/src/Core/Upgrader.php';

use MeuMouse\Joinotify\Core\Upgrader;

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
 * Whether a routine id is among the pending routines for the given inputs.
 */
function has_pending( $from, array $completed, $id ) {
	foreach ( Upgrader::get_pending_routines( $from, $completed ) as $routine ) {
		if ( isset( $routine['id'] ) && $routine['id'] === $id ) {
			return true;
		}
	}

	return false;
}

echo "== resolve_previous_version ==\n";
check( 'stored version wins over inference', Upgrader::resolve_previous_version( '1.4.6', false ) === '1.4.6' );
check( 'stored version wins even when fresh flag set', Upgrader::resolve_previous_version( '2.0.0', true ) === '2.0.0' );
check( 'empty + fresh => fresh-install sentinel', Upgrader::resolve_previous_version( '', true ) === Upgrader::FRESH_INSTALL_VERSION );
check( 'empty + legacy footprint => legacy fallback', Upgrader::resolve_previous_version( '', false ) === Upgrader::LEGACY_FALLBACK_VERSION );
check( 'whitespace-only stored treated as empty', Upgrader::resolve_previous_version( '   ', true ) === Upgrader::FRESH_INSTALL_VERSION );
check( 'non-scalar stored treated as empty', Upgrader::resolve_previous_version( array( 'x' ), false ) === Upgrader::LEGACY_FALLBACK_VERSION );

echo "\n== get_routines ordering / shape ==\n";
$routines = Upgrader::get_routines();
check( 'core workflow routine is registered', has_pending( '1.0.0', array(), 'workflows_schema_2_0_0' ) );
check( 'routines sorted ascending by target version', (function () use ( $routines ) {
	$prev = '0';
	foreach ( $routines as $r ) {
		$v = $r['version'] ?? '0';
		if ( version_compare( $v, $prev, '<' ) ) {
			return false;
		}
		$prev = $v;
	}
	return true;
})() );

echo "\n== get_pending_routines gating ==\n";
check( 'legacy upgrade (1.0.0) runs the 2.0.0 workflow routine', has_pending( '1.0.0', array(), 'workflows_schema_2_0_0' ) );
check( 'legacy fallback exactly (1.4.6) runs the 2.0.0 routine', has_pending( '1.4.6', array(), 'workflows_schema_2_0_0' ) );
check( 'fresh install (0.0.0) still runs the routine (idempotent no-op)', has_pending( Upgrader::FRESH_INSTALL_VERSION, array(), 'workflows_schema_2_0_0' ) );
check( 'already at target (2.0.0) does NOT re-run', ! has_pending( '2.0.0', array(), 'workflows_schema_2_0_0' ) );
check( 'newer than target (2.5.0) does NOT run', ! has_pending( '2.5.0', array(), 'workflows_schema_2_0_0' ) );
check( 'completed routine is not repeated', ! has_pending( '1.0.0', array( 'workflows_schema_2_0_0' ), 'workflows_schema_2_0_0' ) );
check( 'upgrade from 2.3.0 runs the licensing cleanup routine', has_pending( '2.3.0', array(), 'drop_licensing_2_4_0' ) );
check( 'a site already on 2.4.0 skips the licensing cleanup', ! has_pending( '2.4.0', array(), 'drop_licensing_2_4_0' ) );
check( 'non-array completed coerced safely', is_array( Upgrader::get_pending_routines( '1.0.0', 'oops' ) ) );
check( 'upgrade from 2.5.1 seals the API key', has_pending( '2.5.1', array(), 'seal_cloud_api_token_2_6_0' ) );
check( 'a site already on 2.6.0 skips the API key sealing', ! has_pending( '2.6.0', array(), 'seal_cloud_api_token_2_6_0' ) );
check( 'the API key sealing runs once', ! has_pending( '2.5.1', array( 'seal_cloud_api_token_2_6_0' ), 'seal_cloud_api_token_2_6_0' ) );
check( 'upgrade from 2.6.2 moves the workflows to the payment trigger', has_pending( '2.6.2', array(), Upgrader::PAYMENT_TRIGGER_ROUTINE ) );
check( 'a site already on 2.7.0 skips the payment trigger move', ! has_pending( '2.7.0', array(), Upgrader::PAYMENT_TRIGGER_ROUTINE ) );
check( 'a site upgraded from 2.0.0 gets both workflow routines', has_pending( '1.4.6', array(), 'workflows_schema_2_0_0' ) && has_pending( '1.4.6', array(), Upgrader::PAYMENT_TRIGGER_ROUTINE ) );
check( 'upgrade from 2.7.3 switches WhatsApp back on', has_pending( '2.7.3', array(), 'enable_whatsapp_2_8_0' ) );
check( 'a site already on 2.8.0 skips the WhatsApp switch', ! has_pending( '2.8.0', array(), 'enable_whatsapp_2_8_0' ) );

echo "\n== enable_whatsapp_integration ==\n";

// Minimal option store: the routine only reads and writes joinotify_settings.
$GLOBALS['joinotify_test_options'] = array();
$GLOBALS['joinotify_test_writes'] = 0;

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['joinotify_test_options'] ) ? $GLOBALS['joinotify_test_options'][ $name ] : $default;
}

function update_option( $name, $value ) {
	$GLOBALS['joinotify_test_options'][ $name ] = $value;
	$GLOBALS['joinotify_test_writes']++;

	return true;
}

$GLOBALS['joinotify_test_options']['joinotify_settings'] = array( 'enable_whatsapp_integration' => 'no', 'joinotify_default_country_code' => '55' );
Upgrader::enable_whatsapp_integration();
$stored = $GLOBALS['joinotify_test_options']['joinotify_settings'];
check( 'a site that had WhatsApp off gets it back on', 'yes' === $stored['enable_whatsapp_integration'] );
check( 'the other settings are kept', '55' === $stored['joinotify_default_country_code'] );

$GLOBALS['joinotify_test_writes'] = 0;
Upgrader::enable_whatsapp_integration();
check( 'running it again writes nothing', 0 === $GLOBALS['joinotify_test_writes'] );

$GLOBALS['joinotify_test_options'] = array();
Upgrader::enable_whatsapp_integration();
check( 'a site with no settings stored is left alone', ! isset( $GLOBALS['joinotify_test_options']['joinotify_settings'] ) );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
