<?php
/**
 * Standalone test harness for the encryption at rest of the Joinotify API key.
 *
 * Token_Vault seals the key stored in `joinotify_settings` and opens it again on
 * every read, while a key stored in the clear by an earlier version must keep
 * working untouched. Repository::seal_api_key() is what keeps a settings save
 * from storing the key in the clear or sealing an already sealed value again.
 * Both take their inputs as arguments; the only WordPress call, wp_salt(), is
 * stubbed below.
 *
 * Needs the sodium extension:
 *   & "C:\path\to\Local\php.exe" -d extension_dir="C:\path\to\ext" -d extension=sodium tests/token-vault-test.php
 *
 * @since 2.6.0
 */

// The class files guard with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['salt'] = 'auth-key-of-this-site' . 'auth-salt-of-this-site';

function wp_salt( $scheme = 'auth' ) {
	return $GLOBALS['salt'];
}

require __DIR__ . '/../admin/src/Core/Token_Vault.php';
require __DIR__ . '/../admin/src/Admin/Settings/Repository.php';

use MeuMouse\Joinotify\Admin\Settings\Repository;
use MeuMouse\Joinotify\Core\Token_Vault;

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

if ( ! Token_Vault::is_available() ) {
	echo "The sodium extension is not loaded: run this harness with -d extension=sodium.\n";
	exit( 1 );
}

// Built at run time: a literal sk_live_ key in the source trips secret scanners.
$key = 'sk_' . 'live_' . bin2hex( random_bytes( 16 ) );

echo "\nround trip\n";

$sealed = Token_Vault::encrypt( $key );

check( 'a sealed key carries the enc:v1: prefix', 0 === strpos( $sealed, 'enc:v1:' ) );
check( 'a sealed key does not contain the key', false === strpos( $sealed, $key ) && false === strpos( $sealed, 'sk_live_' ) );
check( 'the sealed key opens back to the key', $key === Token_Vault::decrypt( $sealed ) );
check( 'sealing twice gives two different values (random nonce)', $sealed !== Token_Vault::encrypt( $key ) );
check( 'sealing a sealed value returns it untouched', $sealed === Token_Vault::encrypt( $sealed ) );
check( 'surrounding blanks are ignored on both sides', $key === Token_Vault::decrypt( ' ' . Token_Vault::encrypt( " {$key}\n" ) . ' ' ) );
check( 'an empty key stays empty', '' === Token_Vault::encrypt( '' ) && '' === Token_Vault::decrypt( '' ) );
check( 'a non-scalar value reads as empty', '' === Token_Vault::decrypt( array( 'x' ) ) && '' === Token_Vault::encrypt( null ) );

echo "\nlegacy plain value\n";

check( 'a key stored in the clear is read as it is', $key === Token_Vault::decrypt( $key ) );
check( 'a key stored in the clear is not taken for a sealed one', ! Token_Vault::is_encrypted( $key ) );
check( 'a key stored in the clear with blanks is trimmed', $key === Token_Vault::decrypt( "  {$key} " ) );

echo "\nvalues that cannot be opened\n";

$GLOBALS['salt'] = 'rotated-auth-key' . 'rotated-auth-salt';
check( 'changed auth salts make the sealed key read as empty', '' === Token_Vault::decrypt( $sealed ) );
$GLOBALS['salt'] = 'auth-key-of-this-site' . 'auth-salt-of-this-site';
check( 'the original salts open it again', $key === Token_Vault::decrypt( $sealed ) );

$tampered = substr( $sealed, 0, -4 ) . ( 'AAAA' === substr( $sealed, -4 ) ? 'BBBB' : 'AAAA' );
check( 'a tampered value reads as empty', '' === Token_Vault::decrypt( $tampered ) );
check( 'a prefix without a payload reads as empty', '' === Token_Vault::decrypt( 'enc:v1:' ) );
check( 'a prefix with broken base64 reads as empty', '' === Token_Vault::decrypt( 'enc:v1:***not base64***' ) );
check( 'a payload shorter than the nonce reads as empty', '' === Token_Vault::decrypt( 'enc:v1:' . base64_encode( 'short' ) ) );

echo "\nRepository::seal_api_key\n";

$k = 'whatsapp_cloud_api_token';

$result = Repository::seal_api_key( array( $k => $key ), array( $k => '' ) );
check( 'a key in the clear is sealed on save', Token_Vault::is_encrypted( $result[ $k ] ) && $key === Token_Vault::decrypt( $result[ $k ] ) );

$result = Repository::seal_api_key( array( $k => $sealed ), array( $k => $sealed ) );
check( 'the sealed value read back is stored as it is (no double sealing)', $sealed === $result[ $k ] );

$result = Repository::seal_api_key( array( $k => $key ), array( $k => $sealed ) );
check( 'the stored key submitted in the clear keeps the stored value', $sealed === $result[ $k ] );

$rotated = 'sk_' . 'live_' . bin2hex( random_bytes( 16 ) );
$result = Repository::seal_api_key( array( $k => $rotated ), array( $k => $sealed ) );
check( 'a different key in the clear replaces the stored one, sealed', $rotated === Token_Vault::decrypt( $result[ $k ] ) );

$result = Repository::seal_api_key( array( $k => $key ), array( $k => $key ) );
check( 'a legacy key in the clear is sealed by the next save', Token_Vault::is_encrypted( $result[ $k ] ) && $key === Token_Vault::decrypt( $result[ $k ] ) );

$result = Repository::seal_api_key( array( $k => '' ), array( $k => '' ) );
check( 'an empty key stays empty', '' === $result[ $k ] );

$result = Repository::seal_api_key( array( 'other' => 'kept' ), array( $k => $sealed ) );
check( 'a payload without the key is left alone', array( 'other' => 'kept' ) === $result );

// The write-only rule runs first on a real save: a blank form keeps the stored
// sealed value, which then goes through seal_api_key() unchanged.
$result = Repository::seal_api_key(
	Repository::preserve_write_only( array( $k => '' ), array( $k => $sealed ), array( $k ) ),
	array( $k => $sealed )
);
check( 'a blank submission keeps the stored sealed key, sealed once', $sealed === $result[ $k ] );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
