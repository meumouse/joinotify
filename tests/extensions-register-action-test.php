<?php
/**
 * Standalone test harness for Extensions::register_action(): what it leaves in the catalog entry
 * pushed to Joinotify/Builder/Actions, and which convenience keys it wires to their own filters.
 *
 * No WordPress bootstrap: add_filter() only records the callbacks, and apply_filters() runs them.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/extensions-register-action-test.php
 *
 * @since 2.5.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );

$failures = 0;
$assertions = 0;
$filters = array();

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

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $filters;
	$filters[ $hook ][] = array( $callback, $accepted_args );
}

function apply_filters( $hook, $value, ...$args ) {
	global $filters;

	foreach ( isset( $filters[ $hook ] ) ? $filters[ $hook ] : array() as $filter ) {
		$value = call_user_func_array( $filter[0], array_slice( array_merge( array( $value ), $args ), 0, $filter[1] ) );
	}

	return $value;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function reset_filters() {
	global $filters;
	$filters = array();
}

function catalog_entry( $slug ) {
	foreach ( apply_filters( 'Joinotify/Builder/Actions', array() ) as $action ) {
		if ( $action['action'] === $slug ) {
			return $action;
		}
	}

	return null;
}

require __DIR__ . '/../admin/src/Api/Extensions.php';

use MeuMouse\Joinotify\Api\Extensions;

echo "\nA string description is the library text\n";

reset_filters();
Extensions::register_action( array(
	'action'      => 'send_webhook',
	'title'       => 'Send webhook',
	'description' => 'Send the trigger\'s data to any URL.',
) );

$entry = catalog_entry( 'send_webhook' );
check( 'stays in the catalog entry', isset( $entry['description'] ) && 'Send the trigger\'s data to any URL.' === $entry['description'] );
check( 'registers no canvas description builder', empty( $GLOBALS['filters']['Joinotify/Builder/Action_Description'] ) );

reset_filters();
Extensions::register_action( array(
	'action'      => 'nap',
	'title'       => 'Nap',
	'description' => 'Sleep',
) );

$entry = catalog_entry( 'nap' );
check( 'stays even when it names a PHP function', isset( $entry['description'] ) && 'Sleep' === $entry['description'] );
check( '... and is never called as a builder', empty( $GLOBALS['filters']['Joinotify/Builder/Action_Description'] ) );

echo "\nA closure description is the canvas builder\n";

reset_filters();
Extensions::register_action( array(
	'action'      => 'my_app_send_sms',
	'title'       => 'Send SMS',
	'description' => function( $data ) {
		return 'SMS to ' . $data['to'];
	},
) );

$entry = catalog_entry( 'my_app_send_sms' );
check( 'is stripped from the catalog entry', is_array( $entry ) && ! array_key_exists( 'description', $entry ) );
check( 'is wired to Joinotify/Builder/Action_Description', 'SMS to 5541' === apply_filters( 'Joinotify/Builder/Action_Description', '', 'my_app_send_sms', array( 'data' => array( 'to' => '5541' ) ) ) );
check( 'leaves other actions\' descriptions alone', 'untouched' === apply_filters( 'Joinotify/Builder/Action_Description', 'untouched', 'other_action', array() ) );

echo "\nThe other convenience keys\n";

reset_filters();
Extensions::register_action( array(
	'action'         => 'my_app_send_sms',
	'title'          => 'Send SMS',
	'description'    => 'Send an SMS.',
	'handler'        => function() {
		return true;
	},
	'fill_sender'    => true,
	'category'       => 'my_app',
	'category_label' => 'My App',
) );

$entry = catalog_entry( 'my_app_send_sms' );
check( 'are stripped from the catalog entry', is_array( $entry ) && ! array_intersect( array( 'handler', 'fill_sender', 'category_label', 'category_icon', 'category_priority' ), array_keys( $entry ) ) );
check( 'keep the category id', 'my_app' === $entry['category'] );
check( 'auto-register the category tab', array( 'my_app' ) === array_column( apply_filters( 'Joinotify/Builder/Action_Categories', array() ), 'id' ) );
check( 'add the slug to the fill-sender list', array( 'my_app_send_sms' ) === apply_filters( 'Joinotify/Download_Template/Fill_Sender_Actions', array() ) );

echo "\n{$assertions} assertions, {$failures} failure(s).\n";
exit( $failures > 0 ? 1 : 0 );

}
