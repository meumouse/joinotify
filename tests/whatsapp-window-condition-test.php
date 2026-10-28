<?php
/**
 * Standalone test harness for the WhatsApp 24-hour window condition.
 *
 * Covers the two halves of the `whatsapp_window` condition: the WhatsApp
 * integration offering it on every registered trigger (dropping the "nothing
 * to check" placeholder and keeping the conditions other integrations set),
 * and Validations\Conditions reading the window of the recipient the condition
 * names — resolved like a message recipient, on the chosen sender, only when
 * that condition is the one being evaluated. No WordPress bootstrap is required.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/whatsapp-window-condition-test.php
 *
 * @since 2.6.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_DEV_MODE', false );

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
// WordPress / Joinotify stubs
// ---------------------------------------------------------------------------

function apply_filters( $hook, $value ) { return $value; }
function add_filter() { return true; }
function __( $text, $domain = 'default' ) { return $text; }
function get_userdata( $user_id ) { return null; }
function get_current_user_id() { return 0; }
function get_post_type( $post ) { return ''; }
function get_post_meta( $id, $key, $single = false ) { return ''; }

// A placeholder resolves to the phone the payload carries.
function joinotify_prepare_receiver( $receiver, $payload = array() ) {
	$receiver = str_replace( '{{ wc_billing_phone }}', (string) ( $payload['phone'] ?? '' ), (string) $receiver );

	return preg_replace( '/\D+/', '', $receiver );
}

$GLOBALS['window_calls'] = array();
$GLOBALS['window_answer'] = array( 'state' => 'open', 'expires_at' => '', 'remaining_seconds' => 3600 );

function joinotify_get_whatsapp_window( string $phone, string $sender = '' ) {
	$GLOBALS['window_calls'][] = array( $phone, $sender );

	return $GLOBALS['window_answer'];
}

} // end global namespace

namespace MeuMouse\Joinotify\Core {
	class Helpers {}
	class Logger { public static function register_log( $m, $l = 'INFO' ) {} }
}

namespace MeuMouse\Joinotify\Admin {
	class Admin { public static function get_setting( $key ) { return 'yes'; } }
}

namespace MeuMouse\Joinotify\Builder {
	class Triggers {
		public static function get_all_triggers() {
			return array(
				'woocommerce' => array(
					array( 'data_trigger' => 'woocommerce_order_status_completed' ),
					array( 'data_trigger' => 'woocommerce_new_order' ),
				),
				'wordpress' => array(
					array( 'data_trigger' => 'user_register' ),
					array( 'title' => 'broken, no slug' ),
				),
			);
		}
	}
}

namespace MeuMouse\Joinotify\Integrations {
	abstract class Integrations_Base {}
}

namespace {

require_once __DIR__ . '/../admin/src/Validations/Conditions.php';
require_once __DIR__ . '/../admin/src/Integrations/Whatsapp.php';

use MeuMouse\Joinotify\Integrations\Whatsapp;
use MeuMouse\Joinotify\Validations\Conditions;

echo "\nWhatsapp::add_window_condition\n";

$integration = ( new ReflectionClass( Whatsapp::class ) )->newInstanceWithoutConstructor();

$conditions = $integration->add_window_condition( array(
	'woocommerce_order_status_completed' => array(
		'order_total' => array( 'title' => 'Order total' ),
	),
	'user_register' => array(
		'no_action' => array( 'title' => 'No condition available for this action' ),
	),
) );

check( 'every trigger gets the window condition', isset(
	$conditions['woocommerce_order_status_completed']['whatsapp_window'],
	$conditions['woocommerce_new_order']['whatsapp_window'],
	$conditions['user_register']['whatsapp_window']
) );
check( 'conditions of other integrations are kept', isset( $conditions['woocommerce_order_status_completed']['order_total'] ) );
check( 'the "nothing to check" placeholder is dropped', ! isset( $conditions['user_register']['no_action'] ) );
check( 'a trigger without a slug is skipped', ! isset( $conditions[''] ) );
check( 'the condition has a title', 'WhatsApp 24-hour window' === $conditions['woocommerce_new_order']['whatsapp_window']['title'] );
check( 'a non-array map is tolerated', isset( $integration->add_window_condition( null )['user_register']['whatsapp_window'] ) );

echo "\nConditions whatsapp_window\n";

check( 'the condition compares with is and is_not', array( 'is', 'is_not' ) === Conditions::check_condition_type( 'whatsapp_window' ) );

$payload = array(
	'phone' => '+55 (41) 98711-1527',
	'condition_content' => array(
		'condition' => 'whatsapp_window',
		'type' => 'is',
		'value' => 'open',
		'receiver' => '{{ wc_billing_phone }}',
		'sender' => '+55 41 3000-0000',
	),
);

$state = Conditions::get_compare_value( 'whatsapp_window', $payload );

check( 'the window state is the compare value', 'open' === $state );
check( 'the recipient is resolved from the payload', '5541987111527' === $GLOBALS['window_calls'][0][0] );
check( 'the sender goes as digits', '554130000000' === $GLOBALS['window_calls'][0][1] );
check( 'an open window matches "is open"', Conditions::check_condition( 'is', $state, 'open', $payload ) );
check( 'an open window does not match "is not open"', ! Conditions::check_condition( 'is_not', $state, 'open', $payload ) );

$GLOBALS['window_answer'] = array( 'state' => 'closed', 'expires_at' => '', 'remaining_seconds' => 0 );
check( 'a closed window is reported', 'closed' === Conditions::get_compare_value( 'whatsapp_window', $payload ) );

$GLOBALS['window_calls'] = array();
$payload['condition_content']['receiver'] = '';
check( 'a recipient that resolves to nothing is unknown', 'unknown' === Conditions::get_compare_value( 'whatsapp_window', $payload ) );
check( 'the platform is not asked without a recipient', array() === $GLOBALS['window_calls'] );

$GLOBALS['window_answer'] = null;
$payload['condition_content']['receiver'] = '5541987111527';
check( 'a malformed answer is unknown', 'unknown' === Conditions::get_compare_value( 'whatsapp_window', $payload ) );

$GLOBALS['window_calls'] = array();
Conditions::get_compare_value( 'cart_recovered', $payload );
check( 'other conditions never ask for the window', array() === $GLOBALS['window_calls'] );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions failed\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
