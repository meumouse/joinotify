<?php

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
        <div id="joinotify_actions_wrapper" class="actions-wrapper d-grid"></div>
    </div>

    <?php
    /**
     * Add custom content after actions body
     * 
     * @since 1.0.0
     */
    do_action('Joinotify/Builder/Actions/After_Actions_Body'); ?>
</div>