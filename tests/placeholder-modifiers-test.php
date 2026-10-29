<?php
/**
 * Standalone test harness for the placeholder modifiers and {{ wc_first_product_review_path }}.
 *
 * A token may carry modifiers after a pipe — "{{ wc_review_links | remove_home_url }}" — so a
 * value can complete the link of a WhatsApp template URL button whose base is the site address.
 * This harness runs the real Placeholders::replace_placeholders() and the real
 * Woocommerce::get_first_product_review_path() against stubbed WordPress/WooCommerce functions.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/placeholder-modifiers-test.php
 *
 * @since 2.7.0
 */

namespace MeuMouse\Joinotify\Core {

	class Logger {
		public static $logs = array();

		public static function register_log( $message ) {
			self::$logs[] = $message;
		}
	}
}

namespace MeuMouse\Joinotify\Admin {

	class Admin {
		public static function get_setting( $key ) { return ''; }
	}
}

namespace MeuMouse\Joinotify\Builder {

	class Triggers {
		public static function get_trigger_names( $integration ) {
			return array( 'woocommerce_checkout_order_processed' );
		}
	}
}

namespace MeuMouse\Joinotify\Integrations {

	abstract class Integrations_Base {}
}

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_DEV_MODE', true );

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
 * Assert two values are identical, printing both when they are not.
 */
function check_same( $label, $expected, $actual ) {
	check( $label, $expected === $actual );

	if ( $expected !== $actual ) {
		echo '        expected: ' . var_export( $expected, true ) . "\n";
		echo '        actual:   ' . var_export( $actual, true ) . "\n";
	}
}

// ---------------------------------------------------------------------------
// WordPress / WooCommerce stubs
// ---------------------------------------------------------------------------

$home_url = 'https://shop.example';
$placeholders_list = array();
$filters = array();

function __( $text, $domain = 'default' ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function home_url() { global $home_url; return $home_url; }
function wp_parse_url( $url ) { return parse_url( $url ); }

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	global $filters;
	$filters[ $hook ][] = $callback;
}

function apply_filters( $hook, $value, ...$args ) {
	global $filters, $placeholders_list;

	if ( 'Joinotify/Builder/Placeholders_List' === $hook ) {
		return $placeholders_list;
	}

	foreach ( $filters[ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}

	return $value;
}

$products = array();
$closed_reviews = array();

class WC_Product_Stub {
	public $id;
	public function __construct( $id ) { $this->id = $id; }
	public function get_name() { return 'Product ' . $this->id; }
}

class WC_Order_Item_Stub {
	private $product_id;
	public function __construct( $product_id ) { $this->product_id = $product_id; }
	public function get_product_id() { return $this->product_id; }
}

class WC_Order_Stub {
	public $items;
	public function __construct( $product_ids ) { $this->items = array_map( function( $id ) { return new WC_Order_Item_Stub( $id ); }, $product_ids ); }
	public function get_items( $type = '' ) { return $this->items; }
}

function wc_get_product( $id ) { global $products; return in_array( $id, $products, true ) ? new WC_Product_Stub( $id ) : false; }
function comments_open( $id ) { global $closed_reviews; return ! in_array( $id, $closed_reviews, true ); }
function get_permalink( $id ) { return home_url() . '/product/item-' . $id . '/'; }

require_once __DIR__ . '/../admin/src/Builder/Placeholders.php';
require_once __DIR__ . '/../admin/src/Integrations/Woocommerce.php';

use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Integrations\Woocommerce;

/**
 * Resolve a message against a WooCommerce-like payload.
 */
function resolve( $message, $mode = 'production' ) {
	return Placeholders::replace_placeholders( $message, array( 'integration' => 'woocommerce', 'trigger' => 'order_paid', 'fields' => array( '7' => 'R$ 1.250,00' ) ), $mode );
}

$placeholders_list = array(
	'woocommerce' => array(
		'{{ wc_review_links }}' => array(
			'triggers' => array( 'order_paid' ),
			'replacement' => array(
				'production' => "Shirt: https://shop.example/product/shirt/#reviews\nCap: https://shop.example/product/cap/#reviews",
				'sandbox' => 'https://shop.example/product/sample/#reviews',
			),
		),
		'{{ wc_payment_url }}' => array(
			'triggers' => array( 'order_paid' ),
			'replacement' => array( 'production' => 'https://www.shop.example/checkout/pay/12/?key=a$1\\b' ),
		),
	),
);

// ---------------------------------------------------------------------------
echo "\nModifiers\n";
// ---------------------------------------------------------------------------

check_same( 'remove drops every occurrence of the text',
	"Shirt: product/shirt/#reviews\nCap: product/cap/#reviews",
	resolve( '{{ wc_review_links | remove:"https://shop.example/" }}' ) );

check_same( 'remove_home_url keeps the path, the query and the "$1"/"\\" of the value',
	'checkout/pay/12/?key=a$1\\b',
	resolve( '{{ wc_payment_url | remove_home_url }}' ) );

check_same( 'modifiers chain left to right and single quotes work',
	"Shirt: shirt\nCap: cap",
	resolve( "{{wc_review_links|remove_home_url|remove:'product/'|replace:\"/#reviews\",\"\"}}" ) );

check_same( 'replace with commas and pipes inside quotes',
	'1.250,00 | BRL',
	resolve( '{{ field_id=[7] | replace:"R$ ","" | replace:",00",",00 | BRL" }}' ) );

check_same( 'quotes stored as entities by the rich text editor are decoded',
	'1.250,00',
	resolve( '{{ field_id=[7] | remove:&quot;R$ &quot; }}' ) );

check_same( 'the same token with and without modifiers in one message',
	'https://www.shop.example/checkout/pay/12/?key=a$1\\b -> checkout/pay/12/?key=a$1\\b',
	resolve( '{{ wc_payment_url }} -> {{ wc_payment_url | remove_home_url }}' ) );

check_same( 'modifiers apply in sandbox mode too',
	'product/sample/#reviews',
	resolve( '{{ wc_review_links | remove_home_url }}', 'sandbox' ) );

check_same( 'an unresolved token is kept as written, modifiers included',
	'Hi {{ unknown_token | remove:"x" }}!',
	resolve( 'Hi {{ unknown_token | remove:"x" }}!' ) );

Logger::$logs = array();

check_same( 'an unknown modifier is skipped and the rest still runs',
	"Shirt: product/shirt/#reviews\nCap: product/cap/#reviews",
	resolve( '{{ wc_review_links | shout | remove_home_url }}' ) );

check( 'the unknown modifier is logged in dev mode', in_array( 'Unknown placeholder modifier: shout', Logger::$logs, true ) );

check_same( 'a pipe outside any token is left alone',
	'a | b https://www.shop.example/checkout/pay/12/?key=a$1\\b',
	resolve( 'a | b {{ wc_payment_url }}' ) );

check_same( 'an empty modifier chain leaves the token as written',
	'{{ wc_payment_url | }}',
	resolve( '{{ wc_payment_url | }}' ) );

check( 'no private marker reaches the message', false === strpbrk( resolve( '{{ wc_review_links | remove_home_url }} {{ nope | remove:"a" }}' ), "\x1E\x1F" ) );

MeuMouse\Joinotify\Api\register_modifier_for_test();

check_same( 'a registered modifier receives the value and its arguments',
	'SHIRT-x',
	resolve( '{{ field_id=[7] | remove:"R$ " | remove:"1.250,00" | replace:"","" | tag:"SHIRT","x" }}' ) );

// ---------------------------------------------------------------------------
echo "\nremove_home_url\n";
// ---------------------------------------------------------------------------

check_same( 'http, https, www and protocol-relative links to the site are stripped',
	'a/ b/ c/ d',
	Placeholders::strip_home_url( 'http://shop.example/a/ https://www.shop.example/b/ //shop.example/c/ https://SHOP.example/d' ) );

check_same( 'links to other sites, including a longer host, are kept',
	'https://shop.example.br/a/ https://other.example/b/',
	Placeholders::strip_home_url( 'https://shop.example.br/a/ https://other.example/b/' ) );

$home_url = 'https://example.com/store';

check_same( 'a site in a subfolder strips the subfolder too',
	'product/shirt/#reviews https://example.com/blog/',
	Placeholders::strip_home_url( 'https://example.com/store/product/shirt/#reviews https://example.com/blog/' ) );

$home_url = 'https://shop.example';

// ---------------------------------------------------------------------------
echo "\n{{ wc_first_product_review_path }}\n";
// ---------------------------------------------------------------------------

$products = array( 10, 20, 30 );
$closed_reviews = array( 10 );

check_same( 'the first product open to reviews wins',
	'product/item-20/#reviews',
	Woocommerce::get_first_product_review_path( new WC_Order_Stub( array( 0, 99, 10, 20, 30 ) ) ) );

$closed_reviews = array( 10, 20, 30 );

check_same( 'with reviews closed everywhere the first existing product is used',
	'product/item-10/#reviews',
	Woocommerce::get_first_product_review_path( new WC_Order_Stub( array( 99, 10, 20 ) ) ) );

check_same( 'empty when no purchased product exists',
	'',
	Woocommerce::get_first_product_review_path( new WC_Order_Stub( array( 99 ) ) ) );

$closed_reviews = array( 10 );

check_same( 'wc_review_links still lists every reviewable product once',
	"Product 20: https://shop.example/product/item-20/#reviews\nProduct 30: https://shop.example/product/item-30/#reviews",
	Woocommerce::get_review_links( new WC_Order_Stub( array( 10, 20, 20, 30 ) ) ) );

// ---------------------------------------------------------------------------

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions failed\n"
	: "OK: all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}

namespace MeuMouse\Joinotify\Api {

	/**
	 * Register a test modifier through the real Extensions facade.
	 */
	function register_modifier_for_test() {
		require_once __DIR__ . '/../admin/src/Api/Extensions.php';

		Extensions::register_placeholder_modifier( 'TAG', function( $value, $args ) {
			return $args[0] . $value . '-' . $args[1];
		} );
	}
}
