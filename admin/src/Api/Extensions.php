<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Workflow_Processor;
use MeuMouse\Joinotify\Integrations\Integrations_Base;

defined('ABSPATH') || exit;

/**
 * Developer extension API (facade).
 *
 * A thin, discoverable layer over Joinotify's PHP filters. Every method here is sugar around a
 * single add_filter() call (or a Workflow_Processor dispatch), so the whole system stays
 * extensible "with PHP filters only" while developers get a clean, documented surface.
 *
 * Global helper functions (joinotify_register_action(), joinotify_register_trigger(), ...) wrap
 * these methods for convenience — see admin/src/Core/Functions.php. Full reference: DEVELOPERS.md.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Extensions {

	/**
	 * ---------------------------------------------------------------------
	 * Actions & categories
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register an action category (a tab in the builder actions library modal).
	 *
	 * @since 1.4.7
	 * @param array $category {id, label, icon?, priority?}.
	 * @param int   $priority add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_action_category( $category, $priority = 10 ) {
		if ( empty( $category['id'] ) ) {
			return;
		}

		add_filter( 'Joinotify/Builder/Action_Categories', function( $categories ) use ( $category ) {
			$categories[] = $category;

			return $categories;
		}, $priority, 1 );
	}


	/**
	 * Register a builder action.
	 *
	 * Accepts the full action definition (action, title, description, icon, context, category,
	 * has_settings, priority, is_expansible, default_data, settings_schema). Three convenience keys
	 * auto-wire the related filters and are stripped from the catalog entry:
	 * - handler      (callable) Runtime handler. See register_action_handler().
	 * - description  (callable) Canvas description builder. See register_action_description().
	 * - fill_sender  (bool)     Auto-fill the WhatsApp sender on template import.
	 *
	 * @since 1.4.7
	 * @param array $definition Action definition.
	 * @param int   $priority   add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_action( $definition, $priority = 10 ) {
		$slug = isset( $definition['action'] ) ? sanitize_key( (string) $definition['action'] ) : '';

		if ( empty( $slug ) ) {
			return;
		}

		// Pull convenience keys out before exposing the definition to the catalog.
		$handler = isset( $definition['handler'] ) && is_callable( $definition['handler'] ) ? $definition['handler'] : null;
		$description = isset( $definition['description'] ) && is_callable( $definition['description'] ) ? $definition['description'] : null;
		$fill_sender = ! empty( $definition['fill_sender'] );
		unset( $definition['handler'], $definition['description'], $definition['fill_sender'] );

		add_filter( 'Joinotify/Builder/Actions', function( $actions ) use ( $definition ) {
			$actions[] = $definition;

			return $actions;
		}, $priority, 1 );

		if ( $handler ) {
			self::register_action_handler( $slug, $handler );
		}

		if ( $description ) {
			self::register_action_description( $slug, $description );
		}

		if ( $fill_sender ) {
			add_filter( 'Joinotify/Download_Template/Fill_Sender_Actions', function( $list ) use ( $slug ) {
				$list[] = $slug;

				return $list;
			}, 10, 1 );
		}
	}


	/**
	 * Register the runtime handler executed when an action of $slug runs in a workflow.
	 *
	 * The callback receives ($action_data, $action, $post_id, $event_data) and should return bool.
	 * - $action_data  array  The action's data payload ($action['data']).
	 * - $action       array  The full action item ({id,type,data,children}).
	 * - $post_id      int    Workflow post ID.
	 * - $event_data   array  Runtime trigger payload.
	 *
	 * @since 1.4.7
	 * @param string   $slug     Action slug.
	 * @param callable $callback Handler.
	 * @return void
	 */
	public static function register_action_handler( $slug, $callback ) {
		$slug = sanitize_key( (string) $slug );

		if ( empty( $slug ) || ! is_callable( $callback ) ) {
			return;
		}

		add_filter( 'Joinotify/Workflow_Processor/Handle_Actions', function( $actions, $action, $post_id, $event_data ) use ( $slug, $callback ) {
			$actions[ $slug ] = function() use ( $callback, $action, $post_id, $event_data ) {
				$action_data = isset( $action['data'] ) ? $action['data'] : array();

				return call_user_func( $callback, $action_data, $action, $post_id, $event_data );
			};

			return $actions;
		}, 10, 4 );
	}


	/**
	 * Register the canvas description builder for a custom action.
	 *
	 * The callback receives ($action_data, $workflow_action) and returns an HTML string.
	 *
	 * @since 1.4.7
	 * @param string   $slug     Action slug.
	 * @param callable $callback Description builder.
	 * @return void
	 */
	public static function register_action_description( $slug, $callback ) {
		$slug = sanitize_key( (string) $slug );

		if ( empty( $slug ) || ! is_callable( $callback ) ) {
			return;
		}

		add_filter( 'Joinotify/Builder/Action_Description', function( $message, $action_slug, $workflow_action ) use ( $slug, $callback ) {
			if ( $action_slug !== $slug ) {
				return $message;
			}

			$action_data = isset( $workflow_action['data'] ) ? $workflow_action['data'] : array();

			return (string) call_user_func( $callback, $action_data, $workflow_action );
		}, 10, 3 );
	}


	/**
	 * ---------------------------------------------------------------------
	 * Triggers & integrations
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register an integration card (Settings → Integrations) — also defines a trigger context.
	 *
	 * @since 1.4.7
	 * @param array $integration {slug, title, description, icon, setting_key?, is_plugin?, plugin_active?, settings?, modal?, ...}.
	 * @param int   $priority    add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_integration( $integration, $priority = 10 ) {
		$slug = isset( $integration['slug'] ) ? sanitize_key( (string) $integration['slug'] ) : '';

		if ( empty( $slug ) ) {
			return;
		}

		add_filter( 'Joinotify/Settings/Tabs/Integrations', function( $integrations ) use ( $slug, $integration ) {
			if ( class_exists( Integrations_Base::class ) && method_exists( Integrations_Base::class, 'build_integration_item' ) ) {
				$integrations[ $slug ] = Integrations_Base::build_integration_item(
					$slug,
					$integration['title'] ?? $slug,
					$integration['description'] ?? '',
					$integration['icon'] ?? '',
					$integration
				);
			} else {
				$integrations[ $slug ] = $integration;
			}

			return $integrations;
		}, $priority, 1 );
	}


	/**
	 * Register a trigger under a context (integration slug).
	 *
	 * @since 1.4.7
	 * @param string $context Context/integration slug (eg: 'woocommerce').
	 * @param array  $trigger {data_trigger, title, description, require_settings?, settings?, category?, icon?}.
	 * @param int    $priority add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_trigger( $context, $trigger, $priority = 10 ) {
		$context = sanitize_key( (string) $context );

		if ( empty( $context ) || empty( $trigger['data_trigger'] ) ) {
			return;
		}

		add_filter( 'Joinotify/Builder/Get_All_Triggers', function( $triggers ) use ( $context, $trigger ) {
			if ( ! isset( $triggers[ $context ] ) || ! is_array( $triggers[ $context ] ) ) {
				$triggers[ $context ] = array();
			}

			$triggers[ $context ][] = $trigger;

			return $triggers;
		}, $priority, 1 );
	}


	/**
	 * Dispatch workflows for a trigger at runtime.
	 *
	 * @since 1.4.7
	 * @param string $hook        Trigger slug (matches a registered trigger's data_trigger).
	 * @param string $integration Context/integration slug.
	 * @param array  $payload     Extra payload keys (order_id, user_id, fields, ...).
	 * @return void
	 */
	public static function dispatch_trigger( $hook, $integration, $payload = array() ) {
		if ( ! class_exists( Workflow_Processor::class ) ) {
			return;
		}

		$payload = is_array( $payload ) ? $payload : array();

		Workflow_Processor::process_workflows( array_merge( array(
			'type' => 'trigger',
			'hook' => (string) $hook,
			'integration' => (string) $integration,
		), $payload ) );
	}


	/**
	 * ---------------------------------------------------------------------
	 * Conditions
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register conditions available for a trigger.
	 *
	 * @since 1.4.7
	 * @param string $trigger    Trigger slug.
	 * @param array  $conditions Map of condition_key => {title, description}.
	 * @param int    $priority   add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_conditions( $trigger, $conditions, $priority = 10 ) {
		$trigger = sanitize_key( (string) $trigger );

		if ( empty( $trigger ) || ! is_array( $conditions ) ) {
			return;
		}

		add_filter( 'Joinotify/Validations/Get_Action_Conditions', function( $map ) use ( $trigger, $conditions ) {
			if ( ! isset( $map[ $trigger ] ) || ! is_array( $map[ $trigger ] ) ) {
				$map[ $trigger ] = array();
			}

			$map[ $trigger ] = array_merge( $map[ $trigger ], $conditions );

			return $map;
		}, $priority, 1 );
	}


	/**
	 * Register the allowed comparison operators for a condition type.
	 *
	 * @since 1.4.7
	 * @param string $condition_type Condition key.
	 * @param array  $operators      List of operator slugs (is, is_not, contains, ...).
	 * @param int    $priority       add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_condition_operators( $condition_type, $operators, $priority = 10 ) {
		$condition_type = sanitize_key( (string) $condition_type );

		if ( empty( $condition_type ) || ! is_array( $operators ) ) {
			return;
		}

		add_filter( 'Joinotify/Conditions/Check_Condition_Type', function( $map ) use ( $condition_type, $operators ) {
			$map[ $condition_type ] = $operators;

			return $map;
		}, $priority, 1 );
	}


	/**
	 * Register the value resolver for a condition type (used at comparison time).
	 *
	 * The callback receives ($value_map, $condition_type, $payload) and must return the resolved
	 * value for $condition_type (or the untouched map for any other type).
	 *
	 * @since 1.4.7
	 * @param string   $condition_type Condition key.
	 * @param callable $callback       Resolver returning the value to compare.
	 * @param int      $priority       add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_condition_value( $condition_type, $callback, $priority = 10 ) {
		$condition_type = sanitize_key( (string) $condition_type );

		if ( empty( $condition_type ) || ! is_callable( $callback ) ) {
			return;
		}

		add_filter( 'Joinotify/Conditions/Get_Compare_Value', function( $value_map, $type = '', $payload = array() ) use ( $condition_type, $callback ) {
			if ( $type !== $condition_type ) {
				return $value_map;
			}

			if ( is_array( $value_map ) ) {
				$value_map[ $condition_type ] = call_user_func( $callback, $value_map, $type, $payload );
			}

			return $value_map;
		}, $priority, 3 );
	}


	/**
	 * ---------------------------------------------------------------------
	 * Placeholders
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register dynamic placeholders for an integration/context.
	 *
	 * @since 1.4.7
	 * @param string $integration  Integration/context slug.
	 * @param array  $placeholders Map of '{{ name }}' => {triggers, description, replacement:{sandbox,production}}.
	 * @param int    $priority     add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_placeholders( $integration, $placeholders, $priority = 10 ) {
		$integration = sanitize_key( (string) $integration );

		if ( empty( $integration ) || ! is_array( $placeholders ) ) {
			return;
		}

		add_filter( 'Joinotify/Builder/Placeholders_List', function( $list, $context = '' ) use ( $integration, $placeholders ) {
			if ( ! is_array( $list ) ) {
				$list = array();
			}

			if ( ! isset( $list[ $integration ] ) || ! is_array( $list[ $integration ] ) ) {
				$list[ $integration ] = array();
			}

			$list[ $integration ] = array_merge( $list[ $integration ], $placeholders );

			return $list;
		}, $priority, 2 );
	}


	/**
	 * ---------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register a settings navigation tab.
	 *
	 * @since 1.4.7
	 * @param array $tab {id, name, icon, section}.
	 * @param int   $priority add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_settings_tab( $tab, $priority = 10 ) {
		if ( empty( $tab['id'] ) ) {
			return;
		}

		add_filter( 'Joinotify/Admin/Settings/Section_Tabs', function( $tabs ) use ( $tab ) {
			$tabs[] = $tab;

			return $tabs;
		}, $priority, 1 );
	}


	/**
	 * Register a settings schema section (a settings page with cards/fields).
	 *
	 * @since 1.4.7
	 * @param array $section {id, title, description?, layout?, cards}.
	 * @param int   $priority add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_settings_section( $section, $priority = 10 ) {
		if ( empty( $section['id'] ) ) {
			return;
		}

		add_filter( 'Joinotify/Admin/Settings/Schema', function( $schema ) use ( $section ) {
			if ( ! is_array( $schema ) ) {
				$schema = array();
			}

			$schema[] = $section;

			return $schema;
		}, $priority, 1 );
	}


	/**
	 * ---------------------------------------------------------------------
	 * REST & bootstrap
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register a custom REST route under the joinotify/v1 namespace.
	 *
	 * @since 1.4.7
	 * @param array $route {route, methods?, callback, permission?, args?}.
	 * @param int   $priority add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_rest_route( $route, $priority = 10 ) {
		if ( empty( $route['route'] ) || empty( $route['callback'] ) ) {
			return;
		}

		add_filter( 'Joinotify/Rest/Routes', function( $routes ) use ( $route ) {
			$routes[] = $route;

			return $routes;
		}, $priority, 1 );
	}


	/**
	 * Register a plugin class to be instantiated on a given bootstrap hook.
	 *
	 * Only classes under the MeuMouse\Joinotify\ namespace are instantiated (enforced by
	 * Init::safe_instance_class). Third parties should wire their own classes on standard WP hooks;
	 * this is provided mainly for internal/add-on modules that live under the plugin namespace.
	 *
	 * @since 1.4.7
	 * @param string $fqcn Fully-qualified class name.
	 * @param string $hook One of: 'init' (default), 'admin_init', 'wp_loaded'.
	 * @return void
	 */
	public static function register_class( $fqcn, $hook = 'init' ) {
		$fqcn = (string) $fqcn;

		if ( empty( $fqcn ) ) {
			return;
		}

		$filter_map = array(
			'init' => 'Joinotify/Init/Init_Classes',
			'admin_init' => 'Joinotify/Init/Admin_Init_Classes',
			'wp_loaded' => 'Joinotify/Init/WP_Loaded_Classes',
		);

		$filter = $filter_map[ $hook ] ?? $filter_map['init'];

		add_filter( $filter, function( $classes ) use ( $fqcn ) {
			if ( ! is_array( $classes ) ) {
				$classes = array();
			}

			$classes[] = $fqcn;

			return $classes;
		}, 10, 1 );
	}


	/**
	 * ---------------------------------------------------------------------
	 * Notification channels
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Register a notification delivery channel (WhatsApp, Telegram, e-mail, SMS, ...).
	 *
	 * The class (or instance) must implement
	 * MeuMouse\Joinotify\Notifications\Channel_Interface. Once registered it can be
	 * targeted by setting Notification_Message->channel to its id and dispatched
	 * through Channel_Manager (see joinotify_dispatch_notification()).
	 *
	 * @since 2.1.0
	 * @param string        $id    Unique channel id (eg: 'telegram').
	 * @param string|object $class Class name or instance implementing Channel_Interface.
	 * @param int           $priority add_filter priority. Default 10.
	 * @return void
	 */
	public static function register_notification_channel( $id, $class, $priority = 10 ) {
		$id = is_string( $id ) ? trim( $id ) : '';

		if ( '' === $id || empty( $class ) ) {
			return;
		}

		add_filter( 'Joinotify/Notifications/Channels', function( $channels ) use ( $id, $class ) {
			if ( ! is_array( $channels ) ) {
				$channels = array();
			}

			$channels[ $id ] = $class;

			return $channels;
		}, $priority, 1 );
	}
}
