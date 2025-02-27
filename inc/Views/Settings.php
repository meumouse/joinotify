<?php

use MeuMouse\Joinotify\Admin\Components as Admin_Components;

use MeuMouse\Joinotify\Validations\Conditions;

$payload = array(
    'order_id' => 96,
//    'meta_key' => 'last_login',
);

$get_condition = 'order_total';
$condition_type = 'finish_with';
$condition_value = '00';

$compare_value = Conditions::get_compare_value( $get_condition, $payload );
$condition_met = Conditions::check_condition( $condition_type, $compare_value, $condition_value );

var_dump( $compare_value );
var_dump( $condition_met );

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

<?php
/**
 * Display admin notices
 * 
 * @since 1.0.0
 */
do_action('Joinotify/Admin/Display_Notices'); ?>

<div class="joinotify-wrapper">
    <div class="nav-tab-wrapper joinotify-tab-wrapper">
        <?php
        /**
         * Settings nav tabs hook
         * 
         * @since 1.1.0
         */
        do_action('Joinotify/Admin/Settings_Nav_Tabs'); ?>
    </div>

    <div class="joinotify-options-settings-container">
        <form method="post" class="joinotify-form" name="joinotify-options-form">
            <?php $tabs = Admin_Components::get_settings_tabs();

            foreach ( $tabs as $tab ) :
                if ( ! empty( $tab['file'] ) ) {
                    include_once $tab['file'];
                }
            endforeach; ?>
        </form>

        <div class="joinotify-actions-footer mt-5">
            <button id="joinotify_save_options" class="btn btn-primary d-flex align-items-center justify-content-center" disabled>
                <svg class="icon me-2 icon-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 21h14a2 2 0 0 0 2-2V8a1 1 0 0 0-.29-.71l-4-4A1 1 0 0 0 16 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2zm10-2H9v-5h6zM13 7h-2V5h2zM5 5h2v4h8V5h.59L19 8.41V19h-2v-5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v5H5z"></path></svg>
                <?php esc_html_e( 'Salvar alterações', 'joinotify' ) ?></a>
            </button>
        </div>
    </div>
</div>