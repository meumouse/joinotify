<?php
/**
 * Standalone test harness for Contacts\Contact_Actions::build_row: the `/contacts/sync` row the
 * "Save contact" and "Tag contact" workflow actions queue — who the person is, the tags added and
 * removed, the custom fields and the consent.
 *
 * No WordPress bootstrap: the few WP functions reached are stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" -d extension=mbstring tests/contact-actions-test.php
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

require __DIR__ . '/../admin/src/Cloud_Sync/Contact.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Consent.php';
require __DIR__ . '/../admin/src/Contacts/Sources.php';
require __DIR__ . '/../admin/src/Contacts/Contact_Actions.php';

use MeuMouse\Joinotify\Contacts\Contact_Actions;

$context = array(
	'now' => '2026-09-30T12:00:00Z',
	'country' => 'BR',
	'site_tag' => 'Loja Exemplo',
	'workflow' => 'Pedido pago',
	'host' => 'loja.test',
);

$customer = array(
	'ref' => array( 'kind' => 'wc_customer', 'id' => '42' ),
	'email' => 'Ana@Example.com',
	'phone' => '(41) 98711-1527',
);

echo "\nWho the person is\n";

$row = Contact_Actions::build_row( array( 'phone' => '41 98711-1527', 'email' => '' ), $customer, $context );
check( 'the trigger\'s customer, when the typed phone is theirs', array( 'kind' => 'wc_customer', 'id' => '42' ) === $row['ref'] );

$row = Contact_Actions::build_row( array( 'phone' => '', 'email' => 'ana@example.com' ), $customer, $context );
check( '…or the typed e-mail is theirs, whatever its case', 'wc_customer' === $row['ref']['kind'] );

$other = Contact_Actions::build_row( array( 'phone' => '+55 11 91234-5678', 'email' => 'owner@store.test' ), $customer, $context );
check( 'someone else typed in the action is a lead, never the customer', 'lead' === $other['ref']['kind'] );
check( '…known by the hash of their e-mail', hash( 'sha256', 'owner@store.test' ) === $other['ref']['id'] );

$no_trigger = Contact_Actions::build_row( array( 'phone' => '41987111527' ), null, $context );
check( 'without a trigger person, a lead known by the phone', hash( 'sha256', 'phone:41987111527' ) === $no_trigger['ref']['id'] );

check( 'no phone and no e-mail is nothing to send', null === Contact_Actions::build_row( array( 'phone' => ' ', 'email' => 'not-an-email' ), $customer, $context ) );
check( 'a phone too short to be one is dropped', null === Contact_Actions::build_row( array( 'phone' => '1234' ), null, $context ) );

echo "\nThe row\n";

check( 'a phone without "+" is read in the store country', 'BR' === $no_trigger['country'] );
check( 'a phone with "+" carries its own country', ! isset( $other['country'] ) );
check( 'the e-mail is lowercased', 'owner@store.test' === $other['email'] );

$full = Contact_Actions::build_row( array(
	'phone' => '+5541987111527',
	'first_name' => ' Ana ',
	'last_name' => 'Souza',
	'tags' => 'VIP, loja exemplo, Cliente',
	'remove_tags' => 'Lead',
	'attributes' => array( 'cidade' => 'Curitiba', 'Bad Key' => 'x', 'vazio' => ' ' ),
	'consent' => 'keep',
), $customer, $context );
check( 'names travel in camelCase', 'Ana' === $full['firstName'] && 'Souza' === $full['lastName'] );
check( 'the site tag comes first and a repeat of it is dropped', array( 'Loja Exemplo', 'VIP', 'Cliente' ) === $full['tags'] );
check( 'tags to remove travel as removeTags', array( 'Lead' ) === $full['removeTags'] );
check( 'only valid, filled custom fields travel', array( 'cidade' => 'Curitiba' ) === $full['attributes'] );
check( '"leave as it is" sends no consent', ! isset( $full['consent'] ) );
check( 'the row says when it was read', '2026-09-30T12:00:00Z' === $full['occurredAt'] );

echo "\nConsent\n";

$in = Contact_Actions::build_row( array( 'phone' => '+5541987111527', 'consent' => 'opt_in', 'evidence' => 'Marcou "quero ofertas" no pedido 1042' ), null, $context );
check( 'an opt-in carries the evidence and when', 'opted_in' === $in['consent']['status'] && 'Marcou "quero ofertas" no pedido 1042' === $in['consent']['evidence'] && '2026-09-30T12:00:00Z' === $in['consent']['at'] );

$in_default = Contact_Actions::build_row( array( 'phone' => '+5541987111527', 'consent' => 'opt_in', 'evidence' => '' ), null, $context );
check( 'an opt-in without evidence names the workflow and the site', 'Workflow "Pedido pago" on loja.test' === $in_default['consent']['evidence'] );

$out = Contact_Actions::build_row( array( 'phone' => '+5541987111527', 'consent' => 'opt_out' ), null, $context );
check( 'an opt-out says it came from a workflow', array( 'status' => 'opted_out', 'reason' => 'workflow' ) === $out['consent'] );

echo "\n{$assertions} assertions, {$failures} failures\n";
exit( $failures > 0 ? 1 : 0 );

}
