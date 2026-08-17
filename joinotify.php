<?php

/**
 * Plugin Name: 			Joinotify
 * Description: 			Increase customer satisfaction by automating WhatsApp messaging with Joinotify.
 * Plugin URI: 				https://meumouse.com/plugins/joinotify/
 * Author: 					MeuMouse.com
 * Author URI: 				https://meumouse.com/
 * Version: 				2.3.0
 * Requires at least: 		6.0
 * Requires PHP: 			8.1.0
 * Tested up to: 			7.0
 * Text Domain: 			joinotify
 * Domain Path: 			/languages
 * License: 				GPLv2 or later
 * License URI: 			https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright 				2026 MeuMouse.com
 * @license 				GPL-2.0-or-later - See LICENSE for details
 */

use MeuMouse\Joinotify\Core\Init;
use MeuMouse\Joinotify\Core\Onboarding;
use MeuMouse\Joinotify\Telemetry\Dispatcher;
use MeuMouse\Joinotify\Telemetry\Recorder;

defined('ABSPATH') || exit;

// Load Composer autoloader if available.
$joinotify_autoload = plugin_dir_path( __FILE__ ) . 'admin/vendor/autoload.php';

if ( file_exists( $joinotify_autoload ) ) {
	require_once $joinotify_autoload;
}

$joinotify_plugin_version = '2.3.0';

// Flag a fresh activation so the first admin request opens the setup wizard.
// The wizard is where the site owner picks a country, connects the Joinotify
// account and decides about usage data, so nothing reaches an external server
// before they have been through it.
register_activation_hook( __FILE__, function() {
	if ( class_exists( Onboarding::class ) ) {
		Onboarding::on_activation();
	}

	if ( class_exists( Recorder::class ) ) {
		Recorder::record('plugin.activated');
		Recorder::flush_now();
	}
} );

// Deactivation. Two jobs, and the second one is overdue: until now the plugin left every
// scheduled event of its own behind on deactivation, so a site that switched it off kept
// firing empty cron hooks until something cleaned wp_options by hand.
//
// Nothing is deleted here. Turning a plugin off is not the same as uninstalling it, and a
// site owner who deactivates to debug a conflict expects to find their workflows intact.
register_deactivation_hook( __FILE__, function() {
	if ( class_exists( Recorder::class ) ) {
		Recorder::record('plugin.deactivated');
		Recorder::flush_now();
	}

	// One last attempt while the plugin is still loaded — there will be no next cron run
	// to carry it. Best effort by definition; the buffer keeps it if this fails.
	if ( class_exists( Dispatcher::class ) ) {
		Dispatcher::dispatch();
	}

	$scheduled = array(
		'joinotify_check_phone_connection_event',
		'joinotify_update_templates_count',
		'joinotify_process_notification_queue_event',
		'joinotify_purge_debug_logs_event',
		'joinotify_purge_message_history_event',
		'joinotify_scheduled_actions_event',
		'joinotify_telemetry_dispatch_event',
	);

	foreach ( $scheduled as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
} );

// Initialize the plugin
new Init( __FILE__, $joinotify_plugin_version );
