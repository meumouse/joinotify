<?php
/**
 * Standalone test harness for the full address format fields (2.8.0).
 *
 * The two "full address format" settings of the WooCommerce integration are
 * edited in the rich text editor with a variable list. This harness checks
 * that the list offers exactly the keys get_address_data() resolves (the core
 * address fields, plus every checkout field of the section without its
 * prefix), and that a format built from those chips still formats the way the
 * plain text one did.
 *
 * No WordPress bootstrap is required: the WP functions touched are stubbed.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/address-format-richtext-test.php
 *
 * @since 2.8.0
 */

namespace MeuMouse\Joinotify\Admin {

	class Admin {
		public static function get_setting( $key ) { return ''; }
	}
}

namespace MeuMouse\Joinotify\Builder {

	class Triggers {
		public static function get_trigger_names( $integration ) { return array(); }
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

// Checkout fields WooCommerce reports, grouped by section ('billing', 'shipping').
$GLOBALS['joinotify_test_checkout'] = array();

function __( $text, $domain = 'default' ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function apply_filters( $hook, $value ) { return $value; }

// Just enough of WooCommerce for export_checkout_fields(): a checkout object,
// and the session, customer and cart already in place so nothing is built.
class WooCommerce {}

function WC() {
	static $wc = null;

	if ( null === $wc ) {
		$wc = new stdClass();
		$wc->session = true;
		$wc->customer = true;
		$wc->cart = true;
		$wc->checkout = new class {
			public function get_checkout_fields() {
				return $GLOBALS['joinotify_test_checkout'];
			}
		};
	}

	return $wc;
}

require __DIR__ . '/../admin/src/Integrations/Woocommerce.php';

// Aliased: the WooCommerce stub above already holds the short name.
use MeuMouse\Joinotify\Integrations\Woocommerce as Woo_Integration;

/**
 * Tokens of a placeholder list, without the braces.
 */
function tokens( array $placeholders ) {
	return array_map( function ( $item ) {
		return trim( str_replace( array( '{{', '}}' ), '', $item['placeholder'] ) );
	}, $placeholders );
}

echo "== variables without a checkout ==\n";
$billing = Woo_Integration::get_address_placeholders( 'billing' );
$shipping = Woo_Integration::get_address_placeholders( 'shipping' );

check( 'billing offers the core address fields', array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone', 'email' ) === tokens( $billing ) );
check( 'shipping has no e-mail', ! in_array( 'email', tokens( $shipping ), true ) );
check( 'the empty checkout groups are not offered as variables', ! array_intersect( array( 'billing', 'shipping' ), tokens( $billing ) ) );
check( 'every entry carries a description and a sample', ! array_filter( $billing, function ( $item ) {
	return '' === $item['description'] || ! isset( $item['replacement']['sandbox'] );
} ) );
check( 'an unknown type falls back to billing', tokens( Woo_Integration::get_address_placeholders( 'nope' ) ) === tokens( $billing ) );

echo "\n== variables with checkout fields ==\n";
$GLOBALS['joinotify_test_checkout'] = array(
	'billing' => array(
		'billing_first_name' => array( 'label' => 'Nome' ),
		'billing_number' => array( 'label' => 'Número' ),
		'billing_neighborhood' => array( 'label' => '<b>Bairro</b>' ),
		'billing_bad key' => array( 'label' => 'Broken' ),
		'billing_cpf' => array(),
	),
	'shipping' => array(
		'shipping_number' => array( 'label' => 'Número' ),
	),
);

$billing = Woo_Integration::get_address_placeholders( 'billing' );
$by_token = array_combine( tokens( $billing ), $billing );

check( 'a checkout field is offered without its prefix', isset( $by_token['number'] ) && '{{ number }}' === $by_token['number']['placeholder'] );
check( 'its label becomes the description, without markup', isset( $by_token['neighborhood'] ) && 'Bairro' === $by_token['neighborhood']['description'] );
check( 'a core field is not offered twice', 1 === count( array_keys( tokens( $billing ), 'first_name', true ) ) );
check( 'a key the format cannot resolve is skipped', ! isset( $by_token['bad key'] ) );
check( 'an empty field definition is skipped', ! isset( $by_token['cpf'] ) );
check( 'shipping strips its own prefix', in_array( 'number', tokens( Woo_Integration::get_address_placeholders( 'shipping' ) ), true ) );

echo "\n== a format built from the chips ==\n";
$format = new ReflectionMethod( Woo_Integration::class, 'format_address_template' );
$format->setAccessible( true );

$address = array(
	'address_1' => 'Rua das Flores',
	'number' => '123',
	'city' => 'Curitiba',
	'state' => 'PR',
	'postcode' => '80000-000',
);

// What the editor stores: the plain text between chips, tokens as written.
$stored = '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})';

check( 'the default format resolves every token', 'Rua das Flores, 123, Curitiba - PR (CEP: 80000-000)' === $format->invoke( null, $stored, $address, null, 'billing' ) );
check( 'a token typed without spaces resolves the same', 'Curitiba/PR' === $format->invoke( null, '{{city}}/{{ state }}', $address, null, 'billing' ) );
check( 'a token with no value resolves to empty', 'Rua das Flores, ' === $format->invoke( null, '{{ address_1 }}, {{ address_2 }}', $address, null, 'billing' ) );

$all_resolvable = true;

foreach ( $billing as $item ) {
	if ( ! preg_match( '/^\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}$/', $item['placeholder'] ) ) {
		$all_resolvable = false;
	}
}

check( 'every offered token has the shape the format resolves', $all_resolvable );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
}
