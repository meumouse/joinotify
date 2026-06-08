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
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Builder
 * @author MeuMouse.com
 */
class Components {

    /**
     * Display workflow action component
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $post_id | Post ID
     * @param array $action_details | Action details array (action name, description, id, etc)
     * @return string
     */
    public static function workflow_action_component( $post_id, $action_details ) {
        foreach ( Actions::get_all_actions() as $action => $value ) {
            $action_id = $action_details['id'];
    
            // Skip if it's not the correct action
            if ( $action_details['action_name'] !== $value['action'] ) {
                continue;
            }
    
            $html = '<div class="funnel-action-item" data-action="'. esc_attr( $value['action'] ) .'" data-action-id="'. esc_attr( $action_details['id'] ) .'">';
                $html .= '<div class="action-item-body">';
    
                    // Action title
                    if ( isset( $action_details['title'] ) && ! empty( $action_details['title'] ) && $action_details['type'] === 'condition' ) {
                        $html .= '<h4 class="title">'. sprintf( esc_html__( 'Condition: %s', 'joinotify' ), $action_details['title'] ) .'</h4>';
                    } else {
                        $html .= '<h4 class="title">'. $value['title'] .'</h4>';
                    }
    
                    // Message sender and receiver details
                    if ( $value['action'] === 'send_whatsapp_message_text' || $value['action'] === 'send_whatsapp_message_media' ) {
                        $html .= '<span class="text-muted fs-xs sender d-block">'. sprintf( __( 'Sender: %s', 'joinotify' ), $action_details['sender'] ) .'</span>';
                        $html .= '<span class="text-muted fs-xs receiver d-block mb-2">'. sprintf( __( 'Recipient: %s', 'joinotify' ), $action_details['receiver'] ) .'</span>';
                    }
    
                    // Action description
                    if ( empty( $action_details['description'] ) ) {
                        $html .= '<span class="description">'. $value['description'] .'</span>';
                    } else {
                        $html .= '<span class="description">'. $action_details['description'] .'</span>';
                    }
                $html .= '</div>';
    
                // Button group
                $html .= '<div class="btn-group">';
                    $html .= '<div class="funnel-action-cta icon-translucent btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false"><svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 12c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg></div>';
                    
                    $html .= '<div class="funnel-action-details">';
                        $html .= '<ul class="dropdown-menu builder-dropdown shadow-sm">';
                            $html .= '<li class="d-flex align-items-center mb-0">';
                                $html .= '<a id="exclude_action_'. esc_attr( $action_id ) .'" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 exclude-action" data-action-id="'. esc_attr( $action_id ) .'" href="#">';
                                    $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15 2H9c-1.103 0-2 .897-2 2v2H3v2h2v12c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2V8h2V6h-4V4c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 16H7V8h10v12z"></path></svg>';
                                    $html .= esc_html__( 'Delete action', 'joinotify' );
                                $html .= '</a>';
                            $html .= '</li>';
                            
                            if ( $value['action'] === 'condition' ) {
                                $html .= '<li class="d-flex align-items-center mb-0">';
                                    $html .= '<a id="edit_condition_'. esc_attr( $action_id ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-action" href="#" data-bs-toggle="modal" data-bs-target="#edit_condition_'. esc_attr( $action_id ) .'">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                        $html .= esc_html__( 'Edit condition', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';
                            } elseif ( $value['action'] !== 'condition' && $value['action'] !== 'stop_funnel' ) {
                                $html .= '<li class="d-flex align-items-center mb-0">';
                                    $html .= '<a id="edit_action_'. esc_attr( $action_id ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-action" href="#" data-bs-toggle="modal" data-bs-target="#edit_action_'. esc_attr( $action_id ) .'">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                        $html .= esc_html__( 'Edit action', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';
                            }
                        $html .= '</ul>';
                    $html .= '</div>';
                $html .= '</div>'; // Closing .btn-group
    
                // Display modal settings for condition action
                if ( $value['action'] === 'condition' ) {
                    $html .= self::render_condition_modal( $post_id, $action_id );
                } elseif ( $value['action'] !== 'condition' && $value['action'] !== 'stop_funnel' ) {
                    $html .= self::render_action_modal( $post_id, $value['action'], $action_id );
                }
            $html .= '</div>'; // Closing .funnel-action-item
    
            return $html;
        }
    }
    
    
    /**
     * Render modal for condition settings
     * 
     * @since 1.2.2
     * @param int $post_id | Post id
     * @param int $action_id | Action ID
     * @return string $html | HTML code
     */
    public static function render_condition_modal( $post_id, $action_id ) {
        $html = '<div class="modal fade" id="edit_condition_'. esc_attr( $action_id ) .'" tabindex="-1" aria-labelledby="edit_condition_'. esc_attr( $action_id ) .'_label">';
            $html .= '<div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">';
                $html .= '<div class="modal-content">';
                    $html .= '<div class="modal-header px-4">';
                        $html .= '<h3 class="modal-title fs-5">'. esc_html__( 'Configure condition', 'joinotify' ) .'</h3>';
                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                    $html .= '</div>';

                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                        $get_condition = Conditions::get_condition_content( $post_id, $action_id );
                        $html .= self::render_condition_settings( $get_condition['condition_content']['condition'], $get_condition );
                    $html .= '</div>';

                    $html .= '<div class="modal-footer px-4">';
                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancel', 'joinotify' ) .'</button>';
                        $html .= '<button type="button" class="btn btn-primary save-action-edit m-0" data-action="condition" data-action-id="'. esc_attr( $action_id ) .'">'. esc_html__( 'Save changes', 'joinotify' ) .'</button>';
                    $html .= '</div>';
                $html .= '</div>';
            $html .= '</div>';
        $html .= '</div>';
    
        return $html;
    }

    
    /**
     * Render modal for action settings
     * 
     * @since 1.2.2
     * @param int $post_id | Post ID
     * @param string $action | Action type
     * @param int $action_id | Action ID
     * @return string $html | HTML string
     */
    public static function render_action_modal( $post_id, $action, $action_id ) {
        $html = '<div class="modal fade" id="edit_action_'. esc_attr( $action_id ) .'" tabindex="-1" aria-labelledby="edit_action_'. esc_attr( $action_id ) .'_label">';
            $html .= '<div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">';
                $html .= '<div class="modal-content">';
                    $html .= '<div class="modal-header px-4">';
                        $html .= '<h3 class="modal-title fs-5">'. esc_html__( 'Configure action', 'joinotify' ) .'</h3>';
                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                    $html .= '</div>';

                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                        $html .= self::get_action_settings( $post_id, $action, $action_id );
                    $html .= '</div>';

                    $html .= '<div class="modal-footer px-4">';
                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancel', 'joinotify' ) .'</button>';
                        $html .= '<button type="button" class="btn btn-primary save-action-edit m-0" data-action="'. esc_attr( $action ) .'" data-action-id="'. esc_attr( $action_id ) .'">'. esc_html__( 'Save changes', 'joinotify' ) .'</button>';
                    $html .= '</div>';
                $html .= '</div>';
            $html .= '</div>';
        $html .= '</div>';
    
        return $html;
    }


    /**
     * Display action condition component for condition children actions
     * 
     * @since 1.0.0
     * @version 1.4.7
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
                            $html .= '<span class="text-muted fs-xs sender d-block">'. sprintf( __( 'Sender: %s', 'joinotify' ), $condition_data['data']['sender'] ) .'</span>';
                            $html .= '<span class="text-muted fs-xs receiver d-block mb-2">'. sprintf( __( 'Recipient: %s', 'joinotify' ), $condition_data['data']['receiver'] ) .'</span>';
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
                                    $html .= '<a id="exclude_action_'. esc_attr( $action ) .'" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 exclude-action" data-action-id="'. esc_attr( $action ) .'" href="#">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15 2H9c-1.103 0-2 .897-2 2v2H3v2h2v12c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2V8h2V6h-4V4c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 16H7V8h10v12z"></path></svg>';
                                        $html .= esc_html__( 'Delete action', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';

                                if ( $condition_data['data']['action'] !== 'stop_funnel' ) {
                                    $html .= '<li class="d-flex align-items-center mb-0">';
                                        $html .= '<a id="edit_action_'. esc_attr( $action ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-action" href="#" data-bs-toggle="modal" data-bs-target="#edit_action_'. esc_attr( $action ) .'" data-action-id="'. esc_attr( $action ) .'">';
                                            $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                            $html .= esc_html__( 'Edit action', 'joinotify' );
                                        $html .= '</a>';
                                    $html .= '</li>';
                                }
                            $html .= '</ul>';
                        $html .= '</div>';
                    $html .= '</div>';

                    // display modal settings for condition action
                    if ( $condition_data['data']['action'] !== 'stop_funnel' ) {
                        $html .= '<div class="modal fade" id="edit_action_'. esc_attr( $action ) .'" tabindex="-1" aria-labelledby="edit_action_'. esc_attr( $action ) .'_label">';
                            $html .= '<div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">';
                                $html .= '<div class="modal-content">';
                                    $html .= '<div class="modal-header px-4">';
                                        $html .= '<h3 class="modal-title fs-5" id="edit_action_'. esc_attr( $action ) .'_label">'. esc_html__( 'Configure action', 'joinotify' ) .'</h3>';
                                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                    $html .= '</div>';

                                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                                        $html .= self::get_action_settings( $post_id, $condition_data['data']['action'], $condition_data['id'] );
                                    $html .= '</div>';

                                    $html .= '<div class="modal-footer px-4">';
                                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancel', 'joinotify' ) .'</button>';
                                        $html .= '<button type="button" class="btn btn-primary save-action-edit m-0" data-action="'. esc_attr( $action_value['action'] ) .'" data-action-id="'. esc_attr( $action ) .'">'. esc_html__( 'Save changes', 'joinotify' ) .'</button>';
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
            'seconds' =>  esc_html__( 'Second(s)', 'joinotify' ),
            'minute' => esc_html__( 'Minute(s)', 'joinotify' ),
            'hours' => esc_html__( 'Hour(s)', 'joinotify' ),
            'day' => esc_html__( 'Day(s)', 'joinotify' ),
            'week' => esc_html__( 'Week(s)', 'joinotify' ),
            'month' => esc_html__( 'Month(s)', 'joinotify' ),
            'year' => esc_html__( 'Year(s)', 'joinotify' ),
        ));
    }


    /**
     * Get each action settings
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $post_id | Post ID
     * @param string $action | Action name
     * @param string $action_id | Action ID
     * @return string
     */
    public static function get_action_settings( $post_id, $action, $action_id ) {
        // get workflow content from post id
        $workflow_content = Helpers::get_workflow_content_meta( $post_id );
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

                // placeholders helper
                $html .= self::render_placeholders_list( $post_id );

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
     * @version 1.4.7
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
                                        $html .= esc_html__( 'Settings', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';
                            $html .= '</ul>';
                        $html .= '</div>';

                        $html .= '<div class="modal fade" id="edit_trigger_'. esc_attr( $trigger_id ) .'" tabindex="-1" aria-labelledby="edit_trigger_'. esc_attr( $trigger_id ) .'_label">';
                            $html .= '<div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">';
                                $html .= '<div class="modal-content">';
                                    $html .= '<div class="modal-header px-4">';
                                        $html .= '<h3 class="modal-title fs-5" id="edit_trigger_'. esc_attr( $trigger_id ) .'_label">'. esc_html__( 'Configure trigger', 'joinotify' ) .'</h3>';
                                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
                                    $html .= '</div>';

                                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                                        $html .= self::required_settings_for_trigger( $post_id, $trigger_details );
                                    $html .= '</div>';

                                    $html .= '<div class="modal-footer px-4 py-3">';
                                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancel', 'joinotify' ) .'</button>';
                                        $html .= '<button type="button" class="btn btn-primary save-trigger-settings m-0" data-trigger="'. esc_attr( $data_trigger ) .'" data-trigger-id="'. esc_attr( $trigger_id ) .'">'. esc_html__( 'Save changes', 'joinotify' ) .'</button>';
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
     * @version 1.4.7
     * @param int $post_id | Post ID
     * @param array $trigger_details | Trigger details (context name, trigger name, etc)
     * @return string
     */
    public static function required_settings_for_trigger( $post_id, $trigger_details ) {
        $trigger_id = $trigger_details['trigger_id'];
        $trigger = $trigger_details['data_trigger'];

        // get workflow content from post id
        $workflow_content = Helpers::get_workflow_content_meta( $post_id );
        $current_trigger = Utils::find_workflow_item_by_id( $workflow_content, $trigger_id );
        $trigger_data = isset( $current_trigger['data'] ) ? $current_trigger['data'] : array();

        $html = '';

        if ( $trigger === 'woocommerce_order_status_changed' ) {
            $current_status = isset( $trigger_data['settings']['order_status'] ) ? $trigger_data['settings']['order_status'] : null;

            $html .= '<div class="joinotify-get-order-status-trigger">';
                $html .= '<label class="form-label" for="woocommerce_order_status">'. esc_html__( 'Order status', 'joinotify' ) .'</label>';
                
                $html .= '<select id="woocommerce_order_status" class="form-select set-trigger-settings order-status">';
                    $html .= '<option value="none">' . esc_html__( 'Any status', 'joinotify' ) . '</option>';
                    
                    foreach ( wc_get_order_statuses() as $status_key => $status_label ) {
                        $html .= '<option value="'. esc_attr( $status_key ) .'" '. selected( $current_status, $status_key, false) .'>'. esc_html( $status_label ) .'</option>';
                    }
                $html .= '</select>';
            $html .= '</div>';
        } elseif ( $trigger === 'wpforms_process_complete' || $trigger === 'wpforms_paypal_standard_process_complete' ) {
            $current_form_id = isset( $trigger_data['settings']['form_id'] ) ? $trigger_data['settings']['form_id'] : null;

            $html .= '<div class="joinotify-get-form-id-trigger">';
                $html .= '<label class="form-label" for="get_wpforms_form_id">'. esc_html__( 'WPForms form: *', 'joinotify' ) .'</label>';

                $html .= '<select id="get_wpforms_form_id" class="form-select set-trigger-settings wpforms-form-id required-setting">';
                    $html .= '<option value="none">'. esc_html__( 'Select a form', 'joinotify' ) .'</option>';
                    
                    // get all wpforms forms
                    $forms = \MeuMouse\Joinotify\Integrations\Wpforms::get_forms();

                    foreach ( $forms as $form ) {
                        $html .= '<option value="'. esc_attr( $form['ID'] ) .'" '. selected( $current_form_id, $form['ID'], false ) .'>'. esc_html( $form['title'] ) .'</option>';
                    }
                $html .= '</select>';
            $html .= '</div>';
        } elseif ( $trigger === 'transition_post_status' ) {
            $current_status = isset( $trigger_data['settings']['post_status'] ) ? $trigger_data['settings']['post_status'] : null;

            $html .= '<div class="joinotify-get-post-status-trigger">';
                $html .= '<label class="form-label" for="wp_post_status">'. esc_html__( 'Post status', 'joinotify' ) .'</label>';
                
                $html .= '<select id="wp_post_status" class="form-select set-trigger-settings post-status">';
                    $html .= '<option value="none">' . esc_html__( 'Any status', 'joinotify' ) . '</option>';
                    
                    foreach ( get_post_statuses() as $status_key => $status_label ) {
                        $html .= '<option value="'. esc_attr( $status_key ) .'" '. selected( $current_status, $status_key, false) .'>'. esc_html( $status_label ) .'</option>';
                    }
                $html .= '</select>';
            $html .= '</div>';
        } elseif ( $trigger === 'elementor_pro/forms/new_record' ) {
            $current_form_id = isset( $trigger_data['settings']['form_id'] ) ? $trigger_data['settings']['form_id'] : '';

            $html .= '<div class="joinotify-get-elementor-form-id-trigger">';
                $html .= '<label class="form-label" for="get_elementor_form_id">'. esc_html__( 'Elementor form: *', 'joinotify' ) .'</label>';
                $html .= '<input id="get_elementor_form_id" type="text" class="form-control set-trigger-settings elementor-form-id required-setting" placeholder="'. esc_attr__( 'Enter the form ID', 'joinotify' ) .'" value="'. esc_attr( $current_form_id ) .'">';
                $html .= '<div class="form-text">'. esc_html__( 'Use the ID provided in the Elementor form\'s Additional information.', 'joinotify' ) .'</div>';
            $html .= '</div>';
        }

        return $html;
    }


    /**
     * Get workflow connector between actions and steps
     * 
     * @since 1.0.0
     * @version 1.4.7
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
                $html .= '<div class="funnel_block_item_add">';
                    $html .= '<div class="funnel_add_action_wrapper between_actions funnel_show_plus">';
                        $html .= '<div class="funnel_add_action between-action-connector">';
                            $html .= '<div class="plusminus"></div>';
                        $html .= '</div>';
                    $html .= '</div>';
                $html .= '</div>';
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
                    $html .= '<span>'. esc_html__( 'False', 'joinotify' ) .'</span>';
    
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
                            $html .= esc_html__( 'Add action', 'joinotify' );
                        $html .= '</button>';
                    $html .= '</div>';
                $html .= '</div>';
    
                // condition true
                $html .= '<div class="joinotify_condition_node_point condition_true">';
                    $html .= '<span>'. esc_html__( 'True', 'joinotify' ) .'</span>';
    
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
                            $html .= esc_html__( 'Add action', 'joinotify' );
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
     * @version 1.4.7
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
     * @version 1.4.7
     * @param string $condition | Condition key
     * @param array $settings | Settings for condition
     * @return string HTML of rendered condition settings
     */
    public static function render_condition_settings( $condition, $settings = array() ) {
        $html = '';

        $condition_content = $settings['condition_content'] ?? array();
        $condition_settings = $condition_content['type'] ?? '';
        $condition_value = $condition_content['value'] ?? '';

        $html .= '<input type="hidden" class="get-condition-title" value="'. esc_attr( $settings['title'] ?? '' ) .'"/>';
        $html .= '<input type="hidden" class="get-condition" value="'. esc_attr( $condition_content['condition'] ?? '' ) .'"/>';

        // add condition options
        $html .= self::get_condition_options( $condition, $condition_settings );

        switch ( $condition ) {
            case 'user_role':
                global $wp_roles;
            
                $roles = $wp_roles->get_names();

                $html .= '<div class="mb-4 user-role-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('User role: *', 'joinotify') . '</label>';
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
                    $html .= '<label class="form-label">' . esc_html__('Key: *', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_content['meta_key'] ?? '' ) .'" placeholder="'. esc_attr__( 'Meta key', 'joinotify' ) .'">';
                $html .= '</div>';

                $html .= '<div class="mb-4 meta-value-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Value: *', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Meta value', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'post_type':
                $html .= '<div class="mb-4 post-type-wrapper">';
                    // Retrieves all registered post types
                    $post_types = get_post_types( array( 'public' => true ), 'objects' );

                    $html .= '<label class="form-label">' . esc_html__('Post type: *', 'joinotify') . '</label>';
                    
                    $html .= '<select class="form-control get-post-type get-condition-value required-setting">';
                        $html .= '<option value="none">' . esc_html__( 'Select a post type', 'joinotify' ) . '</option>';

                        foreach ( $post_types as $post_type_key => $post_type_object ) {
                            $html .= '<option value="' . esc_attr( $post_type_key ) . '" '. selected( $condition_value, $post_type_key, false ) .'>' . esc_html( $post_type_object->labels->name ) . '</option>';
                        }
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'post_author':
                $html .= '<div class="mb-4 post-author-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Post author: *', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value post-author" placeholder="'. esc_attr__( 'Author name', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'order_status':
                $html .= '<div class="mb-4 order-status-wrapper">';
                    // Retrieves all WooCommerce order statuses
                    if ( function_exists('wc_get_order_statuses') ) {
                        $order_statuses = wc_get_order_statuses();

                        $html .= '<label class="form-label">' . esc_html__('Order status: *', 'joinotify') . '</label>';
                        
                        $html .= '<select class="form-control get-condition-value required-setting">';
                            $html .= '<option value="none">' . esc_html__( 'Select a status', 'joinotify' ) . '</option>';

                            foreach ( $order_statuses as $status_key => $status_name ) {
                                $html .= '<option value="' . esc_attr( $status_key ) . '" '. selected( $condition_value, $status_key, false ) .'>' . esc_html( $status_name ) . '</option>';
                            }
                        $html .= '</select>';
                    }
                $html .= '</div>';

                break;
            case 'order_total':
                $html .= '<div class="mb-4 order-total-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Order total: *', 'joinotify') . '</label>';

                    $html .= '<div class="input-group">';
                        if ( function_exists('get_woocommerce_currency_symbol') ) {
                            $html .= '<span class="input-group-text">'. get_woocommerce_currency_symbol() .'</span>';
                        }

                        $html .= '<input type="text" class="form-control get-condition-value format-currency required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Order total', 'joinotify' ) .'">';
                    $html .= '</div>';
                $html .= '</div>';

                break;
            case 'order_paid':

                break;
            case 'products_purchased':
                $html .= '<div class="mb-4 search-products-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Products purchased:', 'joinotify') . '</label>';

                    // create a JSON with the selected products
                    $selected_products = isset( $condition_content['products'] ) ? json_encode( $condition_content['products'] ) : '[]';

                    $html .= '<select class="get-condition-value search-products required-setting" multiple data-selected-products="'. esc_attr( $selected_products ) .'" placeholder="'. esc_attr__( 'Search for the products you want to include', 'joinotify' ) .'">';
                        if ( isset( $condition_content['products'] ) && is_array( $condition_content['products'] ) ) {
                            foreach ( $condition_content['products'] as $product ) {
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
                        $html .= '<label class="form-label">' . esc_html__('Payment method: *', 'joinotify') . '</label>';

                        $html .= '<select class="form-control get-condition-value required-setting">';
                            $html .= '<option value="none">' . esc_html__( 'Select a payment method', 'joinotify' ) . '</option>';
                            
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
                        $html .= '<label class="form-label">' . esc_html__('Delivery method: *', 'joinotify') . '</label>';

                        $html .= '<select class="form-control get-condition-value required-setting">';
                            $html .= '<option value="none">' . esc_html__( 'Select a shipping method', 'joinotify' ) . '</option>';

                            foreach ( WC()->shipping->get_shipping_methods() as $shipping_method_key => $shipping_method_object ) {
                                $html .= '<option value="' . esc_attr( $shipping_method_key ) . '" '. selected( $condition_value, $shipping_method_key, false ) .'>' . esc_html( $shipping_method_object->method_title ) . '</option>';
                            }
                        $html .= '</select>';
                    }
                $html .= '</div>';

                break;
            case 'customer_email':
                $html .= '<div class="mb-4 customer-email-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Customer email:', 'joinotify') . '</label>';
                    $html .= '<input type="email" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Customer email', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'refund_amount':
                $html .= '<div class="mb-4 refund-amount-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Refund amount: *', 'joinotify') . '</label>';

                    $html .= '<div class="input-group">';
                        if ( function_exists('get_woocommerce_currency_symbol') ) {
                            $html .= '<span class="input-group-text">'. get_woocommerce_currency_symbol() .'</span>';
                        }
                        
                        $html .= '<input type="text" class="form-control get-condition-value format-currency required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Refund amount', 'joinotify' ) .'">';
                    $html .= '</div>';
                $html .= '</div>';

                break;
            case 'subscription_status':
                $html .= '<div class="mb-4 subscription-status-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Subscription status: *', 'joinotify') . '</label>';
                    
                    $html .= '<select class="form-control get-condition-value required-setting">';
                        $html .= '<option value="active" '. selected( $condition_value, 'active', false ) .'>' . esc_html__( 'Active', 'joinotify' ) . '</option>';
                        $html .= '<option value="on-hold" '. selected( $condition_value, 'on-hold', false ) .'>' . esc_html__( 'Pending', 'joinotify' ) . '</option>';
                        $html .= '<option value="cancelled" '. selected( $condition_value, 'cancelled', false ) .'>' . esc_html__( 'Canceled', 'joinotify' ) . '</option>';
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'renewal_payment':
                $html .= '<div class="mb-4 renewal-payment-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Renewal payment: *', 'joinotify') . '</label>';
                    
                    $html .= '<select class="form-control get-condition-value required-setting">';
                        $html .= '<option value="yes" '. selected( $condition_value, 'yes', false ) .'>' . esc_html__( 'Yes', 'joinotify' ) . '</option>';
                        $html .= '<option value="no" '. selected( $condition_value, 'no', false ) .'>' . esc_html__( 'No', 'joinotify' ) . '</option>';
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'cart_total':
                $html .= '<div class="mb-4 cart-total-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Cart total:', 'joinotify') . '</label>';
                    $html .= '<input type="number" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Cart total', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'items_in_cart':
                $html .= '<div class="mb-4 search-products-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Cart items:', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value search-products" placeholder="'. esc_attr__( 'Search for the products you want to include', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'field_value':
                $html .= '<div class="mb-4 field-id-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Field ID:', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_content['field_id'] ?? '' ) .'" placeholder="'. esc_attr__( 'Field ID', 'joinotify' ) .'">';
                $html .= '</div>';

                $html .= '<div class="mb-4 field-value-wrapper">';
                    $html .= '<label class="form-label">' . esc_html__('Field value:', 'joinotify') . '</label>';
                    $html .= '<input type="text" class="form-control get-condition-value required-setting" value="'. esc_attr( $condition_value ) .'" placeholder="'. esc_attr__( 'Field value', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            default:
                $html .= '<p class="alert alert-info">' . esc_html__( 'No configuration available for this condition', 'joinotify' ) . '</p>';
                
                break;
        }

        return $html;
    }


    /**
     * Get condition options
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $condition | Condition key
     * @param string $condition_value | Condition value
     * @return string
     */
    public static function get_condition_options( $condition, $condition_value = '' ) {
        $condition_options = array(
            'is' => esc_html__( 'Is', 'joinotify' ),
            'is_not' => esc_html__( 'Is not', 'joinotify' ),
            'empty' => esc_html__( 'Empty', 'joinotify' ),
            'not_empty' => esc_html__( 'Not empty', 'joinotify' ),
            'contains' => esc_html__( 'Contains', 'joinotify' ),
            'not_contain' => esc_html__( 'Not contains', 'joinotify' ),
            'start_with' => esc_html__( 'Start with', 'joinotify' ),
            'finish_with' => esc_html__( 'Ends with', 'joinotify' ),
            'bigger_than' => esc_html__( 'Bigger than', 'joinotify' ),
            'less_than' => esc_html__( 'Less than', 'joinotify' ),
        );

        $html = '<div class="mb-4">';
            $html .= '<label class="form-label">'. esc_html__( 'Condition: *', 'joinotify' ) .'</label>';
            $html .= '<select class="form-select get-condition-type required-setting">';
                $html .= '<option value="none">'. esc_html__( 'Select a condition', 'joinotify' ) .'</option>';

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
     * @version 1.4.7
     * @param int $post_id | Post ID
     * @return string
     */
    public static function render_placeholders_list( $post_id = 0 ) {
        $accordion_id = uniqid('whatsapp_msg_text_placeholder_');
        $accordion_container_id = uniqid('whatsapp_msg_text_placeholder_collapse_');

        $html = '<div class="accordion" id="'. esc_attr( $accordion_id ) .'">';
            $html .= '<div class="accordion-item">';
                $html .= '<h2 class="accordion-header fw-normal">';
                    $html .= '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#'. esc_attr( $accordion_container_id ) .'" aria-expanded="true">'. esc_html__( 'Text variables', 'joinotify' ) .'</button>';
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
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Select the delay type for the next action', 'joinotify' ) ?></span>
                
                <select class="form-select set-time-delay-type">
                    <option value="period" <?php selected( $settings['delay_type'] ?? '', 'period' ) ?>><?php esc_html_e( 'Wait time', 'joinotify' ) ?></option>
                    <option value="date" <?php selected( $settings['delay_type'] ?? '', 'date' ) ?>><?php esc_html_e( 'Wait until a date', 'joinotify' ) ?></option>
                </select>
            </div>

            <div class="wait-time-period-container">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Wait for', 'joinotify' ) ?></span>
                
                <div class="input-group">
                    <input type="number" class="form-control get-wait-value" value="<?php echo $settings['delay_value'] ?? '' ?>"/>

                    <select class="form-select get-wait-period">
                        <?php foreach ( self::get_time_delay_options() as $option => $title ) : ?>
                            <option value="<?php echo esc_attr( $option ) ?>" <?php selected( $settings['delay_period'] ?? '', $option ) ?>><?php echo esc_html( $title ) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="wait-date-container d-none">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Wait until', 'joinotify' ) ?></span>
                
                <div class="input-group">
                    <input type="text" class="form-control dateselect get-date-value" value="<?php echo $settings['date_value'] ?? '' ?>" placeholder="<?php esc_attr_e( 'Select a date', 'joinotify' ) ?>"/>
                    <input type="time" class="form-control get-time-value" value="<?php echo $settings['time_value'] ?? '' ?>"/>
                </div>
            </div>
        <?php else : ?>
            <div class="alert alert-warning d-flex align-items-center">
                <svg class="icon icon-lg icon-warning me-2 w-25" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11.001 10h2v5h-2zM11 16h2v2h-2z"/><path d="M13.768 4.2C13.42 3.545 12.742 3.138 12 3.138s-1.42.407-1.768 1.063L2.894 18.064a1.986 1.986 0 0 0 .054 1.968A1.984 1.984 0 0 0 4.661 21h14.678c.708 0 1.349-.362 1.714-.968a1.989 1.989 0 0 0 .054-1.968L13.768 4.2zM4.661 19 12 5.137 19.344 19H4.661z"/></svg>
                <?php esc_html_e( 'The WP-CRON function is disabled on this site. Enable it to use the Wait Time action.', 'joinotify' ) ?>
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
                <?php echo __( '<strong>Warning!</strong> Incorrect use of PHP Snippets may cause errors on the site.', 'joinotify' ) ?>
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
            <div class="action-item-container <?php echo esc_attr( $action['action'] ) ?>" style="order: <?php echo esc_attr( $action['priority'] ) ?>;">
                <!-- ACTION CARD START -->
                <div class="action-item <?php echo isset( $action['class'] ) ? esc_attr( $action['class'] ) : ''; ?>" data-action="<?php echo esc_attr( $action['action'] ) ?>" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_<?php echo esc_attr( $action['action'] ) ?>">
                    <div class="d-flex align-items-center">
                        <?php if ( ! $action['external_icon'] ) : ?>
                            <div class="action-item-icon me-3"><?php echo $action['icon'] ?></div>
                        <?php endif; ?>

                        <div class="d-grid">
                            <span class="title mb-1"><?php echo esc_html( $action['title'] ) ?></span>
                            <span class="description"><?php echo esc_html( $action['description'] ) ?></span>
                        </div>
                    </div>
                </div>
                <!-- ACTION CARD END -->

                <!-- SIDEBAR SETTINGS -->
                <div class="offcanvas offcanvas-end" data-action="<?php echo esc_attr( $action['action'] ) ?>" data-bs-scroll="false" data-bs-backdrop="false" tabindex="-1" id="offcanvas_<?php echo esc_attr( $action['action'] ) ?>" aria-labelledby="offcanvas_<?php echo esc_attr( $action['action'] ) ?>_label">
                    <!-- SIDEBAR ACTION SETTINGS START -->
                    <div class="offcanvas-header px-4 py-lg-1 py-xxl-3 mt-2 border-bottom justify-content-between">
                        <div class="d-flex align-items-center">
                            <?php if ( ! $action['external_icon'] ) : ?>
                                <div class="action-item-icon me-3"><?php echo $action['icon'] ?></div>
                            <?php endif; ?>

                            <h5 class="offcanvas-title" id="offcanvas_<?php echo esc_attr( $action['action'] ) ?>_label"><?php echo esc_html( $action['title'] ) ?></h5>
                        </div>

                        <div class="d-flex align-items-center">
                            <?php if ( isset( $action['is_expansible'] ) && $action['is_expansible'] === true ) : ?>
                                <button type="button" class="btn ms-3 px-2 btn-link expand-offcanvas" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Expand', 'joinotify' ) ?>" aria-label="<?php esc_attr_e( 'Expand', 'joinotify' ) ?>">
                                    <svg class="icon icon-dark icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M8.29 5.64 1.93 12l6.36 6.36 1.42-1.41L4.76 12l4.95-4.95-1.42-1.41zm6 1.41L19.24 12l-4.95 4.95 1.42 1.41L22.07 12l-6.36-6.36-1.42 1.41z"></path></svg>
                                </button>

                                <button type="button" class="btn ms-3 px-2 btn-link collapse-offcanvas d-none" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Collapse', 'joinotify' ) ?>" aria-label="<?php esc_attr_e( 'Collapse', 'joinotify' ) ?>">
                                    <svg class="icon icon-dark icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.95 5.64 13.59 12l6.36 6.36 1.41-1.41L16.41 12l4.95-4.95-1.41-1.41zM2.64 7.05 7.59 12l-4.95 4.95 1.41 1.41L10.41 12 4.05 5.64 2.64 7.05z"></path></svg>
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php esc_attr_e( 'Close', 'joinotify' ) ?>"></button>
                        </div>
                    </div>
                    <!-- SIDEBAR ACTION SETTINGS END -->

                    <div class="offcanvas-body p-4 py-5">
                        <?php if ( isset( $action['has_settings'] ) && $action['has_settings'] ) : ?>
                            <?php echo $action['settings']; ?>
                        <?php else : ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <svg class="icon icon-lg icon-info me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
                                <?php esc_html_e( 'This action does not require additional settings.', 'joinotify' ) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="offcanvas-footer px-4 py-lg-2 py-xxl-3 d-flex justify-content-end">
                        <button id="add_action_<?php echo esc_attr( $action['action'] ) ?>" class="btn btn-primary add-funnel-action" disabled data-action="<?php echo esc_attr( $action['action'] ) ?>"><?php esc_html_e( 'Add action', 'joinotify' ) ?></button>
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


    /**
     * Render dynamic placeholder action
     * 
     * @since 1.3.0
     * @param array $settings | The action settings
     * @return string
     */
    public static function dynamic_placeholder_action( $settings = array() ) {
        ob_start(); ?>

        <button type="submit" id="joinotify_listen_hook" class="btn btn-outline-secondary" role="button" data-trigger="" data-bs-toggle="modal" data-bs-target="#edit_workflow_title"><?php esc_html_e( 'Get sample data', 'joinotify' ) ?></button>

        <div class="mb-4">
            <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Text variable name', 'joinotify' ) ?></span>
            
            <input type="text" class="form-control required-setting get-dynamic-placeholder-text" value="<?php echo $settings['dynamic_placeholder_text'] ?? ''; ?>" placeholder="<?php esc_attr_e( 'variable_name', 'joinotify' ) ?>"/>
        </div>

        <div>
            <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Variable value', 'joinotify' ) ?></span>

            <input type="text" class="form-control required-setting get-dynamic-placeholder-value" value="<?php echo $settings['dynamic_placeholder_value'] ?? ''; ?>" placeholder="<?php esc_attr_e( '$object->item', 'joinotify' ) ?>"/>
        </div>

        <?php return ob_get_clean();
    }
}