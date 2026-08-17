<?php
/**
 * Standalone test harness for the telemetry dispatch policy.
 *
 * The service answers every batch with directives, and this is where obeying them is
 * decided: how long to wait, whether the batch stays in the buffer, and whether to stop
 * altogether. Getting the backoff wrong is the kind of bug that only shows up as load on
 * the service months later, so the curve is asserted rather than reasoned about.
 *
 * Pure functions over the stored state; no WordPress required.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/telemetry-policy-test.php
 *
 * @since 2.5.0
 */

// The class file guards with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../admin/src/Telemetry/Policy.php';

use MeuMouse\Joinotify\Telemetry\Policy;

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

$now = 1787000000;

echo "== apply: the service's directives ==\n";

$state = Policy::apply( Policy::defaults(), array(
	'accepted' => 3,
	'discarded' => 0,
	'optedOut' => false,
	'intervalSeconds' => 43200,
	'sampleRate' => 25,
), $now );

check( 'the interval is adopted', 43200 === $state['interval'] );
check( 'the sample rate is stored', 25 === $state['sample_rate'] );
check( 'a success clears the failure count', 0 === $state['failures'] );
check( 'and unpauses', false === $state['paused'] );

$state = Policy::apply( Policy::defaults(), array( 'optedOut' => true ), $now );
check( 'the account opt-out is recorded', true === $state['opted_out'] );

// A bad interval either hammers the service or silences the site for a year, and neither
// announces itself.
$state = Policy::apply( Policy::defaults(), array( 'intervalSeconds' => 5 ), $now );
check( 'an absurdly short interval is ignored', Policy::DEFAULT_INTERVAL === $state['interval'] );

$state = Policy::apply( Policy::defaults(), array( 'intervalSeconds' => 999999 ), $now );
check( 'an absurdly long one is ignored too', Policy::DEFAULT_INTERVAL === $state['interval'] );

$state = Policy::apply( Policy::defaults(), array( 'sampleRate' => 400 ), $now );
check( 'the sample rate is clamped', 100 === $state['sample_rate'] );

$state = Policy::apply( Policy::defaults(), array(), $now );
check( 'an empty response leaves the defaults alone', Policy::DEFAULT_INTERVAL === $state['interval'] );

echo "\n== next_attempt: the backoff curve ==\n";

check( 'a success waits the normal interval', ( $now + 21600 ) === Policy::next_attempt( 202, 0, 21600, $now ) );
check( 'the first failure waits the base', ( $now + 1800 ) === Policy::next_attempt( 503, 1, 21600, $now ) );
check( 'the second doubles it', ( $now + 3600 ) === Policy::next_attempt( 503, 2, 21600, $now ) );
check( 'the third doubles again', ( $now + 7200 ) === Policy::next_attempt( 503, 3, 21600, $now ) );
check( 'it stops growing at a day', ( $now + 86400 ) === Policy::next_attempt( 503, 20, 21600, $now ) );
check( 'a transport failure backs off like a 5xx', ( $now + 1800 ) === Policy::next_attempt( 0, 1, 21600, $now ) );
check( 'Retry-After wins when the service sends one', ( $now + 120 ) === Policy::next_attempt( 429, 3, 21600, $now, 120 ) );
check( 'but never past the ceiling', ( $now + 86400 ) === Policy::next_attempt( 429, 1, 21600, $now, 999999 ) );
// Repeating a rejected body fails identically forever; it is a bug to read, not a wait.
check( 'a rejected body goes back to the normal schedule', ( $now + 21600 ) === Policy::next_attempt( 422, 1, 21600, $now ) );
check( 'a dead key schedules nothing', 0 === Policy::next_attempt( 401, 1, 21600, $now ) );

echo "\n== keeps_batch ==\n";

check( 'a success drains the batch', ! Policy::keeps_batch( 202 ) );
// Without this the buffer would never get past the batch the service refuses.
check( 'a rejected body is thrown away, not retried forever', ! Policy::keeps_batch( 422 ) );
check( 'a rate limit keeps it', Policy::keeps_batch( 429 ) );
check( 'a server error keeps it', Policy::keeps_batch( 503 ) );
check( 'a transport failure keeps it', Policy::keeps_batch( 0 ) );

echo "\n== pauses ==\n";

check( 'a revoked key stops dispatching', Policy::pauses( 401 ) );
check( 'a forbidden key too', Policy::pauses( 403 ) );
check( 'a rate limit does not', ! Policy::pauses( 429 ) );

echo "\n== normalize: junk in the option ==\n";

$state = Policy::normalize( 'not an array' );
check( 'a corrupted option falls back to the defaults', Policy::DEFAULT_INTERVAL === $state['interval'] && 100 === $state['sample_rate'] );

$state = Policy::normalize( array( 'interval' => '3600', 'opted_out' => 1 ) );
check( 'stored strings are coerced', 3600 === $state['interval'] && true === $state['opted_out'] );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );
