<?php
/**
 * Standalone test harness for the Joinotify Cloud sync's pure parts: what each answer of the
 * platform means (Dispatcher::classify), how long a failed batch waits (Dispatcher::backoff), the
 * envelope of a site event (Outbox::envelope), the address comparison that catches a staging copy
 * (Reporter::origin) and the event catalog's names (Catalog::definitions).
 *
 * No WordPress bootstrap: the few WP functions reached are stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/cloud-sync-dispatcher-test.php
 *
 * @since 2.5.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$failures = 0;
$assertions = 0;

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

function __( $text ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }

require __DIR__ . '/../admin/src/Cloud_Sync/State.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Outbox.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Dispatcher.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Reporter.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Catalog.php';

use MeuMouse\Joinotify\Cloud_Sync\Catalog;
use MeuMouse\Joinotify\Cloud_Sync\Dispatcher;
use MeuMouse\Joinotify\Cloud_Sync\Outbox;
use MeuMouse\Joinotify\Cloud_Sync\Reporter;
use MeuMouse\Joinotify\Cloud_Sync\State;

echo "\nDispatcher::classify — events\n";

$accepted = Dispatcher::classify( 'event', 202, array(
	'data' => array( 'accepted' => 2, 'duplicates' => 0, 'rejected' => array(
		array( 'index' => 1, 'id' => 'x', 'error' => 'name: invalid' ),
	) ),
), 3 );
check( '202 delivers the batch', 'deliver' === $accepted['action'] );
check( '…and gives up on the event the platform rejected, by its index', array( 1 => 'name: invalid' ) === $accepted['dead'] );

$paused = Dispatcher::classify( 'event', 409, array(
	'error' => array( 'type' => 'site_url_mismatch', 'registeredUrl' => 'https://loja.test' ),
), 1 );
check( 'a copy at another address pauses the sync', 'pause' === $paused['action'] && State::PAUSE_URL_MISMATCH === $paused['pause'] );
check( '…remembering where the site was paired', 'https://loja.test' === $paused['registered_url'] );

check( 'a revoked key pauses (401)', State::PAUSE_AUTH === Dispatcher::classify( 'event', 401, array(), 1 )['pause'] );
check( 'a key without permission pauses (403)', State::PAUSE_AUTH === Dispatcher::classify( 'event', 403, array( 'error' => array( 'type' => 'key_permission_denied' ) ), 1 )['pause'] );
check( 'a site that is gone pauses (404 site_not_found)', State::PAUSE_SITE_MISSING === Dispatcher::classify( 'event', 404, array( 'error' => array( 'type' => 'site_not_found' ) ), 1 )['pause'] );
check( 'a 404 of an older API without the route retries instead', 'retry' === Dispatcher::classify( 'event', 404, array( 'error' => array( 'type' => 'not_found' ) ), 1 )['action'] );

$panel = Dispatcher::classify( 'event', 409, array( 'error' => array( 'type' => 'site_paused' ) ), 1 );
check( 'a site paused in the panel waits and asks again', 'backoff' === $panel['action'] && 900 === $panel['delay'] );
check( 'an account whose billing needs attention waits too (402)', 'backoff' === Dispatcher::classify( 'contact', 402, array(), 1 )['action'] );
check( 'a whole body refused is given up on (422)', 'reject' === Dispatcher::classify( 'event', 422, array( 'error' => array( 'type' => 'invalid_request' ) ), 1 )['action'] );
check( 'a network failure retries', 'retry' === Dispatcher::classify( 'event', 0, array(), 1 )['action'] );
check( 'a 5xx retries', 'http_503' === Dispatcher::classify( 'event', 503, array(), 1 )['error'] );
check( 'a 429 retries', 'retry' === Dispatcher::classify( 'event', 429, array(), 1 )['action'] );

echo "\nDispatcher::classify — contacts\n";

$synced = Dispatcher::classify( 'contact', 200, array(
	'data' => array( 'results' => array(
		array( 'index' => 0, 'status' => 'created' ),
		array( 'index' => 1, 'status' => 'skipped', 'reason' => 'no_phone' ),
		array( 'index' => 2, 'status' => 'failed', 'reason' => 'invalid_row' ),
		array( 'index' => 3, 'status' => 'failed', 'reason' => 'conflict_retry' ),
		array( 'index' => 4, 'status' => 'updated', 'reason' => 'kept_opt_out' ),
	) ),
), 5 );
check( '200 delivers the batch', 'deliver' === $synced['action'] );
check( 'a row refused for what it is dies', array( 2 => 'invalid_row' ) === $synced['dead'] );
check( 'a race on the number is not a death sentence', ! isset( $synced['dead'][3] ) );
check( 'what was skipped or kept is noted for the monitor', array( 1 => 'no_phone', 3 => 'conflict_retry', 4 => 'kept_opt_out' ) === $synced['notes'] );
check( 'an event-shaped 202 on the contacts route is not a delivery', 'retry' === Dispatcher::classify( 'contact', 202, array(), 1 )['action'] );

echo "\nDispatcher::backoff\n";

check( 'first retry after a minute', 60 === Dispatcher::backoff( 0 ) );
check( 'doubling', 480 === Dispatcher::backoff( 3 ) );
check( 'capped at an hour', 3600 === Dispatcher::backoff( 20 ) );
check( 'Retry-After wins', 120 === Dispatcher::backoff( 5, 120 ) );
check( '…within the cap', 3600 === Dispatcher::backoff( 0, 99999 ) );

echo "\nOutbox::envelope\n";

$envelope = Outbox::envelope( 'evt-1', 'wc.order.paid', array( 'order' => array( 'id' => 1 ) ), array( 'phone' => '+5511987654321' ), 'https://loja.test', 1790000000 );
check( 'carries the id, name and schema version', 'evt-1' === $envelope['id'] && 'wc.order.paid' === $envelope['name'] && 1 === $envelope['schema_version'] );
check( 'occurred_at is ISO 8601 in UTC', '2026-09-21T14:13:20Z' === $envelope['occurred_at'] );
check( 'carries the site address for the copy check', 'https://loja.test' === $envelope['site_url'] );
check( 'carries the contact block', array( 'phone' => '+5511987654321' ) === $envelope['contact'] );

$bare = Outbox::envelope( 'evt-2', 'custom.ping', array(), null, 'https://loja.test', 1790000000 );
check( 'no contact block when there is none', ! isset( $bare['contact'] ) );
check( 'empty data goes out as an object, not a list', '{}' === json_encode( $bare['data'] ) );

echo "\nReporter::origin\n";

check( 'ignores the path, the trailing slash and the case', 'https://loja.test' === Reporter::origin( 'https://Loja.TEST/wp/' ) );
check( 'keeps a port', 'http://localhost:8080' === Reporter::origin( 'http://localhost:8080/' ) );
check( 'tells a staging copy apart', Reporter::origin( 'https://staging.loja.test' ) !== Reporter::origin( 'https://loja.test' ) );
check( 'nothing for something that is not a URL', '' === Reporter::origin( 'loja' ) );

echo "\nCatalog::definitions\n";

$grammar = '/^(wp|wc|wcs|form|fcrc|custom)(\.[a-z0-9_]+){1,3}$/';
$all_valid = true;
$names = array();

foreach ( Catalog::definitions() as $events ) {
	foreach ( $events as $name => $sample ) {
		$names[] = $name;
		$all_valid = $all_valid && 1 === preg_match( $grammar, $name ) && is_array( $sample ) && ! empty( $sample );
	}
}

check( 'every event name follows the platform grammar, with a sample', $all_valid );
check( 'no name twice', count( $names ) === count( array_unique( $names ) ) );
check( 'the order samples carry the billing phone the flows read', '+5511987654321' === Catalog::definitions()['woocommerce']['wc.order.paid']['order']['billing']['phone'] );

echo "\n{$assertions} assertions, {$failures} failure(s).\n";
exit( $failures > 0 ? 1 : 0 );

}
