<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The contact block of a site event, and the contact row of a backfill: who the person is on this
 * site, how to reach them, and the snapshot of their history the platform's fields hold.
 *
 * Who the person is (`ref`) is what the platform finds them by before the phone, so a customer who
 * changed numbers stays one contact:
 *
 *  - `wc_customer` — a WooCommerce customer with an account (the user id);
 *  - `wp_user` — a WordPress user who registered;
 *  - `wc_guest` — a buyer without an account, known by the SHA-256 of their e-mail (the address
 *    itself goes in its own field; the id must never be personal data in the clear);
 *  - `lead` — a form or a cart, same hashing.
 *
 * The attributes are the platform's field packs, written to the keys the last report said they
 * live at (`State::field_map()`): a key the account does not have is left out rather than refused.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Contact {

	/**
	 * A ref for someone without an account: the hash of their e-mail, or of their phone.
	 *
	 * @since 2.5.0
	 * @param string $kind `wc_guest` or `lead`.
	 * @param string $email
	 * @param string $phone
	 * @return array<string,string>|null
	 */
	public static function anonymous_ref( $kind, $email, $phone ) {
		$email = strtolower( trim( (string) $email ) );
		$digits = preg_replace( '/\D/', '', (string) $phone );

		if ( '' !== $email ) {
			return array( 'kind' => $kind, 'id' => hash( 'sha256', $email ) );
		}

		if ( '' !== $digits ) {
			return array( 'kind' => $kind, 'id' => hash( 'sha256', 'phone:' . $digits ) );
		}

		return null;
	}


	/**
	 * Pack values written to the keys they live at in the account; unmapped keys are dropped.
	 *
	 * Pure (the map comes in), so the harness can check the translation.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $values Pack key → value.
	 * @param array<string,string> $map Pack key → account key.
	 * @return array<string,mixed>
	 */
	public static function map_attributes( $values, $map ) {
		$out = array();

		foreach ( $values as $key => $value ) {
			if ( isset( $map[ $key ] ) && is_string( $map[ $key ] ) && '' !== $map[ $key ] ) {
				$out[ $map[ $key ] ] = $value;
			}
		}

		return $out;
	}


	/**
	 * Assemble a block, leaving out what is empty.
	 *
	 * @since 2.5.0
	 * @param array<string,string>|null $ref
	 * @param array<string,mixed> $fields `phone`, `country`, `first_name`, `last_name`, `email`.
	 * @param array<string,mixed> $pack Pack key → value.
	 * @return array<string,mixed>|null Null without a ref — nobody to say who it is.
	 */
	private static function block( $ref, $fields, $pack = array() ) {
		if ( null === $ref ) {
			return null;
		}

		$block = array( 'ref' => $ref );

		foreach ( array( 'phone', 'country', 'first_name', 'last_name', 'email' ) as $key ) {
			$value = trim( (string) ( $fields[ $key ] ?? '' ) );

			if ( '' !== $value ) {
				$block[ $key ] = $value;
			}
		}

		$attributes = self::map_attributes( array_filter( $pack, static function( $value ) {
			return null !== $value && '' !== $value;
		} ), State::field_map() );

		if ( ! empty( $attributes ) ) {
			$block['attributes'] = $attributes;
		}

		$tag = Cloud_Sync::source_tag();

		if ( '' !== $tag ) {
			$block['tags'] = array( $tag );
		}

		/**
		 * Filter a contact block before it is queued — where consent is added (`Consent`), and where
		 * a store adds a tag of its own.
		 *
		 * @since 2.5.0
		 * @param array<string,mixed> $block
		 * @param array<string,mixed> $fields What the block was built from.
		 */
		return (array) apply_filters( 'Joinotify/Cloud_Sync/Contact_Block', $block, $fields );
	}


	/**
	 * The buyer of an order, with their history in the store.
	 *
	 * @since 2.5.0
	 * @param \WC_Order $order
	 * @param array<string,mixed>|null $customer The snapshot, when the caller already built it.
	 * @return array<string,mixed>|null
	 */
	public static function from_order( $order, $customer = null ) {
		$customer = $customer ?? Payload::customer( $order );
		$customer_id = (int) $order->get_customer_id();
		$phone = (string) $order->get_billing_phone();

		if ( '' === trim( $phone ) && method_exists( $order, 'get_shipping_phone' ) ) {
			$phone = (string) $order->get_shipping_phone();
		}

		$ref = $customer_id > 0
			? array( 'kind' => 'wc_customer', 'id' => (string) $customer_id )
			: self::anonymous_ref( 'wc_guest', $order->get_billing_email(), $phone );

		return self::block( $ref, array(
			'phone' => $phone,
			'country' => $order->get_billing_country(),
			'first_name' => $order->get_billing_first_name(),
			'last_name' => $order->get_billing_last_name(),
			'email' => $order->get_billing_email(),
			'order_id' => $order->get_id(),
		), array(
			'wc_orders_count' => (int) $customer['orders_count'],
			'wc_total_spent' => (float) $customer['total_spent'],
			'wc_avg_order_value' => (float) $customer['avg_order_value'],
			'wc_first_order_at' => $customer['first_order_at'],
			'wc_last_order_at' => $customer['last_order_at'],
			'wc_last_order_status' => Payload::status( $order->get_status() ),
			'wc_billing_city' => $order->get_billing_city(),
			'wc_billing_state' => $order->get_billing_state(),
		) );
	}


	/**
	 * A WordPress user.
	 *
	 * @since 2.5.0
	 * @param \WP_User $user
	 * @return array<string,mixed>|null
	 */
	public static function from_user( $user ) {
		$roles = array_values( (array) $user->roles );

		return self::block( array( 'kind' => 'wp_user', 'id' => (string) $user->ID ), array(
			'phone' => Payload::user_phone( $user->ID ),
			'country' => get_user_meta( $user->ID, 'billing_country', true ),
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'email' => $user->user_email,
			'user_id' => $user->ID,
		), array(
			'wp_registered_at' => Payload::iso( strtotime( (string) $user->user_registered . ' UTC' ) ),
			'wp_role' => $roles[0] ?? '',
			'wc_billing_city' => get_user_meta( $user->ID, 'billing_city', true ),
			'wc_billing_state' => get_user_meta( $user->ID, 'billing_state', true ),
		) );
	}


	/**
	 * The subscriber of a subscription, with its status and next payment.
	 *
	 * @since 2.5.0
	 * @param \WC_Subscription $subscription
	 * @return array<string,mixed>|null
	 */
	public static function from_subscription( $subscription ) {
		$customer_id = (int) $subscription->get_customer_id();
		$ref = $customer_id > 0
			? array( 'kind' => 'wc_customer', 'id' => (string) $customer_id )
			: self::anonymous_ref( 'wc_guest', $subscription->get_billing_email(), $subscription->get_billing_phone() );

		return self::block( $ref, array(
			'phone' => $subscription->get_billing_phone(),
			'country' => $subscription->get_billing_country(),
			'first_name' => $subscription->get_billing_first_name(),
			'last_name' => $subscription->get_billing_last_name(),
			'email' => $subscription->get_billing_email(),
		), array(
			'wcs_status' => Payload::status( $subscription->get_status() ),
			'wcs_next_payment_at' => Payload::iso( (int) $subscription->get_time( 'next_payment' ) ),
		) );
	}


	/**
	 * The person behind a cart or a form: the logged-in user when there is one, a lead otherwise.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $person `phone`, `email`, `first_name`, `last_name`, `country`.
	 * @param int $user_id The logged-in user, or 0.
	 * @return array<string,mixed>|null
	 */
	public static function from_lead( $person, $user_id = 0 ) {
		$ref = $user_id > 0
			? array( 'kind' => 'wp_user', 'id' => (string) $user_id )
			: self::anonymous_ref( 'lead', $person['email'] ?? '', $person['phone'] ?? '' );

		return self::block( $ref, $person );
	}
}
