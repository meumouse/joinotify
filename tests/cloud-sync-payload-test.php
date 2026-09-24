<?php
/**
 * Standalone test harness for the pure parts of what the Joinotify Cloud sync sends: how a form's
 * fields are read (Payload::form_fields), who someone without an account is (Contact::anonymous_ref),
 * where pack values land in the account (Contact::map_attributes) and which names an extension may
 * send through joinotify_track() (Emitters::is_custom_name).
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

echo "\n{$assertions} assertions, {$failures} failure(s).\n";
exit( $failures > 0 ? 1 : 0 );

}
