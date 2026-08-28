<?php
/**
 * Standalone test harness for the money/plain text formatters.
 *
 * Covers joinotify_format_plain_text() and joinotify_format_price() from
 * admin/src/Core/Functions.php. WooCommerce encodes currency symbols as HTML
 * entities (BRL is stored as "&#82;&#36;") and wraps prices in markup, so any
 * value reaching a message must be stripped and decoded first — otherwise the
 * raw entity is delivered literally to the recipient.
 *
 * No WordPress bootstrap is required: wc_price() and the currency helpers are
 * stubbed below to mirror WooCommerce's real output.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/price-format-test.php
 *
 * @since 2.3.0
 */

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

/**
 * Currency symbols exactly as WooCommerce stores them: HTML entities, not glyphs.
 */
function get_woocommerce_currency_symbol( $currency = '' ) {
	$symbols = array(
		'BRL' => '&#82;&#36;',
		'USD' => '&#36;',
		'EUR' => '&euro;',
	);

	$currency = '' !== $currency ? $currency : 'BRL';

	return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';
}

/**
 * Mirrors the markup wc_price() produces: nested spans, an encoded symbol and a
 * non-breaking space between the symbol and the amount.
 */
function wc_price( $price, $args = array() ) {
	$currency = isset( $args['currency'] ) && '' !== $args['currency'] ? $args['currency'] : 'BRL';
	$symbol = get_woocommerce_currency_symbol( $currency );
	$amount = number_format( (float) $price, 2, ',', '.' );

	return '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . $symbol . '</span>&nbsp;' . $amount . '</bdi></span>';
}

/**
 * Mirrors wp_strip_all_tags(): drops script/style bodies, then every tag.
 */
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( $text );

	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}

	return trim( $text );
}

// Functions.php only declares functions, so it can be pulled in directly.
require_once __DIR__ . '/../admin/src/Core/Functions.php';

// ---------------------------------------------------------------------------
// joinotify_format_plain_text()
// ---------------------------------------------------------------------------

echo "\njoinotify_format_plain_text()\n";

check(
	'decodes the numeric entities WooCommerce uses for the BRL symbol',
	joinotify_format_plain_text( '&#82;&#36;68,70' ) === 'R$68,70'
);

check(
	'decodes named entities',
	joinotify_format_plain_text( '&euro;10,00' ) === "\u{20AC}10,00"
);

check(
	'strips the price markup wrapper',
	joinotify_format_plain_text( '<span class="amount"><bdi>68,70</bdi></span>' ) === '68,70'
);

check(
	'replaces non-breaking spaces with regular spaces',
	joinotify_format_plain_text( "R$\u{00A0}68,70" ) === 'R$ 68,70'
);

check(
	'replaces narrow non-breaking spaces with regular spaces',
	joinotify_format_plain_text( "68,70\u{202F}EUR" ) === '68,70 EUR'
);

check(
	'leaves plain text untouched',
	joinotify_format_plain_text( 'Order #4631 confirmed' ) === 'Order #4631 confirmed'
);

check(
	'null becomes an empty string',
	joinotify_format_plain_text( null ) === ''
);

// ---------------------------------------------------------------------------
// joinotify_format_price()
// ---------------------------------------------------------------------------

echo "\njoinotify_format_price()\n";

check(
	'formats a float with the store currency, decoded and tag free',
	joinotify_format_price( 68.7 ) === 'R$ 68,70'
);

check(
	'formats the numeric string WC_Order::get_total() returns',
	joinotify_format_price( '68.70' ) === 'R$ 68,70'
);

check(
	'honours an explicit currency code',
	joinotify_format_price( 68.7, 'USD' ) === '$ 68,70'
);

check(
	'an empty currency code falls back to the store currency',
	joinotify_format_price( 68.7, '' ) === 'R$ 68,70'
);

check(
	'zero is still formatted as a price',
	joinotify_format_price( 0 ) === 'R$ 0,00'
);

check(
	'output carries no HTML tag',
	strip_tags( joinotify_format_price( 1234.5 ) ) === joinotify_format_price( 1234.5 )
);

check(
	'output carries no undecoded entity',
	strpos( joinotify_format_price( 1234.5 ), '&#' ) === false
);

// ---------------------------------------------------------------------------

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions failed\n"
	: "OK: all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
