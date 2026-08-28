<?php
/**
 * Standalone test harness for the Cloud API sender model.
 *
 * Covers the per-sender metadata added to Phone_Manager (which is what unlocks
 * multi-number sending through Cloud_Client::resolve_phone_number_id) and the
 * Sender_Sync import: walking every business account, normalizing both response
 * dialects (Meta mirror snake_case and the simplified camelCase schema), caching
 * only successful non-empty listings, and pruning metadata of dropped numbers.
 * No WordPress bootstrap is required — options, transients and the Cloud client
 * are stubbed below so the assertions stay on the sync logic itself.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/sender-sync-test.php
 *
 * @since 2.3.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );
define( 'JOINOTIFY_PANEL_URL', 'https://app.joinotify.com' );

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

$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['actions'] = array();

function get_option( $key, $default = false ) { global $options; return array_key_exists( $key, $options ) ? $options[ $key ] : $default; }
function update_option( $key, $value ) { global $options; $options[ $key ] = $value; return true; }
function get_transient( $key ) { global $transients; return array_key_exists( $key, $transients ) ? $transients[ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { global $transients; $transients[ $key ] = $value; return true; }
function delete_transient( $key ) { global $transients; unset( $transients[ $key ] ); return true; }
function do_action( $hook ) { global $actions; $actions[] = $hook; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function __( $text, $domain = 'default' ) { return $text; }

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

}

// ---------------------------------------------------------------------------
// Namespaced collaborator fakes (must exist before the real classes load)
// ---------------------------------------------------------------------------

namespace MeuMouse\Joinotify\Core {
	class Helpers {
		public static $ready = true;
		public static $waba_id = '';

		public static function cloud_api_ready() { return self::$ready; }
		public static function cloud_waba_id() { return self::$waba_id; }
	}
}

namespace MeuMouse\Joinotify\Api {
	/**
	 * Records the requested business accounts and answers from a scripted map.
	 */
	class Cloud_Client {
		public static $wabas = array();
		public static $numbers_by_waba = array();
		public static $requested = array();

		/**
		 * Scripted answer for the mirror endpoint. A WP_Error (the default)
		 * makes Sender_Sync fall through to walking the business accounts,
		 * which is the path the bulk of this harness exercises.
		 */
		public static $senders = null;

		public static function list_senders() {
			if ( null === self::$senders ) {
				return new \WP_Error( 'unavailable', 'The mirror endpoint is not available.' );
			}

			return self::$senders;
		}

		public static function list_wabas() { return self::$wabas; }

		public static function list_numbers( $waba_id = '' ) {
			self::$requested[] = $waba_id;

			return self::$numbers_by_waba[ $waba_id ] ?? new \WP_Error( 'not_found', 'Unknown business account.' );
		}
	}
}

namespace {

require_once __DIR__ . '/../admin/src/Core/Phone_Manager.php';
require_once __DIR__ . '/../admin/src/Api/Sender_Sync.php';

use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Api\Sender_Sync;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Phone_Manager;

/**
 * Reset every stubbed store between scenarios.
 */
function reset_state() {
	$GLOBALS['options'] = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['actions'] = array();

	Cloud_Client::$wabas = array();
	Cloud_Client::$numbers_by_waba = array();
	Cloud_Client::$requested = array();
	Cloud_Client::$senders = null;

	Helpers::$ready = true;
	Helpers::$waba_id = '';
}

echo "\nPhone_Manager metadata\n";
reset_state();

Phone_Manager::add_sender( '5541987111527', array(
	'phone_number_id' => '106540352242922',
	'waba_id' => 'waba_1',
	'quality_rating' => 'GREEN',
	'verified' => 1,
	'unknown_key' => 'dropped',
) );

check( 'get_senders() still returns plain phone strings', array( '5541987111527' ) === Phone_Manager::get_senders() );
check( 'get_phone_number_id() resolves the stored id', '106540352242922' === Phone_Manager::get_phone_number_id( '5541987111527' ) );
check( 'get_waba_id() resolves the stored business account', 'waba_1' === Phone_Manager::get_waba_id( '5541987111527' ) );
check( 'boolean metadata is cast', true === Phone_Manager::get_sender_meta( '5541987111527' )['verified'] );
check( 'unknown metadata keys are discarded', ! array_key_exists( 'unknown_key', Phone_Manager::get_sender_meta( '5541987111527' ) ) );
check( 'get_phone_number_id() is empty for an unknown sender', '' === Phone_Manager::get_phone_number_id( '5511999999999' ) );

Phone_Manager::set_sender_meta( '5541987111527', array( 'quality_rating' => 'YELLOW' ) );
$meta = Phone_Manager::get_sender_meta( '5541987111527' );
check( 'set_sender_meta() merges instead of replacing', 'YELLOW' === $meta['quality_rating'] && '106540352242922' === $meta['phone_number_id'] );

Phone_Manager::add_sender( '5511988887777', array( 'waba_id' => 'waba_2' ) );
check( 'get_known_waba_ids() collects every distinct account', array( 'waba_1', 'waba_2' ) === Phone_Manager::get_known_waba_ids() );

Phone_Manager::remove_sender( '5511988887777' );
check( 'remove_sender() drops the metadata too', array() === Phone_Manager::get_sender_meta( '5511988887777' ) );

echo "\nPhone_Manager::set_senders()\n";
reset_state();

Phone_Manager::set_senders( array(
	array( 'phone' => '+55 (41) 98711-1527', 'phone_number_id' => 'id_1', 'waba_id' => 'waba_1' ),
	array( 'phone' => '5511988887777', 'phone_number_id' => 'id_2', 'waba_id' => 'waba_1' ),
	array( 'phone' => '5511988887777', 'phone_number_id' => 'duplicate' ),
	'',
) );

check( 'phones are sanitized to digits and de-duplicated', array( '5541987111527', '5511988887777' ) === Phone_Manager::get_senders() );
check( 'the first entry wins on duplicates', 'id_2' === Phone_Manager::get_phone_number_id( '5511988887777' ) );

Phone_Manager::set_senders( array( array( 'phone' => '5541987111527', 'phone_number_id' => 'id_1' ) ) );
check( 'metadata of removed numbers is pruned', array() === Phone_Manager::get_sender_meta( '5511988887777' ) );

Phone_Manager::set_senders( array( '5541987111527' ) );
check( 'metadata survives a bare phone list', 'id_1' === Phone_Manager::get_phone_number_id( '5541987111527' ) );

echo "\nSender_Sync normalization\n";
reset_state();

Cloud_Client::$wabas = array( 'waba_1' );
// Meta mirror dialect: snake_case, id, code_verification_status.
Cloud_Client::$numbers_by_waba['waba_1'] = array(
	'data' => array(
		array(
			'id' => '106540352242922',
			'display_phone_number' => '+55 41 98711-1527',
			'verified_name' => 'Loja Exemplo',
			'quality_rating' => 'GREEN',
			'messaging_limit_tier' => 'TIER_1K',
			'code_verification_status' => 'VERIFIED',
		),
	),
);

$numbers = Sender_Sync::fetch_numbers( true );

check( 'the mirror dialect is normalized', ! is_wp_error( $numbers ) && 1 === count( $numbers ) );
check( 'phone is derived from the display number', '5541987111527' === $numbers[0]['phone'] );
check( 'phone_number_id comes from the Meta id', '106540352242922' === $numbers[0]['phone_number_id'] );
check( 'waba_id is stamped from the queried account', 'waba_1' === $numbers[0]['waba_id'] );
check( 'verified_name is carried over', 'Loja Exemplo' === $numbers[0]['verified_name'] );
check( 'messaging limit is carried over', 'TIER_1K' === $numbers[0]['messaging_limit'] );
check( 'code_verification_status maps to verified', true === $numbers[0]['verified'] );

reset_state();

Cloud_Client::$wabas = array( 'waba_1' );
// Simplified /numbers dialect: camelCase PhoneNumber schema.
Cloud_Client::$numbers_by_waba['waba_1'] = array(
	'data' => array(
		array(
			'phoneNumberId' => '999',
			'displayNumber' => '+55 11 98888-7777',
			'verifiedName' => 'Outra Loja',
			'verified' => false,
			'qualityRating' => 'YELLOW',
			'messagingLimit' => 'TIER_10K',
		),
	),
);

$numbers = Sender_Sync::fetch_numbers( true );

check( 'the simplified dialect is normalized', ! is_wp_error( $numbers ) && '999' === $numbers[0]['phone_number_id'] );
check( 'verified reads the boolean field', false === $numbers[0]['verified'] );
check( 'quality rating is carried over', 'YELLOW' === $numbers[0]['quality_rating'] );

echo "\nSender_Sync across business accounts\n";
reset_state();

Cloud_Client::$wabas = array( 'waba_1', 'waba_2' );
Cloud_Client::$numbers_by_waba['waba_1'] = array( 'data' => array( array( 'id' => 'id_1', 'display_phone_number' => '5541987111527' ) ) );
Cloud_Client::$numbers_by_waba['waba_2'] = array( 'data' => array( array( 'id' => 'id_2', 'display_phone_number' => '5511988887777' ) ) );

$numbers = Sender_Sync::fetch_numbers( true );

check( 'every business account is queried', array( 'waba_1', 'waba_2' ) === Cloud_Client::$requested );
check( 'numbers from all accounts are merged', 2 === count( $numbers ) );

// One account failing must not discard the numbers of the healthy ones.
reset_state();
Cloud_Client::$wabas = array( 'waba_1', 'waba_broken' );
Cloud_Client::$numbers_by_waba['waba_1'] = array( 'data' => array( array( 'id' => 'id_1', 'display_phone_number' => '5541987111527' ) ) );

$numbers = Sender_Sync::fetch_numbers( true );
check( 'a failing account does not sink the healthy ones', ! is_wp_error( $numbers ) && 1 === count( $numbers ) );

// Every account failing must surface the error rather than an empty list.
reset_state();
Cloud_Client::$wabas = array( 'waba_broken' );
$numbers = Sender_Sync::fetch_numbers( true );
check( 'a total failure surfaces the error', is_wp_error( $numbers ) );

echo "\nSender_Sync caching and import\n";
reset_state();

Cloud_Client::$wabas = array( 'waba_1' );
Cloud_Client::$numbers_by_waba['waba_1'] = array( 'data' => array( array( 'id' => 'id_1', 'display_phone_number' => '5541987111527' ) ) );

Sender_Sync::fetch_numbers( true );
$requested_after_first = count( Cloud_Client::$requested );
Sender_Sync::fetch_numbers();
check( 'a successful listing is served from cache', $requested_after_first === count( Cloud_Client::$requested ) );

Sender_Sync::flush_cache();
Sender_Sync::fetch_numbers();
check( 'flush_cache() forces a new request', $requested_after_first < count( Cloud_Client::$requested ) );

reset_state();
Cloud_Client::$wabas = array( 'waba_broken' );
Sender_Sync::fetch_numbers( true );
check( 'failures are never cached', false === get_transient( Sender_Sync::CACHE_KEY ) );

reset_state();
Cloud_Client::$wabas = array( 'waba_1' );
Cloud_Client::$numbers_by_waba['waba_1'] = array( 'data' => array(
	array( 'id' => 'id_1', 'display_phone_number' => '5541987111527' ),
	array( 'id' => 'id_2', 'display_phone_number' => '5511988887777' ),
) );

$stored = Sender_Sync::sync();

check( 'sync() stores the imported senders', array( '5541987111527', '5511988887777' ) === $stored );
check( 'sync() maps each phone to its own phone_number_id', 'id_2' === Phone_Manager::get_phone_number_id( '5511988887777' ) );
check( 'sync() stamps the last sync time', Sender_Sync::last_sync_time() > 0 );
check( 'sync() fires the synced action', in_array( 'Joinotify/Cloud_Api/Senders_Synced', $GLOBALS['actions'], true ) );

// A number disconnected on the panel must disappear from the site.
Cloud_Client::$numbers_by_waba['waba_1'] = array( 'data' => array( array( 'id' => 'id_1', 'display_phone_number' => '5541987111527' ) ) );
Sender_Sync::sync();
check( 'sync() mirrors removals from the panel', array( '5541987111527' ) === Phone_Manager::get_senders() );

reset_state();
Helpers::$ready = false;
check( 'sync without a token reports it', is_wp_error( Sender_Sync::fetch_numbers( true ) ) );

echo "\n";
echo $failures > 0
	? "FAILED — {$failures} of {$assertions} assertions failed\n"
	: "OK — all {$assertions} assertions passed\n";

exit( $failures > 0 ? 1 : 0 );

}
