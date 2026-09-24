<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

use MeuMouse\Joinotify\Integrations\Flexify_Checkout;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Turns what happens on the site into site events in the outbox.
 *
 * Every callback does the same small thing: build the event's data and contact block, and write one
 * row (`Outbox::push_event`). No request leaves the site here, so a checkout is as fast with the
 * sync on as with it off.
 *
 * The emitters listen to the platform hooks directly, not to the builder's triggers: those only
 * run when the matching integration is switched on for local workflows, and carry ids, not data.
 *
 * What the platform receives once is decided here, where the order is: `wc.order.created` and
 * `wc.order.paid` go out once per order (a flag on the order, through the CRUD API so HPOS stores
 * it), however many times the status goes back and forth. Status changes go out every time — a
 * flow that wants "once" per order sets its business key on the trigger.
 *
 * WordPress users are sent at the end of the request, not in `user_register`: WooCommerce and the
 * profile screens write the phone and the name after the user exists, and an event built at that
 * instant would carry nobody to reach.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Emitters {

	/**
	 * Order meta flagging the one-time events already queued.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const META_CREATED = '_joinotify_sync_created';
	const META_PAID = '_joinotify_sync_paid';

	/**
	 * User meta and fields whose change is worth a `wp.user.updated`.
	 *
	 * @since 2.5.0
	 * @var array<int,string>
	 */
	const USER_KEYS = array( 'billing_phone', 'joinotify_user_phone', 'first_name', 'last_name', 'billing_first_name', 'billing_last_name' );

	/**
	 * Customer snapshots built in this request, by order id — several events of one status change
	 * share it instead of querying the customer's orders again.
	 *
	 * @since 2.5.0
	 * @var array<int,array<string,mixed>>
	 */
	private static $customers = array();

	/**
	 * Users registered, and users changed, in this request — sent at shutdown.
	 *
	 * @since 2.5.0
	 * @var array<int,bool>
	 */
	private static $registered = array();

	/**
	 * @since 2.5.0
	 * @var array<int,array<string,bool>>
	 */
	private static $changed = array();


	/**
	 * Subscribe to the hooks of each source the owner syncs.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function subscribe() {
		if ( Cloud_Sync::syncs( 'woocommerce' ) && class_exists( 'WooCommerce' ) ) {
			self::listen( 'woocommerce_checkout_order_processed', 'on_checkout', 3 );
			// The checkout block goes through the Store API and never fires the classic hook.
			self::listen( 'woocommerce_store_api_checkout_order_processed', 'on_store_api_checkout', 1 );
			self::listen( 'woocommerce_order_status_changed', 'on_status_changed', 4 );
			self::listen( 'woocommerce_payment_complete', 'on_payment_complete', 1 );
			self::listen( 'woocommerce_order_refunded', 'on_refunded', 2 );
			self::listen( 'woocommerce_created_customer', 'on_customer_created', 1 );

			if ( class_exists( 'WC_Subscriptions' ) ) {
				self::listen( 'woocommerce_checkout_subscription_created', 'on_subscription_created', 2 );
				self::listen( 'woocommerce_subscription_status_active', 'on_subscription_active', 1 );
				self::listen( 'woocommerce_subscription_payment_complete', 'on_subscription_payment_complete', 1 );
				self::listen( 'woocommerce_subscription_payment_failed', 'on_subscription_payment_failed', 1 );
				self::listen( 'woocommerce_subscription_status_expired', 'on_subscription_expired', 1 );
				self::listen( 'woocommerce_subscription_status_cancelled', 'on_subscription_cancelled', 1 );
			}
		}

		if ( Cloud_Sync::syncs( 'wordpress' ) ) {
			self::listen( 'user_register', 'on_user_register', 1 );
			self::listen( 'profile_update', 'on_profile_update', 2 );
			self::listen( 'added_user_meta', 'on_user_meta', 3 );
			self::listen( 'updated_user_meta', 'on_user_meta', 3 );
		}

		if ( Cloud_Sync::syncs( 'forms' ) ) {
			self::listen( 'wpforms_process_complete', 'on_wpforms', 4 );
			self::listen( 'elementor_pro/forms/new_record', 'on_elementor', 1 );
		}

		if ( Cloud_Sync::syncs( 'carts' ) && Flexify_Checkout::is_recovery_carts_active() ) {
			self::listen( 'Flexify_Checkout/Recovery_Carts/Lead_Collected', 'on_cart_lead', 2 );
			self::listen( 'Flexify_Checkout/Recovery_Carts/Checkout_Lead_Collected', 'on_cart_lead', 2 );
			self::listen( 'Flexify_Checkout/Recovery_Carts/Cart_Abandoned', 'on_cart_abandoned', 1 );
			self::listen( 'Flexify_Checkout/Recovery_Carts/Order_Abandoned', 'on_order_abandoned', 2 );
			self::listen( 'Flexify_Checkout/Recovery_Carts/Cart_Recovered', 'on_cart_recovered', 2 );
			self::listen( 'Flexify_Checkout/Recovery_Carts/Cart_Lost', 'on_cart_lost', 1 );
		}
	}


	/**
	 * Listen to a hook without ever breaking it: an emitter runs inside a checkout, a sign-up or a
	 * form submission, and a failure building an event must cost the event, never the customer's
	 * order. What failed goes to the PHP error log.
	 *
	 * @since 2.5.0
	 * @param string $hook
	 * @param string $method A public static method of this class.
	 * @param int $args
	 * @return void
	 */
	private static function listen( $hook, $method, $args ) {
		add_action( $hook, static function( ...$params ) use ( $hook, $method ) {
			try {
				call_user_func_array( array( __CLASS__, $method ), $params );
			} catch ( \Throwable $e ) {
				error_log( sprintf( 'Joinotify Cloud sync: %s on %s failed: %s', $method, $hook, $e->getMessage() ) );
			}
		}, 20, $args );
	}


	// ── Custom events ───────────────────────────────────────────────────────────────────────


	/**
	 * Whether a name is one an extension may send: `custom.` and up to three lowercase segments.
	 *
	 * @since 2.5.0
	 * @param string $name
	 * @return bool
	 */
	public static function is_custom_name( $name ) {
		return is_string( $name ) && 1 === preg_match( '/^custom(\.[a-z0-9_]+){1,3}$/', $name );
	}


	/**
	 * Queue an event an extension describes — behind `joinotify_track()`.
	 *
	 * @since 2.5.0
	 * @param string $name `custom.*`.
	 * @param array<string,mixed> $data
	 * @param array<string,mixed>|null $contact The contact block (`ref`, `phone`, `email`, …).
	 * @return string|false The event id, or false with syncing off or a name out of the `custom.` space.
	 */
	public static function track( $name, $data = array(), $contact = null ) {
		if ( ! Cloud_Sync::is_enabled() || ! self::is_custom_name( $name ) ) {
			return false;
		}

		return Outbox::push_event( $name, (array) $data, is_array( $contact ) && ! empty( $contact ) ? $contact : null );
	}


	// ── WooCommerce ─────────────────────────────────────────────────────────────────────────


	/**
	 * The customer snapshot of an order, once per request.
	 *
	 * @since 2.5.0
	 * @param \WC_Order $order
	 * @return array<string,mixed>
	 */
	private static function customer( $order ) {
		$id = (int) $order->get_id();

		if ( ! isset( self::$customers[ $id ] ) ) {
			self::$customers[ $id ] = Payload::customer( $order );
		}

		return self::$customers[ $id ];
	}


	/**
	 * Queue an order event: the order, the customer's history, the links, and who they are.
	 *
	 * @since 2.5.0
	 * @param string $name
	 * @param \WC_Order $order
	 * @param array<string,mixed> $extra More data for this event.
	 * @return void
	 */
	private static function emit_order( $name, $order, $extra = array() ) {
		$customer = self::customer( $order );

		Outbox::push_event(
			$name,
			array_merge( array(
				'order' => Payload::order( $order ),
				'customer' => $customer,
				'links' => Payload::links( $order ),
			), $extra ),
			Contact::from_order( $order, $customer )
		);
	}


	/**
	 * Queue a one-time event of an order, unless it already went.
	 *
	 * @since 2.5.0
	 * @param string $name
	 * @param string $meta The flag.
	 * @param \WC_Order $order
	 * @return void
	 */
	private static function emit_once( $name, $meta, $order ) {
		if ( $order->get_meta( $meta ) ) {
			return;
		}

		$order->update_meta_data( $meta, time() );
		$order->save_meta_data();
		self::emit_order( $name, $order );
	}


	/**
	 * A real order: not a refund, not a draft of the checkout block.
	 *
	 * @since 2.5.0
	 * @param mixed $order
	 * @return \WC_Order|null
	 */
	private static function order( $order ) {
		$order = is_numeric( $order ) ? wc_get_order( (int) $order ) : $order;

		if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) {
			return null;
		}

		return 'checkout-draft' === $order->get_status() ? null : $order;
	}


	/**
	 * @since 2.5.0
	 * @param int $order_id
	 * @param array $posted
	 * @param \WC_Order|null $order
	 * @return void
	 */
	public static function on_checkout( $order_id, $posted = array(), $order = null ) {
		$order = self::order( $order ?: $order_id );

		if ( $order ) {
			self::emit_once( 'wc.order.created', self::META_CREATED, $order );
		}
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Order $order
	 * @return void
	 */
	public static function on_store_api_checkout( $order ) {
		$order = self::order( $order );

		if ( $order ) {
			self::emit_once( 'wc.order.created', self::META_CREATED, $order );
		}
	}


	/**
	 * Every status change, plus the named events a flow listens to most.
	 *
	 * @since 2.5.0
	 * @param int $order_id
	 * @param string $from
	 * @param string $to
	 * @param \WC_Order|null $order
	 * @return void
	 */
	public static function on_status_changed( $order_id, $from, $to, $order = null ) {
		$order = self::order( $order ?: $order_id );

		if ( ! $order ) {
			return;
		}

		// The history changes with the status: a snapshot taken before it is stale now.
		unset( self::$customers[ (int) $order->get_id() ] );

		// A status change made at the checkout comes before `created` in some gateways.
		if ( ! $order->get_meta( self::META_CREATED ) && ! is_admin() ) {
			self::emit_once( 'wc.order.created', self::META_CREATED, $order );
		}

		self::emit_order( 'wc.order.status_changed', $order, array(
			'status_from' => Payload::status( $from ),
			'status_to' => Payload::status( $to ),
		) );

		$paid = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );

		if ( in_array( Payload::status( $to ), $paid, true ) ) {
			self::emit_once( 'wc.order.paid', self::META_PAID, $order );
		}

		$named = array(
			'completed' => 'wc.order.completed',
			'cancelled' => 'wc.order.cancelled',
			'failed' => 'wc.order.failed',
		);

		if ( isset( $named[ Payload::status( $to ) ] ) ) {
			self::emit_order( $named[ Payload::status( $to ) ], $order );
		}
	}


	/**
	 * A gateway that confirms payment without a status change of its own.
	 *
	 * @since 2.5.0
	 * @param int $order_id
	 * @return void
	 */
	public static function on_payment_complete( $order_id ) {
		$order = self::order( $order_id );

		if ( $order ) {
			self::emit_once( 'wc.order.paid', self::META_PAID, $order );
		}
	}


	/**
	 * @since 2.5.0
	 * @param int $order_id
	 * @param int $refund_id
	 * @return void
	 */
	public static function on_refunded( $order_id, $refund_id ) {
		$order = self::order( $order_id );
		$refund = wc_get_order( $refund_id );

		if ( ! $order || ! $refund instanceof \WC_Order_Refund ) {
			return;
		}

		self::emit_order( 'wc.order.refunded', $order, array(
			'refund' => array(
				'id' => (int) $refund->get_id(),
				'amount' => Payload::money( $refund->get_amount() ),
				'reason' => (string) $refund->get_reason(),
				'full' => (float) $order->get_total_refunded() >= (float) $order->get_total(),
			),
		) );
	}


	/**
	 * @since 2.5.0
	 * @param int $customer_id
	 * @return void
	 */
	public static function on_customer_created( $customer_id ) {
		$user = get_userdata( (int) $customer_id );

		if ( ! $user ) {
			return;
		}

		$contact = Contact::from_user( $user );

		if ( is_array( $contact ) ) {
			// The same person as the orders they place: a WooCommerce customer, not only a user.
			$contact['ref'] = array( 'kind' => 'wc_customer', 'id' => (string) $user->ID );
		}

		Outbox::push_event( 'wc.customer.created', array( 'customer' => array_merge( Payload::user( $user ), array( 'orders_count' => 0 ) ) ), $contact );
	}


	// ── Subscriptions ───────────────────────────────────────────────────────────────────────


	/**
	 * Queue a subscription event.
	 *
	 * @since 2.5.0
	 * @param string $name
	 * @param \WC_Subscription $subscription
	 * @param array<string,mixed> $extra
	 * @return void
	 */
	private static function emit_subscription( $name, $subscription, $extra = array() ) {
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_time' ) ) {
			return;
		}

		Outbox::push_event(
			$name,
			array_merge( array( 'subscription' => Payload::subscription( $subscription ) ), $extra ),
			Contact::from_subscription( $subscription )
		);
	}


	/**
	 * The order that last renewed a subscription — what a "pay now" message links to.
	 *
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return \WC_Order|null
	 */
	private static function last_order( $subscription ) {
		$order = method_exists( $subscription, 'get_last_order' ) ? $subscription->get_last_order( 'all' ) : null;

		return self::order( $order );
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @param \WC_Order $order
	 * @return void
	 */
	public static function on_subscription_created( $subscription, $order = null ) {
		$order = self::order( $order );
		self::emit_subscription( 'wcs.subscription.created', $subscription, $order ? array( 'order' => Payload::order( $order ) ) : array() );
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return void
	 */
	public static function on_subscription_active( $subscription ) {
		self::emit_subscription( 'wcs.subscription.activated', $subscription );
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return void
	 */
	public static function on_subscription_payment_complete( $subscription ) {
		$order = self::last_order( $subscription );
		self::emit_subscription( 'wcs.subscription.payment_complete', $subscription, $order ? array( 'order' => Payload::order( $order ) ) : array() );
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return void
	 */
	public static function on_subscription_payment_failed( $subscription ) {
		$order = self::last_order( $subscription );
		self::emit_subscription( 'wcs.subscription.payment_failed', $subscription, $order ? array(
			'order' => Payload::order( $order ),
			'links' => Payload::links( $order ),
		) : array() );
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return void
	 */
	public static function on_subscription_expired( $subscription ) {
		self::emit_subscription( 'wcs.subscription.expired', $subscription );
	}


	/**
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return void
	 */
	public static function on_subscription_cancelled( $subscription ) {
		self::emit_subscription( 'wcs.subscription.cancelled', $subscription );
	}


	// ── WordPress users ─────────────────────────────────────────────────────────────────────


	/**
	 * Wait for the end of the request before sending anything about a user.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private static function defer_users() {
		if ( ! has_action( 'shutdown', array( __CLASS__, 'flush_users' ) ) ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_users' ), 5 );
		}
	}


	/**
	 * @since 2.5.0
	 * @param int $user_id
	 * @return void
	 */
	public static function on_user_register( $user_id ) {
		self::$registered[ (int) $user_id ] = true;
		self::defer_users();
	}


	/**
	 * @since 2.5.0
	 * @param int $user_id
	 * @param \WP_User|null $old
	 * @return void
	 */
	public static function on_profile_update( $user_id, $old = null ) {
		$user = get_userdata( (int) $user_id );

		if ( $user && $old instanceof \WP_User && strtolower( (string) $old->user_email ) !== strtolower( (string) $user->user_email ) ) {
			self::$changed[ (int) $user_id ]['email'] = true;
			self::defer_users();
		}
	}


	/**
	 * @since 2.5.0
	 * @param int $meta_id
	 * @param int $user_id
	 * @param string $meta_key
	 * @return void
	 */
	public static function on_user_meta( $meta_id, $user_id, $meta_key ) {
		if ( ! in_array( $meta_key, self::USER_KEYS, true ) ) {
			return;
		}

		$field = false !== strpos( $meta_key, 'phone' ) ? 'phone' : ( false !== strpos( $meta_key, 'first' ) ? 'first_name' : 'last_name' );
		self::$changed[ (int) $user_id ][ $field ] = true;
		self::defer_users();
	}


	/**
	 * Send the users this request registered or changed.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function flush_users() {
		foreach ( array_keys( self::$registered ) as $user_id ) {
			$user = get_userdata( $user_id );

			if ( $user ) {
				Outbox::push_event( 'wp.user.registered', array( 'user' => Payload::user( $user ) ), Contact::from_user( $user ) );
			}
		}

		foreach ( self::$changed as $user_id => $fields ) {
			// A user registered in the same request is already going out whole.
			if ( isset( self::$registered[ $user_id ] ) ) {
				continue;
			}

			$user = get_userdata( $user_id );

			if ( $user ) {
				Outbox::push_event( 'wp.user.updated', array(
					'user' => Payload::user( $user ),
					'changed' => array_keys( $fields ),
				), Contact::from_user( $user ) );
			}
		}

		self::$registered = array();
		self::$changed = array();
	}


	// ── Forms ───────────────────────────────────────────────────────────────────────────────


	/**
	 * The page the form was sent from, when the browser said.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	private static function page_url() {
		$referer = wp_get_raw_referer();

		return $referer ? esc_url_raw( $referer ) : '';
	}


	/**
	 * Queue a form submission that says who sent it.
	 *
	 * @since 2.5.0
	 * @param array<string,string> $form `plugin`, `id`, `title`.
	 * @param array<int,array<string,string>> $fields `{ id, label, type, value }`.
	 * @param string $entry_id
	 * @return void
	 */
	private static function emit_form( $form, $fields, $entry_id ) {
		$parsed = Payload::form_fields( $fields );

		// A form with neither phone nor e-mail says nothing about anyone the account can reach.
		if ( '' === $parsed['phone'] && '' === $parsed['email'] ) {
			return;
		}

		$name = preg_split( '/\s+/', trim( $parsed['name'] ), 2 );

		Outbox::push_event( 'form.submitted', array(
			'form' => $form,
			'entry_id' => (string) $entry_id,
			'fields' => $parsed['fields'],
			'page_url' => self::page_url(),
		), Contact::from_lead( array(
			'phone' => $parsed['phone'],
			'email' => $parsed['email'],
			'first_name' => $name[0] ?? '',
			'last_name' => $name[1] ?? '',
		), get_current_user_id() ) );
	}


	/**
	 * @since 2.5.0
	 * @param array $fields
	 * @param array $entry
	 * @param array $form_data
	 * @param int $entry_id
	 * @return void
	 */
	public static function on_wpforms( $fields, $entry, $form_data, $entry_id ) {
		$flat = array();

		foreach ( (array) $fields as $id => $field ) {
			$flat[] = array(
				'id' => (string) $id,
				'label' => (string) ( $field['name'] ?? '' ),
				'type' => (string) ( $field['type'] ?? '' ),
				'value' => is_scalar( $field['value'] ?? '' ) ? (string) ( $field['value'] ?? '' ) : '',
			);
		}

		self::emit_form( array(
			'plugin' => 'wpforms',
			'id' => (string) ( $form_data['id'] ?? '' ),
			'title' => (string) ( $form_data['settings']['form_title'] ?? '' ),
		), $flat, (string) $entry_id );
	}


	/**
	 * @since 2.5.0
	 * @param object $record Elementor Pro's form record.
	 * @return void
	 */
	public static function on_elementor( $record ) {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return;
		}

		$flat = array();

		foreach ( (array) $record->get( 'fields' ) as $id => $field ) {
			$flat[] = array(
				'id' => (string) ( $field['id'] ?? $id ),
				'label' => (string) ( $field['title'] ?? '' ),
				'type' => (string) ( $field['type'] ?? '' ),
				'value' => is_scalar( $field['value'] ?? '' ) ? (string) ( $field['value'] ?? '' ) : '',
			);
		}

		self::emit_form( array(
			'plugin' => 'elementor',
			'id' => (string) $record->get_form_settings( 'id' ),
			'title' => (string) $record->get_form_settings( 'form_name' ),
		), $flat, '' );
	}


	// ── Flexify Checkout carts ──────────────────────────────────────────────────────────────


	/**
	 * Queue a cart event, with the cart's contact.
	 *
	 * @since 2.5.0
	 * @param string $name
	 * @param int $cart_id
	 * @param array<string,mixed> $extra
	 * @return void
	 */
	private static function emit_cart( $name, $cart_id, $extra = array() ) {
		$cart = Payload::cart( (int) $cart_id );

		// Always a lead: most cart events fire from a scheduled task, where the current user is
		// nobody, and the cart stores no owner to trust. The platform still finds an existing
		// customer by the phone.
		Outbox::push_event( $name, array_merge( array( 'cart' => $cart ), $extra ), Contact::from_lead( array(
			'phone' => $cart['phone'],
			'email' => $cart['email'],
			'first_name' => $cart['first_name'],
			'last_name' => $cart['last_name'],
		) ) );
	}


	/**
	 * @since 2.5.0
	 * @param int $cart_id
	 * @return void
	 */
	public static function on_cart_lead( $cart_id ) {
		self::emit_cart( 'fcrc.lead.collected', $cart_id );
	}


	/**
	 * @since 2.5.0
	 * @param int $cart_id
	 * @return void
	 */
	public static function on_cart_abandoned( $cart_id ) {
		self::emit_cart( 'fcrc.cart.abandoned', $cart_id );
	}


	/**
	 * An order left unpaid is an abandoned cart with an order attached.
	 *
	 * @since 2.5.0
	 * @param int $order_id
	 * @param int $cart_id
	 * @return void
	 */
	public static function on_order_abandoned( $order_id, $cart_id ) {
		$order = self::order( $order_id );
		self::emit_cart( 'fcrc.cart.abandoned', $cart_id, $order ? array(
			'order' => Payload::order( $order ),
			'links' => Payload::links( $order ),
		) : array() );
	}


	/**
	 * @since 2.5.0
	 * @param int $cart_id
	 * @param int $order_id
	 * @return void
	 */
	public static function on_cart_recovered( $cart_id, $order_id ) {
		$order = self::order( $order_id );
		self::emit_cart( 'fcrc.cart.recovered', $cart_id, $order ? array( 'order' => Payload::order( $order ) ) : array() );
	}


	/**
	 * @since 2.5.0
	 * @param int $cart_id
	 * @return void
	 */
	public static function on_cart_lost( $cart_id ) {
		self::emit_cart( 'fcrc.cart.lost', $cart_id );
	}
}
