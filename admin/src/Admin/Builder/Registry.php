<?php

namespace MeuMouse\Joinotify\Admin\Builder;

use MeuMouse\Joinotify\Admin\Settings\Repository;
use MeuMouse\Joinotify\Api\Workflow_Templates;
use MeuMouse\Joinotify\Builder\Actions;
use MeuMouse\Joinotify\Builder\Messages;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Builder\Utils;
use MeuMouse\Joinotify\Builder\Workflow_Manager;
use MeuMouse\Joinotify\Core\Helpers;

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
			'workflow' => $workflow_state,
			'workflow_file' => self::build_exported_workflow_file( $workflow_state, $post_id ),
			'start_templates' => Workflow_Manager::get_start_templates(),
			'templates' => self::get_templates_catalog(),
			'actions' => self::get_actions_catalog(),
			'triggers' => self::get_triggers_catalog(),
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
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_actions_catalog() {
		$actions = Actions::get_all_actions();
		$catalog = array();

		foreach ( $actions as $action ) {
			$catalog[] = array(
				'action' => $action['action'] ?? '',
				'title' => $action['title'] ?? '',
				'description' => $action['description'] ?? '',
				'icon' => $action['icon'] ?? '',
				'priority' => isset( $action['priority'] ) ? (int) $action['priority'] : 0,
				'has_settings' => ! empty( $action['has_settings'] ),
				'is_expansible' => ! empty( $action['is_expansible'] ),
				'context' => isset( $action['context'] ) && is_array( $action['context'] ) ? array_values( $action['context'] ) : array(),
			);
		}

		return $catalog;
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
					'icon' => $trigger['icon'] ?? '',
					'require_settings' => ! empty( $trigger['require_settings'] ),
					'category' => $trigger['category'] ?? '',
					'settings' => isset( $trigger['settings'] ) && is_array( $trigger['settings'] ) ? $trigger['settings'] : array(),
				);
			}
		}

		return $catalog;
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
		$title = sanitize_text_field( $title );

		if ( empty( $title ) ) {
			$title = sprintf(
				__( 'My automation #%s', 'joinotify' ),
				function_exists( 'random_int' ) ? random_int( 1000, 999999 ) : mt_rand( 1000, 999999 )
			);
		}

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

		Helpers::update_workflow_content_meta( $post_id, array() );

		return array(
			'status' => 'success',
			'workflow' => self::get_workflow_state( $post_id ),
			'workflow_file' => self::build_exported_workflow_file( self::get_workflow_state( $post_id ), $post_id ),
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
		$node['children'] = isset( $node['children'] ) && is_array( $node['children'] ) ? self::sanitize_workflow_content( $node['children'] ) : array();

		return $node;
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

			if ( in_array( $key, array( 'message', 'caption', 'title', 'sender', 'receiver', 'action', 'trigger', 'context', 'delay_type', 'delay_period', 'date_value', 'time_value', 'media_type', 'condition', 'type_text', 'field_id', 'meta_key', 'value_text', 'coupon_code', 'discount_type' ), true ) ) {
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
