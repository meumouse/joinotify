<?php
/**
 * Standalone test harness for the WooCommerce shipping placeholders.
 *
 * {{ wc_shipping_address }} was documented as the order's shipping address, but it has always
 * resolved to WC_Order::get_shipping_to_display() — the shipping cost and method as price markup
 * ("R$ 20,00 via SEDEX"), never the address. Mapping it to a template variable meant for the
 * delivery address sent customers the carrier and the price instead.
 *
 * The fix keeps the token's value, so saved workflows send what they always sent, but as plain
 * text; relabels it in the builder; and adds {{ wc_shipping_method_total }} as a name that says
 * what the value is. This harness runs the real Woocommerce::add_placeholders() against an
 * order stub that mirrors the three branches of WooCommerce's get_shipping_to_display().
 *
 * No WordPress bootstrap is required: the WP/WC functions touched are stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/shipping-placeholder-test.php
 *
 * @since 2.4.2
 */

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
// WooCommerce / WordPress stubs
// ---------------------------------------------------------------------------

function __( $text, $domain = 'default' ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function get_option( $key, $default = false ) { return 'Y-m-d'; }
function date_i18n( $format, $timestamp = false ) { return date( $format, $timestamp ? $timestamp : time() ); }
function wp_date( $format, $timestamp = null ) { return date( $format, $timestamp ? $timestamp : time() ); }
function get_site_url() { return 'https://shop.example'; }
function wc_get_order_status_name( $status ) { return ucfirst( $status ); }

function wp_get_current_user() {
	return new class {
		public function exists() { return false; }
	};
}

/**
 * Currency symbols exactly as WooCommerce stores them: HTML entities, not glyphs.
 */
function get_woocommerce_currency_symbol( $currency = '' ) {
	return '&#82;&#36;';
}

/**
 * Mirrors the markup wc_price() produces: nested spans, an encoded symbol and a
 * non-breaking space between the symbol and the amount.
 */
function wc_price( $price, $args = array() ) {
	$amount = number_format( (float) $price, 2, ',', '.' );

	return '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#82;&#36;</span>&nbsp;' . $amount . '</bdi></span>';
}

/**
 * Mirrors wp_strip_all_tags(): drops script/style bodies, then every tag.
 */
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );

	return trim( strip_tags( $text ) );
}

/**
 * Order stub. get_shipping_to_display() reproduces the three branches of the WooCommerce
 * method (includes/abstracts/abstract-wc-order.php): cost > 0 gives the price markup plus an
 * optional tax label and "via <method>"; a free shipping line gives the method name alone;
 * no shipping line at all gives "Free!". Every other getter returns an empty string.
 */
class WC_Order {
	public $shipping_total = 0.0;
	public $shipping_method = '';
	public $tax_label = '';
	public $address = array();

	public function get_shipping_to_display( $tax_display = '' ) {
		if ( 0 < abs( (float) $this->shipping_total ) ) {
			$shipping = wc_price( $this->shipping_total );

			if ( '' !== $this->tax_label ) {
				$shipping .= '&nbsp;<small class="tax_label">' . $this->tax_label . '</small>';
			}

			return $shipping . '&nbsp;<small class="shipped_via">' . sprintf( 'via %s', $this->shipping_method ) . '</small>';
		}

		return '' !== $this->shipping_method ? $this->shipping_method : 'Free!';
	}

	public function get_items( $type = '' ) { return array(); }
	public function get_date_created() { return '2026-09-11 10:00:00'; }
	public function get_coupon_codes() { return array(); }
	public function get_total() { return 120.0; }
	public function get_total_discount() { return 0.0; }
	public function get_total_tax() { return 0.0; }
	public function get_total_refunded() { return 0.0; }
	public function get_currency() { return 'BRL'; }
	public function get_payment_method() { return ''; }

	public function __call( $name, $args ) {
		if ( preg_match( '/^get_shipping_(\w+)$/', $name, $matches ) && isset( $this->address[ $matches[1] ] ) ) {
			return $this->address[ $matches[1] ];
		}

		return '';
	}
}

function WC() {
	return new class {
		public function payment_gateways() {
			return new class {
				public function payment_gateways() { return array(); }
			};
		}
	};
}

$orders = array();

function wc_get_order( $id ) {
	global $orders;

	return isset( $orders[ $id ] ) ? $orders[ $id ] : false;
}

require_once __DIR__ . '/../admin/src/Core/Functions.php';
require_once __DIR__ . '/../admin/src/Integrations/Woocommerce.php';

use MeuMouse\Joinotify\Integrations\Woocommerce;

/**
 * Build the WooCommerce placeholder group for an order (or the builder's sandbox when null).
 */
function wc_placeholders( $order_id = null ) {
	$integration = ( new ReflectionClass( Woocommerce::class ) )->newInstanceWithoutConstructor();
	$payload = null !== $order_id ? array( 'order_id' => $order_id ) : array();
	$placeholders = $integration->add_placeholders( array(), $payload );

	return $placeholders['woocommerce'];
}

function make_order( $id, $total, $method, $tax_label = '' ) {
	global $orders;

	$order = new WC_Order();
	$order->shipping_total = $total;
	$order->shipping_method = $method;
	$order->tax_label = $tax_label;
	$order->address = array(
		'address_1' => 'Daisy Avenue, 450',
		'city' => 'Curitiba',
		'state' => 'PR',
		'postcode' => '80000-100',
		'country' => 'BR',
	);

	$orders[ $id ] = $order;

	return $order;
}

// ---------------------------------------------------------------------------
// Production values
// ---------------------------------------------------------------------------

echo "\nproduction values\n";

make_order( 1, 20, 'SEDEX' );
make_order( 2, 20, 'SEDEX', '(incl. tax)' );
make_order( 3, 0, 'Free shipping' );
make_order( 4, 0, '' );

$paid = wc_placeholders( 1 );

check(
	'{{ wc_shipping_method_total }} is the cost and method as plain text',
	'R$ 20,00 via SEDEX' === $paid['{{ wc_shipping_method_total }}']['replacement']['production']
);

check(
	'{{ wc_shipping_address }} keeps resolving to the same value, so saved workflows do not change',
	$paid['{{ wc_shipping_address }}']['replacement']['production'] === $paid['{{ wc_shipping_method_total }}']['replacement']['production']
);

check(
	'the legacy value is the plain text of get_shipping_to_display(), nothing else changed',
	$paid['{{ wc_shipping_address }}']['replacement']['production'] === trim( joinotify_format_plain_text( wc_get_order( 1 )->get_shipping_to_display() ) )
);

check(
	'no HTML tag and no undecoded entity reach the message',
	strip_tags( $paid['{{ wc_shipping_address }}']['replacement']['production'] ) === $paid['{{ wc_shipping_address }}']['replacement']['production']
		&& false === strpos( $paid['{{ wc_shipping_address }}']['replacement']['production'], '&' )
);

check(
	'no non-breaking space survives',
	false === strpos( $paid['{{ wc_shipping_method_total }}']['replacement']['production'], "\u{00A0}" )
);

$taxed = wc_placeholders( 2 );

check(
	'the tax label WooCommerce appends stays readable',
	'R$ 20,00 (incl. tax) via SEDEX' === $taxed['{{ wc_shipping_method_total }}']['replacement']['production']
);

$free = wc_placeholders( 3 );

check(
	'free shipping returns the method name alone',
	'Free shipping' === $free['{{ wc_shipping_method_total }}']['replacement']['production']
		&& 'Free shipping' === $free['{{ wc_shipping_address }}']['replacement']['production']
);

$none = wc_placeholders( 4 );

check(
	'an order without a shipping line returns WooCommerce\'s "Free!"',
	'Free!' === $none['{{ wc_shipping_method_total }}']['replacement']['production']
);

check(
	'{{ wc_shipping_full_address }} is still the token that carries the address',
	'Daisy Avenue, 450, Curitiba, PR, 80000-100, BR' === $paid['{{ wc_shipping_full_address }}']['replacement']['production']
);

// ---------------------------------------------------------------------------
// Builder sandbox and descriptions
// ---------------------------------------------------------------------------

echo "\nbuilder sandbox and descriptions\n";

$sandbox = wc_placeholders();

check(
	'the sandbox of {{ wc_shipping_address }} previews a cost and method, not a street address',
	'R$ 20,00 via Express shipping' === $sandbox['{{ wc_shipping_address }}']['replacement']['sandbox']
);

check(
	'both tokens preview the same sample',
	$sandbox['{{ wc_shipping_address }}']['replacement']['sandbox'] === $sandbox['{{ wc_shipping_method_total }}']['replacement']['sandbox']
);

check(
	'without an order the production values are empty',
	'' === $sandbox['{{ wc_shipping_address }}']['replacement']['production']
		&& '' === $sandbox['{{ wc_shipping_method_total }}']['replacement']['production']
);

$legacy_description = $sandbox['{{ wc_shipping_address }}']['description'];

check(
	'the legacy description says it is not the address',
	false !== strpos( $legacy_description, 'NOT the address' )
);

check(
	'the legacy description points to the address token and to the clearer name',
	false !== strpos( $legacy_description, '{{ wc_shipping_full_address }}' )
		&& false !== strpos( $legacy_description, '{{ wc_shipping_method_total }}' )
);

check(
	'both tokens are offered on the same triggers',
	$sandbox['{{ wc_shipping_address }}']['triggers'] === $sandbox['{{ wc_shipping_method_total }}']['triggers']
);

// ---------------------------------------------------------------------------

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions failed\n"
	: "OK: all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
