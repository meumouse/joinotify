<?php

namespace MeuMouse\Joinotify\Admin\Builder;

use MeuMouse\Joinotify\Admin\Settings\Repository;
use MeuMouse\Joinotify\Api\Workflow_Templates;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Builder\Actions;
use MeuMouse\Joinotify\Builder\Messages;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Builder\Utils;
use MeuMouse\Joinotify\Builder\Workflow_Manager;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Admin\Settings\Registry as Settings_Registry;

defined('ABSPATH') || exit;

/**
 * Builder bootstrap and persistence helpers.
 *
 * @since 1.4.7
 */
class Registry {

	/**
	 * Workflow content schema version, stamped on every saved workflow so future
	 * upgrades can detect and migrate older structures deterministically.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const WORKFLOW_SCHEMA_VERSION = '2.0.0';


	/**
	 * Build the Vue bootstrap payload for the workflow builder.
	 *
	 * @since 1.4.7
	 * @param int $post_id Workflow post ID.
	 * @return array<string,mixed>
	 */
	public static function get_bootstrap_data( $post_id = 0 ) {
		$post_id = absint( $post_id );
		$workflow_state = $post_id > 0 ? self::get_workflow_state( $post_id ) : self::get_default_workflow_state();

		return apply_filters( 'Joinotify/Admin/Builder/Bootstrap_Data', array(
			'version' => JOINOTIFY_VERSION,
			'debug_mode' => defined( 'JOINOTIFY_DEBUG_MODE' ) ? (bool) JOINOTIFY_DEBUG_MODE : false,
			'page' => 'builder',
			'title' => __( 'Workflow builder', 'joinotify' ),
			'settings' => Settings_Registry::get_settings(),
			'phones' => Settings_Registry::get_phone_state(),
			'workflow' => $workflow_state,
			'workflow_file' => self::build_exported_workflow_file( $workflow_state, $post_id ),
			'start_templates' => Workflow_Manager::get_start_templates(),
			'triggers' => self::get_triggers_catalog(),
			'trigger_contexts' => self::get_trigger_contexts_catalog(),
			'trigger_availability' => self::get_workflow_trigger_availability( $workflow_state ),
			'placeholders' => self::get_placeholders_catalog( $workflow_state ),
			'conditions' => self::get_conditions_catalog(),
			'links' => array(
				'back_url' => admin_url( 'admin.php?page=joinotify-workflows' ),
				'dashboard_url' => admin_url( 'admin.php?page=joinotify-workflows' ),
				'settings_url' => admin_url( 'admin.php?page=joinotify-settings' ),
				'docs_url' => esc_url_raw( JOINOTIFY_DOCS_URL ),
				'import_url' => rest_url( 'joinotify/v1/admin/builder/import' ),
			),
			'permissions' => array(
				'manage_options' => current_user_can( 'manage_options' ),
				'can_edit' => current_user_can( 'manage_options' ),
			),
			'rest' => array(
				'root' => esc_url_raw( rest_url( 'joinotify/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
			'ajax' => array(
				'url' => admin_url( 'admin-ajax.php' ),
			),
			'i18n' => array(
				'saved' => __( 'Workflow saved.', 'joinotify' ),
				'saving' => __( 'Saving workflow...', 'joinotify' ),
				'create_from_scratch' => __( 'Create from scratch', 'joinotify' ),
				'create_from_template' => __( 'Create from template', 'joinotify' ),
				'import_template' => __( 'Import template', 'joinotify' ),
				'load_workflow' => __( 'Load workflow', 'joinotify' ),
				'workflow_missing' => __( 'Workflow not found.', 'joinotify' ),
				'unsaved_changes' => __( 'You have unsaved changes.', 'joinotify' ),
				'error' => __( 'Could not complete the operation.', 'joinotify' ),
			),
		) );
	}


	/**
	 * Return a blank builder state.
	 *
	 * @since 1.4.7
	 * @return array<string,mixed>
	 */
	public static function get_default_workflow_state() {
		$default_name = sprintf(
			__( 'My automation #%s', 'joinotify' ),
			function_exists( 'random_int' ) ? random_int( 1000, 999999 ) : mt_rand( 1000, 999999 )
		);

		return array(
			'post_id' => 0,
			'title' => $default_name,
			'status' => 'draft',
			'content' => array(),
			'selected_node_id' => '',
			'created_at' => '',
			'updated_at' => '',
			'edit_url' => '',
			'export_url' => '',
			'loading' => false,
			'is_new' => true,
		);
	}


	/**
	 * Return a normalized state for an existing workflow.
	 *
	 * @since 1.4.7
	 * @param int $post_id Workflow post ID.
	 * @return array<string,mixed>
	 */
	public static function get_workflow_state( $post_id ) {
		$post_id = absint( $post_id );
		$post = get_post( $post_id );

		if ( ! $post || 'joinotify-workflow' !== $post->post_type ) {
			return self::get_default_workflow_state();
		}

		$content = Helpers::get_workflow_content_meta( $post_id );
		$content = is_array( $content ) ? self::sanitize_workflow_content( $content ) : array();
		$selected_node_id = self::find_first_node_id( $content );

		return array(
			'post_id' => $post_id,
			'title' => get_the_title( $post ),
			'status' => $post->post_status,
			'content' => $content,
			'selected_node_id' => $selected_node_id,
			'created_at' => get_post_time( 'Y-m-d H:i:s', false, $post ),
			'updated_at' => get_post_modified_time( 'Y-m-d H:i:s', false, $post ),
			'edit_url' => admin_url( 'admin.php?page=joinotify-workflows-builder&id=' . $post_id ),
			'export_url' => rest_url( 'joinotify/v1/admin/builder/export?id=' . $post_id ),
			'loading' => false,
			'is_new' => false,
		);
	}


	/**
	 * List workflow templates with metadata suitable for the Vue builder.
	 *
	 * @since 1.4.7
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_templates_catalog() {
		$items = Workflow_Templates::get_catalog();
		$catalog = array();

		if ( ! is_array( $items ) ) {
			return $catalog;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			// The API catalog exposes the trigger context as `category` and the
			// trigger key as `trigger`; both drive the labels and availability.
			$context = isset( $item['category'] ) ? sanitize_key( (string) $item['category'] ) : '';
			$trigger_key = isset( $item['trigger'] ) ? sanitize_key( (string) $item['trigger'] ) : '';

			$catalog[] = array(
				'file' => isset( $item['file'] ) ? sanitize_text_field( (string) $item['file'] ) : '',
				'title' => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '',
				'category' => $context,
				'integration' => self::get_integration_label( $context ),
				'trigger' => self::get_trigger_label( $context, $trigger_key ),
				'available' => ! empty( Triggers::get_trigger( $context, $trigger_key ) ),
				'description' => isset( $item['description'] ) ? sanitize_text_field( (string) $item['description'] ) : '',
			);
		}

		return $catalog;
	}


	/**
	 * Return the builder action catalog stripped down to data used by Vue.
	 *
	 * @since 1.4.7
	 * @param string $context Optional context filter.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_actions_catalog( $context = '' ) {
		$actions = Actions::get_all_actions( $context );
		$catalog = array();
		$context = sanitize_key( (string) $context );

		foreach ( $actions as $action ) {
			$action_slug = $action['action'] ?? '';
			$catalog[] = array(
				'action' => $action_slug,
				'title' => $action['title'] ?? '',
				'description' => $action['description'] ?? '',
				'icon' => $action['icon'] ?? '',
				'priority' => isset( $action['priority'] ) ? (int) $action['priority'] : 0,
				'has_settings' => ! empty( $action['has_settings'] ),
				'is_expansible' => ! empty( $action['is_expansible'] ),
				'context' => isset( $action['context'] ) && is_array( $action['context'] ) ? array_values( $action['context'] ) : array(),
				'category' => ! empty( $action['category'] ) ? sanitize_key( (string) $action['category'] ) : 'general',
				'default_data' => self::get_action_default_data( $action_slug, $action ),
				'settings_schema' => self::get_action_settings_schema( $action_slug, $action ),
			);
		}

		return $catalog;
	}


	/**
	 * Return the workflow builder action categories catalog.
	 *
	 * Categories are used as tabs on the builder actions library modal. Third
	 * parties can register categories through the "Joinotify/Builder/Action_Categories"
	 * filter (see Actions::get_action_categories()).
	 *
	 * @since 1.4.7
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_action_categories_catalog() {
		$categories = Actions::get_action_categories();
		$catalog = array();

		foreach ( $categories as $category ) {
			$id = isset( $category['id'] ) ? sanitize_key( (string) $category['id'] ) : '';

			if ( empty( $id ) ) {
				continue;
			}

			$catalog[] = array(
				'id' => $id,
				'label' => isset( $category['label'] ) ? (string) $category['label'] : $id,
				'icon' => isset( $category['icon'] ) ? (string) $category['icon'] : '',
				'priority' => isset( $category['priority'] ) ? (int) $category['priority'] : 0,
			);
		}

		usort( $catalog, function( $left, $right ) {
			return $left['priority'] <=> $right['priority'];
		});

		return $catalog;
	}


	/**
	 * Return the full action definition for a specific action slug.
	 *
	 * @since 1.4.7
	 * @param string $action Action slug.
	 * @return array<string,mixed>|null
	 */
	public static function get_action_definition( $action ) {
		$action = sanitize_key( (string) $action );

		if ( empty( $action ) ) {
			return null;
		}

		foreach ( Actions::get_all_actions() as $item ) {
			if ( empty( $item['action'] ) || $item['action'] !== $action ) {
				continue;
			}

			$definition = array(
				'action' => $item['action'] ?? '',
				'title' => $item['title'] ?? '',
				'description' => $item['description'] ?? '',
				'icon' => $item['icon'] ?? '',
				'priority' => isset( $item['priority'] ) ? (int) $item['priority'] : 0,
				'has_settings' => ! empty( $item['has_settings'] ),
				'is_expansible' => ! empty( $item['is_expansible'] ),
				'context' => isset( $item['context'] ) && is_array( $item['context'] ) ? array_values( $item['context'] ) : array(),
				'default_data' => self::get_action_default_data( $action, $item ),
				'settings_schema' => self::get_action_settings_schema( $action, $item ),
			);

			return $definition;
		}

		return null;
	}


	/**
	 * Return the configuration schema for an action.
	 *
	 * @since 1.4.7
	 * @param string $action Action slug.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_action_settings_schema( $action, $item = array() ) {
		$action = sanitize_key( (string) $action );

		// A definition that ships its own settings_schema always wins — a single catalog
		// source of truth that lets third-party actions (and filtered built-ins) drive the
		// settings UI from PHP. The hardcoded cases below remain a backward-compatible
		// fallback for the built-ins that don't carry an inline schema.
		if ( ! empty( $item['settings_schema'] ) && is_array( $item['settings_schema'] ) ) {
			return apply_filters( 'Joinotify/Builder/Action_Settings_Schema', $item['settings_schema'], $action, $item );
		}

		switch ( $action ) {
			case 'time_delay':
				return array(
					array(
						'key' => 'delay_type',
						'label' => __( 'Delay type', 'joinotify' ),
						'component' => 'select',
						'required' => true,
						'options' => array(
							array( 'label' => __( 'Period', 'joinotify' ), 'value' => 'period' ),
							array( 'label' => __( 'Date', 'joinotify' ), 'value' => 'date' ),
							array( 'label' => __( 'Scheduled', 'joinotify' ), 'value' => 'scheduled' ),
						),
					),
					array(
						'key' => 'delay_value',
						'label' => __( 'Amount', 'joinotify' ),
						'component' => 'number',
						'componentProps' => array(
							'min' => 1,
						),
					),
					array(
						'key' => 'delay_period',
						'label' => __( 'Period', 'joinotify' ),
						'component' => 'select',
						'options' => array(
							array( 'label' => __( 'Seconds', 'joinotify' ), 'value' => 'seconds' ),
							array( 'label' => __( 'Minutes', 'joinotify' ), 'value' => 'minute' ),
							array( 'label' => __( 'Hours', 'joinotify' ), 'value' => 'hours' ),
							array( 'label' => __( 'Days', 'joinotify' ), 'value' => 'day' ),
							array( 'label' => __( 'Weeks', 'joinotify' ), 'value' => 'week' ),
							array( 'label' => __( 'Months', 'joinotify' ), 'value' => 'month' ),
							array( 'label' => __( 'Years', 'joinotify' ), 'value' => 'year' ),
						),
					),
					array(
						'key' => 'date_value',
						'label' => __( 'Date', 'joinotify' ),
						'component' => 'date',
					),
					array(
						'key' => 'time_value',
						'label' => __( 'Time', 'joinotify' ),
						'component' => 'time',
					),
				);

			case 'condition':
				return array(
					array(
						'key' => 'condition',
						'label' => __( 'Condition type', 'joinotify' ),
						'component' => 'select',
						'required' => true,
					),
					array(
						'key' => 'condition_type',
						'label' => __( 'Operator', 'joinotify' ),
						'component' => 'select',
						'required' => true,
					),
					array(
						'key' => 'field_id',
						'label' => __( 'Field ID', 'joinotify' ),
						'component' => 'input',
					),
					array(
						'key' => 'meta_key',
						'label' => __( 'Meta key', 'joinotify' ),
						'component' => 'input',
					),
					array(
						'key' => 'value_text',
						'label' => __( 'Value', 'joinotify' ),
						'component' => 'textarea',
					),
					array(
						'key' => 'type_text',
						'label' => __( 'Type label', 'joinotify' ),
						'component' => 'input',
					),
				);

			case 'snippet_php':
				return array(
					array(
						'key' => 'snippet_php',
						'label' => __( 'PHP code', 'joinotify' ),
						'component' => 'code',
						'required' => true,
						'rows' => 12,
					),
				);

			case 'stop_funnel':
				return array();

			default:
				$schema = isset( $item['settings_schema'] ) && is_array( $item['settings_schema'] ) ? $item['settings_schema'] : array();

				/**
				 * Filter the settings field schema for a custom (third-party) action.
				 *
				 * Built-in actions return their schema from the cases above; custom actions fall
				 * through here, letting third parties drive their settings UI from PHP only — the
				 * frontend renders these fields via DynamicActionSettingsRenderer.vue with no JS.
				 *
				 * @since 1.4.7
				 * @param array  $schema Field schema array (each: {key,label,component,required,options,...}).
				 * @param string $action Action slug.
				 * @param array  $item   Raw action definition.
				 * @return array
				 */
				return apply_filters( 'Joinotify/Builder/Action_Settings_Schema', $schema, sanitize_key( (string) $action ), $item );
		}
	}

	/**
	 * Return the default workflow data for an action.
	 *
	 * @since 1.4.7
	 * @param string $action Action slug.
	 * @param array<string,mixed> $item Raw action item.
	 * @return array<string,mixed>
	 */
	private static function get_action_default_data( $action, $item = array() ) {
		$action = sanitize_key( (string) $action );
		$base_title = isset( $item['title'] ) ? (string) $item['title'] : '';

		// Inline default_data from the (filterable) catalog entry takes precedence, keeping
		// a single source of truth. Built-in cases below act as a backward-compatible fallback.
		if ( ! empty( $item['default_data'] ) && is_array( $item['default_data'] ) ) {
			/**
			 * Filter the default workflow data seeded when an action is dropped on the canvas.
			 *
			 * @since 2.0.0
			 * @param array  $default_data Default data map for the action.
			 * @param string $action       Action slug.
			 * @param array  $item         Raw action definition.
			 */
			return apply_filters( 'Joinotify/Builder/Action_Default_Data', $item['default_data'], $action, $item );
		}

		switch ( $action ) {
			case 'time_delay':
				return array(
					'title' => $base_title ?: esc_html__( 'Delay', 'joinotify' ),
					'description' => '',
					'action' => 'time_delay',
					'delay_type' => 'period',
					'delay_value' => 1,
					'delay_period' => 'minute',
					'date_value' => '',
					'time_value' => '',
				);

			case 'condition':
				return array(
					'title' => $base_title ?: esc_html__( 'Condition', 'joinotify' ),
					'description' => '',
					'action' => 'condition',
					'condition' => '',
					'condition_type' => '',
					'field_id' => '',
					'meta_key' => '',
					'value_text' => '',
					'type_text' => '',
				);

			case 'snippet_php':
				return array(
					'title' => $base_title ?: esc_html__( 'PHP Snippet', 'joinotify' ),
					'description' => '',
					'action' => 'snippet_php',
					'snippet_php' => '',
				);

			case 'stop_funnel':
				return array(
					'title' => $base_title ?: esc_html__( 'Stop automation', 'joinotify' ),
					'description' => '',
					'action' => 'stop_funnel',
				);

			case 'send_whatsapp_message_text':
				return array(
					'title' => $base_title ?: esc_html__( 'WhatsApp: Text message', 'joinotify' ),
					'description' => '',
					'action' => 'send_whatsapp_message_text',
					'message' => '',
					'sender' => '',
					'receiver' => '{{ wc_billing_phone }}',
				);

			case 'send_whatsapp_message_media':
				return array(
					'title' => $base_title ?: esc_html__( 'WhatsApp: Media message', 'joinotify' ),
					'description' => '',
					'action' => 'send_whatsapp_message_media',
					'media_type' => 'image',
					'media_url' => '',
					'caption' => '',
					'sender' => '',
					'receiver' => '{{ wc_billing_phone }}',
				);

			case 'create_coupon':
				return array(
					'title' => $base_title ?: esc_html__( 'Discount coupon', 'joinotify' ),
					'description' => '',
					'action' => 'create_coupon',
					'settings' => array(
						'generate_coupon' => 'yes',
						'coupon_code' => '',
						'coupon_description' => '',
						'discount_type' => 'fixed_cart',
						'coupon_amount' => '',
						'free_shipping' => 'no',
						'coupon_expiry' => 'no',
						'expiry_data' => array(
							'type' => 'period',
							'delay_value' => 1,
							'delay_period' => 'day',
							'date_value' => '',
							'time_value' => '',
						),
						'message' => array(
							'sender' => '',
							'receiver' => '{{ wc_billing_phone }}',
							'message' => '',
						),
					),
				);

			default:
				return isset( $item['default_data'] ) && is_array( $item['default_data'] ) ? $item['default_data'] : array();
		}
	}


	/**
	 * Return the trigger catalog grouped by integration context.
	 *
	 * @since 1.4.7
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function get_triggers_catalog() {
		$all = Triggers::get_all_triggers();
		$catalog = array();
		$context_icons = self::get_trigger_context_icons();

		if ( ! is_array( $all ) ) {
			return $catalog;
		}

		foreach ( $all as $context => $triggers ) {
			if ( ! is_array( $triggers ) ) {
				continue;
			}

			$catalog[ $context ] = array();

			foreach ( $triggers as $trigger ) {
				$catalog[ $context ][] = array(
					'context' => (string) $context,
					'data_trigger' => $trigger['data_trigger'] ?? '',
					'title' => $trigger['title'] ?? '',
					'description' => $trigger['description'] ?? '',
					'icon' => $trigger['icon'] ?? ( $context_icons[ $context ] ?? '' ),
					'require_settings' => ! empty( $trigger['require_settings'] ),
					'category' => $trigger['category'] ?? '',
					'settings' => isset( $trigger['settings'] ) && is_array( $trigger['settings'] ) ? $trigger['settings'] : array(),
				);
			}
		}

		return $catalog;
	}


	/**
	 * Return the enabled trigger integrations with their rendered SVG icon.
	 *
	 * @since 1.4.7
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_trigger_contexts_catalog() {
		$trigger_catalog = self::get_triggers_catalog();
		$integrations = Settings_Registry::get_integration_cards();
		$contexts = array();

		foreach ( $integrations as $integration ) {
			$slug = isset( $integration['slug'] ) ? sanitize_key( (string) $integration['slug'] ) : '';

			if ( empty( $slug ) || empty( $trigger_catalog[ $slug ] ) ) {
				continue;
			}

			if ( empty( $integration['enabled'] ) ) {
				continue;
			}

			$contexts[] = array(
				'id' => $slug,
				'label' => $integration['title'] ?? ucfirst( $slug ),
				'description' => $integration['description'] ?? '',
				'icon_svg' => $integration['icon'] ?? '',
				'category' => $integration['setting_key'] ?? $slug,
			);
		}

		return $contexts;
	}


	/**
	 * Evaluate whether the trigger used by a workflow is currently available.
	 *
	 * A workflow can be built while an integration is active and later become
	 * unprocessable if that integration is disabled, its required plugin is
	 * deactivated/uninstalled, or the trigger itself is no longer registered.
	 * The builder uses this assessment to warn the user that the flow may fail
	 * to process before it runs silently into nothing.
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $workflow_state Normalized workflow state payload.
	 * @return array<string,mixed>
	 */
	public static function get_workflow_trigger_availability( $workflow_state ) {
		$context = '';
		$trigger = '';

		if ( ! empty( $workflow_state['content'][0]['data']['context'] ) ) {
			$context = (string) $workflow_state['content'][0]['data']['context'];
		}

		if ( ! empty( $workflow_state['content'][0]['data']['trigger'] ) ) {
			$trigger = (string) $workflow_state['content'][0]['data']['trigger'];
		}

		// No trigger configured yet (new/empty flow): nothing to warn about.
		if ( '' === $context || '' === $trigger ) {
			return array(
				'has_trigger' => false,
				'available' => true,
				'context' => $context,
				'trigger' => $trigger,
				'integration_label' => '',
				'reason' => '',
			);
		}

		$integration = null;

		foreach ( Settings_Registry::get_integration_cards() as $card ) {
			if ( isset( $card['slug'] ) && (string) $card['slug'] === $context ) {
				$integration = $card;
				break;
			}
		}

		$integration_label = ! empty( $integration['title'] )
			? (string) $integration['title']
			: ucwords( str_replace( array( '_', '-' ), ' ', $context ) );
		$integration_enabled = $integration ? ! empty( $integration['enabled'] ) : false;
		$requires_plugin = $integration ? ! empty( $integration['requires_plugin'] ) : false;
		$plugin_active = $integration ? ! empty( $integration['plugin_active'] ) : false;
		$trigger_exists = Triggers::get_trigger( $context, $trigger ) !== null;

		// Resolve a single, most-actionable reason. The order matters: a missing
		// integration/plugin is the root cause and should be surfaced before the
		// downstream "trigger not registered" symptom it produces.
		$reason = '';

		if ( ! $integration ) {
			$reason = 'integration_unavailable';
		} elseif ( $requires_plugin && ! $plugin_active ) {
			$reason = 'plugin_inactive';
		} elseif ( ! $integration_enabled ) {
			$reason = 'integration_disabled';
		} elseif ( ! $trigger_exists ) {
			$reason = 'trigger_not_found';
		}

		return array(
			'has_trigger' => true,
			'available' => ( '' === $reason ),
			'context' => $context,
			'trigger' => $trigger,
			'integration_label' => $integration_label,
			'integration_enabled' => $integration_enabled,
			'requires_plugin' => $requires_plugin,
			'plugin_active' => $plugin_active,
			'trigger_exists' => $trigger_exists,
			'reason' => $reason,
		);
	}


	/**
	 * Return the icon slug used for each trigger context.
	 *
	 * @since 1.4.7
	 * @return array<string,string>
	 */
	private static function get_trigger_context_icons() {
		/**
		 * Filter the fallback icon slug used for each trigger context.
		 *
		 * Used only when a trigger (and its integration card) does not provide its own icon.
		 * Third parties can map their custom context slug to an icon here.
		 *
		 * @since 1.4.7
		 * @param array<string,string> $icons Map of context slug => icon slug/markup.
		 * @return array<string,string>
		 */
		return apply_filters( 'Joinotify/Builder/Trigger_Context_Icons', array(
			'wordpress' => 'wordpress',
			'woocommerce' => 'shopping-cart',
			'flexify_checkout' => 'credit-card',
			'elementor' => 'elementor',
			'wpforms' => 'wpforms',
		) );
	}


	/**
	 * Provide placeholder data for the current workflow context.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $workflow_state Workflow state payload.
	 * @return array<string,mixed>
	 */
	public static function get_placeholders_catalog( $workflow_state ) {
		$context = '';
		$trigger = '';

		if ( ! empty( $workflow_state['content'][0]['data']['context'] ) ) {
			$context = (string) $workflow_state['content'][0]['data']['context'];
		}

		if ( ! empty( $workflow_state['content'][0]['data']['trigger'] ) ) {
			$trigger = (string) $workflow_state['content'][0]['data']['trigger'];
		}

		return Placeholders::get_placeholders_list( $context, $trigger );
	}


	/**
	 * Build the real exported Joinotify workflow payload.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $workflow_state Normalized workflow state or legacy builder payload.
	 * @param int $post_id Workflow post ID.
	 * @return array<string,mixed>
	 */
	public static function build_exported_workflow_file( $workflow_state, $post_id = 0 ) {
		$post_id = absint( $post_id );
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		$content = array();

		if ( isset( $workflow_state['workflow_content'] ) && is_array( $workflow_state['workflow_content'] ) ) {
			$content = self::sanitize_workflow_content( $workflow_state['workflow_content'] );
		} elseif ( isset( $workflow_state['content'] ) && is_array( $workflow_state['content'] ) ) {
			$content = self::sanitize_workflow_content( $workflow_state['content'] );
		}

		$trigger_data = self::get_primary_trigger_data( $content );
		$title = '';
		$status = 'draft';
		$date = current_time( 'mysql' );
		$modified = current_time( 'mysql' );
		$category = '';

		if ( isset( $workflow_state['post'] ) && is_array( $workflow_state['post'] ) ) {
			$title = isset( $workflow_state['post']['title'] ) ? sanitize_text_field( (string) $workflow_state['post']['title'] ) : '';
			$status = isset( $workflow_state['post']['status'] ) ? sanitize_key( (string) $workflow_state['post']['status'] ) : $status;
			$date = isset( $workflow_state['post']['date'] ) ? sanitize_text_field( (string) $workflow_state['post']['date'] ) : $date;
			$modified = isset( $workflow_state['post']['modified'] ) ? sanitize_text_field( (string) $workflow_state['post']['modified'] ) : $modified;
			$category = isset( $workflow_state['post']['category'] ) ? sanitize_key( (string) $workflow_state['post']['category'] ) : $category;
		} else {
			$title = isset( $workflow_state['title'] ) ? sanitize_text_field( (string) $workflow_state['title'] ) : ( $post ? get_the_title( $post ) : '' );
			$status = isset( $workflow_state['status'] ) ? sanitize_key( (string) $workflow_state['status'] ) : ( $post ? $post->post_status : 'draft' );
			$date = isset( $workflow_state['created_at'] ) ? sanitize_text_field( (string) $workflow_state['created_at'] ) : ( $post ? get_post_time( 'Y-m-d H:i:s', false, $post ) : $date );
			$modified = isset( $workflow_state['updated_at'] ) ? sanitize_text_field( (string) $workflow_state['updated_at'] ) : ( $post ? get_post_modified_time( 'Y-m-d H:i:s', false, $post ) : $modified );
			$category = isset( $workflow_state['category'] ) ? sanitize_key( (string) $workflow_state['category'] ) : $category;
		}

		if ( empty( $category ) && ! empty( $trigger_data['context'] ) ) {
			$category = sanitize_key( $trigger_data['context'] );
		}

		return array(
			'plugin_version' => JOINOTIFY_VERSION,
			'post' => array(
				'type' => 'joinotify-workflow',
				'title' => $title,
				'date' => $date,
				'status' => $status,
				'modified' => $modified,
				'category' => $category,
			),
			'workflow_content' => $content,
		);
	}


	/**
	 * Normalize an incoming workflow file payload from import/save requests.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $payload Incoming payload.
	 * @return array<string,mixed>
	 */
	private static function normalize_workflow_file_payload( $payload ) {
		$payload = is_array( $payload ) ? $payload : array();
		$file = isset( $payload['workflow_file'] ) && is_array( $payload['workflow_file'] ) ? $payload['workflow_file'] : $payload;
		$legacy_workflow = isset( $file['workflow'] ) && is_array( $file['workflow'] ) ? $file['workflow'] : array();
		$content = array();

		if ( isset( $file['workflow_content'] ) && is_array( $file['workflow_content'] ) ) {
			$content = $file['workflow_content'];
		} elseif ( isset( $file['content'] ) && is_array( $file['content'] ) ) {
			$content = $file['content'];
		} elseif ( isset( $legacy_workflow['content'] ) && is_array( $legacy_workflow['content'] ) ) {
			$content = $legacy_workflow['content'];
		}

		$post = array(
			'type' => 'joinotify-workflow',
			'title' => '',
			'date' => current_time( 'mysql' ),
			'status' => 'draft',
			'modified' => current_time( 'mysql' ),
			'category' => '',
		);

		if ( isset( $file['post'] ) && is_array( $file['post'] ) ) {
			$post = array_merge( $post, $file['post'] );
		} elseif ( ! empty( $legacy_workflow ) ) {
			$post['title'] = isset( $legacy_workflow['title'] ) ? sanitize_text_field( (string) $legacy_workflow['title'] ) : '';
			$post['status'] = isset( $legacy_workflow['status'] ) ? sanitize_key( (string) $legacy_workflow['status'] ) : 'draft';
			$post['date'] = isset( $legacy_workflow['created_at'] ) ? sanitize_text_field( (string) $legacy_workflow['created_at'] ) : current_time( 'mysql' );
			$post['modified'] = isset( $legacy_workflow['updated_at'] ) ? sanitize_text_field( (string) $legacy_workflow['updated_at'] ) : current_time( 'mysql' );
			$post['category'] = isset( $legacy_workflow['category'] ) ? sanitize_key( (string) $legacy_workflow['category'] ) : '';
		}

		$plugin_version = isset( $file['plugin_version'] ) ? sanitize_text_field( (string) $file['plugin_version'] ) : JOINOTIFY_VERSION;

		// Convert legacy structures up to the current schema before the content is
		// sanitized and persisted. This is the single conversion point shared by
		// both the import endpoint and the builder save, so a pre-2.0.0 flow becomes
		// canonical regardless of how it entered. Idempotent and version-gated, so a
		// current-schema payload passes through untouched.
		$content = Workflow_Migrator::migrate_content( $content, $plugin_version );

		if ( empty( $post['category'] ) ) {
			$trigger_data = self::get_primary_trigger_data( self::sanitize_workflow_content( $content ) );
			if ( ! empty( $trigger_data['context'] ) ) {
				$post['category'] = sanitize_key( $trigger_data['context'] );
			}
		}

		return array(
			'plugin_version' => $plugin_version,
			'post' => $post,
			'workflow_content' => $content,
		);
	}


	/**
	 * Resolve the primary trigger from a workflow content array.
	 *
	 * @since 1.4.7
	 * @param array<int,array<string,mixed>> $workflow_content Workflow nodes.
	 * @return array{context:string,trigger:string}
	 */
	private static function get_primary_trigger_data( $workflow_content ) {
		foreach ( $workflow_content as $item ) {
			if ( isset( $item['type'], $item['data'] ) && 'trigger' === $item['type'] && is_array( $item['data'] ) ) {
				return array(
					'context' => isset( $item['data']['context'] ) ? sanitize_key( (string) $item['data']['context'] ) : '',
					'trigger' => isset( $item['data']['trigger'] ) ? sanitize_text_field( (string) $item['data']['trigger'] ) : '',
				);
			}
		}

		return array(
			'context' => '',
			'trigger' => '',
		);
	}


	/**
	 * Create a blank workflow in the database.
	 *
	 * @since 1.4.7
	 * @param string $title Workflow title.
	 * @return array<string,mixed>
	 */
	public static function create_blank_workflow( $title = '' ) {
		return self::create_workflow_from_content( $title, array() );
	}


	/**
	 * Create a workflow from a trigger selection.
	 *
	 * @since 1.4.8
	 * @param string $title Workflow title.
	 * @param string $context Trigger context.
	 * @param string $trigger Trigger slug.
	 * @param array<string,mixed> $settings Optional trigger settings.
	 * @return array<string,mixed>
	 */
	public static function create_workflow_from_trigger( $title = '', $context = '', $trigger = '', $settings = array() ) {
		$title = sanitize_text_field( $title );
		$context = sanitize_key( (string) $context );
		$trigger = sanitize_key( (string) $trigger );
		$settings = is_array( $settings ) ? $settings : array();
		$trigger_definition = $context && $trigger ? Triggers::get_trigger( $context, $trigger ) : null;
		$trigger_node = array(
			'id' => uniqid( 'joinotify_trigger_' ),
			'type' => 'trigger',
			'data' => array(
				'title' => $title,
				'description' => is_array( $trigger_definition ) && ! empty( $trigger_definition['description'] ) ? (string) $trigger_definition['description'] : '',
				'trigger' => $trigger,
				'context' => $context,
				'settings' => $settings,
			),
			'children' => array(),
		);

		if ( is_array( $trigger_definition ) && ! empty( $trigger_definition['title'] ) ) {
			$trigger_node['data']['title'] = sanitize_text_field( (string) $trigger_definition['title'] );
		}

		return self::create_workflow_from_content( $title, array( $trigger_node ) );
	}


	/**
	 * Create a workflow from an initial content payload.
	 *
	 * @since 1.4.8
	 * @param string $title Workflow title.
	 * @param array<int,array<string,mixed>> $workflow_content Initial workflow nodes.
	 * @return array<string,mixed>
	 */
	public static function create_workflow_from_content( $title = '', $workflow_content = array() ) {
		$title = sanitize_text_field( $title );

		if ( empty( $title ) ) {
			$title = sprintf(
				__( 'My automation #%s', 'joinotify' ),
				function_exists( 'random_int' ) ? random_int( 1000, 999999 ) : mt_rand( 1000, 999999 )
			);
		}

		$workflow_content = is_array( $workflow_content ) ? self::sanitize_workflow_content( $workflow_content ) : array();

		$post_id = wp_insert_post( array(
			'post_title' => $title,
			'post_status' => 'draft',
			'post_type' => 'joinotify-workflow',
			'post_content' => '',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'status' => 'error',
				'message' => $post_id->get_error_message(),
			);
		}

		Helpers::update_workflow_content_meta( $post_id, $workflow_content );

		$workflow_state = self::get_workflow_state( $post_id );

		return array(
			'status' => 'success',
			'post_id' => $post_id,
			'workflow' => $workflow_state,
			'workflow_file' => self::build_exported_workflow_file( $workflow_state, $post_id ),
		);
	}


	/**
	 * Create a workflow from a template file.
	 *
	 * @since 1.4.7
	 * @param string $template_file Template filename.
	 * @param string $title Optional title override.
	 * @return array<string,mixed>
	 */
	public static function create_workflow_from_template( $template_file, $title = '' ) {
		$template_file = sanitize_text_field( $template_file );
		$decoded = Workflow_Templates::get_template( $template_file );

		if ( ! is_array( $decoded ) ) {
			return array(
				'status' => 'error',
				'message' => __( 'The selected template was not found.', 'joinotify' ),
			);
		}

		$post_title = $title ? sanitize_text_field( $title ) : ( isset( $decoded['post']['title'] ) ? sanitize_text_field( $decoded['post']['title'] ) : $template_file );
		$workflow_content = isset( $decoded['workflow_content'] ) && is_array( $decoded['workflow_content'] ) ? self::sanitize_workflow_content( $decoded['workflow_content'] ) : array();

		if ( ! empty( $workflow_content ) && is_array( $workflow_content ) ) {
			Actions::fill_sender_recursive( $workflow_content );
		}

		$post_id = wp_insert_post( array(
			'post_title' => $post_title,
			'post_status' => 'draft',
			'post_type' => 'joinotify-workflow',
			'post_content' => '',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'status' => 'error',
				'message' => $post_id->get_error_message(),
			);
		}

		Helpers::update_workflow_content_meta( $post_id, $workflow_content );

		return array(
			'status' => 'success',
			'workflow' => self::get_workflow_state( $post_id ),
			'workflow_file' => self::build_exported_workflow_file( self::get_workflow_state( $post_id ), $post_id ),
		);
	}


	/**
	 * Save a workflow payload.
	 *
	 * @since 1.4.7
	 * @param int $post_id Workflow post ID.
	 * @param array<string,mixed> $payload Incoming workflow payload.
	 * @return array<string,mixed>
	 */
	public static function save_workflow( $post_id, $payload ) {
		$post_id = absint( $post_id );
		$post = get_post( $post_id );

		if ( ! $post || 'joinotify-workflow' !== $post->post_type ) {
			return array(
				'status' => 'error',
				'message' => __( 'Workflow not found.', 'joinotify' ),
			);
		}

		$normalized = self::normalize_workflow_file_payload( $payload );
		$title = isset( $normalized['post']['title'] ) ? sanitize_text_field( (string) $normalized['post']['title'] ) : $post->post_title;
		$status = isset( $normalized['post']['status'] ) ? sanitize_key( (string) $normalized['post']['status'] ) : $post->post_status;
		$status = in_array( $status, array( 'publish', 'draft', 'trash' ), true ) ? $status : $post->post_status;
		$content = isset( $normalized['workflow_content'] ) && is_array( $normalized['workflow_content'] ) ? self::sanitize_workflow_content( $normalized['workflow_content'] ) : array();

		// Validate before persisting so the runtime never has to process a broken flow.
		$validation = self::validate_workflow_content( $content );
		$forced_draft = false;

		// A structurally invalid workflow is never published; it is kept as a draft
		// and the validation errors are returned so the builder can surface them.
		if ( 'publish' === $status && self::has_blocking_errors( $validation ) ) {
			$status = 'draft';
			$forced_draft = true;
		}

		$updated = wp_update_post( array(
			'ID' => $post_id,
			'post_title' => $title,
			'post_status' => $status,
		), true );

		if ( is_wp_error( $updated ) ) {
			return array(
				'status' => 'error',
				'message' => $updated->get_error_message(),
			);
		}

		Helpers::update_workflow_content_meta( $post_id, $content );

		// Keep the trigger-hook index and schema version in sync on every save.
		self::update_workflow_indexes( $post_id, $content );

		return array(
			'status' => 'success',
			'message' => $forced_draft
				? esc_html__( 'Workflow saved as draft: fix the reported issues before publishing.', 'joinotify' )
				: esc_html__( 'Workflow saved.', 'joinotify' ),
			'validation' => $validation,
			'forced_draft' => $forced_draft,
			'workflow' => self::get_workflow_state( $post_id ),
			'workflow_file' => self::build_exported_workflow_file( self::get_workflow_state( $post_id ), $post_id ),
		);
	}


	/**
	 * Export the workflow payload as JSON-friendly data.
	 *
	 * @since 1.4.7
	 * @param int $post_id Workflow post ID.
	 * @return array<string,mixed>
	 */
	public static function export_workflow( $post_id ) {
		$state = self::get_workflow_state( $post_id );

		if ( empty( $state['post_id'] ) ) {
			return array(
				'status' => 'error',
				'message' => __( 'Workflow not found.', 'joinotify' ),
			);
		}

		return array(
			'status' => 'success',
			'workflow' => $state,
			'workflow_file' => self::build_exported_workflow_file( $state, $post_id ),
			'exported_at' => current_time( 'mysql' ),
		);
	}


	/**
	 * Import a workflow payload and persist it as a new workflow.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $payload Incoming import payload.
	 * @return array<string,mixed>
	 */
	public static function import_workflow( $payload ) {
		// normalize_workflow_file_payload() runs the legacy->current schema migration.
		$normalized = self::normalize_workflow_file_payload( $payload );
		$title = isset( $normalized['post']['title'] ) ? sanitize_text_field( $normalized['post']['title'] ) : '';
		$content = isset( $normalized['workflow_content'] ) && is_array( $normalized['workflow_content'] ) ? self::sanitize_workflow_content( $normalized['workflow_content'] ) : array();

		$post_id = wp_insert_post( array(
			'post_title' => $title ?: sprintf(
				__( 'Imported automation #%s', 'joinotify' ),
				function_exists( 'random_int' ) ? random_int( 1000, 999999 ) : mt_rand( 1000, 999999 )
			),
			'post_status' => 'draft',
			'post_type' => 'joinotify-workflow',
			'post_content' => '',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'status' => 'error',
				'message' => $post_id->get_error_message(),
			);
		}

		Helpers::update_workflow_content_meta( $post_id, $content );

		// index the imported workflow's trigger hook + schema version
		self::update_workflow_indexes( $post_id, $content );

		return array(
			'status' => 'success',
			'workflow' => self::get_workflow_state( $post_id ),
			'workflow_file' => self::build_exported_workflow_file( self::get_workflow_state( $post_id ), $post_id ),
		);
	}


	/**
	 * Build an integration label for a context key.
	 *
	 * @since 1.4.7
	 * @param string $context Context key.
	 * @return string
	 */
    private static function get_integration_label( $context ) {
        $categories = Utils::get_template_categories();
        $context = is_scalar( $context ) ? (string) $context : '';

        return isset( $categories[ $context ] ) ? (string) $categories[ $context ] : ucfirst( str_replace( '_', ' ', $context ) );
    }


	/**
	 * Build a trigger label from the current trigger registry.
	 *
	 * @since 1.4.7
	 * @param string $context Context key.
	 * @param string $trigger Trigger slug.
	 * @return string
	 */
	private static function get_trigger_label( $context, $trigger ) {
		$found = Triggers::get_trigger( $context, $trigger );

		return is_array( $found ) && ! empty( $found['title'] ) ? (string) $found['title'] : $trigger;
	}


	/**
	 * Return the first node id for selection in the canvas.
	 *
	 * @since 1.4.7
	 * @param array<int,array<string,mixed>> $content Workflow content.
	 * @return string
	 */
	private static function find_first_node_id( $content ) {
		foreach ( $content as $item ) {
			if ( ! empty( $item['id'] ) ) {
				return (string) $item['id'];
			}
		}

		return '';
	}


	/**
	 * Normalize workflow content and remove unsafe values.
	 *
	 * @since 1.4.7
	 * @param array<int,mixed> $content Workflow content.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_workflow_content( $content ) {
		$sanitized = array();

		foreach ( $content as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$sanitized[] = self::sanitize_workflow_node( $item );
		}

		return Helpers::decode_emoji_deep( $sanitized );
	}


	/**
	 * Keep the per-workflow runtime indexes in sync with the saved content.
	 *
	 * Writes the dedicated, queryable `_joinotify_trigger_hook` meta (so the
	 * runtime can select workflows by an indexed exact match instead of a fragile
	 * LIKE over serialized content) and stamps the schema version.
	 *
	 * @since 2.0.0
	 * @param int $post_id Workflow post ID.
	 * @param array<int,mixed> $content Sanitized workflow content.
	 * @return void
	 */
	public static function update_workflow_indexes( $post_id, $content ) {
		$hook = self::extract_trigger_hook( $content );

		if ( '' !== $hook ) {
			update_post_meta( $post_id, '_joinotify_trigger_hook', $hook );
		} else {
			delete_post_meta( $post_id, '_joinotify_trigger_hook' );
		}

		update_post_meta( $post_id, '_joinotify_workflow_schema_version', self::WORKFLOW_SCHEMA_VERSION );
	}


	/**
	 * Extract the trigger hook (data.trigger) from a workflow content array.
	 *
	 * @since 2.0.0
	 * @param array<int,mixed> $content Workflow content.
	 * @return string The trigger hook, or '' when absent/unconfigured.
	 */
	private static function extract_trigger_hook( $content ) {
		if ( ! is_array( $content ) ) {
			return '';
		}

		foreach ( $content as $node ) {
			if ( is_array( $node ) && isset( $node['type'] ) && 'trigger' === $node['type'] ) {
				$hook = $node['data']['trigger'] ?? '';

				return is_string( $hook ) ? sanitize_text_field( $hook ) : '';
			}
		}

		return '';
	}


	/**
	 * Validate a workflow content structure before persistence.
	 *
	 * Returns a list of issues (each: code/severity/message[/node_id]). Blocking
	 * errors prevent publishing (see save_workflow), so the runtime only ever sees
	 * structurally sound flows: exactly one configured trigger and conditions that
	 * carry their true/false branch containers.
	 *
	 * @since 2.0.0
	 * @param array<int,mixed> $content Sanitized workflow content.
	 * @return array<int,array<string,string>>
	 */
	public static function validate_workflow_content( $content ) {
		$errors = array();

		if ( ! is_array( $content ) ) {
			$errors[] = array(
				'code' => 'invalid_content',
				'severity' => 'error',
				'message' => __( 'The workflow content is invalid.', 'joinotify' ),
			);

			return $errors;
		}

		$triggers = array_values( array_filter( $content, function ( $node ) {
			return is_array( $node ) && isset( $node['type'] ) && 'trigger' === $node['type'];
		}));

		if ( 0 === count( $triggers ) ) {
			$errors[] = array(
				'code' => 'missing_trigger',
				'severity' => 'error',
				'message' => __( 'The workflow has no trigger.', 'joinotify' ),
			);
		} elseif ( count( $triggers ) > 1 ) {
			$errors[] = array(
				'code' => 'multiple_triggers',
				'severity' => 'error',
				'message' => __( 'The workflow has more than one trigger.', 'joinotify' ),
			);
		} elseif ( '' === self::extract_trigger_hook( $content ) ) {
			$errors[] = array(
				'code' => 'trigger_not_configured',
				'severity' => 'error',
				'message' => __( 'The workflow trigger is not configured.', 'joinotify' ),
			);
		}

		self::collect_node_errors( $content, $errors );

		return $errors;
	}


	/**
	 * Recursively collect node-level validation issues.
	 *
	 * @since 2.0.0
	 * @param array<int,mixed> $nodes Nodes to inspect.
	 * @param array<int,array<string,string>> $errors Accumulator (by reference).
	 * @return void
	 */
	private static function collect_node_errors( $nodes, &$errors ) {
		if ( ! is_array( $nodes ) ) {
			return;
		}

		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$type = $node['type'] ?? '';
			$action = $node['data']['action'] ?? '';
			$children = $node['children'] ?? array();

			if ( 'condition' === $type || 'condition' === $action ) {
				if ( ! self::is_branch_container( $children ) ) {
					$errors[] = array(
						'code' => 'condition_missing_branches',
						'severity' => 'error',
						'message' => __( 'A condition is missing its branches.', 'joinotify' ),
						'node_id' => isset( $node['id'] ) ? (string) $node['id'] : '',
					);

					continue;
				}

				self::collect_node_errors( $children['action_true'] ?? array(), $errors );
				self::collect_node_errors( $children['action_false'] ?? array(), $errors );

				continue;
			}

			// defensive: recurse into any linear children
			if ( is_array( $children ) && ! self::is_branch_container( $children ) ) {
				self::collect_node_errors( $children, $errors );
			}
		}
	}


	/**
	 * Whether a validation result contains at least one blocking error.
	 *
	 * @since 2.0.0
	 * @param array<int,array<string,string>> $errors Validation result.
	 * @return bool
	 */
	private static function has_blocking_errors( $errors ) {
		foreach ( (array) $errors as $error ) {
			if ( 'error' === ( $error['severity'] ?? 'error' ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Backfill the trigger-hook index for every existing workflow.
	 *
	 * Hooked on `Joinotify/Upgraded` so workflows saved before the index existed
	 * get a `_joinotify_trigger_hook` meta, after which get_workflows_by_hook()
	 * resolves them via the fast, exact-match path instead of the LIKE fallback.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function backfill_trigger_hook_index() {
		$workflows = get_posts( array(
			'post_type' => 'joinotify-workflow',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		));

		if ( empty( $workflows ) ) {
			return;
		}

		foreach ( $workflows as $workflow_id ) {
			$content = Helpers::get_workflow_content_meta( $workflow_id );

			if ( is_array( $content ) ) {
				self::update_workflow_indexes( $workflow_id, $content );
			}
		}
	}


	/**
	 * Build the conditions catalog for the builder.
	 *
	 * The runtime engine is the single source of truth: the per-trigger condition
	 * keys come from Conditions::get_conditions_by_trigger() and the operators
	 * allowed for each key come from Conditions::check_condition_type(). The
	 * builder consumes this so a user can only pick conditions/operators the
	 * engine actually evaluates (previously the UI offered invalid operators like
	 * start_with/finish_with that never matched at runtime).
	 *
	 * Shape:
	 *   operators: { op_key => label }
	 *   triggers:  { trigger_id => [ { key, title, description, operators[], value_type, requires[] } ] }
	 *
	 * @since 2.0.0
	 * @return array<string,mixed>
	 */
	public static function get_conditions_catalog() {
		// Canonical operator labels — single source of truth for the builder.
		$operator_labels = apply_filters( 'Joinotify/Builder/Condition_Operators', array(
			'is' => __( 'Is equal to', 'joinotify' ),
			'is_not' => __( 'Is not equal to', 'joinotify' ),
			'contains' => __( 'Contains', 'joinotify' ),
			'not_contain' => __( 'Does not contain', 'joinotify' ),
			'empty' => __( 'Is empty', 'joinotify' ),
			'not_empty' => __( 'Is not empty', 'joinotify' ),
			'bigger_than' => __( 'Greater than', 'joinotify' ),
			'less_than' => __( 'Less than', 'joinotify' ),
		));

		// Value-input hints per condition key, so the builder renders the right
		// control and collects any extra field the runtime needs.
		$value_types = apply_filters( 'Joinotify/Builder/Condition_Value_Types', array(
			'order_status' => array( 'type' => 'order_status' ),
			'subscription_status' => array( 'type' => 'text' ),
			'payment_method' => array( 'type' => 'payment_method' ),
			'shipping_method' => array( 'type' => 'shipping_method' ),
			'products_purchased' => array( 'type' => 'products' ),
			'order_paid' => array( 'type' => 'boolean' ),
			'cart_recovered' => array( 'type' => 'boolean' ),
			'order_total' => array( 'type' => 'number' ),
			'refund_amount' => array( 'type' => 'number' ),
			'cart_total' => array( 'type' => 'number' ),
			'items_in_cart' => array( 'type' => 'number' ),
			'user_last_login' => array( 'type' => 'number' ),
			'user_meta' => array( 'type' => 'text', 'requires' => array( 'meta_key' ) ),
			'field_value' => array( 'type' => 'text', 'requires' => array( 'field_id' ) ),
		));

		// Dynamic value options per condition key (e.g. WooCommerce statuses), so
		// the builder can render a proper select instead of free text.
		$options_map = apply_filters( 'Joinotify/Builder/Condition_Options', array(
			'order_status' => self::get_order_status_condition_options(),
			'subscription_status' => self::get_subscription_status_condition_options(),
			'payment_method' => self::get_payment_method_condition_options(),
			'shipping_method' => self::get_shipping_method_condition_options(),
		));

		// Per-trigger condition map populated by each integration's add_conditions().
		$conditions_map = apply_filters( 'Joinotify/Validations/Get_Action_Conditions', array() );
		$triggers = array();

		foreach ( $conditions_map as $trigger_id => $conditions ) {
			if ( ! is_array( $conditions ) ) {
				continue;
			}

			foreach ( $conditions as $key => $meta ) {
				// skip the "no condition available" placeholder
				if ( 'no_action' === $key || ! is_array( $meta ) ) {
					continue;
				}

				$operators = Conditions::check_condition_type( $key );

				// only expose conditions the engine can actually evaluate
				if ( empty( $operators ) ) {
					continue;
				}

				$hint = $value_types[ $key ] ?? array();

				$triggers[ $trigger_id ][] = array(
					'key' => $key,
					'title' => isset( $meta['title'] ) ? (string) $meta['title'] : $key,
					'description' => isset( $meta['description'] ) ? (string) $meta['description'] : '',
					'operators' => array_values( $operators ),
					'value_type' => isset( $hint['type'] ) ? (string) $hint['type'] : 'text',
					'requires' => isset( $hint['requires'] ) && is_array( $hint['requires'] ) ? array_values( $hint['requires'] ) : array(),
					'options' => isset( $options_map[ $key ] ) && is_array( $options_map[ $key ] ) ? array_values( $options_map[ $key ] ) : array(),
				);
			}
		}

		return array(
			'operators' => $operator_labels,
			'triggers' => $triggers,
		);
	}


	/**
	 * Order-status options for the `order_status` condition value.
	 *
	 * Values are stored without the `wc-` prefix because the runtime compares
	 * them against WC_Order::get_status() (which is unprefixed).
	 *
	 * @since 2.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function get_order_status_condition_options() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		$options = array();

		foreach ( wc_get_order_statuses() as $key => $label ) {
			$options[] = array(
				'label' => (string) $label,
				'value' => str_replace( 'wc-', '', (string) $key ),
			);
		}

		return $options;
	}


	/**
	 * Subscription-status options for the `subscription_status` condition value.
	 *
	 * @since 2.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function get_subscription_status_condition_options() {
		if ( ! function_exists( 'wcs_get_subscription_statuses' ) ) {
			return array();
		}

		$options = array();

		foreach ( wcs_get_subscription_statuses() as $key => $label ) {
			$options[] = array(
				'label' => (string) $label,
				'value' => str_replace( 'wc-', '', (string) $key ),
			);
		}

		return $options;
	}


	/**
	 * Payment-method options for the `payment_method` condition value.
	 *
	 * Values are the gateway IDs because the runtime compares them against
	 * WC_Order::get_payment_method() (which returns the gateway ID).
	 *
	 * @since 2.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function get_payment_method_condition_options() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}

		$options = array();

		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			$title = $gateway->get_title();

			$options[] = array(
				'label' => (string) ( '' !== $title ? $title : $gateway->get_method_title() ),
				'value' => (string) $id,
			);
		}

		return $options;
	}


	/**
	 * Shipping-method options for the `shipping_method` condition value.
	 *
	 * Values are the registered method IDs (e.g. `flat_rate`, `free_shipping`)
	 * because the runtime compares them against the base of the order shipping
	 * item method ID (the part before the `:instance` suffix).
	 *
	 * @since 2.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function get_shipping_method_condition_options() {
		if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) {
			return array();
		}

		$options = array();

		foreach ( WC()->shipping()->get_shipping_methods() as $id => $method ) {
			$options[] = array(
				'label' => (string) $method->get_method_title(),
				'value' => (string) $id,
			);
		}

		return $options;
	}


	/**
	 * Sanitize a single workflow node recursively.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $item Workflow node.
	 * @return array<string,mixed>
	 */
	private static function sanitize_workflow_node( $item ) {
		$node = Helpers::strip_objects( $item );
		$type = isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : 'action';

		$node['id'] = isset( $node['id'] ) ? sanitize_text_field( (string) $node['id'] ) : uniqid( 'joinotify_' . $type . '_' );
		$node['type'] = $type;
		$node['data'] = isset( $node['data'] ) && is_array( $node['data'] ) ? self::sanitize_node_data( $node['data'], $type ) : array();
		$node['children'] = self::sanitize_node_children( $node, $type );
		$node['data'] = self::enrich_workflow_node_data( $node['data'] );

		return $node;
	}


	/**
	 * Sanitize a node children payload while preserving condition branches.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $node Workflow node.
	 * @param string $type Node type.
	 * @return array<string,mixed>
	 */
	private static function sanitize_node_children( $node, $type ) {
		if ( ! isset( $node['children'] ) || ! is_array( $node['children'] ) ) {
			return array();
		}

		$action = isset( $node['data']['action'] ) ? sanitize_key( (string) $node['data']['action'] ) : '';

		if ( ( 'condition' === $type || 'condition' === $action ) && self::is_branch_container( $node['children'] ) ) {
			return self::sanitize_condition_children( $node['children'] );
		}

		return self::sanitize_workflow_content( $node['children'] );
	}


	/**
	 * Determine whether a children payload contains condition branches.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $children Children payload.
	 * @return bool
	 */
	private static function is_branch_container( $children ) {
		return is_array( $children ) && ( array_key_exists( 'action_true', $children ) || array_key_exists( 'action_false', $children ) );
	}


	/**
	 * Sanitize branch-based children without flattening their keys.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $children Branch container.
	 * @return array<string,mixed>
	 */
	private static function sanitize_condition_children( $children ) {
		$sanitized = array(
			'action_true' => array(),
			'action_false' => array(),
		);

		foreach ( array( 'action_true', 'action_false' ) as $branch_key ) {
			if ( isset( $children[ $branch_key ] ) && is_array( $children[ $branch_key ] ) ) {
				$sanitized[ $branch_key ] = self::sanitize_workflow_content( $children[ $branch_key ] );
			}
		}

		return $sanitized;
	}


	/**
	 * Enrich sanitized node data with runtime-compatible fields.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $data Node data.
	 * @return array<string,mixed>
	 */
	private static function enrich_workflow_node_data( $data ) {
		if ( ! is_array( $data ) || empty( $data['action'] ) ) {
			return $data;
		}

		$action = isset( $data['action'] ) ? sanitize_key( (string) $data['action'] ) : '';

		if ( 'condition' === $action ) {
			$data['condition_content'] = self::build_condition_content_payload( $data );
		} elseif ( 'time_delay' === $action ) {
			$data['delay_timestamp'] = self::build_delay_timestamp( $data );
		}

		return $data;
	}


	/**
	 * Build the legacy condition payload expected by the runtime.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $data Node data.
	 * @return array<string,mixed>
	 */
	private static function build_condition_content_payload( $data ) {
		$legacy = isset( $data['condition_content'] ) && is_array( $data['condition_content'] ) ? $data['condition_content'] : array();
		$condition = isset( $data['condition'] ) ? sanitize_text_field( (string) $data['condition'] ) : ( isset( $legacy['condition'] ) ? sanitize_text_field( (string) $legacy['condition'] ) : '' );
		$type = isset( $data['condition_type'] ) ? sanitize_text_field( (string) $data['condition_type'] ) : ( isset( $legacy['type'] ) ? sanitize_text_field( (string) $legacy['type'] ) : '' );
		$type_text = isset( $data['type_text'] ) ? sanitize_text_field( (string) $data['type_text'] ) : ( isset( $legacy['type_text'] ) ? sanitize_text_field( (string) $legacy['type_text'] ) : '' );
		$value_text = isset( $data['value_text'] ) ? sanitize_textarea_field( (string) $data['value_text'] ) : ( isset( $legacy['value_text'] ) ? sanitize_textarea_field( (string) $legacy['value_text'] ) : '' );
		$value = isset( $legacy['value'] ) ? $legacy['value'] : '';
		$meta_key = isset( $data['meta_key'] ) ? sanitize_textarea_field( (string) $data['meta_key'] ) : ( isset( $legacy['meta_key'] ) ? sanitize_textarea_field( (string) $legacy['meta_key'] ) : '' );
		$field_id = isset( $data['field_id'] ) ? sanitize_textarea_field( (string) $data['field_id'] ) : ( isset( $legacy['field_id'] ) ? sanitize_textarea_field( (string) $legacy['field_id'] ) : '' );

		$content = array(
			'condition' => $condition,
			'type' => $type,
			'type_text' => $type_text,
			'value' => $value,
			'value_text' => $value_text,
			'meta_key' => $meta_key,
			'field_id' => $field_id,
		);

		// Accept the products list from either the flat key (the builder UI binds
		// to data.products) or the nested condition_content.products (runtime/
		// canonical copy), so the selection survives regardless of which copy a
		// given payload carries.
		$products = isset( $data['products'] ) && is_array( $data['products'] ) ? $data['products'] : ( isset( $legacy['products'] ) && is_array( $legacy['products'] ) ? $legacy['products'] : array() );
		$products = self::sanitize_condition_products( $products );

		if ( ! empty( $products ) ) {
			$content['products'] = $products;
		}

		return $content;
	}


	/**
	 * Normalize a condition products list to the canonical [{ id:int, title:string }] shape.
	 *
	 * Shared by sanitize_node_data() (flat key) and build_condition_content_payload()
	 * (nested key) so both copies are always well-formed and identical. Products
	 * without a positive id are dropped, since the runtime matches purchased
	 * products by id.
	 *
	 * @since 2.0.0
	 * @param mixed $products Raw products list.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitize_condition_products( $products ) {
		if ( ! is_array( $products ) ) {
			return array();
		}

		$clean = array();

		foreach ( $products as $product ) {
			if ( ! is_array( $product ) ) {
				continue;
			}

			$id = isset( $product['id'] ) ? (int) $product['id'] : 0;

			if ( $id <= 0 ) {
				continue;
			}

			$clean[] = array(
				'id' => $id,
				'title' => isset( $product['title'] ) ? sanitize_text_field( (string) $product['title'] ) : '',
			);
		}

		return $clean;
	}


	/**
	 * Build the runtime delay timestamp from delay settings.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $data Node data.
	 * @return int
	 */
	private static function build_delay_timestamp( $data ) {
		// Cache a best-effort value at save time for display/back-compat, but the
		// runtime always recomputes the delay at trigger time via the same helper
		// (Schedule::resolve_delay_seconds) so absolute/scheduled delays never go
		// stale. Keeping a single source of truth here avoids save/runtime drift.
		return (int) Schedule::resolve_delay_seconds( $data );
	}


	/**
	 * Sanitize node-specific data without destroying structured content.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $data Node data.
	 * @param string $type Node type.
	 * @return array<string,mixed>
	 */
	private static function sanitize_node_data( $data, $type ) {
		$clean = array();

		foreach ( $data as $key => $value ) {
			if ( 'canvas_position' === $key && is_array( $value ) ) {
				$x = isset( $value['x'] ) && is_numeric( $value['x'] ) ? (float) $value['x'] : null;
				$y = isset( $value['y'] ) && is_numeric( $value['y'] ) ? (float) $value['y'] : null;

				if ( null !== $x && null !== $y ) {
					$clean[ $key ] = array(
						'x' => $x,
						'y' => $y,
					);
				}
				continue;
			}

			if ( 'connection_from' === $key && is_array( $value ) ) {
				$clean[ $key ] = array(
					'source_id' => isset( $value['source_id'] ) ? sanitize_text_field( (string) $value['source_id'] ) : '',
					'source_handle' => isset( $value['source_handle'] ) ? sanitize_text_field( (string) $value['source_handle'] ) : 'output',
					'target_handle' => isset( $value['target_handle'] ) ? sanitize_text_field( (string) $value['target_handle'] ) : 'input',
				);
				continue;
			}

			// Preserve the canvas connection mode as a clean key or null.
			if ( 'connection_mode' === $key ) {
				$clean[ $key ] = is_null( $value ) ? null : sanitize_key( (string) $value );
				continue;
			}

			// Preserve the boolean flag without coercing it into "1"/"" strings.
			if ( 'connection_break_before' === $key ) {
				$clean[ $key ] = is_null( $value ) ? null : (bool) $value;
				continue;
			}

			// Condition "products" is a structured list the runtime reads by id
			// (products_purchased). Canonicalize it explicitly so the id stays an
			// int and the title is sanitized, instead of letting the generic array
			// recursion stringify the id. This keeps the flat and nested copies in
			// sync and prevents the selection from being silently malformed.
			if ( 'products' === $key && is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_condition_products( $value );
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_node_data( $value, $type );
				continue;
			}

			if ( in_array( $key, array( 'media_url' ), true ) ) {
				$clean[ $key ] = esc_url_raw( (string) $value );
				continue;
			}

			if ( 'snippet_php' === $key ) {
				$clean[ $key ] = is_scalar( $value ) ? trim( (string) $value ) : '';
				continue;
			}

			if ( 'description' === $key ) {
				$clean[ $key ] = wp_kses_post( (string) $value );
				continue;
			}

			if ( in_array( $key, array( 'message', 'caption', 'title', 'sender', 'receiver', 'action', 'trigger', 'context', 'delay_type', 'delay_period', 'date_value', 'time_value', 'media_type', 'condition', 'type_text', 'field_id', 'meta_key', 'value_text', 'coupon_code', 'discount_type', 'ai_prompt', 'ai_system', 'ai_tone', 'ai_length', 'ai_model', 'ai_temperature', 'var_name' ), true ) ) {
				$clean[ $key ] = sanitize_textarea_field( (string) $value );
				continue;
			}

			$clean[ $key ] = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : $value;
		}

		return $clean;
	}


	/**
	 * Recursively sanitize children branches.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $children Node children.
	 * @return array<string,mixed>
	 */
}
