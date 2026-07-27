<?php
/**
 * Standalone test harness for the builder attachment resolver.
 *
 * Exercises Builder\Attachments and the two call sites that decide how a resolved
 * file is delivered. No WordPress bootstrap is required: the WP functions the
 * resolver touches are stubbed below, and the collaborators it reaches for
 * (Logger, Placeholders, Woocommerce) are replaced by fakes so the assertions
 * stay on the resolution logic itself.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/attachments-test.php
 *
 * @since 2.1.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1024 * 1024 );

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
// WordPress stubs
// ---------------------------------------------------------------------------

function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9 _.\-]/', '', (string) $name ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function is_wp_error( $thing ) { return false; }
function get_attached_file( $id ) { global $attached_files; return $attached_files[ $id ] ?? false; }

/**
 * Mirrors the character stripping of the real esc_url_raw, which is the reason a
 * placeholder cannot be escaped before it is resolved.
 */
function esc_url_raw( $url ) {
	return preg_replace( '|[^a-z0-9\-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', (string) $url );
}

function wp_check_filetype( $filename ) {
	$map = array(
		'pdf' => 'application/pdf',
		'jpg' => 'image/jpeg',
		'png' => 'image/png',
		'mp4' => 'video/mp4',
		'mp3' => 'audio/mpeg',
		'zip' => 'application/zip',
	);

	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

	return array( 'ext' => $ext, 'type' => $map[ $ext ] ?? false );
}

function wp_remote_get( $url, $args = array() ) { return array( 'code' => 200, 'body' => 'remote-bytes' ); }
function wp_remote_retrieve_response_code( $response ) { return $response['code'] ?? 0; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }

/**
 * Minimal order double. The resolver only ever hands it to Woocommerce::get_downloadable_items(),
 * which is faked, so the object itself needs no behaviour.
 */
class WC_Order {}

function wc_get_order( $id ) { return $id ? new WC_Order() : false; }

/**
 * Stands in for the WooCommerce path parser the resolver delegates to. Mirrors the
 * real contract: anything that survives as an http(s) URL is remote, everything
 * else is treated as a path on disk.
 */
class WC_Download_Handler {
	public static function parse_file_path( $file_path ) {
		$remote = (bool) preg_match( '#^https?://#i', $file_path );

		return array( 'remote_file' => $remote, 'file_path' => $file_path );
	}
}

}

// ---------------------------------------------------------------------------
// Collaborator fakes (defined before the class under test loads)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static $entries = array();
		public static function register_log( $message, $level = 'INFO' ) { self::$entries[] = array( $level, $message ); }
	}
}

namespace MeuMouse\Joinotify\Builder {
	class Placeholders {
		public static $map = array();
		public static function replace_placeholders( $message, $payload = array(), $mode = 'production' ) {
			return strtr( (string) $message, self::$map );
		}
	}
}

namespace MeuMouse\Joinotify\Integrations {
	class Woocommerce {
		public static $items = array();
		public static function get_downloadable_items( $order ) { return self::$items; }
	}
}

namespace {

require __DIR__ . '/../admin/src/Builder/Attachments.php';

use MeuMouse\Joinotify\Builder\Attachments;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Integrations\Woocommerce;
use MeuMouse\Joinotify\Core\Logger;

/**
 * Call a protected/private static method under test.
 */
function call_static( $class, $method, array $args = array() ) {
	$ref = new ReflectionMethod( $class, $method );
	$ref->setAccessible( true );

	return $ref->invokeArgs( null, $args );
}

// a real file on disk, so the local-file branch is exercised for real
$fixture_dir = sys_get_temp_dir() . '/joinotify-attachments-test';
@mkdir( $fixture_dir, 0777, true );
$local_pdf = $fixture_dir . '/guide.pdf';
file_put_contents( $local_pdf, str_repeat( 'x', 1024 ) );

$GLOBALS['attached_files'] = array( 42 => $local_pdf );

echo "== normalize_file_name ==\n";
check( 'keeps a name that already has an extension',
	call_static( Attachments::class, 'normalize_file_name', array( 'guide.pdf', '/x/guide.pdf' ) ) === 'guide.pdf' );
check( 'borrows the extension from the path when the label has none',
	call_static( Attachments::class, 'normalize_file_name', array( 'Complete guide', '/x/guide.pdf' ) ) === 'Complete guide.pdf' );
check( 'falls back to the path basename when the label is empty',
	call_static( Attachments::class, 'normalize_file_name', array( '', '/x/guide.pdf' ) ) === 'guide.pdf' );
check( 'never returns an empty name',
	call_static( Attachments::class, 'normalize_file_name', array( '', '' ) ) === 'attachment' );
// The name only ever travels as a display filename in an API body, never to open a file,
// so the invariant that matters is that no path separator survives into it.
$traversal = call_static( Attachments::class, 'normalize_file_name', array( '../../etc/passwd', '/x/f.pdf' ) );
check( 'strips path separators out of the name',
	false === strpos( $traversal, '/' ) && false === strpos( $traversal, '\\' ) );

echo "\n== is_download_permission_url ==\n";
check( 'detects a WooCommerce permission link',
	true === call_static( Attachments::class, 'is_download_permission_url', array( 'https://shop.test/?download_file=12&order=wc_order_x&key=abc&email=a%40b.c' ) ) );
check( 'a plain file URL is not a permission link',
	false === call_static( Attachments::class, 'is_download_permission_url', array( 'https://cdn.test/guide.pdf' ) ) );
check( 'download_file without key is not a permission link',
	false === call_static( Attachments::class, 'is_download_permission_url', array( 'https://shop.test/?download_file=12' ) ) );
check( 'a URL with no query string is not a permission link',
	false === call_static( Attachments::class, 'is_download_permission_url', array( 'https://shop.test/downloads' ) ) );

echo "\n== resolve: media source ==\n";
$resolved = Attachments::resolve( array( array( 'source' => 'media', 'attachment_id' => 42 ) ) );
check( 'resolves the attachment id to its file on disk', count( $resolved ) === 1 && $resolved[0]['path'] === $local_pdf );
check( 'reports the real byte size', $resolved[0]['size'] === 1024 );
check( 'marks the file as local', false === $resolved[0]['remote'] );
check( 'detects the mime type', $resolved[0]['mime'] === 'application/pdf' );

$missing = Attachments::resolve( array( array( 'source' => 'media', 'attachment_id' => 999 ) ) );
check( 'an attachment id with no file resolves to nothing', array() === $missing );

echo "\n== resolve: url source ==\n";
$remote = Attachments::resolve( array( array( 'source' => 'url', 'url' => 'https://cdn.test/manual.pdf' ) ) );
check( 'a remote URL stays remote', count( $remote ) === 1 && true === $remote[0]['remote'] );
check( 'derives the file name from the URL path', $remote[0]['name'] === 'manual.pdf' );
check( 'carries no local path', '' === $remote[0]['path'] );

Placeholders::$map = array( '{{ file_url }}' => 'https://cdn.test/from-token.pdf' );
$from_token = Attachments::resolve( array( array( 'source' => 'url', 'url' => '{{ file_url }}' ) ) );
check( 'resolves a placeholder into the URL', count( $from_token ) === 1 && $from_token[0]['url'] === 'https://cdn.test/from-token.pdf' );

$empty = Attachments::resolve( array( array( 'source' => 'url', 'url' => '' ) ) );
check( 'an empty URL resolves to nothing', array() === $empty );

echo "\n== resolve: order downloads ==\n";
Woocommerce::$items = array(
	array(
		'download_url' => 'https://shop.test/?download_file=1&order=wc_x&key=k1',
		'product_id' => 10,
		'file' => array( 'name' => 'Complete guide', 'file' => $local_pdf ),
	),
	array(
		'download_url' => 'https://shop.test/?download_file=2&order=wc_x&key=k2',
		'product_id' => 11,
		'file' => array( 'name' => 'Bonus pack', 'file' => 'https://cdn.test/bonus.zip' ),
	),
);

$order_files = Attachments::resolve( array( array( 'source' => 'order_downloads' ) ), array( 'order_id' => 7 ) );
check( 'resolves every granted download', count( $order_files ) === 2 );
check( 'a local product file resolves to its path', $order_files[0]['path'] === $local_pdf );
check( 'the download label borrows the file extension', $order_files[0]['name'] === 'Complete guide.pdf' );
check( 'a remotely hosted product file stays remote', true === $order_files[1]['remote'] );
check( 'keeps the permission link for a link fallback', $order_files[0]['link'] === 'https://shop.test/?download_file=1&order=wc_x&key=k1' );
check( 'never exposes the real file path as the link', false === strpos( $order_files[0]['link'], $local_pdf ) );

$no_order = Attachments::resolve( array( array( 'source' => 'order_downloads' ) ), array() );
check( 'no order in the payload resolves to nothing', array() === $no_order );

echo "\n== resolve: permission link used as a URL ==\n";
Logger::$entries = array();
$permission = Attachments::resolve(
	array( array( 'source' => 'url', 'url' => 'https://shop.test/?download_file=1&order=wc_x&key=k1' ) ),
	array( 'order_id' => 7 )
);
check( 'a permission link is resolved through the order instead of fetched', count( $permission ) === 2 );
check( 'the customer download counter is never spent on a fetch', $permission[0]['path'] === $local_pdf );
check( 'the substitution is logged', count( Logger::$entries ) === 1 && 'WARNING' === Logger::$entries[0][0] );

echo "\n== resolve: deduplication and guards ==\n";
$duped = Attachments::resolve( array(
	array( 'source' => 'media', 'attachment_id' => 42 ),
	array( 'source' => 'media', 'attachment_id' => 42 ),
) );
check( 'the same file declared twice is attached once', count( $duped ) === 1 );

check( 'an unknown source falls back to the URL branch',
	count( Attachments::resolve( array( array( 'source' => 'nonsense', 'url' => 'https://cdn.test/a.pdf' ) ) ) ) === 1 );
check( 'a non-array item is skipped', array() === Attachments::resolve( array( 'not-an-item' ) ) );
check( 'an empty list resolves to nothing', array() === Attachments::resolve( array() ) );
check( 'a non-array list resolves to nothing', array() === Attachments::resolve( null ) );

echo "\n== get_contents / total_size ==\n";
check( 'reads a local file', Attachments::get_contents( array( 'path' => $local_pdf ) ) === str_repeat( 'x', 1024 ) );
check( 'a file with neither path nor url yields false', false === Attachments::get_contents( array( 'path' => '', 'url' => '' ) ) );
check( 'sums the known sizes', Attachments::total_size( array( array( 'size' => 10 ), array( 'size' => 32 ) ) ) === 42 );
check( 'remote files contribute zero', Attachments::total_size( array( array( 'size' => 0 ), array( 'size' => 5 ) ) ) === 5 );

echo "\n== esc_url_raw vs placeholders (regression) ==\n";
check( 'esc_url_raw would destroy a placeholder, which is why it is escaped late',
	esc_url_raw( '{{ wc_download_urls }}' ) !== '{{ wc_download_urls }}' );

// ---------------------------------------------------------------------------
// Save-time sanitization
// ---------------------------------------------------------------------------

require __DIR__ . '/../admin/src/Admin/Builder/Registry.php';

use MeuMouse\Joinotify\Admin\Builder\Registry;

/**
 * Sanitize an attachment list the way save_workflow() does.
 */
function sanitize_attachments( array $list ) {
	return call_static( Registry::class, 'sanitize_attachments', array( $list ) );
}

echo "\n== sanitize_attachments ==\n";
$clean = sanitize_attachments( array(
	array( 'source' => 'url', 'url' => 'https://cdn.test/a.pdf', 'name' => 'A' ),
	array( 'source' => 'media', 'attachment_id' => '42', 'url' => 'https://site.test/b.pdf' ),
	array( 'source' => 'order_downloads' ),
) );
check( 'keeps every well-formed item', count( $clean ) === 3 );
check( 'coerces the attachment id to an int', $clean[1]['attachment_id'] === 42 );
check( 'an order item carries no url key', ! array_key_exists( 'url', $clean[2] ) );

$placeholder_url = sanitize_attachments( array(
	array( 'source' => 'url', 'url' => '{{ wc_download_urls }}' ),
) );
check( 'a placeholder URL survives sanitization intact', $placeholder_url[0]['url'] === '{{ wc_download_urls }}' );

check( 'an unknown source is dropped',
	array() === sanitize_attachments( array( array( 'source' => 'ftp', 'url' => 'ftp://x/y' ) ) ) );
check( 'a url item with no url is dropped',
	array() === sanitize_attachments( array( array( 'source' => 'url', 'url' => '' ) ) ) );
check( 'a media item with neither id nor url is dropped',
	array() === sanitize_attachments( array( array( 'source' => 'media' ) ) ) );
check( 'a non-array item is dropped',
	array() === sanitize_attachments( array( 'nope' ) ) );

// ---------------------------------------------------------------------------
// WhatsApp media type selection
// ---------------------------------------------------------------------------

require __DIR__ . '/../admin/src/Core/Workflow_Processor.php';

use MeuMouse\Joinotify\Core\Workflow_Processor;

/**
 * Pick the WhatsApp media type of a resolved file.
 */
function media_type_for( array $file, $fallback = 'document' ) {
	return call_static( Workflow_Processor::class, 'resolve_whatsapp_media_type', array( $file, $fallback ) );
}

echo "\n== resolve_whatsapp_media_type ==\n";
check( 'an image mime wins over the action setting', media_type_for( array( 'mime' => 'image/png' ), 'document' ) === 'image' );
check( 'a video mime wins over the action setting', media_type_for( array( 'mime' => 'video/mp4' ), 'document' ) === 'video' );
check( 'an audio mime wins over the action setting', media_type_for( array( 'mime' => 'audio/mpeg' ), 'document' ) === 'audio' );
check( 'a PDF falls back to the action setting', media_type_for( array( 'mime' => 'application/pdf' ), 'document' ) === 'document' );
check( 'an inconclusive mime with no setting becomes a document', media_type_for( array( 'mime' => '' ), '' ) === 'document' );
check( 'the audio setting never describes an attachment', media_type_for( array( 'mime' => 'application/zip' ), 'audio' ) === 'document' );
check( 'a missing mime key is tolerated', media_type_for( array(), 'image' ) === 'image' );

// cleanup
@unlink( $local_pdf );
@rmdir( $fixture_dir );

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );

}
