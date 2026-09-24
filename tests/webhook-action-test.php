<?php
/**
 * Standalone test harness for the pure parts of the "Send webhook" workflow action: the headers
 * typed one per line (Webhook_Action::parse_headers) and the signature a receiver checks
 * (Webhook_Action::signature).
 *
 * No WordPress bootstrap.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/webhook-action-test.php
 *
 * @since 2.5.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );

$failures = 0;
$assertions = 0;

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

require __DIR__ . '/../admin/src/Builder/Webhook_Action.php';

use MeuMouse\Joinotify\Builder\Webhook_Action;

echo "\nWebhook_Action::parse_headers\n";

$headers = Webhook_Action::parse_headers( "Authorization: Bearer abc:def\r\nX-Store:  loja \n\nnot a header\nBad Name: x\n: empty" );

check( 'reads one "Name: value" per line, whatever the line ending', array( 'Authorization' => 'Bearer abc:def', 'X-Store' => 'loja' ) === $headers );
check( 'a value keeps its own colons', 'Bearer abc:def' === $headers['Authorization'] );
check( 'nothing typed, no headers', array() === Webhook_Action::parse_headers( '' ) );

echo "\nWebhook_Action::signature\n";

$signed = Webhook_Action::signature( '{"a":1}', 'segredo', 1790000000 );

check( 'carries the timestamp it signed', '1790000000' === $signed['X-Joinotify-Timestamp'] );
check( 'signs "timestamp.body" with the secret, as Joinotify\'s own webhooks do', 'sha256=' . hash_hmac( 'sha256', '1790000000.{"a":1}', 'segredo' ) === $signed['X-Joinotify-Signature-256'] );
check( 'another body is another signature', $signed !== Webhook_Action::signature( '{"a":2}', 'segredo', 1790000000 ) );

echo "\n{$assertions} assertions, {$failures} failure(s).\n";
exit( $failures > 0 ? 1 : 0 );

}
