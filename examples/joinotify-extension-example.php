<?php
/**
 * Plugin Name: Joinotify Extension Example
 * Description: Reference example that extends every Joinotify subsystem using PHP filters only.
 *              Drop this file into wp-content/plugins/ and activate it (Joinotify must be active),
 *              or `require_once` it from a snippet/mu-plugin. See DEVELOPERS.md for the full reference.
 * Author: MeuMouse.com
 * Version: 1.0.0
 *
 * This file is intentionally self-contained and uses ONLY the public joinotify_register_*() helpers.
 * Nothing here touches Joinotify core or its JavaScript — the action library modal renders the
 * custom category tab and the settings_schema fields automatically.
 */

defined('ABSPATH') || exit;

/**
 * Register everything on plugins_loaded so the filters are wired before Joinotify builds its
 * bootstrap/catalog. We guard on the helper existing so this file is harmless if Joinotify is off.
 */
add_action( 'plugins_loaded', function() {
	if ( ! function_exists( 'joinotify_register_action' ) ) {
		return; // Joinotify not active.
	}

	$textdomain = 'joinotify-extension-example';

	/**
	 * ---------------------------------------------------------------------
	 * 1. Action category (a tab in the "Add an action" modal)
	 * ---------------------------------------------------------------------
	 */
	joinotify_register_action_category([
		'id'       => 'example_app',
		'label'    => __( 'Example App', $textdomain ),
		'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>',
		'priority' => 50,
	]);

	/**
	 * ---------------------------------------------------------------------
	 * 2. A fully data-driven custom action (settings UI rendered with no JS)
	 * ---------------------------------------------------------------------
	 */
	joinotify_register_action([
		'action'        => 'example_send_sms',
		'title'         => __( 'Example: Send SMS', $textdomain ),
		'description'   => __( 'Send an SMS through the Example App gateway.', $textdomain ),
		'category'      => 'example_app',
		'icon'          => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>',
		'has_settings'  => true,
		'is_expansible' => true,
		'priority'      => 70,
		'context'       => array(), // available for every trigger
		'default_data'  => array(
			'action'  => 'example_send_sms',
			'title'   => __( 'Example: Send SMS', $textdomain ),
			'to'      => '{{ wc_billing_phone }}',
			'message' => '',
		),
		'settings_schema' => array(
			array(
				'key'         => 'to',
				'label'       => __( 'Recipient', $textdomain ),
				'component'   => 'input',
				'required'    => true,
				'placeholder' => '5541999999999',
			),
			array(
				'key'       => 'message',
				'label'     => __( 'Message', $textdomain ),
				'component' => 'textarea',
				'required'  => true,
				'rows'      => 4,
			),
		),

		// Runtime handler — runs when a workflow reaches this action.
		'handler' => function( $action_data, $action, $post_id, $event_data ) {
			$to      = \MeuMouse\Joinotify\Builder\Placeholders::replace_placeholders( $action_data['to'] ?? '', $event_data );
			$message = \MeuMouse\Joinotify\Builder\Placeholders::replace_placeholders( $action_data['message'] ?? '', $event_data );

			// Replace with a real SMS gateway call.
			if ( defined( 'JOINOTIFY_DEV_MODE' ) && JOINOTIFY_DEV_MODE ) {
				error_log( sprintf( '[Example App] SMS to %s: %s', $to, $message ) );
			}

			return true; // handlers must return bool
		},
	]);

	// Dynamic node description shown on the canvas.
	joinotify_register_action_description( 'example_send_sms', function( $data, $workflow_action ) {
		$to = isset( $data['to'] ) ? $data['to'] : '';

		return esc_html( sprintf( __( 'SMS to %s', 'joinotify-extension-example' ), $to ) );
	});

	/**
	 * ---------------------------------------------------------------------
	 * 3. Integration card + trigger + runtime dispatch
	 * ---------------------------------------------------------------------
	 */
	joinotify_register_integration([
		'slug'        => 'example_app',
		'title'       => __( 'Example App', $textdomain ),
		'description' => __( 'Automate messages for Example App events.', $textdomain ),
		'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 2 7l10 5 10-5-10-5z"/></svg>',
		'setting_key' => 'enable_example_app_integration',
	]);

	joinotify_register_trigger( 'example_app', array(
		'data_trigger'     => 'example_app_order_paid',
		'title'            => __( 'Example App: order paid', $textdomain ),
		'description'      => __( 'Fires when an Example App order is paid.', $textdomain ),
		'require_settings' => false,
	));

	// Fire the trigger from your own hook. (Demo hook name — replace with your real one.)
	add_action( 'example_app_order_paid', function( $order_id ) {
		joinotify_dispatch_trigger( 'example_app_order_paid', 'example_app', array(
			'order_id' => $order_id,
		));
	});

	/**
	 * ---------------------------------------------------------------------
	 * 4. Condition (available for the trigger) + operators + value resolver
	 * ---------------------------------------------------------------------
	 */
	joinotify_register_conditions( 'example_app_order_paid', array(
		'example_plan' => array(
			'title'       => __( 'Subscription plan', $textdomain ),
			'description' => __( 'Check the customer plan attached to the order.', $textdomain ),
		),
	));

	joinotify_register_condition_operators( 'example_plan', array( 'is', 'is_not' ) );

	joinotify_register_condition_value( 'example_plan', function( $value_map, $type, $payload ) {
		$order_id = isset( $payload['order_id'] ) ? (int) $payload['order_id'] : 0;

		return $order_id ? get_post_meta( $order_id, '_example_plan', true ) : null;
	});

	/**
	 * ---------------------------------------------------------------------
	 * 5. Placeholder (sandbox value in preview, production value at send)
	 * ---------------------------------------------------------------------
	 */
	joinotify_register_placeholders( 'example_app', array(
		'{{ example_plan }}' => array(
			'triggers'    => array( 'example_app_order_paid' ),
			'description' => __( 'The customer plan name.', $textdomain ),
			'replacement' => array(
				'sandbox'    => __( 'Premium', $textdomain ),
				'production' => __( 'Premium', $textdomain ), // resolve from your data at send time
			),
		),
	));

	/**
	 * ---------------------------------------------------------------------
	 * 6. Settings tab + section/card/field
	 * ---------------------------------------------------------------------
	 */
	joinotify_register_settings_tab([
		'id'      => 'example_app',
		'name'    => __( 'Example App', $textdomain ),
		'icon'    => '<svg class="joinotify-tab-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 2 7l10 5 10-5-10-5z"/></svg>',
		'section' => 'example_app',
	]);

	joinotify_register_settings_section([
		'id'     => 'example_app',
		'title'  => __( 'Example App', $textdomain ),
		'layout' => 'cards',
		'cards'  => array(
			array(
				'id'          => 'example_app-credentials',
				'title'       => __( 'Credentials', $textdomain ),
				'description' => __( 'Connect your Example App account.', $textdomain ),
				'fields'      => array(
					array(
						'type'  => 'text',
						'key'   => 'example_app_api_key',
						'label' => __( 'API key', $textdomain ),
					),
				),
			),
		),
	]);

	/**
	 * ---------------------------------------------------------------------
	 * 7. Custom REST endpoint under joinotify/v1
	 * ---------------------------------------------------------------------
	 */
	joinotify_register_rest_route([
		'route'    => '/admin/example-app/ping',
		'methods'  => 'GET',
		'callback' => function ( WP_REST_Request $request ) {
			return rest_ensure_response( array(
				'status' => 'success',
				'pong'   => true,
			) );
		},
		// 'permission' => fn() => current_user_can( 'manage_options' ), // default
	]);
}, 20 );
