<?php
/**
 * Standalone test harness for telemetry value normalization.
 *
 * This is the file that turns two promises into regressions.
 *
 * The first is about cost: the service turns some of these values into counter names, so
 * a value drawn from an unbounded set creates a permanent row per distinct value. Every
 * assertion below that ends in '_other' is checking that a bucket stayed closed.
 *
 * The second is about privacy: whatever `is_sensitive()` misses leaves the site. The
 * service redacts it too, but by then it has already been sent, and that is the part that
 * cannot be undone.
 *
 * Normalizer has no WordPress calls, so no bootstrap is required.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/telemetry-normalizer-test.php
 *
 * @since 2.3.0
 */

// The class file guards with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../admin/src/Telemetry/Normalizer.php';

use MeuMouse\Joinotify\Telemetry\Normalizer;

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

echo "== error_code: closed buckets ==\n";

check( 'the most common production error keeps its own bucket', 'window_closed_requires_template' === Normalizer::error_code('window_closed_requires_template') );
check( 'a known template code is preserved', 'template_error_132001' === Normalizer::error_code('template_error_132001') );
check( 'an unknown template code collapses', 'template_error_other' === Normalizer::error_code('template_error_999999') );
check( 'a real HTTP status is preserved', 'http_429' === Normalizer::error_code('http_429') );
check( 'an HTTP status we never produce collapses', 'http_other' === Normalizer::error_code('http_418') );
check( 'a PHP errno becomes a level name', 'php_warning' === Normalizer::error_code('php_2') );
check( 'a fatal keeps its name', 'php_fatal' === Normalizer::error_code('php_fatal') );
check( 'an unknown errno collapses', 'php_other' === Normalizer::error_code('php_31337') );
check( 'a known Graph type is normalized', 'meta_oauth' === Normalizer::error_code('OAuthException') );
check( 'an unknown Graph type is still recognizably Meta', 'meta_other' === Normalizer::error_code('SomethingNewException') );
check( 'an internal code passes through', 'channel_unconfigured' === Normalizer::error_code('channel_unconfigured') );
check( 'anything else becomes other', 'other' === Normalizer::error_code('a message someone wrote by hand') );
check( 'an empty code becomes other', 'other' === Normalizer::error_code('') );
check( 'a non-string becomes other', 'other' === Normalizer::error_code( array( 'x' ) ) );

echo "\n== is_sensitive: what must never leave ==\n";

check( 'a phone number', Normalizer::is_sensitive('5511999998888') );
check( 'a phone number inside a longer value', Normalizer::is_sensitive('order_5511999998888_paid') );
check( 'an e-mail', Normalizer::is_sensitive('cliente@exemplo.com.br') );
check( 'a Joinotify key', Normalizer::is_sensitive('sk_live_abcdefghijkl') );
check( 'a bearer header', Normalizer::is_sensitive('Bearer abc123') );
check( 'a Meta token', Normalizer::is_sensitive('EAAGm0PX4ZCpsBA') );
check( 'a JWT', Normalizer::is_sensitive('aaaaaaaaaa.bbbbbbbbbb.cccccccccc') );
check( 'a real error code is not sensitive', ! Normalizer::is_sensitive('window_closed_requires_template') );
check( 'a short number is not a phone number', ! Normalizer::is_sensitive('http_429') );

echo "\n== slug ==\n";

check( 'a plain slug survives', 'cart_abandonment' === Normalizer::slug('cart_abandonment') );
check( 'case is folded so two sites count as one feature', 'cart_abandonment' === Normalizer::slug('Cart_Abandonment') );
check( 'a sensitive value is dropped, not redacted', null === Normalizer::slug('5511999998888') );
check( 'a sentence is not a slug', null === Normalizer::slug('sent to the customer') );
check( 'an accented word is not a slug', null === Normalizer::slug('não_enviado') );
check( 'a leading dash is rejected', null === Normalizer::slug('-leading') );
check( 'an empty value is dropped', null === Normalizer::slug('') );
check( 'an over-long slug is cut to the limit', 60 === strlen( Normalizer::slug( str_repeat( 'a', 200 ) ) ) );

echo "\n== version ==\n";

check( 'a semver survives', '2.5.0' === Normalizer::version('2.5.0') );
check( 'a WordPress pre-release survives', '6.7.1-alpha-59000-src' === Normalizer::version('6.7.1-alpha-59000-src') );
// A Debian-style suffix fits, so it is accepted. Installation still does not send it —
// it builds the version from PHP_MAJOR/MINOR/RELEASE, because the suffix says nothing the
// three numbers do not and only costs length.
check( 'a distribution suffix still fits and is kept', '8.2.20-1+ubuntu22.04.1+deb.sury.org' === Normalizer::version('8.2.20-1+ubuntu22.04.1+deb.sury.org') );
// Truncating would invent a version that does not exist; saying nothing is honest.
check( 'anything past the limit is dropped rather than cut', null === Normalizer::version( '8.2.20-' . str_repeat( 'x', 40 ) ) );
check( 'a version must start with a digit', null === Normalizer::version('v2.5.0') );
check( 'an empty version is dropped', null === Normalizer::version('') );

echo "\n== number, enum ==\n";

check( 'an HTTP status inside the range survives', 429 === Normalizer::number( 429, 100, 599 ) );
check( 'a numeric string is accepted', 429 === Normalizer::number( '429', 100, 599 ) );
check( 'below the range is dropped', null === Normalizer::number( 99, 100, 599 ) );
check( 'above the range is dropped', null === Normalizer::number( 700, 100, 599 ) );
check( 'a transport-failure zero is dropped, not sent as zero', null === Normalizer::number( 0, 100, 599 ) );
check( 'an allowed enum survives', 'cloud' === Normalizer::enum( 'cloud', array( 'cloud', 'evolution' ) ) );
check( 'a channel outside the enum is dropped', null === Normalizer::enum( 'telegram', array( 'cloud', 'evolution' ) ) );

echo "\n== slug_list ==\n";

check( 'sorted and de-duplicated so two sites can be grouped', 'woocommerce,wpforms' === Normalizer::slug_list( array( 'wpforms', 'woocommerce', 'wpforms' ), 30 ) );
check( 'an already joined list is accepted', 'woocommerce,wpforms' === Normalizer::slug_list( 'wpforms,woocommerce', 30 ) );
check( 'one odd third-party slug does not cost the inventory', 'woocommerce,wpforms' === Normalizer::slug_list( array( 'woocommerce', 'Plugin Estranho!', 'wpforms' ), 30 ) );
check( 'the list is cut at the ceiling', 'a,b,c' === Normalizer::slug_list( array( 'e', 'd', 'c', 'b', 'a' ), 3 ) );
check( 'a list with nothing valid is dropped', null === Normalizer::slug_list( array( 'Plugin Estranho!' ), 30 ) );

echo "\n== fingerprint ==\n";

$a = Normalizer::fingerprint( 'other', 'api', '/var/www/plugin/admin/src/Api/Cloud_Client.php', 412 );
$b = Normalizer::fingerprint( 'other', 'api', '/home/user/site/admin/src/Api/Cloud_Client.php', 412 );
$c = Normalizer::fingerprint( 'other', 'api', '/var/www/plugin/admin/src/Api/Cloud_Client.php', 413 );

check( 'stable and short', 10 === strlen( $a ) );
// Only the basename is hashed, so two hosts with different install paths report the same
// call site — otherwise the group would fragment per server.
check( 'independent of where the site is installed', $a === $b );
check( 'a different line is a different call site', $a !== $c );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );
