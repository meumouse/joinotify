<?php

namespace MeuMouse\Joinotify\Contacts;

use MeuMouse\Joinotify\Admin\Contacts\Registry;
use MeuMouse\Joinotify\Cloud_Sync\Cloud_Sync;
use MeuMouse\Joinotify\Cloud_Sync\Consent;
use MeuMouse\Joinotify\Cloud_Sync\Contact;
use MeuMouse\Joinotify\Cloud_Sync\Outbox;
use MeuMouse\Joinotify\Cloud_Sync\Payload;
use MeuMouse\Joinotify\Core\Logger;

defined('ABSPATH') || exit;

/**
 * Workflow actions that write to the Joinotify contact base: "Save contact" (name, e-mail, tags,
 * custom fields and consent) and "Tag contact" (add or remove tags).
 *
 * They reach any trigger, from any integration, without code of their own: the values are
 * placeholders resolved against the trigger. The row goes through the Cloud sync's outbox, like
 * everything the site sends: the workflow never waits on the platform, a failure is retried, and
 * nothing is sent while the sync is off. Rows are sent without starting the account's flows.
 *
 * Who the person is follows the trigger only when the typed phone or e-mail is that of the
 * trigger's user or customer; otherwise the row is a lead known by its e-mail or phone, so a
 * workflow that saves someone else (the store owner, a referral) never renumbers the customer.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Contacts
 * @author MeuMouse.com
 */
class Contact_Actions {

	/**
	 * Slug of "Save contact".
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const SAVE = 'joinotify_save_contact';

	/**
	 * Slug of "Tag contact".
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const TAG = 'joinotify_tag_contact';

	/**
	 * Category of both actions in the action library.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const CATEGORY = 'joinotify_contacts';

	/**
	 * Boxicons' User Plus.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const ICON_SAVE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M22 11h-3V8h-2v3h-3v2h3v3h2v-3h3zM4 8c0 2.28 1.72 4 4 4s4-1.72 4-4-1.72-4-4-4-4 1.72-4 4m6 0c0 1.18-.82 2-2 2s-2-.82-2-2 .82-2 2-2 2 .82 2 2M3 20h10c.55 0 1-.45 1-1v-1c0-2.76-2.24-5-5-5H7c-2.76 0-5 2.24-5 5v1c0 .55.45 1 1 1m4-5h2c1.65 0 3 1.35 3 3H4c0-1.65 1.35-3 3-3"/></svg>';

	/**
	 * Boxicons' Tag.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const ICON_TAG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H8.51c-.64 0-1.25.31-1.63.84l-4.7 6.58a.99.99 0 0 0 0 1.16l4.7 6.58c.37.52.98.84 1.63.84H20c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 14H8.51l-4.29-6 4.29-6H20z"/></svg>';


	/**
	 * Register both actions.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function __construct() {
		if ( ! function_exists( 'joinotify_register_action' ) ) {
			return;
		}

		$identity = array(
			array(
				'key' => 'phone',
				'label' => __( 'Phone', 'joinotify' ),
				'component' => 'placeholder',
				'placeholder' => '{{ wc_billing_phone }}',
				'description' => __( 'The contact\'s phone, usually a placeholder of the trigger. The phone or the e-mail is required.', 'joinotify' ),
			),
			array(
				'key' => 'email',
				'label' => __( 'E-mail', 'joinotify' ),
				'component' => 'placeholder',
				'placeholder' => '{{ wc_billing_email }}',
			),
		);

		$tags = array(
			array(
				'key' => 'tags',
				'label' => __( 'Tags to add', 'joinotify' ),
				'component' => 'placeholder',
				'placeholder' => __( 'e.g. Customer, VIP', 'joinotify' ),
				'description' => __( 'Tag names separated by commas. Tags that do not exist yet are created.', 'joinotify' ),
			),
			array(
				'key' => 'remove_tags',
				'label' => __( 'Tags to remove', 'joinotify' ),
				'component' => 'placeholder',
				'placeholder' => __( 'e.g. Lead', 'joinotify' ),
			),
		);

		joinotify_register_action( array(
			'action' => self::SAVE,
			'title' => __( 'Save contact', 'joinotify' ),
			'description' => __( 'Create or update the contact on Joinotify with the trigger\'s data: name, e-mail, tags, custom fields and marketing consent.', 'joinotify' ),
			'category' => self::CATEGORY,
			'category_label' => __( 'Contacts', 'joinotify' ),
			'category_icon' => self::ICON_SAVE,
			'category_priority' => 60,
			'icon' => self::ICON_SAVE,
			'has_settings' => true,
			'is_expansible' => true,
			'priority' => 10,
			'context' => array(),
			'default_data' => array(
				'action' => self::SAVE,
				'title' => __( 'Save contact', 'joinotify' ),
				'phone' => '{{ wc_billing_phone }}',
				'email' => '',
				'first_name' => '',
				'last_name' => '',
				'tags' => '',
				'remove_tags' => '',
				'consent' => 'keep',
				'evidence' => '',
				'attributes' => array(),
			),
			'settings_schema' => array_merge( $identity, array(
				array(
					'key' => 'first_name',
					'label' => __( 'First name', 'joinotify' ),
					'component' => 'placeholder',
					'placeholder' => '{{ wc_billing_first_name }}',
				),
				array(
					'key' => 'last_name',
					'label' => __( 'Last name', 'joinotify' ),
					'component' => 'placeholder',
					'placeholder' => '{{ wc_billing_last_name }}',
				),
			), $tags, array(
				array(
					'key' => 'attributes',
					'label' => __( 'Custom fields', 'joinotify' ),
					'component' => 'repeater',
					'placeholder' => __( 'Add field', 'joinotify' ),
					'description' => __( 'The key of a custom field (see Joinotify → Audiences & Contacts → Fields & tags) and its value. They only fill fields that are empty on Joinotify.', 'joinotify' ),
					'fields' => array(
						array(
							'key' => 'key',
							'label' => __( 'Field key', 'joinotify' ),
							'component' => 'input',
							'placeholder' => 'cidade',
						),
						array(
							'key' => 'value',
							'label' => __( 'Value', 'joinotify' ),
							'component' => 'placeholder',
							'placeholder' => '{{ wc_billing_city }}',
						),
					),
				),
				array(
					'key' => 'consent',
					'label' => __( 'Marketing consent', 'joinotify' ),
					'component' => 'select',
					'options' => array(
						array( 'label' => __( 'Leave as it is', 'joinotify' ), 'value' => 'keep' ),
						array( 'label' => __( 'Record opt-in', 'joinotify' ), 'value' => 'opt_in' ),
						array( 'label' => __( 'Record opt-out', 'joinotify' ), 'value' => 'opt_out' ),
					),
					'description' => __( 'Record an opt-in only when the trigger means the person agreed, e.g. a ticked checkbox. An opt-out always wins, and an opt-in never replaces an earlier opt-out.', 'joinotify' ),
				),
				array(
					'key' => 'evidence',
					'label' => __( 'How the consent was obtained', 'joinotify' ),
					'component' => 'placeholder',
					'placeholder' => __( 'e.g. Ticked "I want offers" on order {{ wc_order_number }}', 'joinotify' ),
					'condition' => array( array( 'key' => 'consent', 'value' => 'opt_in', 'operator' => 'eq' ) ),
				),
			) ),
			'handler' => array( __CLASS__, 'handle_save' ),
		) );

		joinotify_register_action( array(
			'action' => self::TAG,
			'title' => __( 'Tag contact', 'joinotify' ),
			'description' => __( 'Add or remove tags of the contact on Joinotify.', 'joinotify' ),
			'category' => self::CATEGORY,
			'icon' => self::ICON_TAG,
			'has_settings' => true,
			'is_expansible' => true,
			'priority' => 20,
			'context' => array(),
			'default_data' => array(
				'action' => self::TAG,
				'title' => __( 'Tag contact', 'joinotify' ),
				'phone' => '{{ wc_billing_phone }}',
				'email' => '',
				'tags' => '',
				'remove_tags' => '',
			),
			'settings_schema' => array_merge( $identity, $tags ),
			'handler' => array( __CLASS__, 'handle_tag' ),
		) );

		if ( function_exists( 'joinotify_register_action_description' ) ) {
			joinotify_register_action_description( self::SAVE, array( __CLASS__, 'describe' ) );
			joinotify_register_action_description( self::TAG, array( __CLASS__, 'describe' ) );
		}
	}


	/**
	 * The node's line on the canvas: the tags it adds and removes.
	 *
	 * @since 2.5.0
	 * @param array $data Node data.
	 * @return string
	 */
	public static function describe( $data ) {
		$add = trim( (string) ( $data['tags'] ?? '' ) );
		$remove = trim( (string) ( $data['remove_tags'] ?? '' ) );
		$parts = array();

		if ( '' !== $add ) {
			$parts[] = '+ ' . $add;
		}

		if ( '' !== $remove ) {
			$parts[] = '− ' . $remove;
		}

		return esc_html( ! empty( $parts ) ? implode( ' · ', $parts ) : __( 'Joinotify contact', 'joinotify' ) );
	}


	/**
	 * Run "Save contact".
	 *
	 * @since 2.5.0
	 * @param array $action_data Node data.
	 * @param array $action      Full node.
	 * @param int   $post_id     Workflow id.
	 * @param array $event_data  Trigger payload.
	 * @return bool
	 */
	public static function handle_save( $action_data, $action = array(), $post_id = 0, $event_data = array() ) {
		return self::queue( 'Save contact', $action_data, (int) $post_id, (array) $event_data, true );
	}


	/**
	 * Run "Tag contact".
	 *
	 * @since 2.5.0
	 * @param array $action_data Node data.
	 * @param array $action      Full node.
	 * @param int   $post_id     Workflow id.
	 * @param array $event_data  Trigger payload.
	 * @return bool
	 */
	public static function handle_tag( $action_data, $action = array(), $post_id = 0, $event_data = array() ) {
		return self::queue( 'Tag contact', $action_data, (int) $post_id, (array) $event_data, false );
	}


	/**
	 * Resolve the node's placeholders and queue the row.
	 *
	 * @since 2.5.0
	 * @param string $label       Action name for the log.
	 * @param array  $action_data Node data.
	 * @param int    $post_id     Workflow id.
	 * @param array  $event_data  Trigger payload.
	 * @param bool   $full        Whether name, custom fields and consent are part of the action.
	 * @return bool
	 */
	private static function queue( $label, $action_data, $post_id, $event_data, $full ) {
		if ( ! Cloud_Sync::is_enabled() ) {
			Logger::register_log( sprintf( '%s (workflow %d): switch on Joinotify Cloud sync to send contacts to Joinotify.', $label, $post_id ), 'WARNING' );

			return false;
		}

		$resolve = static function( $value ) use ( $event_data ) {
			return trim( joinotify_replace_placeholders( (string) $value, $event_data ) );
		};

		$values = array(
			'phone' => $resolve( $action_data['phone'] ?? '' ),
			'email' => $resolve( $action_data['email'] ?? '' ),
			'tags' => $resolve( $action_data['tags'] ?? '' ),
			'remove_tags' => $resolve( $action_data['remove_tags'] ?? '' ),
		);

		if ( $full ) {
			$values['first_name'] = $resolve( $action_data['first_name'] ?? '' );
			$values['last_name'] = $resolve( $action_data['last_name'] ?? '' );
			$values['consent'] = (string) ( $action_data['consent'] ?? 'keep' );
			$values['evidence'] = $resolve( $action_data['evidence'] ?? '' );
			$values['attributes'] = array();

			foreach ( (array) ( $action_data['attributes'] ?? array() ) as $row ) {
				if ( is_array( $row ) && ! empty( $row['key'] ) ) {
					$values['attributes'][ (string) $row['key'] ] = $resolve( $row['value'] ?? '' );
				}
			}
		}

		$row = self::build_row( $values, self::known_person( $event_data ), array(
			'now' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'country' => Registry::default_country(),
			'site_tag' => Cloud_Sync::source_tag(),
			'workflow' => get_the_title( $post_id ),
			'host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		) );

		if ( null === $row ) {
			Logger::register_log( sprintf( '%s (workflow %d): the phone and the e-mail are empty once placeholders are replaced.', $label, $post_id ), 'WARNING' );

			return false;
		}

		return false !== Outbox::push_contact( $row );
	}


	/**
	 * Who the trigger is about, when it is about a user or a customer.
	 *
	 * @since 2.5.0
	 * @param array $event_data Trigger payload.
	 * @return array{ref:array,email:string,phone:string}|null
	 */
	private static function known_person( $event_data ) {
		if ( ! empty( $event_data['order_id'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $event_data['order_id'] );

			if ( $order && method_exists( $order, 'get_billing_email' ) ) {
				$customer_id = (int) $order->get_customer_id();
				$email = (string) $order->get_billing_email();
				$phone = (string) $order->get_billing_phone();
				$ref = $customer_id > 0 ? array( 'kind' => 'wc_customer', 'id' => (string) $customer_id ) : Contact::anonymous_ref( 'wc_guest', $email, $phone );

				return null === $ref ? null : array( 'ref' => $ref, 'email' => $email, 'phone' => $phone );
			}
		}

		if ( ! empty( $event_data['user_id'] ) ) {
			$user = get_userdata( (int) $event_data['user_id'] );

			if ( $user ) {
				return array(
					'ref' => array( 'kind' => 'wp_user', 'id' => (string) $user->ID ),
					'email' => (string) $user->user_email,
					'phone' => (string) Payload::user_phone( $user->ID ),
				);
			}
		}

		return null;
	}


	/**
	 * Build the `/contacts/sync` row of an action. Pure, for the harness.
	 *
	 * @since 2.5.0
	 * @param array      $values  Resolved values: `phone`, `email`, `first_name`, `last_name`, `tags`,
	 *     `remove_tags`, `attributes`, `consent` (`keep`, `opt_in`, `opt_out`) and `evidence`.
	 * @param array|null $known   The trigger's person: `ref`, `email`, `phone`.
	 * @param array      $context `now`, `country`, `site_tag`, `workflow`, `host`.
	 * @return array|null Null without a phone or an e-mail.
	 */
	public static function build_row( $values, $known, $context ) {
		$phone = trim( (string) ( $values['phone'] ?? '' ) );
		$email = strtolower( trim( (string) ( $values['email'] ?? '' ) ) );
		$email = false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
		$digits = preg_replace( '/\D/', '', $phone );

		if ( strlen( $digits ) < 8 ) {
			$phone = '';
			$digits = '';
		}

		if ( '' === $phone && '' === $email ) {
			return null;
		}

		$ref = null;

		if ( is_array( $known ) && ! empty( $known['ref'] ) ) {
			$same_email = '' !== $email && strtolower( trim( (string) $known['email'] ) ) === $email;
			$same_phone = '' !== $digits && Consent::same_phone( preg_replace( '/\D/', '', (string) $known['phone'] ), $digits );

			if ( $same_email || $same_phone ) {
				$ref = $known['ref'];
			}
		}

		$row = array( 'ref' => $ref ?? Contact::anonymous_ref( 'lead', $email, $phone ) );

		if ( '' !== $phone ) {
			$row['phone'] = $phone;

			// A number typed without "+" is read in the store's country.
			if ( '+' !== substr( $phone, 0, 1 ) && ! empty( $context['country'] ) ) {
				$row['country'] = (string) $context['country'];
			}
		}

		if ( '' !== $email ) {
			$row['email'] = $email;
		}

		foreach ( array( 'first_name' => 'firstName', 'last_name' => 'lastName' ) as $from => $to ) {
			$value = trim( (string) ( $values[ $from ] ?? '' ) );

			if ( '' !== $value ) {
				$row[ $to ] = mb_substr( $value, 0, 80 );
			}
		}

		$attributes = array();

		foreach ( (array) ( $values['attributes'] ?? array() ) as $key => $value ) {
			if ( 1 === preg_match( '/^[a-z][a-z0-9_]{0,39}$/', (string) $key ) && '' !== trim( (string) $value ) ) {
				$attributes[ $key ] = mb_substr( trim( (string) $value ), 0, 1000 );
			}
		}

		if ( ! empty( $attributes ) ) {
			$row['attributes'] = $attributes;
		}

		$tags = Sources::parse_tags( (string) ( $values['tags'] ?? '' ) );
		$site_tag = trim( (string) ( $context['site_tag'] ?? '' ) );

		if ( '' !== $site_tag ) {
			array_unshift( $tags, $site_tag );
		}

		$merged = Sources::merge_block( array(), $tags, array() );

		if ( ! empty( $merged['tags'] ) ) {
			$row['tags'] = $merged['tags'];
		}

		$remove = array_slice( Sources::parse_tags( (string) ( $values['remove_tags'] ?? '' ) ), 0, Sources::MAX_TAGS );

		if ( ! empty( $remove ) ) {
			$row['removeTags'] = $remove;
		}

		$consent = (string) ( $values['consent'] ?? 'keep' );

		if ( 'opt_in' === $consent ) {
			$evidence = trim( (string) ( $values['evidence'] ?? '' ) );

			if ( strlen( $evidence ) < 3 ) {
				$evidence = sprintf( 'Workflow "%s" on %s', (string) ( $context['workflow'] ?? '' ), (string) ( $context['host'] ?? '' ) );
			}

			$row['consent'] = array(
				'status' => 'opted_in',
				'evidence' => mb_substr( $evidence, 0, 500 ),
				'at' => (string) $context['now'],
			);
		} elseif ( 'opt_out' === $consent ) {
			$row['consent'] = array(
				'status' => 'opted_out',
				'reason' => 'workflow',
			);
		}

		$row['occurredAt'] = (string) $context['now'];

		return $row;
	}
}
