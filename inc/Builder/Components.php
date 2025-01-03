<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Helpers;

use MeuMouse\Joinotify\Validations\Conditions;

use MeuMouse\Joinotify\Builder\Core;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Builder\Utils;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Builder\Actions;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the functions for display components on workflow builder
 * 
 * @since 1.0.0
 * @version 1.1.0
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
     * Display OTP input for validation
     * 
     * @since 1.0.0
     * @param string $phone | Phone number
     * @return string
     */
    public static function otp_input_code( $phone ) {
        ob_start();

        if ( isset( $phone ) ) : ?>
            <div class="d-grid align-items-center justify-content-center p-4 validate-otp-code" data-phone="<?php echo esc_attr( $phone ) ?>">
                <div class="d-grid align-items-center justify-content-center justify-items-center mb-4">
                    <h3 class="fs-5 mb-3"><?php esc_html_e( 'Verifique seu WhatsApp', 'joinotify' ) ?></h3>
                    <span class="fs-base text-muted mb-2"><?php esc_html_e( 'Informe o código de 4 dígitos que foi enviado para', 'joinotify' ) ?></span>
                    <span class="fw-semibold fs-base"><?php echo esc_html( Helpers::format_phone_number( $phone ) ) ?></span>
                </div>

                <div class="d-flex align-items-center justify-content-center mb-4 otp-input-group">
                    <input type="text" maxlenght="1" class="otp-input-item me-3"/>
                    <input type="text" maxlenght="1" class="otp-input-item me-3"/>
                    <input type="text" maxlenght="1" class="otp-input-item me-3"/>
                    <input type="text" maxlenght="1" class="otp-input-item"/>
                </div>

                <div class="d-flex align-items-center justify-content-center resend-otp">
                    <span class="fs-base text-muted me-1"><?php esc_html_e( 'Reenvie o código em', 'joinotify' ) ?></span>
                    <span class="fw-semibold fs-base me-1 countdown-otp-resend"></span>
                    <span class="fs-base fw-semibold"><?php esc_html_e( 'segundos', 'joinotify' ) ?></span>
                </div>
            </div>
        <?php endif;

        return ob_get_clean();
    }


    /**
     * Display action HTML
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @param string $action_name | Action name
     * @param string $description | Action custom description
     * @param string $action_id | Action ID
     * @return string
     */
    public static function get_action_html( $post_id, $action_name, $description = '', $action_id = '' ) {
        foreach ( Actions::get_all_actions() as $action => $value ) {
            if ( $action_name !== $value['action'] ) {
                continue;
            }

            $html = '<div class="funnel-action-item" data-action="'. esc_attr( $value['action'] ) .'" data-action-id="'. esc_attr( $action_id ) .'">';
                $html .= '<div class="action-item-body">';
                    $html .= '<h4 class="title">'. $value['title'] .'</h4>';

                    if ( empty( $description ) ) {
                        $html .= '<span class="description">'. $value['description'] .'</span>';
                    } else {
                        $html .= '<span class="description">'. $description .'</span>';
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
                    $html .= '<div class="modal fade" id="edit_condition_'. esc_attr( $action_id ) .'" tabindex="-1" aria-labelledby="edit_condition_'. esc_attr( $action_id ) .'_label" aria-hidden="true">';
                        $html .= '<div class="modal-dialog modal-dialog-centered modal-md">';
                            $html .= '<div class="modal-content">';
                                $html .= '<div class="modal-header px-4">';
                                    $html .= '<h3 class="modal-title fs-5" id="edit_condition_'. esc_attr( $action_id ) .'_label">'. esc_html__( 'Configurar condição', 'joinotify' ) .'</h3>';
                                    $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'. esc_attr__( 'Fechar', 'joinotify' ) .'"></button>';
                                $html .= '</div>';

                                $html .= '<div class="modal-body px-4 py-3 my-3">';
                                    $get_condition = Utils::get_condition_item( $post_id, $action_id );
                                    $html .= self::render_condition_settings( $get_condition );
                                $html .= '</div>';

                                $html .= '<div class="modal-footer px-4">';
                                    $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                    $html .= '<button type="button" class="btn btn-primary save-condition m-0" data-condition-id="'. esc_attr( $action_id ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
                                $html .= '</div>';
                            $html .= '</div>';
                        $html .= '</div>';
                    $html .= '</div>';
                } elseif ( $value['action'] !== 'condition' && $value['action'] !== 'stop_funnel' ) {
                    $html .= '<div class="modal fade" id="edit_action_'. esc_attr( $action_id ) .'" tabindex="-1" aria-labelledby="edit_action_'. esc_attr( $action_id ) .'_label" aria-hidden="true">';
                        $html .= '<div class="modal-dialog modal-dialog-centered modal-md">';
                            $html .= '<div class="modal-content">';
                                $html .= '<div class="modal-header px-4">';
                                    $html .= '<h3 class="modal-title fs-5" id="edit_action_'. esc_attr( $action_id ) .'_label">'. esc_html__( 'Configurar ação', 'joinotify' ) .'</h3>';
                                    $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'. esc_attr__( 'Fechar', 'joinotify' ) .'"></button>';
                                $html .= '</div>';

                                $html .= '<div class="modal-body px-4 py-3 my-3">';
                                    $html .= self::get_action_settings( $post_id, $value['action'], $action_id );
                                $html .= '</div>';

                                $html .= '<div class="modal-footer px-4">';
                                    $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                    $html .= '<button type="button" class="btn btn-primary save-condition m-0" data-condition-id="'. esc_attr( $action_id ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
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
     * Display action condition HTML
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @param array $condition_data | Condition data
     * @param string $title | (Optional) Action title
     * @param string $description | (Optional) Action description
     * @return string
     */
    public static function get_action_condition_html( $post_id, $condition_data, $title = '', $description = '' ) {
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
                        $html .= '<div class="modal fade" id="edit_action_'. esc_attr( $condition_data['data']['action'] ) .'" tabindex="-1" aria-labelledby="edit_action_'. esc_attr( $condition_data['data']['action'] ) .'_label" aria-hidden="true">';
                            $html .= '<div class="modal-dialog modal-dialog-centered modal-md">';
                                $html .= '<div class="modal-content">';
                                    $html .= '<div class="modal-header px-4">';
                                        $html .= '<h3 class="modal-title fs-5" id="edit_action_'. esc_attr( $condition_data['data']['action'] ) .'_label">'. esc_html__( 'Configurar ação', 'joinotify' ) .'</h3>';
                                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'. esc_attr__( 'Fechar', 'joinotify' ) .'"></button>';
                                    $html .= '</div>';

                                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                                        $html .= self::get_action_settings( $post_id, $condition_data['data']['action'] );
                                    $html .= '</div>';

                                    $html .= '<div class="modal-footer px-4">';
                                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                        $html .= '<button type="button" class="btn btn-primary save-condition m-0" data-condition-id="'. esc_attr( $condition_data['data']['action'] ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
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
     * @param array $action | Action name
     * @param string $action_id | Action ID
     * @return string
     */
    public static function get_action_settings( $post_id, $action, $action_id ) {
        $html = '';

        // get workflow content from post id
        $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
        $current_action = Actions::find_action_by_id( $workflow_content, $action_id );
        $action_data = isset( $current_action['data'] ) ? $current_action['data'] : array();

        switch ( $action ) {
            case 'time_delay':
                $delay_type = isset( $action_data['delay_type'] ) ? esc_attr( $action_data['delay_type'] ) : 'period';

                $html .= '<div class="mb-4">';
                    $html .= '<span class="fs-md text-muted mb-2 ms-2 d-block">'. esc_html__( 'Selecione o tipo de atraso da próxima ação', 'joinotify' ) .'</span>';
                    
                    $html .= '<select class="form-select set-time-delay-type">';
                        $html .= '<option value="period"'. selected( $delay_type, 'period', false ) .'>'. esc_html__( 'Esperar tempo', 'joinotify' ) .'</option>';
                        $html .= '<option value="date"'. selected( $delay_type, 'date', false ) .'>'. esc_html__( 'Esperar até uma data', 'joinotify' ) .'</option>';
                    $html .= '</select>';
                $html .= '</div>';

                $delay_value = isset( $action_data['delay_value'] ) ? esc_attr( $action_data['delay_value'] ) : '';
                $delay_period = isset( $action_data['delay_period'] ) ? esc_attr( $action_data['delay_period'] ) : 'seconds';

                $html .= '<div class="wait-time-period-container">';
                    $html .= '<span class="fs-md text-muted mb-2 ms-2 d-block">'. esc_html__( 'Esperar por', 'joinotify' ) .'</span>';
                    
                    $html .= '<div class="input-group">';
                        $html .= '<input type="number" class="form-control get-wait-value" value="'. $delay_value .'"/>'; 
                        $html .= '<select class="form-select get-wait-period">';
                            foreach ( self::get_time_delay_options() as $option => $title ) {
                                $html .= '<option value="'. esc_attr( $option ) .'"'. selected( $delay_period, $option, false) .'>'. esc_html( $title ) .'</option>';
                            }
                        $html .= '</select>';
                    $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="wait-date-container">';
                    $html .= '<span class="fs-md text-muted mb-2 ms-2 d-block">'. esc_html__( 'Esperar até', 'joinotify' ) .'</span>';
                    
                    $html .= '<div class="input-group">';
                        $html .= '<input type="text" class="form-control dateselect get-date-value" placeholder="'. esc_attr__( 'Selecione uma data', 'joinotify' ) .'"/>';
                        $html .= '<input type="text" class="form-control get-time-value" placeholder="'. esc_attr__( 'Digite um horário (Opcional)', 'joinotify' ) .'" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="'. esc_attr__( 'Informe um horário no formato H:i - 20:03', 'joinotify' ) .'"/>';
                    $html .= '</div>';
                $html .= '</div>';

                break;
            case 'send_whatsapp_message_text':
                $sender = isset( $action_data['sender'] ) ? esc_attr( $action_data['sender'] ) : '';
                $receiver = isset( $action_data['receiver'] ) ? esc_attr( $action_data['receiver'] ) : '';
                $message = isset( $action_data['message'] ) ? esc_html( $action_data['message'] ) : '';

                $html .= '<div class="preview-whatsapp-message-sender active edit-action">'. nl2br( $message ) .'</div>';

                $html .= '<div class="input-group mb-3">';
                    $html .= '<span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="joinotify-tooltip" data-bs-title="'. esc_attr__( 'Remetente', 'joinotify' ) .'">';
                        $html .= '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16.712 13.288a.999.999 0 0 0-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a.999.999 0 0 0-1.414 0L2.586 6c-.38.38-.594.902-.586 1.435.023 1.424.4 6.37 4.298 10.268S15.142 21.977 16.566 22h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-3.999-4zM16.585 20c-1.248-.021-5.518-.356-8.873-3.712C4.346 12.922 4.02 8.637 4 7.414l2.005-2.005 2.586 2.586-1.293 1.293a1 1 0 0 0-.272.912c.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.993.993 0 0 0 .912-.271l1.293-1.293 2.586 2.586L16.585 20z"></path><path d="m16.795 5.791-4.497 4.497 1.414 1.414 4.497-4.497L21.005 10V2.995H14z"></path></svg>';
                    $html .= '</span>';
                            
                    $html .= '<select id="joinotify_get_whatsapp_phone_sender_edit" class="form-select mw-100">';
                        foreach ( get_option('joinotify_get_phones_senders') as $phone ) {
                            $html .= '<option value="'. esc_attr( $phone ) .'" '. selected( $sender, $phone, false ) .' class="get-sender-number">'. esc_html( Helpers::format_phone_number( $phone ) ) .'</option>';
                        }
                    $html .= '</select>';
                $html .= '</div>';

                $html .= '<div class="input-group mb-3">';
                    $html .= '<span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="joinotify-tooltip" data-bs-title="'. esc_attr__( 'Destinatário', 'joinotify' ) .'">';
                        $html .= '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16.712 13.288a.999.999 0 0 0-1.414 0l-1.597 1.596c-.824-.245-2.166-.771-2.99-1.596-.874-.874-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a1.03 1.03 0 0 0-1.414 0l-2.709 2.71c-.382.38-.597.904-.588 1.437.022 1.423.396 6.367 4.297 10.268C10.195 21.6 15.142 21.977 16.566 22h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-3.999-4zM16.585 20c-1.248-.021-5.518-.356-8.874-3.712C4.343 12.92 4.019 8.636 4 7.414l2.004-2.005L8.59 7.995 7.297 9.288c-.238.238-.34.582-.271.912.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.994.994 0 0 0 .912-.271l1.293-1.293 2.586 2.586L16.585 20z"></path><path d="M15.795 6.791 13.005 4v6.995H20l-2.791-2.79 4.503-4.503-1.414-1.414z"></path></svg>';
                    $html .= '</span>';

                    $html .= '<input id="joinotify_get_whatsapp_number_msg_text_edit" type="text" class="form-control" value="'. $receiver .'" placeholder="'. esc_attr__( '+5541987111527', 'joinotify' ) .'"/>';
                $html .= '</div>';

                // Estimate number of lines (counts how many line breaks there are)
                $lines = substr_count( $message, "\n" ) + 1;
                $line_height = 40; // px
                $textarea_height = $lines * $line_height;

                $html .= '<div class="input-group mb-3">';
                    $html .= '<button class="btn btn-icon btn-outline-secondary icon-translucent emoji_picker">';
                        $html .= '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M14.829 14.828a4.055 4.055 0 0 1-1.272.858 4.002 4.002 0 0 1-4.875-1.45l-1.658 1.119a6.063 6.063 0 0 0 1.621 1.62 5.963 5.963 0 0 0 2.148.903 6.042 6.042 0 0 0 2.415 0 5.972 5.972 0 0 0 2.148-.903c.313-.212.612-.458.886-.731.272-.271.52-.571.734-.889l-1.658-1.119a4.017 4.017 0 0 1-.489.592z"></path><circle cx="8.5" cy="10.5" r="1.5"></circle><circle cx="15.493" cy="10.493" r="1.493"></circle></svg>';
                    $html .= '</button>';

                    $html .= '<textarea id="joinotify_get_whatsapp_message_text_edit" class="form-control set-whatsapp-message set-whatsapp-message-text" placeholder="'. esc_attr__( 'Mensagem', 'joinotify' ) .'"  style="height: '. $textarea_height .'px;">'. $message .'</textarea>';
                $html .= '</div>';

                $html .= '<div class="accordion" id="whatsapp_msg_text_placeholder_accordion_edit">';
                    $html .= '<div class="accordion-item">';
                        $html .= '<h2 class="accordion-header fw-normal">';
                            $html .= '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#whatsapp_msg_text_placeholder_collapse_edit" aria-expanded="true" aria-controls="collapseText">'. esc_html__( 'Variáveis de texto', 'joinotify' ) .'</button>';
                        $html .= '</h2>';

                        $html .= '<div id="whatsapp_msg_text_placeholder_collapse_edit" class="accordion-collapse collapse" data-bs-parent="#whatsapp_msg_text_placeholder_accordion_edit">';
                            $html .= '<div class="accordion-body">';
                                $html .= '<div class="mt-3 placeholders-list">';
                                    $trigger = Triggers::get_trigger_from_post( $post_id );
                                    $integration = Utils::get_context_from_post( $post_id );

                                    $context = array(
                                        'integration' => $integration,
                                        'trigger' => $trigger,
                                        'type' => 'trigger',
                                    );

                                    error_log( 'trigger: ' . print_r( $trigger, true ) );
                                    error_log( 'integration: ' . print_r( $integration, true ) );

                                    // get filtered placeholders
                                    $placeholders = Placeholders::get_placeholders_list( $integration, $trigger, $context );
                                    
                                    error_log( 'placeholders: ' . print_r( $placeholders, true ) );

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

                break;
            case 'send_whatsapp_message_media':
                $html .= '';

                break;
        }

        return $html;
    }


    /**
     * Get trigger HTML based on context and data_trigger
     * 
     * @since 1.0.0
     * @param string $context | Context name (woocommerce, elementor, etc.)
     * @param string $data_trigger | Trigger name (ex: 'order_completed')
     * @param string $trigger_id | Trigger ID
     * @return mixed | return the HTML of the trigger or false if not found
     */
    public static function get_trigger_html( $context, $data_trigger, $trigger_id = '' ) {
        $trigger = Triggers::get_trigger( $context, $data_trigger );

        // check if has trigger
        if ( $trigger ) {
            $html = '<div class="funnel-trigger-item" data-context="'. esc_attr( $context ) .'" data-trigger="'. esc_attr( $trigger['data_trigger'] ) .'" data-trigger-id="'. esc_attr( $trigger_id ) .'">';
                $html .= '<div class="funnel-trigger-header me-4">';
                    $html .= '<h4 class="title">'. esc_html( $trigger['title'] ) .'</h4>';
                    $html .= '<span class="description">'. esc_html( $trigger['description'] ) .'</span>';
                $html .= '</div>';

                $html .= '<div class="btn-group">';
                    $html .= '<div class="funnel-trigger-cta icon-translucent btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false"><svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 12c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg></div>';
                
                    $html .= '<div class="funnel-trigger-details">';
                        $html .= '<ul class="dropdown-menu builder-dropdown shadow-sm">';
                            $html .= '<li class="d-flex align-items-center mb-0">';
                                $html .= '<a id="exclude_trigger_'. esc_attr( $trigger['data_trigger'] ) .'" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 exclude-trigger" data-trigger-id="'. esc_attr( $trigger_id ) .'" href="#">';
                                    $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15 2H9c-1.103 0-2 .897-2 2v2H3v2h2v12c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2V8h2V6h-4V4c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 16H7V8h10v12z"></path></svg>';
                                    $html .= esc_html__( 'Excluir acionamento', 'joinotify' );
                                $html .= '</a>';
                            $html .= '</li>';

                            if ( $trigger['require_settings'] !== false ) {
                                $html .= '<li class="d-flex align-items-center mb-0">';
                                    $html .= '<a id="edit_trigger_'. esc_attr( $trigger_id ) .'_trigger" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0 edit-trigger" href="#" data-bs-toggle="modal" data-bs-target="#edit_trigger_'. esc_attr( $trigger_id ) .'">';
                                        $html .= '<svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>';
                                        $html .= esc_html__( 'Configurações', 'joinotify' );
                                    $html .= '</a>';
                                $html .= '</li>';
                            }
                        $html .= '</ul>';
                    $html .= '</div>';

                    if ( $trigger['require_settings'] !== false ) {
                        $html .= '<div class="modal fade" id="edit_trigger_'. esc_attr( $trigger_id ) .'" tabindex="-1" aria-labelledby="edit_trigger_'. esc_attr( $trigger_id ) .'_label" aria-hidden="true">';
                            $html .= '<div class="modal-dialog modal-dialog-centered modal-md">';
                                $html .= '<div class="modal-content">';
                                    $html .= '<div class="modal-header px-4">';
                                        $html .= '<h3 class="modal-title fs-5" id="edit_trigger_'. esc_attr( $trigger_id ) .'_label">'. esc_html__( 'Configurar acionamento', 'joinotify' ) .'</h3>';
                                        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'. esc_attr__( 'Fechar', 'joinotify' ) .'"></button>';
                                    $html .= '</div>';

                                    $html .= '<div class="modal-body px-4 py-3 my-3">';
                                        
                                    $html .= '</div>';

                                    $html .= '<div class="modal-footer px-4 py-3">';
                                        $html .= '<button type="button" class="btn btn-outline-secondary my-2 me-3" data-bs-dismiss="modal">'. esc_html__( 'Cancelar', 'joinotify' ) .'</button>';
                                        $html .= '<button type="button" class="btn btn-primary save-trigger-settings m-0" data-trigger-id="'. esc_attr( $trigger_id ) .'">'. esc_html__( 'Salvar alterações', 'joinotify' ) .'</button>';
                                    $html .= '</div>';
                                $html .= '</div>';
                            $html .= '</div>';
                        $html .= '</div>';
                    }
                $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        return false;
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
    public static function get_workflow_connector( $post_id, $type, $workflow_data ) {
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
        $condition_data = Utils::find_condition_by_id( $post_id, $action_id );

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

                                $html .= self::get_action_condition_html( $post_id, $false_action, $action_false_title, $action_false_description );
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

                                $html .= self::get_action_condition_html( $post_id, $true_action, $action_true_title, $action_true_description );
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
     * @param array $conditions | Condition key
     * @return string
     */
    public static function render_condition_selectors( $conditions ) {
        $html = '<div class="condition-group">';
            foreach ( $conditions as $condition => $value ) {
                $html .= '<div class="condition-item" data-condition="'. esc_attr( $condition ) .'">';
                    $html .= '<span class="title">'. esc_html( $value['title'] ) .'</span>';
                    $html .= '<span class="description">'. esc_html( $value['description'] ) .'</span>';
                $html .= '</div>';

                $html .= '<div class="condition-settings-item" data-condition="'. esc_attr( $condition ) .'">';
                    $html .= self::render_condition_settings( $condition );
                $html .= '</div>';
            }
        $html .= '<div>';

        return $html;
    }


    /**
     * Render condition settings for specific action
     * 
     * @since 1.0.0
     * @param string $condition | Condition key
     * @return string HTML of rendered condition settings
     */
    public static function render_condition_settings( $condition ) {
        $html = '';

        $html .= self::get_condition_options( $condition );

        switch ( $condition ) {
            case 'user_role':
                global $wp_roles;
            
                $roles = $wp_roles->get_names();

                $html .= '<div class="mb-4">';
                    $html .= '<label for="user_role" class="form-label">' . esc_html__('Função do usuário:', 'joinotify') . '</label>';
                    $html .= '<select id="user_role" name="condition[user_role]" class="form-control get-condition-value">';
                
                    foreach ( $roles as $role_key => $role_name ) {
                        $translated_role_name = translate_user_role( $role_name );
                        $html .= '<option value="' . esc_attr( $role_key ) . '">' . esc_html( $translated_role_name ) . '</option>';
                    }
                
                    $html .= '</select>';
                $html .= '</div>';

                break;            
            case 'user_meta':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="user_meta" class="form-label">' . esc_html__('Metadados do usuário:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="user_meta" name="condition[user_meta][user_metadata]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Nome do metadado', 'joinotify' ) .'">';
                $html .= '</div>';

                $html .= '<div class="mb-4">';
                    $html .= '<label for="user_meta" class="form-label">' . esc_html__('Valor do metadado:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="user_meta" name="condition[user_meta][value_metadata]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Valor do metadado', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'user_last_login':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="user_last_login" class="form-label">' . esc_html__('Tempo desde o último login:', 'joinotify') . '</label>';
                    $html .= '<input type="number" id="user_last_login" name="condition[user_last_login]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Número de horas', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'post_type':
                $html .= '<div class="mb-4">';
                    // Retrieves all registered post types
                    $post_types = get_post_types( array( 'public' => true ), 'objects' );
                    $html .= '<label for="post_type" class="form-label">' . esc_html__('Tipo de post:', 'joinotify') . '</label>';
                    $html .= '<select id="post_type" name="condition[post_type]" class="form-control get-condition-value">';
                    
                    foreach ( $post_types as $post_type_key => $post_type_object ) {
                        $html .= '<option value="' . esc_attr( $post_type_key ) . '">' . esc_html( $post_type_object->labels->name ) . '</option>';
                    }

                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'post_author':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="post_author" class="form-label">' . esc_html__('Autor do post:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="post_author" name="condition[post_author]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Nome do autor', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'order_status':
                $html .= '<div class="mb-4">';
                    // Retrieves all WooCommerce order statuses
                    if ( function_exists('wc_get_order_statuses') ) {
                        $order_statuses = wc_get_order_statuses();

                        $html .= '<label for="order_status" class="form-label">' . esc_html__('Status do pedido:', 'joinotify') . '</label>';
                        $html .= '<select id="order_status" name="condition[order_status]" class="form-control get-condition-value">';
                        
                        foreach ( $order_statuses as $status_key => $status_name ) {
                            $html .= '<option value="' . esc_attr( $status_key ) . '">' . esc_html( $status_name ) . '</option>';
                        }

                        $html .= '</select>';
                    }
                $html .= '</div>';

                break;
            case 'order_total':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="order_total" class="form-label">' . esc_html__('Valor total do pedido:', 'joinotify') . '</label>';
                    $html .= '<input type="number" id="order_total" name="condition[order_total]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Valor total do pedido', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'products_purchased':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="products_purchased" class="form-label">' . esc_html__('Produtos adquiridos:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="products_purchased" name="condition[products_purchased]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'ID dos produtos separados por vírgula', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'customer_email':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="customer_email" class="form-label">' . esc_html__('E-mail do cliente:', 'joinotify') . '</label>';
                    $html .= '<input type="email" id="customer_email" name="condition[customer_email]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'E-mail do cliente', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'refund_amount':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="refund_amount" class="form-label">' . esc_html__('Valor do reembolso:', 'joinotify') . '</label>';
                    $html .= '<input type="number" id="refund_amount" name="condition[refund_amount]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Valor do reembolso', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'subscription_status':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="subscription_status" class="form-label">' . esc_html__('Status da assinatura:', 'joinotify') . '</label>';
                    $html .= '<select id="subscription_status" name="condition[subscription_status]" class="form-control get-condition-value">';
                        $html .= '<option value="active">' . esc_html__( 'Ativa', 'joinotify' ) . '</option>';
                        $html .= '<option value="on-hold">' . esc_html__( 'Em espera', 'joinotify' ) . '</option>';
                        $html .= '<option value="cancelled">' . esc_html__( 'Cancelada', 'joinotify' ) . '</option>';
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'renewal_payment':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="renewal_payment" class="form-label">' . esc_html__('Pagamento da renovação:', 'joinotify') . '</label>';
                    $html .= '<select id="renewal_payment" name="condition[renewal_payment]" class="form-control get-condition-value">';
                        $html .= '<option value="yes">' . esc_html__( 'Sim', 'joinotify' ) . '</option>';
                        $html .= '<option value="no">' . esc_html__( 'Não', 'joinotify' ) . '</option>';
                    $html .= '</select>';
                $html .= '</div>';

                break;
            case 'cart_total':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="cart_total" class="form-label">' . esc_html__('Valor total do carrinho:', 'joinotify') . '</label>';
                    $html .= '<input type="number" id="cart_total" name="condition[cart_total]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Valor total do carrinho', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'items_in_cart':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="items_in_cart" class="form-label">' . esc_html__('Produtos no carrinho:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="items_in_cart" name="condition[items_in_cart]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'ID dos produtos no carrinho separados por vírgula', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'form_id':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="form_id" class="form-label">' . esc_html__('ID do formulário:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="form_id" name="condition[form_id]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'ID do formulário', 'joinotify' ) .'">';
                $html .= '</div>';

                break;
            case 'field_value':
                $html .= '<div class="mb-4">';
                    $html .= '<label for="field_value" class="form-label">' . esc_html__('Valor espefífico de um campo:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="field_value" name="condition[field_value]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'Valor do campo', 'joinotify' ) .'">';
                $html .= '</div>';

                $html .= '<div class="mb-4">';
                    $html .= '<label for="field_id" class="form-label">' . esc_html__('ID do campo:', 'joinotify') . '</label>';
                    $html .= '<input type="text" id="field_id" name="condition[field_id]" class="form-control get-condition-value" placeholder="'. esc_attr__( 'ID do campo', 'joinotify' ) .'">';
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
     * @param string $condition | Condition key
     */
    public static function get_condition_options( $condition ) {
        $condition_options = array(
            'is' => esc_html__( 'É', 'joinotify' ),
            'is_not' => esc_html__( 'Não é', 'joinotify' ),
            'empty' => esc_html__( 'Vazio', 'joinotify' ),
            'not_empty' => esc_html__( 'Não está vazio', 'joinotify' ),
            'contains' => esc_html__( 'Contém', 'joinotify' ),
            'not_contain' => esc_html__( 'Não contém', 'joinotify' ),
            'start_with' => esc_html__( 'Começa com', 'joinotify' ),
            'finish_with' => esc_html__( 'Termina com', 'joinotify' ),
            'bigger_then' => esc_html__( 'Maior que', 'joinotify' ),
            'less_than' => esc_html__( 'Menor que', 'joinotify' ),
        );

        $html = '<div class="mb-4">';
            $html .= '<label class="form-label">'. esc_html__( 'Condição: *', 'joinotify' ) .'</label>';
            $html .= '<select class="form-select get-condition-type">';
                $html .= '<option value="none">'. esc_html__( 'Selecione uma condição', 'joinotify' ) .'</option>';
                $allowed_conditions = Conditions::check_condition_type( $condition );

                foreach ( $condition_options as $option => $value ) {
                    if ( in_array( $option, $allowed_conditions ) ) {
                        $html .= '<option value="'. esc_attr( $option ) .'">'. $value .'</option>';
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
    public static function render_placeholders_list( $post_id ) {
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

                            foreach( Placeholders::get_placeholders_list( $trigger ) as $placeholder => $value ) {
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
     * @return void
     */
    public static function workflow_title_modal_content() {
        $current_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        if ( strpos( $current_url, 'admin.php?page=joinotify-workflows-builder' ) !== false ) : ?>
            <div class="modal fade" id="edit_workflow_title" tabindex="-1" aria-labelledby="edit_workflow_title_label" aria-hidden="true">
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
}