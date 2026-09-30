<?php
/**
 * Standalone test harness for the pure parts of the contact base client (Api\Cloud_Contacts):
 * how a platform answer becomes the envelope the "Audiences & Contacts" screen reads, the
 * validation details, the query strings, the page window and what the capability probe decides.
 *
 * No WordPress bootstrap: the few WP functions reached are stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/cloud-contacts-client-test.php
 *
 * @since 2.5.0
 */

namespace {

define( 'ABSPATH', __DIR__ . '/' );

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

function __( $text ) { return $text; }

require __DIR__ . '/../admin/src/Api/Cloud_Contacts.php';

use MeuMouse\Joinotify\Api\Cloud_Contacts;

echo "\nCloud_Contacts::parse — success\n";

$list = Cloud_Contacts::parse( 200, '{"data":[{"id":"c1"},{"id":"c2"}],"total":42,"limit":2,"offset":4}', '', 'application/json; charset=utf-8' );
check( 'a 200 is ok', true === $list['ok'] && 200 === $list['status'] );
check( '…with the rows under data', 'c2' === $list['data'][1]['id'] );
check( '…and the list metadata under meta', array( 'total' => 42, 'limit' => 2, 'offset' => 4 ) === $list['meta'] );
check( '…and no error', null === $list['error'] );

$created = Cloud_Contacts::parse( 201, '{"data":{"id":"c9","phone":"5541987111527"}}' );
check( 'a 201 unwraps the created record', 'c9' === $created['data']['id'] && array() === $created['meta'] );

$csv = Cloud_Contacts::parse( 200, "\xEF\xBB\xBFphone,name\n5541,Ana\n", '', 'text/csv; charset=utf-8' );
check( 'a CSV answer keeps the raw body', is_string( $csv['data'] ) && false !== strpos( $csv['data'], '5541,Ana' ) );

echo "\nCloud_Contacts::parse — failures\n";

$exists = Cloud_Contacts::parse( 409, '{"error":{"type":"contact_exists","message":"Phone already in use.","contactId":"c7"}}' );
check( 'a 409 is not ok', false === $exists['ok'] && 409 === $exists['status'] );
check( '…keeps the stable type', 'contact_exists' === $exists['error']['type'] );
check( '…points at the contact that already has the phone', 'c7' === $exists['error']['contact_id'] );
check( '…and says it in our words', 'A contact with this phone number already exists.' === $exists['error']['message'] );

$limit = Cloud_Contacts::parse( 409, '{"error":{"type":"contact_limit_reached","message":"Limit.","limit":1000,"plan":"start"}}' );
check( 'the plan ceiling carries the limit', 1000 === $limit['error']['limit'] );

$invalid = Cloud_Contacts::parse( 422, '{"error":{"type":"invalid_request","message":"Invalid request payload.","issues":[{"path":["attributes","city"],"message":"Expected string"},{"field":"email","message":"Invalid email"},"junk"]}}' );
check( 'a 422 flattens array paths into a dotted field', 'attributes.city' === $invalid['error']['issues'][0]['field'] );
check( '…reads a plain field too', 'email' === $invalid['error']['issues'][1]['field'] && 'Invalid email' === $invalid['error']['issues'][1]['message'] );
check( '…and skips entries that are not objects', 2 === count( $invalid['error']['issues'] ) );
check( '…and keeps the platform message when we have none better', 'Invalid request payload.' === $invalid['error']['message'] );

$blocked = Cloud_Contacts::parse( 402, '{"error":{"type":"payment_required","reason":"past_due","action":{"kind":"update_payment_method","url":"https://app.joinotify.com/billing"}}}' );
check( 'a 402 carries the panel page that unblocks the account', 'https://app.joinotify.com/billing' === $blocked['error']['action_url'] );
check( '…and the reason', 'past_due' === $blocked['error']['reason'] );

$limited = Cloud_Contacts::parse( 429, '{"error":{"type":"rate_limit","message":"Slow down"}}', '17' );
check( 'a 429 carries the seconds to wait', 17 === $limited['error']['retry_after'] );

$html = Cloud_Contacts::parse( 502, '<html>Bad gateway</html>', '', 'text/html' );
check( 'a 5xx without a JSON body still gets a type', 'server_error' === $html['error']['type'] );
check( '…and a message naming the status', false !== strpos( $html['error']['message'], '502' ) );

check( 'a 401 without a body is an authentication failure', 'authentication' === Cloud_Contacts::parse( 401, '' )['error']['type'] );
check( 'a 404 without a body is not_found', 'not_found' === Cloud_Contacts::parse( 404, '{}' )['error']['type'] );

echo "\nCloud_Contacts::query_string / is_id / page_window\n";

check( 'empty values are dropped', 'q=ana&limit=50' === Cloud_Contacts::query_string( array( 'q' => 'ana', 'tagId' => '', 'source' => null, 'limit' => 50 ) ) );
check( 'booleans become true/false', 'upsert=true' === Cloud_Contacts::query_string( array( 'upsert' => true ) ) );
check( 'arrays never reach the query', '' === Cloud_Contacts::query_string( array( 'x' => array( 1 ) ) ) );
check( 'values are encoded', 'q=ana%20maria%26co' === Cloud_Contacts::query_string( array( 'q' => 'ana maria&co' ) ) );

check( 'a platform id is accepted', Cloud_Contacts::is_id( 'ct_01HZX-abc' ) );
check( 'a path traversal is refused', ! Cloud_Contacts::is_id( '../keys' ) );
check( 'a query injection is refused', ! Cloud_Contacts::is_id( 'c1?confirm=true' ) );
check( 'a non-string is refused', ! Cloud_Contacts::is_id( 12 ) );

check( 'page 3 of 25 skips 50', array( 'limit' => 25, 'offset' => 50 ) === Cloud_Contacts::page_window( 3, 25 ) );
check( 'the page size is capped at 200', 200 === Cloud_Contacts::page_window( 1, 5000 )['limit'] );
check( 'page 0 is page 1', 0 === Cloud_Contacts::page_window( 0, 10 )['offset'] );

$pagination = Cloud_Contacts::pagination( array( 'meta' => array( 'total' => 51 ) ), 2, 25 );
check( 'the pagination counts the pages from the total', 3 === $pagination['total_pages'] && 2 === $pagination['current_page'] && 51 === $pagination['total_items'] );
check( 'an empty list still has one page', 1 === Cloud_Contacts::pagination( array( 'meta' => array() ), 1, 25 )['total_pages'] );

echo "\nCloud_Contacts::classify_probe\n";

$never = function () {
	throw new \RuntimeException( 'The listing should not be asked for.' );
};

check( 'a base-wide read that works means full access', Cloud_Contacts::MODE_FULL === Cloud_Contacts::classify_probe( Cloud_Contacts::success( 200, array() ), $never )['mode'] );
check( 'a 401 means the key is gone', Cloud_Contacts::MODE_UNAUTHORIZED === Cloud_Contacts::classify_probe( Cloud_Contacts::parse( 401, '' ), $never )['mode'] );

$billing = Cloud_Contacts::classify_probe( $blocked, $never );
check( 'a 402 blocks the screen', Cloud_Contacts::MODE_BLOCKED === $billing['mode'] );
check( '…with the way out', 'https://app.joinotify.com/billing' === $billing['action_url'] );

$restricted = Cloud_Contacts::classify_probe( Cloud_Contacts::parse( 403, '{"error":{"type":"forbidden"}}' ), function () {
	return Cloud_Contacts::success( 200, array() );
} );
check( 'a 403 whose listing works is a key restricted to some numbers', Cloud_Contacts::MODE_READ_ONLY === $restricted['mode'] );
check( '…explained on the screen', '' !== $restricted['message'] );

$denied = Cloud_Contacts::classify_probe( Cloud_Contacts::parse( 403, '{"error":{"type":"key_permission_denied"}}' ), function () {
	return Cloud_Contacts::parse( 403, '{"error":{"type":"key_permission_denied"}}' );
} );
check( 'a 403 on the listing too is no access at all', Cloud_Contacts::MODE_FORBIDDEN === $denied['mode'] );
check( 'an outage is unreachable, not a verdict', Cloud_Contacts::MODE_UNREACHABLE === Cloud_Contacts::classify_probe( Cloud_Contacts::failure( 0, 'network_error' ), $never )['mode'] );
check( 'a 429 on the probe is unreachable too', Cloud_Contacts::MODE_UNREACHABLE === Cloud_Contacts::classify_probe( $limited, $never )['mode'] );

echo "\nCloud_Contacts::list_filters\n";

$filters = Cloud_Contacts::list_filters( array(
	'search' => '  <b>Ana</b> ',
	'tag_id' => 'tag_1',
	'opt_in_status' => 'opted_in',
	'source' => 'site',
	'audience_id' => 'aud-9',
) );
check( 'the screen filters become the platform query', array( 'q' => 'Ana', 'tagId' => 'tag_1', 'optInStatus' => 'opted_in', 'source' => 'site', 'audienceId' => 'aud-9' ) === $filters );
check( 'an unknown consent or source is dropped', array() === Cloud_Contacts::list_filters( array( 'opt_in_status' => 'maybe', 'source' => 'ftp', 'tag_id' => '../x' ) ) );

$tree = Cloud_Contacts::list_filters( array( 'filter' => array( 'op' => 'and', 'rules' => array( array( 'type' => 'consent', 'op' => 'is', 'value' => 'opted_in' ) ) ) ) );
check( 'an audience filter travels as JSON', '{"op":"and","rules":[{"type":"consent","op":"is","value":"opted_in"}]}' === $tree['filter'] );
check( 'a filter without op and rules is dropped', array() === Cloud_Contacts::list_filters( array( 'filter' => '{"x":1}' ) ) );

echo "\nCloud_Contacts::contact_payload — create\n";

$create = Cloud_Contacts::contact_payload( array(
	'phone' => ' +55 41 98711-1527 ',
	'firstName' => 'Ana',
	'lastName' => '',
	'email' => 'ana@example.com',
	'locale' => 'pt-BR',
	'defaultCountry' => 'BR',
	'attributes' => array( 'cidade' => 'Curitiba', 'vazio' => '', 'interesses' => array( 'a', '', 'b', 'a' ), 'vip' => true, 'bad key!' => 'x' ),
	'tagIds' => array( 't1', 't1', '../evil', 't2' ),
	'optIn' => array( 'evidence' => 'Checkbox on the store sign-up form' ),
	'hacker' => 'ignored',
), 'create' );
check( 'the phone is trimmed but kept as typed', '+55 41 98711-1527' === $create['phone'] );
check( 'empty values are left out on create', ! array_key_exists( 'lastName', $create ) && ! isset( $create['attributes']['vazio'] ) );
check( 'a multi-select keeps unique, non-empty items', array( 'a', 'b' ) === $create['attributes']['interesses'] );
check( 'a boolean field keeps its type', true === $create['attributes']['vip'] );
check( 'a malformed field key is dropped', ! isset( $create['attributes']['bad key!'] ) );
check( 'tag ids are unique and valid', array( 't1', 't2' ) === $create['tagIds'] );
check( 'the consent evidence travels on create', 'Checkbox on the store sign-up form' === $create['optIn']['evidence'] );
check( 'unknown keys never reach the platform', ! isset( $create['hacker'] ) );
check( 'the default country is kept', 'BR' === $create['defaultCountry'] );

check( 'a two-character evidence is not consent', ! isset( Cloud_Contacts::contact_payload( array( 'phone' => '1', 'optIn' => array( 'evidence' => 'ok' ) ) )['optIn'] ) );
check( 'a malformed locale is dropped', ! isset( Cloud_Contacts::contact_payload( array( 'locale' => 'portuguese' ) )['locale'] ) );

echo "\nCloud_Contacts::contact_payload — update\n";

$update = Cloud_Contacts::contact_payload( array(
	'firstName' => 'Ana Maria',
	'lastName' => '',
	'attributes' => array( 'cidade' => '', 'interesses' => array() ),
	'tagIds' => array(),
	'optIn' => array( 'evidence' => 'Never on update' ),
), 'update' );
check( 'a present, empty value clears it', array_key_exists( 'lastName', $update ) && null === $update['lastName'] );
check( 'an absent key stays absent', ! array_key_exists( 'email', $update ) );
check( 'an emptied custom field is cleared', null === $update['attributes']['cidade'] && null === $update['attributes']['interesses'] );
check( 'an empty tag list removes every tag', array() === $update['tagIds'] );
check( 'consent is never changed by an edit', ! isset( $update['optIn'] ) );

echo "\nCloud_Contacts::text\n";

check( 'markup, scripts and control characters are stripped', 'Ana Souza' === Cloud_Contacts::text( "<script>alert(1)</script><b>Ana</b>\x00Souza" ) );
check( 'the length is capped in characters', 'ção' === Cloud_Contacts::text( 'çãoção', 3 ) );
check( 'arrays and booleans are not text', '' === Cloud_Contacts::text( array( 'a' ) ) && '' === Cloud_Contacts::text( true ) );

echo "\nCloud_Contacts::field_payload / tag_payload\n";

$field = Cloud_Contacts::field_payload( array( 'key' => 'Plano_Contratado', 'label' => ' Plano ', 'type' => 'select', 'options' => array( 'Start', '', 'Pro', 'Start' ), 'position' => 99999 ), 'create' );
check( 'a field key is lowercased', 'plano_contratado' === $field['key'] );
check( 'the options of a choice field are unique and non-empty', array( 'Start', 'Pro' ) === $field['options'] );
check( 'the position is capped', 10000 === $field['position'] );
check( 'a key starting with a digit is refused', ! isset( Cloud_Contacts::field_payload( array( 'key' => '1abc', 'label' => 'x', 'type' => 'text' ) )['key'] ) );
check( 'an unknown type is refused', ! isset( Cloud_Contacts::field_payload( array( 'key' => 'abc', 'type' => 'json' ) )['type'] ) );
check( 'options on a text field are dropped', ! isset( Cloud_Contacts::field_payload( array( 'key' => 'abc', 'type' => 'text', 'options' => array( 'a' ) ) )['options'] ) );

$field_update = Cloud_Contacts::field_payload( array( 'key' => 'other', 'type' => 'multi_select', 'label' => 'Interesses', 'options' => array( 'a' ), 'archived' => true ), 'update' );
check( 'an edit never sends the key or the type', ! isset( $field_update['key'] ) && ! isset( $field_update['type'] ) );
check( 'an edit keeps the options of a choice field', array( 'a' ) === $field_update['options'] );
check( 'an edit can archive', true === $field_update['archived'] );
check( 'archiving is not part of a create', ! isset( Cloud_Contacts::field_payload( array( 'archived' => true ), 'create' )['archived'] ) );

$tag = Cloud_Contacts::tag_payload( array( 'name' => ' Clientes VIP ', 'description' => '', 'color' => 'Green' ) );
check( 'a tag keeps its trimmed name', 'Clientes VIP' === $tag['name'] );
check( 'an emptied description is cleared', array_key_exists( 'description', $tag ) && null === $tag['description'] );
check( 'a palette color is lowercased', 'green' === $tag['color'] );
check( 'a hex color is not a palette name', null === Cloud_Contacts::tag_payload( array( 'color' => '#22c55e' ) )['color'] );

echo "\n{$assertions} assertions, {$failures} failures\n";
exit( $failures > 0 ? 1 : 0 );

}
