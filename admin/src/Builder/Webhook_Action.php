<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Cloud_Sync\Payload;
use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * "Send webhook": a workflow action that posts to any URL — n8n, Zapier, Make, a platform flow's
 * webhook trigger, the store's own ERP.
 *
 * The body is either the trigger's data, built the same way the Joinotify Cloud sync builds its
 * events (the order in WooCommerce's REST shape, the customer's history, the links; the user; the
 * cart), or a JSON of the user's own with placeholders. Optional HMAC signature with the same
 * scheme Joinotify's webhooks use: `X-Joinotify-Signature-256: sha256=HMAC(timestamp.body)`.
 *
 * Sent with `wp_safe_remote_request`, which refuses local and private addresses: a workflow
 * editor must not be a way to reach the server's own network. Sent when the workflow reaches the
 * action, like the other actions; a failure is logged and the action reports it.
 *
 * Registered through the public extension API, with a declarative settings schema — no screen of
 * its own.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Builder
 * @author MeuMouse.com
 */
class Webhook_Action {

	/**
	 * The action's slug.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const ACTION = 'send_webhook';

	/**
	 * Seconds to wait for the other side.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const TIMEOUT = 10;

	const ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10.59 13.41a1 1 0 0 0 1.41 0l4.24-4.24a3 3 0 0 0-4.24-4.24l-1.42 1.41a1 1 0 1 0 1.42 1.42l1.41-1.42a1 1 0 0 1 1.41 1.42l-4.23 4.24a1 1 0 0 0 0 1.41zm2.82-2.82a1 1 0 0 0-1.41 0l-4.24 4.24a3 3 0 0 0 4.24 4.24l1.42-1.41a1 1 0 1 0-1.42-1.42l-1.41 1.42a1 1 0 0 1-1.41-1.42l4.23-4.24a1 1 0 0 0 0-1.41z"/></svg>';


	/**
	 * Register the action.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function __construct() {
		if ( ! function_exists( 'joinotify_register_action' ) ) {
			return;
		}

		joinotify_register_action( array(
			'action' => self::ACTION,
			'title' => __( 'Send webhook', 'joinotify' ),
			'description' => __( 'Send the trigger\'s data, or a JSON of your own, to any URL: n8n, Zapier, Make, a Joinotify Cloud flow or your ERP.', 'joinotify' ),
			'category' => 'general',
			'icon' => self::ICON,
			'has_settings' => true,
			'is_expansible' => true,
			'priority' => 80,
			'context' => array(),
			'default_data' => array(
				'action' => self::ACTION,
				'title' => __( 'Send webhook', 'joinotify' ),
				'url' => '',
				'method' => 'POST',
				'body_mode' => 'trigger',
				'body' => '',
				'headers' => '',
				'secret' => '',
			),
			'settings_schema' => array(
				array(
					'key' => 'url',
					'label' => __( 'URL', 'joinotify' ),
					'component' => 'input',
					'required' => true,
					'placeholder' => 'https://',
					'description' => __( 'A public address. Local and private network addresses are refused.', 'joinotify' ),
				),
				array(
					'key' => 'method',
					'label' => __( 'Method', 'joinotify' ),
					'component' => 'select',
					'options' => array(
						array( 'label' => 'POST', 'value' => 'POST' ),
						array( 'label' => 'PUT', 'value' => 'PUT' ),
						array( 'label' => 'PATCH', 'value' => 'PATCH' ),
					),
				),
				array(
					'key' => 'body_mode',
					'label' => __( 'Body', 'joinotify' ),
					'component' => 'select',
					'options' => array(
						array( 'label' => __( 'The trigger\'s data (order, customer, user, cart)', 'joinotify' ), 'value' => 'trigger' ),
						array( 'label' => __( 'Custom JSON', 'joinotify' ), 'value' => 'custom' ),
					),
				),
				array(
					'key' => 'body',
					'label' => __( 'JSON', 'joinotify' ),
					'component' => 'textarea',
					'rows' => 8,
					'placeholder' => '{ "order": "{{ wc_order_number }}", "phone": "{{ wc_billing_phone }}" }',
					'description' => __( 'Placeholders are replaced before sending. It must be valid JSON once they are.', 'joinotify' ),
					'condition' => array( array( 'key' => 'body_mode', 'value' => 'custom', 'operator' => 'eq' ) ),
				),
				array(
					'key' => 'headers',
					'label' => __( 'Headers', 'joinotify' ),
					'component' => 'textarea',
					'rows' => 3,
					'placeholder' => 'Authorization: Bearer …',
					'description' => __( 'Optional. One "Name: value" per line.', 'joinotify' ),
				),
				array(
					'key' => 'secret',
					'label' => __( 'Signing secret', 'joinotify' ),
					'component' => 'input',
					'description' => __( 'Optional. When set, each request carries X-Joinotify-Timestamp and X-Joinotify-Signature-256 (sha256 HMAC of "timestamp.body"), so the receiver can tell it came from this site.', 'joinotify' ),
				),
			),
			'handler' => array( __CLASS__, 'handle' ),
		) );

		if ( function_exists( 'joinotify_register_action_description' ) ) {
			joinotify_register_action_description( self::ACTION, array( __CLASS__, 'describe' ) );
		}
	}


	/**
	 * The node's line on the canvas: the method and where it goes.
	 *
	 * @since 2.5.0
	 * @param array $data
	 * @return string
	 */
	public static function describe( $data ) {
		$host = (string) wp_parse_url( (string) ( $data['url'] ?? '' ), PHP_URL_HOST );

		return '' !== $host
			? esc_html( sprintf( '%s %s', strtoupper( (string) ( $data['method'] ?? 'POST' ) ), $host ) )
			: esc_html__( 'Set the URL', 'joinotify' );
	}


	/**
	 * The trigger's data, as the sync's events carry it.
	 *
	 * @since 2.5.0
	 * @param array $event_data The workflow's runtime payload.
	 * @return array<string,mixed>
	 */
	public static function trigger_body( $event_data ) {
		$body = array(
			'trigger' => (string) ( $event_data['hook'] ?? '' ),
			'integration' => (string) ( $event_data['integration'] ?? '' ),
			'site_url' => home_url(),
			'occurred_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		$order = ! empty( $event_data['order_id'] ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $event_data['order_id'] ) : null;

		if ( $order instanceof \WC_Order && ! $order instanceof \WC_Order_Refund ) {
			$body['order'] = Payload::order( $order );
			$body['customer'] = Payload::customer( $order );
			$body['links'] = Payload::links( $order );
		}

		$subscription = ! empty( $event_data['subscription_id'] ) && function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( (int) $event_data['subscription_id'] ) : null;

		if ( $subscription ) {
			$body['subscription'] = Payload::subscription( $subscription );
		}

		$user = ! empty( $event_data['user_id'] ) ? get_userdata( (int) $event_data['user_id'] ) : null;

		if ( $user ) {
			$body['user'] = array_merge( Payload::user( $user ), array( 'phone' => Payload::user_phone( $user->ID ) ) );
		}

		if ( ! empty( $event_data['cart_id'] ) ) {
			$body['cart'] = Payload::cart( (int) $event_data['cart_id'] );
		}

		if ( ! empty( $event_data['fields'] ) && is_array( $event_data['fields'] ) ) {
			$body['form'] = array(
				'id' => (string) ( $event_data['id'] ?? '' ),
				'entry_id' => (string) ( $event_data['entry_id'] ?? '' ),
				'fields' => $event_data['fields'],
			);
		}

		/**
		 * Filter the body the "Send webhook" action sends in "trigger's data" mode.
		 *
		 * @since 2.5.0
		 * @param array<string,mixed> $body
		 * @param array $event_data
		 */
		return (array) apply_filters( 'Joinotify/Builder/Webhook_Action/Trigger_Body', $body, $event_data );
	}


	/**
	 * Headers typed one per line, as `Name: value`. Pure, for the harness.
	 *
	 * @since 2.5.0
	 * @param string $text
	 * @return array<string,string>
	 */
	public static function parse_headers( $text ) {
		$headers = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$parts = explode( ':', $line, 2 );
			$name = trim( $parts[0] );

			if ( 2 !== count( $parts ) || ! preg_match( '/^[A-Za-z0-9-]+$/', $name ) ) {
				continue;
			}

			$headers[ $name ] = trim( $parts[1] );
		}

		return $headers;
	}


	/**
	 * The signature headers of a body. Pure, for the harness.
	 *
	 * @since 2.5.0
	 * @param string $body
	 * @param string $secret
	 * @param int $timestamp
	 * @return array<string,string>
	 */
	public static function signature( $body, $secret, $timestamp ) {
		return array(
			'X-Joinotify-Timestamp' => (string) $timestamp,
			'X-Joinotify-Signature-256' => 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, (string) $secret ),
		);
	}


	/**
	 * Run the action.
	 *
	 * @since 2.5.0
	 * @param array $action_data The action's settings.
	 * @param array $action The workflow node.
	 * @param int $post_id The workflow.
	 * @param array $event_data The trigger's payload.
	 * @return bool
	 */
	public static function handle( $action_data, $action = array(), $post_id = 0, $event_data = array() ) {
		$url = trim( joinotify_replace_placeholders( (string) ( $action_data['url'] ?? '' ), $event_data ) );
		$method = strtoupper( (string) ( $action_data['method'] ?? 'POST' ) );
		$method = in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ? $method : 'POST';

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			Logger::register_log( sprintf( 'Send webhook (workflow %d): the URL "%s" is not a public address.', (int) $post_id, $url ), 'ERROR' );

			return false;
		}

		if ( 'custom' === ( $action_data['body_mode'] ?? 'trigger' ) ) {
			$body = joinotify_replace_placeholders( (string) ( $action_data['body'] ?? '' ), $event_data );
			json_decode( $body );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				Logger::register_log( sprintf( 'Send webhook (workflow %d): the body is not valid JSON once placeholders are replaced.', (int) $post_id ), 'ERROR' );

				return false;
			}
		} else {
			$body = (string) wp_json_encode( self::trigger_body( (array) $event_data ) );
		}

		$headers = array_merge(
			self::parse_headers( joinotify_replace_placeholders( (string) ( $action_data['headers'] ?? '' ), $event_data ) ),
			array(
				'Content-Type' => 'application/json',
				'User-Agent' => 'Joinotify/' . ( defined( 'JOINOTIFY_VERSION' ) ? JOINOTIFY_VERSION : '' ) . '; ' . home_url(),
			)
		);

		$secret = (string) ( $action_data['secret'] ?? '' );

		if ( '' !== $secret ) {
			$headers = array_merge( $headers, self::signature( $body, $secret, time() ) );
		}

		$response = wp_safe_remote_request( $url, array(
			'method' => $method,
			'headers' => $headers,
			'body' => $body,
			'timeout' => self::TIMEOUT,
			'redirection' => 0,
		) );

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$reason = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
			Logger::register_log( sprintf( 'Send webhook (workflow %d) to %s failed: %s', (int) $post_id, (string) wp_parse_url( $url, PHP_URL_HOST ), $reason ), 'ERROR' );

			return false;
		}

		return true;
	}
}
