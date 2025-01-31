<?php

use MeuMouse\Joinotify\Builder\Actions;
use MeuMouse\Joinotify\Builder\Placeholders;

use MeuMouse\Joinotify\Validations\Media_Types;

use MeuMouse\Joinotify\Core\Helpers;

use MeuMouse\Joinotify\Cron\Schedule;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="joinotify_actions_group" class="">
    <div class="actions-header mb-5">
        <div class="d-flex justify-content-between">
            <div>
                <h2 class="sidebar-title fs-5"><?php esc_html_e( 'Adicionar uma ação', 'joinotify' ) ?></h2>
                <span class="text-muted fs-md"><?php esc_html_e( 'Selecione uma ou mais ações para o fluxo da automação.', 'joinotify' ) ?></span>
            </div>

            <button type="button" id="joinotify_close_actions_group" class="btn-close"></button>
        </div>
    </div>

    <?php
    /**
     * Add custom content before actions body
     * 
     * @since 1.0.0
     */
    do_action('Joinotify/Builder/Actions/Before_Actions_Body'); ?>

    <div class="actions-body">
        <div class="actions-wrapper">
            <?php foreach ( Actions::get_all_actions() as $action ) : ?>
                <div class="action-item <?php esc_attr_e( $action['class'] ) ?>" data-action="<?php esc_attr_e( $action['action'] ) ?>" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_<?php esc_attr_e( $action['action'] ) ?>">
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

                <div class="offcanvas offcanvas-end" data-action="<?php esc_attr_e( $action['action'] ) ?>" data-bs-scroll="false" data-bs-backdrop="false" tabindex="-1" id="offcanvas_<?php esc_attr_e( $action['action'] ) ?>" aria-labelledby="offcanvas_<?php esc_attr_e( $action['action'] ) ?>_label">
                    <div class="offcanvas-header p-4 mt-2 border-bottom justify-content-between">
                        <div class="d-flex align-items-center">
                            <?php if ( ! $action['external_icon'] ) : ?>
                                <div class="action-item-icon me-3"><?php echo $action['icon'] ?></div>
                            <?php endif; ?>

                            <h5 class="offcanvas-title" id="offcanvas_<?php esc_attr_e( $action['action'] ) ?>_label"><?php esc_html_e( $action['title'] ) ?></h5>
                        </div>

                        <div class="d-flex align-items-center">
                            <?php if ( $action['action'] === 'send_whatsapp_message_text' ) : ?>
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

                    <div class="offcanvas-body p-4 py-5">
                        <!-- TIME DELAY CONTROLLERS -->
                        <?php if ( $action['action'] === 'time_delay' ) :
                            if ( Schedule::is_wp_cron_active() ) : ?>
                                <div class="mb-4">
                                    <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Selecione o tipo de atraso da próxima ação', 'joinotify' ) ?></span>
                                    <select class="form-select set-time-delay-type">
                                        <option value="period"><?php esc_html_e( 'Esperar tempo', 'joinotify' ) ?></option>
                                        <option value="date"><?php esc_html_e( 'Esperar até uma data', 'joinotify' ) ?></option>
                                    </select>
                                </div>

                                <div class="wait-time-period-container">
                                    <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Esperar por', 'joinotify' ) ?></span>
                                    
                                    <div class="input-group">
                                        <input type="number" class="form-control get-wait-value" /> 
                                        <select class="form-select get-wait-period">
                                            <option value="seconds"><?php esc_html_e( 'Segundo (s)', 'joinotify' ) ?></option>
                                            <option value="minute"><?php esc_html_e( 'Minuto (s)', 'joinotify' ) ?></option>
                                            <option value="hours"><?php esc_html_e( 'Hora (s)', 'joinotify' ) ?></option>
                                            <option value="day"><?php esc_html_e( 'Dia (s)', 'joinotify' ) ?></option>
                                            <option value="week"><?php esc_html_e( 'Semana (s)', 'joinotify' ) ?></option>
                                            <option value="month"><?php esc_html_e( 'Mês (es)', 'joinotify' ) ?></option>
                                            <option value="year"><?php esc_html_e( 'Ano (s)', 'joinotify' ) ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="wait-date-container">
                                    <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Esperar até', 'joinotify' ) ?></span>
                                    
                                    <div class="input-group">
                                        <input type="text" class="form-control dateselect get-date-value" placeholder="<?php esc_attr_e( 'Selecione uma data', 'joinotify' ) ?>"/>
                                        <input type="text" class="form-control get-time-value" placeholder="<?php esc_attr_e( 'Digite um horário (Opcional)', 'joinotify' ) ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Informe um horário no formato H:i - 20:03', 'joinotify' ) ?>"/>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="alert alert-warning d-flex align-items-center">
                                    <svg class="icon icon-lg icon-warning me-2 w-25" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M11.001 10h2v5h-2zM11 16h2v2h-2z"/><path d="M13.768 4.2C13.42 3.545 12.742 3.138 12 3.138s-1.42.407-1.768 1.063L2.894 18.064a1.986 1.986 0 0 0 .054 1.968A1.984 1.984 0 0 0 4.661 21h14.678c.708 0 1.349-.362 1.714-.968a1.989 1.989 0 0 0 .054-1.968L13.768 4.2zM4.661 19 12 5.137 19.344 19H4.661z"/></svg>
                                    <?php esc_html_e( 'A função WP-CRON está desabilitada neste site. Ative-o para usar a função Tempo de espera.', 'joinotify' ) ?>
                                </div>
                            <?php endif; ?>
                        <!-- STOP FUNNEL CONTROLLERS -->
                        <?php elseif ( $action['action'] === 'stop_funnel' ) : ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <svg class="icon icon-lg icon-info me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
                                <?php esc_html_e( 'Esta ação não necessita de configurações auxiliares.', 'joinotify' ) ?>
                            </div>

                        <!-- SEND WHATSAPP MESSAGE TEXT CONTROLLERS -->
                        <?php elseif ( $action['action'] === 'send_whatsapp_message_text' ) :
                            if ( get_user_meta( get_current_user_id(), 'joinotify_dismiss_placeholders_tip_user_meta', true ) !== 'hidden' ) : ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <?php echo __( '<strong>Dica: </strong> Você pode deixar textos em negrito, sublinhados, ou riscados com variáveis do WhatsApp. Veja mais detalhes na <a href="https://ajuda.meumouse.com/docs/joinotify/placeholders" class="alert-link" target="_blank">documentação do Joinotify</a>. <a id="joinotify_dismiss_placeholders_tip" class="alert-link mt-4 d-block" data-bs-dismiss="alert" href="#">Não mostrar novamente</a>', 'joinotify' ); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ) ?>"></button>
                                </div>
                            <?php endif; ?>

                            <div class="preview-whatsapp-message-sender"></div>

                            <div class="input-group mb-3">
                                <span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Remetente', 'joinotify' ) ?>">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16.712 13.288a.999.999 0 0 0-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a.999.999 0 0 0-1.414 0L2.586 6c-.38.38-.594.902-.586 1.435.023 1.424.4 6.37 4.298 10.268S15.142 21.977 16.566 22h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-3.999-4zM16.585 20c-1.248-.021-5.518-.356-8.873-3.712C4.346 12.922 4.02 8.637 4 7.414l2.005-2.005 2.586 2.586-1.293 1.293a1 1 0 0 0-.272.912c.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.993.993 0 0 0 .912-.271l1.293-1.293 2.586 2.586L16.585 20z"></path><path d="m16.795 5.791-4.497 4.497 1.414 1.414 4.497-4.497L21.005 10V2.995H14z"></path></svg>    
                                </span>
                                <select id="joinotify_get_whatsapp_phone_sender" class="form-select">
                                    <?php foreach ( get_option('joinotify_get_phones_senders') as $phone ) : ?>
                                        <option value="<?php esc_attr_e( $phone ) ?>" class="get-sender-number"><?php echo esc_html( Helpers::format_phone_number( $phone ) ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="input-group mb-3">
                                <span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Destinatário', 'joinotify' ) ?>">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16.712 13.288a.999.999 0 0 0-1.414 0l-1.597 1.596c-.824-.245-2.166-.771-2.99-1.596-.874-.874-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a1.03 1.03 0 0 0-1.414 0l-2.709 2.71c-.382.38-.597.904-.588 1.437.022 1.423.396 6.367 4.297 10.268C10.195 21.6 15.142 21.977 16.566 22h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-3.999-4zM16.585 20c-1.248-.021-5.518-.356-8.874-3.712C4.343 12.92 4.019 8.636 4 7.414l2.004-2.005L8.59 7.995 7.297 9.288c-.238.238-.34.582-.271.912.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.994.994 0 0 0 .912-.271l1.293-1.293 2.586 2.586L16.585 20z"></path><path d="M15.795 6.791 13.005 4v6.995H20l-2.791-2.79 4.503-4.503-1.414-1.414z"></path></svg>
                                </span>
                                <input id="joinotify_get_whatsapp_number_msg_text" type="text" class="form-control" value="" placeholder="<?php esc_attr_e( '+5541987111527', 'joinotify' ) ?>"/>
                            </div>

                            <div class="input-group mb-3">
                                <button class="btn btn-icon btn-outline-secondary icon-translucent emoji_picker">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M14.829 14.828a4.055 4.055 0 0 1-1.272.858 4.002 4.002 0 0 1-4.875-1.45l-1.658 1.119a6.063 6.063 0 0 0 1.621 1.62 5.963 5.963 0 0 0 2.148.903 6.042 6.042 0 0 0 2.415 0 5.972 5.972 0 0 0 2.148-.903c.313-.212.612-.458.886-.731.272-.271.52-.571.734-.889l-1.658-1.119a4.017 4.017 0 0 1-.489.592z"></path><circle cx="8.5" cy="10.5" r="1.5"></circle><circle cx="15.493" cy="10.493" r="1.493"></circle></svg>
                                </button>
                                <textarea type="text" id="joinotify_get_whatsapp_message_text" class="form-control set-whatsapp-message set-whatsapp-message-text" placeholder="<?php esc_attr_e( 'Mensagem', 'joinotify' ) ?>"></textarea>
                            </div>

                            <?php
                            /**
                             * Hook for add custom content on WhatsApp message text action footer
                             * 
                             * @since 1.1.0
                             */
                            do_action('Joinotify/Builder/Actions/Footer/Whatsapp_Message_Text'); ?>
                        <!-- SEND WHATSAPP MESSAGE MEDIA CONTROLLERS -->
                        <?php elseif ( $action['action'] === 'send_whatsapp_message_media' ) : ?>
                            <div class="preview-whatsapp-message-sender media"></div>

                            <div class="input-group mb-3">
                                <span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Remetente', 'joinotify' ) ?>">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16.712 13.288a.999.999 0 0 0-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a.999.999 0 0 0-1.414 0L2.586 6c-.38.38-.594.902-.586 1.435.023 1.424.4 6.37 4.298 10.268S15.142 21.977 16.566 22h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-3.999-4zM16.585 20c-1.248-.021-5.518-.356-8.873-3.712C4.346 12.922 4.02 8.637 4 7.414l2.005-2.005 2.586 2.586-1.293 1.293a1 1 0 0 0-.272.912c.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.993.993 0 0 0 .912-.271l1.293-1.293 2.586 2.586L16.585 20z"></path><path d="m16.795 5.791-4.497 4.497 1.414 1.414 4.497-4.497L21.005 10V2.995H14z"></path></svg>    
                                </span>
                                <select id="joinotify_get_whatsapp_phone_sender_media" class="form-select">
                                    <?php foreach ( get_option('joinotify_get_phones_senders') as $phone ) : ?>
                                        <option value="<?php esc_attr_e( $phone ) ?>" class="get-sender-number"><?php echo esc_html( Helpers::format_phone_number( $phone ) ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="input-group mb-3">
                                <span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Destinatário', 'joinotify' ) ?>">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16.712 13.288a.999.999 0 0 0-1.414 0l-1.597 1.596c-.824-.245-2.166-.771-2.99-1.596-.874-.874-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a1.03 1.03 0 0 0-1.414 0l-2.709 2.71c-.382.38-.597.904-.588 1.437.022 1.423.396 6.367 4.297 10.268C10.195 21.6 15.142 21.977 16.566 22h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-3.999-4zM16.585 20c-1.248-.021-5.518-.356-8.874-3.712C4.343 12.92 4.019 8.636 4 7.414l2.004-2.005L8.59 7.995 7.297 9.288c-.238.238-.34.582-.271.912.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.994.994 0 0 0 .912-.271l1.293-1.293 2.586 2.586L16.585 20z"></path><path d="M15.795 6.791 13.005 4v6.995H20l-2.791-2.79 4.503-4.503-1.414-1.414z"></path></svg>
                                </span>
                                <input id="joinotify_get_whatsapp_number_msg_media" type="text" class="form-control" value="" placeholder="<?php esc_attr_e( '+5541987111527', 'joinotify' ) ?>"/>
                            </div>

                            <div class="mb-3">
                                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Tipo de mídia', 'joinotify' ) ?></span>
                                <select id="joinotify_get_media_type" class="form-select">
                                    <?php foreach ( Media_Types::get_media_types() as $type => $value ) : ?>
                                        <option value="<?php esc_attr_e( $type ) ?>"><?php esc_html_e( $value ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="require-media-type-image mb-3">
                                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Adicionar mídia', 'joinotify' ) ?></span>
                                
                                <div class="input-group">
                                    <button id="joinotify_set_url_media" class="btn btn-icon btn-outline-secondary icon-translucent">
                                        <svg class="icon icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 5h13v7h2V5c0-1.103-.897-2-2-2H4c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h8v-2H4V5z"></path><path d="m8 11-3 4h11l-4-6-3 4z"></path><path d="M19 14h-2v3h-3v2h3v3h2v-3h3v-2h-3z"></path></svg>
                                    </button>

                                    <input type="text" id="joinotify_get_url_media" class="form-control" placeholder="<?php esc_attr_e( 'URL da mídia', 'joinotify' ) ?>"/>
                                </div>
                            </div>

                            <?php
                            /**
                             * Hook for add custom content on WhatsApp message media action footer
                             * 
                             * @since 1.1.0
                             */
                            do_action('Joinotify/Builder/Actions/Footer/Whatsapp_Message_Media'); ?>
                        <?php endif; ?>
                    </div>

                    <div class="offcanvas-footer p-4 d-flex justify-content-end">
                        <button id="add_action_<?php esc_attr_e( $action['action'] ) ?>" class="btn btn-primary add-funnel-action"><?php esc_html_e( 'Adicionar ação', 'joinotify' ) ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    /**
     * Add custom content after actions body
     * 
     * @since 1.0.0
     */
    do_action('Joinotify/Builder/Actions/After_Actions_Body'); ?>
</div>