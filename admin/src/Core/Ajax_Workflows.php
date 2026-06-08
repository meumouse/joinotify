<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Components as Admin_Components;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Api\Workflow_Templates;
use MeuMouse\Joinotify\Builder\Actions;
use MeuMouse\Joinotify\Builder\Components as Builder_Components;
use MeuMouse\Joinotify\Builder\Messages;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Builder\Utils;
use MeuMouse\Joinotify\Builder\Workflow_Manager;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Validations\Conditions;

defined('ABSPATH') || exit;

/**
 * AJAX callbacks related to workflow and builder operations.
 *
 * Handles: create, load, update, delete, export, import, test workflows,
 *          template management, toggle post status, and WooCommerce products.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Ajax_Workflows {

    /**
     * Register AJAX actions.
     *
     * @since 1.4.7
     */
    public function __construct() {
        $actions = array(
            'joinotify_import_workflow_templates'  => 'import_workflow_templates_callback',
            'joinotify_create_workflow'            => 'create_workflow_callback',
            'joinotify_load_workflow_data'         => 'load_workflow_data_callback',
            'joinotify_update_workflow_status'     => 'update_workflow_status_callback',
            'joinotify_add_workflow_action'        => 'add_workflow_action_callback',
            'joinotify_update_workflow_title'      => 'update_workflow_title_callback',
            'joinotify_delete_workflow_action'     => 'delete_workflow_action_callback',
            'joinotify_export_workflow'            => 'export_workflow_callback',
            'joinotify_run_workflow_test'          => 'run_workflow_test_callback',
            'joinotify_toggle_post_status'         => 'toggle_post_status_callback',
            'joinotify_get_workflow_templates'     => 'get_workflow_templates_callback',
            'joinotify_download_workflow_template' => 'download_workflow_template_callback',
        );

        foreach ( $actions as $action => $callback ) {
            add_action( "wp_ajax_{$action}", array( $this, $callback ) );
        }
    }


    /**
     * Get trigger context and key from template workflow content.
     *
     * @since 1.4.6
     * @param array $workflow_content
     * @return array{context:string,trigger:string}|null
     */
    private function get_template_trigger_data( $workflow_content ) {
        if ( ! is_array( $workflow_content ) ) {
            return null;
        }

        foreach ( $workflow_content as $item ) {
            if ( isset( $item['type'] ) && $item['type'] === 'trigger' && isset( $item['data']['context'], $item['data']['trigger'] ) ) {
                return array(
                    'context' => sanitize_key( $item['data']['context'] ),
                    'trigger' => sanitize_key( $item['data']['trigger'] ),
                );
            }
        }

        return null;
    }


    /**
     * Check if template trigger is currently available.
     *
     * @since 1.4.6
     * @param array $workflow_data
     * @return bool
     */
    private function is_template_trigger_available( $workflow_data ) {
        $trigger_data = $this->get_template_trigger_data( $workflow_data['workflow_content'] ?? array() );

        if ( ! $trigger_data ) {
            return false;
        }

        return ! empty( Triggers::get_trigger( $trigger_data['context'], $trigger_data['trigger'] ) );
    }


    /**
     * Get workflow templates on AJAX callback.
     *
     * @since 1.2.0
     * @return void
     */
    public function get_workflow_templates_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_get_workflow_templates' ) {
            $templates = Workflow_Templates::get_templates( 'meumouse', 'joinotify', 'dist/templates', 'main', null );

            if ( ! empty( $templates ) ) {
                $template_html = '';

                foreach ( $templates as $filename => $content ) {
                    $decoded_content = json_decode( $content, true );

                    if ( $decoded_content !== null ) {
                        $title             = isset( $decoded_content['post']['title'] ) ? esc_html( $decoded_content['post']['title'] ) : esc_html( $filename );
                        $category          = isset( $decoded_content['post']['category'] ) ? esc_attr( $decoded_content['post']['category'] ) : '';
                        $trigger_data      = $this->get_template_trigger_data( $decoded_content['workflow_content'] ?? array() );
                        $integration_label = Utils::get_template_categories()[ $trigger_data['context'] ?? '' ] ?? ucfirst( (string) ( $trigger_data['context'] ?? '' ) );
                        $trigger_label     = $trigger_data ? ( Triggers::get_trigger( $trigger_data['context'], $trigger_data['trigger'] )['title'] ?? $trigger_data['trigger'] ) : esc_html__( 'Not identified', 'joinotify' );
                        $is_available      = $this->is_template_trigger_available( $decoded_content );
                        $button_classes    = $is_available ? 'btn-outline-primary' : 'btn-outline-secondary';
                        $button_disabled   = $is_available ? '' : 'disabled';
                        $button_title      = $is_available ? '' : ' title="' . esc_attr__( 'Integration or trigger unavailable for this template.', 'joinotify' ) . '"';

                        $template_html .= '<div class="template-item" data-category="' . $category . '">';
                        $template_html .= '<div class="template-item-header mb-3">';
                        $template_html .= '<h4 class="title">' . $title . '</h4>';
                        $template_html .= '<span class="d-block text-muted fs-xs mb-1"><strong>' . esc_html__( 'Integration:', 'joinotify' ) . '</strong> ' . esc_html( $integration_label ) . '</span>';
                        $template_html .= '<span class="d-block text-muted fs-xs"><strong>' . esc_html__( 'Trigger:', 'joinotify' ) . '</strong> ' . esc_html( $trigger_label ) . '</span>';
                        $template_html .= '</div>';
                        $template_html .= '<button class="btn btn-sm ' . esc_attr( $button_classes ) . ' d-flex align-items-center justify-content-center download-template" data-file="' . esc_attr( $filename ) . '" ' . $button_disabled . $button_title . '>';
                        $template_html .= '<svg class="icon icon-primary me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m12 18 4-5h-3V2h-2v11H8z"></path><path d="M19 9h-4v2h4v9H5v-9h4V9H5c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2v-9c0-1.103-.897-2-2-2z"></path></svg>';
                        $template_html .= esc_html__( 'Import workflow', 'joinotify' );
                        $template_html .= '</button>';
                        $template_html .= '</div>';
                    }
                }

                wp_send_json( array(
                    'status'        => 'success',
                    'template_html' => $template_html,
                ) );
            } else {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => __( 'No workflow templates available. Please check back later.', 'joinotify' ),
                ) );
            }
        }
    }


    /**
     * Import workflow templates on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function import_workflow_templates_callback() {
        check_ajax_referer( 'joinotify_import_workflow_nonce', 'security' );

        if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Upload error', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'There was a problem uploading the file.', 'joinotify' ),
            ) );
        }

        $file     = $_FILES['file'];
        $file_ext = pathinfo( $file['name'], PATHINFO_EXTENSION );

        if ( strtolower( $file_ext ) !== 'json' ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Invalid file type', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'The file must be a JSON file.', 'joinotify' ),
            ) );
        }

        $file_contents = file_get_contents( $file['tmp_name'] );
        $workflow_data = json_decode( $file_contents, true );

        if ( ! $workflow_data || ! isset( $workflow_data['post'] ) || ! isset( $workflow_data['workflow_content'] ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Invalid file', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'The JSON file does not have a valid format.', 'joinotify' ),
            ) );
        }

        if ( ! isset( $workflow_data['post']['type'] ) || $workflow_data['post']['type'] !== 'joinotify-workflow' ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'An error occurred', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'The uploaded file is not valid.', 'joinotify' ),
            ) );
        }

        $post_data = array(
            'type'         => 'joinotify-workflow',
            'post_title'   => sanitize_text_field( $workflow_data['post']['title'] ),
            'post_status'  => 'draft',
            'post_type'    => 'joinotify-workflow',
            'post_content' => '',
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Error creating workflow', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'An error occurred while creating the workflow in the database.', 'joinotify' ),
            ) );
        }

        if ( ! empty( $workflow_data['workflow_content'] ) && is_array( $workflow_data['workflow_content'] ) ) {
            Actions::fill_sender_recursive( $workflow_data['workflow_content'] );
        }

        Helpers::update_workflow_content_meta( $post_id, $workflow_data['workflow_content'] );

        $redirect_url = admin_url( "admin.php?page=joinotify-workflows-builder&id={$post_id}" );

        wp_send_json( array(
            'status'             => 'success',
            'redirect'           => $redirect_url,
            'toast_header_title' => esc_html__( 'Workflow imported', 'joinotify' ),
            'toast_body_title'   => esc_html__( 'The workflow was imported successfully!', 'joinotify' ),
            'dropfile_message'   => esc_html__( 'File uploaded successfully!', 'joinotify' ),
        ) );
    }


    /**
     * Create workflow on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function create_workflow_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_create_workflow' ) {
            $context = isset( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : '';
            $trigger = isset( $_POST['trigger'] ) ? sanitize_text_field( $_POST['trigger'] ) : '';
            $title   = isset( $_POST['title'] )   ? sanitize_text_field( $_POST['title'] )   : '';
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

            $new_trigger = Workflow_Manager::create_workflow_structure( 'trigger', array(
                'context' => $context,
                'trigger' => $trigger,
                'title'   => $title,
            ) );

            if ( $post_id > 0 && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $update_result = wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );

                if ( $update_result ) {
                    $workflow_content = Helpers::get_workflow_content_meta( $post_id );
                    $workflow_content = array_filter( $workflow_content, static function ( $item ) {
                        return $item['type'] !== 'trigger';
                    } );
                    array_unshift( $workflow_content, $new_trigger );
                    Helpers::update_workflow_content_meta( $post_id, $workflow_content );

                    $response = array(
                        'status'             => 'success',
                        'post_id'            => $post_id,
                        'proceed'            => true,
                        'workflow_content'   => Workflow_Manager::get_workflow_content( $post_id ),
                        'workflow_status'    => Builder_Components::check_workflow_status( $post_id ),
                        'toast_header_title' => __( 'Workflow updated successfully', 'joinotify' ),
                        'toast_body_title'   => __( 'Trigger and workflow updated successfully!', 'joinotify' ),
                    );

                    $response = $this->append_trigger_extras( $response, $post_id, $trigger, $context );
                } else {
                    $response = array(
                        'status'             => 'error',
                        'proceed'            => false,
                        'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                        'toast_body_title'   => __( 'An error occurred while updating the workflow and trigger.', 'joinotify' ),
                    );
                }
            } else {
                $post_data = array(
                    'post_title'  => $title,
                    'post_type'   => 'joinotify-workflow',
                    'post_status' => 'draft',
                    'meta_input'  => array(
                        'joinotify_workflow_content' => Helpers::encode_emoji_deep( array( $new_trigger ) ),
                    ),
                );

                $new_post_id = wp_insert_post( $post_data );

                if ( $new_post_id !== 0 ) {
                    $response = array(
                        'status'             => 'success',
                        'post_id'            => $new_post_id,
                        'proceed'            => true,
                        'workflow_content'   => Workflow_Manager::get_workflow_content( $new_post_id ),
                        'workflow_status'    => Builder_Components::check_workflow_status( $new_post_id ),
                        'toast_header_title' => __( 'Workflow created successfully', 'joinotify' ),
                        'toast_body_title'   => __( 'Trigger and workflow created successfully!', 'joinotify' ),
                        'placeholders_list'  => Builder_Components::render_placeholders_list( $new_post_id ),
                    );

                    $response = $this->append_trigger_extras( $response, $new_post_id, $trigger, $context );
                } else {
                    $response = array(
                        'status'             => 'error',
                        'proceed'            => false,
                        'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                        'toast_body_title'   => __( 'An error occurred while creating the workflow and trigger.', 'joinotify' ),
                    );
                }
            }

            wp_send_json( $response );
        }
    }


    /**
     * Load workflow data on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function load_workflow_data_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_load_workflow_data' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;

            if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $response = array(
                    'status'                  => 'success',
                    'workflow_title'          => get_post( $post_id )->post_title,
                    'workflow_status'         => get_post_status( $post_id ),
                    'display_workflow_status' => Builder_Components::check_workflow_status( $post_id ),
                    'workflow_content'        => Workflow_Manager::get_workflow_content( $post_id ),
                    'has_trigger'             => Utils::check_workflow_content( $post_id, 'trigger' ),
                    'has_action'              => Utils::check_workflow_content( $post_id, 'action' ),
                );

                $workflow_data = Helpers::get_workflow_content_meta( $post_id );
                $trigger_id    = '';

                foreach ( $workflow_data as $workflow ) {
                    if ( isset( $workflow['type'] ) && $workflow['type'] === 'trigger' ) {
                        $trigger_id = $workflow['id'];

                        if ( isset( $workflow['data']['context'], $workflow['data']['trigger'] ) ) {
                            $action_conditions             = Conditions::get_conditions_by_trigger( $workflow['data']['trigger'] );
                            $response['condition_selectors'] = Builder_Components::render_condition_selectors( $action_conditions );
                            $response['placeholders_list']   = Builder_Components::render_placeholders_list( $post_id );
                            $response['sidebar_actions']     = Builder_Components::get_filtered_actions( $workflow['data']['context'] );
                            $response['fetch_groups_trigger'] = Builder_Components::fetch_all_groups_modal_trigger();
                        }
                    }
                }

                if ( Triggers::trigger_requires_settings( $post_id ) ) {
                    $response['active_settings_modal'] = '#edit_trigger_' . $trigger_id;
                }

                if ( defined( 'JOINOTIFY_DEBUG_MODE' ) && JOINOTIFY_DEBUG_MODE ) {
                    $response['debug'] = array(
                        'post_object' => get_post( $post_id ),
                        'post_meta'   => $workflow_data,
                    );
                }
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => __( 'An error occurred while retrieving the workflow data.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Update workflow status on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function update_workflow_status_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_update_workflow_status' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $updated_post = array(
                    'ID'          => $post_id,
                    'post_status' => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'draft',
                );

                $result = wp_update_post( $updated_post );

                if ( $result ) {
                    $workflow_status = get_post_status( $post_id ) === 'publish'
                        ? esc_html__( 'Active', 'joinotify' )
                        : esc_html__( 'Inactive', 'joinotify' );

                    $response = array(
                        'status'                  => 'success',
                        'workflow_status'         => $workflow_status,
                        'display_workflow_status' => Builder_Components::check_workflow_status( $post_id ),
                        'toast_header_title'      => __( 'Status updated successfully', 'joinotify' ),
                        'toast_body_title'        => __( 'The workflow status was updated.', 'joinotify' ),
                    );
                } else {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                        'toast_body_title'   => __( 'An error occurred while updating the workflow status.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Workflow not found', 'joinotify' ),
                    'toast_body_title'   => __( 'The workflow was not found or the post type is incorrect.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Add action to workflow on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_workflow_action_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_add_workflow_action' ) {
            $post_id          = isset( $_POST['post_id'] )         ? intval( $_POST['post_id'] ) : null;
            $current_action_id = isset( $_POST['action_id'] )      ? sanitize_text_field( $_POST['action_id'] ) : '';
            $condition_action  = isset( $_POST['condition_action'] ) ? sanitize_text_field( $_POST['condition_action'] ) : '';
            $workflow_action   = isset( $_POST['workflow_action'] )  ? json_decode( stripslashes( $_POST['workflow_action'] ), true ) : array();
            $next_action_id    = isset( $_POST['next_action_id'] )   ? sanitize_text_field( $_POST['next_action_id'] ) : '';

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $workflow_content = Helpers::get_workflow_content_meta( $post_id );

                if ( empty( $workflow_content ) ) {
                    $workflow_content = array();
                }

                $new_action = Workflow_Manager::create_workflow_structure( 'action', array(
                    'title'       => $workflow_action['data']['title']       ?? '',
                    'description' => $workflow_action['data']['description'] ?? '',
                    'action'      => $workflow_action['data']['action']      ?? '',
                    'message'     => $workflow_action['data']['message']     ?? '',
                ) );

                $build_description = Messages::build_workflow_action_description( $workflow_action );
                $new_action        = $this->populate_action_data( $new_action, $workflow_action, $build_description );

                $updated = false;

                foreach ( $workflow_content as &$existing_action ) {
                    if ( isset( $existing_action['id'] ) && $existing_action['id'] === $current_action_id ) {
                        if ( ! isset( $existing_action['children'] ) ) {
                            $existing_action['children'] = array();
                        }

                        if ( isset( $existing_action['data']['action'] ) && $existing_action['data']['action'] === 'condition' ) {
                            if ( ! isset( $existing_action['children']['action_true'] ) ) {
                                $existing_action['children']['action_true'] = array();
                            }
                            if ( ! isset( $existing_action['children']['action_false'] ) ) {
                                $existing_action['children']['action_false'] = array();
                            }

                            if ( $condition_action === 'true' ) {
                                $existing_action['children']['action_true'][] = $new_action;
                            } elseif ( $condition_action === 'false' ) {
                                $existing_action['children']['action_false'][] = $new_action;
                            }
                        } else {
                            $existing_action['children'][] = $new_action;
                        }

                        $updated = true;
                        break;
                    }
                }

                foreach ( $workflow_content as $index => &$existing_action ) {
                    if ( isset( $existing_action['id'] ) && $existing_action['id'] === $next_action_id ) {
                        array_splice( $workflow_content, $index, 0, array( $new_action ) );
                        $updated = true;
                        break;
                    }
                }

                if ( ! $updated ) {
                    $workflow_content[] = $new_action;
                }

                $workflow_content = array_values( array_filter( $workflow_content, static function ( $action ) {
                    return isset( $action['data'] ) && $action['data'] !== null;
                } ) );

                $result = Helpers::update_workflow_content_meta( $post_id, $workflow_content );

                if ( $result ) {
                    $response = array(
                        'status'             => 'success',
                        'has_action'         => Utils::check_workflow_content( $post_id, 'action' ),
                        'workflow_content'   => Workflow_Manager::get_workflow_content( $post_id ),
                        'toast_header_title' => __( 'Action added successfully', 'joinotify' ),
                        'toast_body_title'   => __( 'Action added to the workflow successfully!', 'joinotify' ),
                    );
                } else {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                        'toast_body_title'   => __( 'An error occurred while adding the action to the workflow.', 'joinotify' ),
                    );
                }

                wp_send_json( $response );
            }
        }
    }


    /**
     * Update workflow title on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function update_workflow_title_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_update_workflow_title' ) {
            $post_id        = isset( $_POST['post_id'] )        ? intval( $_POST['post_id'] ) : null;
            $workflow_title = isset( $_POST['workflow_title'] ) ? sanitize_text_field( $_POST['workflow_title'] ) : '';

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $result = wp_update_post( array( 'ID' => $post_id, 'post_title' => $workflow_title ) );

                if ( $result ) {
                    $response = array(
                        'status'             => 'success',
                        'workflow_title'     => $workflow_title,
                        'toast_header_title' => __( 'Title updated successfully', 'joinotify' ),
                        'toast_body_title'   => __( 'The workflow title was updated.', 'joinotify' ),
                    );
                } else {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                        'toast_body_title'   => __( 'An error occurred while updating the workflow title.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Workflow not found', 'joinotify' ),
                    'toast_body_title'   => __( 'The workflow was not found or the post type is incorrect.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Delete action from workflow on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function delete_workflow_action_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_delete_workflow_action' ) {
            $post_id   = isset( $_POST['post_id'] )   ? intval( $_POST['post_id'] ) : null;
            $action_id = isset( $_POST['action_id'] ) ? sanitize_text_field( $_POST['action_id'] ) : null;

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' && $action_id ) {
                $workflow_content = Helpers::get_workflow_content_meta( $post_id );

                if ( ! empty( $workflow_content ) ) {
                    $workflow_content = array_values( Actions::delete_item_recursive( $workflow_content, $action_id ) );
                    $result           = Helpers::update_workflow_content_meta( $post_id, $workflow_content );

                    if ( $result ) {
                        $response = array(
                            'status'             => 'success',
                            'workflow_content'   => Workflow_Manager::get_workflow_content( $post_id ),
                            'has_action'         => Utils::check_workflow_content( $post_id, 'action' ),
                            'toast_header_title' => __( 'Action deleted successfully', 'joinotify' ),
                            'toast_body_title'   => __( 'The action was removed from the workflow successfully!', 'joinotify' ),
                        );
                    } else {
                        $response = array(
                            'status'             => 'error',
                            'toast_header_title' => __( 'Error updating workflow', 'joinotify' ),
                            'toast_body_title'   => __( 'Could not update the workflow after deleting the action.', 'joinotify' ),
                        );
                    }
                } else {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => __( 'Empty workflow', 'joinotify' ),
                        'toast_body_title'   => __( 'There are no actions to remove from the workflow.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Invalid data', 'joinotify' ),
                    'toast_body_title'   => __( 'The provided data is invalid or the workflow was not found.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Export workflow on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function export_workflow_callback() {
        check_ajax_referer( 'joinotify_export_workflow_nonce', 'security' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $post          = get_post( $post_id );
            $workflow_data = Helpers::get_workflow_content_meta( $post_id );
            $category      = '';

            if ( is_array( $workflow_data ) ) {
                foreach ( $workflow_data as $item ) {
                    if ( isset( $item['type'] ) && $item['type'] === 'trigger' && isset( $item['data']['context'] ) ) {
                        $category = $item['data']['context'];
                        break;
                    }
                }
            }

            $response = array(
                'status'             => 'success',
                'export_data'        => array(
                    'plugin_version'   => JOINOTIFY_VERSION,
                    'post'             => array(
                        'type'     => 'joinotify-workflow',
                        'title'    => $post->post_title,
                        'date'     => $post->post_date,
                        'status'   => $post->post_status,
                        'modified' => $post->post_modified,
                        'category' => $category,
                    ),
                    'workflow_content' => $workflow_data,
                ),
                'toast_header_title' => __( 'Download completed successfully', 'joinotify' ),
                'toast_body_title'   => __( 'Workflow exported successfully!', 'joinotify' ),
            );
        } else {
            $response = array(
                'status'             => 'error',
                'toast_header_title' => __( 'Invalid data', 'joinotify' ),
                'toast_body_title'   => __( 'The provided data is invalid or the workflow was not found.', 'joinotify' ),
            );
        }

        wp_send_json( $response );
    }


    /**
     * Run workflow test on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function run_workflow_test_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_run_workflow_test' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $workflow_content = Helpers::get_workflow_content_meta( $post_id );
                $receiver         = \MeuMouse\Joinotify\Admin\Admin::get_setting( 'test_number_phone' );

                if ( empty( $receiver ) ) {
                    wp_send_json( array(
                        'status'             => 'error',
                        'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                        'toast_body_title'   => __( 'No test phone numbers are registered.', 'joinotify' ),
                    ) );

                    return;
                }

                if ( ! empty( $workflow_content ) && is_array( $workflow_content ) ) {
                    $all_actions = Actions::extract_all_actions( $workflow_content );

                    foreach ( $all_actions as $item ) {
                        if ( isset( $item['type'] ) && $item['type'] === 'action' ) {
                            if ( isset( $item['data']['action'] ) && $item['data']['action'] === 'send_whatsapp_message_text' ) {
                                $payload = array(
                                    'integration' => $workflow_content[0]['data']['context'],
                                    'trigger'     => $workflow_content[0]['data']['trigger'],
                                );

                                $sender  = $item['data']['sender'];
                                $message = Placeholders::replace_placeholders( $item['data']['message'], $payload, 'sandbox' );

                                if ( 201 !== Controller::send_message_text( $sender, $receiver, $message ) ) {
                                    Controller::get_connection_state( $sender );

                                    wp_send_json( array(
                                        'status'             => 'error',
                                        'toast_header_title' => __( 'Ops! An error occurred', 'joinotify' ),
                                        'toast_body_title'   => __( 'Could not send one or more test messages.', 'joinotify' ),
                                    ) );
                                }
                            }
                        }
                    }

                    wp_send_json( array(
                        'status'             => 'success',
                        'toast_header_title' => __( 'Messages sent', 'joinotify' ),
                        'toast_body_title'   => __( 'All test messages were sent successfully!', 'joinotify' ),
                    ) );
                }

                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Workflow execution error', 'joinotify' ),
                    'toast_body_title'   => __( 'Could not process the workflow content.', 'joinotify' ),
                ) );
            }
        }
    }


    /**
     * Toggle post status on workflows table on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function toggle_post_status_callback() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Permission denied', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'You do not have permission to edit posts.', 'joinotify' ),
            ) );
        }

        if ( isset( $_POST['post_id'] ) && isset( $_POST['status'] ) ) {
            $post_id = intval( $_POST['post_id'] );
            $status  = $_POST['status'] === 'publish' ? 'publish' : 'draft';
            $updated = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true );

            if ( is_wp_error( $updated ) ) {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => esc_html__( 'Ops! An error occurred', 'joinotify' ),
                    'toast_body_title'   => esc_html__( 'An error occurred while updating the post status', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => esc_html__( 'Workflow status updated', 'joinotify' ),
                    'toast_body_title'   => esc_html__( 'The workflow status was updated successfully!', 'joinotify' ),
                );
            }
        } else {
            $response = array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Ops! An error occurred', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'Incomplete data.', 'joinotify' ),
            );
        }

        wp_send_json( $response );
    }


    /**
     * Download workflow template from remote repository on AJAX callback.
     *
     * @since 1.2.0
     * @return void
     */
    public function download_workflow_template_callback() {
        check_ajax_referer( 'joinotify_import_workflow_nonce', 'security' );

        if ( ! isset( $_POST['file'] ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Import error', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'No file was specified.', 'joinotify' ),
            ) );
        }

        $filename  = sanitize_text_field( $_POST['file'] );
        $templates = Workflow_Templates::get_templates( 'meumouse', 'joinotify', 'dist/templates', 'main', null );

        if ( ! isset( $templates[ $filename ] ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Import error', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'The selected template was not found.', 'joinotify' ),
            ) );
        }

        $workflow_data = json_decode( $templates[ $filename ], true );

        if ( ! $workflow_data || ! isset( $workflow_data['post'], $workflow_data['workflow_content'] ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Invalid file', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'The template JSON is not valid.', 'joinotify' ),
            ) );
        }

        if ( ! $this->is_template_trigger_available( $workflow_data ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Integration unavailable', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'The integration or trigger for this template is currently unavailable.', 'joinotify' ),
            ) );
        }

        $post_id = wp_insert_post( array(
            'post_title'   => sanitize_text_field( $workflow_data['post']['title'] ),
            'post_status'  => 'draft',
            'post_type'    => 'joinotify-workflow',
            'post_content' => '',
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => esc_html__( 'Error creating workflow', 'joinotify' ),
                'toast_body_title'   => esc_html__( 'An error occurred while creating the workflow.', 'joinotify' ),
            ) );
        }

        if ( ! empty( $workflow_data['workflow_content'] ) && is_array( $workflow_data['workflow_content'] ) ) {
            Actions::fill_sender_recursive( $workflow_data['workflow_content'] );
        }

        Helpers::update_workflow_content_meta( $post_id, $workflow_data['workflow_content'] );

        wp_send_json( array(
            'status'             => 'success',
            'redirect'           => admin_url( "admin.php?page=joinotify-workflows-builder&id={$post_id}" ),
            'toast_header_title' => esc_html__( 'Workflow imported', 'joinotify' ),
            'toast_body_title'   => esc_html__( 'The workflow was imported successfully!', 'joinotify' ),
        ) );
    }


    /**
     * Append trigger-specific extras (conditions, sidebar actions, etc.) to a response array.
     *
     * @since 1.4.7
     * @param array  $response
     * @param int    $post_id
     * @param string $trigger
     * @param string $context
     * @return array
     */
    private function append_trigger_extras( array $response, $post_id, $trigger, $context ) {
        $workflow_data = Helpers::get_workflow_content_meta( $post_id );
        $trigger_id    = '';

        if ( is_array( $workflow_data ) ) {
            foreach ( $workflow_data as $item ) {
                if ( isset( $item['type'] ) && $item['type'] === 'trigger' ) {
                    $trigger_id = $item['id'];
                    break;
                }
            }
        }

        if ( Triggers::trigger_requires_settings( $post_id ) ) {
            $response['active_settings_modal'] = '#edit_trigger_' . $trigger_id;
        }

        $action_conditions             = Conditions::get_conditions_by_trigger( $trigger );
        $response['condition_selectors'] = Builder_Components::render_condition_selectors( $action_conditions );
        $response['sidebar_actions']     = Builder_Components::get_filtered_actions( $context );
        $response['fetch_groups_trigger'] = Builder_Components::fetch_all_groups_modal_trigger();

        return $response;
    }


    /**
     * Populate action-specific data fields from the incoming workflow_action payload.
     *
     * @since 1.4.7
     * @param array $new_action    Base action structure.
     * @param array $workflow_action Incoming action payload from POST.
     * @param string $build_description Pre-built description string.
     * @return array
     */
    private function populate_action_data( array $new_action, array $workflow_action, $build_description ) {
        $action_type = $workflow_action['data']['action'] ?? '';

        if ( ! $action_type ) {
            return $new_action;
        }

        $new_action['data']['description'] = $build_description;

        switch ( $action_type ) {
            case 'condition':
                $cc = $workflow_action['data']['condition_content'] ?? array();
                $new_action['data']['condition_content'] = array(
                    'condition'  => $cc['condition']  ?? '',
                    'type'       => $cc['type']       ?? '',
                    'type_text'  => $cc['type_text']  ?? '',
                    'value'      => $cc['value']      ?? '',
                    'value_text' => $cc['value_text'] ?? '',
                );

                $condition_type = $cc['condition'] ?? '';

                if ( $condition_type === 'user_meta' ) {
                    $new_action['data']['condition_content']['meta_key'] = $cc['meta_key'] ?? '';
                } elseif ( $condition_type === 'products_purchased' ) {
                    $products = array();

                    foreach ( $cc['products'] ?? array() as $product ) {
                        $products[] = array(
                            'id'    => intval( $product['id'] ?? 0 ),
                            'title' => sanitize_text_field( $product['title'] ?? '' ),
                        );
                    }

                    $new_action['data']['condition_content']['products'] = $products;
                } elseif ( $condition_type === 'field_value' ) {
                    $new_action['data']['condition_content']['field_id'] = $cc['field_id'] ?? '';
                }

                break;

            case 'time_delay':
                $d = $workflow_action['data'];
                $new_action['data']['delay_type']   = $d['delay_type']   ?? '';
                $new_action['data']['delay_value']  = $d['delay_value']  ?? '';
                $new_action['data']['delay_period'] = $d['delay_period'] ?? '';
                $new_action['data']['date_value']   = $d['date_value']   ?? '';
                $new_action['data']['time_value']   = $d['time_value']   ?? '';

                $delay_type = $d['delay_type'] ?? 'period';

                if ( $delay_type === 'period' ) {
                    $new_action['data']['delay_timestamp'] = Schedule::get_delay_timestamp(
                        (int) ( $d['delay_value'] ?? 0 ),
                        $d['delay_period'] ?? 'seconds'
                    );
                } elseif ( $delay_type === 'scheduled' ) {
                    $new_action['data']['delay_timestamp'] = Schedule::get_scheduled_delay_timestamp(
                        (int) ( $d['delay_value'] ?? 0 ),
                        $d['delay_period'] ?? 'day',
                        $d['time_value'] ?? '00:00'
                    );
                } elseif ( $delay_type === 'date' && ! empty( $d['date_value'] ) ) {
                    $timestamp = strtotime( $d['date_value'] . ' ' . ( $d['time_value'] ?? '00:00' ) );

                    if ( $timestamp ) {
                        // Store a RELATIVE delay (seconds from now) so Schedule::schedule_actions() fires at the right time.
                        $new_action['data']['delay_timestamp'] = max( 0, (int) $timestamp - time() );
                    }
                }

                break;

            case 'send_whatsapp_message_text':
                $new_action['data']['message']  = $workflow_action['data']['message']  ?? '';
                $new_action['data']['sender']   = $workflow_action['data']['sender']   ?? '';
                $new_action['data']['receiver'] = $workflow_action['data']['receiver'] ?? '';
                break;

            case 'send_whatsapp_message_media':
                $new_action['data']['sender']     = $workflow_action['data']['sender']     ?? '';
                $new_action['data']['receiver']   = $workflow_action['data']['receiver']   ?? '';
                $new_action['data']['media_url']  = $workflow_action['data']['media_url']  ?? '';
                $new_action['data']['media_type'] = $workflow_action['data']['media_type'] ?? '';
                $new_action['data']['caption']    = $workflow_action['data']['caption']    ?? '';
                break;

            case 'snippet_php':
                $new_action['data']['snippet_php'] = $workflow_action['data']['snippet_php'] ?? '';
                break;

            case 'create_coupon':
                $s = $workflow_action['data']['settings'] ?? array();
                $new_action['data']['settings'] = array(
                    'generate_coupon'     => $s['generate_coupon']     ?? '',
                    'coupon_code'         => $s['coupon_code']         ?? '',
                    'coupon_description'  => $s['coupon_description']  ?? '',
                    'discount_type'       => $s['discount_type']       ?? '',
                    'coupon_amount'       => $s['coupon_amount']       ?? '',
                    'free_shipping'       => $s['free_shipping']       ?? '',
                    'coupon_expiry'       => $s['coupon_expiry']       ?? '',
                    'expiry_data'         => array(
                        'type'         => $s['expiry_data']['type']         ?? '',
                        'delay_value'  => $s['expiry_data']['delay_value']  ?? '',
                        'delay_period' => $s['expiry_data']['delay_period'] ?? '',
                        'date_value'   => $s['expiry_data']['date_value']   ?? '',
                        'time_value'   => $s['expiry_data']['time_value']   ?? '',
                    ),
                    'message'             => array(
                        'message'  => $s['message']['message']  ?? '',
                        'sender'   => $s['message']['sender']   ?? '',
                        'receiver' => $s['message']['receiver'] ?? '',
                    ),
                );

                break;
        }

        return $new_action;
    }
}
