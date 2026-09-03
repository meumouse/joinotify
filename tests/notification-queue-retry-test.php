<?php
/**
 * Standalone test harness for the notification retry policy.
 *
 * The backoff is a promise made in two places at once: the settings screen tells the
 * reader "30, 60, 120, 240, 480 minutes", and Core\Notification_Queue is what has to
 * produce it. This file is what keeps the two from drifting apart — and what catches the
 * clamps, since a retry budget read from an option is a number a human can typo.
 *
 * Notification_Queue does call WordPress, so the handful of functions it touches are
 * stubbed below rather than bootstrapped.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/notification-queue-retry-test.php
 *
 * @since 2.4.0
 */

namespace {
	// The class file guards with `defined('ABSPATH') || exit;`.
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );

	// Stands in for the stored settings the queue reads through Admin::get_setting().
	$GLOBALS['joinotify_settings'] = array();

	function apply_filters( $hook, $value ) { return $value; }
	function do_action() {}
	function add_filter() {}
	function add_action() {}
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
	function sanitize_text_field( $value ) { return trim( (string) $value ); }
	function wp_kses_post( $value ) { return (string) $value; }
	function esc_url_raw( $value ) { return (string) $value; }
	function get_option( $key, $default = false ) { return $default; }
	function update_option() { return true; }
	function wp_next_scheduled() { return true; }
	function wp_schedule_event() {}
	function wp_schedule_single_event() {}
	function get_transient() { return false; }
	function set_transient() {}
	function wp_doing_cron() { return false; }
	function wp_doing_ajax() { return false; }
	function __( $text ) { return $text; }
}

namespace MeuMouse\Joinotify\Admin {
	class Admin {
		public static function get_setting( $key ) {
			return $GLOBALS['joinotify_settings'][ $key ] ?? '';
		}
	}
}

namespace MeuMouse\Joinotify\Api {
	class Transport {}
}

namespace MeuMouse\Joinotify\Core {
	class Message_History {
		public static function set_context() {}
		public static function clear_context() {}
		public static function resolve_queue_item() { return true; }
	}
}

namespace {
	require __DIR__ . '/../admin/src/Core/Notification_Queue.php';

	use MeuMouse\Joinotify\Core\Notification_Queue;

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

	echo "== defaults ==\n";

	check( 'five attempts', 5 === Notification_Queue::get_max_attempts() );
	check( 'first retry after 30 minutes', 30 === Notification_Queue::get_first_delay_minutes() );
	check(
		'the wait doubles every attempt: 30, 60, 120, 240, 480',
		array( 30, 60, 120, 240, 480 ) === Notification_Queue::get_retry_schedule_minutes()
	);

	echo "\n== configured ==\n";

	$GLOBALS['joinotify_settings'] = array(
		'message_retry_max_attempts' => '3',
		'message_retry_first_delay_minutes' => '60',
	);

	check( 'the stored budget wins over the default', 3 === Notification_Queue::get_max_attempts() );
	check(
		'the stored wait anchors the doubling',
		array( 60, 120, 240 ) === Notification_Queue::get_retry_schedule_minutes()
	);

	echo "\n== retries turned off ==\n";

	$GLOBALS['joinotify_settings'] = array( 'message_retry_max_attempts' => '0' );

	check( 'no schedule at all', array() === Notification_Queue::get_retry_schedule_minutes() );
	// A budget of zero has to stop the message from entering the queue, not park it
	// forever: an item nothing will ever attempt is a leak, not a retry.
	check(
		'nothing is enqueued',
		false === Notification_Queue::enqueue( 'text', array( 'sender' => '5541999999999', 'receiver' => '5541988888888', 'message' => 'hi' ) )
	);

	echo "\n== clamps ==\n";

	$GLOBALS['joinotify_settings'] = array(
		'message_retry_max_attempts' => '-4',
		'message_retry_first_delay_minutes' => '0',
	);

	check( 'a negative budget becomes zero', 0 === Notification_Queue::get_max_attempts() );
	check( 'a zero wait becomes one minute', 1 === Notification_Queue::get_first_delay_minutes() );

	$GLOBALS['joinotify_settings'] = array(
		'message_retry_max_attempts' => '5000',
		'message_retry_first_delay_minutes' => '99999',
	);

	check( 'an absurd budget is capped', 100 === Notification_Queue::get_max_attempts() );
	check( 'an absurd wait is capped at a day', 1440 === Notification_Queue::get_first_delay_minutes() );

	// Non-numeric junk falls back to the defaults rather than to zero, which would
	// silently disable retries on a site that never touched the setting.
	$GLOBALS['joinotify_settings'] = array( 'message_retry_max_attempts' => 'yes' );

	check( 'junk falls back to the default budget', 5 === Notification_Queue::get_max_attempts() );

	echo "\n";
	echo $failures > 0
		? "FAILED — {$failures} of {$assertions} assertions\n"
		: "OK — all {$assertions} assertions passed\n";

	exit( $failures > 0 ? 1 : 0 );
}
