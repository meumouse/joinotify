<?php
/**
 * Standalone test harness for the pure parts of what the Joinotify Cloud sync sends: how a form's
 * fields are read (Payload::form_fields), who someone without an account is (Contact::anonymous_ref),
 * where pack values land in the account (Contact::map_attributes) and which names an extension may
 * send through joinotify_track() (Emitters::is_custom_name), and the consent a contact block carries,
 * the phone match of a platform opt-out and the refs an erasure names (Consent).
 *
 * No WordPress bootstrap: the few WP functions reached are stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/cloud-sync-payload-test.php
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

function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
function remove_accents( $text ) { return strtr( $text, array( 'á' => 'a', 'ã' => 'a', 'ç' => 'c', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E' ) ); }

require __DIR__ . '/../admin/src/Cloud_Sync/Payload.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Contact.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Emitters.php';
require __DIR__ . '/../admin/src/Cloud_Sync/Consent.php';

use MeuMouse\Joinotify\Cloud_Sync\Consent;
use MeuMouse\Joinotify\Cloud_Sync\Contact;
use MeuMouse\Joinotify\Cloud_Sync\Emitters;
use MeuMouse\Joinotify\Cloud_Sync\Payload;

echo "\nPayload::form_fields\n";

$form = Payload::form_fields( array(
	array( 'id' => '1', 'label' => 'Nome completo', 'type' => 'name', 'value' => 'Ana Souza' ),
	array( 'id' => '2', 'label' => 'Seu e-mail', 'type' => 'text', 'value' => 'ana@example.com' ),
	array( 'id' => '3', 'label' => 'WhatsApp', 'type' => 'text', 'value' => '(11) 98765-4321' ),
	array( 'id' => '4', 'label' => 'Mensagem', 'type' => 'textarea', 'value' => 'Quero um orçamento' ),
	array( 'id' => '5', 'label' => 'Mensagem', 'type' => 'textarea', 'value' => 'Segunda' ),
	array( 'id' => '6', 'label' => 'Empresa', 'type' => 'text', 'value' => '' ),
) );

check( 'the phone is the field whose label says WhatsApp', '(11) 98765-4321' === $form['phone'] );
check( 'the e-mail is the field whose label says mail', 'ana@example.com' === $form['email'] );
check( 'the name is the field typed as one', 'Ana Souza' === $form['name'] );
check( 'fields are keyed by their label, without accents', isset( $form['fields']['nome_completo'], $form['fields']['seu_e-mail'] ) );
check( 'two fields with one label keep both', 'Quero um orçamento' === $form['fields']['mensagem'] && 'Segunda' === $form['fields']['mensagem_2'] );
check( 'an empty field is left out', ! isset( $form['fields']['empresa'] ) );

$typed = Payload::form_fields( array(
	array( 'id' => 'field_a', 'label' => '', 'type' => 'tel', 'value' => '+5511987654321' ),
	array( 'id' => 'field_b', 'label' => 'Contato', 'type' => 'email', 'value' => 'b@example.com' ),
) );

check( 'a field typed tel is the phone, whatever its label', '+5511987654321' === $typed['phone'] );
check( 'a field without a label is keyed by its id', isset( $typed['fields']['field_a'] ) );
check( 'a field typed email is the e-mail', 'b@example.com' === $typed['email'] );
check( 'nothing named, no name', '' === $typed['name'] );

echo "\nContact::anonymous_ref\n";

$guest = Contact::anonymous_ref( 'wc_guest', '  Ana@Example.com ', '+55 11 98765-4321' );
check( 'a guest is the hash of their e-mail, case and spaces aside', array( 'kind' => 'wc_guest', 'id' => hash( 'sha256', 'ana@example.com' ) ) === $guest );
check( 'the id carries no personal data in the clear', false === strpos( $guest['id'], 'ana' ) && 64 === strlen( $guest['id'] ) );

$by_phone = Contact::anonymous_ref( 'lead', '', '+55 (11) 98765-4321' );
check( 'without an e-mail, the hash of the phone digits', hash( 'sha256', 'phone:5511987654321' ) === $by_phone['id'] && 'lead' === $by_phone['kind'] );
check( 'the same phone written otherwise is the same person', $by_phone === Contact::anonymous_ref( 'lead', '', '5511987654321' ) );
check( 'nothing to know them by, no ref', null === Contact::anonymous_ref( 'lead', '', '' ) );

echo "\nContact::map_attributes\n";

$mapped = Contact::map_attributes(
	array( 'wc_orders_count' => 3, 'wc_total_spent' => 540.9, 'wc_billing_city' => 'São Paulo' ),
	array( 'wc_orders_count' => 'wc_orders_count', 'wc_total_spent' => 'total_gasto', 'wc_billing_city' => null )
);

check( 'a pack key lands at its key in the account', 3 === $mapped['wc_orders_count'] );
check( '…even when the account renamed it', 540.9 === $mapped['total_gasto'] && ! isset( $mapped['wc_total_spent'] ) );
check( 'a key the account does not have is left out', ! isset( $mapped['wc_billing_city'] ) && 2 === count( $mapped ) );
check( 'no map, nothing', array() === Contact::map_attributes( array( 'wc_orders_count' => 1 ), array() ) );

echo "\nEmitters::is_custom_name\n";

check( 'custom.quote.requested is allowed', Emitters::is_custom_name( 'custom.quote.requested' ) );
check( 'up to three segments', Emitters::is_custom_name( 'custom.a.b.c' ) && ! Emitters::is_custom_name( 'custom.a.b.c.d' ) );
check( 'the platform\'s own names are not an extension\'s', ! Emitters::is_custom_name( 'wc.order.paid' ) );
check( 'custom alone is not a name', ! Emitters::is_custom_name( 'custom' ) && ! Emitters::is_custom_name( 'custom.' ) );
check( 'uppercase and dashes are refused, as the platform would', ! Emitters::is_custom_name( 'custom.Quote' ) && ! Emitters::is_custom_name( 'custom.quote-requested' ) );
check( 'not a string, not a name', ! Emitters::is_custom_name( array( 'custom.x' ) ) );

echo "\nContact::to_sync_row\n";

$row = Contact::to_sync_row( array(
	'ref' => array( 'kind' => 'wc_customer', 'id' => '42' ),
	'phone' => '+5511987654321',
	'first_name' => 'Ana',
	'last_name' => 'Souza',
	'email' => 'ana@example.com',
	'attributes' => array( 'wc_orders_count' => 3 ),
	'tags' => array( 'Loja' ),
	'consent' => array( 'status' => 'opted_in', 'evidence' => 'x — checkout — loja.test' ),
), '2026-09-24T12:00:00Z' );

check( 'the block\'s snake_case becomes the route\'s camelCase', 'Ana' === $row['firstName'] && 'Souza' === $row['lastName'] && ! isset( $row['first_name'] ) );
check( 'who, how to reach and what they hold go as they are', '42' === $row['ref']['id'] && '+5511987654321' === $row['phone'] && 3 === $row['attributes']['wc_orders_count'] );
check( 'tags and consent ride along', array( 'Loja' ) === $row['tags'] && 'opted_in' === $row['consent']['status'] );
check( 'the row says when its values were read', '2026-09-24T12:00:00Z' === $row['occurredAt'] );
check( 'what the block does not have, the row does not invent', ! isset( $row['country'] ) && ! isset( $row['removeTags'] ) );

echo "\nConsent::block_consent\n";

$ticked = Consent::block_consent( array( 'text' => 'Quero receber ofertas no WhatsApp.', 'where' => 'checkout', 'order_id' => 1234, 'at' => 1790000000 ), false, 'loja.test' );
check( 'a ticked box is an opt-in', 'opted_in' === $ticked['status'] );
check( '…whose evidence is the exact text, where and the site', 'Quero receber ofertas no WhatsApp. — checkout, order #1234 — loja.test' === $ticked['evidence'] );
check( '…and when, in UTC', '2026-09-21T14:13:20Z' === $ticked['at'] );
check( 'unticked in My account is an opt-out, whatever was stored', array( 'status' => 'opted_out', 'reason' => 'my_account' ) === Consent::block_consent( array( 'text' => 'x', 'at' => 1 ), true, 'loja.test' ) );
check( 'nothing stored, nothing said', null === Consent::block_consent( null, false, 'loja.test' ) );
check( 'evidence without its text is no evidence', null === Consent::block_consent( array( 'text' => ' ', 'at' => 1 ), false, 'loja.test' ) );
check( 'evidence fits the platform\'s 500 characters', 500 === mb_strlen( Consent::block_consent( array( 'text' => str_repeat( 'á', 600 ) ), false, 'loja.test' )['evidence'] ) );

echo "\nConsent::same_phone\n";

check( 'the platform\'s number with its country is the store\'s without it', Consent::same_phone( '5511987654321', '11987654321' ) );
check( 'a trunk zero is not a different line', Consent::same_phone( '011987654321', '5511987654321' ) );
check( 'another number with the same last digits is not the same line', ! Consent::same_phone( '5511987654321', '21987654321' ) );
check( 'too short to tell', ! Consent::same_phone( '4321', '5511987654321' ) );

echo "\nConsent::refs_of\n";

$refs = Consent::refs_of( 'Ana@Example.com', 42 );
check( 'a user is erased as user and as customer', array( 'kind' => 'wp_user', 'id' => '42' ) === $refs[0] && array( 'kind' => 'wc_customer', 'id' => '42' ) === $refs[1] );
check( '…and as the guest and the lead of their e-mail', hash( 'sha256', 'ana@example.com' ) === $refs[2]['id'] && 'lead' === $refs[3]['kind'] );
check( 'someone without an account is only a guest and a lead', 2 === count( Consent::refs_of( 'b@example.com', 0 ) ) );

echo "\n{$assertions} assertions, {$failures} failure(s).\n";
exit( $failures > 0 ? 1 : 0 );

}
