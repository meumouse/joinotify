<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The data of the site's events and contact blocks, built from WooCommerce and WordPress objects.
 *
 * Raw values in the shape of WooCommerce's REST API (`order.billing.phone`, `line_items[0].name`,
 * totals as decimal strings, dates as ISO 8601 in UTC) — the same paths the platform's webhook
 * trigger documents for WooCommerce, so a flow built for one works with the other. Never the
 * builder's placeholders: those are formatted for display (localized prices, joined lines) and some
 * read the logged-in user, which is nobody in a cron run.
 *
 * What the owner chose to keep out stays out here, before anything is queued: the items of an order
 * and the full addresses (`cloud_sync_send_items`, `cloud_sync_send_address`). City and state always
 * go — the audiences by region need them.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Payload {

	/**
	 * Most orders read to compute a guest's history — a guest with more is a store's test email.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const GUEST_ORDERS_LIMIT = 200;


	/**
	 * A date as the platform reads it, or null.
	 *
	 * @since 2.5.0
	 * @param \WC_DateTime|\DateTimeInterface|int|null $date
	 * @return string|null
	 */
	public static function iso( $date ) {
		if ( $date instanceof \DateTimeInterface ) {
			return gmdate( 'Y-m-d\TH:i:s\Z', $date->getTimestamp() );
		}

		if ( is_numeric( $date ) && (int) $date > 0 ) {
			return gmdate( 'Y-m-d\TH:i:s\Z', (int) $date );
		}

		return null;
	}


	/**
	 * A money value as a decimal string with the store's decimals, the way the REST API writes it.
	 *
	 * @since 2.5.0
	 * @param mixed $amount
	 * @return string
	 */
	public static function money( $amount ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		return number_format( (float) $amount, $decimals, '.', '' );
	}


	/**
	 * An order's status without WooCommerce's `wc-` prefix.
	 *
	 * @since 2.5.0
	 * @param string $status
	 * @return string
	 */
	public static function status( $status ) {
		return 0 === strpos( (string) $status, 'wc-' ) ? substr( (string) $status, 3 ) : (string) $status;
	}


	/**
	 * An order.
	 *
	 * @since 2.5.0
	 * @param \WC_Order $order
	 * @return array<string,mixed>
	 */
	public static function order( $order ) {
		$send_address = 'yes' === Cloud_Sync::setting( 'cloud_sync_send_address' );
		$send_items = 'yes' === Cloud_Sync::setting( 'cloud_sync_send_items' );

		$billing = array(
			'first_name' => (string) $order->get_billing_first_name(),
			'last_name' => (string) $order->get_billing_last_name(),
			'email' => (string) $order->get_billing_email(),
			'phone' => (string) $order->get_billing_phone(),
			'city' => (string) $order->get_billing_city(),
			'state' => (string) $order->get_billing_state(),
			'country' => (string) $order->get_billing_country(),
		);

		$shipping = array(
			'city' => (string) $order->get_shipping_city(),
			'state' => (string) $order->get_shipping_state(),
			'country' => (string) $order->get_shipping_country(),
		);

		if ( $send_address ) {
			$billing = array_merge( $billing, array(
				'company' => (string) $order->get_billing_company(),
				'address_1' => (string) $order->get_billing_address_1(),
				'address_2' => (string) $order->get_billing_address_2(),
				'postcode' => (string) $order->get_billing_postcode(),
			) );
			$shipping = array_merge( $shipping, array(
				'first_name' => (string) $order->get_shipping_first_name(),
				'last_name' => (string) $order->get_shipping_last_name(),
				'address_1' => (string) $order->get_shipping_address_1(),
				'address_2' => (string) $order->get_shipping_address_2(),
				'postcode' => (string) $order->get_shipping_postcode(),
				'phone' => method_exists( $order, 'get_shipping_phone' ) ? (string) $order->get_shipping_phone() : '',
			) );
		}

		$data = array(
			'id' => (int) $order->get_id(),
			'number' => (string) $order->get_order_number(),
			'status' => self::status( $order->get_status() ),
			'currency' => (string) $order->get_currency(),
			'total' => self::money( $order->get_total() ),
			'subtotal' => self::money( $order->get_subtotal() ),
			'discount_total' => self::money( $order->get_discount_total() ),
			'shipping_total' => self::money( $order->get_shipping_total() ),
			'total_tax' => self::money( $order->get_total_tax() ),
			'payment_method' => (string) $order->get_payment_method(),
			'payment_method_title' => (string) $order->get_payment_method_title(),
			'date_created' => self::iso( $order->get_date_created() ),
			'date_paid' => self::iso( $order->get_date_paid() ),
			'customer_id' => (int) $order->get_customer_id(),
			'customer_note' => (string) $order->get_customer_note(),
			'billing' => $billing,
			'shipping' => $shipping,
			'shipping_lines' => array(),
			'coupon_lines' => array(),
		);

		foreach ( $order->get_shipping_methods() as $line ) {
			$data['shipping_lines'][] = array(
				'method_id' => (string) $line->get_method_id(),
				'method_title' => (string) $line->get_method_title(),
				'total' => self::money( $line->get_total() ),
			);
		}

		foreach ( $order->get_coupons() as $coupon ) {
			$data['coupon_lines'][] = array(
				'code' => (string) $coupon->get_code(),
				'discount' => self::money( $coupon->get_discount() ),
			);
		}

		if ( $send_items ) {
			$data['line_items'] = self::line_items( $order );
		}

		/**
		 * Filter an order's data before it is queued — where a store adds a meta of its own.
		 *
		 * @since 2.5.0
		 * @param array<string,mixed> $data
		 * @param \WC_Order $order
		 */
		return (array) apply_filters( 'Joinotify/Cloud_Sync/Order_Data', $data, $order );
	}


	/**
	 * An order's items, with each product's categories — what "bought coffee" audiences read.
	 *
	 * @since 2.5.0
	 * @param \WC_Order|\WC_Abstract_Order $order
	 * @return array<int,array<string,mixed>>
	 */
	public static function line_items( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			$product_id = (int) $item->get_product_id();
			$categories = array();

			if ( $product_id > 0 && function_exists( 'wc_get_product_terms' ) ) {
				$categories = wc_get_product_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
			}

			$quantity = (int) $item->get_quantity();

			$items[] = array(
				'product_id' => $product_id,
				'variation_id' => (int) $item->get_variation_id(),
				'sku' => $product ? (string) $product->get_sku() : '',
				'name' => (string) $item->get_name(),
				'quantity' => $quantity,
				'price' => self::money( $quantity > 0 ? (float) $item->get_total() / $quantity : 0 ),
				'total' => self::money( $item->get_total() ),
				'categories' => array_values( array_map( 'strval', (array) $categories ) ),
			);
		}

		return $items;
	}


	/**
	 * Links a flow sends the customer: pay a pending order, see it, review what was bought.
	 *
	 * @since 2.5.0
	 * @param \WC_Order $order
	 * @return array<string,mixed>
	 */
	public static function links( $order ) {
		$review_urls = array();

		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product && $item->get_product_id() > 0 ) {
				$review_urls[] = get_permalink( $item->get_product_id() ) . '#reviews';
			}
		}

		return array(
			'payment_url' => (string) $order->get_checkout_payment_url(),
			'order_received_url' => (string) $order->get_checkout_order_received_url(),
			'my_account_url' => (string) $order->get_view_order_url(),
			'review_urls' => array_values( array_unique( $review_urls ) ),
		);
	}


	/**
	 * The customer's history, as an absolute snapshot computed from the store: the platform
	 * overwrites with it instead of adding to what it has — a refund or an order edited in the
	 * admin would otherwise break a running sum.
	 *
	 * Paid orders only, the same statuses WooCommerce's own customer totals count.
	 *
	 * @since 2.5.0
	 * @param \WC_Order $order The order the snapshot is taken for.
	 * @return array<string,mixed>
	 */
	public static function customer( $order ) {
		$statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		$history = self::history( (int) $order->get_customer_id(), (string) $order->get_billing_email() );
		$this_paid = in_array( self::status( $order->get_status() ), $statuses, true );

		// This order is the first paid one — or, not paid yet, there is none before it.
		$history['is_first_order'] = $this_paid ? 1 === $history['orders_count'] : 0 === $history['orders_count'];

		return $history;
	}


	/**
	 * A customer's paid-order history — by account, or for a guest by the e-mail of their orders.
	 * What the order events carry and what the backfill sends for each customer.
	 *
	 * @since 2.5.0
	 * @param int $customer_id
	 * @param string $email
	 * @return array<string,mixed>
	 */
	public static function history( $customer_id, $email ) {
		$statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );

		$query = array(
			'status' => $statuses,
			'limit' => self::GUEST_ORDERS_LIMIT,
			'orderby' => 'date',
			'order' => 'ASC',
			'return' => 'objects',
		);

		if ( $customer_id > 0 ) {
			$query['customer_id'] = $customer_id;
		} elseif ( '' !== $email ) {
			// A guest is known by the e-mail of their orders — the same person's orders placed while
			// logged in count too, which is what they are.
			$query['billing_email'] = $email;
		} else {
			$query = null;
		}

		$orders = null !== $query ? wc_get_orders( $query ) : array();
		$count = count( $orders );
		$spent = 0.0;

		foreach ( $orders as $paid ) {
			$spent += (float) $paid->get_total() - (float) $paid->get_total_refunded();
		}

		$first = $count > 0 ? $orders[0] : null;
		$last = $count > 0 ? $orders[ $count - 1 ] : null;

		return array(
			'id' => (int) $customer_id,
			'orders_count' => $count,
			'total_spent' => self::money( $spent ),
			'avg_order_value' => self::money( $count > 0 ? $spent / $count : 0 ),
			'first_order_at' => $first ? self::iso( $first->get_date_created() ) : null,
			'last_order_at' => $last ? self::iso( $last->get_date_created() ) : null,
			'last_order_status' => $last ? self::status( $last->get_status() ) : null,
		);
	}


	/**
	 * A WordPress user.
	 *
	 * @since 2.5.0
	 * @param \WP_User $user
	 * @return array<string,mixed>
	 */
	public static function user( $user ) {
		return array(
			'id' => (int) $user->ID,
			'email' => (string) $user->user_email,
			'first_name' => (string) $user->first_name,
			'last_name' => (string) $user->last_name,
			'roles' => array_values( (array) $user->roles ),
			'registered_at' => self::iso( strtotime( (string) $user->user_registered . ' UTC' ) ),
		);
	}


	/**
	 * The phone a user keeps: WooCommerce's billing phone, or the one the OTP login saved.
	 *
	 * @since 2.5.0
	 * @param int $user_id
	 * @return string
	 */
	public static function user_phone( $user_id ) {
		foreach ( array( 'billing_phone', 'joinotify_user_phone' ) as $key ) {
			$phone = trim( (string) get_user_meta( $user_id, $key, true ) );

			if ( '' !== $phone ) {
				return $phone;
			}
		}

		return '';
	}


	/**
	 * A subscription.
	 *
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return array<string,mixed>
	 */
	public static function subscription( $subscription ) {
		$data = array(
			'id' => (int) $subscription->get_id(),
			'status' => self::status( $subscription->get_status() ),
			'total' => self::money( $subscription->get_total() ),
			'currency' => (string) $subscription->get_currency(),
			'billing_period' => (string) $subscription->get_billing_period(),
			'billing_interval' => (int) $subscription->get_billing_interval(),
			'next_payment_at' => self::iso( (int) $subscription->get_time( 'next_payment' ) ),
			'customer_id' => (int) $subscription->get_customer_id(),
			'billing' => array(
				'first_name' => (string) $subscription->get_billing_first_name(),
				'last_name' => (string) $subscription->get_billing_last_name(),
				'email' => (string) $subscription->get_billing_email(),
				'phone' => (string) $subscription->get_billing_phone(),
				'city' => (string) $subscription->get_billing_city(),
				'state' => (string) $subscription->get_billing_state(),
				'country' => (string) $subscription->get_billing_country(),
			),
		);

		if ( 'yes' === Cloud_Sync::setting( 'cloud_sync_send_items' ) ) {
			$data['line_items'] = self::line_items( $subscription );
		}

		return $data;
	}


	/**
	 * A Flexify Checkout cart.
	 *
	 * @since 2.5.0
	 * @param int $cart_id
	 * @return array<string,mixed>
	 */
	public static function cart( $cart_id ) {
		$total = get_post_meta( $cart_id, '_fcrc_cart_total', true );
		$recovery = class_exists( '\MeuMouse\Flexify_Checkout\Recovery_Carts\Core\Helpers' )
			? (string) \MeuMouse\Flexify_Checkout\Recovery_Carts\Core\Helpers::generate_recovery_cart_link( $cart_id )
			: '';

		return array(
			'id' => (int) $cart_id,
			'total' => '' !== $total && null !== $total ? self::money( $total ) : null,
			'currency' => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '',
			'recovery_url' => $recovery,
			'first_name' => (string) get_post_meta( $cart_id, '_fcrc_first_name', true ),
			'last_name' => (string) get_post_meta( $cart_id, '_fcrc_last_name', true ),
			'email' => (string) get_post_meta( $cart_id, '_fcrc_cart_email', true ),
			'phone' => (string) get_post_meta( $cart_id, '_fcrc_cart_phone', true ),
		);
	}


	/**
	 * A form submission's fields by name, and which of them are the phone, e-mail and name.
	 *
	 * Pure: the emitters flatten WPForms' and Elementor's structures into `[{ id, label, type,
	 * value }]` first. The phone is the field typed as one, or else the first whose label says
	 * phone/WhatsApp in the languages the plugin speaks.
	 *
	 * @since 2.5.0
	 * @param array<int,array<string,string>> $fields `{ id, label, type, value }`.
	 * @return array{fields: array<string,string>, phone: string, email: string, name: string}
	 */
	public static function form_fields( $fields ) {
		$out = array();
		$phone = '';
		$email = '';
		$name = '';

		foreach ( $fields as $field ) {
			$label = trim( (string) ( $field['label'] ?? '' ) );
			$value = trim( (string) ( $field['value'] ?? '' ) );
			$type = strtolower( (string) ( $field['type'] ?? '' ) );
			$key = sanitize_key( str_replace( ' ', '_', remove_accents( '' !== $label ? $label : (string) ( $field['id'] ?? '' ) ) ) );

			if ( '' === $key || '' === $value ) {
				continue;
			}

			// Two fields with the same label keep both.
			$unique = $key;
			$n = 2;

			while ( isset( $out[ $unique ] ) ) {
				$unique = $key . '_' . $n++;
			}

			$out[ $unique ] = $value;
			$lower = strtolower( remove_accents( $label ) );

			if ( '' === $phone && ( 'phone' === $type || 'tel' === $type || preg_match( '/phone|telefone|celular|whatsapp|movil|tel\b/', $lower ) ) ) {
				$phone = $value;
			} elseif ( '' === $email && ( 'email' === $type || false !== strpos( $lower, 'mail' ) ) ) {
				$email = $value;
			} elseif ( '' === $name && ( 'name' === $type || preg_match( '/^(name|nome|nombre)\b/', $lower ) ) ) {
				$name = $value;
			}
		}

		return array( 'fields' => $out, 'phone' => $phone, 'email' => $email, 'name' => $name );
	}
}
