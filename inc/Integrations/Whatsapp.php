<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Builder\Messages;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Validations\Media_Types;
use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WhatsApp
 * 
 * @since 1.1.0
 * @version 1.3.0
 * @package MeuMouse.com
 */
class Whatsapp extends Integrations_Base {

    /**
     * Construct function
     *
     * @since 1.1.0
     * @version 1.3.0
     * @return void
     */
    public function __construct() {
        // add integration on settings
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 10, 1 );

        // add WhatsApp message actions
        if ( Admin::get_setting('enable_whatsapp_integration') === 'yes' ) {
            add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_whatsapp_messages' ), 10, 1 );
        }
    }


    /**
     * Add integration item on settings
     * 
     * @since 1.3.0
     * @param array $integrations | Current integrations
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations['whatsapp'] = array(
            'title' => esc_html__('WhatsApp', 'joinotify'),
            'description' => esc_html__('Use o maior serviço de mensagens do mundo para notificar seu cliente em eventos do seu site.', 'joinotify'),
            'icon' => '<svg viewBox="-2.73 0 1225.016 1225.016" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path fill="#E0E0E0" d="M1041.858 178.02C927.206 63.289 774.753.07 612.325 0 277.617 0 5.232 272.298 5.098 606.991c-.039 106.986 27.915 211.42 81.048 303.476L0 1225.016l321.898-84.406c88.689 48.368 188.547 73.855 290.166 73.896h.258.003c334.654 0 607.08-272.346 607.222-607.023.056-162.208-63.052-314.724-177.689-429.463zm-429.533 933.963h-.197c-90.578-.048-179.402-24.366-256.878-70.339l-18.438-10.93-191.021 50.083 51-186.176-12.013-19.087c-50.525-80.336-77.198-173.175-77.16-268.504.111-278.186 226.507-504.503 504.898-504.503 134.812.056 261.519 52.604 356.814 147.965 95.289 95.36 147.728 222.128 147.688 356.948-.118 278.195-226.522 504.543-504.693 504.543z"></path><linearGradient id="a" gradientUnits="userSpaceOnUse" x1="609.77" y1="1190.114" x2="609.77" y2="21.084"><stop offset="0" stop-color="#20b038"></stop><stop offset="1" stop-color="#60d66a"></stop></linearGradient><path fill="url(#a)" d="M27.875 1190.114l82.211-300.18c-50.719-87.852-77.391-187.523-77.359-289.602.133-319.398 260.078-579.25 579.469-579.25 155.016.07 300.508 60.398 409.898 169.891 109.414 109.492 169.633 255.031 169.57 409.812-.133 319.406-260.094 579.281-579.445 579.281-.023 0 .016 0 0 0h-.258c-96.977-.031-192.266-24.375-276.898-70.5l-307.188 80.548z"></path><image overflow="visible" opacity=".08" width="682" height="639" xlink:href="FCC0802E2AF8A915.png" transform="translate(270.984 291.372)"></image><path fill-rule="evenodd" clip-rule="evenodd" fill="#FFF" d="M462.273 349.294c-11.234-24.977-23.062-25.477-33.75-25.914-8.742-.375-18.75-.352-28.742-.352-10 0-26.25 3.758-39.992 18.766-13.75 15.008-52.5 51.289-52.5 125.078 0 73.797 53.75 145.102 61.242 155.117 7.5 10 103.758 166.266 256.203 226.383 126.695 49.961 152.477 40.023 179.977 37.523s88.734-36.273 101.234-71.297c12.5-35.016 12.5-65.031 8.75-71.305-3.75-6.25-13.75-10-28.75-17.5s-88.734-43.789-102.484-48.789-23.75-7.5-33.75 7.516c-10 15-38.727 48.773-47.477 58.773-8.75 10.023-17.5 11.273-32.5 3.773-15-7.523-63.305-23.344-120.609-74.438-44.586-39.75-74.688-88.844-83.438-103.859-8.75-15-.938-23.125 6.586-30.602 6.734-6.719 15-17.508 22.5-26.266 7.484-8.758 9.984-15.008 14.984-25.008 5-10.016 2.5-18.773-1.25-26.273s-32.898-81.67-46.234-111.326z"></path><path fill="#FFF" d="M1036.898 176.091C923.562 62.677 772.859.185 612.297.114 281.43.114 12.172 269.286 12.039 600.137 12 705.896 39.633 809.13 92.156 900.13L7 1211.067l318.203-83.438c87.672 47.812 186.383 73.008 286.836 73.047h.255.003c330.812 0 600.109-269.219 600.25-600.055.055-160.343-62.328-311.108-175.649-424.53zm-424.601 923.242h-.195c-89.539-.047-177.344-24.086-253.93-69.531l-18.227-10.805-188.828 49.508 50.414-184.039-11.875-18.867c-49.945-79.414-76.312-171.188-76.273-265.422.109-274.992 223.906-498.711 499.102-498.711 133.266.055 258.516 52 352.719 146.266 94.195 94.266 146.031 219.578 145.992 352.852-.118 274.999-223.923 498.749-498.899 498.749z"></path></g></svg>',
            'setting_key' => 'enable_whatsapp_integration',
            'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Whatsapp',
        );

        return $integrations;
    }


    /**
     * Add WhatsApp messages actions in sidebar list on builder
     * 
     * @since 1.1.0
     * @param array $actions | Current actions
     * @return array
     */
    public function add_whatsapp_messages( $actions ) {
        $actions[] = array(
            'action' => 'send_whatsapp_message_text',
            'title' => esc_html__( 'WhatsApp: Mensagem de texto', 'joinotify' ),
            'description' => esc_html__( 'Envie uma mensagem de texto com o WhatsApp.', 'joinotify' ),
            'context' => array(),
            'icon' => '<svg class="icon icon-lg whatsapp" viewBox="-1.5 0 259 259" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid" fill="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g> <path d="M67.6631045,221.823373 L71.8484512,223.916047 C89.2873956,234.379413 108.819013,239.262318 128.350631,239.262318 L128.350631,239.262318 C189.735716,239.262318 239.959876,189.038158 239.959876,127.653073 C239.959876,98.3556467 228.101393,69.7557778 207.17466,48.8290445 C186.247927,27.9023111 158.345616,16.0438289 128.350631,16.0438289 C66.9655467,16.0438289 16.7413867,66.2679889 17.4389445,128.350631 C17.4389445,149.277365 23.7169645,169.50654 34.1803311,186.945485 L36.9705622,191.130831 L25.8096378,232.28674 L67.6631045,221.823373 Z" fill="#00E676"> </path> <path d="M219.033142,37.66812 C195.316178,13.2535978 162.530962,0 129.048189,0 C57.8972956,0 0.697557778,57.8972956 1.39511556,128.350631 C1.39511556,150.67248 7.67313556,172.296771 18.1365022,191.828389 L0,258.096378 L67.6631045,240.657433 C86.4971645,251.1208 107.423898,256.003705 128.350631,256.003705 L128.350631,256.003705 C198.803967,256.003705 256.003705,198.106409 256.003705,127.653073 C256.003705,93.4727423 242.750107,61.3850845 219.033142,37.66812 Z M129.048189,234.379413 L129.048189,234.379413 C110.214129,234.379413 91.380069,229.496509 75.3362401,219.7307 L71.1508934,217.638027 L30.6925422,228.101393 L41.1559089,188.3406 L38.3656778,184.155253 C7.67313556,134.628651 22.3218489,69.05822 72.5460089,38.3656778 C122.770169,7.67313556 187.643042,22.3218489 218.335585,72.5460089 C249.028127,122.770169 234.379413,187.643042 184.155253,218.335585 C168.111425,228.798951 148.579807,234.379413 129.048189,234.379413 Z M190.433273,156.9505 L182.760138,153.462711 C182.760138,153.462711 171.599213,148.579807 164.623636,145.092018 C163.926078,145.092018 163.22852,144.39446 162.530962,144.39446 C160.438289,144.39446 159.043173,145.092018 157.648058,145.789576 L157.648058,145.789576 C157.648058,145.789576 156.9505,146.487133 147.184691,157.648058 C146.487133,159.043173 145.092018,159.740731 143.696902,159.740731 L142.999345,159.740731 C142.301787,159.740731 140.906671,159.043173 140.209113,158.345616 L136.721325,156.9505 L136.721325,156.9505 C129.048189,153.462711 122.072611,149.277365 116.492149,143.696902 C115.097033,142.301787 113.00436,140.906671 111.609245,139.511556 C106.72634,134.628651 101.843436,129.048189 98.3556467,122.770169 L97.658089,121.375053 C96.9605312,120.677496 96.9605312,119.979938 96.2629734,118.584822 C96.2629734,117.189707 96.2629734,115.794591 96.9605312,115.097033 C96.9605312,115.097033 99.7507623,111.609245 101.843436,109.516571 C103.238551,108.121456 103.936109,106.028782 105.331225,104.633667 C106.72634,102.540993 107.423898,99.7507623 106.72634,97.658089 C106.028782,94.1703001 97.658089,75.3362401 95.5654156,71.1508934 C94.1703001,69.05822 92.7751845,68.3606623 90.6825112,67.6631045 L88.5898378,67.6631045 C87.1947223,67.6631045 85.1020489,67.6631045 83.0093756,67.6631045 C81.6142601,67.6631045 80.2191445,68.3606623 78.8240289,68.3606623 L78.1264712,69.05822 C76.7313556,69.7557778 75.3362401,71.1508934 73.9411245,71.8484512 C72.5460089,73.2435667 71.8484512,74.6386823 70.4533356,76.0337978 C65.5704312,82.3118178 62.7802,89.9849534 62.7802,97.658089 L62.7802,97.658089 C62.7802,103.238551 64.1753156,108.819013 66.2679889,113.701918 L66.9655467,115.794591 C73.2435667,129.048189 81.6142601,140.906671 92.7751845,151.370038 L95.5654156,154.160269 C97.658089,156.252942 99.7507623,157.648058 101.145878,159.740731 C115.794591,172.296771 132.535978,181.365022 151.370038,186.247927 C153.462711,186.945485 156.252942,186.945485 158.345616,187.643042 L158.345616,187.643042 C160.438289,187.643042 163.22852,187.643042 165.321193,187.643042 C168.808982,187.643042 172.994329,186.247927 175.78456,184.852811 C177.877233,183.457696 179.272349,183.457696 180.667465,182.06258 L182.06258,180.667465 C183.457696,179.272349 184.852811,178.574791 186.247927,177.179676 C187.643042,175.78456 189.038158,174.389445 189.735716,172.994329 C191.130831,170.204098 191.828389,166.716309 192.525947,163.22852 C192.525947,161.833405 192.525947,159.740731 192.525947,158.345616 C192.525947,158.345616 191.828389,157.648058 190.433273,156.9505 Z" fill="#FFFFFF"></path></g></g></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'settings' => self::whatsapp_message_text_action(),
            'priority' => 40,
            'is_expansible' => true,
        );

        $actions[] = array(
            'action' => 'send_whatsapp_message_media',
            'title' => esc_html__( 'WhatsApp: Mensagem de mídia', 'joinotify' ),
            'description' => esc_html__( 'Envie uma mensagem de mídia (imagem, vídeo, documento e áudio) com o WhatsApp.', 'joinotify' ),
            'context' => array(),
            'icon' => '<svg class="icon icon-lg whatsapp" viewBox="-1.5 0 259 259" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid" fill="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g> <path d="M67.6631045,221.823373 L71.8484512,223.916047 C89.2873956,234.379413 108.819013,239.262318 128.350631,239.262318 L128.350631,239.262318 C189.735716,239.262318 239.959876,189.038158 239.959876,127.653073 C239.959876,98.3556467 228.101393,69.7557778 207.17466,48.8290445 C186.247927,27.9023111 158.345616,16.0438289 128.350631,16.0438289 C66.9655467,16.0438289 16.7413867,66.2679889 17.4389445,128.350631 C17.4389445,149.277365 23.7169645,169.50654 34.1803311,186.945485 L36.9705622,191.130831 L25.8096378,232.28674 L67.6631045,221.823373 Z" fill="#00E676"> </path> <path d="M219.033142,37.66812 C195.316178,13.2535978 162.530962,0 129.048189,0 C57.8972956,0 0.697557778,57.8972956 1.39511556,128.350631 C1.39511556,150.67248 7.67313556,172.296771 18.1365022,191.828389 L0,258.096378 L67.6631045,240.657433 C86.4971645,251.1208 107.423898,256.003705 128.350631,256.003705 L128.350631,256.003705 C198.803967,256.003705 256.003705,198.106409 256.003705,127.653073 C256.003705,93.4727423 242.750107,61.3850845 219.033142,37.66812 Z M129.048189,234.379413 L129.048189,234.379413 C110.214129,234.379413 91.380069,229.496509 75.3362401,219.7307 L71.1508934,217.638027 L30.6925422,228.101393 L41.1559089,188.3406 L38.3656778,184.155253 C7.67313556,134.628651 22.3218489,69.05822 72.5460089,38.3656778 C122.770169,7.67313556 187.643042,22.3218489 218.335585,72.5460089 C249.028127,122.770169 234.379413,187.643042 184.155253,218.335585 C168.111425,228.798951 148.579807,234.379413 129.048189,234.379413 Z M190.433273,156.9505 L182.760138,153.462711 C182.760138,153.462711 171.599213,148.579807 164.623636,145.092018 C163.926078,145.092018 163.22852,144.39446 162.530962,144.39446 C160.438289,144.39446 159.043173,145.092018 157.648058,145.789576 L157.648058,145.789576 C157.648058,145.789576 156.9505,146.487133 147.184691,157.648058 C146.487133,159.043173 145.092018,159.740731 143.696902,159.740731 L142.999345,159.740731 C142.301787,159.740731 140.906671,159.043173 140.209113,158.345616 L136.721325,156.9505 L136.721325,156.9505 C129.048189,153.462711 122.072611,149.277365 116.492149,143.696902 C115.097033,142.301787 113.00436,140.906671 111.609245,139.511556 C106.72634,134.628651 101.843436,129.048189 98.3556467,122.770169 L97.658089,121.375053 C96.9605312,120.677496 96.9605312,119.979938 96.2629734,118.584822 C96.2629734,117.189707 96.2629734,115.794591 96.9605312,115.097033 C96.9605312,115.097033 99.7507623,111.609245 101.843436,109.516571 C103.238551,108.121456 103.936109,106.028782 105.331225,104.633667 C106.72634,102.540993 107.423898,99.7507623 106.72634,97.658089 C106.028782,94.1703001 97.658089,75.3362401 95.5654156,71.1508934 C94.1703001,69.05822 92.7751845,68.3606623 90.6825112,67.6631045 L88.5898378,67.6631045 C87.1947223,67.6631045 85.1020489,67.6631045 83.0093756,67.6631045 C81.6142601,67.6631045 80.2191445,68.3606623 78.8240289,68.3606623 L78.1264712,69.05822 C76.7313556,69.7557778 75.3362401,71.1508934 73.9411245,71.8484512 C72.5460089,73.2435667 71.8484512,74.6386823 70.4533356,76.0337978 C65.5704312,82.3118178 62.7802,89.9849534 62.7802,97.658089 L62.7802,97.658089 C62.7802,103.238551 64.1753156,108.819013 66.2679889,113.701918 L66.9655467,115.794591 C73.2435667,129.048189 81.6142601,140.906671 92.7751845,151.370038 L95.5654156,154.160269 C97.658089,156.252942 99.7507623,157.648058 101.145878,159.740731 C115.794591,172.296771 132.535978,181.365022 151.370038,186.247927 C153.462711,186.945485 156.252942,186.945485 158.345616,187.643042 L158.345616,187.643042 C160.438289,187.643042 163.22852,187.643042 165.321193,187.643042 C168.808982,187.643042 172.994329,186.247927 175.78456,184.852811 C177.877233,183.457696 179.272349,183.457696 180.667465,182.06258 L182.06258,180.667465 C183.457696,179.272349 184.852811,178.574791 186.247927,177.179676 C187.643042,175.78456 189.038158,174.389445 189.735716,172.994329 C191.130831,170.204098 191.828389,166.716309 192.525947,163.22852 C192.525947,161.833405 192.525947,159.740731 192.525947,158.345616 C192.525947,158.345616 191.828389,157.648058 190.433273,156.9505 Z" fill="#FFFFFF"></path></g></g></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'settings' => self::whatsapp_message_media_action(),
            'priority' => 50,
            'is_expansible' => false,
        );

        return $actions;
    }


    /**
     * Render WhatsApp message text component
     * 
     * @since 1.1.0
     * @param array $settings | Current settings
     * @return string
     */
    public static function whatsapp_message_text_action( $settings = array() ) {
        ob_start();

        $sender = $settings['sender'] ?? '';
        $receiver = $settings['receiver'] ?? '';
        $message = $settings['message'] ?? '';

        // Estimate number of lines (counts how many line breaks there are)
        $lines = substr_count( $message, "\n" ) + 1;
        $line_height = 40; // px
        $textarea_height = $lines * $line_height;

        // display toast tip for customize WhatsApp texts with variables
        if ( empty( $settings ) && get_user_meta( get_current_user_id(), 'joinotify_dismiss_placeholders_tip_user_meta', true ) !== 'hidden' ) : ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo __( '<strong>Dica: </strong> Você pode deixar textos em negrito, sublinhados, ou riscados com variáveis do WhatsApp. Veja mais detalhes na <a href="https://ajuda.meumouse.com/docs/joinotify/placeholders" class="alert-link" target="_blank">documentação do Joinotify</a>. <a id="joinotify_dismiss_placeholders_tip" class="alert-link mt-4 d-block" data-bs-dismiss="alert" href="#">Não mostrar novamente</a>', 'joinotify' ); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ) ?>"></button>
            </div>
        <?php endif; ?>

        <div class="preview-whatsapp-message-sender <?php echo ( ! empty( $message ) ) ? 'active' : ''; ?>"><?php echo nl2br( $message ) ?></div>

        <div class="mb-4">
            <label class="form-label" for="get-whatsapp-phone-sender"><?php esc_html_e( 'Remetente: *', 'joinotify' ); ?></label>
            
            <select class="form-select get-whatsapp-phone-sender required-setting">
                <?php foreach ( get_option('joinotify_get_phones_senders') as $phone ) : ?>
                    <option value="<?php esc_attr_e( $phone ) ?>" <?php selected( $sender, $phone, true ) ?> class="get-sender-number"><?php echo esc_html( Helpers::validate_and_format_phone( $phone ) ) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="form-label" for="get-whatsapp-receiver"><?php esc_html_e( 'Destinatário: *', 'joinotify' ); ?></label>

            <input type="text" class="form-control get-whatsapp-receiver required-setting" value="<?php echo $receiver ?>" placeholder="<?php esc_attr_e( '5541987111527', 'joinotify' ) ?>"/>
        </div>

        <div class="mb-4">
            <label class="form-label" for="set-whatsapp-message-text"><?php esc_html_e( 'Mensagem de texto: *', 'joinotify' ); ?></label>
            
            <textarea type="text" class="form-control add-emoji-picker set-whatsapp-message-text required-setting" placeholder="<?php esc_attr_e( 'Mensagem', 'joinotify' ) ?>" style="height: <?php echo $textarea_height; ?>px;"><?php echo $message ?></textarea>
        </div>

        <?php return ob_get_clean();
    }


    /**
     * Render WhatsApp message media component
     * 
     * @since 1.1.0
     * @param array $settings | Current settings
     * @return string
     */
    public static function whatsapp_message_media_action( $settings = array() ) {
        ob_start();
        
        $sender = $settings['sender'] ?? '';
        $receiver = $settings['receiver'] ?? '';
        $media_type = $settings['media_type'] ?? '';
        $media_url = $settings['media_url'] ?? ''; ?>

        <div class="preview-whatsapp-message-sender media <?php echo $media_type ?> <?php echo ( ! empty( $media_url ) ) ? 'active' : ''; ?>"><?php echo Messages::build_whatsapp_media_description( $settings ) ?></div>

        <div class="mb-4">
            <label class="form-label" for="get-whatsapp-phone-sender"><?php esc_html_e( 'Remetente: *', 'joinotify' ); ?></label>

            <select class="form-select get-whatsapp-phone-sender required-setting">
                <?php foreach ( get_option('joinotify_get_phones_senders') as $phone ) : ?>
                    <option value="<?php esc_attr_e( $phone ) ?>" <?php selected( $sender, $phone ) ?> class="get-sender-number"><?php echo esc_html( Helpers::validate_and_format_phone( $phone ) ) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="form-label" for="get-whatsapp-receiver"><?php esc_html_e( 'Destinatário: *', 'joinotify' ); ?></label>

            <input type="text" class="form-control get-whatsapp-receiver required-setting" value="<?php echo $receiver ?>" placeholder="<?php esc_attr_e( '5541987111527', 'joinotify' ) ?>"/>
        </div>

        <div class="mb-4">
            <label class="form-label" for="get-whatsapp-media-type"><?php esc_html_e( 'Tipo de mídia: *', 'joinotify' ) ?></label>

            <select class="form-select get-whatsapp-media-type required-setting">
                <?php foreach ( Media_Types::get_media_types() as $type => $value ) : ?>
                    <option value="<?php esc_attr_e( $type ) ?>" <?php selected( $media_type, $type, true ) ?>><?php esc_html_e( $value ) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="require-media-type-image">
            <label class="form-label" for="get-whatsapp-media-url"><?php esc_html_e( 'Adicionar mídia: *', 'joinotify' ) ?></label>
            
            <div class="input-group">
                <button id="joinotify_set_url_media" class="set-media-url btn btn-icon btn-outline-secondary icon-translucent">
                    <svg class="icon icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 5h13v7h2V5c0-1.103-.897-2-2-2H4c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h8v-2H4V5z"></path><path d="m8 11-3 4h11l-4-6-3 4z"></path><path d="M19 14h-2v3h-3v2h3v3h2v-3h3v-2h-3z"></path></svg>
                </button>

                <input type="text" class="form-control get-media-url get-whatsapp-media-url required-setting" value="<?php echo $media_url ?>" placeholder="<?php esc_attr_e( 'URL da mídia', 'joinotify' ) ?>"/>
            </div>
        </div>

        <?php return ob_get_clean();
    }
}