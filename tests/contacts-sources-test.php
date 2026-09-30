<?php
/**
 * Standalone test harness for the pure parts of Contacts\Sources: how the tags, custom fields and
 * consent the owner configured join a Cloud sync contact block, and how a form submission is read
 * through the rule set for that form.
 *
 * No WordPress bootstrap: the few WP functions reached are stubbed below.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" -d extension=mbstring tests/contacts-sources-test.php
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

require __DIR__ . '/../admin/src/Contacts/Sources.php';

use MeuMouse\Joinotify\Contacts\Sources;

echo "\nSources::parse_tags\n";

check( 'a comma list becomes trimmed names', array( 'Cliente WooCommerce', 'VIP' ) === Sources::parse_tags( ' Cliente WooCommerce ,VIP,, ' ) );
check( 'repeated names are kept once', array( 'VIP' ) === Sources::parse_tags( 'VIP, VIP' ) );
check( 'a list already split is accepted', array( 'a', 'b' ) === Sources::parse_tags( array( 'a', '', 'b' ) ) );
check( 'a name is capped at 60 characters', 60 === mb_strlen( Sources::parse_tags( str_repeat( 'é', 80 ) )[0] ) );

echo "\nSources::merge_block\n";

$block = array( 'ref' => array( 'kind' => 'wp_user', 'id' => '4' ), 'tags' => array( 'Loja' ), 'attributes' => array( 'wc_orders_count' => 3 ) );
$merged = Sources::merge_block( $block, array( 'loja', 'Cliente', ' ' ), array( 'cpf' => '123', 'wc_orders_count' => 99, 'empty' => '' ) );
check( 'the block tags come first and a case-insensitive repeat is dropped', array( 'Loja', 'Cliente' ) === $merged['tags'] );
check( 'a mapped field is added', '123' === $merged['attributes']['cpf'] );
check( 'a value the sync computed is never replaced', 3 === $merged['attributes']['wc_orders_count'] );
check( 'empty values are not sent', ! isset( $merged['attributes']['empty'] ) );

$many = Sources::merge_block( array(), array_map( function ( $n ) { return 'tag ' . $n; }, range( 1, 30 ) ), array() );
check( 'no more than 20 tags travel', 20 === count( $many['tags'] ) );

$opted_out = array( 'consent' => array( 'status' => 'opted_out', 'reason' => 'my_account' ) );
check( 'a form consent never overrides the one the sync attached', 'opted_out' === Sources::merge_block( $opted_out, array(), array(), array( 'status' => 'opted_in', 'evidence' => 'form' ) )['consent']['status'] );
check( 'a form consent fills a block without one', 'opted_in' === Sources::merge_block( array(), array(), array(), array( 'status' => 'opted_in', 'evidence' => 'form' ) )['consent']['status'] );
check( 'nothing to add leaves the block as it was', $block === Sources::merge_block( $block, array(), array() ) );

echo "\nSources::sanitize_attribute_map\n";

$map = Sources::sanitize_attribute_map( array(
	array( 'source' => 'order_meta:_billing_cpf', 'target' => 'cpf' ),
	array( 'source' => 'user_meta:billing_birthdate', 'target' => 'aniversario' ),
	array( 'source' => 'option:siteurl', 'target' => 'x' ),
	array( 'source' => 'user_meta:ok', 'target' => 'Bad Key' ),
	'junk',
) );
check( 'order and user meta sources are kept', 2 === count( $map ) && 'cpf' === $map[0]['target'] );
check( 'any other source is refused', ! in_array( 'option:siteurl', array_column( $map, 'source' ), true ) );

echo "\nSources::sanitize_form_rules\n";

$rules = Sources::sanitize_form_rules( array(
	'wpforms:12' => array(
		'enabled' => 'yes',
		'phone' => '3',
		'email' => '2',
		'first_name' => '1',
		'consent' => '5',
		'consent_text' => 'Quero receber ofertas pelo WhatsApp',
		'tags' => 'Lead site, Newsletter',
		'attributes' => array( '4' => 'cidade', '6' => 'Not Valid', '../x' => 'y' ),
	),
	'elementor:abc123' => array( 'enabled' => 'no' ),
	'gravity:1' => array( 'enabled' => true ),
	'wpforms:13' => array( 'enabled' => true, 'phone' => '<script>' ),
) );
check( 'a rule keeps its field ids', '3' === $rules['wpforms:12']['phone'] && '1' === $rules['wpforms:12']['first_name'] );
check( 'its tags become a list', array( 'Lead site', 'Newsletter' ) === $rules['wpforms:12']['tags'] );
check( 'only valid custom field targets are kept', array( '4' => 'cidade' ) === $rules['wpforms:12']['attributes'] );
check( '"no" disables a form', false === $rules['elementor:abc123']['enabled'] );
check( 'an unknown form plugin is dropped', ! isset( $rules['gravity:1'] ) );
check( 'a malformed field id is emptied', '' === $rules['wpforms:13']['phone'] );

echo "\nSources::map_form\n";

$submission = array(
	array( 'id' => '1', 'label' => 'Nome completo', 'type' => 'text', 'value' => 'Ana Maria Souza' ),
	array( 'id' => '2', 'label' => 'E-mail', 'type' => 'email', 'value' => 'ana@example.com' ),
	array( 'id' => '3', 'label' => 'Contato', 'type' => 'text', 'value' => '41 98711-1527' ),
	array( 'id' => '4', 'label' => 'Cidade', 'type' => 'text', 'value' => 'Curitiba' ),
	array( 'id' => '5', 'label' => 'Aceito receber ofertas', 'type' => 'checkbox', 'value' => 'Aceito receber ofertas' ),
);
$parsed = array( 'phone' => '', 'email' => 'ana@example.com', 'name' => 'Ana Maria Souza' );
$person = Sources::map_form( $rules['wpforms:12'], $submission, $parsed, array( 'title' => 'Contato', 'host' => 'loja.test' ), '2026-09-30T12:00:00Z' );

check( 'the rule finds a phone the labels would miss', '41 98711-1527' === $person['phone'] );
check( 'a full name picked as first name is split', 'Ana' === $person['first_name'] && 'Maria Souza' === $person['last_name'] );
check( 'mapped fields become custom fields', array( 'cidade' => 'Curitiba' ) === $person['attributes'] );
check( 'the rule tags travel', array( 'Lead site', 'Newsletter' ) === $person['tags'] );
check( 'a ticked consent field opts the contact in', 'opted_in' === $person['consent']['status'] );
check( '…with the owner\'s text, the form and the site as evidence', 'Quero receber ofertas pelo WhatsApp — form "Contato" — loja.test' === $person['consent']['evidence'] );
check( '…and when', '2026-09-30T12:00:00Z' === $person['consent']['at'] );

$unticked = $submission;
$unticked[4]['value'] = '';
check( 'an unticked consent field says nothing', null === Sources::map_form( $rules['wpforms:12'], $unticked, $parsed )['consent'] );

$no_rule = Sources::map_form( array(), $submission, array( 'phone' => '999', 'email' => 'x@y.z', 'name' => 'Bruno Lima' ) );
check( 'without a rule the sync reading is kept', '999' === $no_rule['phone'] && 'Bruno' === $no_rule['first_name'] && 'Lima' === $no_rule['last_name'] );
check( '…and no consent is invented', null === $no_rule['consent'] && array() === $no_rule['tags'] );

echo "\nSources::ticked\n";

check( 'a checked box sends its label', Sources::ticked( 'Aceito' ) );
check( 'Elementor sends "on"', Sources::ticked( 'on' ) );
check( '"no", "0" and empty are not consent', ! Sources::ticked( 'no' ) && ! Sources::ticked( '0' ) && ! Sources::ticked( ' ' ) );

echo "\nSources::sanitize_config\n";

$config = Sources::sanitize_config( array(
	'cloud_sync_forms_mode' => 'selected',
	'cloud_sync_tags_woocommerce' => 'Cliente, VIP',
	'cloud_sync_form_rules' => array( 'wpforms:12' => array( 'enabled' => true ) ),
	'enable_cloud_sync' => 'yes',
) );
check( 'the forms mode is kept', 'selected' === $config['cloud_sync_forms_mode'] );
check( 'source tags are normalized', 'Cliente, VIP' === $config['cloud_sync_tags_woocommerce'] );
check( 'rules are stored with yes/no', 'yes' === $config['cloud_sync_form_rules']['wpforms:12']['enabled'] );
check( 'keys the tab does not own are left out', ! isset( $config['enable_cloud_sync'] ) );

echo "\n{$assertions} assertions, {$failures} failures\n";
exit( $failures > 0 ? 1 : 0 );

}
