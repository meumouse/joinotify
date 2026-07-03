<?php
/**
 * Plugin Name: Joinotify Extension Example
 * Description: Reference example that extends every Joinotify subsystem using PHP filters only.
 *              Drop this file into wp-content/plugins/ and activate it (Joinotify must be active),
 *              or `require_once` it from a snippet/mu-plugin. See DEVELOPERS.md for the full reference.
 * Author: MeuMouse.com
 * Version: 1.1.0
 *
 * This file is intentionally self-contained and uses ONLY the public joinotify_register_*() helpers.
 * Nothing here touches Joinotify core or its JavaScript — the action library modal renders the
 * custom category tab and the settings_schema fields automatically (the node settings drawer renders
 * your settings_schema generically, so no bespoke Vue component is required for standard fields).
 *
 * Sections 8-10 demonstrate the extension points added in Joinotify 2.0.0: parametric placeholder
 * resolvers, structural (delay/branch) actions, and single-call actions that auto-create their tab.
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
			// Resolve {{ ... }} tokens with the runtime helper (see DEVELOPERS.md → Runtime helpers).
			$to      = joinotify_replace_placeholders( $action_data['to'] ?? '', $event_data );
			$message = joinotify_replace_placeholders( $action_data['message'] ?? '', $event_data );

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

	/**
	 * ---------------------------------------------------------------------
	 * 8. Parametric placeholder resolver (bracket-syntax tokens)  [2.0.0]
	 * ---------------------------------------------------------------------
	 *
	 * Static "{{ name }}" tokens are registered in section 5. For tokens that carry an argument —
	 * here "{{ example_meta[KEY] }}" — register a resolver with a PCRE pattern. The callback gets the
	 * preg_match result ($matches[1] = first capture) and the runtime payload, and returns the
	 * replacement (or null to leave the token untouched). Runs BEFORE the built-in bracket handlers.
	 */
	if ( function_exists( 'joinotify_register_dynamic_placeholder' ) ) {
		joinotify_register_dynamic_placeholder( '/\{\{\s*example_meta\[(.+?)\]\s*\}\}/', function( $matches, $payload ) {
			$meta_key = $matches[1];
			$order_id = isset( $payload['order_id'] ) ? (int) $payload['order_id'] : 0;

			if ( ! $order_id || '' === $meta_key ) {
				return null; // keep the original token when we cannot resolve it
			}

			$value = get_post_meta( $order_id, $meta_key, true );

			return is_scalar( $value ) ? (string) $value : null;
		});
	}

	/**
	 * ---------------------------------------------------------------------
	 * 9. Structural action — a custom "delay" step  [2.0.0]
	 * ---------------------------------------------------------------------
	 *
	 * Leaf actions run through a handler. The two STRUCTURAL behaviors (pause/delay and branch) are
	 * dispatched by capability lists. Register the slug on the relevant filter and reuse the built-in
	 * delay-resolution data shape (delay_type/delay_value/delay_period/date_value/time_value) — the
	 * engine pauses the funnel and schedules the remaining actions as a continuation. No handler is
	 * needed for a delaying action; run_segment intercepts it before the handler map.
	 */
	joinotify_register_action([
		'action'        => 'example_business_hours_wait',
		'title'         => __( 'Example: wait 1 hour', $textdomain ),
		'description'   => __( 'Pause the funnel for one hour before continuing.', $textdomain ),
		'category'      => 'example_app',
		'icon'          => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 11h-4v-2h2V7h2v6z"/></svg>',
		'has_settings'  => false,
		'is_expansible' => true,
		'priority'      => 60,
		'default_data'  => array(
			'action'       => 'example_business_hours_wait',
			'title'        => __( 'Example: wait 1 hour', $textdomain ),
			'delay_type'   => 'period',
			'delay_value'  => 1,
			'delay_period' => 'hours',
		),
	]);

	// Declare the slug as a delaying action so the engine treats it like the built-in time_delay.
	add_filter( 'Joinotify/Workflow_Processor/Delaying_Actions', function( $slugs ) {
		$slugs[] = 'example_business_hours_wait';

		return $slugs;
	});

	/**
	 * ---------------------------------------------------------------------
	 * 10. Single-call action that auto-creates its own tab  [2.0.0]
	 * ---------------------------------------------------------------------
	 *
	 * Passing `category_label` (and optionally `category_icon`) lets one joinotify_register_action()
	 * call also create the category tab — no separate joinotify_register_action_category() needed.
	 * The dedup guard means it is safe even if that category id is also registered elsewhere.
	 */
	joinotify_register_action([
		'action'         => 'example_tag_customer',
		'title'          => __( 'Example: tag customer', $textdomain ),
		'description'    => __( 'Attach a CRM tag to the customer.', $textdomain ),
		'category'       => 'example_crm',
		'category_label' => __( 'Example CRM', $textdomain ), // <- auto-creates the tab
		'category_icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5 0-9 2.5-9 5v1h18v-1c0-2.5-4-5-9-5z"/></svg>',
		'has_settings'   => true,
		'is_expansible'  => true,
		'priority'       => 50,
		'default_data'   => array(
			'action' => 'example_tag_customer',
			'title'  => __( 'Example: tag customer', $textdomain ),
			'tag'    => '',
		),
		'settings_schema' => array(
			array(
				'key'         => 'tag',
				'label'       => __( 'Tag', $textdomain ),
				'component'   => 'input',
				'required'    => true,
				'placeholder' => __( 'vip', $textdomain ),
			),
		),
		'handler' => function( $action_data, $action, $post_id, $event_data ) {
			$tag = isset( $action_data['tag'] ) ? sanitize_title( $action_data['tag'] ) : '';

			if ( defined( 'JOINOTIFY_DEV_MODE' ) && JOINOTIFY_DEV_MODE ) {
				error_log( sprintf( '[Example CRM] tag customer: %s', $tag ) );
			}

			return true;
		},
	]);
}, 20 );
