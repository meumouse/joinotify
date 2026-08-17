<?php
/**
 * Standalone test harness for the telemetry buffer's pure logic.
 *
 * Three functions decide whether a busy site stays cheap: `merge()` collapses repeats and
 * enforces the ceilings, `take_batch()` keeps a request inside both of the service's
 * limits, and `remove_ids()` drains only what was confirmed. They take everything as
 * arguments, so no WordPress is needed — which is exactly why the rules were put there
 * rather than inline in the flush.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/telemetry-buffer-test.php
 *
 * @since 2.3.0
 */

// The class files guard with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../admin/src/Telemetry/Normalizer.php';
require __DIR__ . '/../admin/src/Telemetry/Event_Catalog.php';
require __DIR__ . '/../admin/src/Telemetry/Buffer.php';

use MeuMouse\Joinotify\Telemetry\Buffer;

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
 * Build an event the way Recorder would.
 */
function event( $id, $name, $props = array() ) {
	return array( 'id' => $id, 'name' => $name, 'at' => '2026-08-16T12:00:00Z', 'props' => $props );
}

$now = 1787000000;

echo "== merge: daily coalescing ==\n";

$incoming = array(
	event( 'a', 'feature.used', array( 'feature' => 'woocommerce', 'trigger' => 'order_paid' ) ),
	event( 'b', 'feature.used', array( 'feature' => 'woocommerce', 'trigger' => 'order_paid' ) ),
	event( 'c', 'feature.used', array( 'feature' => 'wpforms', 'trigger' => 'form_sent' ) ),
);

$buffer = Buffer::merge( Buffer::blank(), $incoming, array(), $now );

// A store with five thousand orders a day contributes one event per feature, not five
// thousand — and the question these answer ("which features run, on how many sites") is
// unchanged by the reduction.
check( 'the repeat collapses, the different one survives', 2 === count( $buffer['events'] ) );

$buffer = Buffer::merge( $buffer, array( event( 'd', 'feature.used', array( 'feature' => 'woocommerce', 'trigger' => 'order_paid' ) ) ), array(), $now + 3600 );
check( 'still collapsed an hour later', 2 === count( $buffer['events'] ) );

$buffer = Buffer::merge( $buffer, array( event( 'e', 'feature.used', array( 'feature' => 'woocommerce', 'trigger' => 'order_paid' ) ) ), array(), $now + 90000 );
check( 'the next day it counts again', 3 === count( $buffer['events'] ) );

$key_a = Buffer::coalesce_key( event( 'x', 'feature.used', array( 'feature' => 'woo', 'trigger' => 'paid' ) ) );
$key_b = Buffer::coalesce_key( event( 'y', 'feature.used', array( 'trigger' => 'paid', 'feature' => 'woo' ) ) );
check( 'property order does not change identity', $key_a === $key_b );

echo "\n== merge: a once-only event is never coalesced ==\n";

$buffer = Buffer::merge( Buffer::blank(), array( event( 'a', 'plugin.activated' ), event( 'b', 'plugin.activated' ) ), array(), $now );
check( 'both survive', 2 === count( $buffer['events'] ) );

echo "\n== merge: per-family ceiling ==\n";

$errors = array();

for ( $i = 0; $i < 30; $i++ ) {
	$errors[] = event( 'e' . $i, 'plugin.error', array( 'code' => 'code_' . $i ) );
}

$buffer = Buffer::merge( Buffer::blank(), $errors, array(), $now );

check( 'a burst of errors is capped at the family limit', 20 === count( $buffer['events'] ) );
check( 'and the overflow is counted, not hidden', 10 === $buffer['dropped'] );

echo "\n== trim: milestones outlive a flood ==\n";

$events = array( event( 'milestone', 'plugin.updated', array( 'previousVersion' => '2.4.0', 'newVersion' => '2.5.0' ) ) );

for ( $i = 0; $i < 20; $i++ ) {
	$events[] = event( 'q' . $i, 'queue.retried', array( 'reason' => 'timeout', 'attempt' => 1 ) );
}

$trimmed = Buffer::trim( $events, 5 );
$names = array_column( $trimmed['events'], 'name' );

check( 'the buffer is cut to the ceiling', 5 === count( $trimmed['events'] ) );
// The service records each milestone the first time it sees it and never again, so one
// evicted by a burst of retries is lost for that installation for good.
check( 'the milestone is still there', in_array( 'plugin.updated', $names, true ) );
check( 'the eviction is counted', 16 === $trimmed['dropped'] );

echo "\n== take_batch: both ceilings ==\n";

$many = array();

for ( $i = 0; $i < 250; $i++ ) {
	$many[] = event( 'n' . $i, 'plugin.activated' );
}

list( $batch, $ids ) = Buffer::take_batch( $many, 200, 56000 );

check( 'never more events than the service accepts', 200 === count( $batch ) );
check( 'the ids line up with the batch', 200 === count( $ids ) && 'n0' === $ids[0] );

$fat = array();

for ( $i = 0; $i < 200; $i++ ) {
	$fat[] = event( 'f' . $i, 'plugin.error', array(
		'code' => str_repeat( 'a', 60 ),
		'context' => str_repeat( 'b', 60 ),
		'fingerprint' => str_repeat( 'c', 10 ),
	) );
}

list( $batch, $ids ) = Buffer::take_batch( $fat, 200, 4000 );

// The byte limit is the one that gets forgotten: long error codes reach 64 kb well before
// they reach two hundred events, and the service answers 422 for the whole batch.
check( 'the byte ceiling stops it before the event ceiling', count( $batch ) < 200 && count( $batch ) > 0 );
check( 'the encoded batch fits the ceiling', strlen( json_encode( $batch ) ) <= 4000 );

// Leaving an oversized event behind would block every event queued after it forever.
list( $batch, $ids ) = Buffer::take_batch( array( event( 'huge', 'plugin.error', array( 'code' => str_repeat( 'a', 60 ) ) ) ), 200, 10 );
check( 'an oversized event still goes, rather than jamming the buffer', 1 === count( $batch ) );

echo "\n== remove_ids ==\n";

$stored = array( event( 'a', 'plugin.activated' ), event( 'b', 'plugin.activated' ), event( 'c', 'plugin.activated' ) );
$kept = Buffer::remove_ids( $stored, array( 'a', 'c' ) );

check( 'only the confirmed ones go', 1 === count( $kept ) && 'b' === $kept[0]['id'] );
// Anything recorded while the request was in flight has to survive it.
check( 'nothing is removed when nothing was confirmed', 3 === count( Buffer::remove_ids( $stored, array() ) ) );

echo "\n== normalize: junk in the option ==\n";

$buffer = Buffer::merge( 'not an array', array( event( 'a', 'plugin.activated' ) ), array(), $now );
check( 'a corrupted option does not lose the new event', 1 === count( $buffer['events'] ) );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );
