<?php
/**
 * Standalone test harness for the workflow templates API client.
 *
 * Covers the migration to the `/workflow-templates` namespace on the account
 * API: the routes each method calls, the pagination walk, the download route
 * being the one the import path uses (and never served from a stored copy), the
 * checksum/ETag freshness rules of the plain read, and the error envelope the
 * API returns. HTTP is scripted, so no network and no WordPress bootstrap are
 * required.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/workflow-templates-test.php
 *
 * @since 2.3.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'JOINOTIFY_VERSION', '2.3.0' );

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

$GLOBALS['transients'] = array();
$GLOBALS['http_queue'] = array();
$GLOBALS['http_calls'] = array();
$GLOBALS['logged'] = array();

function get_transient( $key ) { global $transients; return array_key_exists( $key, $transients ) ? $transients[ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { global $transients; $transients[ $key ] = $value; return true; }
function delete_transient( $key ) { global $transients; unset( $transients[ $key ] ); return true; }
function apply_filters( $hook, $value = null ) { return $value; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function __( $text, $domain = 'default' ) { return $text; }

function add_query_arg( $args, $url ) {
	$separator = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $separator . http_build_query( $args );
}

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->message = $message;
	}

	public function get_error_message() { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

/**
 * Answer requests from a scripted queue, recording what was asked.
 */
function wp_remote_get( $url, $args = array() ) {
	global $http_queue, $http_calls;

	$http_calls[] = array(
		'url' => $url,
		'headers' => isset( $args['headers'] ) ? $args['headers'] : array(),
	);

	$response = array_shift( $http_queue );

	return null === $response ? new WP_Error( 'http_failure', 'No scripted response' ) : $response;
}

function wp_remote_retrieve_response_code( $response ) { return isset( $response['code'] ) ? $response['code'] : 200; }
function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }

function wp_remote_retrieve_header( $response, $header ) {
	$headers = isset( $response['headers'] ) ? $response['headers'] : array();

	return isset( $headers[ $header ] ) ? $headers[ $header ] : '';
}

/**
 * Queue a JSON response.
 */
function queue_json( $payload, $code = 200, $etag = '' ) {
	global $http_queue;

	$http_queue[] = array(
		'code' => $code,
		'body' => is_string( $payload ) ? $payload : json_encode( $payload ),
		'headers' => '' === $etag ? array() : array( 'etag' => '"' . $etag . '"' ),
	);
}

/**
 * Minimal $wpdb double so flush_cache() can be exercised.
 */
class Fake_Wpdb {
	public $options = 'wp_options';
	public $queries = array();

	public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
	public function prepare( $query, ...$args ) { return vsprintf( str_replace( '%s', "'%s'", $query ), $args ); }
	public function query( $query ) { $this->queries[] = $query; return 1; }
}

$GLOBALS['wpdb'] = new Fake_Wpdb();

}

// ---------------------------------------------------------------------------
// Namespaced collaborator fakes (must exist before the real class loads)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Logger {
		public static function register_log( $message, $level = 'INFO' ) {
			$GLOBALS['logged'][] = array( 'message' => $message, 'level' => $level );
		}
	}
}

namespace MeuMouse\Joinotify\Api {
	class Cloud_Client {
		public static function base_url() { return 'https://api.joinotify.com'; }
	}
}

namespace {

require_once __DIR__ . '/../admin/src/Api/Workflow_Templates.php';

use MeuMouse\Joinotify\Api\Workflow_Templates;

const TEMPLATE_ID = '0fd76bf3-d69e-4bd8-97c8-e23a7e70b8cd';

/**
 * Reset every stubbed store between scenarios.
 */
function reset_state() {
	$GLOBALS['transients'] = array();
	$GLOBALS['http_queue'] = array();
	$GLOBALS['http_calls'] = array();
	$GLOBALS['logged'] = array();
	$GLOBALS['wpdb'] = new Fake_Wpdb();
}

/**
 * Build a catalog item as the API returns it.
 */
function catalog_item( $id, $checksum = 'abc123', $category = 'wordpress' ) {
	return array(
		'id' => $id,
		'file' => $id . '.json',
		'title' => 'Boas-vindas ao cadastro',
		'description' => 'Trigger description.',
		'category' => $category,
		'trigger' => 'user_register',
		'tags' => array(),
		'status' => 'published',
		'min_plugin_version' => '2.0.0',
		'downloads' => 0,
		'version' => 1,
		'checksum' => $checksum,
	);
}

/**
 * Build a template payload as the API returns it.
 */
function template_payload( $title = 'Boas-vindas ao cadastro' ) {
	return array(
		'plugin_version' => '2.0.0',
		'post' => array( 'type' => 'joinotify-workflow', 'title' => $title, 'status' => 'draft' ),
		'workflow_content' => array( array( 'id' => 'joinotify_trigger_1', 'type' => 'trigger' ) ),
	);
}

echo "\nWorkflow templates API client\n";

// ---------------------------------------------------------------------------
// Catalog
// ---------------------------------------------------------------------------

echo "\n> Catalog\n";
reset_state();

queue_json( array(
	'items' => array( catalog_item( TEMPLATE_ID ) ),
	'pagination' => array( 'page' => 1, 'per_page' => 100, 'total' => 1, 'total_pages' => 1 ),
) );

$catalog = Workflow_Templates::get_catalog();
$call = $GLOBALS['http_calls'][0]['url'];

check( 'catalog hits the /workflow-templates route on the account API', 0 === strpos( $call, 'https://api.joinotify.com/workflow-templates?' ) );
check( 'catalog asks for the maximum page size the API allows', false !== strpos( $call, 'per_page=100' ) );
check( 'catalog returns the API items', 1 === count( $catalog ) && TEMPLATE_ID === $catalog[0]['id'] );
check( 'catalog keeps the fields the library needs', ! empty( $catalog[0]['checksum'] ) && ! empty( $catalog[0]['min_plugin_version'] ) );

// Second call is served from the transient.
Workflow_Templates::get_catalog();
check( 'catalog is cached after a successful fetch', 1 === count( $GLOBALS['http_calls'] ) );

reset_state();

queue_json( array(
	'items' => array( catalog_item( TEMPLATE_ID ) ),
	'pagination' => array( 'page' => 1, 'per_page' => 100, 'total' => 101, 'total_pages' => 2 ),
) );
queue_json( array(
	'items' => array( catalog_item( '11111111-2222-3333-4444-555555555555' ) ),
	'pagination' => array( 'page' => 2, 'per_page' => 100, 'total' => 101, 'total_pages' => 2 ),
) );

$catalog = Workflow_Templates::get_catalog();

check( 'catalog walks every page', 2 === count( $catalog ) && 2 === count( $GLOBALS['http_calls'] ) );
check( 'catalog requests the second page', false !== strpos( $GLOBALS['http_calls'][1]['url'], 'page=2' ) );

reset_state();
queue_json( array( 'error' => array( 'message' => 'Invalid query.', 'type' => 'invalid_request' ) ), 422 );

$catalog = Workflow_Templates::get_catalog();

check( 'a failed catalog fetch returns an empty list', array() === $catalog );
check( 'a failed catalog fetch is not cached', false === get_transient( Workflow_Templates::CATALOG_CACHE_KEY ) );
check( 'the API error message reaches the log', ! empty( $GLOBALS['logged'] ) && false !== strpos( $GLOBALS['logged'][0]['message'], 'Invalid query.' ) );

// ---------------------------------------------------------------------------
// Count and categories
// ---------------------------------------------------------------------------

echo "\n> Count and categories\n";
reset_state();
queue_json( array( 'count' => 7 ) );

$count = Workflow_Templates::get_templates_count();

check( 'count hits /workflow-templates/count', 'https://api.joinotify.com/workflow-templates/count' === $GLOBALS['http_calls'][0]['url'] );
check( 'count is returned as an integer', 7 === $count );

reset_state();
queue_json( array( 'items' => array() ), 500 );

check( 'a failed count returns null instead of zero', null === Workflow_Templates::get_templates_count() );

reset_state();
queue_json( array( 'categories' => array(
	array( 'category' => 'woocommerce', 'count' => 4 ),
	array( 'category' => 'wordpress', 'count' => 3 ),
	array( 'count' => 9 ),
) ) );

$categories = Workflow_Templates::get_categories();

check( 'categories hits /workflow-templates/categories', 'https://api.joinotify.com/workflow-templates/categories' === $GLOBALS['http_calls'][0]['url'] );
check( 'categories are normalized with their counts', array(
	array( 'category' => 'woocommerce', 'count' => 4 ),
	array( 'category' => 'wordpress', 'count' => 3 ),
) === $categories );

Workflow_Templates::get_categories();
check( 'categories are cached after a successful fetch', 1 === count( $GLOBALS['http_calls'] ) );

// ---------------------------------------------------------------------------
// Download (import path)
// ---------------------------------------------------------------------------

echo "\n> Download\n";
reset_state();
queue_json( template_payload(), 200, 'abc123' );

$template = Workflow_Templates::download_template( TEMPLATE_ID . '.json' );

check( 'download hits /workflow-templates/{id}/download', 'https://api.joinotify.com/workflow-templates/' . TEMPLATE_ID . '/download' === $GLOBALS['http_calls'][0]['url'] );
check( 'download accepts the catalog file name', is_array( $template ) && isset( $template['workflow_content'] ) );
check( 'download returns the raw payload, not an envelope', ! isset( $template['template'] ) && '2.0.0' === $template['plugin_version'] );

queue_json( template_payload(), 200, 'abc123' );
Workflow_Templates::download_template( TEMPLATE_ID );

check( 'download is never served from a stored copy, so every import counts', 2 === count( $GLOBALS['http_calls'] ) );

reset_state();
check( 'download rejects an identifier that is not a UUID', null === Workflow_Templates::download_template( '../../etc/passwd' ) );
check( 'a rejected identifier never reaches the network', empty( $GLOBALS['http_calls'] ) );

reset_state();
queue_json( array( 'error' => array( 'message' => 'Template not found.', 'type' => 'not_found' ) ), 404 );
check( 'a missing template returns null', null === Workflow_Templates::download_template( TEMPLATE_ID ) );

// ---------------------------------------------------------------------------
// Plain read (checksum / ETag)
// ---------------------------------------------------------------------------

echo "\n> Read and revalidation\n";
reset_state();
queue_json( array( 'meta' => catalog_item( TEMPLATE_ID ), 'template' => template_payload() ), 200, 'abc123' );

$template = Workflow_Templates::get_template( TEMPLATE_ID );

check( 'read hits /workflow-templates/{id}', 'https://api.joinotify.com/workflow-templates/' . TEMPLATE_ID === $GLOBALS['http_calls'][0]['url'] );
check( 'read unwraps the { meta, template } envelope', is_array( $template ) && isset( $template['workflow_content'] ) );

// With the catalog advertising the same checksum, the stored copy is provably
// current and no request is made at all.
set_transient( Workflow_Templates::CATALOG_CACHE_KEY, array( catalog_item( TEMPLATE_ID, 'abc123' ) ), 3600 );
Workflow_Templates::get_template( TEMPLATE_ID );

check( 'a stored copy matching the catalog checksum skips the request', 1 === count( $GLOBALS['http_calls'] ) );

// A new checksum upstream invalidates the stored copy without a cache flush.
set_transient( Workflow_Templates::CATALOG_CACHE_KEY, array( catalog_item( TEMPLATE_ID, 'def456' ) ), 3600 );
queue_json( array( 'meta' => catalog_item( TEMPLATE_ID, 'def456' ), 'template' => template_payload( 'Updated' ) ), 200, 'def456' );

$template = Workflow_Templates::get_template( TEMPLATE_ID );

check( 'a changed catalog checksum refetches the template', 2 === count( $GLOBALS['http_calls'] ) && 'Updated' === $template['post']['title'] );
check( 'the refetch revalidates with the stored ETag', '"abc123"' === ( $GLOBALS['http_calls'][1]['headers']['If-None-Match'] ?? '' ) );

// Without a catalog to compare against, the conditional request is what keeps
// the stored copy honest.
delete_transient( Workflow_Templates::CATALOG_CACHE_KEY );
queue_json( '', 304, 'def456' );

$template = Workflow_Templates::get_template( TEMPLATE_ID );

check( 'a 304 serves the stored copy', is_array( $template ) && 'Updated' === $template['post']['title'] );
check( 'the 304 request carried the current ETag', '"def456"' === ( $GLOBALS['http_calls'][2]['headers']['If-None-Match'] ?? '' ) );

// An outage must not lose a copy we already hold.
$template = Workflow_Templates::get_template( TEMPLATE_ID, true );

check( 'a failed refresh falls back to the stored copy', is_array( $template ) && 'Updated' === $template['post']['title'] );

// ---------------------------------------------------------------------------
// Base URL and cache flush
// ---------------------------------------------------------------------------

echo "\n> Base URL and flush\n";
reset_state();

check( 'the base URL follows the account API', 'https://api.joinotify.com' === Workflow_Templates::get_base_url() );

set_transient( Workflow_Templates::CATALOG_CACHE_KEY, array( catalog_item( TEMPLATE_ID ) ), 3600 );
set_transient( Workflow_Templates::CATEGORIES_CACHE_KEY, array( array( 'category' => 'wordpress', 'count' => 3 ) ), 3600 );

Workflow_Templates::flush_cache();

check( 'flush drops the catalog', false === get_transient( Workflow_Templates::CATALOG_CACHE_KEY ) );
check( 'flush drops the categories', false === get_transient( Workflow_Templates::CATEGORIES_CACHE_KEY ) );
// The LIKE patterns are escaped for the query, so compare without the escapes.
$flush_query = ! empty( $GLOBALS['wpdb']->queries ) ? stripslashes( $GLOBALS['wpdb']->queries[0] ) : '';

check( 'flush drops the stored templates', false !== strpos( $flush_query, '_transient_' . Workflow_Templates::TEMPLATE_CACHE_PREFIX ) );

// ---------------------------------------------------------------------------

echo "\n";
echo $failures > 0
	? "FAILED: {$failures} of {$assertions} assertions failed\n\n"
	: "OK: {$assertions} assertions passed\n\n";

exit( $failures > 0 ? 1 : 0 );

}
