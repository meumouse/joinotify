<?php
/**
 * Regression test: a node `description` never leaves the workflow sanitizer as live markup.
 *
 * The canvas renders `data.description` as HTML. Registry::sanitize_workflow_content() runs
 * wp_kses_post() on it and then decodes entities for the emoji round trip, and that order used
 * to undo the filter: `&lt;img src=x onerror=…&gt;` is text to kses and an element after the
 * decode. Imported workflow files, remote templates and the AI generator all reach the canvas
 * through this function, so the payloads below are the shapes each of them can deliver.
 *
 * Runs against WordPress's REAL kses, loaded from a core checkout without a database — a
 * stubbed wp_kses_post() would test nothing. Point JOINOTIFY_WP_ROOT at any WordPress root;
 * by default it is the one this plugin is installed in (wp-content/plugins/joinotify).
 *
 * Run (Windows / Local):
 *   $env:JOINOTIFY_WP_ROOT = "C:\Users\<you>\Local Sites\joinotify\app\public"
 *   & "C:\path\to\Local\php.exe" tests/workflow-description-xss-test.php
 *
 * @since 2.5.0
 */

namespace {

$wp_root = rtrim( (string) ( getenv( 'JOINOTIFY_WP_ROOT' ) ?: dirname( __DIR__, 4 ) ), '/\\' ) . '/';

if ( ! is_file( $wp_root . 'wp-includes/kses.php' ) ) {
	fwrite( STDERR, "WordPress core not found at {$wp_root}. Set JOINOTIFY_WP_ROOT to a WordPress root.\n" );
	exit( 2 );
}

define( 'ABSPATH', $wp_root );
define( 'WPINC', 'wp-includes' );

// What wp-settings.php loads before kses, minus everything that needs a database.
require ABSPATH . WPINC . '/compat-utf8.php';
require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/plugin.php';
if ( is_file( ABSPATH . WPINC . '/utf8.php' ) ) {
	require ABSPATH . WPINC . '/utf8.php';
}
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/class-wp-token-map.php';
foreach ( array( 'html5-named-character-references', 'class-wp-html-attribute-token', 'class-wp-html-span', 'class-wp-html-doctype-info', 'class-wp-html-text-replacement', 'class-wp-html-decoder', 'class-wp-html-tag-processor' ) as $html_api_file ) {
	require ABSPATH . WPINC . "/html-api/{$html_api_file}.php";
}
require ABSPATH . WPINC . '/kses.php';

// Two helpers that live in functions.php / load.php, which cannot load without the rest of core.
function wp_allowed_protocols() {
	return array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );
}

function is_utf8_charset( $charset = null ) {
	return true;
}

function get_option( $name, $default = false ) {
	return $default;
}

require __DIR__ . '/../admin/src/Core/Helpers.php';
require __DIR__ . '/../admin/src/Admin/Builder/Registry.php';

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

/**
 * Whether the HTML, once parsed, holds an element or attribute that can run script.
 *
 * Parsed with WordPress's own tag processor rather than matched with a regex: `onerror=` inside
 * escaped text is harmless, and only the elements a browser would actually build count. A plain
 * `<img src="x">` is allowed by wp_kses_post() and runs nothing, so it is not a failure here;
 * turning every tag into text is the canvas's job (app/src/utils/html.ts), tested in the browser.
 */
function has_live_markup( $html ) {
	$processor = new WP_HTML_Tag_Processor( $html );

	while ( $processor->next_tag() ) {
		if ( in_array( $processor->get_tag(), array( 'SCRIPT', 'SVG', 'IFRAME', 'OBJECT', 'EMBED', 'STYLE', 'MATH' ), true ) ) {
			return true;
		}

		if ( array() !== ( $processor->get_attribute_names_with_prefix( 'on' ) ?? array() ) ) {
			return true;
		}

		foreach ( array( 'href', 'src', 'action', 'formaction' ) as $url_attribute ) {
			$url = $processor->get_attribute( $url_attribute );

			if ( is_string( $url ) && preg_match( '/^\s*(javascript|vbscript|data)\s*:/i', $url ) ) {
				return true;
			}
		}
	}

	return false;
}

$payloads = array(
	'raw tag'                => '<img src=x onerror="window.xssFired=1"><b data-xss-marker>xss</b>',
	'named entities'         => '&lt;img src=x onerror="window.xssFired=1"&gt;&lt;b data-xss-marker&gt;xss&lt;/b&gt;',
	'double-encoded'         => '&amp;lt;img src=x onerror=window.xssFired=1&amp;gt;',
	'numeric entities'       => '&#60;img src=x onerror=window.xssFired=1&#62;',
	'double numeric'         => '&amp;#60;svg onload=window.xssFired=1&amp;#62;',
	'javascript href'        => '<a href="javascript:window.xssFired=1">x</a>',
	'encoded javascript href' => '&lt;a href="jav&amp;#x61;script:window.xssFired=1"&gt;x&lt;/a&gt;',
);

foreach ( $payloads as $label => $payload ) {
	$content = array(
		array(
			'id'       => 'node_trigger',
			'type'     => 'trigger',
			'data'     => array( 'trigger' => 'order_completed', 'context' => 'woocommerce', 'description' => $payload ),
			'children' => array(),
		),
		array(
			'id'       => 'node_condition',
			'type'     => 'action',
			'data'     => array( 'action' => 'condition', 'description' => 'outer' ),
			'children' => array(
				'action_true'  => array(
					array(
						'id'   => 'node_nested',
						'type' => 'action',
						'data' => array( 'action' => 'send_whatsapp_message_text', 'message' => 'oi', 'description' => $payload ),
					),
				),
				'action_false' => array(),
			),
		),
	);

	$sanitized = \MeuMouse\Joinotify\Admin\Builder\Registry::sanitize_workflow_content( $content );
	$top = (string) ( $sanitized[0]['data']['description'] ?? '' );
	$nested = (string) ( $sanitized[1]['children']['action_true'][0]['data']['description'] ?? '' );

	check( "{$label}: top-level description holds no live markup (" . $top . ')', ! has_live_markup( $top ) );
	check( "{$label}: nested description holds no live markup (" . $nested . ')', ! has_live_markup( $nested ) );

	// The sanitizer runs again on every read (get_workflow_state), so its output must be stable.
	$again = \MeuMouse\Joinotify\Admin\Builder\Registry::sanitize_workflow_content( $sanitized );
	check( "{$label}: a second pass stays clean", ! has_live_markup( (string) ( $again[0]['data']['description'] ?? '' ) ) );
}

// The formatting the canvas relies on survives.
$formatted = \MeuMouse\Joinotify\Admin\Builder\Registry::sanitize_workflow_content(
	array(
		array(
			'id'   => 'node_fmt',
			'type' => 'action',
			'data' => array( 'action' => 'send_whatsapp_message_text', 'description' => 'Olá, <span class="builder-placeholder">{{ first_name }}</span> <strong>😀</strong> A &amp; B' ),
		),
	)
);
$formatted_description = (string) ( $formatted[0]['data']['description'] ?? '' );
check( 'placeholder pill kept (' . $formatted_description . ')', false !== strpos( $formatted_description, '<span class="builder-placeholder">{{ first_name }}</span>' ) );
check( 'bold and emoji kept', false !== strpos( $formatted_description, '<strong>😀</strong>' ) );

echo "\n{$assertions} assertions, {$failures} failures\n";
exit( $failures > 0 ? 1 : 0 );

}
