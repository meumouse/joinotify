<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Admin;
use MeuMouse\Joinotify\Builder\Core;
use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\API\Workflow_Templates;
use MeuMouse\Joinotify\API\License;
use MeuMouse\Joinotify\Validations\Otp_Validation;
use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Core\Components;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Cron\Schedule;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Handle AJAX callbacks
 *
 * @since 1.0.0
 * @version 1.0.5
 * @package MeuMouse.com
 */
class Ajax {

    public $response_obj;
    public $license_message;

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        // save admin options
		add_action( 'wp_ajax_joinotify_save_options', array( $this, 'admin_save_options_callback' ) );

        // active license process
		add_action( 'wp_ajax_joinotify_active_license', array( $this, 'active_license_callback' ) );

        // alternative active license process
		add_action( 'wp_ajax_joinotify_alternative_activation_license', array( $this, 'alternative_active_license_callback' ) );

        // deactive license process
        add_action( 'wp_ajax_joinotify_deactive_license', array( $this, 'deactive_license_callback' ) );

        // get templates count
    //    add_action( 'wp_ajax_joinotify_get_templates_count', array( $this, 'get_templates_count_callback' ) );

        // get workflow templates
    //    add_action( 'wp_ajax_joinotify_get_workflow_templates', array( $this, 'get_workflow_templates_callback' ) );

        // add init trigger
        add_action( 'wp_ajax_joinotify_create_workflow', array( $this, 'create_workflow_callback' ) );

        // load workflow data
        add_action( 'wp_ajax_joinotify_load_workflow_data', array( $this, 'load_workflow_data_callback' ) );

        // update workflow status
        add_action( 'wp_ajax_joinotify_update_workflow_status', array( $this, 'update_workflow_status_callback' ) );

        // add workflow action
        add_action( 'wp_ajax_joinotify_add_workflow_action', array( $this, 'add_workflow_action_callback' ) );

        // add workflow action
        add_action( 'wp_ajax_joinotify_update_workflow_title', array( $this, 'update_workflow_title_callback' ) );

        // Delete workflow action
        add_action( 'wp_ajax_joinotify_delete_workflow_action', array( $this, 'delete_workflow_action_callback' ) );

        // Add new action to handle the delete trigger via AJAX
        add_action( 'wp_ajax_joinotify_delete_trigger', array( $this, 'delete_trigger_callback' ) );

        // export workflow action
        add_action( 'wp_ajax_joinotify_export_workflow', array( $this,  'export_workflow_callback' ) );

        // export workflow action
        add_action( 'wp_ajax_joinotify_get_phone_numbers', array( $this,  'get_phone_numbers_callback' ) );

        // register sender action
        add_action( 'wp_ajax_joinotify_register_phone_sender', array( $this,  'register_phone_sender_callback' ) );

        // validate otp
        add_action( 'wp_ajax_joinotify_validate_otp', array( $this, 'validate_otp_callback' ) );

        // remove phone sender
        add_action( 'wp_ajax_joinotify_remove_phone_sender', array( $this, 'remove_phone_sender_callback' ) );

        // run workflow test
        add_action( 'wp_ajax_joinotify_run_workflow_test', array( $this, 'run_workflow_test_callback' ) );

        // reset settings to default
		add_action( 'wp_ajax_joinotify_reset_plugin_action', array( $this, 'reset_plugin_callback' ) );

        // change workflow status on workflows table
        add_action( 'wp_ajax_joinotify_toggle_post_status', array( $this, 'toggle_post_status_callback' ) );

        // send message test for admin panel
        add_action( 'wp_ajax_joinotify_send_message_test', array( $this, 'send_message_test_callback' ) );
    }


    /**
     * Save options in AJAX
     * 
     * @since 1.0.0
     * @return void
     */
    public function admin_save_options_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_save_options' ) {
            // Convert serialized data into an array
            parse_str( $_POST['form_data'], $form_data );

            $switch_options = apply_filters( 'Joinotify/Admin/Ajax/Save_Options', array(
                'enable_whatsapp_integration',
                'enable_woocommerce_integration',
                'enable_elementor_integration',
                'enable_wpforms_integration',
                'enable_flexify_checkout_integration',
                'enable_wordpress_integration',
            //    'enable_advanced_trigger',
                'enable_proxy_api',
            ));

            foreach ( $switch_options as $switch ) {
                $options[$switch] = isset( $form_data[$switch] ) ? 'yes' : 'no';
            }

            // Merge the form data with the default options
            $updated_options = wp_parse_args( $form_data, $options );

            // Save the updated options
            $saved_options = update_option( 'joinotify_settings', $updated_options );

            if ( $saved_options ) {
                $response = array(
                    'status' => 'success',
                    'toast_header_title' => esc_html__( 'Salvo com sucesso', 'joinotify' ),
                    'toast_body_title' => esc_html__( 'As configurações foram atualizadas!', 'joinotify' ),
                );

                if ( JOINOTIFY_DEBUG_MODE ) {
                    $response['debug'] = array(
                        'options' => $updated_options,
                    );
                }

                wp_send_json( $response ); // Send JSON response
            }
        }
    }


    /**
     * Active license process on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function active_license_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_active_license' ) {
            $this->response_obj = new \stdClass();
            $message = '';
            $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
        
            // clear response cache first
            delete_transient('joinotify_api_request_cache');
            delete_transient('joinotify_api_response_cache');
            delete_transient('joinotify_license_status_cached');

            update_option( 'joinotify_license_key', $license_key ) || add_option('joinotify_license_key', $license_key );
            update_option( 'joinotify_temp_license_key', $license_key ) || add_option('joinotify_temp_license_key', $license_key );
    
            // Check on the server if the license is valid and update responses and options
            if ( License::check_license( $license_key, $this->license_message, $this->response_obj, JOINOTIFY_FILE ) ) {
                if ( $this->response_obj && $this->response_obj->is_valid ) {
                    update_option( 'joinotify_license_status', 'valid' );
                    delete_option('joinotify_temp_license_key');
                    delete_option('joinotify_alternative_license_activation');
                } else {
                    update_option( 'joinotify_license_status', 'invalid' );
                }
        
                if ( License::is_valid() ) {
                    $response = array(
                        'status' => 'success',
                        'toast_header_title' => __( 'Licença ativada com sucesso.', 'joinotify' ),
                        'toast_body_title' => __( 'Agora todos os recursos estão ativos!', 'joinotify' ),
                    );
                }
            } else {
                if ( ! empty( $license_key ) && ! empty( $this->license_message ) ) {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                        'toast_body_title' => $this->license_message,
                    );
                }
            }

            // send response for frontend
            wp_send_json( $response );
        }
    }


    /**
     * Alternative activation license process on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function alternative_active_license_callback() {
        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'joinotify_alternative_activation_license' ) {
            $response = array(
                'status' => 'error',
                'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                'toast_body_title' => __( 'Erro ao carregar o arquivo. A ação não foi acionada corretamente.', 'joinotify' ),
            );

            wp_send_json( $response );
        }

        // Check if the file was uploaded
        if ( empty( $_FILES['file'] ) ) {
            $response = array(
                'status' => 'error',
                'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                'toast_body_title' => __( 'Erro ao carregar o arquivo. O arquivo não foi enviado.', 'joinotify' ),
            );

            wp_send_json( $response );
        }

        $file = $_FILES['file'];

        // Check if it is a .key file
        if ( pathinfo( $file['name'], PATHINFO_EXTENSION ) !== 'key' ) {
            $response = array(
                'status' => 'invalid_file',
                'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                'toast_body_title' => __( 'Arquivo inválido. O arquivo deve ser extensão .key', 'joinotify' ),
            );
            
            wp_send_json( $response );
        }

        $file_content = file_get_contents( $file['tmp_name'] );

        $decrypt_keys = array(
            'E63390D3F50B70F0', // original product key
            'B729F2659393EE27', // Clube M
        );

        $decrypted_data = License::decrypt_alternative_license( $file_content, $decrypt_keys );

        if ( $decrypted_data !== null ) {
            $license_data_array = json_decode( stripslashes( $decrypted_data ) );
            $this_domain = License::get_domain();

            if ( $license_data_array === null ) {
                return;
            }

            // stop if this site is not same from license site
            if ( $this_domain !== $license_data_array->site_domain ) {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => __( 'O domínio de ativação não é permitido.', 'joinotify' ),
                );
    
                wp_send_json( $response );
            }

            $allowed_products = array(
                '8', // Joinotify product ID
                '7', // Clube M product ID
            );

            // stop if product license is not same this product
            if ( ! in_array( $license_data_array->selected_product, $allowed_products ) ) {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => __( 'A licença informada não é permitida para este produto.', 'joinotify' ),
                );
    
                wp_send_json( $response );
            }

            $license_object = $license_data_array->license_object;

            if ( $this_domain === $license_data_array->site_domain ) {
                delete_transient('joinotify_api_request_cache');
                delete_transient('joinotify_api_response_cache');
                delete_transient('joinotify_license_status_cached');

                $obj = new \stdClass();
                $obj->license_key = $license_data_array->license_code;
                $obj->email = $license_data_array->user_email;
                $obj->domain = $this_domain;
                $obj->app_version = JOINOTIFY_VERSION;
                $obj->product_id = $license_data_array->selected_product;
                $obj->product_base = $license_data_array->product_base;
                $obj->is_valid = $license_object->is_valid;
                $obj->license_title = $license_object->license_title;
                $obj->expire_date = $license_object->expire_date;

                update_option( 'joinotify_alternative_license', 'active' );
                update_option( 'joinotify_license_response_object', $obj );
                update_option( 'joinotify_license_key', $obj->license_key );
                update_option( 'joinotify_license_status', 'valid' );

                $response = array(
                    'status' => 'success',
                    'dropfile_message' => __( 'Arquivo enviado com sucesso.', 'joinotify' ),
                    'toast_header_title' => __( 'Licença ativada com sucesso.', 'joinotify' ),
                    'toast_body_title' => __( 'Agora todos os recursos estão ativos!', 'joinotify' ),
                );

                wp_send_json( $response );
            }
        } else {
            $response = array(
                'status' => 'error',
                'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                'toast_body_title' => __( 'Não foi possível descriptografar o arquivo de licença.', 'joinotify' ),
            );

            wp_send_json( $response );
        }
    }


    /**
     * Deactive license process on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function deactive_license_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_deactive_license' ) {
            $message = '';

            if ( License::deactive_license( JOINOTIFY_FILE, $message ) ) {
                $response = array(
                    'status' => 'success',
                    'toast_header_title' => __( 'Licença desativada.', 'joinotify' ),
                    'toast_body_title' => __( 'A licença foi desativada com sucesso!', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => __( 'Não foi possível desativar a licença.', 'joinotify' ),
                );
            }

            // send response for frontend
            wp_send_json( $response );
        }
    }


    /**
     * Get workflow templates on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function get_workflow_templates_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_get_workflow_templates' ) {
            $template_type = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : '';
        
            if ( $template_type === 'template' ) {
                $templates = Workflow_Templates::get_templates( 'meumouse', 'joinotify', 'dist/templates', 'main', null );
        
                if ( ! empty( $templates ) ) {
                    $template_html = '';
    
                    foreach ( $templates as $filename => $content ) {
                        $decoded_content = json_decode( $content, true );
        
                        if ( $decoded_content !== null ) {
                            $title = isset( $decoded_content['post']['title'] ) ? esc_html( $decoded_content['post']['title'] ) : esc_html( $filename );
                            $category = isset( $decoded_content['post']['category'] ) ? esc_attr( $decoded_content['post']['category'] ) : '';
        
                            $template_html .= '<div class="template-item" data-category="' . $category . '">';
                                $template_html .= '<div class="template-item-header mb-3">';
                                    $template_html .= '<h4 class="title">' . $title . '</h4>';
                                $template_html .= '</div>';
                                $template_html .= '<button class="btn btn-sm btn-outline-primary d-flex align-items-center download-template" data-file="' . esc_attr( $filename ) . '">';
                                    $template_html .= '<svg class="icon icon-primary me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m12 18 4-5h-3V2h-2v11H8z"></path><path d="M19 9h-4v2h4v9H5v-9h4V9H5c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2v-9c0-1.103-.897-2-2-2z"></path></svg>';
                                    $template_html .= esc_html__( 'Importar fluxo', 'joinotify' );
                                $template_html .= '</button>';
                            $template_html .= '</div>';
                        }
                    }
        
                    $response = array(
                        'status' => 'success',
                        'template_html' => $template_html,
                    );
                } else {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                        'toast_body_title' => __( 'Não foi possível carregar os modelos.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => __( 'Tipo de template inválido.', 'joinotify' ),
                );
            }

            // send response for frontend
            wp_send_json( $response );
        }
    }


    /**
     * Create workflow on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function create_workflow_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_create_workflow' ) {
            $context = isset( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : '';
            $trigger = isset( $_POST['trigger'] ) ? sanitize_text_field( $_POST['trigger'] ) : '';
            $title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    
            // Structure the workflow content with a trigger using create_workflow_structure
            $new_trigger = Core::create_workflow_structure( 'trigger', array(
                'context' => $context,
                'trigger' => $trigger,
                'title' => $title,
            ));
    
            if ( $post_id > 0 && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                // Update existing post
                $updated_post = array(
                    'ID' => $post_id,
                    'post_title' => $title,
                );
    
                $update_result = wp_update_post( $updated_post );
    
                if ( $update_result ) {
                    // Retrieve existing workflow content
                    $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
    
                    // Remove existing trigger(s) from the workflow content
                    $workflow_content_updated = array_filter( $workflow_content, function( $item ) {
                        return $item['type'] !== 'trigger';
                    });
    
                    // Add the new trigger at the beginning
                    array_unshift( $workflow_content_updated, $new_trigger );
    
                    // Update the post meta
                    update_post_meta( $post_id, 'joinotify_workflow_content', $workflow_content_updated );
    
                    $response = array(
                        'status' => 'success',
                        'post_id' => $post_id,
                        'proceed' => true,
                        'workflow_content' => Core::get_workflow_content( $post_id ),
                        'workflow_status' => Components::check_workflow_status( $post_id ),
                        'toast_header_title' => __('Fluxo atualizado com sucesso', 'joinotify'),
                        'toast_body_title' => __('Acionamento e fluxo atualizados com sucesso!', 'joinotify'),
                    );
    
                    $action_conditions = Conditions::get_conditions_by_trigger( $trigger );
                    $condition_selectors = Components::render_condition_selectors( $action_conditions );
                    $response['condition_selectors'] = $condition_selectors;
    
                    if ( JOINOTIFY_DEBUG_MODE ) {
                        $response['debug'] = array(
                            'check_content' => get_post_meta( $post_id, 'joinotify_workflow_content', true ),
                        );
                    }
                } else {
                    // Error updating post
                    $response = array(
                        'status' => 'error',
                        'proceed' => false,
                        'toast_header_title' => __('Ops! Ocorreu um erro.', 'joinotify'),
                        'toast_body_title' => __('Ocorreu um erro ao atualizar o fluxo e acionamento.', 'joinotify'),
                    );
                }
            } else {
                // Create new post
                $post_data = array(
                    'post_title' => $title,
                    'post_type' => 'joinotify-workflow',
                    'post_status' => 'draft',
                    'meta_input' => array(
                        'joinotify_workflow_content' => array( $new_trigger ),
                    ),
                );
    
                // Insert post data and get created post ID
                $new_post_id = wp_insert_post( $post_data );
    
                if ( $new_post_id !== 0 ) {
                    $response = array(
                        'status' => 'success',
                        'post_id' => $new_post_id,
                        'proceed' => true,
                        'workflow_content' => Core::get_workflow_content( $new_post_id ),
                        'workflow_status' => Components::check_workflow_status( $new_post_id ),
                        'toast_header_title' => __('Fluxo criado com sucesso', 'joinotify'),
                        'toast_body_title' => __('Acionamento e fluxo criados com sucesso!', 'joinotify'),
                    );
    
                    $action_conditions = Conditions::get_conditions_by_trigger( $trigger );
                    $response['condition_selectors'] = Components::render_condition_selectors( $action_conditions );
                    $response['placeholders_list'] = Components::render_placeholders_list( $post_id );
    
                    if ( JOINOTIFY_DEBUG_MODE ) {
                        $response['debug'] = array(
                            'check_content' => get_post_meta( $new_post_id, 'joinotify_workflow_content', true ),
                        );
                    }
                } else {
                    $response = array(
                        'status' => 'error',
                        'proceed' => false,
                        'toast_header_title' => __('Ops! Ocorreu um erro.', 'joinotify'),
                        'toast_body_title' => __('Ocorreu um erro ao criar o fluxo e acionamento.', 'joinotify'),
                    );
                }
            }
    
            // Send response to frontend
            wp_send_json( $response );
        }
    }


    /**
     * Load workflow data on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function load_workflow_data_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_load_workflow_data' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;
            $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );

            if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $response = array(
                    'status' => 'success',
                    'workflow_title' => get_post( $post_id )->post_title,
                    'workflow_status' => get_post_status( $post_id ),
                    'display_workflow_status' => Components::check_workflow_status( $post_id ),
                    'workflow_content' => Core::get_workflow_content( $post_id ),
                    'has_trigger' => Core::check_workflow_content( $post_id, 'trigger' ),
                    'has_action' => Core::check_workflow_content( $post_id, 'action' ),
                );

                foreach ( $workflow_data as $workflow ) {
                    if ( isset( $workflow['type'] ) && $workflow['type'] === 'trigger' ) {
                        if ( isset( $workflow['data']['context'], $workflow['data']['trigger'] ) ) {
                            $action_conditions = Conditions::get_conditions_by_trigger( $workflow['data']['trigger'] );
                            $response['condition_selectors'] = Components::render_condition_selectors( $action_conditions );
                            $response['placeholders_list'] = Components::render_placeholders_list( $post_id );
                        }
                    }
                }

                if ( JOINOTIFY_DEBUG_MODE ) {
                    $response['debug'] = array(
                        'post_object' => get_post( $post_id ),
                        'post_meta' => $workflow_data,
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => __( 'Ocorreu um erro ao recuperar os dados do fluxo.', 'joinotify' ),
                );
            }

            // send response to frontend
            wp_send_json( $response );
        }
    }


    /**
     * Update workflow status on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function update_workflow_status_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_update_workflow_status' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $updated_post = array(
                    'ID' => $post_id,
                    'post_status' => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'draft',
                );

                $result = wp_update_post( $updated_post );

                // check if post has been updated successful
                if ( $result ) {
                    if ( get_post_status( $post_id ) === 'publish' ) {
                        $workflow_status = esc_html__( 'Ativo', 'joinotify' );
                    } else {
                        $workflow_status = esc_html__( 'Inativo', 'joinotify' );
                    }

                    $response = array(
                        'status' => 'success',
                        'workflow_status' => $workflow_status,
                        'display_workflow_status' => Components::check_workflow_status( $post_id ),
                        'toast_header_title' => __( 'Status atualizado com sucesso', 'joinotify' ),
                        'toast_body_title' => __( 'O status do fluxo foi atualizado.', 'joinotify' ),
                    );
                } else {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                        'toast_body_title' => __( 'Ocorreu um erro ao atualizar o status do fluxo.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Fluxo não encontrado', 'joinotify' ),
                    'toast_body_title' => __( 'O fluxo não foi encontrado ou o tipo de post está incorreto.', 'joinotify' ),
                );
            }

            // send response to frontend
            wp_send_json( $response );
        }
    }


    /**
     * Add action on workflow on AJAX callback
     * 
     * @since 1.0.0
     * @version 1.0.5
     * @return void
     */
    public function add_workflow_action_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_add_workflow_action' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;
            $current_action_id = isset( $_POST['action_id'] ) ? sanitize_text_field( $_POST['action_id'] ) : '';
            $condition_action = isset( $_POST['condition_action'] ) ? sanitize_text_field( $_POST['condition_action'] ) : '';
            $workflow_action = isset( $_POST['workflow_action'] ) ? json_decode( stripslashes( $_POST['workflow_action'] ), true ) : array();
    
            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                // Retrieve workflow content
                $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
    
                // If empty workflow content, initialize empty array
                if ( empty( $workflow_content ) ) {
                    $workflow_content = array();
                }
    
                $updated = false;
    
                // Create new action using create_workflow_structure
                $new_action = Core::create_workflow_structure(
                    'action',
                    array(
                        'title' => isset( $workflow_action['data']['title'] ) ? $workflow_action['data']['title'] : '',
                        'description' => isset( $workflow_action['data']['description'] ) ? $workflow_action['data']['description'] : '',
                        'action' => isset( $workflow_action['data']['action'] ) ? $workflow_action['data']['action'] : '',
                        'message' => isset( $workflow_action['data']['message'] ) ? $workflow_action['data']['message'] : ''
                    )
                );
    
                if ( isset( $workflow_action['data']['action'] ) ) {
                    if ( $workflow_action['data']['action'] === 'condition' ) {
                        $new_action['data']['description'] = Core::build_funnel_action_message( $workflow_action );
                        $new_action['data']['condition_content']['condition'] = isset( $workflow_action['data']['condition_content']['condition'] ) ? $workflow_action['data']['condition_content']['condition'] : '';
                        $new_action['data']['condition_content']['type'] = isset( $workflow_action['data']['condition_content']['type'] ) ? $workflow_action['data']['condition_content']['type'] : '';
                        $new_action['data']['condition_content']['value'] = isset( $workflow_action['data']['condition_content']['value'] ) ? $workflow_action['data']['condition_content']['value'] : '';
                    } elseif ( $workflow_action['data']['action'] === 'time_delay' ) {
                        $new_action['data']['description'] = Core::build_funnel_action_message( $workflow_action );
                        $new_action['data']['delay_type'] = isset( $workflow_action['data']['delay_type'] ) ? $workflow_action['data']['delay_type'] : '';
                        $new_action['data']['delay_value'] = isset( $workflow_action['data']['delay_value'] ) ? $workflow_action['data']['delay_value'] : '';
                        $new_action['data']['delay_period'] = isset( $workflow_action['data']['delay_period'] ) ? $workflow_action['data']['delay_period'] : '';
                        $new_action['data']['date_value'] = isset( $workflow_action['data']['date_value'] ) ? $workflow_action['data']['date_value'] : '';
                        $new_action['data']['time_value'] = isset( $workflow_action['data']['time_value'] ) ? $workflow_action['data']['time_value'] : '';

                        $delay_type = $workflow_action['data']['delay_type'] ?? 'period';

                        if ( $delay_type === 'period' ) {
                            $delay_value = (int) ( $workflow_action['data']['delay_value'] ?? 0 );
                            $delay_period = $workflow_action['data']['delay_period'] ?? 'seconds';

                            $new_action['data']['delay_timestamp'] = Schedule::get_delay_timestamp( $delay_value, $delay_period );
                        } elseif ( $delay_type === 'date' ) {
                            // Calculate timestamp from given date and time
                            $date_value = $workflow_action['data']['date_value'] ?? '';
                            $time_value = $workflow_action['data']['time_value'] ?? '00:00';

                            if ( ! empty( $date_value ) ) {
                                $datetime = $date_value . ' ' . $time_value;
                                $timestamp = strtotime( $datetime );

                                if ( $timestamp ) {
                                    $new_action['data']['delay_timestamp'] = $timestamp;
                                }
                            }
                        }
                    } elseif ( $workflow_action['data']['action'] === 'send_whatsapp_message_text' ) {
                        $new_action['data']['description'] = Core::build_funnel_action_message( $workflow_action );
                        $new_action['data']['message'] = isset( $workflow_action['data']['message'] ) ? $workflow_action['data']['message'] : '';
                        $new_action['data']['sender'] = isset( $workflow_action['data']['sender'] ) ? $workflow_action['data']['sender'] : '';
                        $new_action['data']['receiver'] = isset( $workflow_action['data']['receiver'] ) ? $workflow_action['data']['receiver'] : '';
                    } elseif ( $workflow_action['data']['action'] === 'send_whatsapp_message_media' ) {
                        $new_action['data']['description'] = Core::build_funnel_action_message( $workflow_action );
                        $new_action['data']['sender'] = isset( $workflow_action['data']['sender'] ) ? $workflow_action['data']['sender'] : '';
                        $new_action['data']['receiver'] = isset( $workflow_action['data']['receiver'] ) ? $workflow_action['data']['receiver'] : '';
                        $new_action['data']['media_url'] = isset( $workflow_action['data']['media_url'] ) ? $workflow_action['data']['media_url'] : '';
                        $new_action['data']['media_type'] = isset( $workflow_action['data']['media_type'] ) ? $workflow_action['data']['media_type'] : '';
                    }
                }
    
                // Find and update the specific trigger or action by ID
                foreach ( $workflow_content as &$existing_action ) {
                    // Check if existing action ID matches current action ID
                    if ( isset( $existing_action['id'] ) && $existing_action['id'] === $current_action_id ) {
                        // Add the new action to the children of the current trigger or action
                        if ( ! isset( $existing_action['children'] ) ) {
                            $existing_action['children'] = array();
                        }
    
                        if ( isset( $existing_action['data']['action'] ) && $existing_action['data']['action'] === 'condition' ) {
                            // Handle condition actions with action_true and action_false
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
                            // For non-condition actions, add to the children array
                            $existing_action['children'][] = $new_action;
                        }
    
                        $updated = true;
    
                        break; // Stop the loop after finding and updating the action
                    }
                }
    
                // If the action was not found, add it as new at the top level
                if ( $updated === false ) {
                    $workflow_content[] = $new_action;
                }
    
                // Update workflow content
                $result = update_post_meta( $post_id, 'joinotify_workflow_content', $workflow_content );
    
                // Check if post has been updated successfully
                if ( $result ) {
                    $response = array(
                        'status' => 'success',
                        'has_action' => Core::check_workflow_content( $post_id, 'action' ),
                        'workflow_content' => Core::get_workflow_content( $post_id ),
                        'toast_header_title' => __( 'Ação adicionada com sucesso', 'joinotify' ),
                        'toast_body_title' => __( 'Ação adicionada no fluxo com sucesso!', 'joinotify' ),
                    );
    
                    if ( JOINOTIFY_DEBUG_MODE ) {
                        $response['debug'] = array(
                            'existing_content' => $workflow_content,
                            'action_id' => $current_action_id,
                            'condition_action' => $condition_action,
                            'action_updated' => $updated,
                        );
                    }
                } else {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                        'toast_body_title' => __( 'Ocorreu um erro ao adicionar ação no fluxo.', 'joinotify' ),
                    );
    
                    if ( JOINOTIFY_DEBUG_MODE ) {
                        $response['debug'] = array(
                            'update_meta' => $result,
                            'workflow_action' => $workflow_action,
                            'existing_content' => $workflow_content,
                            'post_object' => get_post( $post_id ),
                            'post_meta' => get_post_meta( $post_id, 'joinotify_workflow_content' ),
                        );
                    }
                }
    
                // Send response to frontend
                wp_send_json( $response );
            }
        }
    }


    /**
     * Update workflow title
     * 
     * @since 1.0.0
     * @return void
     */
    public function update_workflow_title_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_update_workflow_title' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;
            $workflow_title = isset( $_POST['workflow_title'] ) ? sanitize_text_field( $_POST['workflow_title'] ) : '';

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $updated_post = array(
                    'ID' => $post_id,
                    'post_title' => $workflow_title,
                );

                $result = wp_update_post( $updated_post );

                // check if post has been updated successful
                if ( $result ) {
                    $response = array(
                        'status' => 'success',
                        'workflow_title' => $workflow_title,
                        'toast_header_title' => __( 'Título atualizado com sucesso', 'joinotify' ),
                        'toast_body_title' => __( 'O título do fluxo foi atualizado.', 'joinotify' ),
                    );
                } else {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                        'toast_body_title' => __( 'Ocorreu um erro ao atualizar o título do fluxo.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Fluxo não encontrado', 'joinotify' ),
                    'toast_body_title' => __( 'O fluxo não foi encontrado ou o tipo de post está incorreto.', 'joinotify' ),
                );
            }

            // send response to frontend
            wp_send_json( $response );
        }
    }


    /**
     * Delete action from workflow via AJAX callback
     *
     * @since 1.0.0
     * @return void
     */
    public function delete_workflow_action_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_delete_workflow_action' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;
            $action_id = isset( $_POST['action_id'] ) ? sanitize_text_field( $_POST['action_id'] ) : null;
    
            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' && $action_id ) {
                // retrieve workflow content
                $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
    
                if ( ! empty( $workflow_content ) ) {
                    $workflow_content = Core::delete_item_recursive( $workflow_content, $action_id );
    
                    // reindex the main array to prevent gaps in array keys
                    $workflow_content = array_values( $workflow_content );
                    $result = update_post_meta( $post_id, 'joinotify_workflow_content', $workflow_content );
    
                    if ( $result ) {
                        $response = array(
                            'status' => 'success',
                            'workflow_content' => Core::get_workflow_content( $post_id ),
                            'has_action' => Core::check_workflow_content( $post_id, 'action' ),
                            'toast_header_title' => __( 'Ação excluída com sucesso', 'joinotify' ),
                            'toast_body_title' => __( 'A ação foi removida do fluxo com sucesso!', 'joinotify' ),
                        );
                    } else {
                        $response = array(
                            'status' => 'error',
                            'post_id' => $post_id,
                            'action_id' => $action_id,
                            'check_content' => $workflow_content,
                            'toast_header_title' => __( 'Erro ao atualizar o fluxo', 'joinotify' ),
                            'toast_body_title' => __( 'Não foi possível atualizar o fluxo após a exclusão da ação.', 'joinotify' ),
                        );

                        if ( JOINOTIFY_DEBUG_MODE ) {
                            $response['debug'] = array(
                                'action_id' => $action_id,
                            );
                        }
                    }
                } else {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __( 'Fluxo vazio', 'joinotify' ),
                        'toast_body_title' => __( 'Não há ações para remover no fluxo.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Dados inválidos', 'joinotify' ),
                    'toast_body_title' => __( 'Os dados fornecidos são inválidos ou o fluxo não foi encontrado.', 'joinotify' ),
                );
            }
    
            // send response for frontend
            wp_send_json( $response );
        }
    }    


    /**
     * Delete trigger on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function delete_trigger_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_delete_trigger' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;
            $trigger_id = isset( $_POST['trigger_id'] ) ? sanitize_text_field( $_POST['trigger_id'] ) : '';
    
            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                // Retrieve the current workflow content
                $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
    
                if ( ! empty( $workflow_content ) ) {
                    // Remove the trigger using delete_item_recursive
                    $workflow_content = Core::delete_item_recursive( $workflow_content, $trigger_id );
    
                    // Reindex the array to maintain consistent indexes
                    $workflow_content = array_values( $workflow_content );
    
                    // Update the workflow content
                    $result = update_post_meta( $post_id, 'joinotify_workflow_content', $workflow_content );
    
                    if ( $result ) {
                        $response = array(
                            'status' => 'success',
                            'workflow_content' => Core::get_workflow_content( $post_id ),
                            'has_trigger' => Core::check_workflow_content( $post_id, 'trigger' ),
                            'toast_header_title' => __('Acionamento removido com sucesso', 'joinotify'),
                            'toast_body_title' => __('O acionamento foi removido do fluxo com sucesso!', 'joinotify'),
                        );
                    } else {
                        $response = array(
                            'status' => 'error',
                            'toast_header_title' => __('Erro ao atualizar o fluxo', 'joinotify'),
                            'toast_body_title' => __('Não foi possível atualizar o fluxo após a exclusão do acionamento.', 'joinotify'),
                        );

                        if ( JOINOTIFY_DEBUG_MODE ) {
                            $response['debug'] = array(
                                'trigger_id' => $trigger_id,
                                'workflow_content' => $workflow_content,
                            );
                        }
                    }
                } else {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __('Fluxo vazio', 'joinotify'),
                        'toast_body_title' => __('Não há acionamentos para remover no fluxo.', 'joinotify'),
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __('Dados inválidos', 'joinotify'),
                    'toast_body_title' => __('Os dados fornecidos são inválidos ou o fluxo não foi encontrado.', 'joinotify'),
                );
            }
    
            // Send response for frontend
            wp_send_json( $response );
        }
    }


    /**
     * Export workflow on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function export_workflow_callback() {
        check_ajax_referer( 'joinotify_export_workflow_nonce', 'security' );
    
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    
        if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $post = get_post( $post_id );
            $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );
            $category = '';

            // Check if workflow_data contains triggers and get the context
            if ( is_array( $workflow_data ) ) {
                foreach ( $workflow_data as $item ) {
                    if ( isset( $item['type'] ) && $item['type'] === 'trigger' && isset( $item['data']['context'] ) ) {
                        $category = $item['data']['context'];
                        break;
                    }
                }
            }

            $export_data = array(
                'plugin_version' => JOINOTIFY_VERSION,
                'post' => array(
                    'title' => $post->post_title,
                    'date' => $post->post_date,
                    'status' => $post->post_status,
                    'modified' => $post->post_modified,
                    'category' => $category,
                ),
                'workflow_content' => $workflow_data,
            );

            $response = array(
                'status' => 'success',
                'export_data' => $export_data,
                'toast_header_title' => __( 'Download realizado com sucesso', 'joinotify' ),
                'toast_body_title' => __( 'Fluxo exportado com sucesso!', 'joinotify' ),
            );
        } else {
            $response = array(
                'status' => 'error',
                'toast_header_title' => __( 'Dados inválidos', 'joinotify' ),
                'toast_body_title' => __( 'Os dados fornecidos são inválidos ou o fluxo não foi encontrado.', 'joinotify' ),
            );
        }

        // send response for frontend
        wp_send_json( $response );
    }


    /**
     * Get templates library on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function get_templates_count_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_get_templates_count' ) {
            $template_type = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : '';
        
            if ( $template_type === 'template' ) {
                // Get the number of templates
                $template_count = Workflow_Templates::get_templates_count( 'meumouse', 'joinotify', 'dist/templates', 'main', null );
        
                if ( $template_count !== null ) {
                    $response = array(
                        'status' => 'success',
                        'template_count' => $template_count,
                    );
                } else {
                    $response = array(
                        'status' => 'error',
                        'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                        'toast_body_title' => __( 'Não foi possível obter a quantidade de templates.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => __( 'Tipo de template inválido.', 'joinotify' ),
                );
            }
        
            // send response for frontend
            wp_send_json( $response );
        }
    }


    /**
     * Get phones senders on AJAX callback
     * 
     * @since 1.0.0
     * @version 1.0.2
     * @return void
     */
    public function get_phone_numbers_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_get_phone_numbers' ) {
            $response_data = Controller::get_numbers();
    
            // Check if the response has 'slots' and if it is an array
            $phone_numbers = isset( $response_data['slots'] ) && is_array( $response_data['slots'] ) ? $response_data['slots'] : array();
    
            // Get numbers already registered as senders
            $registered_phones = get_option( 'joinotify_get_phones_senders', array() );
    
            // Ensure $registered_phones is an array
            if ( ! is_array( $registered_phones ) ) {
                $registered_phones = array();
            }
    
            // Filter already registered numbers
            $filtered_phone_numbers = array_filter( $phone_numbers, function( $value ) use ( $registered_phones ) {
                // Make sure $value is an array and contains the key 'phone'
                return is_array( $value ) && isset( $value['phone'] ) && ! in_array( $value['phone'], $registered_phones, true );
            });
    
            // If there are no numbers available after the filter
            if ( empty( $filtered_phone_numbers ) ) {
                $response = array(
                    'status' => 'success',
                    'empty_phone_message' => sprintf( __( 'Não foi encontrado nenhum telefone disponível para cadastro. Faça o cadastro pelo link: <a class="fancy-link" href="%s" target="_blank">%s</a>', 'joinotify' ), esc_url( JOINOTIFY_REGISTER_PHONE_URL ), __( 'Cadastrar um remetente', 'joinotify' ) ),
                );
            } else {
                $html = '<ul class="list-group">';
    
                foreach ( $filtered_phone_numbers as $value ) {
                    // Make sure $value is valid
                    if ( ! is_array( $value ) || ! isset( $value['phone'] ) ) {
                        continue;
                    }
    
                    $html .= '<li class="list-group-item d-flex align-items-center justify-content-between py-3" data-phone="'. esc_attr( $value['phone'] ) .'">';
                    $html .= '<span class="fs-base">'. Helpers::format_phone_number( $value['phone'] ) .'</span>';
                    $html .= '<button class="btn btn-sm btn-outline-primary register-sender" data-phone="'. esc_attr( $value['phone'] ) .'">'. esc_html__( 'Cadastrar remetente', 'joinotify' ) .'</button>';
                    $html .= '</li>';
                }
    
                $html .= '</ul>';
    
                $response = array(
                    'status' => 'success',
                    'phone_numbers_html' => $html,
                );
            }
    
            // Send the response to the frontend
            wp_send_json( $response );
        }
    }    


    /**
     * Register phone sender on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function register_phone_sender_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_register_phone_sender' ) {
            $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
            $phone = preg_replace( '/\D/', '', $phone ); // allow only numbers
            $get_otp = Otp_Validation::generate_and_send_otp( $phone );

            if ( $get_otp ) {
                $response = array(
                    'status' => 'success',
                    'otp_input_component' => Components::otp_input_code( $phone ),
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => __( 'Não foi possível enviar o código de verificação.', 'joinotify' ),
                );
            }

            // send response for frontend
            wp_send_json( $response );
        }
    }

    
    /**
     * Callback to validate OTP
     * 
     * @since 1.0.0
     * @return void
     */
    public function validate_otp_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_validate_otp' ) {
            $phone = isset( $_POST['phone']) ? sanitize_text_field( $_POST['phone'] ) : '';
            $otp = isset( $_POST['otp']) ? sanitize_text_field( $_POST['otp'] ) : '';

            // Ensure that phone and OTP are not empty
            if ( empty( $phone ) || empty( $otp ) ) {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Erro na validação do OTP', 'joinotify' ),
                    'toast_body_title' => __( 'Por favor, preencha o número de telefone e o código OTP.', 'joinotify' ),
                );

                // send response for frontend
                wp_send_json( $response );

                return;
            }

            // Validate the OTP using the validate_otp method
            $otp_valid = Otp_Validation::validate_otp( $phone, $otp );

            if ( $otp_valid ) {
                // Get current phone senders from options
                $current_senders = get_option( 'joinotify_get_phones_senders', array() );

                // Ensure current senders is always an array
                if ( ! is_array( $current_senders ) ) {
                    $current_senders = array();
                }

                // Add the new phone to the array if it doesn't already exist
                if ( ! in_array( $phone, $current_senders, true ) ) {
                    $current_senders[] = $phone;
                }

                // Update the option with the new array of phone senders
                update_option( 'joinotify_get_phones_senders', $current_senders );

                // update connection state
                Controller::get_connection_state( $phone );

                // If OTP is valid, send a success response
                $response = array(
                    'status' => 'success',
                    'toast_header_title' => __( 'Verificação bem-sucedida', 'joinotify' ),
                    'toast_body_title' => __( 'Seu WhatsApp foi verificado com sucesso!', 'joinotify' ),
                    'current_phone_senders' => Components::current_phones_senders(),
                );
            } else {
                // If OTP is invalid or expired, send an error response
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Erro na verificação', 'joinotify' ),
                    'toast_body_title' => __( 'O código de verificação está incorreto ou expirou. Por favor, tente novamente.', 'joinotify' ),
                );
            }

            // send response for frontend
            wp_send_json( $response );
        }
    }


    /**
     * Callback to remove a phone sender
     *
     * @since 1.0.0
     * @return void
     */
    public function remove_phone_sender_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_remove_phone_sender' ) {
            $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

            if ( empty( $phone ) ) {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Erro ao remover telefone', 'joinotify' ),
                    'toast_body_title' => __( 'Número de telefone inválido.', 'joinotify' ),
                );

                wp_send_json( $response );

                return;
            }

            // Retrieves the current list of phones
            $phones_senders = get_option( 'joinotify_get_phones_senders', array() );

            if ( ! empty( $phones_senders ) && is_array( $phones_senders ) ) {
                // Searches for phone index and removes it if found
                $index = array_search( $phone, $phones_senders );

                if ( $index !== false ) {
                    unset( $phones_senders[$index] );

                    // Update option without phone removed
                    update_option( 'joinotify_get_phones_senders', array_values( $phones_senders ) );

                    $response = array(
                        'status' => 'success',
                        'toast_header_title' => __( 'Remetente removido', 'joinotify' ),
                        'toast_body_title' => __( 'O telefone remetente foi removido com sucesso!', 'joinotify' ),
                        'updated_list_html' => Components::current_phones_senders(),
                    );

                    wp_send_json( $response );
                }
            }

            $response = array(
                'status' => 'error',
                'toast_header_title' => __( 'Erro ao remover remetente', 'joinotify' ),
                'toast_body_title' => __( 'Não foi possível encontrar o telefone informado.', 'joinotify' ),
            );

            wp_send_json( $response );
        }
    }


    /**
     * Send message test for workflow test on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function run_workflow_test_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_run_workflow_test' ) {
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
                $receiver = Admin::get_setting('test_number_phone');

                if ( ! empty( $workflow_content ) && is_array( $workflow_content ) ) {
                    foreach ( $workflow_content as $item ) {
                        if ( isset( $item['type'] ) && $item['type'] === 'action' && isset( $item['data']['action'] ) && $item['data']['action'] === 'send_whatsapp_message_text' ) {
                            $sender = $item['data']['sender'];
                            $message = Placeholders::replace_placeholders( $item['data']['message'], 0, 'sandbox' );
                            $send_message_text = Controller::send_message_text( $sender, $receiver, $message );

                            if ( 201 === $send_message_text ) {
                                continue; // Continue to the next action if the message is successfully sent
                            } else {
                                $response = array(
                                    'status' => 'error',
                                    'toast_header_title' => __( 'Ops! Ocorreu um erro', 'joinotify' ),
                                    'toast_body_title' => __( 'Não foi possível enviar a mensagem de teste.', 'joinotify' ),
                                );

                                wp_send_json( $response );
                            }
                        }

                        if ( isset( $item['type'] ) && $item['type'] === 'action' && isset( $item['data']['action'] ) && $item['data']['action'] === 'send_whatsapp_message_media' ) {
                            $sender = $item['data']['sender'];
                            $media_type = $item['data']['media_type'];
                            $media = $item['data']['media_url'];
                            $send_message_media = Controller::send_message_media( $sender, $receiver, $media_type, $media );

                            if ( 201 === $send_message_media ) {
                                continue; // Continue to the next action if the message is successfully sent
                            } else {
                                $response = array(
                                    'status' => 'error',
                                    'toast_header_title' => __( 'Ops! Ocorreu um erro', 'joinotify' ),
                                    'toast_body_title' => __( 'Não foi possível enviar uma ou mais mensages de teste.', 'joinotify' ),
                                );

                                wp_send_json( $response );
                            }
                        }
                    }

                    // If all messages were sent successfully
                    $response = array(
                        'status' => 'success',
                        'toast_header_title' => __( 'Mensagens enviadas', 'joinotify' ),
                        'toast_body_title' => __( 'Todas as mensagens de teste foram enviadas com sucesso!', 'joinotify' ),
                    );

                    wp_send_json( $response );
                }

                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Erro na execução do fluxo', 'joinotify' ),
                    'toast_body_title' => __( 'Não foi possível processar o conteúdo do fluxo.', 'joinotify' ),
                );

                wp_send_json( $response );
            }
        }
    }


    /**
     * Reset plugin options to default on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function reset_plugin_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_reset_plugin_action' ) {
            $delete_option = delete_option('joinotify_settings');

            if ( $delete_option ) {
				delete_option('joinotify_alternative_license_activation');
				delete_transient('joinotify_api_request_cache');
                delete_transient('joinotify_api_response_cache');
                delete_transient('joinotify_license_status_cached');

                $response = array(
                    'status' => 'success',
                    'toast_header_title' => esc_html__( 'As opções foram redefinidas', 'joinotify' ),
                    'toast_body_title' => esc_html__( 'As opções foram redefinidas com sucesso!', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => esc_html__( 'Ops! Ocorreu um erro.', 'joinotify' ),
                    'toast_body_title' => esc_html__( 'Ocorreu um erro ao redefinir as configurações.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Change workflow status on workflows table on AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function toggle_post_status_callback() {
        if ( ! current_user_can('edit_posts') ) {
            $response = array(
                'status' => 'error',
                'toast_header_title' => esc_html__( 'Permissão negada', 'joinotify' ),
                'toast_body_title' => esc_html__( 'Você não tem permissão de editar posts', 'joinotify' ),
            );

            wp_send_json( $response );
        }
    
        // Verify that the post ID and new status were sent
        if ( isset( $_POST['post_id'] ) && isset( $_POST['status'] ) ) {
            $post_id = intval( $_POST['post_id'] );
            $status = $_POST['status'] === 'publish' ? 'publish' : 'draft';
    
            $updated = wp_update_post( array(
                'ID' => $post_id,
                'post_status' => $status,
            ), true );
    
            if ( is_wp_error( $updated ) ) {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => esc_html__( 'Ops! Ocorreu um erro', 'joinotify' ),
                    'toast_body_title' => esc_html__( 'Ocorreu um erro ao atualizar o status do post', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status' => 'success',
                    'toast_header_title' => esc_html__( 'Status do fluxo atualizado', 'joinotify' ),
                    'toast_body_title' => esc_html__( 'O status do fluxo foi atualizado com sucesso!', 'joinotify' ),
                );
            }
        } else {
            $response = array(
                'status' => 'error',
                'toast_header_title' => esc_html__( 'Ops! Ocorreu um erro', 'joinotify' ),
                'toast_body_title' => esc_html__( 'Dados incompletos', 'joinotify' ),
            );
        }

        wp_send_json( $response );
    }


    /**
     * Send message test on admin panel for AJAX callback
     * 
     * @since 1.0.0
     * @return void
     */
    public function send_message_test_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_send_message_test' ) {
            $sender = isset( $_POST['sender'] ) ? sanitize_text_field( $_POST['sender'] ) : '';
            $receiver = isset( $_POST['receiver'] ) ? sanitize_text_field( $_POST['receiver'] ) : '';
            $message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
            $send_test_message = Controller::send_message_text( $sender, $receiver, $message );

            if ( 201 === $send_test_message ) {
                $response = array(
                    'status' => 'success',
                    'toast_header_title' => __( 'Mensagem enviada', 'joinotify' ),
                    'toast_body_title' => __( 'A mensagem teste foi enviada com sucesso!', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'toast_header_title' => __( 'Ops! Ocorreu um erro', 'joinotify' ),
                    'toast_body_title' => __( 'Não foi possível enviar a mensagem de teste.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }
}