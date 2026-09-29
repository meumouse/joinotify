<?php
/**
 * Standalone test harness for loading WordPress.org language packs into the Vue bundles.
 *
 * translate.wordpress.org ships one JSON per built script file, keyed by the md5 of
 * its path. Core only reads the one for the enqueued entry, so
 * Settings_Assets::merge_language_pack_translations() folds in the JSONs of every
 * chunk the entry imports. This exercises that merge and Scripts::get_entry_script_files()
 * against a fixture manifest, with the WordPress functions they touch stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/script-translations-test.php
 *
 * @since 2.4.3
 */

namespace MeuMouse\Joinotify\Assets {
	abstract class Abstract_Assets {
		public function __construct() {}
	}
}

namespace {

$fixture = sys_get_temp_dir() . '/joinotify-script-translations-' . uniqid();

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_DIR', $fixture . '/plugin/' );
define( 'JOINOTIFY_URL', 'https://example.test/wp-content/plugins/joinotify/' );
define( 'WP_LANG_DIR', $fixture . '/languages' );

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

$current_locale = 'pt_BR';
$registered_scripts = array();

function determine_locale() { global $current_locale; return $current_locale; }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function wp_json_encode( $data ) { return json_encode( $data ); }

function wp_scripts() {
	return new class() {
		public function query( $handle ) {
			global $registered_scripts;
			return isset( $registered_scripts[ $handle ] ) ? (object) array( 'src' => $registered_scripts[ $handle ] ) : false;
		}
	};
}

// ---------------------------------------------------------------------------
// Fixture: a Vite manifest and the language packs WordPress.org would ship
// ---------------------------------------------------------------------------

function write_file( $path, $contents ) {
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0777, true );
	}

	file_put_contents( $path, $contents );
}

function write_pack( $locale, $dist_file, $messages ) {
	$messages = array( '' => array( 'domain' => 'messages', 'lang' => $locale, 'plural-forms' => 'nplurals=2; plural=n > 1;' ) ) + $messages;

	write_file(
		WP_LANG_DIR . "/plugins/joinotify-{$locale}-" . md5( 'app/dist/' . $dist_file ) . '.json',
		json_encode( array( 'domain' => 'messages', 'locale_data' => array( 'messages' => $messages ) ) )
	);
}

write_file( JOINOTIFY_DIR . 'app/dist/.vite/manifest.json', json_encode( array(
	'src/entries/settings.js' => array(
		'file' => 'settings/app.js',
		'isEntry' => true,
		'imports' => array( '_PageHeader.js', '_shared.js' ),
		'dynamicImports' => array( 'src/lazy.js' ),
		'css' => array( 'styles/settings.css' ),
	),
	'src/entries/queue.js' => array(
		'file' => 'queue/app.js',
		'isEntry' => true,
		'imports' => array( '_shared.js' ),
	),
	'_PageHeader.js' => array( 'file' => 'chunks/PageHeader-a1.js', 'imports' => array( '_shared.js' ) ),
	'_shared.js' => array( 'file' => 'chunks/shared-b2.js', 'imports' => array( '_PageHeader.js' ) ),
	'src/lazy.js' => array( 'file' => 'chunks/lazy-c3.js' ),
) ) );

write_pack( 'pt_BR', 'chunks/PageHeader-a1.js', array( 'Save' => array( 'Salvar' ) ) );
write_pack( 'pt_BR', 'chunks/shared-b2.js', array( 'Cancel' => array( 'Cancelar' ), 'Save' => array( 'Gravar' ) ) );
write_pack( 'pt_BR', 'chunks/lazy-c3.js', array( '%d item' => array( '%d item', '%d itens' ) ) );
write_pack( 'es_ES', 'queue/app.js', array( 'Queue' => array( 'Cola' ) ) );

$registered_scripts = array(
	'joinotify-settings-app' => JOINOTIFY_URL . 'app/dist/settings/app.js',
	'joinotify-queue-app' => JOINOTIFY_URL . 'app/dist/queue/app.js',
	'joinotify-history-app' => JOINOTIFY_URL . 'app/dist/history/app.js',
	'other-plugin-app' => 'https://example.test/wp-content/plugins/other/app/dist/settings/app.js',
);

require dirname( __DIR__ ) . '/admin/src/Core/Scripts.php';
require dirname( __DIR__ ) . '/admin/src/Assets/Settings_Assets.php';

use MeuMouse\Joinotify\Assets\Settings_Assets;
use MeuMouse\Joinotify\Core\Scripts;

function merge( $handle, $domain = 'joinotify', $translations = null ) {
	return Settings_Assets::merge_language_pack_translations( $translations, false, $handle, $domain );
}

// ---------------------------------------------------------------------------
// Scripts::get_entry_script_files()
// ---------------------------------------------------------------------------

echo "\nScripts::get_entry_script_files\n";

check(
	'walks static and dynamic imports once each, entry first, skipping CSS',
	array( 'settings/app.js', 'chunks/PageHeader-a1.js', 'chunks/shared-b2.js', 'chunks/lazy-c3.js' ) === Scripts::get_entry_script_files( 'settings/app.js' )
);
check( 'accepts a leading slash', 'settings/app.js' === Scripts::get_entry_script_files( '/settings/app.js' )[0] );
check( 'returns nothing for an unknown entry', array() === Scripts::get_entry_script_files( 'missing/app.js' ) );
check( 'does not treat a chunk as an entry', array() === Scripts::get_entry_script_files( 'chunks/shared-b2.js' ) );

// ---------------------------------------------------------------------------
// Settings_Assets::merge_language_pack_translations()
// ---------------------------------------------------------------------------

echo "\nSettings_Assets::merge_language_pack_translations\n";

$json = merge( 'joinotify-settings-app' );
$data = json_decode( (string) $json, true );
$messages = $data['locale_data']['messages'] ?? array();

check( 'returns JSON for an entry whose strings live in its chunks', is_string( $json ) );
check( 'keeps the Jed "messages" domain core expects', 'messages' === ( $data['domain'] ?? null ) );
check( 'keeps the pack header with plural forms', 'pt_BR' === ( $messages['']['lang'] ?? null ) );
check( 'merges strings from a static import', array( 'Cancelar' ) === ( $messages['Cancel'] ?? null ) );
check( 'merges strings from a dynamic import', array( '%d item', '%d itens' ) === ( $messages['%d item'] ?? null ) );
check( 'the first chunk wins on duplicate strings', array( 'Salvar' ) === ( $messages['Save'] ?? null ) );
check( 'memoizes the merge for the same handle', $json === merge( 'joinotify-settings-app' ) );

check( 'reads the entry file\'s own pack too', 'Cola' === ( json_decode( (string) ( function () {
	global $current_locale;
	$current_locale = 'es_ES';
	return merge( 'joinotify-queue-app' );
} )(), true )['locale_data']['messages']['Queue'][0] ?? null ) );

$current_locale = 'pt_BR';

check( 'steps aside when no pack exists for any file', null === merge( 'joinotify-history-app' ) );
check( 'ignores scripts outside the Joinotify dist', null === merge( 'other-plugin-app' ) );
check( 'ignores unregistered handles', null === merge( 'joinotify-unknown-app' ) );
check( 'ignores other text domains', null === merge( 'joinotify-queue-app', 'woocommerce' ) );
check( 'passes through translations an earlier filter supplied', '{"a":1}' === merge( 'joinotify-queue-app', 'joinotify', '{"a":1}' ) );

$current_locale = 'en_US';
check( 'does nothing for en_US', null === merge( 'joinotify-settings-app' ) );

$current_locale = 'fr_FR';
write_pack( 'fr_FR', 'chunks/shared-b2.js', array( 'Cancel' => array( 'Annuler' ) ) );
write_file( JOINOTIFY_DIR . 'languages/joinotify-fr_FR-joinotify-queue-app.json', '{}' );
check( 'defers to a bundled handle JSON (--ship-locales packages)', null === merge( 'joinotify-queue-app' ) );

// ---------------------------------------------------------------------------

echo "\n{$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );

}
