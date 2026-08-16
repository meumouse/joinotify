<?php
/**
 * Standalone test harness for the write-only settings rule.
 *
 * The Joinotify API key is blanked before it reaches the browser, so the
 * settings form always submits it empty. Repository::preserve_write_only() is
 * what stops that empty string from being written back over a key the setup
 * wizard (or the WhatsApp modal) just stored. Getting this wrong silently
 * disconnects the site on the first press of Save, which is exactly the kind of
 * failure nobody attributes to the Save button.
 *
 * The method takes its inputs as arguments and calls nothing from WordPress, so
 * no bootstrap is required.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/write-only-settings-test.php
 *
 * @since 2.4.0
 */

// The class file guards with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../admin/src/Admin/Settings/Repository.php';

use MeuMouse\Joinotify\Admin\Settings\Repository;

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

$keys = array( 'whatsapp_cloud_api_token' );

echo "\npreserve_write_only\n";

// The case that matters: the wizard stored a key, the settings form submits the
// blanked field, and Save must not treat that as "delete my key".
$result = Repository::preserve_write_only(
	array( 'whatsapp_cloud_api_token' => '', 'other' => 'kept' ),
	array( 'whatsapp_cloud_api_token' => 'sk_live_wizard123', 'other' => 'previous' ),
	$keys
);

check( 'a blank submission keeps the stored key', 'sk_live_wizard123' === $result['whatsapp_cloud_api_token'] );
check( 'other settings are untouched by the guard', 'kept' === $result['other'] );

// Whitespace is not a value.
$result = Repository::preserve_write_only(
	array( 'whatsapp_cloud_api_token' => "   \n" ),
	array( 'whatsapp_cloud_api_token' => 'sk_live_wizard123' ),
	$keys
);

check( 'a whitespace-only submission also keeps the stored key', 'sk_live_wizard123' === $result['whatsapp_cloud_api_token'] );

// A real new key must win: this is how rotating a key works.
$result = Repository::preserve_write_only(
	array( 'whatsapp_cloud_api_token' => 'sk_live_rotated456' ),
	array( 'whatsapp_cloud_api_token' => 'sk_live_wizard123' ),
	$keys
);

check( 'a submitted key replaces the stored one', 'sk_live_rotated456' === $result['whatsapp_cloud_api_token'] );

// Nothing stored yet: an empty submission stays empty rather than inventing one.
$result = Repository::preserve_write_only(
	array( 'whatsapp_cloud_api_token' => '' ),
	array( 'whatsapp_cloud_api_token' => '' ),
	$keys
);

check( 'an empty store plus an empty submission stays empty', '' === $result['whatsapp_cloud_api_token'] );

// Absent keys must not be conjured into the payload.
$result = Repository::preserve_write_only( array( 'other' => 'x' ), array( 'other' => 'x' ), $keys );

check( 'a key absent from both sides is not added', ! array_key_exists( 'whatsapp_cloud_api_token', $result ) );

// A first-time connection through the modal writes the option directly, so the
// sanitized payload may carry nothing while the store already has the key.
$result = Repository::preserve_write_only( array(), array( 'whatsapp_cloud_api_token' => 'sk_live_modal789' ), $keys );

check( 'a payload missing the key entirely still keeps the stored one', 'sk_live_modal789' === $result['whatsapp_cloud_api_token'] );

// Defensive shapes.
check( 'a non-array key list is tolerated', is_array( Repository::preserve_write_only( array(), array(), 'oops' ) ) );

$result = Repository::preserve_write_only(
	array( 'whatsapp_cloud_api_token' => '' ),
	array( 'whatsapp_cloud_api_token' => 'sk_live_wizard123' ),
	array( 'whatsapp_cloud_api_token', 'whatsapp_cloud_api_token' )
);

check( 'a duplicated key in the list is harmless', 'sk_live_wizard123' === $result['whatsapp_cloud_api_token'] );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
