<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Workflow_Processor;
use MeuMouse\Joinotify\Builder\Components as Builder_Components;

use WC_Session_Handler;
use WC_Cart;
use WC_Coupon;
use WC_Customer;
use WP_Error;
use WP_Query;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WooCommerce
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Woocommerce extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        // add integration on settings
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 20, 1 );
        
        // check if woocommerce is active
        if ( class_exists('WooCommerce') ) {
            // add triggers
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );

            // add trigger tab
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 20 );

            // add trigger content
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );

            // add placeholders
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 10, 2 );

            // add settings modal on integrations admin page
            add_action( 'Joinotify/Settings/Tabs/Integrations/Woocommerce', array( $this, 'add_modal_settings' ) );

            // add coupon action
            if ( Admin::get_setting('enable_create_coupon_action') === 'yes' ) {
                add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_coupon_action' ), 10, 1 );
            }

            // add conditions
            add_filter( 'Joinotify/Validations/Get_Action_Conditions', array( $this, 'add_conditions' ), 10, 1 );

            // fire hooks if WooCommerce is active
            if ( Admin::get_setting('enable_woocommerce_integration') === 'yes' ) {
                // on receive new order
                // before hook used is "woocommerce_new_order", but products isent received
                add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_workflow_on_new_order' ), 10, 1 );

                // when order is processing
                add_action( 'woocommerce_order_status_processing', array( $this, 'process_workflow_order_processed' ), 10, 3 );

                // when order is completed
                add_action( 'woocommerce_order_status_completed', array( $this, 'process_workflow_order_completed' ), 10, 3 );

                // when a order is fully refunded
                add_action( 'woocommerce_order_fully_refunded', array( $this, 'process_workflow_order_fully_refunded' ), 10, 2 );

                // when a order is partially refunded
                add_action( 'woocommerce_order_partially_refunded', array( $this, 'process_workflow_order_partially_refunded' ), 10, 2 );
                
                // when a order has status changed
                add_action( 'woocommerce_order_status_changed', array( $this, 'process_workflow_order_status_changed' ), 10, 3 );
            }
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
        $integrations['woocommerce'] = self::build_integration_item(
            'woocommerce',
            esc_html__( 'WooCommerce', 'joinotify' ),
            esc_html__( 'Send messages regarding new orders, cancellations, refunds, and recovery of unpaid orders. Keep your customers updated.', 'joinotify' ),
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1052 1052"><defs><style>.cls-1{fill:#873eff;}.cls-2,.cls-3{fill:#fff;}.cls-3{fill-rule:evenodd;}</style></defs><circle class="cls-1" cx="526" cy="526" r="526"/><path class="cls-2" d="M201.11,657.84c26.54,0,47.83-13.1,63.89-43.25l35.71-66.84v56.68c0,33.42,21.63,53.41,55.05,53.41,26.21,0,45.54-11.47,64.21-43.25l82.24-138.92c18-30.47,5.25-53.41-34.4-53.41-21.3,0-35.06,6.89-47.51,30.15L363.62,558.89V464.2c0-28.17-13.43-41.94-38.33-41.94-19.66,0-35.39,8.52-47.51,32.11L224.37,558.89v-93.7c0-30.15-12.45-42.93-42.59-42.93h-61.6c-23.26,0-35.06,10.82-35.06,30.8s12.45,31.46,35.06,31.46h25.23V604.11C145.41,637.85,168,657.84,201.11,657.84Z" transform="translate(-14 -14)"/><path class="cls-3" d="M622.48,422.26c-67.17,0-118.61,50.13-118.61,118s51.77,117.62,118.61,117.62,117.95-50.13,118.27-117.62C740.75,472.39,689.31,422.26,622.48,422.26Zm0,163.17c-25.23,0-42.6-19-42.6-45.21s17.37-45.55,42.6-45.55,42.59,19.34,42.59,45.55S648,585.43,622.48,585.43Z" transform="translate(-14 -14)"/><path class="cls-3" d="M757.44,540.22c0-67.83,51.44-118,118.28-118S994,472.72,994,540.22,942.56,657.84,875.72,657.84,757.44,608,757.44,540.22Zm76,0c0,26.21,16.7,45.21,42.26,45.21,25.23,0,42.59-19,42.59-45.21S901,494.67,875.72,494.67,833.46,514,833.46,540.22Z" transform="translate(-14 -14)"/></svg>',
            array(
                'setting_key' => 'enable_woocommerce_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Woocommerce',
                'is_plugin' => true,
                'plugin_active' => array(
                    'woocommerce/woocommerce.php',
                ),
                'settings' => self::get_integration_settings(),
                'modal' => array(
                    'title' => esc_html__( 'WooCommerce settings', 'joinotify' ),
                    'description' => esc_html__( 'Configure discount coupons and checkout field formatting.', 'joinotify' ),
                    'button_label' => esc_html__( 'Configure', 'joinotify' ),
                ),
            )
        );

        return $integrations;
    }


    /**
     * Declarative settings rendered in the integrations modal.
     *
     * @since 1.4.7
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_settings() {
        return array(
            self::field_toggle(
                'enable_create_coupon_action',
                esc_html__( 'Activate discount coupon action', 'joinotify' ),
                esc_html__( 'Adds the coupon action to WooCommerce workflows.', 'joinotify' )
            ),
            self::field_text(
                'create_coupon_prefix',
                esc_html__( 'Coupon prefix', 'joinotify' ),
                esc_html__( 'Prefix used in the automatic creation of coupons.', 'joinotify' ),
                array(
                    'placeholder' => 'CUPOM_',
                )
            ),
            self::field_textarea(
                'woocommerce_billing_full_address_format',
                esc_html__( 'Billing full address format', 'joinotify' ),
                esc_html__( 'Defines the text used in the billing full address variable.', 'joinotify' ),
                array(
                    'placeholder' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
                    'rows' => 3,
                )
            ),
            self::field_textarea(
                'woocommerce_shipping_full_address_format',
                esc_html__( 'Shipping full address format', 'joinotify' ),
                esc_html__( 'Defines the text used in the shipping full address variable.', 'joinotify' ),
                array(
                    'placeholder' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
                    'rows' => 3,
                )
            ),
            self::field_toggle(
                'enable_ignore_processed_actions',
                esc_html__( 'Ignore actions already processed.', 'joinotify' ),
                esc_html__( 'It prevents the same action from being processed again when the trigger is repeated.', 'joinotify' )
            ),
        );
    }


    /**
     * Add WooCommerce triggers
     * 
     * @since 1.1.0
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['woocommerce'] = array(
            array(
                'data_trigger' => 'woocommerce_new_order',
                'title' => esc_html__( 'New order', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento Ã© disparado quando um novo pedido Ã© recebido no WooCommerce com qualquer status.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_checkout_order_processed',
                'title' => esc_html__( 'New order (Processando)', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento Ã© disparado quando um novo pedido Ã© recebido no WooCommerce com status processando.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_status_completed',
                'title' => esc_html__( 'Pedido concluÃ­do', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento Ã© disparado quando um pedido tem o status alterado para concluÃ­do.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_fully_refunded',
                'title' => esc_html__( 'Order fully refunded', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento Ã© disparado quando um pedido Ã© totalmente reembolsado.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_partially_refunded',
                'title' => esc_html__( 'Order partially refunded', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento Ã© disparado quando um pedido Ã© parcialmente reembolsado.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_status_changed',
                'title' => esc_html__( 'Order status changed', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento Ã© disparado quando um pedido tem seu status alterado.', 'joinotify' ),
                'require_settings' => true,
            ),
        );

        return $triggers;
    }


    /**
     * Add Woocommerce triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'woocommerce';
        $integration_name = esc_html__( 'WooCommerce', 'joinotify' );
        $icon_svg = '<svg class="joinotify-tab-icon" viewBox="0 -51.5 256 256" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid" fill="#000000" stroke="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g fill-rule="evenodd"> <path d="M232.137523,0 L23.7592719,0 C10.5720702,0 -0.103257595,10.7799647 0.000639559736,23.862498 L0.000639559736,103.404247 C0.000639559736,116.591115 10.6767385,127.266612 23.8639402,127.266612 L122.558181,127.266612 L167.667206,152.384995 L157.410382,127.266612 L232.137523,127.266612 C245.325059,127.266612 255.999888,116.591115 255.999888,103.404247 L255.999888,23.862498 C255.999888,10.6752964 245.325059,0 232.137523,0 Z M19.3640061,18.4201481 C16.4334946,18.6294847 14.2355942,19.6761007 12.7703719,21.6645976 C11.3051496,23.5484931 10.781875,25.9556632 11.095813,28.6768382 C17.2707741,67.9247058 23.027062,94.4034429 28.3647436,108.113986 C30.4579757,113.137395 32.8651458,115.5451 35.690989,115.335764 C40.086656,115.021425 45.3196694,108.951332 51.4946305,97.1248185 C54.73908,90.4260478 59.7628237,80.3792294 66.5657276,66.9823567 C72.2170797,86.762992 79.9618646,101.625221 89.6956814,111.567705 C92.417057,114.393415 95.2427665,115.649434 97.9634733,115.440098 C100.371178,115.230761 102.254539,113.974742 103.510558,111.672039 C104.557241,109.683676 104.975914,107.380974 104.766578,104.764601 C104.138568,95.2407893 105.080917,81.9489193 107.69729,64.8892584 C110.417997,47.3063898 113.767382,34.6424627 117.849111,27.1069476 C118.686458,25.5370569 119.000128,23.9670994 118.895794,22.0832708 C118.686458,19.6760338 117.639775,17.6875369 115.651411,16.1176463 C113.663048,14.5477557 111.46468,13.8151445 109.057643,14.0244142 C106.022597,14.2337508 103.719895,15.6989731 102.150205,18.6294847 C95.6614396,30.4560654 91.0560348,49.6088916 88.335328,76.1924977 C84.3579328,66.145211 81.0085475,54.3185634 78.3921746,40.3987506 C77.2411578,34.2238564 74.4154483,31.2933449 69.8100434,31.6073497 C66.670329,31.8166194 64.0538892,33.9098515 61.9606572,37.8869122 L39.0401069,81.5302462 C35.2723159,66.3544807 31.7138615,47.8296643 28.4694119,25.9556632 C27.7368007,20.5133802 24.7016209,18.0014749 19.3640061,18.4201481 Z M221.044022,25.9559976 C228.475136,27.5259551 234.022221,31.5030159 237.789611,38.0965832 C241.138996,43.7482697 242.81302,50.5511737 242.81302,58.7146317 C242.81302,69.4943957 240.092314,79.3325464 234.649562,88.3333508 C228.370134,98.7995112 220.206676,104.032257 210.054854,104.032257 C208.275828,104.032257 206.391799,103.82292 204.402767,103.404247 C196.972321,101.834557 191.425236,97.857831 187.657177,91.264063 C184.308461,85.5076413 182.633768,78.6002028 182.633768,70.5410787 C182.633768,59.7612478 185.355144,49.923164 190.797226,41.0270948 C197.181658,30.5610681 205.345116,25.3280548 215.392603,25.3280548 C217.171629,25.3280548 219.055659,25.5373913 221.044022,25.9559976 Z M216.648622,82.5769291 C220.521015,79.1232099 223.137388,73.9947979 224.602744,67.0873594 C225.021417,64.6802562 225.335087,62.0637496 225.335087,59.3425746 C225.335087,56.3074616 224.707078,53.0630121 223.451058,49.8184957 C221.881368,45.7367667 219.788002,43.5389332 217.275963,43.0155917 C213.508574,42.2829805 209.845518,44.3762126 206.391799,49.5045577 C203.566089,53.4816184 201.786394,57.6680157 200.844713,61.9590813 C200.321038,64.3663182 200.111701,66.9830255 200.111701,69.5993984 C200.111701,72.6344445 200.739711,75.8788272 201.99573,79.1232099 C203.566089,83.2049389 205.658786,85.4026386 208.170825,85.9263145 C210.787198,86.4493215 213.612908,85.2983047 216.648622,82.5769291 Z M172.167608,38.0965832 C168.399549,31.5030159 162.74813,27.5259551 155.422019,25.9559976 C153.432987,25.5373913 151.549626,25.3280548 149.769931,25.3280548 C139.723112,25.3280548 131.559654,30.5610681 125.175223,41.0270948 C119.732472,49.923164 117.011765,59.7612478 117.011765,70.5410787 C117.011765,78.6002028 118.686458,85.5076413 122.035174,91.264063 C125.803233,97.857831 131.350318,101.834557 138.780763,103.404247 C140.769126,103.82292 142.653156,104.032257 144.432851,104.032257 C154.584672,104.032257 162.74813,98.7995112 169.027559,88.3333508 C174.47031,79.3325464 177.191017,69.4943957 177.191017,58.7146317 C177.191017,50.5511737 175.516324,43.7482697 172.167608,38.0965832 Z M158.980072,67.0873594 C157.515384,73.9947979 154.898343,79.1232099 151.02595,82.5769291 C147.990904,85.2983047 145.165195,86.4493215 142.548822,85.9263145 C140.036783,85.4026386 137.943417,83.2049389 136.373727,79.1232099 C135.117707,75.8788272 134.489698,72.6344445 134.489698,69.5993984 C134.489698,66.9830255 134.699034,64.3663182 135.22271,61.9590813 C136.16439,57.6680157 137.943417,53.4816184 140.769126,49.5045577 C144.223514,44.3762126 147.88657,42.2829805 151.65396,43.0155917 C154.165999,43.5389332 156.259365,45.7367667 157.829055,49.8184957 C159.085074,53.0630121 159.713084,56.3074616 159.713084,59.3425746 C159.713084,62.0637496 159.503748,64.6802562 158.980072,67.0873594 Z"></path></g></g></svg>';

        $this->render_integration_trigger_tab( $integration_slug, $integration_name, $icon_svg );
    }

    
    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function add_triggers_content() {
        $this->render_integration_trigger_content('woocommerce');
    }


    /**
     * Add WooCommerce placeholders on workflow builder
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param array $placeholders | Current placeholders
     * @param array $payload | Payload data
     * @return array
     */
    public function add_placeholders( $placeholders, $payload ) {
        $order = isset( $payload['order_id'] ) ? wc_get_order( $payload['order_id'] ) : null;
        $current_user = wp_get_current_user();
        $trigger_names = Triggers::get_trigger_names('woocommerce');

        // if is refund, get parent order
        if ( $order instanceof \WC_Order_Refund ) {
            $order = wc_get_order( $order->get_parent_id() ); 
        }
    
        // add woocommerce placeholders
        $placeholders['woocommerce'] = array(
            '{{ wc_billing_first_name }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the customer billing first name from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_first_name() : '',
                    'sandbox' => $current_user->exists() ? $current_user->first_name : esc_html__( 'JoÃ£o', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_last_name }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the customer billing last name from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_last_name() : '',
                    'sandbox' => $current_user->exists() ? $current_user->last_name : esc_html__( 'Doe', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_email }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the customer billing email from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ?  $order->get_billing_email() : '',
                    'sandbox' => $current_user->exists() ? $current_user->user_email : esc_html__( 'user@example.com', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_phone }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the billing phone from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_phone() : '',
                    'sandbox' => esc_html__( '+55 11 91234-5678', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_phone }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the shipping phone from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_shipping_phone() : '',
                    'sandbox' => esc_html__( '+55 41 91234-5678', 'joinotify' ),
                ),
            ),
            '{{ wc_order_number }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o nÃºmero do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_order_number() : '',
                    'sandbox' => esc_html__( '12345', 'joinotify' ),
                ),
            ),
            '{{ wc_order_status }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the WooCommerce order status', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ?  wc_get_order_status_name( $order->get_status() ) : '',
                    'sandbox' => esc_html__( 'ConcluÃ­do', 'joinotify' ),
                ),
            ),
            '{{ wc_order_date }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the WooCommerce order date', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? date_i18n( get_option('date_format'), strtotime( $order->get_date_created() ) ) : '',
                    'sandbox' => date( get_option('date_format') ),
                ),
            ),
            '{{ wc_billing_full_address }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o endereÃ§o completo de faturamento do usuÃ¡rio (formato configurÃ¡vel nas opÃ§Ãµes do WooCommerce).', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_full_address( $order, 'billing' ) : '',
                    'sandbox' => esc_html__( 'Flower Street, 123 - Curitiba/PR - Brazil (ZIP: 80000-000)', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_full_address }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o endereÃ§o completo de entrega do usuÃ¡rio (formato configurÃ¡vel nas opÃ§Ãµes do WooCommerce).', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_full_address( $order, 'shipping' ) : '',
                    'sandbox' => esc_html__( 'Daisy Avenue, 450 - Curitiba/PR - Brazil (ZIP: 80000-100)', 'joinotify' ),
                ),
            ),
            '{{ wc_purchased_items }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve each purchased product and quantity from the order, separated by line', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_purchased_items( $order ) : '',
                    'sandbox' => sprintf( '%s %s %s', esc_html__( '1x - Camiseta de algodÃ£o masculina (Produto exemplo)', 'joinotify' ), "\n",  esc_html__( '1x - Ã“culos de sol com proteÃ§Ã£o UV (Produto exemplo)', 'joinotify' ) ),
                ),
            ),
            '{{ wc_payment_url }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the order payment URL', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_checkout_payment_url() : '',
                    'sandbox' => sprintf( esc_html__( '%s/checkout/pay/order/12345', 'joinotify' ), get_site_url() ),
                ),
            ),
            '{{ wc_currency_symbol }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o sÃ­mbolo de moeda do pedido', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? get_woocommerce_currency_symbol( $order->get_currency() ) : '',
                    'sandbox' => get_woocommerce_currency_symbol(),
                ),
            ),
            '{{ wc_order_total }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the WooCommerce order total', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_plain_text( $order->get_total() ) : '',
                    'sandbox' => joinotify_format_plain_text( wc_price( 150 ) ),
                ),
            ),
            '{{ wc_total_discount }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the WooCommerce total discount', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_plain_text( $order->get_total_discount() ) : '',
                    'sandbox' => joinotify_format_plain_text( wc_price( 20 ) ),
                ),
            ),
            '{{ wc_total_tax }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the WooCommerce total tax', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_plain_text( $order->get_total_tax() ) : '',
                    'sandbox' => joinotify_format_plain_text( wc_price( 15 ) ),
                ),
            ),
            '{{ wc_total_refunded }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'To retrieve the WooCommerce total refunded amount', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_plain_text( $order->get_total_refunded() ) : '',
                    'sandbox' => joinotify_format_plain_text( wc_price( 10 ) ),
                ),
            ),
            '{{ wc_coupon_codes }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar os cÃ³digos de cupom utilizados no pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? implode(', ', $order->get_coupon_codes()) : '',
                    'sandbox' => esc_html__( 'CUPOM10, FRETEGRATIS', 'joinotify' ),
                ),
            ),
            '{{ wc_payment_method_title }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o tÃ­tulo do mÃ©todo de pagamento do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? (function ( $order ) {
                        $id = $order->get_payment_method();
                        $gateways = WC()->payment_gateways()->payment_gateways();
                        
                        return isset( $gateways[ $id ] )
                            ? joinotify_format_plain_text( $gateways[ $id ]->get_title() )
                            : '';
                    })( $order ) : '',
                    'sandbox' => esc_html__( 'CartÃ£o de crÃ©dito', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_address }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o endereÃ§o de entrega do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_shipping_to_display() : '',
                    'sandbox' => esc_html__( 'Daisy Avenue, 450 - Curitiba/PR - Brazil (ZIP: 80000-100)', 'joinotify' ),
                ),
            ),
            '{{ wc_checkout_field=[FIELD_ID] }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o valor de um campo especÃ­fico do checkout no pedido do WooCommerce. Substitua FIELD_ID pelo ID do campo do checkout, por exemplo: billing_email.', 'joinotify' ),
                'replacement' => array(), // dynamic replacement is make on Placeholders::replace_placeholders()
            ),
        );

        // check if the payload has a 'settings' key
        if ( isset( $payload['settings'] ) ) {
            $coupon_settings = $payload['settings'];
            $coupon_placeholders = self::get_coupon_placeholders( $coupon_settings );
    
            // iterate over the coupon placeholders and add them to the placeholders array
            foreach ( $coupon_placeholders as $placeholder_key => $placeholder_data ) {
                $placeholders['woocommerce'][$placeholder_key] = array(
                    'triggers' => $trigger_names,
                    'description' => $placeholder_data['description'],
                    'replacement' => array(
                        'production' => $placeholder_data['replacement'],
                        'sandbox' => $placeholder_data['replacement'],
                    ),
                );
            }
        }

        return $placeholders;
    }


    /**
     * Returns the items purchased in the order formatted
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param \WC_Order $order | The object of the request
     * @return string Items formatted on separate lines
     */
    public static function get_purchased_items( $order ) {
        $items = $order->get_items();
        $purchased_items = array();

        foreach ( $items as $item ) {
            $product_name = $item->get_name();
            $variation_details = '';
            $product = $item->get_product();
            
            if ( $product && $product->is_type('variation') ) {
                $variation_attributes = $product->get_variation_attributes();
                $variation_details = ' (' . implode( ', ', $variation_attributes ) . ')';
            }

            $purchased_items[] = sprintf( '%dx - %s%s', $item->get_quantity(), $product_name, $variation_details );
        }

        return ! empty( $purchased_items ) ? implode( "\n", $purchased_items ) : esc_html__( 'Nenhum item adquirido.', 'joinotify' );
    }


    /**
     * Get the full address of the order
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param \WC_Order $order | WooCommerce Order object
     * @return string Full address as a formatted string
     */
    public static function get_full_address( $order, $type = 'billing' ) {
        $format_setting = 'billing' === $type ? 'woocommerce_billing_full_address_format' : 'woocommerce_shipping_full_address_format';
        $format = Admin::get_setting( $format_setting );
        $address_data = self::get_address_data( $order, $type );

        if ( ! empty( $format ) ) {
            return trim( self::format_address_template( $format, $address_data, $order, $type ) );
        }

        $address = array(
            $address_data['address_1'],
            $address_data['address_2'],
            $address_data['city'],
            $address_data['state'],
            $address_data['postcode'],
            $address_data['country'],
        );

        // Filter out empty values and join with a comma.
        return implode( ', ', array_filter( $address ) );
    }


    /**
     * Get address data for placeholder replacement (with custom fields support)
     *
     * @since 1.4.5
     * @param \WC_Order $order | WooCommerce Order object
     * @param string   $type  | Address type (billing or shipping)
     * @return array
     */
    protected static function get_address_data( $order, $type ) {
        $data = array(
            'first_name' => $order->{"get_{$type}_first_name"}(),
            'last_name'  => $order->{"get_{$type}_last_name"}(),
            'company'    => $order->{"get_{$type}_company"}(),
            'address_1'  => $order->{"get_{$type}_address_1"}(),
            'address_2'  => $order->{"get_{$type}_address_2"}(),
            'city'       => $order->{"get_{$type}_city"}(),
            'state'      => $order->{"get_{$type}_state"}(),
            'postcode'   => $order->{"get_{$type}_postcode"}(),
            'country'    => $order->{"get_{$type}_country"}(),
        );

        if ( 'billing' === $type ) {
            $data['email'] = $order->get_billing_email();
            $data['phone'] = $order->get_billing_phone();
        }

        if ( method_exists( $order, "get_{$type}_phone" ) ) {
            $data['phone'] = $order->{"get_{$type}_phone"}();
        }

        // Include custom checkout fields (billing/shipping) as placeholders without prefix.
        $fields = self::export_checkout_fields( $type );

        if ( ! empty( $fields ) && is_array( $fields ) ) {
            foreach ( $fields as $field_id => $field ) {
                $field_id = is_string( $field_id ) ? $field_id : '';
                $placeholder_id = is_string( $field_id ) ? $field_id : '';

                if ( 'billing' === $type && strpos( $field_id, 'billing_' ) === 0 ) {
                    $placeholder_id = substr( $field_id, 8 );
                } elseif ( 'shipping' === $type && strpos( $field_id, 'shipping_' ) === 0 ) {
                    $placeholder_id = substr( $field_id, 9 );
                }

                // Donâ€™t overwrite core keys already set.
                if ( isset( $data[ $placeholder_id ] ) && $data[ $placeholder_id ] !== '' ) {
                    continue;
                }

                $value = self::get_order_meta_fallback_value( $order, array(
                    // Most common:
                    "{$type}_{$placeholder_id}",      // billing_number
                    $field_id,                        // billing_number (original)
                    $placeholder_id                   // number
                ) );

                if ( $value !== '' ) {
                    $data[ $placeholder_id ] = $value;
                }
            }
        }

        /**
         * Filter address data before replace.
         *
         * @since 1.4.5
         * @param array    $data
         * @param \WC_Order $order
         * @param string   $type
         */
        return apply_filters( 'Joinotify/WooCommerce/Address_Data', $data, $order, $type );
    }


    /**
     * Get value for internal WooCommerce meta key using official order getters.
     *
     * @since 1.4.5
     * @param \WC_Order $order | Order object
     * @param string    $meta_key | Meta key
     * @return string
     */
    protected static function get_internal_order_value_by_meta_key( $order, $meta_key ) {
        $meta_key = (string) $meta_key;

        $internal_map = array(
            '_billing_first_name' => 'get_billing_first_name',
            '_billing_last_name' => 'get_billing_last_name',
            '_billing_company' => 'get_billing_company',
            '_billing_address_1' => 'get_billing_address_1',
            '_billing_address_2' => 'get_billing_address_2',
            '_billing_city' => 'get_billing_city',
            '_billing_state' => 'get_billing_state',
            '_billing_postcode' => 'get_billing_postcode',
            '_billing_country' => 'get_billing_country',
            '_billing_email' => 'get_billing_email',
            '_billing_phone' => 'get_billing_phone',
            '_shipping_first_name' => 'get_shipping_first_name',
            '_shipping_last_name' => 'get_shipping_last_name',
            '_shipping_company' => 'get_shipping_company',
            '_shipping_address_1' => 'get_shipping_address_1',
            '_shipping_address_2' => 'get_shipping_address_2',
            '_shipping_city' => 'get_shipping_city',
            '_shipping_state' => 'get_shipping_state',
            '_shipping_postcode' => 'get_shipping_postcode',
            '_shipping_country' => 'get_shipping_country',
            '_shipping_phone' => 'get_shipping_phone',
        );

        if ( ! isset( $internal_map[ $meta_key ] ) ) {
            return '';
        }

        $getter = $internal_map[ $meta_key ];

        if ( ! method_exists( $order, $getter ) ) {
            return '';
        }

        $value = $order->{$getter}();

        if ( is_array( $value ) ) {
            $value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
        } elseif ( is_object( $value ) ) {
            $value = method_exists( $value, '__toString' ) ? (string) $value : '';
        } else {
            $value = (string) $value;
        }

        return trim( wp_strip_all_tags( $value ) );
    }


    /**
     * Get meta value trying multiple keys (supports arrays and objects).
     *
     * @since 1.4.5
     * @param \WC_Order $order | Order object
     * @param array     $keys
     * @return string
     */
    protected static function get_order_meta_fallback_value( $order, $keys = array() ) {
        foreach ( (array) $keys as $meta_key ) {
            if ( empty( $meta_key ) ) {
                continue;
            }

            $meta_key = (string) $meta_key;
            $value = self::get_internal_order_value_by_meta_key( $order, $meta_key );

            if ( '' === $value ) {
                $value = $order->get_meta( $meta_key, true );

                if ( is_array( $value ) ) {
                    $value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
                } elseif ( is_object( $value ) ) {
                    $value = method_exists( $value, '__toString' ) ? (string) $value : '';
                } else {
                    $value = (string) $value;
                }

                $value = trim( wp_strip_all_tags( $value ) );
            }

            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }


    /**
     * Format address template with order data (now uses $address_data only).
     *
     * @since 1.4.5
     * @param string    $format
     * @param array     $address_data
     * @param \WC_Order $order
     * @param string    $type
     * @return string
     */
    protected static function format_address_template( $format, $address_data, $order, $type ) {
        return preg_replace_callback( '/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/', function( $matches ) use ( $address_data ) {
            $key = $matches[1];

            return isset( $address_data[ $key ] ) ? (string) $address_data[ $key ] : '';
        }, $format );
    }


    /**
     * Add modal settings for WooCommerce
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @return void
     */
    public function add_modal_settings() {
        if ( Admin::get_setting('enable_woocommerce_integration') === 'yes' ) : ?>
            <button id="woocommerce_settings_trigger" class="btn btn-outline-primary mb-5"><?php esc_html_e( 'ConfiguraÃ§Ãµes', 'joinotify' ); ?></button>
		
            <div id="woocommerce_settings_container" class="joinotify-popup-container">
                <div class="joinotify-popup-content popup-lg">
                    <div class="joinotify-popup-header">
                        <h5 class="joinotify-popup-title"><?php esc_html_e( 'ConfiguraÃ§Ãµes da integraÃ§Ã£o com WooCommerce', 'joinotify' ); ?></h5>
                        <button id="woocommerce_settings_close" class="btn-close fs-lg" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ); ?>"></button>
                    </div>

                    <div class="joinotify-popup-body my-3">
                        <table class="popup-table">
                            <tbody>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Ativar aÃ§Ã£o Discount coupon', 'joinotify' ); ?>
                                        <span class="joinotify-description"><?php esc_html_e( 'Ative essa opÃ§Ã£o para adicionar a aÃ§Ã£o Discount coupon em fluxos do WooCommerce.', 'joinotify' ); ?></span>
                                    </th>
                                    <td class="d-flex align-items-center">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="toggle-switch" id="enable_create_coupon_action" name="enable_create_coupon_action" value="yes" <?php checked( Admin::get_setting('enable_create_coupon_action') === 'yes' ); ?> />
                                        </div>
                                    </td>
                                </tr>

                                <tr class="create-coupon-wrapper">
                                    <th>
                                        <?php esc_html_e( 'Prefixo para criaÃ§Ã£o de cupons', 'joinotify' ); ?>
                                        <span class="joinotify-description"><?php esc_html_e( 'Essa opÃ§Ã£o controla o prefixo do cupom criado automaticamente.', 'joinotify' ); ?></span>
                                    </th>
                                    <td>
                                        <input type="text" class="form-control" name="create_coupon_prefix" id="create_coupon_prefix" value="<?php echo Admin::get_setting('create_coupon_prefix') ?>" placeholder="<?php esc_attr_e( 'CUPOM_', 'joinotify' ) ?>"/>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Formato do endereÃ§o completo (faturamento)', 'joinotify' ); ?>
                                        <span class="joinotify-description mb-4"><?php esc_html_e( 'Personalize o texto usado na variÃ¡vel {{ wc_billing_full_address }} usando os campos do checkout, por exemplo: {{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }}).', 'joinotify' ); ?></span>

                                        <?php foreach ( self::get_checkout_placeholders_by_section('billing') as $field_id => $value ) : ?>
                                            <div class="d-flex mb-1">
                                                <span class="joinotify-description"><code><?php echo esc_html( $value['placeholder_html'] ); ?></code></span>
                                                <span class="joinotify-description ms-2"><?php echo esc_html( $value['description'] ); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </th>
                                    
                                    <td>
                                        <textarea class="form-control" name="woocommerce_billing_full_address_format" id="woocommerce_billing_full_address_format" rows="2" placeholder="<?php esc_attr_e( '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})', 'joinotify' ); ?>"><?php echo esc_textarea( Admin::get_setting('woocommerce_billing_full_address_format') ); ?></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Formato do endereÃ§o completo (entrega)', 'joinotify' ); ?>
                                        <span class="joinotify-description mb-4"><?php esc_html_e( 'Personalize o texto usado na variÃ¡vel {{ wc_shipping_full_address }} usando os campos do checkout, por exemplo: {{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }}).', 'joinotify' ); ?></span>

                                        <?php foreach ( self::get_checkout_placeholders_by_section('shipping') as $field_id => $value ) : ?>
                                            <div class="d-flex mb-1">
                                                <span class="joinotify-description"><code><?php echo esc_html( $value['placeholder_html'] ); ?></code></span>
                                                <span class="joinotify-description ms-2"><?php echo esc_html( $value['description'] ); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </th>

                                    <td>
                                        <textarea class="form-control" name="woocommerce_shipping_full_address_format" id="woocommerce_shipping_full_address_format" rows="2" placeholder="<?php esc_attr_e( '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})', 'joinotify' ); ?>"><?php echo esc_textarea( Admin::get_setting('woocommerce_shipping_full_address_format') ); ?></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Ignorar aÃ§Ãµes jÃ¡ processadas no fluxo', 'joinotify' ); ?>
                                        <span class="joinotify-description"><?php esc_html_e( 'Ative essa opÃ§Ã£o para que aÃ§Ãµes jÃ¡ previamente processadas sejam ignoradas quando o mesmo acionamento ocorrer. Esta opÃ§Ãµes Ã© Ãºtil quando aÃ§Ãµes agendadas estÃ£o sendo disparadas novamente.', 'joinotify' ); ?></span>
                                    </th>
                                    <td class="d-flex align-items-center">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="toggle-switch" id="enable_ignore_processed_actions" name="enable_ignore_processed_actions" value="yes" <?php checked( Admin::get_setting('enable_ignore_processed_actions') === 'yes' ); ?> />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif;
    }


    /**
     * Add coupon action in sidebar list on builder
     * 
     * @since 1.1.0
     * @param array $actions | Current actions
     * @return array
     */
    public function add_coupon_action( $actions ) {
        $actions[] = array(
            'action' => 'create_coupon',
            'title' => esc_html__( 'Discount coupon', 'joinotify' ),
            'description' => esc_html__( 'Envie um cupom de desconto para seu usuÃ¡rio atravÃ©s de mensagem de texto do WhatsApp.', 'joinotify' ),
            'context' => array(
                'woocommerce',
            ),
            'icon' => '<svg class="icon icon-lg icon-dark coupon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path fill-rule="evenodd" clip-rule="evenodd" d="M3.75 6.75L4.5 6H20.25L21 6.75V10.7812H20.25C19.5769 10.7812 19.0312 11.3269 19.0312 12C19.0312 12.6731 19.5769 13.2188 20.25 13.2188H21V17.25L20.25 18L4.5 18L3.75 17.25V13.2188H4.5C5.1731 13.2188 5.71875 12.6731 5.71875 12C5.71875 11.3269 5.1731 10.7812 4.5 10.7812H3.75V6.75ZM5.25 7.5V9.38602C6.38677 9.71157 7.21875 10.7586 7.21875 12C7.21875 13.2414 6.38677 14.2884 5.25 14.614V16.5L9 16.5L9 7.5H5.25ZM10.5 7.5V16.5L19.5 16.5V14.614C18.3632 14.2884 17.5312 13.2414 17.5312 12C17.5312 10.7586 18.3632 9.71157 19.5 9.38602V7.5H10.5Z"></path></g></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'settings' => self::create_coupon_action(),
            'priority' => 60,
        );

        return $actions;
    }


    /**
     * Render coupon action settings on sidebar action
     * 
     * @since 1.1.0
     * @param array $settings | Current settings
     * @return string
     */
    public static function create_coupon_action( $settings = array() ) {
        ob_start(); ?>

        <div class="coupon-action-group">
            <div class="coupon-action-item d-flex align-items-center">
                <label class="form-label me-3"><?php esc_html_e( 'Generate coupon automatically', 'joinotify' ) ?></label>
                <input type="checkbox" class="toggle-switch generate-coupon-code validate-required-settings" <?php checked( isset( $settings['settings']['generate_coupon'] ) && $settings['settings']['generate_coupon'] === 'yes' ) ?>>
            </div>

            <div class="coupon-action-item coupon-code-wrapper">
                <label class="form-label"><?php esc_html_e( 'CÃ³digo do cupom: *', 'joinotify' ) ?></label>
                <input type="text" class="form-control set-coupon-code required-setting" value="<?php esc_attr_e( $settings['settings']['coupon_code'] ?? '' ) ?>" placeholder="<?php esc_attr_e( 'EXCLUSIVE_COUPON', 'joinotify' ) ?>"/>
            </div>

            <div class="coupon-action-item">
                <label class="form-label"><?php esc_html_e( 'DescriÃ§Ã£o do cupom (opcional)', 'joinotify' ) ?></label>
                <input type="text" class="form-control set-coupon-description" value="<?php esc_attr_e( $settings['settings']['coupon_description'] ?? '' ) ?>" placeholder="<?php esc_attr_e( 'Cupom para recuperaÃ§Ã£o de compras', 'joinotify' ) ?>"/>
            </div>

            <div class="coupon-action-item">
                <label class="form-label"><?php esc_html_e( 'Discount type and amount: *', 'joinotify' ) ?></label>
                
                <div class="input-group">
                    <select class="form-select set-coupon-discount-type">
                        <option value="fixed_cart" <?php selected( $settings['settings']['discount_type'] ?? '', 'fixed_cart', true ) ?> ><?php esc_html_e( 'Desconto fixo', 'joinotify' ) ?></option>
                        <option value="percent" <?php selected( $settings['settings']['discount_type'] ?? '', 'percent', true ) ?> ><?php esc_html_e( 'Percentage', 'joinotify' ) ?></option>
                    </select>

                    <input type="text" class="form-control set-coupon-discount-value required-setting" value="<?php esc_attr_e( $settings['settings']['coupon_amount'] ?? '' ) ?>" placeholder="<?php esc_attr_e( 'Discount amount', 'joinotify' ) ?>"/>
                </div>
            </div>

            <div class="coupon-action-item d-flex align-items-center">
                <label class="form-label me-3"><?php esc_html_e( 'Permitir frete grÃ¡tis', 'joinotify' ) ?></label>
                <input type="checkbox" class="toggle-switch allow-free-shipping" <?php checked( isset( $settings['settings']['free_shipping'] ) && $settings['settings']['free_shipping'] === 'yes' ) ?>>
            </div>

            <div class="coupon-action-item d-flex align-items-center">
                <label class="form-label me-3"><?php esc_html_e( 'Definir expiraÃ§Ã£o do cupom', 'joinotify' ) ?></label>
                <input type="checkbox" class="toggle-switch set-expires-coupon validate-required-settings" <?php checked( isset( $settings['settings']['coupon_expiry'] ) && $settings['settings']['coupon_expiry'] === 'yes' ) ?>>
            </div>

            <div class="coupon-action-item select-time-delay-container d-none">
                <label class="form-label" for="set-time-delay-type"><?php esc_html_e( 'Selecione o tipo de expiraÃ§Ã£o', 'joinotify' ) ?></label>
                
                <select class="form-select set-time-delay-type">
                    <option value="period" <?php selected( $settings['settings']['expiry_data']['type'] ?? '', 'period', true ) ?> ><?php esc_html_e( 'Esperar tempo', 'joinotify' ) ?></option>
                    <option value="date" <?php selected( $settings['settings']['expiry_data']['type'] ?? '', 'date', true ) ?> ><?php esc_html_e( 'Esperar atÃ© uma data', 'joinotify' ) ?></option>
                </select>
            </div>

            <div class="coupon-action-item wait-date-container d-none">
                <label class="form-label"><?php esc_html_e( 'Esperar atÃ©', 'joinotify' ) ?></label>
                
                <div class="input-group">
                    <input type="text" class="form-control dateselect get-date-value" value="<?php esc_attr_e( $settings['settings']['expiry_data']['date_value'] ?? '' ) ?>" placeholder="<?php esc_attr_e( 'Selecione uma data', 'joinotify' ) ?>"/>
                    <input type="time" class="form-control get-time-value" value="<?php esc_attr_e( $settings['settings']['expiry_data']['time_value'] ?? '' ) ?>"/>
                </div>
            </div>

            <div class="coupon-action-item wait-time-period-container d-none">
                <label class="form-label"><?php esc_html_e( 'Esperar por', 'joinotify' ) ?></label>
                
                <div class="input-group">
                    <input type="number" class="form-control get-wait-value" value="<?php esc_attr_e( $settings['settings']['expiry_data']['delay_value'] ?? '' ) ?>"/>

                    <select class="form-select get-wait-period">
                        <?php foreach ( Builder_Components::get_time_delay_options() as $option => $title ) : ?>
                            <option value="<?php esc_attr_e( $option ) ?>" <?php selected( $settings['settings']['expiry_data']['delay_period'] ?? '', $option, true ) ?>><?php esc_html_e( $title ) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="border-bottom divider my-5"></div>

            <div class="coupon-action-item">
                <?php echo Whatsapp::whatsapp_message_text_action( $settings['settings']['message'] ?? array() ); ?>
            </div>

            <div class="border-top divider mt-5 pt-4 coupon-placeholders">
                <label class="form-label"><?php esc_html_e( 'VariÃ¡veis de texto:', 'joinotify' ) ?></label>
                <?php echo Builder_Components::render_coupon_placeholders(); ?>
            </div>
        </div>

        <?php return ob_get_clean();
    }


    /**
     * Generate WooCommerce discount coupon
     *
     * @since 1.1.0
     * @version 1.4.7
     * @param array $coupon_data | Coupon settings data
     * @return mixed int|WP_Error Coupon ID or error
     */
    public static function generate_wc_coupon( $coupon_data ) {
        if ( empty( $coupon_data ) || ! isset( $coupon_data['discount_type'], $coupon_data['coupon_amount'] ) ) {
            error_log( 'Coupon data is empty or missing required fields.' );

            return new WP_Error( 'missing_data', __( 'Insufficient data to create the coupon.', 'joinotify' ) );
        }

        // Get prefix
        $coupon_prefix = Admin::get_setting('create_coupon_prefix');

        // Set coupon code
        $coupon_code = ( isset( $coupon_data['generate_coupon'] ) && $coupon_data['generate_coupon'] === 'yes' )
            ? $coupon_prefix . strtoupper( wp_generate_password( 6, false ) )
            : $coupon_data['coupon_code'];

        // Check if coupon already exists
        $query = new WP_Query( array(
            'post_type' => 'shop_coupon',
            'title' => $coupon_code,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));

        if ( $query->have_posts() ) {
            error_log( 'Coupon already exists.' );
            
            return new WP_Error( 'duplicate_coupon', __( 'O cupom jÃ¡ existe.', 'joinotify' ) );
        }

        // reset query
        wp_reset_postdata();

        // Create coupon
        $coupon = new WC_Coupon();
        $coupon->set_code( $coupon_code );
        $coupon->set_description( isset( $coupon_data['coupon_description'] ) ? $coupon_data['coupon_description'] : '' );

        // General settings
        $coupon->set_discount_type( $coupon_data['discount_type'] ); // Discount type (fixed_cart, percent, fixed_product)
        $coupon->set_amount( floatval( $coupon_data['coupon_amount'] ) ); // Discount value
        $coupon->set_free_shipping( isset( $coupon_data['free_shipping'] ) && $coupon_data['free_shipping'] === 'yes' );

        // Set coupon expiration
        $original_expiry_date = null;
        $expiry_date = null;

        if ( isset( $coupon_data['coupon_expiry'] ) && $coupon_data['coupon_expiry'] === 'yes' ) {
            if ( isset( $coupon_data['expiry_data']['type'] ) ) {
                if ( $coupon_data['expiry_data']['type'] === 'date' ) {
                    // Specific date
                    $date = $coupon_data['expiry_data']['date_value'];
                    $time = $coupon_data['expiry_data']['time_value'];

                    if ( ! empty( $date ) ) {
                        $original_expiry_date = ! empty( $time ) ? strtotime( $date . ' ' . $time ) : strtotime( $date );
                    }
                } elseif ( $coupon_data['expiry_data']['type'] === 'period' ) {
                    // Delay expiration
                    $delay_value = isset( $coupon_data['expiry_data']['delay_value'] ) ? intval( $coupon_data['expiry_data']['delay_value'] ) : 0;
                    $delay_period = isset( $coupon_data['expiry_data']['delay_period'] ) ? $coupon_data['expiry_data']['delay_period'] : '';
                    
                    if ( $delay_value > 0 && ! empty( $delay_period ) ) {
                        $original_expiry_date = time() + Schedule::get_delay_timestamp( $delay_value, $delay_period );
                    }
                }
            }

            // Define o expiry_date como a data original primeiro
            $expiry_date = $original_expiry_date;

            // Se a expiraÃ§Ã£o for menor que 24 horas, ajusta para o inÃ­cio do prÃ³ximo dia (23:59:59)
            if ( $expiry_date && ( $expiry_date - time() ) < DAY_IN_SECONDS ) {
                $expiry_date = strtotime( 'tomorrow 23:59:59' ); // Fim do dia seguinte
            }

            if ( $expiry_date ) {
                $coupon->set_date_expires( $expiry_date );
            }
        }

        // Save coupon
        $coupon->save();

        // Schedule an event to update expiration to the previous day usando a **data original**
        if ( ! empty( $original_expiry_date ) ) {
            wp_schedule_single_event( $original_expiry_date, 'joinotify_update_coupon_expiration', array( $coupon->get_id() ) );
        }

        return array(
            'coupon_id' => $coupon->get_id(),
            'coupon_code' => $coupon_code,
        );
    }


    /**
     * Get the list of placeholders for the coupon
     * 
     * @since 1.1.0
     * @param array $settings | The settings for the coupon
     * @return array The list of placeholders
     */
    public static function get_coupon_placeholders( $settings = array() ) {
        // get coupon code
        $coupon_code = isset( $settings['coupon_code'] ) ? $settings['coupon_code'] : '';
        $formatted_discount = '';
        $formatted_expires = '';

        // format the discount amount
        if ( isset( $settings['discount_type'] ) && $settings['discount_type'] === 'percent' ) {
            $discount_amount = isset( $settings['coupon_amount'] ) ? $settings['coupon_amount'] : '0';

            $formatted_discount = sprintf( __( '%s%%', 'joinotify' ), $discount_amount );
        } elseif ( isset( $settings['discount_type'] ) && $settings['discount_type'] === 'fixed_cart' ) {
            $discount = floatval( $settings['coupon_amount'] );
            $formatted_discount = wc_price( $discount );
        }

        // add coupon expires replacement
        if ( isset( $settings['coupon_expiry'] ) && $settings['coupon_expiry'] === 'yes' ) {
            if ( $settings['expiry_data']['type'] === 'period' ) {
                $time_value = $settings['expiry_data']['delay_value'] ?? '';
                $time_unit = $settings['expiry_data']['delay_period'] ?? '';

                // Format time unit: singular/plural
                $formatted_time = ( $time_value > 1 ) ? Helpers::format_time_unit( $time_unit, true ) : Helpers::format_time_unit( $time_unit, false );
                $formatted_expires = sprintf( esc_html__( 'Expires in %s %s', 'joinotify' ), $time_value, $formatted_time );
            } elseif ( $settings['expiry_data']['type'] === 'date' ) {
                $date_value = $settings['expiry_data']['date_value'] ?? '';
                $time_value = $settings['expiry_data']['time_value'] ?? '';

                if ( ! empty( $time_value ) ) {
                    $formatted_expires = sprintf( esc_html__( 'Expires in %s - %s', 'joinotify' ), $date_value, $time_value );
                } else {
                    $formatted_expires = sprintf( esc_html__( 'Expires in %s', 'joinotify' ), $date_value );
                }
            }
        } else {
            $formatted_expires = esc_html__( 'Coupon does not expire', 'joinotify' );
        }

        // add coupon placeholders on array
        $placeholders = apply_filters( 'Joinotify/Builder/Components/Coupon_Placeholders', array(
            '{{ joinotify_coupon_code }}' => array(
                'description' => esc_html__( 'To retrieve the coupon discount code.', 'joinotify' ),
                'replacement' => $coupon_code,
            ),
            '{{ joinotify_coupon_description }}' => array(
                'description' => esc_html__( 'To retrieve the coupon description.', 'joinotify' ),
                'replacement' => isset( $settings['coupon_description'] ) ? $settings['coupon_description'] : '',
            ),
            '{{ joinotify_coupon_discount_type }}' => array(
                'description' => esc_html__( 'To retrieve the coupon discount type. Example: Percentage or fixed amount.', 'joinotify' ),
                'replacement' => isset( $settings['discount_type'] ) && $settings['discount_type'] === 'percent' ? esc_html__( 'Percentage', 'joinotify' ) : esc_html__( 'Fixed amount', 'joinotify' ),
            ),
            '{{ joinotify_coupon_discount_value }}' => array(
                'description' => esc_html__( 'To retrieve the coupon discount amount.', 'joinotify' ),
                'replacement' => isset( $settings['coupon_amount'] ) ? $settings['coupon_amount'] : '',
            ),
            '{{ joinotify_coupon_discount_formatted }}' => array(
                'description' => esc_html__( 'To retrieve the coupon discount amount formatted with a currency symbol or percentage. Example: 10%.', 'joinotify' ),
                'replacement' => $formatted_discount,
            ),
            '{{ joinotify_coupon_expires }}' => array(
                'description' => esc_html__( 'To retrieve the coupon expiration. Example: Expires in 1 hour.', 'joinotify' ),
                'replacement' => $formatted_expires,
            ),
        ));

        return $placeholders;
    }


    /**
     * Add conditions for WooCommerce triggers
     * 
     * @since 1.2.0
     * @version 1.4.7
     * @param array $conditions | Current conditions
     * @return array
     */
    public function add_conditions( $conditions ) {
        // Define reusable conditions
        $common_conditions = array(
            'order_status' => array(
                'title' => __( 'Order status', 'joinotify' ),
                'description' => __( 'Allows checking the order status.', 'joinotify' ),
            ),
            'order_total' => array(
                'title' => __( 'Order total', 'joinotify' ),
                'description' => __( 'Allows checking the order total.', 'joinotify' ),
            ),
            'order_paid' => array(
                'title' => __( 'Order paid', 'joinotify' ),
                'description' => __( 'Allows checking whether the order was paid.', 'joinotify' ),
            ),
            'products_purchased' => array(
                'title' => __( 'Purchased products', 'joinotify' ),
                'description' => __( 'Allows checking the products purchased in the order.', 'joinotify' ),
            ),
            'payment_method' => array(
                'title' => __( 'Payment method', 'joinotify' ),
                'description' => __( 'Allows checking the order payment method.', 'joinotify' ),
            ),
            'shipping_method' => array(
                'title' => __( 'Shipping method', 'joinotify' ),
                'description' => __( 'Allows checking the order shipping method.', 'joinotify' ),
            ),
            'user_meta' => array(
                'title' => __( 'User metadata', 'joinotify' ),
                'description' => __( 'Permite verificar metadados especÃ­ficos do usuÃ¡rio.', 'joinotify' ),
            ),
            'customer_email' => array(
                'title' => __( 'Customer email', 'joinotify' ),
                'description' => __( 'Allows checking the customer email for the order.', 'joinotify' ),
            ),
            'refund_amount' => array(
                'title' => __( 'Refund amount', 'joinotify' ),
                'description' => __( 'Allows checking the order refund amount.', 'joinotify' ),
            ),
            'subscription_status' => array(
                'title' => __( 'Subscription status', 'joinotify' ),
                'description' => __( 'Allows checking the status of the created subscription.', 'joinotify' ),
            ),
        );
    
        // Define triggers and their associated conditions
        $woocommerce_conditions = array(
            'woocommerce_new_order' => array( 'order_total', 'order_paid', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_checkout_order_processed' => array( 'order_total', 'order_paid', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_order_status_completed' => array( 'customer_email', 'order_total', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta' ),
            'woocommerce_order_status_changed' => array( 'order_status', 'order_total', 'order_paid', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_order_partially_refunded' => array( 'refund_amount', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_order_fully_refunded' => array( 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_checkout_subscription_created' => array( 'order_paid', 'subscription_status', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
        );
    
        // Build the final conditions array dynamically
        $formatted_conditions = array();
        
        foreach ( $woocommerce_conditions as $trigger => $keys ) {
            foreach ( $keys as $key ) {
                $formatted_conditions[ $trigger ][ $key ] = $common_conditions[ $key ];
            }
        }
    
        return array_merge( $conditions, $formatted_conditions );
    }


    /**
     * Process workflow on receive new order on WooCommerce
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_on_new_order( $order_id ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/New_Order', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_new_order',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when order status is processing
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @param object $order | Order object
     * @param array $status_transition | Status transition data
     * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-order.html#source-view.411
     * @return void
     */
    public function process_workflow_order_processed( $order_id, $order, $status_transition ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Checkout_Order_Processed', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_checkout_order_processed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'status_transition' => $status_transition,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when order status is complete
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @param object $order | Order object
     * @param array $status_transition | Status transition
     * @return void
     */
    public function process_workflow_order_completed( $order_id, $order, $status_transition ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Status_Completed', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_status_completed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'status_transition' => $status_transition,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when order is fully refunded
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @param int $refund_id | Refund ID
     * @return void
     */
    public function process_workflow_order_fully_refunded( $order_id, $refund_id ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Fully_Refunded', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_fully_refunded',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'refund_id' => $refund_id,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when order is partially refunded
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param bool $is_partially_refunded | Is partially refunded
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_partially_refunded( $is_partially_refunded, $order_id ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Partially_Refunded', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_partially_refunded',
            'integration' => 'woocommerce',
            'is_partially_refunded' => $is_partially_refunded,
            'order_id' => $order_id,
        ));

        Workflow_Processor::process_workflows( $payload );
    }
    

    /**
     * Process workflow when order has status changed
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @param string $old_status | Old status
     * @param string $new_status | New status
     * @return void
     */
    public function process_workflow_order_status_changed( $order_id, $old_status, $new_status ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Status_Changed', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_status_changed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
	 * Ensure WooCommerce checkout context (session/customer) for admin screens.
	 *
	 * @since 1.4.5
	 * @return void
	 */
	public static function ensure_wc_checkout_context() {
		if ( ! function_exists('WC') || ! WC() ) {
			return;
		}

		// Ensure session.
		if ( empty( WC()->session ) ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}

		// Ensure customer.
		if ( empty( WC()->customer ) ) {
			WC()->customer = new WC_Customer( get_current_user_id(), true );
		}

		// Ensure cart (optional but helps some setups).
		if ( empty( WC()->cart ) ) {
			WC()->cart = new WC_Cart();
		}
	}


	/**
	 * Get all checkout fields available (raw WooCommerce array).
	 *
	 * @since 1.4.5
	 * @return array
	 */
	public static function get_checkout_fields_on_admin() {
		if ( ! class_exists('WooCommerce') || ! function_exists('WC') || ! WC()->checkout ) {
			return array();
		}

		self::ensure_wc_checkout_context();

		return WC()->checkout->get_checkout_fields();
	}
    

	/**
	 * Export checkout fields grouped by section (billing/shipping).
	 *
	 * @since 1.4.5
	 * @param string $section billing|shipping|all
	 * @return array
	 */
	public static function export_checkout_fields( $section = 'all' ) {
		$get_fields = self::get_checkout_fields_on_admin();

		if ( empty( $get_fields ) ) {
			return array(
				'billing'  => array(),
				'shipping' => array(),
			);
		}

		$billing  = isset( $get_fields['billing'] ) ? $get_fields['billing'] : array();
		$shipping = isset( $get_fields['shipping'] ) ? $get_fields['shipping'] : array();

		$result = array(
			'billing'  => $billing,
			'shipping' => $shipping,
		);

		if ( 'billing' === $section ) {
			$result = $billing;
		} elseif ( 'shipping' === $section ) {
			$result = $shipping;
		}

		/**
		 * Filter exported checkout fields.
		 *
		 * @since 1.4.5
		 * @param array  $result
		 * @param string $section
		 */
		return apply_filters( 'Joinotify/WooCommerce/Export_Checkout_Fields', $result, $section );
	}


	/**
	 * Get placeholders for checkout fields (billing/shipping separated).
	 *
	 * Ex:
	 * - billing_first_name -> {{ billing_first_name }}
	 * - shipping_address_1 -> {{ shipping_address_1 }}
	 *
	 * @since 1.4.5
	 * @param string $section billing|shipping
	 * @return array
	 */
	public static function get_checkout_placeholders_by_section( $section = 'billing' ) {
		$fields = self::export_checkout_fields( $section );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return array();
		}

		$placeholders = array();

		foreach ( $fields as $field_id => $field ) {
			// Remove prefix billing_ or shipping_ to show the "clean" placeholder id.
			$placeholder_id = $field_id;

			if ( 'billing' === $section && strpos( $field_id, 'billing_' ) === 0 ) {
				$placeholder_id = substr( $field_id, 8 );
			} elseif ( 'shipping' === $section && strpos( $field_id, 'shipping_' ) === 0 ) {
				$placeholder_id = substr( $field_id, 9 );
			}

			$label = isset( $field['label'] ) ? $field['label'] : $field_id;

			$placeholders[ $field_id ] = array(
				'field_id'          => $field_id,
				'placeholder_id'    => $placeholder_id,
				'placeholder_html'  => sprintf( '{{ %s }}', $placeholder_id ),
				'description'       => sprintf( __( 'To retrieve the value of %s', 'joinotify' ), $label ),
				'section'           => $section,
			);
		}

		/**
		 * Filter placeholders list by section.
		 *
		 * @since 1.4.5
		 * @param array  $placeholders
		 * @param string $section
		 */
		return apply_filters( 'Joinotify/WooCommerce/Checkout_Field_Placeholders', $placeholders, $section );
	}


	/**
	 * Convenience: get billing + shipping placeholders together (grouped).
	 *
	 * @since 1.4.5
	 * @return array
	 */
	public static function get_checkout_placeholders_grouped() {
		return array(
			'billing'  => self::get_checkout_placeholders_by_section( 'billing' ),
			'shipping' => self::get_checkout_placeholders_by_section( 'shipping' ),
		);
	}
}
