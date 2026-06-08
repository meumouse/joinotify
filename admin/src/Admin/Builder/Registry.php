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
use MeuMouse\Joinotify\Admin\Settings\Registry as Settings_Registry;

defined('ABSPATH') || exit;

/**
 * Builder bootstrap and persistence helpers.
 *
 * @since 1.4.7
 */
class Registry {

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
			'title' => esc_html__( 'Workflow builder', 'joinotify' ),
			'settings' => Settings_Registry::get_settings(),
			'phones' => Settings_Registry::get_phone_state(),
			'workflow' => $workflow_state,
			'workflow_file' => self::build_exported_workflow_file( $workflow_state, $post_id ),
			'start_templates' => Workflow_Manager::get_start_templates(),
			'triggers' => self::get_triggers_catalog(),
			'trigger_contexts' => self::get_trigger_contexts_catalog(),
			'placeholders' => self::get_placeholders_catalog( $workflow_state ),
			'links' => array(
				'back_url' => admin_url( 'admin.php?page=joinotify-workflows' ),
				'dashboard_url' => admin_url( 'admin.php?page=joinotify-workflows' ),
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
				'saved' => esc_html__( 'Workflow saved.', 'joinotify' ),
				'saving' => esc_html__( 'Saving workflow...', 'joinotify' ),
				'create_from_scratch' => esc_html__( 'Create from scratch', 'joinotify' ),
				'create_from_template' => esc_html__( 'Create from template', 'joinotify' ),
				'import_template' => esc_html__( 'Import template', 'joinotify' ),
				'load_workflow' => esc_html__( 'Load workflow', 'joinotify' ),
				'workflow_missing' => esc_html__( 'Workflow not found.', 'joinotify' ),
				'unsaved_changes' => esc_html__( 'You have unsaved changes.', 'joinotify' ),
				'error' => esc_html__( 'Could not complete the operation.', 'joinotify' ),
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
		$templates = Workflow_Templates::get_templates( 'meumouse', 'joinotify', 'dist/templates', 'main', null );
		$catalog = array();

		if ( ! is_array( $templates ) ) {
			return $catalog;
		}

		foreach ( $templates as $filename => $content ) {
			$decoded = json_decode( (string) $content, true );

			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$trigger_data = self::get_template_trigger_data( $decoded['workflow_content'] ?? array() );
			$category = isset( $decoded['post']['category'] ) ? (string) $decoded['post']['category'] : '';
			$integration = self::get_integration_label( $trigger_data['context'] ?? '' );
			$trigger = self::get_trigger_label( $trigger_data['context'] ?? '', $trigger_data['trigger'] ?? '' );

			$catalog[] = array(
				'file' => $filename,
				'title' => isset( $decoded['post']['title'] ) ? sanitize_text_field( $decoded['post']['title'] ) : sanitize_text_field( $filename ),
				'category' => $category,
				'integration' => $integration,
				'trigger' => $trigger,
				'available' => self::is_template_trigger_available( $decoded ),
				'description' => isset( $decoded['post']['description'] ) ? sanitize_text_field( $decoded['post']['description'] ) : '',
				'workflow_content' => isset( $decoded['workflow_content'] ) && is_array( $decoded['workflow_content'] ) ? self::sanitize_workflow_content( $decoded['workflow_content'] ) : array(),
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
		switch ( sanitize_key( (string) $action ) ) {
			case 'time_delay':
				return array(
					array(
						'key' => 'delay_type',
						'label' => esc_html__( 'Delay type', 'joinotify' ),
						'component' => 'select',
						'required' => true,
						'options' => array(
							array( 'label' => esc_html__( 'Period', 'joinotify' ), 'value' => 'period' ),
							array( 'label' => esc_html__( 'Date', 'joinotify' ), 'value' => 'date' ),
							array( 'label' => esc_html__( 'Scheduled', 'joinotify' ), 'value' => 'scheduled' ),
						),
					),
					array(
						'key' => 'delay_value',
						'label' => esc_html__( 'Amount', 'joinotify' ),
						'component' => 'number',
						'componentProps' => array(
							'min' => 1,
						),
					),
					array(
						'key' => 'delay_period',
						'label' => esc_html__( 'Period', 'joinotify' ),
						'component' => 'select',
						'options' => array(
							array( 'label' => esc_html__( 'Seconds', 'joinotify' ), 'value' => 'seconds' ),
							array( 'label' => esc_html__( 'Minutes', 'joinotify' ), 'value' => 'minute' ),
							array( 'label' => esc_html__( 'Hours', 'joinotify' ), 'value' => 'hours' ),
							array( 'label' => esc_html__( 'Days', 'joinotify' ), 'value' => 'day' ),
							array( 'label' => esc_html__( 'Weeks', 'joinotify' ), 'value' => 'week' ),
							array( 'label' => esc_html__( 'Months', 'joinotify' ), 'value' => 'month' ),
							array( 'label' => esc_html__( 'Years', 'joinotify' ), 'value' => 'year' ),
						),
					),
					array(
						'key' => 'date_value',
						'label' => esc_html__( 'Date', 'joinotify' ),
						'component' => 'date',
					),
					array(
						'key' => 'time_value',
						'label' => esc_html__( 'Time', 'joinotify' ),
						'component' => 'time',
					),
				);

			case 'condition':
				return array(
					array(
						'key' => 'condition',
						'label' => esc_html__( 'Condition type', 'joinotify' ),
						'component' => 'select',
						'required' => true,
					),
					array(
						'key' => 'condition_type',
						'label' => esc_html__( 'Operator', 'joinotify' ),
						'component' => 'select',
						'required' => true,
					),
					array(
						'key' => 'field_id',
						'label' => esc_html__( 'Field ID', 'joinotify' ),
						'component' => 'input',
					),
					array(
						'key' => 'meta_key',
						'label' => esc_html__( 'Meta key', 'joinotify' ),
						'component' => 'input',
					),
					array(
						'key' => 'value_text',
						'label' => esc_html__( 'Value', 'joinotify' ),
						'component' => 'textarea',
					),
					array(
						'key' => 'type_text',
						'label' => esc_html__( 'Type label', 'joinotify' ),
						'component' => 'input',
					),
				);

			case 'snippet_php':
				return array(
					array(
						'key' => 'snippet_php',
						'label' => esc_html__( 'PHP code', 'joinotify' ),
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
					'title' => $base_title ?: esc_html__( 'Snippet PHP', 'joinotify' ),
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

		if ( empty( $post['category'] ) ) {
			$trigger_data = self::get_primary_trigger_data( self::sanitize_workflow_content( $content ) );
			if ( ! empty( $trigger_data['context'] ) ) {
				$post['category'] = sanitize_key( $trigger_data['context'] );
			}
		}

		return array(
			'plugin_version' => isset( $file['plugin_version'] ) ? sanitize_text_field( (string) $file['plugin_version'] ) : JOINOTIFY_VERSION,
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
		$templates = Workflow_Templates::get_templates( 'meumouse', 'joinotify', 'dist/templates', 'main', null );

		if ( ! is_array( $templates ) || ! isset( $templates[ $template_file ] ) ) {
			return array(
				'status' => 'error',
				'message' => esc_html__( 'The selected template was not found.', 'joinotify' ),
			);
		}

		$decoded = json_decode( (string) $templates[ $template_file ], true );

		if ( ! is_array( $decoded ) ) {
			return array(
				'status' => 'error',
				'message' => esc_html__( 'Invalid template file.', 'joinotify' ),
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
				'message' => esc_html__( 'Workflow not found.', 'joinotify' ),
			);
		}

		$normalized = self::normalize_workflow_file_payload( $payload );
		$title = isset( $normalized['post']['title'] ) ? sanitize_text_field( (string) $normalized['post']['title'] ) : $post->post_title;
		$status = isset( $normalized['post']['status'] ) ? sanitize_key( (string) $normalized['post']['status'] ) : $post->post_status;
		$status = in_array( $status, array( 'publish', 'draft', 'trash' ), true ) ? $status : $post->post_status;
		$content = isset( $normalized['workflow_content'] ) && is_array( $normalized['workflow_content'] ) ? self::sanitize_workflow_content( $normalized['workflow_content'] ) : array();

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

		return array(
			'status' => 'success',
			'message' => esc_html__( 'Workflow saved.', 'joinotify' ),
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
				'message' => esc_html__( 'Workflow not found.', 'joinotify' ),
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

		return array(
			'status' => 'success',
			'workflow' => self::get_workflow_state( $post_id ),
			'workflow_file' => self::build_exported_workflow_file( self::get_workflow_state( $post_id ), $post_id ),
		);
	}


	/**
	 * Resolve the trigger data from a template payload.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $workflow_content Workflow content.
	 * @return array{context:string,trigger:string}|null
	 */
	private static function get_template_trigger_data( $workflow_content ) {
		if ( ! is_array( $workflow_content ) ) {
			return null;
		}

		foreach ( $workflow_content as $item ) {
			if ( isset( $item['type'] ) && 'trigger' === $item['type'] && isset( $item['data']['context'], $item['data']['trigger'] ) ) {
				return array(
					'context' => sanitize_key( (string) $item['data']['context'] ),
					'trigger' => sanitize_key( (string) $item['data']['trigger'] ),
				);
			}
		}

		return null;
	}


	/**
	 * Check if the template trigger can be used on the current install.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $workflow_data Template payload.
	 * @return bool
	 */
	private static function is_template_trigger_available( $workflow_data ) {
		$trigger_data = self::get_template_trigger_data( $workflow_data['workflow_content'] ?? array() );

		if ( ! $trigger_data ) {
			return false;
		}

		return ! empty( Triggers::get_trigger( $trigger_data['context'], $trigger_data['trigger'] ) );
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

		$products = isset( $legacy['products'] ) && is_array( $legacy['products'] ) ? $legacy['products'] : ( isset( $data['products'] ) && is_array( $data['products'] ) ? $data['products'] : array() );

		if ( ! empty( $products ) ) {
			$content['products'] = array();

			foreach ( $products as $product ) {
				if ( ! is_array( $product ) ) {
					continue;
				}

				$content['products'][] = array(
					'id' => isset( $product['id'] ) ? (int) $product['id'] : 0,
					'title' => isset( $product['title'] ) ? sanitize_text_field( (string) $product['title'] ) : '',
				);
			}
		}

		return $content;
	}


	/**
	 * Build the runtime delay timestamp from delay settings.
	 *
	 * @since 1.4.7
	 * @param array<string,mixed> $data Node data.
	 * @return int
	 */
	private static function build_delay_timestamp( $data ) {
		$delay_type = isset( $data['delay_type'] ) ? sanitize_text_field( (string) $data['delay_type'] ) : 'period';

		if ( 'date' === $delay_type ) {
			$date_value = isset( $data['date_value'] ) ? sanitize_text_field( (string) $data['date_value'] ) : '';
			$time_value = isset( $data['time_value'] ) ? sanitize_text_field( (string) $data['time_value'] ) : '00:00';
			$timestamp = $date_value ? strtotime( $date_value . ' ' . $time_value ) : 0;

			// Return a RELATIVE delay (seconds from now) so Schedule::schedule_actions() fires at the right time.
			return $timestamp ? max( 0, (int) $timestamp - time() ) : 0;
		}

		$delay_value = isset( $data['delay_value'] ) ? (int) $data['delay_value'] : 0;
		$delay_period = isset( $data['delay_period'] ) ? sanitize_text_field( (string) $data['delay_period'] ) : 'seconds';

		if ( 'scheduled' === $delay_type ) {
			$time_value = isset( $data['time_value'] ) ? sanitize_text_field( (string) $data['time_value'] ) : '00:00';

			return (int) Schedule::get_scheduled_delay_timestamp( $delay_value, $delay_period, $time_value );
		}

		return (int) Schedule::get_delay_timestamp( $delay_value, $delay_period );
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

			if ( in_array( $key, array( 'message', 'caption', 'title', 'sender', 'receiver', 'action', 'trigger', 'context', 'delay_type', 'delay_period', 'date_value', 'time_value', 'media_type', 'condition', 'type_text', 'field_id', 'meta_key', 'value_text', 'coupon_code', 'discount_type', 'ai_prompt', 'ai_system', 'ai_tone', 'ai_length', 'ai_model', 'ai_temperature' ), true ) ) {
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
