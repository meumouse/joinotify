<?php
/**
 * Standalone test harness for the WooCommerce triggers added in 2.7.0.
 *
 * Covers "Payment confirmed" (once per order, legacy slug until the upgrade routine
 * ran), "Customer account created" (deferred to shutdown, where the account came
 * from), "Product low in stock" / "Product out of stock" (once per request),
 * "Product back in stock" (only a real restock, one message per variable product)
 * and "Back in stock notice sent" (the email filter value stays untouched), plus the
 * placeholder scoping of the triggers that carry no order.
 *
 * No WordPress bootstrap is required: the workflow processor records the payloads
 * it receives instead of running them, and the WP/WC functions reached are stubbed.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/woocommerce-customer-stock-triggers-test.php
 *
 * @since 2.7.0
 */

namespace MeuMouse\Joinotify\Admin {

	class Admin {
		public static function get_setting( $key ) { return ''; }
	}
}

namespace MeuMouse\Joinotify\Builder {

	class Triggers {
		public static function get_trigger_names( $integration ) {
			return array(
				'woocommerce_new_order',
				'woocommerce_payment_complete',
				'woocommerce_order_status_completed',
				'woocommerce_created_customer',
				'woocommerce_low_stock',
				'woocommerce_no_stock',
				'woocommerce_product_back_in_stock',
				'woocommerce_customer_stock_notification',
			);
		}
	}
}

namespace MeuMouse\Joinotify\Core {

	class Workflow_Processor {
		public static $payloads = array();
		public static $throw = false;

		public static function process_workflows( $payload ) {
			self::$payloads[] = $payload;

			if ( self::$throw ) {
				throw new \RuntimeException( 'workflow failed' );
			}
		}
	}

	class Upgrader {
		const COMPLETED_OPTION = 'joinotify_completed_upgrades';
		const PAYMENT_TRIGGER_ROUTINE = 'workflows_schema_2_7_0';
	}

	class Logger {
		public static $logs = array();

		public static function register_log( $message, $level = 'INFO' ) {
			self::$logs[] = $level . ': ' . $message;
		}
	}
}

namespace MeuMouse\Joinotify\Integrations {

	abstract class Integrations_Base {}
}

namespace {

define( 'ABSPATH', __DIR__ . '/' );

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

$options = array();
$did_actions = array();
$actions = array();
$users = array();
$orders = array();
$products = array();

function __( $text, $domain = 'default' ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function get_site_url() { return 'https://shop.example'; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function wc_stock_amount( $amount ) { return (int) $amount; }
function wc_get_page_permalink( $page ) { return 'https://shop.example/my-account/'; }
function wc_get_is_paid_statuses() { return array( 'processing', 'completed' ); }

// reached by the order placeholders, which add_placeholders() builds for every trigger
function date_i18n( $format, $timestamp = false ) { return date( $format, $timestamp ? $timestamp : time() ); }
function wp_date( $format, $timestamp = null ) { return date( $format, $timestamp ? $timestamp : time() ); }
function wc_get_order_status_name( $status ) { return ucfirst( $status ); }
function get_woocommerce_currency_symbol( $currency = '' ) { return 'R$'; }
function wc_price( $price, $args = array() ) { return 'R$ ' . number_format( (float) $price, 2, ',', '.' ); }

function WC() {
	return new class {
		public function payment_gateways() {
			return new class {
				public function payment_gateways() { return array(); }
			};
		}
	};
}

function get_option( $key, $default = false ) {
	global $options;

	return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

function did_action( $hook ) {
	global $did_actions;

	return $did_actions[ $hook ] ?? 0;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	global $actions;

	$actions[] = array( $hook, $callback, $priority );

	return true;
}

function has_action( $hook, $callback = false ) {
	global $actions;

	foreach ( $actions as $action ) {
		if ( $action[0] === $hook && $action[1] === $callback ) {
			return $action[2];
		}
	}

	return false;
}

function get_userdata( $user_id ) {
	global $users;

	return isset( $users[ $user_id ] ) ? (object) array( 'ID' => $user_id ) : false;
}

function get_user_by( $field, $value ) {
	global $users;

	foreach ( $users as $id => $user ) {
		if ( 'email' === $field && $user['email'] === $value ) {
			return (object) array( 'ID' => $id );
		}
	}

	return false;
}

function wp_get_current_user() {
	return new class {
		public $first_name = '';
		public $last_name = '';
		public function exists() { return false; }
	};
}

function wc_get_order( $id ) {
	global $orders;

	return $orders[ $id ] ?? false;
}

function wc_get_product( $id ) {
	global $products;

	return $products[ $id ] ?? false;
}

class WC_Order {
	public $id;
	public $meta = array();
	public $meta_saves = 0;

	public function __construct( $id ) { $this->id = $id; }
	public function get_id() { return $this->id; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function save_meta_data() { $this->meta_saves++; }
	public function get_transaction_id() { return 'tx-from-order'; }
}

class WC_Product {
	public $id;
	public $parent_id = 0;
	public $data = array();
	public $changes = array();
	public $name = 'Blue shirt';
	public $stock = 4;

	public function __construct( $id, $parent_id = 0 ) {
		$this->id = $id;
		$this->parent_id = $parent_id;
	}

	public function get_id() { return $this->id; }
	public function get_parent_id() { return $this->parent_id; }
	public function get_data() { return $this->data; }
	public function get_changes() { return $this->changes; }
	public function get_name() { return $this->name; }
	public function get_permalink() { return 'https://shop.example/product/blue-shirt/'; }
	public function get_sku() { return 'BS-1'; }
	public function get_stock_quantity() { return $this->stock; }
}

class WC_Customer {
	public $id = 0;
	public $user = array();

	public function __construct( $id ) {
		global $users;

		if ( isset( $users[ $id ] ) ) {
			$this->id = $id;
			$this->user = $users[ $id ];
		}
	}

	public function get_id() { return $this->id; }
	public function get_billing_first_name() { return $this->user['billing_first_name'] ?? ''; }
	public function get_first_name() { return $this->user['first_name'] ?? ''; }
	public function get_billing_last_name() { return ''; }
	public function get_last_name() { return $this->user['last_name'] ?? ''; }
	public function get_email() { return $this->user['email'] ?? ''; }
	public function get_billing_phone() { return $this->user['phone'] ?? ''; }
}

/**
 * Shape of Automattic\WooCommerce\Internal\StockNotifications\Notification.
 */
class Stock_Notice_Stub {
	public $id;
	public function __construct( $id ) { $this->id = $id; }
	public function get_id() { return $this->id; }
	public function get_product_id() { return 40; }
	public function get_user_id() { return 0; }
	public function get_user_email() { return 'guest@example.com'; }
}

require_once __DIR__ . '/../admin/src/Core/Functions.php';
require_once __DIR__ . '/../admin/src/Integrations/Woocommerce.php';

use MeuMouse\Joinotify\Core\Workflow_Processor;
use MeuMouse\Joinotify\Integrations\Woocommerce;

$integration = ( new ReflectionClass( Woocommerce::class ) )->newInstanceWithoutConstructor();

/**
 * Reset the per-request state of the integration and the recorded payloads.
 */
function reset_request() {
	global $actions, $did_actions;

	$actions = array();
	$did_actions = array();
	$_POST = array();
	Workflow_Processor::$payloads = array();
	Workflow_Processor::$throw = false;

	foreach ( array( 'created_customers', 'restocking', 'restocked_products', 'stock_alerts', 'stock_notices' ) as $property ) {
		$reflection = new ReflectionProperty( Woocommerce::class, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( null, array() );
	}
}

function hooks_dispatched() {
	return array_map( function ( $payload ) {
		return $payload['hook'];
	}, Workflow_Processor::$payloads );
}

// ---------------------------------------------------------------------------
echo "== Payment confirmed ==\n";
// ---------------------------------------------------------------------------

reset_request();
$options['joinotify_completed_upgrades'] = array( 'workflows_schema_2_7_0' );
$orders[10] = new WC_Order( 10 );

$integration->process_workflow_payment_status_changed( 10, 'pending', 'processing' );
check( 'pending -> processing runs the trigger', hooks_dispatched() === array( 'woocommerce_payment_complete' ) );
check( 'the order is flagged', '' !== $orders[10]->get_meta( Woocommerce::META_PAYMENT_CONFIRMED ) );
check( 'the transaction ID falls back to the order', 'tx-from-order' === Workflow_Processor::$payloads[0]['transaction_id'] );

$integration->process_workflow_payment_complete( 10, 'tx-1' );
check( 'the gateway confirmation of the same order does not run it again', 1 === count( Workflow_Processor::$payloads ) );

$integration->process_workflow_payment_status_changed( 10, 'processing', 'completed' );
check( 'processing -> completed is not a new payment', 1 === count( Workflow_Processor::$payloads ) );

reset_request();
$orders[11] = new WC_Order( 11 );
$integration->process_workflow_payment_status_changed( 11, 'processing', 'completed' );
check( 'an order paid before the upgrade does not run it when completed', array() === hooks_dispatched() );

$integration->process_workflow_payment_status_changed( 11, 'pending', 'on-hold' );
check( 'an unpaid status does not run it', array() === hooks_dispatched() );

$integration->process_workflow_payment_complete( 11, 'tx-2' );
check( 'a gateway confirmation runs it', hooks_dispatched() === array( 'woocommerce_payment_complete' ) );
check( 'the gateway transaction ID is kept', 'tx-2' === Workflow_Processor::$payloads[0]['transaction_id'] );

reset_request();
$options['joinotify_completed_upgrades'] = array();
$orders[12] = new WC_Order( 12 );
$integration->process_workflow_payment_status_changed( 12, 'pending', 'completed' );
check( 'before the upgrade routine, the old slug runs too', hooks_dispatched() === array( 'woocommerce_payment_complete', 'woocommerce_checkout_order_processed' ) );
check( 'the old slug resolves the placeholders of the new trigger', 'woocommerce_payment_complete' === Workflow_Processor::$payloads[1]['trigger'] );
$options['joinotify_completed_upgrades'] = array( 'workflows_schema_2_7_0' );

reset_request();
$integration->process_workflow_payment_complete( 999 );
check( 'a missing order runs nothing', array() === hooks_dispatched() );

// ---------------------------------------------------------------------------
echo "\n== Customer account created ==\n";
// ---------------------------------------------------------------------------

reset_request();
check( 'the checkout block is the checkout', 'checkout' === Woocommerce::detect_account_source( array( 'source' => 'store-api' ) ) );
check( 'the order confirmation block', 'order_confirmation' === Woocommerce::detect_account_source( array( 'source' => 'delayed-account-creation' ) ) );

$did_actions['woocommerce_checkout_process'] = 1;
check( 'the classic checkout', 'checkout' === Woocommerce::detect_account_source( array( 'source' => 'unknown' ) ) );
$did_actions = array();

$_POST['woocommerce-register-nonce'] = 'abc';
check( 'the My Account form', 'my_account' === Woocommerce::detect_account_source( array( 'source' => 'unknown' ) ) );
$_POST = array();

check( 'anything else (REST API, another plugin)', 'other' === Woocommerce::detect_account_source( array( 'source' => 'unknown' ) ) );
check( 'malformed data is "other"', 'other' === Woocommerce::detect_account_source( 'oops' ) );

$users[7] = array( 'email' => 'ana@example.com', 'first_name' => 'Ana', 'phone' => '+5541999990000' );
$users[8] = array( 'email' => 'bia@example.com' );
$_POST['woocommerce-register-nonce'] = 'abc';
$integration->queue_customer_created( 7, array( 'source' => 'unknown' ), true );
$integration->queue_customer_created( 8, array( 'source' => 'store-api' ), false );
$integration->queue_customer_created( 0, array(), false );
$_POST = array();

check( 'nothing runs while the request is still saving the account', array() === hooks_dispatched() );
check( 'the dispatch is deferred to shutdown, before priority 0', -10 === has_action( 'shutdown', array( $integration, 'process_workflow_customers_created' ) ) );
check( 'shutdown is hooked once', 1 === count( array_filter( $actions, function ( $action ) { return 'shutdown' === $action[0]; } ) ) );

unset( $users[8] );
$integration->process_workflow_customers_created();
check( 'one run per account still present', hooks_dispatched() === array( 'woocommerce_created_customer' ) );
check( 'the payload carries the user', 7 === Workflow_Processor::$payloads[0]['user_id'] );
check( 'the payload carries where it was created', 'my_account' === Workflow_Processor::$payloads[0]['account_source'] );
check( 'the payload never carries a password', ! isset( Workflow_Processor::$payloads[0]['password'] ) && true === Workflow_Processor::$payloads[0]['password_generated'] );

$integration->process_workflow_customers_created();
check( 'the queue is emptied', 1 === count( Workflow_Processor::$payloads ) );

// ---------------------------------------------------------------------------
echo "\n== Low and out of stock ==\n";
// ---------------------------------------------------------------------------

reset_request();
$products[30] = new WC_Product( 30 );
$integration->process_workflow_low_stock( $products[30] );
$integration->process_workflow_low_stock( $products[30] );
$integration->process_workflow_no_stock( $products[30] );
check( 'low stock runs once per product and request, out of stock separately', hooks_dispatched() === array( 'woocommerce_low_stock', 'woocommerce_no_stock' ) );
check( 'the payload carries the product', 30 === Workflow_Processor::$payloads[0]['product_id'] );

$integration->process_workflow_low_stock( null );
check( 'a hook without a product runs nothing', 2 === count( Workflow_Processor::$payloads ) );

// ---------------------------------------------------------------------------
echo "\n== Product back in stock ==\n";
// ---------------------------------------------------------------------------

/**
 * Save a product through the two hooks, from one stock status to another.
 */
function save_stock_status( $integration, $product, $from, $to ) {
	$product->data = array( 'stock_status' => $from );
	$product->changes = $from === $to ? array() : array( 'stock_status' => $to );
	$integration->remember_restock( $product );

	if ( $from !== $to ) {
		$integration->process_workflow_back_in_stock( $product->get_id(), $to, $product );
	}
}

reset_request();
$products[40] = new WC_Product( 40 );
save_stock_status( $integration, $products[40], 'outofstock', 'instock' );
check( 'out of stock -> in stock runs the trigger', hooks_dispatched() === array( 'woocommerce_product_back_in_stock' ) );

reset_request();
save_stock_status( $integration, $products[40], 'onbackorder', 'instock' );
check( 'on backorder -> in stock runs the trigger', hooks_dispatched() === array( 'woocommerce_product_back_in_stock' ) );

reset_request();
save_stock_status( $integration, $products[40], 'instock', 'outofstock' );
check( 'going out of stock does not', array() === hooks_dispatched() );

reset_request();
$new_product = new WC_Product( 0 );
$new_product->changes = array( 'stock_status' => 'instock' );
$integration->remember_restock( $new_product );
$integration->process_workflow_back_in_stock( 41, 'instock', new WC_Product( 41 ) );
check( 'a product created in stock is not a restock', array() === hooks_dispatched() );

reset_request();
$products[50] = new WC_Product( 50 );
$products[51] = new WC_Product( 51, 50 );
$products[52] = new WC_Product( 52, 50 );
save_stock_status( $integration, $products[51], 'outofstock', 'instock' );
save_stock_status( $integration, $products[50], 'outofstock', 'instock' );
check( 'a variation and its variable product run once, for the variation', array( 51 ) === array_column( Workflow_Processor::$payloads, 'product_id' ) );

save_stock_status( $integration, $products[52], 'outofstock', 'instock' );
check( 'a second variation of the same product still runs', array( 51, 52 ) === array_column( Workflow_Processor::$payloads, 'product_id' ) );

reset_request();
save_stock_status( $integration, $products[50], 'outofstock', 'instock' );
save_stock_status( $integration, $products[51], 'outofstock', 'instock' );
check( 'a variable product announced itself covers its variations', array( 50 ) === array_column( Workflow_Processor::$payloads, 'product_id' ) );

// ---------------------------------------------------------------------------
echo "\n== Back in stock notice sent ==\n";
// ---------------------------------------------------------------------------

reset_request();
check( 'an enabled email stays enabled', true === $integration->process_workflow_customer_stock_notification( true, new Stock_Notice_Stub( 5 ) ) );
check( 'the notice runs the trigger', hooks_dispatched() === array( 'woocommerce_customer_stock_notification' ) );
check( 'the payload carries the notice', 5 === Workflow_Processor::$payloads[0]['notification_id'] && 40 === Workflow_Processor::$payloads[0]['product_id'] && 'guest@example.com' === Workflow_Processor::$payloads[0]['user_email'] );

check( 'a disabled email stays disabled', false === $integration->process_workflow_customer_stock_notification( false, new Stock_Notice_Stub( 5 ) ) );
check( 'the same notice runs once', 1 === count( Workflow_Processor::$payloads ) );

$integration->process_workflow_customer_stock_notification( true, new Stock_Notice_Stub( 0 ) );
check( 'the email preview (unsaved notice) runs nothing', 1 === count( Workflow_Processor::$payloads ) );

$integration->process_workflow_customer_stock_notification( true, null );
check( 'another object runs nothing', 1 === count( Workflow_Processor::$payloads ) );

Workflow_Processor::$throw = true;
$value = $integration->process_workflow_customer_stock_notification( true, new Stock_Notice_Stub( 6 ) );
check( 'a failing workflow does not reach WooCommerce', true === $value );

// ---------------------------------------------------------------------------
echo "\n== Placeholders ==\n";
// ---------------------------------------------------------------------------

reset_request();
$order_triggers = Woocommerce::get_order_trigger_names();
check( 'order placeholders leave out the triggers without an order', array( 'woocommerce_new_order', 'woocommerce_payment_complete', 'woocommerce_order_status_completed' ) === $order_triggers );

$users[9] = array( 'email' => 'carla@example.com', 'first_name' => 'Carla', 'billing_first_name' => '', 'phone' => '+5511988887777' );
$placeholders = $integration->add_placeholders( array(), array(
	'hook' => 'woocommerce_customer_stock_notification',
	'user_id' => 0,
	'user_email' => 'carla@example.com',
	'product_id' => 30,
) )['woocommerce'];

check( 'wc_billing_phone is not offered on a customer trigger', ! in_array( 'woocommerce_created_customer', $placeholders['{{ wc_billing_phone }}']['triggers'], true ) );
check( 'wc_customer_phone is offered on the customer triggers', Woocommerce::get_customer_trigger_names() === $placeholders['{{ wc_customer_phone }}']['triggers'] );
check( 'a guest notice finds the account by its email', '+5511988887777' === $placeholders['{{ wc_customer_phone }}']['replacement']['production'] );
check( 'the first name falls back to the account name', 'Carla' === $placeholders['{{ wc_customer_first_name }}']['replacement']['production'] );
check( 'the product name resolves', 'Blue shirt' === $placeholders['{{ wc_product_name }}']['replacement']['production'] );
check( 'the stock quantity resolves', '4' === $placeholders['{{ wc_product_stock_quantity }}']['replacement']['production'] );

$products[30]->stock = null;
$placeholders = $integration->add_placeholders( array(), array( 'hook' => 'woocommerce_low_stock', 'product_id' => 30 ) )['woocommerce'];
check( 'a product without stock management has an empty quantity', '' === $placeholders['{{ wc_product_stock_quantity }}']['replacement']['production'] );
check( 'a product trigger does not load a customer', '' === $placeholders['{{ wc_customer_phone }}']['replacement']['production'] );

$placeholders = $integration->add_placeholders( array(), array(
	'hook' => 'woocommerce_customer_stock_notification',
	'user_id' => 0,
	'user_email' => 'nobody@example.com',
	'product_id' => 30,
) )['woocommerce'];
check( 'a guest without an account keeps the notice email', 'nobody@example.com' === $placeholders['{{ wc_customer_email }}']['replacement']['production'] );
check( 'and has no phone', '' === $placeholders['{{ wc_customer_phone }}']['replacement']['production'] );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
}
