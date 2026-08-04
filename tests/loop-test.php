<?php
/**
 * Standalone test harness for the workflow "loop" action.
 *
 * Exercises the loop wiring reconstruction, the per-item unrolling and the
 * end-to-end run through Workflow_Processor: one dispatch per collection item,
 * the correct item pinned on the payload for each iteration, a time delay inside
 * the loop body scheduling the remaining iterations as a continuation, and the
 * order-downloads source. No WordPress bootstrap is required: the WP functions
 * and the namespaced collaborators the processor touches are stubbed below so the
 * assertions stay on the loop logic itself.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/loop-test.php
 *
 * @since 2.1.1
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1024 * 1024 );
define( 'JOINOTIFY_DEV_MODE', false );
define( 'JOINOTIFY_DEBUG_MODE', false );

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

// Per-hook filter overrides so a test can inject a custom action handler, etc.
$GLOBALS['filter_overrides'] = array();

function apply_filters( $hook, $value = null ) {
	$args = func_get_args();

	if ( isset( $GLOBALS['filter_overrides'][ $hook ] ) ) {
		return call_user_func_array( $GLOBALS['filter_overrides'][ $hook ], array_slice( $args, 1 ) );
	}

	return $value;
}

function do_action() {}
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = 'default' ) { return $text; }

$GLOBALS['post_meta'] = array();
function get_post_meta( $id, $key, $single = false ) { global $post_meta; return $post_meta[ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { global $post_meta; $post_meta[ $id ][ $key ] = $value; return true; }
function get_post_status( $id ) { return 'publish'; }

// Global message helper: the loop list is literal in these tests, so returning it
// unchanged is enough (the real one also resolves placeholders/loop tokens).
function joinotify_prepare_message( $message, $payload = array() ) { return is_scalar( $message ) ? (string) $message : ''; }

// WooCommerce order stub for the order-downloads source test.
class WC_Order_Refund {}

$GLOBALS['wc_orders'] = array();
function wc_get_order( $id ) { global $wc_orders; return $wc_orders[ $id ] ?? false; }

/**
 * Minimal order double exposing get_downloadable_items().
 */
class Fake_WC_Order {
	private $downloads;

	public function __construct( $downloads ) { $this->downloads = $downloads; }
	public function get_downloadable_items() { return $this->downloads; }
	public function get_status() { return 'completed'; }
	public function get_id() { return 123; }
}

}

// ---------------------------------------------------------------------------
// Namespaced collaborator fakes (must exist before the real class is required)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Logger { public static function register_log( $m, $l = 'INFO' ) {} }
}

namespace MeuMouse\Joinotify\Admin {
	class Admin { public static function get_setting( $key ) { return 'no'; } }
}

namespace MeuMouse\Joinotify\Cron {
	// Records scheduled continuations instead of really scheduling them.
	class Schedule {
		public static function resolve_delay_seconds( $data ) { return 60; }
		public static function is_action_scheduler_available() { return true; }
		public static function is_wp_cron_active() { return true; }
		public static function schedule_actions( $post_id, $payload, $delay, $node, $key = '' ) {
			$GLOBALS['scheduled'][] = array( 'post_id' => $post_id, 'payload' => $payload, 'node' => $node );
			return true;
		}
	}
}

namespace MeuMouse\Joinotify\Integrations {
	// Returns whatever the test seeded, mirroring WC_Order::get_downloadable_items().
	class Woocommerce {
		public static function get_downloadable_items( $order ) {
			return $order && method_exists( $order, 'get_downloadable_items' ) ? $order->get_downloadable_items() : array();
		}
	}
}

// ---------------------------------------------------------------------------
// Test body
// ---------------------------------------------------------------------------

namespace {

use MeuMouse\Joinotify\Core\Workflow_Processor;

require __DIR__ . '/../admin/src/Core/Workflow_Processor.php';

$PROC = 'MeuMouse\\Joinotify\\Core\\Workflow_Processor';

/**
 * Call a protected/public static method by reflection.
 */
function call_static( $method, array $args ) {
	global $PROC;
	$ref = new \ReflectionMethod( $PROC, $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( null, $args );
}

/**
 * Inject a counting body action ('test_send') into the handle map, logging the
 * loop item value seen on each dispatch.
 */
function install_counting_handler() {
	$GLOBALS['dispatch_log'] = array();

	$GLOBALS['filter_overrides']['Joinotify/Workflow_Processor/Handle_Actions'] = function( $actions, $action, $post_id, $event_data ) {
		$actions['test_send'] = function() use ( $event_data ) {
			$GLOBALS['dispatch_log'][] = $event_data['loop']['item']['value'] ?? '(none)';
			return true;
		};

		return $actions;
	};
}

function body_send_node( $id ) {
	return array(
		'id' => $id,
		'type' => 'action',
		'data' => array( 'action' => 'test_send', 'connection_from' => array( 'source_id' => 'L', 'source_handle' => 'loop' ) ),
		'children' => array(),
	);
}

echo "\n== Test A: reorder_by_connections nests the loop body under action_loop ==\n";
$GLOBALS['scheduled'] = array();

$content_a = array(
	array( 'id' => 't1', 'type' => 'trigger', 'data' => array( 'trigger' => 'test_hook' ) ),
	// drifted top-level order: after-node stored before the body
	array( 'id' => 'a1', 'type' => 'action', 'data' => array( 'action' => 'test_send', 'connection_from' => array( 'source_id' => 'L', 'source_handle' => 'output' ) ), 'children' => array() ),
	array( 'id' => 'b1', 'type' => 'action', 'data' => array( 'action' => 'test_send', 'connection_from' => array( 'source_id' => 'L', 'source_handle' => 'loop' ) ), 'children' => array() ),
	array( 'id' => 'L', 'type' => 'action', 'data' => array( 'action' => 'loop', 'loop_source' => 'placeholder_list', 'connection_from' => array( 'source_id' => 't1', 'source_handle' => 'output' ) ), 'children' => array() ),
);

$reordered = call_static( 'reorder_by_connections', array( $content_a ) );

// expected top-level order: trigger, loop, after
check( 'top-level order is [trigger, loop, after]', ( $reordered[0]['id'] ?? '' ) === 't1' && ( $reordered[1]['id'] ?? '' ) === 'L' && ( $reordered[2]['id'] ?? '' ) === 'a1' );
check( 'loop body nested under children.action_loop', ( $reordered[1]['children']['action_loop'][0]['id'] ?? '' ) === 'b1' );
check( 'after-loop node is a top-level sibling (not inside the body)', count( $reordered ) === 3 );

echo "\n== Test B: build_loop_queue unrolls ctx + body with unique ids ==\n";
$items_b = array(
	array( 'type' => 'list', 'value' => 'A' ),
	array( 'type' => 'list', 'value' => 'B' ),
);
$queue_b = call_static( 'build_loop_queue', array( $items_b, array( body_send_node( 'b1' ) ), 'L' ) );

check( 'unrolled queue has 4 nodes (2 ctx + 2 body)', count( $queue_b ) === 4 );
check( 'first node is a loop_set_context', ( $queue_b[0]['data']['action'] ?? '' ) === 'loop_set_context' );
check( 'ctx pins item value A then B', ( $queue_b[0]['data']['loop_context']['item']['value'] ?? '' ) === 'A' && ( $queue_b[2]['data']['loop_context']['item']['value'] ?? '' ) === 'B' );
check( 'body ids are rewritten per iteration and unique', ( $queue_b[1]['id'] ?? '' ) === 'L::0::b1' && ( $queue_b[3]['id'] ?? '' ) === 'L::1::b1' );
check( 'ctx carries index/number/count', ( $queue_b[2]['data']['loop_context']['index'] ?? -1 ) === 1 && ( $queue_b[2]['data']['loop_context']['number'] ?? 0 ) === 2 && ( $queue_b[2]['data']['loop_context']['count'] ?? 0 ) === 2 );

echo "\n== Test C: resolve_loop_items (placeholder_list) splits by line ==\n";
$items_c = call_static( 'resolve_loop_items', array( array( 'loop_source' => 'placeholder_list', 'loop_list' => "One\nTwo\n\nThree" ), array() ) );
check( 'blank lines are skipped, 3 items resolved', count( $items_c ) === 3 );
check( 'values trimmed in order', ( $items_c[0]['value'] ?? '' ) === 'One' && ( $items_c[2]['value'] ?? '' ) === 'Three' );

echo "\n== Test D: end-to-end placeholder_list loop -> one dispatch per item ==\n";
install_counting_handler();
$GLOBALS['scheduled'] = array();

$content_d = array(
	array( 'id' => 't1', 'type' => 'trigger', 'data' => array( 'trigger' => 'test_hook' ) ),
	array( 'id' => 'L', 'type' => 'action', 'data' => array( 'action' => 'loop', 'loop_source' => 'placeholder_list', 'loop_list' => "Alpha\nBeta\nGamma", 'connection_from' => array( 'source_id' => 't1', 'source_handle' => 'output' ) ), 'children' => array() ),
	body_send_node( 'b1' ),
);

$payload_d = array( 'integration' => 'test', 'hook' => 'test_hook' );
Workflow_Processor::process_workflow_content( $content_d, 1, $payload_d );

check( 'exactly 3 dispatches (one per list item)', count( $GLOBALS['dispatch_log'] ) === 3 );
check( 'each dispatch saw its own item value in order', $GLOBALS['dispatch_log'] === array( 'Alpha', 'Beta', 'Gamma' ) );

echo "\n== Test E: a time delay inside the loop body schedules the remaining iterations ==\n";
install_counting_handler();
$GLOBALS['scheduled'] = array();

// body: [ delay, send ]. Each iteration sends AFTER a delay, so the first delay
// captures everything still queued (send of item0 + the rest of the iterations).
$delay_node = array( 'id' => 'd1', 'type' => 'action', 'data' => array( 'action' => 'time_delay', 'connection_from' => array( 'source_id' => 'L', 'source_handle' => 'loop' ) ), 'children' => array() );
$send_after = array( 'id' => 's1', 'type' => 'action', 'data' => array( 'action' => 'test_send', 'connection_from' => array( 'source_id' => 'd1', 'source_handle' => 'output' ) ), 'children' => array() );

$content_e = array(
	array( 'id' => 't1', 'type' => 'trigger', 'data' => array( 'trigger' => 'test_hook' ) ),
	array( 'id' => 'L', 'type' => 'action', 'data' => array( 'action' => 'loop', 'loop_source' => 'placeholder_list', 'loop_list' => "One\nTwo", 'connection_from' => array( 'source_id' => 't1', 'source_handle' => 'output' ) ), 'children' => array() ),
	$delay_node,
	$send_after,
);

$payload_e = array( 'integration' => 'test', 'hook' => 'test_hook' );
Workflow_Processor::process_workflow_content( $content_e, 2, $payload_e );

check( 'nothing dispatched before the first delay fires', count( $GLOBALS['dispatch_log'] ) === 0 );
check( 'a continuation was scheduled', count( $GLOBALS['scheduled'] ) === 1 );

// Resume: feed the captured continuation back through the scheduled-action entry point.
if ( ! empty( $GLOBALS['scheduled'] ) ) {
	$evt = $GLOBALS['scheduled'][0];
	Workflow_Processor::process_scheduled_action( $evt['post_id'], $evt['payload'], $evt['node'] );
}

// First resume sends item One, then hits the second iteration's delay -> reschedules.
check( 'after first resume, item One was sent', $GLOBALS['dispatch_log'] === array( 'One' ) );
check( 'the second iteration delay rescheduled a continuation', count( $GLOBALS['scheduled'] ) === 2 );

if ( count( $GLOBALS['scheduled'] ) >= 2 ) {
	$evt2 = $GLOBALS['scheduled'][1];
	Workflow_Processor::process_scheduled_action( $evt2['post_id'], $evt2['payload'], $evt2['node'] );
}

check( 'after second resume, both items delivered in order', $GLOBALS['dispatch_log'] === array( 'One', 'Two' ) );

echo "\n== Test F: order_downloads loop -> one dispatch per granted file, correct item pinned ==\n";
install_counting_handler();
$GLOBALS['scheduled'] = array();

$GLOBALS['wc_orders'][123] = new \Fake_WC_Order( array(
	array( 'download_name' => 'E-book', 'download_url' => 'https://x/dl?k=1', 'product_id' => 10, 'product_name' => 'Course', 'file' => array( 'name' => 'ebook.pdf', 'file' => '/files/ebook.pdf' ) ),
	array( 'download_name' => 'Bonus', 'download_url' => 'https://x/dl?k=2', 'product_id' => 10, 'product_name' => 'Course', 'file' => array( 'name' => 'bonus.zip', 'file' => '/files/bonus.zip' ) ),
) );

$content_f = array(
	array( 'id' => 't1', 'type' => 'trigger', 'data' => array( 'trigger' => 'woocommerce_grant_product_download_permissions', 'settings' => array() ) ),
	array( 'id' => 'L', 'type' => 'action', 'data' => array( 'action' => 'loop', 'loop_source' => 'order_downloads', 'connection_from' => array( 'source_id' => 't1', 'source_handle' => 'output' ) ), 'children' => array() ),
	body_send_node( 'b1' ),
);

$payload_f = array( 'integration' => 'woocommerce', 'hook' => 'woocommerce_grant_product_download_permissions', 'order_id' => 123 );
Workflow_Processor::process_workflow_content( $content_f, 3, $payload_f );

check( 'exactly 2 dispatches (one per granted file)', count( $GLOBALS['dispatch_log'] ) === 2 );
check( 'each dispatch saw its own download name in order', $GLOBALS['dispatch_log'] === array( 'E-book', 'Bonus' ) );

// The resolved items must carry the raw file reference so the loop_item attachment can deliver it.
$items_f = call_static( 'resolve_loop_order_downloads', array( $payload_f ) );
check( 'resolved download item carries file_ref for the attachment', ( $items_f[0]['file_ref'] ?? '' ) === '/files/ebook.pdf' && ( $items_f[1]['file_ref'] ?? '' ) === '/files/bonus.zip' );

echo "\n== Test G: loop tokens resolve centrally (message, caption AND attachment URL) ==\n";
require __DIR__ . '/../admin/src/Builder/Placeholders.php';

$loop_payload = array(
	'loop' => array(
		'item' => array(
			'value' => 'E-book',
			'file_name' => 'ebook.pdf',
			'download_url' => 'https://shop.test/?download_file=10&order=wc_x&key=abc',
			'product_name' => 'Course',
		),
		'index' => 0,
		'number' => 1,
		'count' => 2,
	),
);

$resolve_loop = 'MeuMouse\\Joinotify\\Builder\\Placeholders';

check( 'token in a URL string resolves (the reported bug)',
	$resolve_loop::replace_loop_tokens( '{{ loop_download_url }}', $loop_payload ) === 'https://shop.test/?download_file=10&order=wc_x&key=abc' );
check( 'file name token resolves', $resolve_loop::replace_loop_tokens( 'File: {{ loop_file_name }}', $loop_payload ) === 'File: ebook.pdf' );
check( 'number/count tokens resolve', $resolve_loop::replace_loop_tokens( '{{ loop_number }}/{{ loop_count }}', $loop_payload ) === '1/2' );
check( 'unknown loop token is left intact, not blanked', $resolve_loop::replace_loop_tokens( 'x {{ loop_unknown }} y', $loop_payload ) === 'x {{ loop_unknown }} y' );
check( 'outside a loop, tokens are left untouched', $resolve_loop::replace_loop_tokens( '{{ loop_value }}', array() ) === '{{ loop_value }}' );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n";
echo "-----------------------------------------\n";
echo "Assertions: {$assertions}   Failures: {$failures}\n";
echo "-----------------------------------------\n";

exit( $failures > 0 ? 1 : 0 );

}
