<?php

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
         * Before nav tabs hook
         * 
         * @since 1.0.0
         */
        do_action('Joinotify/Admin/Before_Nav_Tabs'); ?>

        <a href="#general" class="nav-tab">
            <svg class="joinotify-tab-icon"><path d="M7.5 14.5c-1.58 0-2.903 1.06-3.337 2.5H2v2h2.163c.434 1.44 1.757 2.5 3.337 2.5s2.903-1.06 3.337-2.5H22v-2H10.837c-.434-1.44-1.757-2.5-3.337-2.5zm0 5c-.827 0-1.5-.673-1.5-1.5s.673-1.5 1.5-1.5S9 17.173 9 18s-.673 1.5-1.5 1.5zm9-11c-1.58 0-2.903 1.06-3.337 2.5H2v2h11.163c.434 1.44 1.757 2.5 3.337 2.5s2.903-1.06 3.337-2.5H22v-2h-2.163c-.434-1.44-1.757-2.5-3.337-2.5zm0 5c-.827 0-1.5-.673-1.5-1.5s.673-1.5 1.5-1.5 1.5.673 1.5 1.5-.673 1.5-1.5 1.5z"></path><path d="M12.837 5C12.403 3.56 11.08 2.5 9.5 2.5S6.597 3.56 6.163 5H2v2h4.163C6.597 8.44 7.92 9.5 9.5 9.5s2.903-1.06 3.337-2.5h9.288V5h-9.288zM9.5 7.5C8.673 7.5 8 6.827 8 6s.673-1.5 1.5-1.5S11 5.173 11 6s-.673 1.5-1.5 1.5z"></path></svg>
            <?php esc_html_e( 'Geral', 'joinotify' ) ?>
        </a>

        <a href="#phones" class="nav-tab">
            <svg class="joinotify-tab-icon" xmlns="http://www.w3.org/2000/svg"><path d="M17.707 12.293a.999.999 0 0 0-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a.999.999 0 0 0-1.414 0L3.581 5.005c-.38.38-.594.902-.586 1.435.023 1.424.4 6.37 4.298 10.268s8.844 4.274 10.269 4.298h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-4-4.001zm-.127 6.712c-1.248-.021-5.518-.356-8.873-3.712-3.366-3.366-3.692-7.651-3.712-8.874L7 4.414 9.586 7 8.293 8.293a1 1 0 0 0-.272.912c.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.991.991 0 0 0 .912-.271L17 14.414 19.586 17l-2.006 2.005z"></path></svg>
            <?php esc_html_e( 'Telefones', 'joinotify' ) ?>
        </a>

        <a href="#integrations" class="nav-tab">
            <svg class="joinotify-tab-icon"><path d="M3 8h2v5c0 2.206 1.794 4 4 4h2v5h2v-5h2c2.206 0 4-1.794 4-4V8h2V6H3v2zm4 0h10v5c0 1.103-.897 2-2 2H9c-1.103 0-2-.897-2-2V8zm0-6h2v3H7zm8 0h2v3h-2z"></path></svg>
            <?php esc_html_e( 'Integrações', 'joinotify' ) ?>
        </a>
        
        <a href="#about" class="nav-tab">
            <svg class="joinotify-tab-icon"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
            <?php esc_html_e( 'Sobre', 'joinotify' ) ?>
        </a>

        <?php
        /**
         * After nav tabs hook
         * 
         * @since 1.0.0
         */
        do_action('Joinotify/Admin/After_Nav_Tabs'); ?>
    </div>

    <form method="post" class="joinotify-form" name="joinotify-options-form">
        <?php $tab_files = apply_filters( 'Joinotify/Settings/Tabs/Load_Files', array(
            'General.php',
            'Phones.php',
            'Integrations.php',
            'About.php',
        ));

        foreach ( $tab_files as $file ) :
            include_once JOINOTIFY_INC . 'Views/Settings/Tabs/' . $file;
        endforeach; ?>
    </form>
</div>