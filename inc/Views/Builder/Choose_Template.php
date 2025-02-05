<?php

use MeuMouse\Joinotify\Builder\Workflow_Manager;
use MeuMouse\Joinotify\Builder\Utils;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="joinotify_choose_template_container" class="active">
    <!-- INITIALIZE JOINOTIFY BUILDER -->
    <div id="joinotify_start_choose_container" class="choose-template-container active slide-animation slide-left-animation">
        <div class="d-grid justify-content-center pt-5 mb-4">
            <h3 class="title text-center"><?php esc_html_e( 'Como você gostaria de criar seu novo fluxo?', 'joinotify' ) ?></h3>
            <span class="fs-lg text-muted text-center"><?php esc_html_e( 'Clique no botão do modelo de sua preferência para iniciar a criação do fluxo.', 'joinotify' ) ?></span>
        </div>

        <div class="d-grid justify-items-center justify-content-center pb-5">
            <div class="d-flex align-items-center">
                <?php foreach ( Workflow_Manager::get_start_templates() as $template => $value ) : ?>
                    <div class="card m-4 start-action-item">
                        <div class="card-header border-bottom-0 d-flex justify-content-center">
                            <?php echo $value['icon'] ?>
                        </div>

                        <div class="card-body align-items-center">
                            <h5 class="card-title fs-5 fw-semibold"><?php esc_html_e( $value['title'] ) ?></h5>
                            <p class="card-text mb-4 mt-1 text-muted text-center"><?php esc_html_e( $value['description'] ) ?></p>

                            <button type="button" class="btn btn-outline-primary choose-template" data-template="<?php esc_attr_e( $template ) ?>"><?php esc_html_e( $value['button_title'] ) ?></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <a class="btn btn-link text-dark d-flex align-items-center justify-content-center mt-3 pt-4 w-fit m-auto" href="<?php echo esc_url( admin_url('admin.php?page=joinotify') ) ?>">
                <svg class="icon icon-lg icon-dark me-2" xmlns="http://www.w3.org/2000/svg"><path d="M16 13v-2H7V8l-5 4 5 4v-3z"></path><path d="M20 3h-9c-1.103 0-2 .897-2 2v4h2V5h9v14h-9v-4H9v4c0 1.103.897 2 2 2h9c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2z"></path></svg>
                <?php esc_html_e( 'Voltar para o painel', 'joinotify' ) ?>
            </a>
        </div>
    </div>

    <!-- CHOOSE TEMPLATE LIBRARY -->
    <div id="joinotify_template_library_container" class="choose-template-container slide-animation slide-right-animation">
        <div class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="<?php esc_attr_e( 'Pesquisar por fluxos', 'joinotify' ) ?>">
                
                <select id="joinotify_filter_library_categories" class="form-select">
                    <option value="all"><?php esc_html_e( 'Todos os fluxos', 'joinotify' ) ?></option>    

                    <?php foreach ( Utils::get_template_categories() as $categorie => $value ) : ?>
                        <option value="<?php esc_attr_e( $categorie ) ?>"><?php esc_html_e( $value ) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- DISPLAY JSON TEMPLATES ON RESPONSE REQUEST -->
        <div class="joinotify-templates-group"></div>
    </div>

    <!-- IMPORT TEMPLATE CONTAINER -->
    <div id="joinotify_import_template_container" class="choose-template-container slide-animation slide-right-animation">
        <div class="card p-0 m-0">
            <div class="card-header border-bottom-0 px-4 pt-5">
                <h3 class="title fs-5"><?php esc_html_e( 'Importar modelo', 'joinotify' ) ?></h3>
                <span class="text-muted fs-lg"><?php esc_html_e( 'Envie seu arquivo .json para importar o modelo da automação de mensagens.', 'joinotify' ) ?></span>
            </div>

            <div class="card-body align-items-start w-100 px-4 mb-3">
                <div class="dropzone mt-4" id="joinotify_import_file_zone">
                    <div class="drag-text">
                        <svg class="drag-and-drop-file-icon" fill="#008aff" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 503.607 503.607" xml:space="preserve" stroke="#008aff"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g transform="translate(1 1)"> <g> <g> <path d="M385.098,57.754h-41.967v-8.393c0-9.233-7.554-16.787-16.787-16.787h-25.18V24.18c0-14.269-10.911-25.18-25.18-25.18 h-50.361c-14.269,0-25.18,11.751-25.18,25.18v8.393h-25.18c-9.233,0-16.787,7.554-16.787,16.787v8.393h-41.967 c-23.502,0-41.967,18.466-41.967,41.967v360.918c0,23.502,18.466,41.967,41.967,41.967h268.59 c23.502,0,41.967-18.466,41.967-41.967V99.721C427.066,76.22,408.6,57.754,385.098,57.754z M175.262,49.361h33.574 c5.036,0,8.393-3.357,8.393-8.393V24.18c0-5.036,3.357-8.393,8.393-8.393h50.361c5.036,0,8.393,4.197,8.393,8.393v16.787 c0,5.036,3.357,8.393,8.393,8.393h33.574v16.787v41.967H175.262V66.148V49.361z M410.279,460.639 c0,14.269-10.911,25.18-25.18,25.18h-268.59c-14.269,0-25.18-10.911-25.18-25.18V99.721c0-14.269,10.911-25.18,25.18-25.18 h41.967v33.574c0,9.233,7.554,16.787,16.787,16.787h151.082c9.233,0,16.787-7.554,16.787-16.787V74.541h41.967 c14.269,0,25.18,10.911,25.18,25.18V460.639z"></path> <path d="M197.925,228.141c-3.357-3.357-8.393-4.197-11.751-0.839l-67.148,58.754c-1.679,1.679-2.518,4.197-2.518,6.715 c0,2.518,0.839,5.036,2.518,6.715l67.148,58.754c1.679,0.839,4.197,1.679,5.875,1.679c2.518,0,5.036-0.839,5.875-3.357 c3.357-3.357,2.518-8.393-0.839-11.751l-59.593-52.039l59.593-52.879C200.443,236.534,201.282,231.498,197.925,228.141z"></path> <path d="M314.593,228.98c-3.357-3.357-8.393-2.518-11.751,0.839c-3.357,3.357-2.518,8.393,0.839,11.751l59.593,52.039 l-59.593,52.039c-3.357,3.357-4.197,8.393-0.839,11.751c1.679,1.679,4.197,2.518,6.715,2.518c1.679,0,4.197-0.839,5.036,0 l67.148-58.754c1.679-1.679,2.518-4.197,2.518-6.715s-0.839-5.036-2.518-6.715L314.593,228.98z"></path> <path d="M279.341,226.462c-4.197-1.679-9.233,0-10.911,4.197l-50.361,117.508c-1.679,4.197,0,9.233,4.197,10.911 c0.839,0.839,2.518,0.839,3.357,0.839c3.357,0,6.715-1.679,7.554-5.036l50.361-117.508 C285.216,233.177,283.538,228.141,279.341,226.462z"></path></g></g></g></g></svg>
                        <?php esc_html_e( 'Arraste e solte o arquivo .json aqui', 'joinotify' ); ?>
                    </div>

                    <div class="file-list"></div>
                    
                    <div class="drag-and-drop-file">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="upload_template_file" name="upload_template_file" hidden>
                            <label class="custom-file-label" for="upload_template_file"><?php esc_html_e( 'Ou clique para procurar seu arquivo', 'joinotify' ); ?></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center p-4">
                <button type="button" id="joinotify_cancel_import_template" class="btn btn-outline-secondary"><?php esc_html_e( 'Cancelar', 'joinotify' ) ?></button>
                <button type="button" id="joinotify_send_import_files" class="btn btn-primary" disabled><?php esc_html_e( 'Importar', 'joinotify' ) ?></button>
            </div>
        </div>
    </div>
</div>