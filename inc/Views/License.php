<?php

use MeuMouse\Joinotify\API\License;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div class="joinotify-admin-title-container">
    <svg id="joinotify-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 703 882.5"><path d="M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z" transform="translate(-205.66 -112.03)" style="fill:#22c55e"/></svg>
    <h1 class="joinotify-admin-section-tile mb-0"><?php echo esc_html( 'Joinotify: Automatize suas notificações. Simplifique sua comunicação.', 'joinotify' ) ?></h1>
</div>

<div class="joinotify-admin-title-description">
    <p><?php esc_html_e( 'Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify. Se precisar de ajuda para configurar, acesse nossa', 'joinotify' ) ?>
        <a class="fancy-link" href="<?php esc_attr_e( JOINOTIFY_DOCS_URL ) ?>" target="_blank"><?php esc_html_e( 'Central de ajuda', 'joinotify' ) ?></a>
    </p>
</div>

<div id="joinotify_license_area" class="bg-white p-5 ps-0 mt-5 rounded-4 me-3">
    <table class="form-table">
        <tbody>
            <?php if ( License::is_valid() ) : ?>
                <tr>
                    <td class="d-grid">
                        <h3 class="mb-4"><?php esc_html_e( 'Informações sobre a licença:', 'joinotify' ); ?></h3>

                        <span class="mb-2"><?php esc_html_e( 'Status da licença:', 'joinotify' ) ?>
                            <?php if ( License::is_valid() ) : ?>
                                <span class="badge bg-translucent-success rounded-pill"><?php _e(  'Válida', 'joinotify' );?></span>
                            <?php elseif ( empty( get_option('joinotify_license_key') ) ) : ?>
                                <span class="fs-sm"><?php _e(  'Nenhuma licença informada', 'joinotify' );?></span>
                            <?php else : ?>
                                <span class="badge bg-translucent-danger rounded-pill"><?php _e(  'Inválida', 'joinotify' );?></span>
                            <?php endif; ?>
                        </span>

                        <?php if ( License::is_valid() ) :
                            $license_key = get_option('joinotify_license_key');

                            if ( strpos( $license_key, 'CM-' ) === 0 ) : ?>
                                <span class="mb-2"><?php printf( esc_html__( 'Assinatura: Clube M - %s', 'joinotify' ), License::license_title() ) ?></span>
                            <?php else : ?>
                                <span class="mb-2"><?php printf( esc_html__( 'Tipo da licença: %s', 'joinotify' ), License::license_title() ) ?></span>
                            <?php endif; ?>

                            <span class="mb-2"><?php printf( esc_html__( 'Licença expira em: %s', 'joinotify' ), License::license_expire() ) ?></span>
                            
                            <span class="mb-2"><?php esc_html_e( 'Sua chave de licença:', 'joinotify' ) ?>
                                <?php if ( ! empty( $license_key ) ) :
                                    echo esc_attr( substr( $license_key, 0, 9 ) . "XXXXXXXX-XXXXXXXX" . substr( $license_key, -9 ) );
                                else :
                                    esc_html_e(  'Não disponível', 'joinotify' );
                                endif; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <td class="d-flex align-items-center">
                        <button type="submit" id="joinotify_deactive_license" class="btn btn-sm btn-primary" name="joinotify_deactive_license"><?php esc_html_e( 'Desativar licença', 'joinotify' ); ?></button>
                    </td>
                </tr>
            <?php else : ?>
                <tr>
                    <td>
                        <h2><?php esc_html_e( 'Informe sua licença para começar a usar o Joinotify', 'joinotify' ); ?></h2>
                    </td>
                </tr>

                <?php if ( get_option('joinotify_alternative_license_activation') === 'yes' ) : ?>
                    <tr>
                        <td>
                            <span class="h4 d-block"><?php esc_attr_e( 'Notamos que teve problemas de conexão ao tentar ativar sua licença', 'joinotify' ); ?></span>
                            <span class="d-block text-muted"><?php esc_attr_e( 'Você pode fazer upload do arquivo .key da licença para fazer sua ativação manual.', 'joinotify' ); ?></span>
                            <a class="fancy-link mt-2 mb-3" href="https://meumouse.com/minha-conta/licenses/?domain=<?php echo urlencode( License::get_domain() ); ?>&license_key=<?php echo urlencode( get_option('joinotify_temp_license_key') ); ?>&app_version=<?php echo urlencode( JOINOTIFY_VERSION ); ?>&product_id=<?php echo ( strpos( get_option('joinotify_temp_license_key'), 'CM-' ) === 0 ) ? '7' : '8'; ?>&settings_page=<?php echo urlencode( License::get_domain() . '/wp-admin/admin.php?page=joinotify-license' ); ?>" target="_blank"><?php esc_html_e( 'Clique aqui para gerar seu arquivo de licença', 'joinotify' ) ?></a>

                            <div class="drop-file-license-key">
                                <div class="dropzone-license mt-4" id="license_key_zone">
                                    <div class="drag-text">
                                        <svg class="drag-and-drop-file-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M19.937 8.68c-.011-.032-.02-.063-.033-.094a.997.997 0 0 0-.196-.293l-6-6a.997.997 0 0 0-.293-.196c-.03-.014-.062-.022-.094-.033a.991.991 0 0 0-.259-.051C13.04 2.011 13.021 2 13 2H6c-1.103 0-2 .897-2 2v16c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2V9c0-.021-.011-.04-.013-.062a.99.99 0 0 0-.05-.258zM16.586 8H14V5.414L16.586 8zM6 20V4h6v5a1 1 0 0 0 1 1h5l.002 10H6z"></path></svg>
                                        <?php esc_html_e( 'Arraste e solte o arquivo .key aqui', 'joinotify' ); ?>
                                    </div>

                                    <div class="file-list"></div>
                                    
                                    <div class="drag-and-drop-file">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="upload_license_key" name="upload_license_key" hidden>
                                            <label class="custom-file-label mb-4" for="upload_license_key"><?php esc_html_e( 'Ou clique para procurar seu arquivo', 'joinotify' ); ?></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <tr>
                        <td class="d-grid">
                            <a class="btn btn-primary mt-2 mb-4 d-inline-flex w-fit" href="https://meumouse.com/plugins/joinotify/?utm_source=wordpress&utm_medium=license-page&utm_campaign=joinotify" target="_blank">
                                <svg class="icon icon-white me-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <path d="M13.5 16.5854C13.5 17.4138 12.8284 18.0854 12 18.0854C11.1716 18.0854 10.5 17.4138 10.5 16.5854C10.5 15.7569 11.1716 15.0854 12 15.0854C12.8284 15.0854 13.5 15.7569 13.5 16.5854Z"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M6.33367 10C6.20971 9.64407 6.09518 9.27081 5.99836 8.88671C5.69532 7.68444 5.54485 6.29432 5.89748 4.97439C6.26228 3.60888 7.14664 2.39739 8.74323 1.59523C10.3398 0.793061 11.8397 0.806642 13.153 1.32902C14.4225 1.83396 15.448 2.78443 16.2317 3.7452C16.4302 3.98851 16.6166 4.23669 16.7907 4.48449C17.0806 4.89706 16.9784 5.45918 16.5823 5.7713C16.112 6.14195 15.4266 6.01135 15.0768 5.52533C14.9514 5.35112 14.8197 5.17831 14.6819 5.0094C14.0088 4.18414 13.2423 3.51693 12.4138 3.18741C11.6292 2.87533 10.7252 2.83767 9.64112 3.38234C8.55703 3.92702 8.04765 4.6748 7.82971 5.49059C7.5996 6.35195 7.6774 7.36518 7.93771 8.39788C8.07953 8.96054 8.26936 9.50489 8.47135 10H18C19.6569 10 21 11.3431 21 13V20C21 21.6569 19.6569 23 18 23H6C4.34315 23 3 21.6569 3 20V13C3 11.3431 4.34315 10 6 10H6.33367ZM19 13C19 12.4477 18.5523 12 18 12H6C5.44772 12 5 12.4477 5 13V20C5 20.5523 5.44772 21 6 21H18C18.5523 21 19 20.5523 19 20V13Z"></path></g></svg>	
                                <span><?php esc_html_e(  'Comprar licença', 'joinotify' );?></span>	
                            </a>

                            <span class="bg-translucent-success fw-medium rounded-2 px-3 py-2 mb-4 d-flex align-items-center w-fit">
                                <svg class="icon icon-success me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
                                <?php esc_html_e( 'Informe sua licença abaixo para desbloquear todos os recursos.', 'joinotify' ) ?>
                            </span>

                            <span class="form-label d-block mt-2"><?php esc_html_e( 'Código da licença', 'joinotify' ) ?></span>
                            
                            <div class="input-group" style="max-width: 550px;">
                                <input class="form-control" type="text" placeholder="XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX" id="joinotify_license_key" name="joinotify_license_key" size="50" value="<?php echo get_option( 'joinotify_license_key' ) ?>" />
                                <button type="submit" id="joinotify_active_license" class="btn btn-primary button-loading" name="joinotify_active_license"><?php esc_html_e( 'Ativar licença', 'joinotify' ); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endif;
            endif; ?>
        </tbody>
    </table>
</div>