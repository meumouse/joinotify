<?php
/**
 * Standalone test harness for the WooCommerce refund triggers.
 *
 * Covers the argument order of the refund callbacks and the refund_amount
 * condition that reads the payload they produce. No WordPress bootstrap is
 * required: the WP/WC functions touched by Validations\Conditions are stubbed
 * below, and the refund callbacks are asserted against their source signature
 * because loading the whole integration would drag in the entire plugin.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/refund-trigger-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_DEV_MODE', false );

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
// WordPress / WooCommerce stubs
// ---------------------------------------------------------------------------

function apply_filters( $hook, $value ) { return $value; }
function get_user_meta( $user_id, $key, $single = false ) { return ''; }
function get_userdata( $user_id ) { return null; }
function get_current_user_id() { return 0; }
function get_post_type( $post ) { return ''; }
function get_the_author_meta( $field, $user_id ) { return ''; }

/**
 * Minimal stand-in for WC_Order. Only the getters reached by get_compare_value()
 * are implemented; get_total_refunded() is the order's cumulative refunded total.
 */
class WC_Order {
	public $id;
	public $total_refunded;

	public function __construct( $id, $total_refunded = 0 ) {
		$this->id = $id;
		$this->total_refunded = $total_refunded;
	}

	public function get_id() { return $this->id; }
	public function get_total_refunded() { return $this->total_refunded; }
	public function get_items( $type = '' ) { return array(); }
	public function get_status() { return 'processing'; }
	public function get_total() { return 100.0; }
	public function is_paid() { return true; }
	public function get_billing_email() { return 'customer@example.com'; }
	public function get_payment_method() { return 'bacs'; }
}

/**
 * Mirrors the real hierarchy: WC_Order_Refund extends WC_Abstract_Order, NOT
 * WC_Order, so it carries an amount and a parent but no billing data.
 */
class WC_Order_Refund {
	public $id;
	public $amount;
	public $parent_id;

	public function __construct( $id, $amount, $parent_id ) {
		$this->id = $id;
		$this->amount = $amount;
		$this->parent_id = $parent_id;
	}

	public function get_id() { return $this->id; }
	public function get_amount() { return $this->amount; }
	public function get_parent_id() { return $this->parent_id; }
}

class WC_Subscription {}
class WC_Cart {}

$GLOBALS['wc_objects'] = array();

function wc_get_order( $id ) {
	return $GLOBALS['wc_objects'][ (int) $id ] ?? false;
}

} // end global namespace

namespace MeuMouse\Joinotify\Core {
	/** Referenced by a use statement in Conditions; never called by these tests. */
	class Helpers {}
}

namespace {

require_once __DIR__ . '/../admin/src/Validations/Conditions.php';

use MeuMouse\Joinotify\Validations\Conditions;

echo "\n== woocommerce refund trigger callbacks ==\n";

// ---------------------------------------------------------------------------
// Callback signatures must match how WooCommerce fires the actions:
//   do_action( 'woocommerce_order_partially_refunded', $order_id, $refund_id )
//   do_action( 'woocommerce_order_fully_refunded',     $order_id, $refund_id )
// A swapped signature silently writes the refund ID into the payload's order_id.
// ---------------------------------------------------------------------------

$integration_src = file_get_contents( __DIR__ . '/../admin/src/Integrations/Woocommerce.php' );

/**
 * Extract the declared parameter names of a method from the source file.
 */
function signature_of( $src, $method ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $method, '/' ) . '\s*\(([^)]*)\)/', $src, $m ) ) {
		return null;
	}

	preg_match_all( '/\$(\w+)/', $m[1], $params );

	return $params[1];
}

check(
	'partially refunded callback takes ( $order_id, $refund_id )',
	signature_of( $integration_src, 'process_workflow_order_partially_refunded' ) === array( 'order_id', 'refund_id' )
);

check(
	'fully refunded callback takes ( $order_id, $refund_id )',
	signature_of( $integration_src, 'process_workflow_order_fully_refunded' ) === array( 'order_id', 'refund_id' )
);

check(
	'both refund callbacks share the same signature',
	signature_of( $integration_src, 'process_workflow_order_partially_refunded' )
		=== signature_of( $integration_src, 'process_workflow_order_fully_refunded' )
);

// Both hooks are registered with 2 accepted args, matching the signatures above.
preg_match_all(
	'/add_action\(\s*\'woocommerce_order_(?:partially|fully)_refunded\'.*?,\s*(\d+)\s*,\s*(\d+)\s*\)/',
	$integration_src,
	$registrations,
	PREG_SET_ORDER
);

check( 'both refund hooks are registered', count( $registrations ) === 2 );
check(
	'both refund hooks accept 2 arguments',
	count( $registrations ) === 2 && $registrations[0][2] === '2' && $registrations[1][2] === '2'
);

// The payload must carry the real order ID, not the refund ID.
preg_match(
	'/\'hook\'\s*=>\s*\'woocommerce_order_partially_refunded\'.*?\)\);/s',
	$integration_src,
	$payload_block
);

check(
	'partial refund payload maps order_id to $order_id',
	isset( $payload_block[0] ) && preg_match( '/\'order_id\'\s*=>\s*\$order_id/', $payload_block[0] ) === 1
);

check(
	'partial refund payload carries the refund separately',
	isset( $payload_block[0] ) && preg_match( '/\'refund_id\'\s*=>\s*\$refund_id/', $payload_block[0] ) === 1
);

check(
	'partial refund payload no longer carries the bogus is_partially_refunded flag',
	isset( $payload_block[0] ) && strpos( $payload_block[0], 'is_partially_refunded' ) === false
);

echo "\n== refund_amount condition ==\n";

// Order 100 has two refunds against it: 30 and 25, so 55 refunded in total.
$GLOBALS['wc_objects'] = array(
	100 => new WC_Order( 100, 55.0 ),
	201 => new WC_Order_Refund( 201, -30.0, 100 ),
	202 => new WC_Order_Refund( 202, -25.0, 100 ),
);

/**
 * Build the payload shape get_compare_value() expects.
 */
function refund_payload( $hook, $order_id, $refund_id = null ) {
	$payload = array(
		'integration' => 'woocommerce',
		'hook' => $hook,
		'order_id' => $order_id,
		'condition_content' => array( 'condition' => 'refund_amount' ),
	);

	if ( null !== $refund_id ) {
		$payload['refund_id'] = $refund_id;
	}

	return $payload;
}

// The partial trigger is scoped to one refund, so it reports that refund's amount
// (25) rather than the order's cumulative refunded total (55).
check(
	'partial refund reports the amount of that refund',
	Conditions::get_compare_value( 'refund_amount', refund_payload( 'woocommerce_order_partially_refunded', 100, 202 ) ) === 25.0
);

check(
	'partial refund reports each refund independently',
	Conditions::get_compare_value( 'refund_amount', refund_payload( 'woocommerce_order_partially_refunded', 100, 201 ) ) === 30.0
);

// The full refund trigger completes the order, so the cumulative total is the
// meaningful figure; reporting only the closing slice would understate it.
check(
	'full refund reports the order cumulative refunded total',
	Conditions::get_compare_value( 'refund_amount', refund_payload( 'woocommerce_order_fully_refunded', 100, 202 ) ) === 55.0
);

check(
	'a refund amount is always positive',
	Conditions::get_compare_value( 'refund_amount', refund_payload( 'woocommerce_order_partially_refunded', 100, 201 ) ) > 0
);

// Defensive branch: a third-party payload may still put a refund in order_id.
check(
	'a refund passed as order_id still resolves to its own amount',
	Conditions::get_compare_value( 'refund_amount', refund_payload( 'woocommerce_order_partially_refunded', 201 ) ) === 30.0
);

// An unknown refund must not fall through to a wrong figure.
check(
	'an unresolvable refund_id falls back to the order total refunded',
	Conditions::get_compare_value( 'refund_amount', refund_payload( 'woocommerce_order_partially_refunded', 100, 999 ) ) === 55.0
);

check(
	'a missing order yields null',
	Conditions::get_compare_value( 'refund_amount', refund_payload( 'woocommerce_order_partially_refunded', 404 ) ) === null
);

// Unrelated order conditions keep reading the order, not the refund.
check(
	'order_total still reads the parent order',
	Conditions::get_compare_value( 'order_total', refund_payload( 'woocommerce_order_partially_refunded', 100, 202 ) ) === 100.0
);

check(
	'customer_email still reads the parent order',
	Conditions::get_compare_value( 'customer_email', refund_payload( 'woocommerce_order_partially_refunded', 100, 202 ) ) === 'customer@example.com'
);

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );

} // end global namespace
