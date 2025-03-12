<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Validations\Media_Types;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Integrations\Woocommerce;
use MeuMouse\Joinotify\Integrations\Whatsapp;


// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the functions for display components on workflow builder
 * 
 * @since 1.0.0
 * @version 1.2.0
 * @package MeuMouse.com
 */
class Components {

    /**
     * Check workflow status
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @return string
     */
    public static function check_workflow_status( $post_id ) {
        if ( isset( $post_id ) ) {
            if ( 'publish' === get_post_status( $post_id ) ) {
                $class = 'text-success bg-success border-success';
                $text = esc_html__( 'Fluxo ativo', 'joinotify' );
            } else {
                $class = 'text-dark bg-secondary border-dark';
                $text = esc_html__( 'Fluxo inativo', 'joinotify' );
            }

            return '<span id="joinotify_workflow_status" class="fw-semibold fs-sm bg-opacity-10 border border-opacity-10 px-2 py-1 w-fit rounded-3 '. esc_attr( $class ) .'">'. $text .'</span>';
        }

        return '';
    }


    /**
     * Display workflow action component
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @param array $action_details | Action details array (action name, description, id, etc)
     * @return string
     */
    public static function workflow_action_component( $post_id, $action_details ) {
        foreach ( Actions::get_all_actions() as $action => $value ) {
            $action_id = $action_details['id'];

            // skip if is not action
            if ( $action_details['action_name'] !== $value['action'] ) {
                continue;
            }

            $html = '<div class="funnel-action-item" data-action="'. esc_attr( $value['action'] ) .'" data-action-id="'. esc_attr( $action_details['id'] ) .'">';
                $html .= '<div class="action-item-body">';
                    // action title
                    $html .= '<h4 class="title">'. $value['title'] .'</h4>';

                    if ( $value['action'] === 'send_whatsapp_message_text' || $value['action'] === 'send_whatsapp_message_media' ) {
                        $html .= '<span class="text-muted fs-xs sender d-block">'. sprintf( __( 'Remetente: %s', 'joinotify' ), $action_details['sender'] ) .'</span>';
                        $html .= '<span class="text-muted fs-xs receiver d-block mb-2">'. sprintf( __( 'Destinatário: %s', 'joinotify' ), $action_details['receiver'] ) .'</span>';
                    }

                    // action description funnel
                    if ( empty( $action_details['description'] ) ) {
                        $html .= '<span class="description">'. $value['description'] .'</span>';
                    } else {
                        $html .= '<span class="description">'. $action_details['description'] .'</span>';
                    }
                $html .= '</div>';

                $html .= '<div class="btn-group">';
                    $html .= '<div class="funnel-action-cta icon-translucent btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false"><svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 12c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg></div>';
                
                    $html .= '<div class="funnel-action-details">';
                        $html .= '<ul class="dropdown-menu builder-dropdown shadow-sm">';
                            $html .= '<li class="d-flex align-items-center mb-0">';
                                $html .= '<a id="exclude_action_'. esc_attr( $action_id ) .'" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 exclude-action" data-action-id="'. esc_attr( $action_id ) .'" href="#">';
                                    $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15 2H9c-1.103 0-2 .897-2 2v2H3v2h2v12c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2V8h2V6h-4V4c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 16H7V8h10v12z"></path></svg>';
                                    $html .= esc_html__( 'Excluir ação', 'joinotify' );
                                $html .= '</a>';
                            $html .= '</li>';
                            
                            if ( $value['action'] === 'condition' ) {
                                $html .= '<li class="d-flex align-items-center mb-0">';
                                    $html .= '<a id="edit_condition_'. esc_attr( $action_id ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-action" href="#" data-bs-toggle="modal" data-bs-target="#edit_condition_'. esc_attr( $action_id ) .'">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                        $html .= esc_html__( 'Editar condição', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';
                            } elseif ( $value['action'] !== 'condition' && $value['action'] !== 'stop_funnel' ) {
                                $html .= '<li class="d-flex align-items-center mb-0">';
                                    $html .= '<a id="edit_action_'. esc_attr( $action_id ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-action" href="#" data-bs-toggle="modal" data-bs-target="#edit_action_'. esc_attr( $action_id ) .'">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                        $html .= esc_html__( 'Editar ação', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';
                            }
                        $html .= '</ul>';
                    $html .= '</div>';
                $html .= '</div>';

                // display modal settings for condition action
                if ( $value['action'] === 'condition' ) {
                    $html .= '<div class="modal fade" id="edit_condition_'. esc_attr( $action_id ) .'" tabindex="-1" aria-labelledby="edit_condition_'. esc_attr( $action_id ) .'_label">';
                        $html .= '<div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">';
                            $html .= '<div class="modal-content">';
                                $html .= '<div class="modal-header px-4">';
                                    $html .= '<h3 class="modal-title fs-5" id="edit_condition_'. esc_attr( $action_id ) .'_label">'. esc_html__( 'Configurar condição', 'joinotify' ) .'</h3>';
                                    $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                $html .= '</div>';

                                $html .= '<div class="modal-body px-4 py-3 my-3">';
                                    $get_condition = Conditions::get_condition_content( $post_id, $action_id );

                                    $html .= self::render_condition_settings( $get_condition['condition'], $get_condition );
                                $html .= '</div>';

                                $html .= '<div class="modal-footer px-4">';
                                    $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                    $html .= '<button type="button" class="btn btn-primary save-action-edit m-0" data-action="'. esc_attr( $value['action'] ) .'" data-action-id="'. esc_attr( $action_id ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
                                $html .= '</div>';
                            $html .= '</div>';
                        $html .= '</div>';
                    $html .= '</div>';
                } elseif ( $value['action'] !== 'condition' && $value['action'] !== 'stop_funnel' ) {
                    $html .= '<div class="modal fade" id="edit_action_'. esc_attr( $action_id ) .'" tabindex="-1" aria-labelledby="edit_action_'. esc_attr( $action_id ) .'_label">';
                        $html .= '<div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">';
                            $html .= '<div class="modal-content">';
                                $html .= '<div class="modal-header px-4">';
                                    $html .= '<h3 class="modal-title fs-5" id="edit_action_'. esc_attr( $action_id ) .'_label">'. esc_html__( 'Configurar ação', 'joinotify' ) .'</h3>';
                                    $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                $html .= '</div>';

                                $html .= '<div class="modal-body px-4 py-3 my-3">';
                                    $html .= self::get_action_settings( $post_id, $value['action'], $action_id );
                                $html .= '</div>';

                                $html .= '<div class="modal-footer px-4">';
                                    $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                    $html .= '<button type="button" class="btn btn-primary save-action-edit m-0" data-action="'. esc_attr( $value['action'] ) .'" data-action-id="'. esc_attr( $action_id ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
                                $html .= '</div>';
                            $html .= '</div>';
                        $html .= '</div>';
                    $html .= '</div>';
                }
            $html .= '</div>';

            return $html;
        }
    }


    /**
     * Display action condition component for condition children actions
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param int $post_id | Post ID
     * @param array $condition_data | Condition data
     * @param string $title | (Optional) Action title
     * @param string $description | (Optional) Action description
     * @return string
     */
    public static function workflow_action_children_component( $post_id, $condition_data, $title = '', $description = '' ) {
        $html = '';

        foreach ( $condition_data as $action ) {
            foreach ( Actions::get_all_actions() as $action_index => $action_value ) {
                if ( $condition_data['data']['action'] !== $action_value['action'] ) {
                    continue;
                }

                $html = '<div class="funnel-action-item condition" data-action="'. esc_attr( $condition_data['data']['action'] ) .'" data-action-id="'. esc_attr( $action ) .'">';
                    $html .= '<div class="action-item-body">';
                        if ( ! empty( $title ) ) {
                            $html .= '<h4 class="title">'. $title .'</h4>';
                        } else {
                            $html .= '<h4 class="title">'. $action_value['title'] .'</h4>';
                        }

                        if ( $action_value['action'] === 'send_whatsapp_message_text' || $action_value['action'] === 'send_whatsapp_message_media' ) {
                            $html .= '<span class="text-muted fs-xs sender d-block">'. sprintf( __( 'Remetente: %s', 'joinotify' ), $condition_data['data']['sender'] ) .'</span>';
                            $html .= '<span class="text-muted fs-xs receiver d-block mb-2">'. sprintf( __( 'Destinatário: %s', 'joinotify' ), $condition_data['data']['receiver'] ) .'</span>';
                        }
                        
                        if ( ! empty( $description ) ) {
                            $html .= '<span class="description">'. $description .'</span>';
                        } else {
                            $html .= '<span class="description">'. $action_value['description'] .'</span>';
                        }
                    $html .= '</div>';

                    $html .= '<div class="btn-group">';
                        $html .= '<div class="funnel-action-cta icon-translucent btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false"><svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 12c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg></div>';
                    
                        $html .= '<div class="funnel-action-details">';
                            $html .= '<ul class="dropdown-menu builder-dropdown shadow-sm">';
                                $html .= '<li class="d-flex align-items-center mb-0">';
                                    $html .= '<a id="exclude_action_'. esc_attr( $condition_data['data']['action'] ) .'" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 exclude-action" data-action-id="'. esc_attr( $action ) .'" href="#">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15 2H9c-1.103 0-2 .897-2 2v2H3v2h2v12c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2V8h2V6h-4V4c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 16H7V8h10v12z"></path></svg>';
                                        $html .= esc_html__( 'Excluir ação', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';

                                if ( $condition_data['data']['action'] !== 'stop_funnel' ) {
                                    $html .= '<li class="d-flex align-items-center mb-0">';
                                        $html .= '<a id="edit_action_'. esc_attr( $condition_data['data']['action'] ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-action" href="#" data-bs-toggle="modal" data-bs-target="#edit_action_'. esc_attr( $condition_data['data']['action'] ) .'">';
                                            $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                            $html .= esc_html__( 'Editar ação', 'joinotify' );
                                        $html .= '</a>';
                                    $html .= '</li>';
                                }
                            $html .= '</ul>';
                        $html .= '</div>';
                    $html .= '</div>';

                    // display modal settings for condition action
                    if ( $condition_data['data']['action'] !== 'stop_funnel' ) {
                        $html .= '<div class="modal fade" id="edit_action_'. esc_attr( $condition_data['data']['action'] ) .'" tabindex="-1" aria-labelledby="edit_action_'. esc_attr( $condition_data['data']['action'] ) .'_label">';
                            $html .= '<div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">';
                                $html .= '<div class="modal-content">';
                                    $html .= '<div class="modal-header px-4">';
                                        $html .= '<h3 class="modal-title fs-5" id="edit_action_'. esc_attr( $condition_data['data']['action'] ) .'_label">'. esc_html__( 'Configurar ação', 'joinotify' ) .'</h3>';
                                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                    $html .= '</div>';

                                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                                        $html .= self::get_action_settings( $post_id, $condition_data['data']['action'], $condition_data['id'] );
                                    $html .= '</div>';

                                    $html .= '<div class="modal-footer px-4">';
                                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                        $html .= '<button type="button" class="btn btn-primary save-action-edit m-0" data-action="'. esc_attr( $action_value['action'] ) .'" data-action-id="'. esc_attr( $condition_data['id'] ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
                                    $html .= '</div>';
                                $html .= '</div>';
                            $html .= '</div>';
                        $html .= '</div>';
                    }
                $html .= '</div>';
            }

            return $html;
        }
    }


    /**
     * Get time delay options
     * 
     * @since 1.1.0
     * @return array
     */
    public static function get_time_delay_options() {
        return apply_filters( 'Joinotify/Components/Time_Delay_Options', array(
            'seconds' =>  esc_html__( 'Segundo (s)', 'joinotify' ),
            'minute' => esc_html__( 'Minuto (s)', 'joinotify' ),
            'hours' => esc_html__( 'Hora (s)', 'joinotify' ),
            'day' => esc_html__( 'Dia (s)', 'joinotify' ),
            'week' => esc_html__( 'Semana (s)', 'joinotify' ),
            'month' => esc_html__( 'Mês (es)', 'joinotify' ),
            'year' => esc_html__( 'Ano (s)', 'joinotify' ),
        ));
    }


    /**
     * Get each action settings
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @param string $action | Action name
     * @param string $action_id | Action ID
     * @return string
     */
    public static function get_action_settings( $post_id, $action, $action_id ) {
        // get workflow content from post id
        $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
        $current_action = Utils::find_workflow_item_by_id( $workflow_content, $action_id );
        $action_data = isset( $current_action['data'] ) ? $current_action['data'] : array();
        $html = '';

        switch ( $action ) {
            case 'time_delay':
                $html .= self::time_delay_action( $action_data );

                break;
            case 'send_whatsapp_message_text':
                $html .= Whatsapp::whatsapp_message_text_action( $action_data );

                // placeholders helper
                $html .= self::render_placeholders_list( $post_id );

                break;
            case 'send_whatsapp_message_media':
                $html .= Whatsapp::whatsapp_message_media_action( $action_data );

                break;
            case 'snippet_php':
                $html .= self::snippet_php_action( $action_data );

                break;
            case 'create_coupon':
                $html .= Woocommerce::create_coupon_action( $action_data, $post_id );

                break;
        }

        return $html;
    }


    /**
     * Get trigger HTML based on context and data_trigger
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @param array $trigger_details | Trigger details (context name, trigger name, etc)
     * @return mixed | return the HTML of the trigger or false if not found
     */
    public static function workflow_trigger_component( $post_id, $trigger_details ) {
        $context = $trigger_details['context'];
        $data_trigger = $trigger_details['data_trigger'];
        $trigger_id = $trigger_details['trigger_id'];
        $trigger = Triggers::get_trigger( $context, $data_trigger );

        // check if has trigger
        if ( $trigger ) {
            $html = '<div class="funnel-trigger-item" data-context="'. esc_attr( $context ) .'" data-trigger="'. esc_attr( $trigger['data_trigger'] ) .'" data-trigger-id="'. esc_attr( $trigger_id ) .'">';
                $html .= '<div class="funnel-trigger-header me-4">';
                    $html .= '<h4 class="title">'. esc_html( $trigger['title'] ) .'</h4>';
                    $html .= '<span class="description">'. esc_html( $trigger['description'] ) .'</span>';
                $html .= '</div>';

                if ( $trigger['require_settings'] !== false ) {
                    $html .= '<div class="btn-group">';
                        $html .= '<div class="funnel-trigger-cta icon-translucent btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false"><svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 12c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg></div>';
                    
                        $html .= '<div class="funnel-trigger-details">';
                            $html .= '<ul class="dropdown-menu builder-dropdown shadow-sm">';
                                $html .= '<li class="d-flex align-items-center mb-0">';
                                    $html .= '<a id="edit_trigger_'. esc_attr( $trigger_id ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-trigger" href="#" data-bs-toggle="modal" data-bs-target="#edit_trigger_'. esc_attr( $trigger_id ) .'">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                        $html .= esc_html__( 'Configurações', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';
                            $html .= '</ul>';
                        $html .= '</div>';

                        $html .= '<div class="modal fade" id="edit_trigger_'. esc_attr( $trigger_id ) .'" tabindex="-1" aria-labelledby="edit_trigger_'. esc_attr( $trigger_id ) .'_label">';
                            $html .= '<div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">';
                                $html .= '<div class="modal-content">';
                                    $html .= '<div class="modal-header px-4">';
                                        $html .= '<h3 class="modal-title fs-5" id="edit_trigger_'. esc_attr( $trigger_id ) .'_label">'. esc_html__( 'Configurar acionamento', 'joinotify' ) .'</h3>';
                                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                    $html .= '</div>';

                                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                                        $html .= self::required_settings_for_trigger( $post_id, $trigger_details );
                                    $html .= '</div>';

                                    $html .= '<div class="modal-footer px-4 py-3">';
                                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                        $html .= '<button type="button" class="btn btn-primary save-trigger-settings m-0" data-trigger="'. esc_attr( $data_trigger ) .'" data-trigger-id="'. esc_attr( $trigger_id ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
                                    $html .= '</div>';
                                $html .= '</div>';
                            $html .= '</div>';
                        $html .= '</div>';
                    $html .= '</div>';
                }
            $html .= '</div>';

            return $html;
        }

        return false;
    }


    /**
     * Render required settings for specific triggers
     * 
     * @since 1.1.0
     * @param int $post_id | Post ID
     * @param array $trigger_details | Trigger details (context name, trigger name, etc)
     * @return string
     */
    public static function required_settings_for_trigger( $post_id, $trigger_details ) {
        $trigger_id = $trigger_details['trigger_id'];
        $trigger = $trigger_details['data_trigger'];

        // get workflow content from post id
        $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
        $current_trigger = Utils::find_workflow_item_by_id( $workflow_content, $trigger_id );
        $trigger_data = isset( $current_trigger['data'] ) ? $current_trigger['data'] : array();

        $html = '';

        if ( $trigger === 'woocommerce_order_status_changed' ) {
            $current_status = isset( $trigger_data['settings']['order_status'] ) ? $trigger_data['settings']['order_status'] : null;

            $html .= '<div class="joinotify-get-order-status-trigger">';
                $html .= '<label class="form-label" for="woocommerce_order_status">'. esc_html__( 'Status do pedido', 'joinotify' ) .'</label>';
                
                $html .= '<select id="woocommerce_order_status" class="form-select set-trigger-settings order-status">';
                    $html .= '<option value="none">' . esc_html__( 'Qualquer status', 'joinotify' ) . '</option>';
                    
                    foreach ( wc_get_order_statuses() as $status_key => $status_label ) {
                        $html .= '<option value="'. esc_attr( $status_key ) .'" '. selected( $current_status, $status_key, false) .'>'. esc_html( $status_label ) .'</option>';
                    }
                $html .= '</select>';
            $html .= '</div>';
        } elseif ( $trigger === 'wpforms_process_complete' || $trigger === 'wpforms_paypal_standard_process_complete' ) {
            $current_form_id = isset( $trigger_data['settings']['form_id'] ) ? $trigger_data['settings']['form_id'] : null;

            $html .= '<div class="joinotify-get-form-id-trigger">';
                $html .= '<label class="form-label" for="get_wpforms_form_id">'. esc_html__( 'Formulário do WPForms: *', 'joinotify' ) .'</label>';

                $html .= '<select id="get_wpforms_form_id" class="form-select set-trigger-settings wpforms-form-id required-setting">';
                    $html .= '<option value="none">'. esc_html__( 'Selecione um formulário', 'joinotify' ) .'</option>';
                    
                    // get all wpforms forms
                    $forms = \MeuMouse\Joinotify\Integrations\Wpforms::get_forms();

                    foreach ( $forms as $form ) {
                        $html .= '<option value="'. esc_attr( $form['ID'] ) .'" '. selected( $current_form_id, $form['ID'], false ) .'>'. esc_html( $form['title'] ) .'</option>';
                    }
                $html .= '</select>';
            $html .= '</div>';
        }

        return $html;
    }


    /**
     * Get workflow connector between actions and steps
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @param string $type | Connector type
     * @param array $workflow_data | Workflow array data
     * @return string
     */
    public static function workflow_connector_component( $post_id, $type, $workflow_data ) {
        $html = '';

        if ( $type === 'connector_add' ) {
            $html .= '<div class="funnel_block_item_add">';
                $html .= '<div class="funnel_add_action_wrapper funnel_show_plus">';
                    $html .= '<div class="funnel_add_action">';
                        $html .= '<div class="plusminus"></div>';
                $html .= '</div>';
            $html .= '</div>';
        } elseif ( $type === 'connector' ) {
            $html .= '<div class="funnel_block_item_connector">';
                $html .= '<div class="timeline"></div>';
            $html .= '</div>';
        } elseif ( $type === 'connector_condition' ) {
            $action_id = isset( $workflow_data['parent_id'] ) ? $workflow_data['parent_id'] : '';
            $html .= self::build_condition_connector( $post_id, $action_id );
        }

        return $html;
    }


    /**
     * Build condition connector for workflow content
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @param string $action_id | Action ID
     * @return string
     */
    public static function build_condition_connector( $post_id, $action_id ) {
        $condition_data = Conditions::find_condition_by_id( $post_id, $action_id );

        if ( empty( $condition_data ) ) {
            return '';
        }

        $html = '<div class="funnel_block_item_condition" data-condition-id="'. esc_attr( $action_id ) .'">';
            $html .= '<div class="d-flex justify-content-between w-100">';
                // condition false
                $html .= '<div class="joinotify_condition_node_point condition_false">';
                    $html .= '<span>'. esc_html__( 'Falso', 'joinotify' ) .'</span>';
    
                    $html .= '<div class="add_condition_inside_node_point condition_false">';
                        // Iterate and add actions for condition false
                        if ( isset( $condition_data['children']['action_false'] ) && is_array( $condition_data['children']['action_false'] ) ) {
                            foreach ( $condition_data['children']['action_false'] as $false_action ) {
                                $action_false_title = isset( $false_action['data']['title'] ) ? $false_action['data']['title'] : '';
                                $action_false_description = isset( $false_action['data']['description'] ) ? $false_action['data']['description'] : '';

                                $html .= self::workflow_action_children_component( $post_id, $false_action, $action_false_title, $action_false_description );
                            }
                        }
    
                        // button for add new action on condition false
                        $html .= '<button class="funnel_add_action btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center" data-action="condition" data-condition="false" data-action-id="'. esc_attr( $action_id ) .'">';
                            $html .= '<svg class="icon icon-dark me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" d="M12 3.5v17m8.5-8.5h-17"/></svg>';
                            $html .= esc_html__( 'Adicionar ação', 'joinotify' );
                        $html .= '</button>';
                    $html .= '</div>';
                $html .= '</div>';
    
                // condition true
                $html .= '<div class="joinotify_condition_node_point condition_true">';
                    $html .= '<span>'. esc_html__( 'Verdadeiro', 'joinotify' ) .'</span>';
    
                    $html .= '<div class="add_condition_inside_node_point condition_true">';
                        // iterate and add actions for condition true
                        if ( isset( $condition_data['children']['action_true'] ) && is_array( $condition_data['children']['action_true'] ) ) {
                            foreach ( $condition_data['children']['action_true'] as $true_action ) {
                                $action_true_title = isset( $true_action['data']['title'] ) ? $true_action['data']['title'] : '';
                                $action_true_description = isset( $true_action['data']['description'] ) ? $true_action['data']['description'] : '';

                                $html .= self::workflow_action_children_component( $post_id, $true_action, $action_true_title, $action_true_description );
                            }
                        }
    
                        // button for add new action on condition true
                        $html .= '<button class="funnel_add_action btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center" data-action="condition" data-condition="true" data-action-id="'. esc_attr( $action_id ) .'">';
                            $html .= '<svg class="icon icon-dark me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" d="M12 3.5v17m8.5-8.5h-17"/></svg>';
                            $html .= esc_html__( 'Adicionar ação', 'joinotify' );
                        $html .= '</button>';
                    $html .= '</div>';
                $html .= '</div>';
            $html .= '</div>';
    
            $html .= '<div class="end_condition_wrapper"></div>';
        $html .= '</div>';
    
        return $html;
    }


    /**
     * Render condition selectors for condition builder offcanvas
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $conditions | Condition key
     * @return string
     */
    public static function render_condition_selectors( $conditions ) {
        $html = '<div class="condition-group">';
            foreach ( $conditions as $condition => $value ) {
                $html .= '<div class="condition-item ' . ( $condition === 'no_action' ? 'locked' : '' ) . '" data-condition="'. esc_attr( $condition ) .'">';
                    $html .= '<span class="title">'. esc_html( $value['title'] ) .'</span>';
                    $html .= '<span class="description">'. esc_html( $value['description'] ) .'</span>';
                $html .= '</div>';

                $html .= '<div class="condition-settings-item" data-condition="'. esc_attr( $condition ) .'">';
                    $html .= self::render_condition_settings( $condition );
                $html .= '</div>';
            }
        $html .= '</div>';

        return $html;
    }


    /**
     * Render condition settings for specific action
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $condition | Condition key
     * @param array $settings | Settings for condition
     * @return string HTML of rendered condition settings
     */
    public static function render_condition_settings( $condition, $settings = array() ) {
        $html = '';
        $condition_settings = $settings['type'] ?? '';
        $condition_value = $settings['value'] ?? '';

        // add condition options
        $html .= self::get_condition_options( $condition, $condition_settings );

        switch ( $condition ) {
            case 'user_role':
                global $wp_roles;
            
                $roles = $wp_roles->get_names();

                $html .= '<div class="mb-4 user-role-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Função do usuário: *', 'joinotify') . '</label>';
                    $html .= '<select class="form-control get-condition-value required-setting">';
                
                    foreach ( $roles as $role_key => $role_name ) {
                        $translated_role_name = translate_user_role( $role_name );

                        $html .= '<option value="'. esc_attr( $role_key ) .'" '. selected( $condition_value, $role_key, false ) .'>'. esc_html( $translated_role_name ) .'</option>';
                    }
                
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'user_meta':
                $html .= '<div class="mb-4 meta-key-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Chave: *', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $settings['meta_key'] ?? '' ) .'" placeholder="'. esc_attr__( 'Chave do meta dado', 'joinotify' ) .'">';
                $html .= '</div>';

                $html .= '<div class="mb-4 meta-value-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Valor: *', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Valor do meta dado', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'post_type':
                $html .= '<div class="mb-4 post-type-wrapper">';
                    // Retrieves all registered post types
                    $post_types = get_post_types( array( 'public' => true ), 'objects' );

                    $html .= '<label class="form-label">' . esc_html__('Tipo de post: *', 'joinotify') . '</label>';
                    
                    $html .= '<select class="form-control get-post-type get-condition-value required-setting">';
                        $html .= '<option value="none">' . esc_html__( 'Selecione um tipo de post', 'joinotify' ) . '</option>';

                        foreach ( $post_types as $post_type_key => $post_type_object ) {
                            $html .= '<option value="' . esc_attr( $post_type_key ) . '" '. selected( $condition_value, $post_type_key, false ) .'>' . esc_html( $post_type_object->labels->name ) . '</option>';
                        }
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'post_author':
                $html .= '<div class="mb-4 post-author-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Autor do post: *', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value post-author" placeholder="'. esc_attr__( 'Nome do autor', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'order_status':
                $html .= '<div class="mb-4 order-status-wrapper">';
                    // Retrieves all WooCommerce order statuses
                    if ( function_exists('wc_get_order_statuses') ) {
                        $order_statuses = wc_get_order_statuses();

                        $html .= '<label class="form-label">' . esc_html__('Status do pedido: *', 'joinotify') . '</label>';
                        
                        $html .= '<select class="form-control get-condition-value required-setting">';
                            $html .= '<option value="none">' . esc_html__( 'Selecione um status', 'joinotify' ) . '</option>';

                            foreach ( $order_statuses as $status_key => $status_name ) {
                                $html .= '<option value="' . esc_attr( $status_key ) . '" '. selected( $condition_value, $status_key, false ) .'>' . esc_html( $status_name ) . '</option>';
                            }
                        $html .= '</select>';
                    }
                $html .= '</div>';

                break;
            case 'order_total':
                $html .= '<div class="mb-4 order-total-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Valor total do pedido: *', 'joinotify') . '</label>';

                    $html .= '<div class="input-group">';
                        if ( function_exists('get_woocommerce_currency_symbol') ) {
                            $html .= '<span class="input-group-text">'. get_woocommerce_currency_symbol() .'</span>';
                        }

                        $html .= '<input type="text" class="form-control get-condition-value format-currency required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Valor total do pedido', 'joinotify' ) .'">';
                    $html .= '</div>';
                $html .= '</div>';

                break;
            case 'order_paid':

                break;
            case 'products_purchased':
                $html .= '<div class="mb-4 search-products-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Produtos adquiridos:', 'joinotify') . '</label>';

                    // create a JSON with the selected products
                    $selected_products = isset( $settings['products'] ) ? json_encode( $settings['products'] ) : '[]';

                    $html .= '<select class="get-condition-value search-products" multiple data-selected-products="'. esc_attr( $selected_products ) .'" placeholder="'. esc_attr__( 'Pesquise os produtos que deseja incluir', 'joinotify' ) .'">';
                        if ( isset( $settings['products'] ) && is_array( $settings['products'] ) ) {
                            foreach ( $settings['products'] as $product ) {
                                $html .= '<option value="'. esc_attr( $product['id'] ) .'">'. esc_html( $product['title'] ) .'</option>';
                            }
                        }
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'payment_method':
                $html .= '<div class="mb-4 payment-method-wrapper">';;
                    // Retrieves all WooCommerce payment gateways
                    if ( function_exists('WC') ) {
                        $html .= '<label class="form-label">' . esc_html__('Método de pagamento: *', 'joinotify') . '</label>';

                        $html .= '<select class="form-control get-condition-value required-setting">';
                            $html .= '<option value="none">' . esc_html__( 'Selecione um método de pagamento', 'joinotify' ) . '</option>';
                            
                            foreach ( WC()->payment_gateways->payment_gateways() as $payment_gateway_key => $payment_gateway_object ) {
                                $html .= '<option value="' . esc_attr( $payment_gateway_key ) . '" '. selected( $condition_value, $payment_gateway_key, false ) .'>' . esc_html( $payment_gateway_object->title ) . '</option>';
                            }
                        $html .= '</select>';
                    }
                $html .= '</div>';

                break;
            case 'shipping_method':
                $html .= '<div class="mb-4 shipping-method-wrapper">';
                    // Retrieves all WooCommerce shipping methods
                    if ( function_exists('WC') ) {
                        $html .= '<label class="form-label">' . esc_html__('Método de entrega: *', 'joinotify') . '</label>';

                        $html .= '<select class="form-control get-condition-value required-setting">';
                            $html .= '<option value="none">' . esc_html__( 'Selecione um método de entrega', 'joinotify' ) . '</option>';

                            foreach ( WC()->shipping->get_shipping_methods() as $shipping_method_key => $shipping_method_object ) {
                                $html .= '<option value="' . esc_attr( $shipping_method_key ) . '" '. selected( $condition_value, $shipping_method_key, false ) .'>' . esc_html( $shipping_method_object->method_title ) . '</option>';
                            }
                        $html .= '</select>';
                    }
                $html .= '</div>';

                break;
            case 'customer_email':
                $html .= '<div class="mb-4 customer-email-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('E-mail do cliente:', 'joinotify') . '</label>';
                    $html .= '<input type="email" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'E-mail do cliente', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'refund_amount':
                $html .= '<div class="mb-4 refund-amount-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Valor do reembolso: *', 'joinotify') . '</label>';

                    $html .= '<div class="input-group">';
                        if ( function_exists('get_woocommerce_currency_symbol') ) {
                            $html .= '<span class="input-group-text">'. get_woocommerce_currency_symbol() .'</span>';
                        }
                        
                        $html .= '<input type="text" class="form-control get-condition-value format-currency required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Valor do reembolso', 'joinotify' ) .'">';
                    $html .= '</div>';
                $html .= '</div>';

                break;
            case 'subscription_status':
                $html .= '<div class="mb-4 subscription-status-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Status da assinatura: *', 'joinotify') . '</label>';
                    
                    $html .= '<select class="form-control get-condition-value required-setting">';
                        $html .= '<option value="active" '. selected( $condition_value, 'active', false ) .'>' . esc_html__( 'Ativa', 'joinotify' ) . '</option>';
                        $html .= '<option value="on-hold" '. selected( $condition_value, 'on-hold', false ) .'>' . esc_html__( 'Em espera', 'joinotify' ) . '</option>';
                        $html .= '<option value="cancelled" '. selected( $condition_value, 'cancelled', false ) .'>' . esc_html__( 'Cancelada', 'joinotify' ) . '</option>';
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'renewal_payment':
                $html .= '<div class="mb-4 renewal-payment-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Pagamento da renovação: *', 'joinotify') . '</label>';
                    
                    $html .= '<select class="form-control get-condition-value required-setting">';
                        $html .= '<option value="yes" '. selected( $condition_value, 'yes', false ) .'>' . esc_html__( 'Sim', 'joinotify' ) . '</option>';
                        $html .= '<option value="no" '. selected( $condition_value, 'no', false ) .'>' . esc_html__( 'Não', 'joinotify' ) . '</option>';
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'cart_total':
                $html .= '<div class="mb-4 cart-total-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Valor total do carrinho:', 'joinotify') . '</label>';
                    $html .= '<input type="number" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Valor total do carrinho', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'items_in_cart':
                $html .= '<div class="mb-4 search-products-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Produtos no carrinho:', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value search-products" placeholder="'. esc_attr__( 'Pesquise os produtos que deseja incluir', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'field_value':
                $html .= '<div class="mb-4 field-id-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('ID do campo:', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $settings['field_id'] ?? '' ) .'" placeholder="'. esc_attr__( 'ID do campo', 'joinotify' ) .'">';
                $html .= '</div>';

                $html .= '<div class="mb-4 field-value-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Valor do campo:', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Valor do campo', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            default:
                $html .= '<p class="alert alert-info">' . esc_html__( 'Nenhuma configuração disponível para esta condição', 'joinotify' ) . '</p>';
                
                break;
        }

        return $html;
    }


    /**
     * Get condition options
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $condition | Condition key
     * @param string $condition_value | Condition value
     * @return string
     */
    public static function get_condition_options( $condition, $condition_value = '' ) {
        $condition_options = array(
            'is' => esc_html__( 'É', 'joinotify' ),
            'is_not' => esc_html__( 'Não é', 'joinotify' ),
            'empty' => esc_html__( 'Vazio', 'joinotify' ),
            'not_empty' => esc_html__( 'Não está vazio', 'joinotify' ),
            'contains' => esc_html__( 'Contém', 'joinotify' ),
            'not_contain' => esc_html__( 'Não contém', 'joinotify' ),
            'start_with' => esc_html__( 'Começa com', 'joinotify' ),
            'finish_with' => esc_html__( 'Termina com', 'joinotify' ),
            'bigger_than' => esc_html__( 'Maior que', 'joinotify' ),
            'less_than' => esc_html__( 'Menor que', 'joinotify' ),
        );

        $html = '<div class="mb-4">';
            $html .= '<label class="form-label">'. esc_html__( 'Condição: *', 'joinotify' ) .'</label>';
            $html .= '<select class="form-select get-condition-type required-setting">';
                $html .= '<option value="none">'. esc_html__( 'Selecione uma condição', 'joinotify' ) .'</option>';

                $allowed_conditions = Conditions::check_condition_type( $condition );

                foreach ( $condition_options as $option => $value ) {
                    if ( in_array( $option, $allowed_conditions ) ) {
                        $html .= '<option value="'. esc_attr( $option ) .'" '. selected( $option, $condition_value, false ) .'>'. $value .'</option>';
                    }
                }
            $html .= '</select>';
        $html .= '</div>';

        return $html;
    }


    /**
     * Render placeholders list for workflow trigger
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @return string
     */
    public static function render_placeholders_list( $post_id = 0 ) {
        $accordion_id = uniqid('whatsapp_msg_text_placeholder_');
        $accordion_container_id = uniqid('whatsapp_msg_text_placeholder_collapse_');

        $html = '<div class="accordion" id="'. esc_attr( $accordion_id ) .'">';
            $html .= '<div class="accordion-item">';
                $html .= '<h2 class="accordion-header fw-normal">';
                    $html .= '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#'. esc_attr( $accordion_container_id ) .'" aria-expanded="true">'. esc_html__( 'Variáveis de texto', 'joinotify' ) .'</button>';
                $html .= '</h2>';

                $html .= '<div id="'. esc_attr( $accordion_container_id ) .'" class="accordion-collapse collapse" data-bs-parent="#'. esc_attr( $accordion_id ) .'">';
                    $html .= '<div class="accordion-body pe-0 pt-1">';
                        $html .= '<div class="mt-3 placeholders-list">';
                            $trigger = Triggers::get_trigger_from_post( $post_id );
                            $integration = Utils::get_context_from_post( $post_id );

                            // get filtered placeholders
                            $placeholders = Placeholders::get_placeholders_list( $integration, $trigger );

                            // iterate over placeholders
                            foreach( $placeholders as $placeholder => $value ) {
                                $html .= '<div class="d-grid mb-3">';
                                    $html .= '<span class="fs-sm fs-italic"><code>'. esc_html( $placeholder ) .'</code></span>';
                                    $html .= '<span class="fs-sm mt-1">'. esc_html( $value['description'] ) .'</span>';
                                $html .= '</div>';
                            }
                        $html .= '</div>';
                    $html .= '</div>';
                $html .= '</div>';
            $html .= '</div>';
        $html .= '</div>';

        return $html;
    }


    /**
     * Add modal content for edit workflow title
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public static function workflow_title_modal_content() {
        if ( joinotify_check_admin_page('joinotify-workflows-builder') ) : ?>
            <div class="modal fade" id="edit_workflow_title" tabindex="-1" aria-labelledby="edit_workflow_title_label">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-bottom-0">
                            <h1 class="modal-title fs-5" id="edit_workflow_title_label"><?php esc_html_e( 'Editar título do fluxo', 'joinotify' ) ?></h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ) ?>"></button>
                        </div>

                        <div class="modal-body">
                            <input type="text" id="joinotify_edit_workflow_title" class="form-control" value="" placeholder="<?php esc_attr_e( 'Título do seu fluxo', 'joinotify' ) ?>"/>
                        </div>
                        
                        <div class="modal-footer border-top-0">
                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'joinotify' ) ?></button>
                            <button type="button" id="joinotify_update_workflow_title" class="btn btn-sm btn-primary"><?php esc_html_e( 'Salvar alterações', 'joinotify' ) ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif;
    }


    /**
     * Add modal trigger for fetch all groups
     * 
     * @since 1.1.0
     * @return void
     */
    public static function fetch_all_groups_modal_trigger() {
        return '<button id="joinotify_fetch_all_groups" class="btn btn-outline-secondary d-block w-100 my-3" role="button" data-bs-toggle="modal" data-bs-target="#joinotify_fetch_all_groups_container">'. esc_html__( 'Obter informações de grupos', 'joinotify' ) .'</button>';
    }


    /**
     * Add modal content for fetch all groups
     * 
     * @since 1.1.0
     * @return void
     */
    public static function fetch_all_groups_modal_content() {
        if ( joinotify_check_admin_page('joinotify-workflows-builder') ) : ?>
            <div class="modal fade" id="joinotify_fetch_all_groups_container" tabindex="-1" aria-labelledby="joinotify_fetch_all_groups_label">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="joinotify_fetch_all_groups_label"><?php esc_html_e( 'Obter informações de grupos', 'joinotify' ) ?></h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ) ?>"></button>
                        </div>

                        <div class="modal-body my-3 text-start">
                            <div class="placeholder-content" style="width: 100%; height: 10rem;"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif;
    }


    /**
     * Render time delay settings on sidebar action
     * 
     * @since 1.1.0
     * @param array $settings | Current settings
     * @return string
     */
    public static function time_delay_action( $settings = array() ) {
        ob_start();

        if ( Schedule::is_wp_cron_active() ) : ?>
            <div class="mb-4">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Selecione o tipo de atraso da próxima ação', 'joinotify' ) ?></span>
                
                <select class="form-select set-time-delay-type">
                    <option value="period" <?php selected( $settings['delay_type'] ?? '', 'period' ) ?>><?php esc_html_e( 'Esperar tempo', 'joinotify' ) ?></option>
                    <option value="date" <?php selected( $settings['delay_type'] ?? '', 'date' ) ?>><?php esc_html_e( 'Esperar até uma data', 'joinotify' ) ?></option>
                </select>
            </div>

            <div class="wait-time-period-container">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Esperar por', 'joinotify' ) ?></span>
                
                <div class="input-group">
                    <input type="number" class="form-control get-wait-value" value="<?php echo $settings['delay_value'] ?? '' ?>"/>

                    <select class="form-select get-wait-period">
                        <?php foreach ( self::get_time_delay_options() as $option => $title ) : ?>
                            <option value="<?php esc_attr_e( $option ) ?>" <?php selected( $settings['delay_period'] ?? '', $option ) ?>><?php esc_html_e( $title ) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="wait-date-container d-none">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Esperar até', 'joinotify' ) ?></span>
                
                <div class="input-group">
                    <input type="text" class="form-control dateselect get-date-value" value="<?php echo $settings['date_value'] ?? '' ?>" placeholder="<?php esc_attr_e( 'Selecione uma data', 'joinotify' ) ?>"/>
                    <input type="time" class="form-control get-time-value" value="<?php echo $settings['time_value'] ?? '' ?>"/>
                </div>
            </div>
        <?php else : ?>
            <div class="alert alert-warning d-flex align-items-center">
                <svg class="icon icon-lg icon-warning me-2 w-25" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11.001 10h2v5h-2zM11 16h2v2h-2z"/><path d="M13.768 4.2C13.42 3.545 12.742 3.138 12 3.138s-1.42.407-1.768 1.063L2.894 18.064a1.986 1.986 0 0 0 .054 1.968A1.984 1.984 0 0 0 4.661 21h14.678c.708 0 1.349-.362 1.714-.968a1.989 1.989 0 0 0 .054-1.968L13.768 4.2zM4.661 19 12 5.137 19.344 19H4.661z"/></svg>
                <?php esc_html_e( 'A função WP-CRON está desabilitada neste site. Ative-o para usar a ação Tempo de espera.', 'joinotify' ) ?>
            </div>
        <?php endif;

        return ob_get_clean();
    }


    /**
     * Render Snippet PHP component
     * 
     * @since 1.1.0
     * @param array $settings | Current settings
     * @return string
     */
    public static function snippet_php_action( $settings = array() ) {
        ob_start();

        // display alert on sidebar, because is calling without settings param
        if ( empty( $settings ) ) : ?>
            <div class="alert alert-warning alert-dismissible fade show mb-4">
                <svg class="icon icon-lg me-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#664d03" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path fill="#664d03" d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
                <?php echo __( '<strong>Atenção!</strong> O uso incorreto de Snippets PHP pode causar erros no site.', 'joinotify' ) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <textarea class="form-control joinotify-code-editor required-setting"><?php echo $settings['snippet_php'] ?? ''; ?></textarea>

        <?php return ob_get_clean();
    }


    /**
     * Get actions filtered by context
     * 
     * @since 1.1.0
     * @param string $context | Trigger context
     * @return string
     */
    public static function get_filtered_actions( $context = '' ) {
        ob_start();

        // iterate for each action
        foreach ( Actions::get_all_actions( $context ) as $action ) : ?>
            <div class="action-item-container <?php esc_attr_e( $action['action'] ) ?>" style="order: <?php esc_attr_e( $action['priority'] ) ?>;">
                <!-- ACTION CARD START -->
                <div class="action-item <?php isset( $action['class'] ) ? esc_attr_e( $action['class'] ) : ''; ?>" data-action="<?php esc_attr_e( $action['action'] ) ?>" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_<?php esc_attr_e( $action['action'] ) ?>">
                    <div class="d-flex align-items-center">
                        <?php if ( ! $action['external_icon'] ) : ?>
                            <div class="action-item-icon me-3"><?php echo $action['icon'] ?></div>
                        <?php endif; ?>

                        <div class="d-grid">
                            <span class="title mb-1"><?php esc_html_e( $action['title'] ) ?></span>
                            <span class="description"><?php esc_html_e( $action['description'] ) ?></span>
                        </div>
                    </div>
                </div>
                <!-- ACTION CARD END -->

                <!-- SIDEBAR SETTINGS -->
                <div class="offcanvas offcanvas-end" data-action="<?php esc_attr_e( $action['action'] ) ?>" data-bs-scroll="false" data-bs-backdrop="false" tabindex="-1" id="offcanvas_<?php esc_attr_e( $action['action'] ) ?>" aria-labelledby="offcanvas_<?php esc_attr_e( $action['action'] ) ?>_label">
                    <!-- SIDEBAR ACTION SETTINGS START -->
                    <div class="offcanvas-header px-4 py-lg-1 py-xxl-3 mt-2 border-bottom justify-content-between">
                        <div class="d-flex align-items-center">
                            <?php if ( ! $action['external_icon'] ) : ?>
                                <div class="action-item-icon me-3"><?php echo $action['icon'] ?></div>
                            <?php endif; ?>

                            <h5 class="offcanvas-title" id="offcanvas_<?php esc_attr_e( $action['action'] ) ?>_label"><?php esc_html_e( $action['title'] ) ?></h5>
                        </div>

                        <div class="d-flex align-items-center">
                            <?php if ( isset( $action['is_expansible'] ) && $action['is_expansible'] === true ) : ?>
                                <button type="button" class="btn ms-3 px-2 btn-link expand-offcanvas" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Expandir', 'joinotify' ) ?>" aria-label="<?php esc_attr_e( 'Expandir', 'joinotify' ) ?>">
                                    <svg class="icon icon-dark icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M8.29 5.64 1.93 12l6.36 6.36 1.42-1.41L4.76 12l4.95-4.95-1.42-1.41zm6 1.41L19.24 12l-4.95 4.95 1.42 1.41L22.07 12l-6.36-6.36-1.42 1.41z"></path></svg>
                                </button>

                                <button type="button" class="btn ms-3 px-2 btn-link collapse-offcanvas d-none" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Retrair', 'joinotify' ) ?>" aria-label="<?php esc_attr_e( 'Retrair', 'joinotify' ) ?>">
                                    <svg class="icon icon-dark icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.95 5.64 13.59 12l6.36 6.36 1.41-1.41L16.41 12l4.95-4.95-1.41-1.41zM2.64 7.05 7.59 12l-4.95 4.95 1.41 1.41L10.41 12 4.05 5.64 2.64 7.05z"></path></svg>
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ) ?>"></button>
                        </div>
                    </div>
                    <!-- SIDEBAR ACTION SETTINGS END -->

                    <div class="offcanvas-body p-4 py-5">
                        <?php if ( isset( $action['has_settings'] ) && $action['has_settings'] ) : ?>
                            <?php echo $action['settings']; ?>
                        <?php else : ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <svg class="icon icon-lg icon-info me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
                                <?php esc_html_e( 'Esta ação não necessita de configurações auxiliares.', 'joinotify' ) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="offcanvas-footer px-4 py-lg-2 py-xxl-3 d-flex justify-content-end">
                        <button id="add_action_<?php esc_attr_e( $action['action'] ) ?>" class="btn btn-primary add-funnel-action" disabled data-action="<?php esc_attr_e( $action['action'] ) ?>"><?php esc_html_e( 'Adicionar ação', 'joinotify' ) ?></button>
                    </div>
                </div>
            </div>
        <?php endforeach;

        return ob_get_clean();
    }


    /**
     * Render the condition products
     * 
     * @since 1.1.0
     * @param array $settings | The condition settings
     * @return string
     */
    public static function render_condition_products( $settings = array() ) {
        $html = '<ul class="list-group search-products-results">';
            foreach( $settings['products'] as $product ) {
                $html .= '<li class="list-group-item product-item" data-product-id="' . get_the_ID() . '">' . get_the_title() . '</li>';
            }
        $html .= '</ul>';

        return $html;
    }


    /**
     * Render  the coupon placeholders
     * 
     * @since 1.1.0
     * @return string
     */
    public static function render_coupon_placeholders() {
        $placeholders = Woocommerce::get_coupon_placeholders();

        $html = '';

        // iterate for each placeholder
        foreach( $placeholders as $placeholder => $value ) {
            $html .= '<div class="d-grid mb-3">';
                $html .= '<span class="fs-sm fs-italic"><code>'. esc_html( $placeholder ) .'</code></span>';
                $html .= '<span class="fs-sm mt-1">'. esc_html( $value['description'] ) .'</span>';
            $html .= '</div>';
        }

        return $html;
    }
}