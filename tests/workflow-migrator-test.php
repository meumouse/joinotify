<?php
/**
 * Standalone test harness for the legacy workflow migrator.
 *
 * Exercises Workflow_Migrator against the bundled legacy (plugin 1.2.5) export
 * fixtures, asserting that the pure conversion produces canonical 2.0.0 content
 * and is idempotent. No WordPress bootstrap is required: the migrator's core
 * transforms are WP-free, and the filter helper degrades to a no-op when
 * apply_filters() is undefined.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/workflow-migrator-test.php
 *
 * @since 2.0.0
 */

// The migrator file guards with `defined('ABSPATH') || exit;`.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../admin/src/Admin/Builder/Workflow_Migrator.php';

use MeuMouse\Joinotify\Admin\Builder\Workflow_Migrator;

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

/**
 * Recursively find the first node whose data.action matches.
 */
function find_action_node( array $nodes, $action ) {
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}

		if ( isset( $node['data']['action'] ) && $node['data']['action'] === $action ) {
			return $node;
		}

		$children = $node['children'] ?? array();

		if ( is_array( $children ) ) {
			if ( isset( $children['action_true'] ) || isset( $children['action_false'] ) ) {
				foreach ( array( 'action_true', 'action_false' ) as $branch ) {
					$found = find_action_node( $children[ $branch ] ?? array(), $action );
					if ( $found ) {
						return $found;
					}
				}
			} else {
				$found = find_action_node( $children, $action );
				if ( $found ) {
					return $found;
				}
			}
		}
	}

	return null;
}

/**
 * Resolve the trigger node from a content array.
 */
function find_trigger_node( array $nodes ) {
	foreach ( $nodes as $node ) {
		if ( isset( $node['type'] ) && 'trigger' === $node['type'] ) {
			return $node;
		}
	}

	return null;
}

function load_fixture( $name ) {
	$json = file_get_contents( __DIR__ . '/fixtures/' . $name );

	return json_decode( $json, true );
}

echo "== version gate ==\n";
check( 'needs_migration(1.2.5) is true', Workflow_Migrator::needs_migration( '1.2.5' ) === true );
check( 'needs_migration(1.4.7) is true', Workflow_Migrator::needs_migration( '1.4.7' ) === true );
check( 'needs_migration(2.0.0) is false', Workflow_Migrator::needs_migration( '2.0.0' ) === false );
check( 'needs_migration(2.1.0) is false', Workflow_Migrator::needs_migration( '2.1.0' ) === false );
check( 'needs_migration("") is true', Workflow_Migrator::needs_migration( '' ) === true );

echo "\n== fixture 725 (user_register, single whatsapp text) ==\n";
$file = load_fixture( 'joinotify-workflow-725.json' );
$migrated = Workflow_Migrator::migrate_file( $file );
$content = $migrated['workflow_content'];
$trigger = find_trigger_node( $content );

check( 'plugin_version stamped to current schema', $migrated['plugin_version'] === Workflow_Migrator::CURRENT_SCHEMA_VERSION );
check( 'trigger slug preserved (user_register)', $trigger['data']['trigger'] === 'user_register' );
check( 'trigger context preserved (wordpress)', $trigger['data']['context'] === 'wordpress' );
check( 'trigger gains a settings container', isset( $trigger['data']['settings'] ) && is_array( $trigger['data']['settings'] ) );
$text = find_action_node( $content, 'send_whatsapp_message_text' );
check( 'whatsapp text node preserved', $text !== null );
check( 'user_meta placeholder receiver untouched', $text['data']['receiver'] === '{{ user_meta[billing_phone] }}' );

echo "\n== fixture 727 (new order, delay + condition with branches) ==\n";
$file = load_fixture( 'joinotify-workflow-727.json' );
$migrated = Workflow_Migrator::migrate_file( $file );
$content = $migrated['workflow_content'];
$trigger = find_trigger_node( $content );

check( 'trigger slug preserved (woocommerce_new_order)', $trigger['data']['trigger'] === 'woocommerce_new_order' );

$delay = find_action_node( $content, 'time_delay' );
check( 'delay node preserved', $delay !== null );
check( 'stale delay_timestamp dropped', ! isset( $delay['data']['delay_timestamp'] ) );
check( 'delay_period canonical (minute)', $delay['data']['delay_period'] === 'minute' );
check( 'delay_value preserved (5)', (string) $delay['data']['delay_value'] === '5' );

$condition = find_action_node( $content, 'condition' );
check( 'condition node preserved', $condition !== null );
check( 'condition flat key lifted from condition_content', ( $condition['data']['condition'] ?? null ) === 'order_paid' );
check( 'condition_type lifted from legacy type', ( $condition['data']['condition_type'] ?? null ) === 'is' );
check( 'value flat key present', array_key_exists( 'value', $condition['data'] ) );
check( 'condition_content preserved', isset( $condition['data']['condition_content'] ) && is_array( $condition['data']['condition_content'] ) );
check( 'branch container preserved (action_true)', isset( $condition['children']['action_true'] ) && is_array( $condition['children']['action_true'] ) );
check( 'branch container preserved (action_false)', isset( $condition['children']['action_false'] ) && is_array( $condition['children']['action_false'] ) );
check( 'true branch has a whatsapp node', find_action_node( $condition['children']['action_true'], 'send_whatsapp_message_text' ) !== null );
check( 'false branch has a whatsapp node', find_action_node( $condition['children']['action_false'], 'send_whatsapp_message_text' ) !== null );

echo "\n== fixture 729 (order completed, two text nodes) ==\n";
$file = load_fixture( 'joinotify-workflow-729.json' );
$migrated = Workflow_Migrator::migrate_file( $file );
$content = $migrated['workflow_content'];
$trigger = find_trigger_node( $content );
check( 'trigger slug preserved (woocommerce_order_status_completed)', $trigger['data']['trigger'] === 'woocommerce_order_status_completed' );
check( 'first action is a whatsapp text', $content[1]['data']['action'] === 'send_whatsapp_message_text' );

echo "\n== fixture 146 (1.4.6 subscription, delay + nested condition branches) ==\n";
$file = load_fixture( 'joinotify-workflow-146.json' );
check( 'fixture is plugin 1.4.6', $file['plugin_version'] === '1.4.6' );
$migrated = Workflow_Migrator::migrate_file( $file );
$content = $migrated['workflow_content'];
$trigger = find_trigger_node( $content );

check( 'subscription trigger slug preserved (woocommerce_subscription_status_cancelled)', $trigger['data']['trigger'] === 'woocommerce_subscription_status_cancelled' );
check( 'legacy trigger gains a settings container', isset( $trigger['data']['settings'] ) && is_array( $trigger['data']['settings'] ) );

$delay = find_action_node( $content, 'time_delay' );
check( 'delay node preserved', $delay !== null );
check( 'stale delay_timestamp dropped', ! isset( $delay['data']['delay_timestamp'] ) );
check( 'delay_period preserved (day)', $delay['data']['delay_period'] === 'day' );

$condition = find_action_node( $content, 'condition' );
check( 'condition node preserved', $condition !== null );
check( 'condition flat key lifted (customer_phone)', ( $condition['data']['condition'] ?? null ) === 'customer_phone' );
check( 'condition_type lifted from legacy type (not_empty)', ( $condition['data']['condition_type'] ?? null ) === 'not_empty' );
check( 'condition_content preserved', isset( $condition['data']['condition_content'] ) && is_array( $condition['data']['condition_content'] ) );
check( 'nested true branch has whatsapp text', find_action_node( $condition['children']['action_true'], 'send_whatsapp_message_text' ) !== null );
check( 'nested false branch has stop_funnel', find_action_node( $condition['children']['action_false'], 'stop_funnel' ) !== null );
check( 'user_meta placeholder untouched in branch', find_action_node( $condition['children']['action_true'], 'send_whatsapp_message_text' )['data']['receiver'] === '{{ user_meta[billing_phone] }}' );

echo "\n== idempotency (migrate twice == migrate once) ==\n";
foreach ( array( 'joinotify-workflow-725.json', 'joinotify-workflow-727.json', 'joinotify-workflow-729.json', 'joinotify-workflow-146.json' ) as $fixture ) {
	$once = Workflow_Migrator::migrate_file( load_fixture( $fixture ) );
	$twice = Workflow_Migrator::migrate_content( $once['workflow_content'], $once['plugin_version'] );
	check( "{$fixture}: re-running migration is a no-op", $once['workflow_content'] === $twice );
}

echo "\n== summary ==\n";
echo "  {$assertions} assertions, {$failures} failures\n";

exit( $failures > 0 ? 1 : 0 );
