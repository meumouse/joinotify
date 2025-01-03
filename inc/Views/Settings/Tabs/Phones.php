<?php

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Components;
use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="phones" class="nav-content ms-5 mt-4">
    <div class="mb-5">
        <div class="d-flex align-items-center">
            <div class="w-25 me-3">
                <h3><?php esc_html_e( 'Telefone para testes', 'joinotify' ); ?></h3>
                <span class="joinotify-description"><?php esc_html_e( 'Informe um telefone para receber mensagens de teste para disparar no construtor. Use o formato internacional, informando apenas números: 5541987111527', 'joinotify' ); ?></span>
            </div>

            <input type="text" class="form-control w-25" name="test_number_phone" id="test_number_phone" value="<?php echo Admin::get_setting('test_number_phone') ?>" placeholder="<?php esc_attr_e( '5541987111527', 'joinotify' ) ?>"/>
        </div>
    </div>

    <h3 class="mb-3"><?php esc_html_e( 'Remetentes cadastrados', 'joinotify' ); ?></h3>

    <div id="joinotify_current_phones_senders">
        <?php echo Components::current_phones_senders(); ?>
    </div>

    <div class="mt-5">
        <div class="d-flex align-items-center">
            <button id="joinotify_add_new_phone_trigger" class="btn btn-primary"><?php esc_html_e( 'Adicionar novo telefone', 'joinotify' ); ?></button>
            
            <div id="joinotify_add_new_phone_container" class="joinotify-popup-container">
                <div class="joinotify-popup-content popup-sm">
                    <div class="joinotify-popup-header">
                        <h5 class="joinotify-popup-title"><?php esc_html_e( 'Adicionar novo remetente para WhatsApp', 'joinotify' ); ?></h5>
                        <button id="joinotify_add_new_phone_close" class="btn-close fs-lg" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ); ?>"></button>
                    </div>

                    <div class="joinotify-popup-body my-3">
                        <div class="placeholder-content" style="width: 100%; height: 5rem;"></div>
                    </div>
                </div>
            </div>

            <?php $phones_senders = get_option('joinotify_get_phones_senders');
            
            if ( ! empty( $phones_senders ) ) : ?>
                <button id="joinotify_send_message_test_trigger" class="btn btn-outline-primary ms-3"><?php esc_html_e( 'Enviar mensagem teste', 'joinotify' ); ?></button>

                <div id="joinotify_send_message_test_container" class="joinotify-popup-container">
                    <div class="joinotify-popup-content popup-sm">
                        <div class="joinotify-popup-header">
                            <h5 class="joinotify-popup-title"><?php esc_html_e( 'Enviar uma mensagem de teste', 'joinotify' ); ?></h5>
                            <button id="joinotify_send_message_test_close" class="btn-close fs-lg" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ); ?>"></button>
                        </div>

                        <div class="joinotify-popup-body my-3">
                            <div class="d-grid justify-items-start mb-4">
                                <label for="joinotify_select_sender_test" class="form-label"><?php esc_html_e( 'Escolha um remetente:', 'joinotify' ); ?></label>
                                <select id="joinotify_select_sender_test" class="form-select form-select-lg mw-100">
                                    <?php foreach ( $phones_senders as $phone ) : ?>
                                        <option value="<?php esc_attr_e( $phone ) ?>" class="get-sender-number"><?php echo esc_html( Helpers::format_phone_number( $phone ) ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid justify-items-start mb-4">
                                <label for="joinotify_get_phone_receive_test" class="form-label"><?php esc_html_e( 'Informe o telefone destinatário - Apenas números com DDI + DDD:', 'joinotify' ); ?></label>
                                <input type="text" id="joinotify_get_phone_receive_test" class="form-control" value="<?php echo Admin::get_setting('test_number_phone') ?>"/>
                            </div>

                            <div class="d-grid justify-items-start mb-4">
                                <label for="joinotify_get_test_message" class="form-label"><?php esc_html_e( 'Mensagem teste:' ); ?></label>
                                <textarea id="joinotify_get_test_message" class="form-control"></textarea>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button id="joinotify_send_test_message" class="btn btn-primary" disabled><?php esc_html_e( 'Enviar mensagem' ); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>