<?php

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="joinotify_triggers_group" class="">
    <?php   
    /**
     * Display custom content before triggers header section
     * 
     * @since 1.0.0
     */
    do_action('Joinotify/Builder/Triggers/Before_Header_Section'); ?>

    <div class="triggers-header mb-lg-3 mb-xxl-5">
        <h2 class="sidebar-title fs-5"><?php esc_html_e( 'Escolha o tipo de acionamento', 'joinotify' ) ?></h2>
        <span class="text-muted fs-md"><?php esc_html_e( 'Selecione uma integração de acionamento para iniciar o fluxo da sua automação.', 'joinotify' ) ?></span>
    </div>

    <?php
    /**
     * Display custom content after triggers header section
     * 
     * @since 1.0.0
     */
    do_action('Joinotify/Builder/Triggers/After_Header_Section');

    /**
     * Display custom content before triggers body section
     * 
     * @since 1.0.0
     */
    do_action('Joinotify/Builder/Triggers/Before_Body_Section'); ?>

    <div class="triggers-body">
        <div class="nav-tab-wrapper joinotify-triggers-tab-wrapper">
            <?php
            /**
             * Add triggers tabs
             * 
             * @since 1.0.0
             */
            do_action('Joinotify/Builder/Triggers'); ?>
        </div>

        <?php
        /**
         * Add triggers content
         * 
         * @since 1.0.0
         */
        do_action('Joinotify/Builder/Triggers/Body_Section'); ?>
    </div>

    <?php
    /**
     * Display custom content after triggers body section
     * 
     * @since 1.0.0
     */
    do_action('Joinotify/Builder/Triggers/After_Body_Section'); ?>
</div>

<div id="joinotify_triggers_content" class="triggers-content-wrapper">
    <div class="triggers-content-container">
        <div class="set-trigger-name d-grid mb-xxl-4 pb-3">
            <h3 class="trigger-name mt-0 mb-lg-2 mb-xxl-3 fs-5"><?php esc_html_e( 'Defina um nome para este fluxo', 'joinotify' ) ?></h3>
            <span class="description mb-lg-3 mb-xxl-4 text-muted fs-6"><?php esc_html_e( 'O nome servirá para controle interno desse fluxo', 'joinotify' ) ?></span>
            <input type="text" id="joinotify_set_workflow_name" class="form-control" name="joinotify_set_workflow_name" value="" placeholder="<?php esc_attr_e( 'Minha automação de mensagens', 'joinotify' ) ?>"/>
        </div>

        <?php
        /**
         * Add triggers content tab
         * 
         * @since 1.0.0
         */
        do_action('Joinotify/Builder/Triggers_Content'); ?>

        <div class="mt-lg-2 mt-xxl-4 pt-lg-2 pt-xxl-3 w-100 d-flex align-items-center justify-content-between">
            <button type="button" class="return-to-start btn btn-link text-dark d-flex align-items-center p-0 fs-lg">
                <svg class="icon icon-xg icon-dark me-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <path fill-rule="evenodd" clip-rule="evenodd" d="M10.5303 5.46967C10.8232 5.76256 10.8232 6.23744 10.5303 6.53033L5.81066 11.25H20C20.4142 11.25 20.75 11.5858 20.75 12C20.75 12.4142 20.4142 12.75 20 12.75H5.81066L10.5303 17.4697C10.8232 17.7626 10.8232 18.2374 10.5303 18.5303C10.2374 18.8232 9.76256 18.8232 9.46967 18.5303L3.46967 12.5303C3.17678 12.2374 3.17678 11.7626 3.46967 11.4697L9.46967 5.46967C9.76256 5.17678 10.2374 5.17678 10.5303 5.46967Z"></path> </g></svg>
                <?php esc_html_e( 'Voltar', 'joinotify' ) ?>
            </button>

            <button type="button" id="joinotify_proceed_step_funnel" class="btn btn-lg btn-primary" disabled><?php esc_html_e( 'Continuar', 'joinotify' ) ?></button>
        </div>
    </div>
</div>