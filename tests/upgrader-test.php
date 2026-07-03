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
check( 'non-array completed coerced safely', is_array( Upgrader::get_pending_routines( '1.0.0', 'oops' ) ) );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
