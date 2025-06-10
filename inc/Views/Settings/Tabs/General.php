<?php

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Validations\Country_Codes;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="general" class="nav-content">
    <table class="form-table">
        <tbody>
            <tr>
                <th>
                    <?php esc_html_e( 'Código padrão do país', 'joinotify' ); ?>
                    <span class="joinotify-description"><?php esc_html_e( 'Selecione um país para definir o código padrão. O código padrão do país será usado quando não for possível obter o código de país do telefone.', 'joinotify' ); ?></span>
                </th>
                <td>
                    <select name="joinotify_default_country_code" id="joinotify_default_country_code" class="form-select form-select-lg">
                        <option class="joinotify-cc" value="0" data-country="0" <?php selected( Admin::get_setting('joinotify_default_country_code'), '0' ); ?>>
                            <?php esc_html_e( 'Nenhum', 'joinotify' ); ?>
                        </option>
                        
                        <?php foreach ( Country_Codes::build_country_code_select() as $country ) : ?>
                            <option class="joinotify-cc" value="<?php echo esc_attr( $country['code'] ); ?>" data-country="<?php echo esc_attr( $country['country'] ); ?>" <?php selected( Admin::get_setting('joinotify_default_country_code'), $country['code'] ); ?>>
                                <?php echo esc_html( $country['country'] . ' (+' . $country['code'] . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <?php esc_html_e( 'Ativar Proxy API', 'joinotify' ); ?>
                    <span class="joinotify-description"><?php esc_html_e( 'Ative essa opção para ativar endpoints neste site para processar requisições de API do Joinotify.', 'joinotify' ); ?></span>
                </th>
                <td class="d-flex align-items-center">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="toggle-switch" id="enable_proxy_api" name="enable_proxy_api" value="yes" <?php checked( Admin::get_setting('enable_proxy_api') === 'yes' ); ?> />
                    </div>

                    <div class="require-proxy-api">
                        <button id="proxy_api_settings_trigger" class="btn btn-outline-primary ms-3"><?php esc_html_e( 'Configurar', 'joinotify' ); ?></button>

                        <div id="proxy_api_settings_container" class="joinotify-popup-container">
                            <div class="joinotify-popup-content popup-sm">
                                <div class="joinotify-popup-header">
                                    <h5 class="joinotify-popup-title"><?php esc_html_e( 'Configurar Proxy API', 'joinotify' ); ?></h5>
                                    <button id="proxy_api_settings_close" class="btn-close fs-lg" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ); ?>"></button>
                                </div>

                                <div class="joinotify-popup-body my-3">
                                    <div class="d-grid justify-items-start mb-4">
                                        <label for="send_text_proxy_api_route" class="form-label"><?php esc_html_e( 'Rota para envio de mensagens de texto:', 'joinotify' ); ?></label>
                                        <input type="text" class="form-control" id="send_text_proxy_api_route" name="send_text_proxy_api_route" value="<?php echo Admin::get_setting('send_text_proxy_api_route') ?>" placeholder="<?php esc_attr_e( 'send-message/text', 'joinotify' ) ?>"/>
                                    </div>

                                    <div class="d-grid justify-items-start mb-4">
                                        <label for="send_media_proxy_api_route" class="form-label"><?php esc_html_e( 'Rota para envio de mensagens de mídia:', 'joinotify' ); ?></label>
                                        <input type="text" class="form-control" id="send_media_proxy_api_route" name="send_media_proxy_api_route" value="<?php echo Admin::get_setting('send_media_proxy_api_route') ?>" placeholder="<?php esc_attr_e( 'send-message/media', 'joinotify' ) ?>"/>
                                    </div>

                                    <div class="d-grid justify-items-start mb-4">
                                        <label for="proxy_api_key" class="form-label"><?php esc_html_e( 'Chave de API:', 'joinotify' ); ?></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="proxy_api_key" name="proxy_api_key" value="<?php echo Admin::get_setting('proxy_api_key') ?>" placeholder="" />
                                            <button id="joinotify_generate_proxy_api_key" class="btn btn-outline-primary"><?php esc_html_e( 'Gerar chave', 'joinotify' ); ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th>
                    <?php esc_html_e( 'Receber avisos quando WhatsApp estiver desconectado', 'joinotify' ); ?>
                    <span class="joinotify-description"><?php esc_html_e( 'Ative essa opção para enviar uma notificação ao remetente quando a conexão não for estabelecida.', 'joinotify' ); ?></span>
                </th>
                <td class="d-flex align-items-center">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="toggle-switch" id="enable_send_disconnect_notifications" name="enable_send_disconnect_notifications" value="yes" <?php checked( Admin::get_setting('enable_send_disconnect_notifications') === 'yes' ); ?> />
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>