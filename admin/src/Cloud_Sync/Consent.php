<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Api\Webhooks;
use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Marketing consent of the site's customers, and their privacy requests.
 *
 * - **Asking.** When the owner turns it on, an unticked checkbox appears in the checkout (classic
 *   and block), the sign-up forms and "My account". Who ticks it has the exact text, where it was
 *   and when stored on the order or the user — the evidence the platform requires — and the next
 *   event about them carries it as `opted_in`. Who unticks it in "My account" opts out, and that
 *   one event carries `opted_out`: withdrawing must be as easy as agreeing (LGPD, art. 8, § 5).
 * - **Mirroring.** An opt-out on the platform (a keyword, the panel, a flow) comes back by webhook
 *   and marks the user, so the site shows the preference unticked. An opt-in there clears it.
 * - **Privacy requests.** WordPress's "Export/Erase Personal Data" tools cover what the sync keeps:
 *   the consent records and the outbox rows. Erasing also asks the platform to erase the contacts
 *   this site linked to the person — by who they are on the site, never by phone.
 *
 * Consent only ever goes up from "unknown" on the platform: a checkbox from an old order never
 * undoes an opt-out, whatever the site sends.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Consent {

	/**
	 * Evidence of a checkbox ticked: on the order, and on the user.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const ORDER_META = '_joinotify_marketing_consent';
	const USER_META = 'joinotify_marketing_consent';

	/**
	 * When the user opted out — on the site or on the platform.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const OPT_OUT_META = 'joinotify_marketing_opt_out';

	/**
	 * The checkbox: its name in classic forms, and its id as a WooCommerce additional field.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const FIELD = 'joinotify_marketing_consent';
	const BLOCK_FIELD = 'joinotify/marketing-consent';

	/**
	 * Platform events the site's webhook endpoint subscribes to while syncing.
	 *
	 * @since 2.5.0
	 * @var array<int,string>
	 */
	const WEBHOOK_EVENTS = array( 'contact.opted_in', 'contact.opted_out' );

	/**
	 * Option remembering which events the endpoint was last set to.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const WEBHOOK_EVENTS_OPTION = 'joinotify_cloud_sync_webhook_events';

	/**
	 * Users who unticked the preference in this request — the one event that carries `opted_out`.
	 *
	 * @since 2.5.0
	 * @var array<int,bool>
	 */
	private static $withdrawn = array();


	/**
	 * Wire what is always on (privacy tools, the webhook's subscription) and, when syncing, the
	 * consent the events carry and the checkboxes.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'add_eraser' ) );
		add_filter( 'Joinotify/Cloud_Api/Webhook_Events', array( __CLASS__, 'webhook_events' ) );
		add_action( 'admin_init', array( __CLASS__, 'sync_webhook_events' ) );
		add_action( 'Joinotify/Cloud_Api/Webhook_Event', array( __CLASS__, 'on_webhook' ), 10, 2 );

		if ( ! Cloud_Sync::is_enabled() ) {
			return;
		}

		add_filter( 'Joinotify/Cloud_Sync/Contact_Block', array( __CLASS__, 'attach' ), 10, 2 );

		if ( ! self::asks() ) {
			return;
		}

		// Classic checkout.
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'render_checkout' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_classic_checkout' ), 10, 2 );

		// Checkout block: WooCommerce's additional checkout fields (8.9+). Older stores keep the classic one.
		add_action( 'woocommerce_init', array( __CLASS__, 'register_block_field' ) );
		add_action( 'woocommerce_set_additional_field_value', array( __CLASS__, 'save_block_field' ), 10, 4 );

		// Sign-up: WordPress's own form and WooCommerce's.
		add_action( 'register_form', array( __CLASS__, 'render_signup' ) );
		add_action( 'woocommerce_register_form', array( __CLASS__, 'render_signup' ) );
		add_action( 'user_register', array( __CLASS__, 'save_signup' ), 5 );

		// My account: the preference, both ways.
		add_action( 'woocommerce_edit_account_form', array( __CLASS__, 'render_account' ) );
		add_action( 'woocommerce_save_account_details', array( __CLASS__, 'save_account' ) );
	}


	/**
	 * Whether the owner asks customers for consent.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public static function asks() {
		return 'yes' === Cloud_Sync::setting( 'cloud_sync_consent_checkbox' );
	}


	/**
	 * The checkbox's text: the owner's, or the default.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public static function label() {
		$text = trim( Cloud_Sync::setting( 'cloud_sync_consent_text' ) );

		return '' !== $text ? $text : __( 'I want to receive offers and news on WhatsApp.', 'joinotify' );
	}


	/**
	 * Whether a user has agreed and not opted out since.
	 *
	 * @since 2.5.0
	 * @param int $user_id
	 * @return bool
	 */
	public static function user_consents( $user_id ) {
		return $user_id > 0
			&& ! empty( get_user_meta( $user_id, self::USER_META, true ) )
			&& empty( get_user_meta( $user_id, self::OPT_OUT_META, true ) );
	}


	/**
	 * The evidence of a checkbox ticked now.
	 *
	 * @since 2.5.0
	 * @param string $where `checkout`, `signup` or `my_account`.
	 * @param int $order_id
	 * @return array<string,mixed>
	 */
	private static function evidence( $where, $order_id = 0 ) {
		return array(
			'text' => self::label(),
			'where' => $where,
			'order_id' => (int) $order_id,
			'at' => time(),
		);
	}


	/**
	 * Record that a user agreed, clearing an earlier opt-out: ticking the box again is consent again.
	 *
	 * @since 2.5.0
	 * @param int $user_id
	 * @param array<string,mixed> $evidence
	 * @return void
	 */
	private static function record_user( $user_id, $evidence ) {
		if ( $user_id <= 0 ) {
			return;
		}

		update_user_meta( $user_id, self::USER_META, $evidence );
		delete_user_meta( $user_id, self::OPT_OUT_META );
	}


	/**
	 * The consent a contact block carries, as the platform reads it. Pure, for the harness.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed>|null $evidence What was stored when the box was ticked.
	 * @param bool $withdrawn Unticked in this request.
	 * @param string $host The site's host.
	 * @return array<string,mixed>|null
	 */
	public static function block_consent( $evidence, $withdrawn, $host ) {
		if ( $withdrawn ) {
			return array( 'status' => 'opted_out', 'reason' => 'my_account' );
		}

		if ( ! is_array( $evidence ) || '' === trim( (string) ( $evidence['text'] ?? '' ) ) ) {
			return null;
		}

		$where = (string) ( $evidence['where'] ?? '' );
		$order_id = (int) ( $evidence['order_id'] ?? 0 );
		$places = array(
			'checkout' => $order_id > 0 ? sprintf( 'checkout, order #%d', $order_id ) : 'checkout',
			'signup' => 'sign-up',
			'my_account' => 'my account',
			'joinotify' => 'Joinotify',
		);
		$at = (int) ( $evidence['at'] ?? 0 );

		$consent = array(
			'status' => 'opted_in',
			'evidence' => mb_substr( implode( ' — ', array_filter( array(
				trim( (string) $evidence['text'] ),
				$places[ $where ] ?? $where,
				$host,
			) ) ), 0, 500 ),
		);

		if ( $at > 0 ) {
			$consent['at'] = gmdate( 'Y-m-d\TH:i:s\Z', $at );
		}

		return $consent;
	}


	/**
	 * Add the consent to a contact block: the order's checkbox, or the user's preference.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $block
	 * @param array<string,mixed> $fields What the block was built from (`order_id`, `user_id`).
	 * @return array<string,mixed>
	 */
	public static function attach( $block, $fields ) {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$user_id = (int) ( $fields['user_id'] ?? 0 );
		$order_id = (int) ( $fields['order_id'] ?? 0 );
		$evidence = null;

		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			$evidence = $order ? $order->get_meta( self::ORDER_META ) : null;
			$user_id = $user_id ?: ( $order ? (int) $order->get_customer_id() : 0 );
		}

		if ( $user_id > 0 && ! empty( get_user_meta( $user_id, self::OPT_OUT_META, true ) ) && ! isset( self::$withdrawn[ $user_id ] ) ) {
			// Opted out later than any checkbox: say nothing, the platform already knows.
			return $block;
		}

		if ( empty( $evidence ) && $user_id > 0 ) {
			$evidence = get_user_meta( $user_id, self::USER_META, true );
		}

		$consent = self::block_consent( is_array( $evidence ) ? $evidence : null, isset( self::$withdrawn[ $user_id ] ), $host );

		if ( null !== $consent ) {
			$block['consent'] = $consent;
		}

		return $block;
	}


	// ── The checkboxes ──────────────────────────────────────────────────────────────────────


	/**
	 * @since 2.5.0
	 * @param bool $checked
	 * @return void
	 */
	private static function checkbox( $checked = false ) {
		printf(
			'<p class="form-row joinotify-marketing-consent"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox"><input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="%1$s" value="1"%2$s /> <span>%3$s</span></label></p>',
			esc_attr( self::FIELD ),
			$checked ? ' checked="checked"' : '',
			esc_html( self::label() )
		);
	}


	/**
	 * Whether the checkbox came ticked in this request.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	private static function ticked() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read inside the form's own handler, which verified its nonce.
		return ! empty( $_POST[ self::FIELD ] );
	}


	/**
	 * Unticked, and hidden from whoever already agreed.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function render_checkout() {
		if ( ! self::user_consents( get_current_user_id() ) ) {
			self::checkbox();
		}
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Order $order
	 * @param array $data
	 * @return void
	 */
	public static function save_classic_checkout( $order, $data = array() ) {
		if ( ! self::ticked() ) {
			return;
		}

		$evidence = self::evidence( 'checkout' );
		$order->update_meta_data( self::ORDER_META, $evidence );
		self::record_user( (int) $order->get_customer_id(), $evidence );
	}


	/**
	 * @since 2.5.0
	 * @return void
	 */
	public static function register_block_field() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || self::user_consents( get_current_user_id() ) ) {
			return;
		}

		// `order` replaced `additional` as the location below the order notes.
		$location = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.2', '>=' ) ? 'order' : 'additional';

		try {
			woocommerce_register_additional_checkout_field( array(
				'id' => self::BLOCK_FIELD,
				'label' => self::label(),
				'location' => $location,
				'type' => 'checkbox',
				'required' => false,
			) );
		} catch ( \Throwable $e ) {
			error_log( 'Joinotify Cloud sync: the consent checkbox could not be added to the checkout block: ' . $e->getMessage() );
		}
	}


	/**
	 * @since 2.5.0
	 * @param string $key
	 * @param mixed $value
	 * @param string $group
	 * @param \WC_Data $object
	 * @return void
	 */
	public static function save_block_field( $key, $value, $group, $object ) {
		if ( self::BLOCK_FIELD !== $key || ! $value || ! $object instanceof \WC_Order ) {
			return;
		}

		$evidence = self::evidence( 'checkout' );
		$object->update_meta_data( self::ORDER_META, $evidence );
		$object->save_meta_data();
		self::record_user( (int) $object->get_customer_id(), $evidence );
	}


	/**
	 * @since 2.5.0
	 * @return void
	 */
	public static function render_signup() {
		self::checkbox();
	}


	/**
	 * @since 2.5.0
	 * @param int $user_id
	 * @return void
	 */
	public static function save_signup( $user_id ) {
		if ( self::ticked() ) {
			self::record_user( (int) $user_id, self::evidence( 'signup' ) );
		}
	}


	/**
	 * @since 2.5.0
	 * @return void
	 */
	public static function render_account() {
		self::checkbox( self::user_consents( get_current_user_id() ) );
	}


	/**
	 * Both ways: ticking agrees, unticking opts out.
	 *
	 * @since 2.5.0
	 * @param int $user_id
	 * @return void
	 */
	public static function save_account( $user_id ) {
		$user_id = (int) $user_id;
		$was = self::user_consents( $user_id );
		$is = self::ticked();

		if ( $is && ! $was ) {
			self::record_user( $user_id, self::evidence( 'my_account' ) );
			Emitters::user_changed( $user_id, 'marketing_consent' );
		} elseif ( ! $is && $was ) {
			delete_user_meta( $user_id, self::USER_META );
			update_user_meta( $user_id, self::OPT_OUT_META, time() );
			self::$withdrawn[ $user_id ] = true;
			Emitters::user_changed( $user_id, 'marketing_consent' );
		}
	}


	// ── The platform's side ─────────────────────────────────────────────────────────────────


	/**
	 * Subscribe the site's endpoint to consent changes while syncing.
	 *
	 * @since 2.5.0
	 * @param string[] $events
	 * @return string[]
	 */
	public static function webhook_events( $events ) {
		return Cloud_Sync::is_enabled() ? array_merge( (array) $events, self::WEBHOOK_EVENTS ) : (array) $events;
	}


	/**
	 * Bring an endpoint registered before the sync (or before it was switched off) to the events
	 * it should receive now. Only talks to the API when the list changed.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function sync_webhook_events() {
		if ( ! Helpers::cloud_api_ready() || ! Webhooks::is_registered() ) {
			return;
		}

		$events = Webhooks::events();
		sort( $events );
		$hash = md5( implode( ',', $events ) );

		if ( get_option( self::WEBHOOK_EVENTS_OPTION ) === $hash ) {
			return;
		}

		// First sight of this code on a site set up before it: the endpoint has the base list.
		if ( false === get_option( self::WEBHOOK_EVENTS_OPTION ) && ! Cloud_Sync::is_enabled() ) {
			update_option( self::WEBHOOK_EVENTS_OPTION, $hash, false );

			return;
		}

		$response = Cloud_Client::request( 'PATCH', '/webhook-endpoints/' . rawurlencode( (string) get_option( Webhooks::ENDPOINT_OPTION, '' ) ), array( 'events' => $events ), 15 );
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			update_option( self::WEBHOOK_EVENTS_OPTION, $hash, false );
		}
	}


	/**
	 * Mirror a consent change made on the platform on the site's users.
	 *
	 * @since 2.5.0
	 * @param string $field
	 * @param array<string,mixed> $value
	 * @return void
	 */
	public static function on_webhook( $field, $value ) {
		if ( ! in_array( $field, self::WEBHOOK_EVENTS, true ) || ! is_array( $value ) ) {
			return;
		}

		$contact = is_array( $value['contact'] ?? null ) ? $value['contact'] : array();

		foreach ( self::users_of( (string) ( $contact['email'] ?? '' ), (string) ( $contact['phone'] ?? '' ) ) as $user_id ) {
			if ( 'contact.opted_out' === $field ) {
				delete_user_meta( $user_id, self::USER_META );
				update_user_meta( $user_id, self::OPT_OUT_META, time() );
			} else {
				delete_user_meta( $user_id, self::OPT_OUT_META );

				if ( empty( get_user_meta( $user_id, self::USER_META, true ) ) ) {
					update_user_meta( $user_id, self::USER_META, array(
						'text' => __( 'Opted in on Joinotify', 'joinotify' ),
						'where' => 'joinotify',
						'order_id' => 0,
						'at' => time(),
					) );
				}
			}
		}
	}


	/**
	 * The users a platform contact is: by e-mail, then by the phone they keep.
	 *
	 * @since 2.5.0
	 * @param string $email
	 * @param string $phone In E.164, as the platform sends it.
	 * @return array<int,int>
	 */
	private static function users_of( $email, $phone ) {
		$ids = array();
		$user = '' !== $email ? get_user_by( 'email', $email ) : false;

		if ( $user ) {
			$ids[] = (int) $user->ID;
		}

		$digits = preg_replace( '/\D/', '', $phone );

		if ( strlen( $digits ) >= 8 ) {
			$tail = substr( $digits, -8 );
			$candidates = get_users( array(
				'fields' => 'ID',
				'number' => 20,
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => 'billing_phone', 'value' => substr( $tail, -4 ), 'compare' => 'LIKE' ),
					array( 'key' => 'joinotify_user_phone', 'value' => substr( $tail, -4 ), 'compare' => 'LIKE' ),
				),
			) );

			foreach ( $candidates as $candidate ) {
				$known = preg_replace( '/\D/', '', Payload::user_phone( (int) $candidate ) );

				// The platform's number carries the country; the store's often does not.
				if ( '' !== $known && self::same_phone( $known, $digits ) ) {
					$ids[] = (int) $candidate;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}


	/**
	 * Whether two numbers are the same line: equal, or one is the other with a country code in front.
	 * Pure, for the harness.
	 *
	 * @since 2.5.0
	 * @param string $a Digits.
	 * @param string $b Digits.
	 * @return bool
	 */
	public static function same_phone( $a, $b ) {
		$a = ltrim( (string) $a, '0' );
		$b = ltrim( (string) $b, '0' );

		if ( strlen( $a ) < 8 || strlen( $b ) < 8 ) {
			return false;
		}

		$long = strlen( $a ) >= strlen( $b ) ? $a : $b;
		$short = $long === $a ? $b : $a;

		return $long === $short || ( strlen( $long ) - strlen( $short ) <= 3 && substr( $long, -strlen( $short ) ) === $short );
	}


	// ── WordPress privacy tools ─────────────────────────────────────────────────────────────


	/**
	 * @since 2.5.0
	 * @param array $exporters
	 * @return array
	 */
	public static function add_exporter( $exporters ) {
		$exporters['joinotify-cloud-sync'] = array(
			'exporter_friendly_name' => __( 'Joinotify Cloud sync', 'joinotify' ),
			'callback' => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}


	/**
	 * @since 2.5.0
	 * @param array $erasers
	 * @return array
	 */
	public static function add_eraser( $erasers ) {
		$erasers['joinotify-cloud-sync'] = array(
			'eraser_friendly_name' => __( 'Joinotify Cloud sync', 'joinotify' ),
			'callback' => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}


	/**
	 * The consent records and what the outbox holds about an e-mail address.
	 *
	 * @since 2.5.0
	 * @param string $email
	 * @param int $page
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$items = array();
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			$evidence = get_user_meta( $user->ID, self::USER_META, true );
			$opt_out = (int) get_user_meta( $user->ID, self::OPT_OUT_META, true );
			$data = array();

			if ( is_array( $evidence ) ) {
				$data[] = array( 'name' => __( 'Marketing consent', 'joinotify' ), 'value' => (string) ( $evidence['text'] ?? '' ) );
				$data[] = array( 'name' => __( 'Consent given on', 'joinotify' ), 'value' => gmdate( 'Y-m-d H:i:s', (int) ( $evidence['at'] ?? 0 ) ) . ' UTC' );
			}

			if ( $opt_out > 0 ) {
				$data[] = array( 'name' => __( 'Opted out of marketing on', 'joinotify' ), 'value' => gmdate( 'Y-m-d H:i:s', $opt_out ) . ' UTC' );
			}

			if ( ! empty( $data ) ) {
				$items[] = array(
					'group_id' => 'joinotify-consent',
					'group_label' => __( 'Joinotify marketing consent', 'joinotify' ),
					'item_id' => 'joinotify-consent-' . $user->ID,
					'data' => $data,
				);
			}
		}

		foreach ( Outbox::rows_with_email( $email ) as $row ) {
			$items[] = array(
				'group_id' => 'joinotify-cloud-sync',
				'group_label' => __( 'Sent to Joinotify', 'joinotify' ),
				'item_id' => 'joinotify-outbox-' . $row['id'],
				'data' => array(
					array( 'name' => __( 'Event', 'joinotify' ), 'value' => (string) $row['name'] ),
					array( 'name' => __( 'Status', 'joinotify' ), 'value' => (string) $row['status'] ),
					array( 'name' => __( 'Recorded on', 'joinotify' ), 'value' => $row['created_at'] . ' UTC' ),
				),
			);
		}

		return array( 'data' => $items, 'done' => true );
	}


	/**
	 * The refs this site knows a person by — how the platform finds what to erase.
	 *
	 * @since 2.5.0
	 * @param string $email
	 * @param int $user_id
	 * @return array<int,array<string,string>>
	 */
	public static function refs_of( $email, $user_id ) {
		$refs = array();

		if ( $user_id > 0 ) {
			$refs[] = array( 'kind' => 'wp_user', 'id' => (string) $user_id );
			$refs[] = array( 'kind' => 'wc_customer', 'id' => (string) $user_id );
		}

		foreach ( array( 'wc_guest', 'lead' ) as $kind ) {
			$ref = Contact::anonymous_ref( $kind, $email, '' );

			if ( $ref ) {
				$refs[] = $ref;
			}
		}

		return $refs;
	}


	/**
	 * Forget a person: the consent records, the outbox rows, and the contacts this site linked on
	 * the platform.
	 *
	 * @since 2.5.0
	 * @param string $email
	 * @param int $page
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		$user_id = $user ? (int) $user->ID : 0;
		$removed = false;
		$retained = false;
		$messages = array();

		if ( $user_id > 0 ) {
			$removed = delete_user_meta( $user_id, self::USER_META ) || $removed;
			$removed = delete_user_meta( $user_id, self::OPT_OUT_META ) || $removed;
		}

		$removed = Outbox::erase_email( $email ) > 0 || $removed;

		// Only a site that ever reported to the platform can have linked anyone there.
		if ( '' !== (string) ( State::load()['site_id'] ?? '' ) && Helpers::cloud_api_ready() ) {
			$response = Cloud_Client::request( 'POST', '/sites/me/erasures', array( 'refs' => self::refs_of( $email, $user_id ) ), 20 );
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 200 === $code ) {
				$removed = ( (int) ( $body['data']['erased'] ?? 0 ) ) > 0 || $removed;
			} else {
				$retained = true;
				$messages[] = __( 'The contact could not be erased on Joinotify now. Erase it from the Joinotify panel, or run this request again later.', 'joinotify' );
			}
		}

		return array(
			'items_removed' => $removed,
			'items_retained' => $retained,
			'messages' => $messages,
			'done' => true,
		);
	}
}
