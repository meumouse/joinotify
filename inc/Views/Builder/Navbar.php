<?php

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="joinotify_builder_navbar" class="builder-navbar bg-white w-100">
    <div class="d-flex align-items-center navbar-items-start">
        <div class="static-logo p-3 me-5">
            <svg class="navbar-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 703 882.5">
                <path d="M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z" transform="translate(-205.66 -112.03)" style="fill:#22c55e;"></path>
            </svg>
        </div>

        <div id="joinotify_workflow_header_title_container" class="d-flex align-items-center">
            <h1 id="joinotify_workflow_title" class="fw-semibold fs-5 me-3 mb-0"></h1>
        </div>
    </div>

    <div class="joinotify-navbar-actions d-flex align-items-center">
        <button type="button" id="joinotify_builder_run_test" class="btn btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Rodar um teste da automação. Obs.: Atrasos e condições não são considerados.', 'joinotify' ) ?>"><?php esc_html_e( 'Rodar teste', 'joinotify' ) ?></button>

        <div class="form-check form-switch me-4 d-flex justify-content-center mb-0 w-fit">
            <span id="joinotify_workflow_status_title" class="fs-sm fw-semibold me-2"><?php esc_html_e( 'Inativo', 'joinotify' ) ?></span>
            <input type="checkbox" class="toggle-switch status" id="joinotify_workflow_status_switch" name="joinotify_workflow_status_switch" disabled value="yes" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Status do fluxo', 'joinotify' ) ?>"/>
        </div>

        <div class="btn-group me-3">
            <button type="button" id="joinotify_navbar_actions" class="btn btn-sm icon-translucent" data-bs-toggle="dropdown" aria-expanded="false">
                <svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>
            </button>

            <ul class="dropdown-menu builder-dropdown shadow-sm">
                <li>
                    <a id="joinotify_edit_workflow_title" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0" href="#" role="button" data-bs-toggle="modal" data-bs-target="#edit_workflow_title">
                        <svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.045 7.401c.378-.378.586-.88.586-1.414s-.208-1.036-.586-1.414l-1.586-1.586c-.378-.378-.88-.586-1.414-.586s-1.036.208-1.413.585L4 13.585V18h4.413L19.045 7.401zm-3-3 1.587 1.585-1.59 1.584-1.586-1.585 1.589-1.584zM6 16v-1.585l7.04-7.018 1.586 1.586L7.587 16H6zm-2 4h16v2H4z"></path></svg>
                        <?php esc_html_e( 'Editar título do fluxo', 'joinotify' ) ?>
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a id="joinotify_export_workflow" class="dropdown-item px-3 py-2 d-flex align-items-center box-shadow-0 bg-transparent border-0" href="#">
                        <svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11 16h2V7h3l-4-5-4 5h3z"></path><path d="M5 22h14c1.103 0 2-.897 2-2v-9c0-1.103-.897-2-2-2h-4v2h4v9H5v-9h4V9H5c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2z"></path></svg>
                        <?php esc_html_e( 'Exportar fluxo', 'joinotify' ) ?>
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item px-3 py-2 d-flex align-items-center" href="<?php echo esc_url( admin_url('admin.php?page=joinotify-workflows-builder') ) ?>">
                        <svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill: rgba(0, 0, 0, 1);transform: ;msFilter:;"><path d="M20 2H8c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zM8 16V4h12l.002 12H8z"></path><path d="M4 8H2v12c0 1.103.897 2 2 2h12v-2H4V8zm11-2h-2v3h-3v2h3v3h2v-3h3V9h-3z"></path></svg>
                        <?php esc_html_e( 'Criar um novo fluxo', 'joinotify' ) ?>
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item px-3 py-2 d-flex align-items-center" href="<?php echo esc_url( admin_url('admin.php?page=joinotify-workflows') ) ?>">
                        <svg class="icon me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m2 12 5 4v-3h9v-2H7V8z"></path><path d="M13.001 2.999a8.938 8.938 0 0 0-6.364 2.637L8.051 7.05c1.322-1.322 3.08-2.051 4.95-2.051s3.628.729 4.95 2.051 2.051 3.08 2.051 4.95-.729 3.628-2.051 4.95-3.08 2.051-4.95 2.051-3.628-.729-4.95-2.051l-1.414 1.414c1.699 1.7 3.959 2.637 6.364 2.637s4.665-.937 6.364-2.637c1.7-1.699 2.637-3.959 2.637-6.364s-.937-4.665-2.637-6.364a8.938 8.938 0 0 0-6.364-2.637z"></path></svg>
                        <?php esc_html_e( 'Voltar para o painel', 'joinotify' ) ?>
                    </a>
                </li>
            </ul>
        </div>

        <a class="me-5 icon-translucent help-center-btn" href="<?php esc_attr_e( JOINOTIFY_DOCS_URL ) ?>" target="_blank" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php esc_attr_e( 'Central de ajuda', 'joinotify' ) ?>">
            <svg class="icon icon-xg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 6a3.939 3.939 0 0 0-3.934 3.934h2C10.066 8.867 10.934 8 12 8s1.934.867 1.934 1.934c0 .598-.481 1.032-1.216 1.626a9.208 9.208 0 0 0-.691.599c-.998.997-1.027 2.056-1.027 2.174V15h2l-.001-.633c.001-.016.033-.386.441-.793.15-.15.339-.3.535-.458.779-.631 1.958-1.584 1.958-3.182A3.937 3.937 0 0 0 12 6zm-1 10h2v2h-2z"></path><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path></svg>
        </a>
    </div>
</div>