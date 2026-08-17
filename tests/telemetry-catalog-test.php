<?php
/**
 * Standalone test harness for the local mirror of the service catalog.
 *
 * The service already drops what it does not recognize, so on paper this class is
 * redundant. The point of it — and of this harness — is that "the service discarded
 * something" should never be routine: if the mirror is right, `discarded` in a response
 * is always zero, and any other number means the two catalogs drifted apart and is worth
 * a warning in the log. These assertions are what keeps that true.
 *
 * Keep in step with `src/telemetry/registry.ts` on the service.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/telemetry-catalog-test.php
 *
 * @since 2.5.0
 */

// The class files guard with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../admin/src/Telemetry/Normalizer.php';
require __DIR__ . '/../admin/src/Telemetry/Event_Catalog.php';

use MeuMouse\Joinotify\Telemetry\Event_Catalog;

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

echo "== the catalog itself ==\n";

$events = Event_Catalog::events();

check( 'every event is named domain.verb', (function() use ( $events ) {
	foreach ( array_keys( $events ) as $name ) {
		if ( ! preg_match( '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $name ) ) {
			return false;
		}
	}

	return true;
})() );

check( 'no property is named after personal data', (function() use ( $events ) {
	$forbidden = array( 'phone', 'recipient', 'to', 'msisdn', 'email', 'mail', 'name', 'address', 'body', 'message', 'text', 'content', 'token', 'secret', 'password' );

	foreach ( $events as $event ) {
		foreach ( array_keys( $event['props'] ) as $prop ) {
			// Compared word by word, not as substrings: 'context' contains 'text' and
			// 'httpStatus' contains 'status', and neither is anybody's data. Substring
			// matching here would produce false alarms until somebody switched the check
			// off, which is how a guard dies.
			$words = preg_split( '/[^a-z0-9]+/', strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1 $2', $prop ) ) );

			if ( array_intersect( $words, $forbidden ) ) {
				return false;
			}
		}
	}

	return true;
})() );

check( 'every property uses a closed-alphabet kind', (function() use ( $events ) {
	$allowed = array( 'enum', 'number', 'boolean', 'version', 'slug', 'slug_list' );

	foreach ( $events as $event ) {
		foreach ( $event['props'] as $spec ) {
			if ( ! in_array( $spec['kind'], $allowed, true ) ) {
				return false;
			}
		}
	}

	return true;
})() );

check( 'the lifecycle milestones are protected from trimming', Event_Catalog::is_protected('plugin.updated') && Event_Catalog::is_protected('onboarding.sender_selected') );
check( 'the noisy families are coalesced', Event_Catalog::coalesces('feature.used') && Event_Catalog::coalesces('message.sent') );
check( 'a once-only event is not coalesced', ! Event_Catalog::coalesces('plugin.activated') );

echo "\n== has ==\n";

check( 'a catalog name is known', Event_Catalog::has('message.failed') );
check( 'an invented name is not', ! Event_Catalog::has('inventado.pelo.plugin') );

echo "\n== filter_props: nothing outside the allow-list survives ==\n";

$props = Event_Catalog::filter_props( 'message.sent', array(
	'type' => 'template',
	'transport' => 'cloud',
	'scheduled' => true,
	// None of these exist in the catalog, and none may ever reach the buffer.
	'to' => '5511999998888',
	'body' => 'Ola Ana, seu pedido saiu',
	'recipientName' => 'Ana',
) );

check( 'only the declared keys remain', array( 'transport' => 'cloud', 'type' => 'template', 'scheduled' => true ) == $props );

check( 'a channel outside the enum is dropped', ! array_key_exists( 'transport', Event_Catalog::filter_props( 'message.sent', array( 'transport' => 'telegram' ) ) ) );
check( 'an HTTP status below the range is dropped', ! array_key_exists( 'httpStatus', Event_Catalog::filter_props( 'message.failed', array( 'httpStatus' => 99 ) ) ) );
check( 'an HTTP status above the range is dropped', ! array_key_exists( 'httpStatus', Event_Catalog::filter_props( 'message.failed', array( 'httpStatus' => 700 ) ) ) );
check( 'a zero attempt is dropped', ! array_key_exists( 'attempt', Event_Catalog::filter_props( 'queue.retried', array( 'attempt' => 0 ) ) ) );
check( 'an attempt past the ceiling is dropped', ! array_key_exists( 'attempt', Event_Catalog::filter_props( 'queue.retried', array( 'attempt' => 101 ) ) ) );
check( 'a non-boolean does not become a boolean', ! array_key_exists( 'scheduled', Event_Catalog::filter_props( 'message.sent', array( 'scheduled' => 'yes' ) ) ) );
check( 'an unknown event yields nothing', array() === Event_Catalog::filter_props( 'inventado.pelo.plugin', array( 'x' => 1 ) ) );
check( 'a phone number in a declared slug is dropped', ! array_key_exists( 'code', Event_Catalog::filter_props( 'plugin.error', array( 'code' => '5511999998888' ) ) ) );

echo "\n== filter_environment ==\n";

$environment = Event_Catalog::filter_environment( array(
	'multisite' => false,
	'wooActive' => true,
	'wooVersion' => '9.1.2',
	'activeIntegrations' => array( 'wpforms', 'woocommerce' ),
	'workflowsPublished' => 12,
	'workflowsDraft' => 3,
	'adminEmail' => 'dono@loja.com.br',
	'siteName' => 'Loja da Ana',
) );

check( 'the inventory survives', 'woocommerce,wpforms' === $environment['activeIntegrations'] && 12 === $environment['workflowsPublished'] );
check( 'the admin e-mail has nowhere to go', ! array_key_exists( 'adminEmail', $environment ) );
check( 'the site name has nowhere to go', ! array_key_exists( 'siteName', $environment ) );

echo "\n== samples shown on the consent screen ==\n";

$samples = Event_Catalog::samples();

check( 'every sample is a real catalog event with valid properties', (function() use ( $samples ) {
	foreach ( $samples as $sample ) {
		if ( ! Event_Catalog::has( $sample['name'] ) ) {
			return false;
		}

		// A sample that would itself be stripped would be showing the owner something the
		// plugin never actually sends.
		if ( Event_Catalog::filter_props( $sample['name'], $sample['props'] ) != $sample['props'] ) {
			return false;
		}
	}

	return count( $samples ) > 0;
})() );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );
