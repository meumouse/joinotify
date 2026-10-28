<?php
/**
 * Standalone test harness for the customer service window read from the platform.
 *
 * Covers Contact_Window: the phone is matched to a contact id on the exact number (the search
 * also matches partial digits), the window of one origin number is read from
 * `GET /contacts/{id}/window?from=`, answers are cached (an open window until shortly before it
 * closes, a closed one briefly), an inbound message forgets them, and every failure answers
 * `unknown` instead of a guess. No WordPress bootstrap is required.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/contact-window-test.php
 *
 * @since 2.6.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );

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

$GLOBALS['transients'] = array();

function get_transient( $key ) { global $transients; return array_key_exists( $key, $transients ) ? $transients[ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { global $transients; $transients[ $key ] = $value; return true; }
function delete_transient( $key ) { global $transients; unset( $transients[ $key ] ); return true; }
function joinotify_get_first_sender() { return '5541999990000'; }

}

namespace MeuMouse\Joinotify\Core {
	class Helpers {
		public static $ready = true;

		public static function cloud_api_ready() { return self::$ready; }
	}
}

namespace MeuMouse\Joinotify\Api {
	class Cloud_Client {
		public static $senders = array( '5541999990000' => 'pn_1', '5541988880000' => 'pn_2' );

		public static function resolve_phone_number_id( $sender ) { return self::$senders[ $sender ] ?? ''; }
	}

	/**
	 * Answers platform calls from scripted envelopes, keyed by path.
	 */
	class Cloud_Contacts {
		public static $responses = array();
		public static $calls = array();

		public static function call( $method, $path, $query = array(), $body = null, $headers = array() ) {
			self::$calls[] = array( 'path' => $path, 'query' => $query );

			return self::$responses[ $path ] ?? array( 'ok' => false, 'status' => 0, 'data' => null, 'meta' => array(), 'error' => array( 'type' => 'network_error' ) );
		}

		public static function is_id( $id ) { return is_string( $id ) && 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $id ); }
	}
}

namespace {

require_once __DIR__ . '/../admin/src/Api/Contact_Window.php';

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use MeuMouse\Joinotify\Api\Contact_Window;
use MeuMouse\Joinotify\Core\Helpers;

const NOW = 1790000000;

/**
 * Reset every stubbed store between scenarios.
 */
function reset_state() {
	$GLOBALS['transients'] = array();
	Cloud_Contacts::$responses = array();
	Cloud_Contacts::$calls = array();
	Helpers::$ready = true;
}

function ok( $data ) { return array( 'ok' => true, 'status' => 200, 'data' => $data, 'meta' => array(), 'error' => null ); }
function fail( $status ) { return array( 'ok' => false, 'status' => $status, 'data' => null, 'meta' => array(), 'error' => array( 'type' => 'x' ) ); }
function iso( $time ) { return gmdate( 'Y-m-d\TH:i:s\Z', $time ); }

echo "\nContact_Window::match_contact\n";

$contacts = array(
	array( 'id' => 'c_partial', 'phone' => '55419999911112222' ),
	array( 'id' => 'c_wa', 'phone' => '5541999991111', 'waId' => '554199991111' ),
	array( 'id' => 'bad id', 'phone' => '5541977771111' ),
);

check( 'the exact number wins over a partial match', 'c_wa' === Contact_Window::match_contact( $contacts, '5541999991111' ) );
check( 'the WhatsApp id matches too (Brazilian ninth digit)', 'c_wa' === Contact_Window::match_contact( $contacts, '554199991111' ) );
check( 'a contact with an unusable id is ignored', '' === Contact_Window::match_contact( $contacts, '5541977771111' ) );
check( 'no exact number means no contact', '' === Contact_Window::match_contact( $contacts, '5541999' ) );
check( 'a non-list is tolerated', '' === Contact_Window::match_contact( 'nope', '5541999991111' ) );

echo "\nContact_Window::parse_window\n";

$data = array(
	'contactId' => 'c_1',
	'open' => true,
	'expiresAt' => iso( NOW + 7200 ),
	'remainingSeconds' => 7200,
	'windows' => array(
		array( 'phoneNumberId' => 'pn_2', 'open' => true, 'expiresAt' => iso( NOW + 7200 ), 'remainingSeconds' => 7200, 'lastInboundAt' => iso( NOW - 3600 ) ),
		array( 'phoneNumberId' => 'pn_1', 'open' => false, 'expiresAt' => iso( NOW - 60 ), 'remainingSeconds' => 0, 'lastInboundAt' => iso( NOW - 86460 ) ),
	),
);

$window = Contact_Window::parse_window( $data, 'pn_1', NOW );
check( 'the entry of the origin number wins over the summary', 'closed' === $window['state'] && 0 === $window['remaining_seconds'] );

$window = Contact_Window::parse_window( $data, 'pn_2', NOW );
check( 'an open window says when it closes', 'open' === $window['state'] && 7200 === $window['remaining_seconds'] && iso( NOW + 7200 ) === gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $window['expires_at'] ) ) );

$window = Contact_Window::parse_window( array( 'open' => true, 'expiresAt' => iso( NOW - 1 ), 'windows' => array() ), 'pn_9', NOW );
check( 'an open flag past its expiry reads as closed', 'closed' === $window['state'] );

$window = Contact_Window::parse_window( array( 'open' => false, 'expiresAt' => null, 'windows' => array() ), 'pn_1', NOW );
check( 'a contact who never wrote is closed with no expiry', 'closed' === $window['state'] && '' === $window['expires_at'] );

check( 'an answer without `open` is unknown', 'unknown' === Contact_Window::parse_window( array( 'windows' => array() ), 'pn_1', NOW )['state'] );
check( 'a non-array answer is unknown', 'unknown' === Contact_Window::parse_window( null, 'pn_1', NOW )['state'] );

echo "\nContact_Window::get\n";
reset_state();

Cloud_Contacts::$responses['/contacts'] = ok( array( array( 'id' => 'c_1', 'phone' => '5541999991111' ) ) );
Cloud_Contacts::$responses['/contacts/c_1/window'] = ok( array( 'open' => true, 'expiresAt' => iso( NOW + 7200 ), 'windows' => array( array( 'phoneNumberId' => 'pn_1', 'open' => true, 'expiresAt' => iso( NOW + 7200 ) ) ) ) );

$window = Contact_Window::get( '+55 (41) 99999-1111', '', NOW );
check( 'an open window is read for the first sender', 'open' === $window['state'] && 7200 === $window['remaining_seconds'] );
check( 'the window is asked for the origin number', 'pn_1' === Cloud_Contacts::$calls[1]['query']['from'] );
check( 'the phone is searched by its digits', '5541999991111' === Cloud_Contacts::$calls[0]['query']['q'] );

$calls = count( Cloud_Contacts::$calls );
$window = Contact_Window::get( '5541999991111', '', NOW + 600 );
check( 'an open answer is served from the cache', $calls === count( Cloud_Contacts::$calls ) && 6600 === $window['remaining_seconds'] );

Contact_Window::get( '5541999991111', '', NOW + 7200 - 30 );
check( 'an open answer stops being cached just before it closes', $calls + 1 === count( Cloud_Contacts::$calls ) );
check( 'the contact id is not looked up again', '/contacts/c_1/window' === end( Cloud_Contacts::$calls )['path'] );

Contact_Window::forget( '5541999991111' );
$calls = count( Cloud_Contacts::$calls );
Contact_Window::get( '5541999991111', '', NOW + 100 );
check( 'an inbound message forgets the cached answer', $calls + 1 === count( Cloud_Contacts::$calls ) );

$window = Contact_Window::get( '5541999991111', '5541988880000', NOW + 100 );
check( 'another origin number is asked on its own', 'pn_2' === end( Cloud_Contacts::$calls )['query']['from'] );
check( 'the entry missing for that number falls back to the summary', 'open' === $window['state'] );

echo "\nContact_Window::get closed and failures\n";
reset_state();

Cloud_Contacts::$responses['/contacts'] = ok( array() );
check( 'a number missing from the base is closed', 'closed' === Contact_Window::get( '5541999991111', '', NOW )['state'] );
check( 'a missing contact id is not kept', false === get_transient( Contact_Window::CONTACT_PREFIX . md5( '5541999991111' ) ) );

$calls = count( Cloud_Contacts::$calls );
Contact_Window::get( '5541999991111', '', NOW + 60 );
check( 'a closed answer is served from the cache for a short while', $calls === count( Cloud_Contacts::$calls ) );
Contact_Window::get( '5541999991111', '', NOW + Contact_Window::CLOSED_TTL + 1 );
check( 'a closed answer is asked again after it', $calls + 1 === count( Cloud_Contacts::$calls ) );

reset_state();
Cloud_Contacts::$responses['/contacts'] = ok( array( array( 'id' => 'c_1', 'phone' => '5541999991111' ) ) );
Cloud_Contacts::$responses['/contacts/c_1/window'] = fail( 404 );
check( 'a contact gone from the platform is closed', 'closed' === Contact_Window::get( '5541999991111', '', NOW )['state'] );
check( 'and its id is forgotten', false === get_transient( Contact_Window::CONTACT_PREFIX . md5( '5541999991111' ) ) );

foreach ( array( 0, 401, 402, 429, 500 ) as $status ) {
	reset_state();
	Cloud_Contacts::$responses['/contacts'] = ok( array( array( 'id' => 'c_1', 'phone' => '5541999991111' ) ) );
	Cloud_Contacts::$responses['/contacts/c_1/window'] = fail( $status );
	$window = Contact_Window::get( '5541999991111', '', NOW );
	check( "a window answered with {$status} is unknown and not cached", 'unknown' === $window['state'] && false === get_transient( Contact_Window::STATE_PREFIX . md5( '5541999991111' ) ) );
}

reset_state();
Cloud_Contacts::$responses['/contacts'] = fail( 429 );
check( 'a failed lookup is unknown', 'unknown' === Contact_Window::get( '5541999991111', '', NOW )['state'] );

reset_state();
Helpers::$ready = false;
check( 'a site not connected is unknown without a call', 'unknown' === Contact_Window::get( '5541999991111', '', NOW )['state'] && array() === Cloud_Contacts::$calls );

reset_state();
check( 'a sender without a phone_number_id is unknown', 'unknown' === Contact_Window::get( '5541999991111', '5500000000000', NOW )['state'] );
check( 'a short phone is unknown', 'unknown' === Contact_Window::get( '1234', '', NOW )['state'] && array() === Cloud_Contacts::$calls );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions failed\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
