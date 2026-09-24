<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

use MeuMouse\Joinotify\Integrations\Flexify_Checkout;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The site events this site sends, and the integrations it has — what the site reports to the
 * platform (`PUT /sites/me`) so the flow editor can offer "Pedido pago" with its fields before a
 * single order arrives.
 *
 * The samples are **synthetic**: made-up values in the exact shape the payload builders produce,
 * never a real customer's order. A sample travels to the panel and sits in the flow editor, and a
 * real one would be personal data sent for no reason.
 *
 * Event names follow the platform's grammar (`<source>.<object>.<what happened>`) and are a
 * public contract once shipped: a field may be added to a sample, never renamed or removed without
 * a new `schema_version`.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Catalog {

	/**
	 * Version of every event's data shape.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const SCHEMA_VERSION = 1;


	/**
	 * Which of the integrations the platform cares about are active, with their versions.
	 *
	 * @since 2.5.0
	 * @return array<string,array<string,string>>
	 */
	public static function integrations() {
		$found = array();

		if ( class_exists( 'WooCommerce' ) ) {
			$found['woocommerce'] = array( 'version' => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '' );
		}

		if ( class_exists( 'WC_Subscriptions' ) ) {
			$found['woocommerce_subscriptions'] = array(
				'version' => isset( \WC_Subscriptions::$version ) ? (string) \WC_Subscriptions::$version : '',
			);
		}

		if ( function_exists( 'wpforms' ) ) {
			$found['wpforms'] = array( 'version' => defined( 'WPFORMS_VERSION' ) ? (string) WPFORMS_VERSION : '' );
		}

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$found['elementor_pro'] = array( 'version' => (string) ELEMENTOR_PRO_VERSION );
		}

		// The native module and the standalone add-on fire the same hooks; this checks for either.
		if ( Flexify_Checkout::is_recovery_carts_active() ) {
			$found['flexify_checkout'] = array( 'version' => defined( 'FLEXIFY_CHECKOUT_VERSION' ) ? (string) FLEXIFY_CHECKOUT_VERSION : '' );
		}

		/**
		 * Filter the integrations the site reports as active.
		 *
		 * @since 2.5.0
		 * @param array<string,array<string,string>> $found Integration id → `{ version }`.
		 */
		return (array) apply_filters( 'Joinotify/Cloud_Sync/Integrations', $found );
	}


	/**
	 * Every event the plugin knows how to send, by source, with its sample.
	 *
	 * @since 2.5.0
	 * @return array<string,array<string,array<string,mixed>>> Source → event name → sample data.
	 */
	public static function definitions() {
		$order = self::sample_order();
		$subscription = self::sample_subscription();

		return array(
			'woocommerce' => array(
				'wc.order.created' => array( 'order' => $order, 'customer' => self::sample_customer(), 'links' => self::sample_links() ),
				'wc.order.paid' => array( 'order' => array_merge( $order, array( 'status' => 'processing', 'date_paid' => '2026-09-24T12:05:00Z' ) ), 'customer' => self::sample_customer(), 'links' => self::sample_links() ),
				'wc.order.status_changed' => array( 'order' => $order, 'status_from' => 'pending', 'status_to' => 'processing', 'customer' => self::sample_customer(), 'links' => self::sample_links() ),
				'wc.order.completed' => array( 'order' => array_merge( $order, array( 'status' => 'completed' ) ), 'customer' => self::sample_customer(), 'links' => self::sample_links() ),
				'wc.order.cancelled' => array( 'order' => array_merge( $order, array( 'status' => 'cancelled' ) ), 'customer' => self::sample_customer(), 'links' => self::sample_links() ),
				'wc.order.failed' => array( 'order' => array_merge( $order, array( 'status' => 'failed' ) ), 'customer' => self::sample_customer(), 'links' => self::sample_links() ),
				'wc.order.refunded' => array( 'order' => array_merge( $order, array( 'status' => 'refunded' ) ), 'refund' => array( 'id' => 1235, 'amount' => '189.90', 'reason' => 'Produto com defeito', 'full' => true ), 'customer' => self::sample_customer() ),
				'wc.customer.created' => array( 'customer' => array_merge( self::sample_user(), array( 'orders_count' => 0 ) ) ),
			),
			'woocommerce_subscriptions' => array(
				'wcs.subscription.created' => array( 'subscription' => $subscription, 'order' => $order ),
				'wcs.subscription.activated' => array( 'subscription' => array_merge( $subscription, array( 'status' => 'active' ) ) ),
				'wcs.subscription.payment_complete' => array( 'subscription' => array_merge( $subscription, array( 'status' => 'active' ) ), 'order' => $order ),
				'wcs.subscription.payment_failed' => array( 'subscription' => array_merge( $subscription, array( 'status' => 'on-hold' ) ), 'order' => array_merge( $order, array( 'status' => 'failed' ) ), 'links' => self::sample_links() ),
				'wcs.subscription.expired' => array( 'subscription' => array_merge( $subscription, array( 'status' => 'expired' ) ) ),
				'wcs.subscription.cancelled' => array( 'subscription' => array_merge( $subscription, array( 'status' => 'cancelled' ) ) ),
			),
			'wordpress' => array(
				'wp.user.registered' => array( 'user' => self::sample_user() ),
				'wp.user.updated' => array( 'user' => self::sample_user(), 'changed' => array( 'phone' ) ),
			),
			'forms' => array(
				'form.submitted' => array(
					'form' => array( 'plugin' => 'wpforms', 'id' => '12', 'title' => 'Fale conosco' ),
					'entry_id' => '345',
					'fields' => array(
						'name' => 'Ana Souza',
						'email' => 'ana@example.com',
						'phone' => '+5511987654321',
						'message' => 'Quero um orçamento',
					),
					'page_url' => 'https://example.com/contato',
				),
			),
			'flexify_checkout' => array(
				'fcrc.lead.collected' => array( 'cart' => self::sample_cart() ),
				'fcrc.cart.abandoned' => array( 'cart' => self::sample_cart(), 'order' => $order, 'links' => self::sample_links() ),
				'fcrc.cart.recovered' => array( 'cart' => self::sample_cart(), 'order' => $order ),
				'fcrc.cart.lost' => array( 'cart' => self::sample_cart() ),
			),
		);
	}


	/**
	 * The catalog to report: the events of the sources this site has active and syncs.
	 *
	 * @since 2.5.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function entries() {
		$active = self::integrations();
		$entries = array();

		foreach ( self::definitions() as $source => $events ) {
			if ( ! self::source_on( $source, $active ) ) {
				continue;
			}

			foreach ( $events as $name => $sample ) {
				$entries[] = array(
					'name' => $name,
					'schemaVersion' => self::SCHEMA_VERSION,
					'sample' => $sample,
				);
			}
		}

		/**
		 * Filter the event catalog the site reports — where an extension that sends `custom.*`
		 * events through `joinotify_track()` describes them.
		 *
		 * @since 2.5.0
		 * @param array<int,array<string,mixed>> $entries `{ name, schemaVersion, sample }`.
		 */
		return array_values( (array) apply_filters( 'Joinotify/Cloud_Sync/Catalog', $entries ) );
	}


	/**
	 * Whether a source's events are sent: its plugin is active and the owner left it on.
	 *
	 * @since 2.5.0
	 * @param string $source
	 * @param array<string,mixed> $active Result of integrations().
	 * @return bool
	 */
	public static function source_on( $source, $active ) {
		switch ( $source ) {
			case 'woocommerce':
				return isset( $active['woocommerce'] ) && Cloud_Sync::syncs( 'woocommerce' );
			case 'woocommerce_subscriptions':
				return isset( $active['woocommerce_subscriptions'] ) && Cloud_Sync::syncs( 'woocommerce' );
			case 'wordpress':
				return Cloud_Sync::syncs( 'wordpress' );
			case 'forms':
				return ( isset( $active['wpforms'] ) || isset( $active['elementor_pro'] ) ) && Cloud_Sync::syncs( 'forms' );
			case 'flexify_checkout':
				return isset( $active['flexify_checkout'] ) && Cloud_Sync::syncs( 'carts' );
		}

		return false;
	}


	/**
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	private static function sample_order() {
		return array(
			'id' => 1234,
			'number' => '1234',
			'status' => 'pending',
			'currency' => 'BRL',
			'total' => '189.90',
			'subtotal' => '179.90',
			'discount_total' => '10.00',
			'shipping_total' => '20.00',
			'total_tax' => '0.00',
			'payment_method' => 'pix',
			'payment_method_title' => 'Pix',
			'date_created' => '2026-09-24T12:00:00Z',
			'date_paid' => null,
			'customer_id' => 42,
			'customer_note' => '',
			'billing' => array(
				'first_name' => 'Ana',
				'last_name' => 'Souza',
				'email' => 'ana@example.com',
				'phone' => '+5511987654321',
				'city' => 'São Paulo',
				'state' => 'SP',
				'country' => 'BR',
			),
			'shipping' => array(
				'city' => 'São Paulo',
				'state' => 'SP',
				'country' => 'BR',
			),
			'shipping_lines' => array(
				array( 'method_id' => 'flat_rate', 'method_title' => 'SEDEX', 'total' => '20.00' ),
			),
			'line_items' => array(
				array(
					'product_id' => 77,
					'variation_id' => 0,
					'sku' => 'CAFE-500',
					'name' => 'Café especial 500 g',
					'quantity' => 2,
					'price' => '89.95',
					'total' => '179.90',
					'categories' => array( 'Cafés' ),
				),
			),
			'coupon_lines' => array(
				array( 'code' => 'BEMVINDO10', 'discount' => '10.00' ),
			),
		);
	}


	/**
	 * @since 2.5.0
	 * @param int $orders Orders the sample customer has.
	 * @return array<string,mixed>
	 */
	private static function sample_customer( $orders = 3 ) {
		return array(
			'id' => 42,
			'orders_count' => $orders,
			'total_spent' => $orders > 0 ? '540.90' : '0.00',
			'avg_order_value' => $orders > 0 ? '180.30' : '0.00',
			'first_order_at' => $orders > 0 ? '2026-03-02T15:10:00Z' : null,
			'last_order_at' => $orders > 0 ? '2026-09-24T12:00:00Z' : null,
			'last_order_status' => $orders > 0 ? 'completed' : null,
			'is_first_order' => 1 === $orders,
		);
	}


	/**
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	private static function sample_links() {
		return array(
			'payment_url' => 'https://example.com/checkout/order-pay/1234/?pay_for_order=true&key=wc_order_example',
			'order_received_url' => 'https://example.com/checkout/order-received/1234/?key=wc_order_example',
			'my_account_url' => 'https://example.com/minha-conta/view-order/1234/',
			'review_urls' => array( 'https://example.com/produto/cafe-especial/#reviews' ),
		);
	}


	/**
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	private static function sample_subscription() {
		return array(
			'id' => 900,
			'status' => 'pending',
			'total' => '59.90',
			'currency' => 'BRL',
			'billing_period' => 'month',
			'billing_interval' => 1,
			'next_payment_at' => '2026-10-24T12:00:00Z',
			'customer_id' => 42,
			'billing' => array(
				'first_name' => 'Ana',
				'last_name' => 'Souza',
				'email' => 'ana@example.com',
				'phone' => '+5511987654321',
				'city' => 'São Paulo',
				'state' => 'SP',
				'country' => 'BR',
			),
			'line_items' => array(
				array( 'product_id' => 88, 'variation_id' => 0, 'sku' => 'CLUBE-CAFE', 'name' => 'Clube do café', 'quantity' => 1, 'price' => '59.90', 'total' => '59.90', 'categories' => array( 'Assinaturas' ) ),
			),
		);
	}


	/**
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	private static function sample_user() {
		return array(
			'id' => 42,
			'email' => 'ana@example.com',
			'first_name' => 'Ana',
			'last_name' => 'Souza',
			'roles' => array( 'customer' ),
			'registered_at' => '2026-09-24T12:00:00Z',
		);
	}


	/**
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	private static function sample_cart() {
		return array(
			'id' => 555,
			'total' => '189.90',
			'currency' => 'BRL',
			'recovery_url' => 'https://example.com/checkout/?recovery_cart=555',
			'first_name' => 'Ana',
			'last_name' => 'Souza',
			'email' => 'ana@example.com',
			'phone' => '+5511987654321',
		);
	}
}
