<?php
/**
 * Standalone test harness for the telemetry listeners.
 *
 * The other telemetry harnesses cover pure functions in isolation. This one covers the
 * seam where the plugin's own hooks meet them: a Channel_Result becomes an event, a queue
 * item becomes an event, a trigger becomes an event — and, just as importantly, some of
 * them become nothing at all.
 *
 * The telemetry classes are the real ones; only the collaborators are faked, so the
 * assertions are about what actually lands in the buffer.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/telemetry-listeners-test.php
 *
 * @since 2.5.0
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'JOINOTIFY_VERSION', '2.5.0' );

	$GLOBALS['options'] = array();
	$GLOBALS['hooks'] = array();

	function get_option( $key, $default = false ) {
		global $options;

		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	function update_option( $key, $value ) {
		global $options;
		$options[ $key ] = $value;

		return true;
	}

	function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
		global $options;
		$options[ $key ] = $value;

		return true;
	}

	function delete_option( $key ) {
		global $options;
		unset( $options[ $key ] );

		return true;
	}

	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		global $hooks;
		$hooks[] = $hook;
	}

	function apply_filters( $hook, $value ) {
		return $value;
	}

	function wp_rand( $min = 0, $max = 0 ) {
		return $min;
	}

	function wp_generate_uuid4() {
		return '11111111-2222-4333-8444-555555555555';
	}
}

namespace MeuMouse\Joinotify\Core {
	class Telemetry {
		const SETTING = 'enable_usage_tracking';
		public static $enabled = true;

		public static function is_enabled() {
			return self::$enabled;
		}
	}

	class Upgrader {
		const FRESH_INSTALL_VERSION = '0.0.0';
	}
}

namespace MeuMouse\Joinotify\Api {
	class Transport {
		public static $cloud = true;

		public static function is_cloud() {
			return self::$cloud;
		}
	}
}

namespace MeuMouse\Joinotify\Builder {
	class Triggers {
		public static $names = array();

		public static function get_trigger_names( $integration ) {
			return isset( self::$names[ $integration ] ) ? self::$names[ $integration ] : array();
		}
	}
}

namespace MeuMouse\Joinotify\Integrations {
	class Integrations_Base {
		public static $items = array();

		public static function integration_tab_items() {
			return self::$items;
		}
	}
}

namespace MeuMouse\Joinotify\Telemetry {
	// Faked so the tests never touch the network or the scheduler.
	class Dispatcher {
		const HOOK = 'joinotify_telemetry_dispatch_event';
		public static $calls = array();

		public static function schedule_next( $seconds ) {
			self::$calls[] = 'schedule_next';
		}

		public static function unschedule() {
			self::$calls[] = 'unschedule';
		}

		public static function dispatch( $opt_out_notice = false ) {
			self::$calls[] = $opt_out_notice ? 'opt_out_notice' : 'dispatch';

			return true;
		}

		public static function resume() {
			self::$calls[] = 'resume';
		}

		public static function ensure_scheduled() {
			self::$calls[] = 'ensure_scheduled';
		}

		public static function maybe_dispatch_late() {
			self::$calls[] = 'maybe_dispatch_late';
		}
	}
}

namespace {
	require __DIR__ . '/../admin/src/Telemetry/Normalizer.php';
	require __DIR__ . '/../admin/src/Telemetry/Event_Catalog.php';
	require __DIR__ . '/../admin/src/Telemetry/Ulid.php';
	require __DIR__ . '/../admin/src/Telemetry/Buffer.php';
	require __DIR__ . '/../admin/src/Telemetry/Policy.php';
	require __DIR__ . '/../admin/src/Telemetry/Recorder.php';
	require __DIR__ . '/../admin/src/Telemetry/Listeners.php';

	use MeuMouse\Joinotify\Api\Transport;
	use MeuMouse\Joinotify\Builder\Triggers;
	use MeuMouse\Joinotify\Core\Telemetry;
	use MeuMouse\Joinotify\Integrations\Integrations_Base;
	use MeuMouse\Joinotify\Telemetry\Buffer;
	use MeuMouse\Joinotify\Telemetry\Dispatcher;
	use MeuMouse\Joinotify\Telemetry\Listeners;
	use MeuMouse\Joinotify\Telemetry\Recorder;

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
	 * Flush the recorder and return the events now sitting in the buffer.
	 */
	function recorded() {
		Recorder::flush();
		$buffer = Buffer::load();

		return $buffer['events'];
	}

	/**
	 * Start each scenario from an empty buffer.
	 */
	function reset_buffer() {
		global $options;
		$options = array();
		Recorder::discard();
		Recorder::refresh();
	}

	/** Stand-in for Channel_Result. */
	class Fake_Result {
		public $success = true;
		public $error = '';
		public $response_code = 200;

		public function __construct( $success, $error = '', $response_code = 200 ) {
			$this->success = $success;
			$this->error = $error;
			$this->response_code = $response_code;
		}

		public function is_success() {
			return $this->success;
		}
	}

	/** Stand-in for Notification_Message. */
	class Fake_Message {
		public $type = 'text';
		public $delay = 0;
		public $context = array();

		public function __construct( $type = 'text', $delay = 0, $context = array() ) {
			$this->type = $type;
			$this->delay = $delay;
			$this->context = $context;
		}
	}

	/** Stand-in for a Channel_Interface implementation. */
	class Fake_Channel {
		private $id;

		public function __construct( $id ) {
			$this->id = $id;
		}

		public function get_id() {
			return $this->id;
		}
	}

	Integrations_Base::$items = array( 'woocommerce' => array(), 'wpforms' => array() );
	Triggers::$names = array( 'woocommerce' => array( 'woocommerce_new_order', 'woocommerce_order_status_completed' ) );

	echo "== the cost of being switched off ==\n";

	Telemetry::$enabled = false;
	$GLOBALS['hooks'] = array();
	new Listeners();

	// On a site that never agreed — the shipped default — the module must cost a string
	// comparison, not a page full of listeners.
	check( 'only the consent listener is registered', array( 'Joinotify/Settings/Saved' ) === $GLOBALS['hooks'] );

	Telemetry::$enabled = true;
	$GLOBALS['hooks'] = array();
	new Listeners();
	check( 'with consent, the delivery hook is registered', in_array( 'Joinotify/Notifications/Message_Sent', $GLOBALS['hooks'], true ) );
	check( 'and so is the error hook', in_array( 'Joinotify/Debug_Log/Recorded', $GLOBALS['hooks'], true ) );

	echo "\n== message.sent ==\n";

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( true ), new Fake_Message('template'), new Fake_Channel('whatsapp_cloud') );
	$events = recorded();

	check( 'a successful delivery is recorded', 1 === count( $events ) && 'message.sent' === $events[0]['name'] );
	check( 'with the transport mapped from the channel id', 'cloud' === $events[0]['props']['transport'] );
	check( 'and the type carried through', 'template' === $events[0]['props']['type'] );

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( true ), new Fake_Message('audio'), new Fake_Channel('whatsapp') );
	$events = recorded();

	check( 'the legacy channel maps to evolution', 'evolution' === $events[0]['props']['transport'] );
	// Audio is media everywhere that matters, and a value the service does not know would
	// be stripped on arrival.
	check( 'audio is folded into media', 'media' === $events[0]['props']['type'] );

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( true ), new Fake_Message( 'text', 5000 ), new Fake_Channel('whatsapp_cloud') );
	check( 'a delayed message is marked scheduled', true === recorded()[0]['props']['scheduled'] );

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( true ), new Fake_Message( 'text', 0, array( 'source' => 'queue' ) ), new Fake_Channel('whatsapp_cloud') );
	check( 'so is one coming out of the retry queue', true === recorded()[0]['props']['scheduled'] );

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( true ), new Fake_Message( 'text', 0, array( 'source' => 'test' ) ), new Fake_Channel('whatsapp_cloud') );
	check( 'a test send is typed as a test', 'test' === recorded()[0]['props']['type'] );

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( true ), new Fake_Message('text'), new Fake_Channel('telegram') );
	// Half an event — a delivery with no transport — would sit next to the WhatsApp ones
	// and quietly skew them.
	check( 'a channel the enum cannot express records nothing', 0 === count( recorded() ) );

	echo "\n== message.failed ==\n";

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( false, 'template_error_999999', 400 ), new Fake_Message('template'), new Fake_Channel('whatsapp_cloud') );
	$props = recorded()[0]['props'];

	check( 'a failure is recorded as such', 'message.failed' === recorded()[0]['name'] );
	check( 'the error code is collapsed into a closed bucket', 'template_error_other' === $props['code'] );
	check( 'the HTTP status rides along', 400 === $props['httpStatus'] );

	reset_buffer();
	Listeners::on_message_sent( new Fake_Result( false, 'window_closed_requires_template', 0 ), new Fake_Message('text'), new Fake_Channel('whatsapp_cloud') );
	$props = recorded()[0]['props'];

	check( 'the common production error keeps its own bucket', 'window_closed_requires_template' === $props['code'] );
	// Zero is not a status; leaving it out says "no HTTP answer", which is what happened.
	check( 'a transport failure omits the status instead of sending zero', ! array_key_exists( 'httpStatus', $props ) );

	echo "\n== queue.retried ==\n";

	reset_buffer();
	Listeners::on_queue_retried( array( 'attempts' => 3, 'last_error' => 'http_503' ) );
	$props = recorded()[0]['props'];

	check( 'an early retry is recorded', 3 === $props['attempt'] && 'http_503' === $props['reason'] );

	reset_buffer();
	Listeners::on_queue_retried( array( 'attempts' => 7, 'last_error' => 'http_503' ) );
	// The queue allows 120 attempts. A stuck item reporting all of them would drown
	// everything else in the buffer.
	check( 'a mid-run attempt is skipped', 0 === count( recorded() ) );

	reset_buffer();
	Listeners::on_queue_retried( array( 'attempts' => 64, 'last_error' => 'http_503' ) );
	check( 'a power of two still reports, so a stuck item stays visible', 1 === count( recorded() ) );

	echo "\n== feature.used ==\n";

	reset_buffer();
	Listeners::on_workflows_processed( 'woocommerce_new_order', array( 'integration' => 'woocommerce' ), array( 'a workflow' ) );
	$props = recorded()[0]['props'];

	check( 'a matched trigger is recorded', 'woocommerce' === $props['feature'] && 'woocommerce_new_order' === $props['trigger'] );
	check( 'with the active transport', 'cloud' === $props['transport'] );

	reset_buffer();
	// Every integration calls the processor on every one of its hooks. Without this guard
	// the event would measure the site's traffic, not the plugin's use.
	Listeners::on_workflows_processed( 'woocommerce_new_order', array( 'integration' => 'woocommerce' ), array() );
	check( 'a trigger that matched no workflow records nothing', 0 === count( recorded() ) );

	reset_buffer();
	Listeners::on_workflows_processed( 'some_third_party_hook', array( 'integration' => 'acme_plugin' ), array( 'a workflow' ) );
	$props = recorded()[0]['props'];

	// A third-party integration registered through the extension API would otherwise put
	// an unbounded set of slugs into a counter name on the service.
	check( 'an unknown integration collapses to custom', 'custom' === $props['feature'] );
	check( 'and so does its hook', 'custom' === $props['trigger'] );

	reset_buffer();
	Transport::$cloud = false;
	Listeners::on_workflows_processed( 'woocommerce_new_order', array( 'integration' => 'woocommerce' ), array( 'a workflow' ) );
	check( 'the legacy transport is reported as evolution', 'evolution' === recorded()[0]['props']['transport'] );
	Transport::$cloud = true;

	echo "\n== plugin.error ==\n";

	reset_buffer();
	Listeners::on_error_recorded( array(
		'code' => 'http_418',
		'channel' => 'api',
		'file' => '/var/www/site/admin/src/Api/Cloud_Client.php',
		'line' => 412,
		'response_code' => 418,
	), 'error' );
	$props = recorded()[0]['props'];

	check( 'the code is bucketed', 'http_other' === $props['code'] );
	check( 'the channel becomes the context', 'api' === $props['context'] );
	check( 'the fingerprint is short and present', 10 === strlen( $props['fingerprint'] ) );
	check( 'the status rides along', 418 === $props['httpStatus'] );

	reset_buffer();
	Listeners::on_error_recorded( array( 'code' => '', 'channel' => '' ), 'critical' );
	$props = recorded()[0]['props'];

	check( 'an entry with nothing useful still records something bucketed', 'other' === $props['code'] );
	check( 'and falls back to a general context', 'general' === $props['context'] );

	echo "\n== settings.changed ==\n";

	reset_buffer();
	Listeners::on_settings_saved(
		array( 'whatsapp_transport' => 'cloud', 'enable_debug_mode' => 'no', 'enable_usage_tracking' => 'yes' ),
		array( 'whatsapp_transport' => 'auto', 'enable_debug_mode' => 'no', 'enable_usage_tracking' => 'yes' )
	);
	$events = recorded();

	check( 'only the key that changed is recorded', 1 === count( $events ) && 'whatsapp_transport' === $events[0]['props']['setting'] );
	// A value could be a token, a phone number or a message template, and no version of
	// this event wants any of them.
	check( 'the value is never carried', 1 === count( $events[0]['props'] ) );

	echo "\n== consent transitions ==\n";

	reset_buffer();
	Dispatcher::$calls = array();
	Telemetry::$enabled = true;
	Listeners::on_settings_saved(
		array( 'enable_usage_tracking' => 'yes' ),
		array( 'enable_usage_tracking' => 'no' )
	);

	check( 'switching on schedules the first report', in_array( 'schedule_next', Dispatcher::$calls, true ) );
	check( 'and does not send anything right away', ! in_array( 'dispatch', Dispatcher::$calls, true ) );

	reset_buffer();
	Dispatcher::$calls = array();
	$GLOBALS['options'][ Buffer::OPTION ] = array( 'v' => 1, 'events' => array( array( 'id' => 'x', 'name' => 'plugin.activated' ) ), 'seen' => array(), 'dropped' => 0 );

	Listeners::on_settings_saved(
		array( 'enable_usage_tracking' => 'no' ),
		array( 'enable_usage_tracking' => 'yes' )
	);

	// Without this last request the installation stays alive in the service's counts
	// forever, and "off" would only mean "no new data".
	check( 'switching off tells the service to stop counting', in_array( 'opt_out_notice', Dispatcher::$calls, true ) );
	check( 'and clears the schedule', in_array( 'unschedule', Dispatcher::$calls, true ) );
	check( 'and throws away what was still queued', ! isset( $GLOBALS['options'][ Buffer::OPTION ] ) );

	echo "\n";
	echo $failures > 0
		? "FAILED — {$failures} of {$assertions} assertions\n"
		: "OK — all {$assertions} assertions passed\n";

	exit( $failures > 0 ? 1 : 0 );
}
