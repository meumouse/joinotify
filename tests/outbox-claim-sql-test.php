<?php
/**
 * Standalone test harness for the SQL Outbox::claim() builds: every value reaches the database
 * through a `$wpdb->prepare()` placeholder, and no id is written into the query text — the one
 * `IN (...)` list that used to be concatenated included.
 *
 * No WordPress bootstrap: a fake $wpdb records each prepare() call (the template and its
 * arguments apart) and answers the SELECTs claim() makes.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/outbox-claim-sql-test.php
 *
 * @since 2.5.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

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

function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000000'; }

/**
 * Minimal $wpdb: prepare() keeps the template and the arguments apart, so the test can tell a
 * bound value from one written into the SQL.
 */
class Fake_Wpdb {
	public $prefix = 'wp_';
	public $prepared = array();
	public $ids = array();

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$this->prepared[] = array( 'query' => $query, 'args' => $args );
		return $query;
	}

	public function query( $sql ) { return 0; }

	public function get_col( $sql ) { return $this->ids; }

	public function get_results( $sql, $output = null ) {
		return array_map( function ( $id ) {
			return array( 'id' => $id, 'event_id' => "evt-{$id}", 'attempts' => 0, 'payload' => '{}' );
		}, $this->ids );
	}
}

require __DIR__ . '/../admin/src/Cloud_Sync/Outbox.php';

use MeuMouse\Joinotify\Cloud_Sync\Outbox;

echo "\nOutbox::claim — the ids it claims\n";

$GLOBALS['wpdb'] = new Fake_Wpdb();
$GLOBALS['wpdb']->ids = array( '17', '23', '42' );

$claimed = Outbox::claim( 'event', 3 );
check( 'claims the three pending rows', array( 17, 23, 42 ) === array_column( $claimed, 'id' ) );

$claim_update = null;
foreach ( $GLOBALS['wpdb']->prepared as $call ) {
	if ( false !== strpos( $call['query'], "SET status = 'sending'" ) ) {
		$claim_update = $call;
	}
}

check( 'the claim UPDATE goes through prepare()', null !== $claim_update );
check( '…with one %d placeholder per id in its IN list', null !== $claim_update && false !== strpos( $claim_update['query'], 'id IN (%d,%d,%d)' ) );
check( '…and the ids among its arguments, as integers', null !== $claim_update && array( 17, 23, 42 ) === array_slice( $claim_update['args'], -3 ) );

echo "\nOutbox::claim — no value in the query text\n";

foreach ( $GLOBALS['wpdb']->prepared as $i => $call ) {
	$literal = preg_match( '/\b(17|23|42)\b/', $call['query'] );
	check( "query #{$i} carries no id in its text", 0 === $literal );
}

echo "\n{$assertions} assertions, {$failures} failed\n";
exit( $failures > 0 ? 1 : 0 );

}
