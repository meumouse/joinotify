<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WooCommerce
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Woocommerce extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function __construct() {
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
            add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_coupon_action' ), 10, 1 );
        }
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
                'title' => esc_html__( 'Novo pedido', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um novo pedido é recebido no WooCommerce com qualquer status.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_checkout_order_processed',
                'title' => esc_html__( 'Novo pedido (Processando)', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um novo pedido é recebido no WooCommerce com status processando.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_status_completed',
                'title' => esc_html__( 'Pedido concluído', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um pedido tem o status alterado para concluído.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_fully_refunded',
                'title' => esc_html__( 'Pedido totalmente reembolsado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um pedido é totalmente reembolsado.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_partially_refunded',
                'title' => esc_html__( 'Pedido parcialmente reembolsado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um pedido é parcialmente reembolsado.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_status_changed',
                'title' => esc_html__( 'Status de um pedido alterado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um pedido tem seu status alterado.', 'joinotify' ),
                'require_settings' => true,
            ),
        );

        return $triggers;
    }


    /**
     * Add Woocommerce triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.1.0
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
     * @version 1.1.0
     * @return void
     */
    public function add_triggers_content() {
        $this->render_integration_trigger_content('woocommerce');
    }


    /**
     * Add WooCommerce placeholders on workflow builder
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $placeholders | Current placeholders
     * @param array $context | Context data
     * @return array
     */
    public function add_placeholders( $placeholders, $context ) {
        $order = isset( $context['order_id'] ) ? wc_get_order( $context['order_id'] ) : null;
        $current_user = wp_get_current_user();
        $trigger_names = Triggers::get_trigger_names('woocommerce');
    
        $placeholders['woocommerce'] = array(
            '{{ wc_billing_first_name }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o primeiro nome de faturamento do cliente no pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_first_name() : '',
                    'sandbox' => $current_user->exists() ? $current_user->first_name : esc_html__( 'João', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_last_name }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o sobrenome de faturamento do cliente no pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_last_name() : '',
                    'sandbox' => $current_user->exists() ? $current_user->last_name : esc_html__( 'da Silva', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_email }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o e-mail de faturamento do cliente no pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ?  $order->get_billing_email() : '',
                    'sandbox' => $current_user->exists() ? $current_user->user_email : esc_html__( 'usuario@exemplo.com', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_phone }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o telefone de faturamento do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_phone() : '',
                    'sandbox' => esc_html__( '+55 11 91234-5678', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_phone }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o telefone de entrega do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_shipping_phone() : '',
                    'sandbox' => esc_html__( '+55 41 91234-5678', 'joinotify' ),
                ),
            ),
            '{{ wc_order_number }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o número do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_order_number() : '',
                    'sandbox' => esc_html__( '12345', 'joinotify' ),
                ),
            ),
            '{{ wc_order_status }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o status do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ?  wc_get_order_status_name( $order->get_status() ) : '',
                    'sandbox' => esc_html__( 'Concluído', 'joinotify' ),
                ),
            ),
            '{{ wc_order_date }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar a data do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? date_i18n( get_option('date_format'), strtotime( $order->get_date_created() ) ) : '',
                    'sandbox' => date( get_option('date_format') ),
                ),
            ),
            '{{ wc_billing_full_address }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o endereço completo de faturamento do usuário', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_full_address( $order, 'billing' ) : '',
                    'sandbox' => esc_html__( 'Rua das Flores, 123 - Curitiba/PR - Brasil (CEP: 80000-000)', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_full_address }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o endereço completo de entrega do usuário', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_full_address( $order, 'shipping' ) : '',
                    'sandbox' => esc_html__( 'Rua das Margaridas, 450 - Curitiba/PR - Brasil (CEP: 80000-100)', 'joinotify' ),
                ),
            ),
            '{{ wc_purchased_items }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar cada produto e quantidade adquiridos no pedido, separados por linha', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_purchased_items( $order ) : '',
                    'sandbox' => sprintf( '%s %s %s', esc_html__( '1x - Camiseta de algodão masculina (Produto exemplo)', 'joinotify' ), "\n",  esc_html__( '1x - Óculos de sol com proteção UV (Produto exemplo)', 'joinotify' ) ),
                ),
            ),
            '{{ wc_payment_url }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar a URL de pagamento do pedido', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_checkout_payment_url() : '',
                    'sandbox' => sprintf( esc_html__( '%s/checkout/pay/order/12345', 'joinotify' ), get_site_url() ),
                ),
            ),
            '{{ wc_currency_symbol }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o símbolo de moeda do pedido', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? get_woocommerce_currency_symbol( $order->get_currency() ) : '',
                    'sandbox' => get_woocommerce_currency_symbol(),
                ),
            ),
            '{{ wc_order_total }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o valor total do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_total() : '',
                    'sandbox' => wc_price( 150 ),
                ),
            ),
            '{{ wc_total_discount }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o valor total de desconto do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_total_discount() : '',
                    'sandbox' => wc_price( 20 ),
                ),
            ),
            '{{ wc_total_tax }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o valor total de impostos do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_total_tax() : '',
                    'sandbox' => wc_price( 15 ),
                ),
            ),
            '{{ wc_total_refunded }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o valor total reembolsado do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_total_refunded() : '',
                    'sandbox' => wc_price( 10 ),
                ),
            ),
            '{{ wc_coupon_codes }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar os códigos de cupom utilizados no pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? implode(', ', $order->get_coupon_codes()) : '',
                    'sandbox' => esc_html__( 'CUPOM10, FRETEGRATIS', 'joinotify' ),
                ),
            ),
            '{{ wc_payment_method_title }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o título do método de pagamento do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_payment_method_title() : '',
                    'sandbox' => esc_html__( 'Cartão de crédito', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_address }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o endereço de entrega do pedido do WooCommerce', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_shipping_to_display() : '',
                    'sandbox' => esc_html__( 'Rua das Margaridas, 450 - Curitiba/PR - Brasil (CEP: 80000-100)', 'joinotify' ),
                ),
            ),
            '{{ wc_checkout_field=[FIELD_ID] }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Para recuperar o valor de um campo específico do checkout no pedido do WooCommerce. Substitua FIELD_ID pelo ID do campo do checkout, por exemplo: billing_email.', 'joinotify' ),
                'replacement' => array(),
            ),
        );

        return $placeholders;
    }


    /**
     * Returns the items purchased in the order formatted
     *
     * @since 1.0.0
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

            $purchased_items[] = sprintf(
                '%dx - %s%s',
                $item->get_quantity(),
                $product_name,
                $variation_details
            );
        }

        return ! empty( $purchased_items ) ? implode( "\n", $purchased_items ) : esc_html__( 'Nenhum item adquirido.', 'joinotify' );
    }


    /**
     * Get the full address of the order
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param \WC_Order $order | WooCommerce Order object
     * @return string Full address as a formatted string
     */
    public static function get_full_address( $order, $type = 'billing' ) {
        $format = '';

        $address = array(
            $order->{"get_{$type}_address_1"}(),
            $order->{"get_{$type}_address_2"}(),
            $order->{"get_{$type}_city"}(),
            $order->{"get_{$type}_state"}(),
            $order->{"get_{$type}_postcode"}(),
            $order->{"get_{$type}_country"}(),
        );

        // Filter out empty values and join with a comma.
        return implode( ', ', array_filter( $address ) );
    }


    /**
     * Add modal settings for WooCommerce
     * 
     * @since 1.1.0
     * @return void
     */
    public function add_modal_settings() {
        if ( Admin::get_setting('enable_woocommerce_integration') === 'yes' ) : ?>
            <button id="woocommerce_settings_trigger" class="btn btn-outline-primary mb-5"><?php esc_html_e( 'Configurações', 'joinotify' ); ?></button>
		
            <div id="woocommerce_settings_container" class="joinotify-popup-container">
                <div class="joinotify-popup-content">
                    <div class="joinotify-popup-header">
                        <h5 class="joinotify-popup-title"><?php esc_html_e( 'Configurações da integração com WooCommerce', 'joinotify' ); ?></h5>
                        <button id="woocommerce_settings_container" class="btn-close fs-lg" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ); ?>"></button>
                    </div>

                    <div class="joinotify-popup-body my-3">
                        <table class="popup-table">
                            <tbody>

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
            'title' => esc_html__( 'Cupom de desconto', 'joinotify' ),
            'description' => esc_html__( 'Envie um cupom de desconto para seu usuário através de mensagem de texto do WhatsApp.', 'joinotify' ),
            'context' => array(
                'woocommerce',
            ),
            'icon' => '<svg class="icon icon-lg icon-dark coupon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path fill-rule="evenodd" clip-rule="evenodd" d="M3.75 6.75L4.5 6H20.25L21 6.75V10.7812H20.25C19.5769 10.7812 19.0312 11.3269 19.0312 12C19.0312 12.6731 19.5769 13.2188 20.25 13.2188H21V17.25L20.25 18L4.5 18L3.75 17.25V13.2188H4.5C5.1731 13.2188 5.71875 12.6731 5.71875 12C5.71875 11.3269 5.1731 10.7812 4.5 10.7812H3.75V6.75ZM5.25 7.5V9.38602C6.38677 9.71157 7.21875 10.7586 7.21875 12C7.21875 13.2414 6.38677 14.2884 5.25 14.614V16.5L9 16.5L9 7.5H5.25ZM10.5 7.5V16.5L19.5 16.5V14.614C18.3632 14.2884 17.5312 13.2414 17.5312 12C17.5312 10.7586 18.3632 9.71157 19.5 9.38602V7.5H10.5Z"></path></g></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'settings' => self::create_coupon_action_settings(),
            'priority' => 60,
        );

        return $actions;
    }


    /**
     * Render coupon action settings on sidebar action
     * 
     * @since 1.1.0
     * @return string
     */
    public static function create_coupon_action_settings() {
        ob_start(); ?>

        <div class="coupon-action-group">
            <div class="coupon-action-item">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Gerar código do cupom automaticamente', 'joinotify' ) ?></span>
                <input type="checkbox" class="toggle-switch" id="joinotify_generate_coupon_code">
            </div>

            <div class="coupon-action-item coupon-code-wrapper">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Código do cupom', 'joinotify' ) ?></span>
                <input type="text" id="joinotify_set_coupon_code" class="form-control" placeholder="<?php esc_attr_e( 'CUPOM_EXCLUSIVO', 'joinotify' ) ?>"/>
            </div>

            <div class="coupon-action-item">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Descrição do cupom (opcional)', 'joinotify' ) ?></span>
                <input type="text" id="joinotify_set_coupon_description" class="form-control" placeholder="<?php esc_attr_e( 'Cupom para recuperação de compras', 'joinotify' ) ?>"/>
            </div>

            <div class="coupon-action-item">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Tipo e valor do desconto', 'joinotify' ) ?></span>
                
                <div class="input-group">
                    <select id="joinotify_coupon_discount_type" class="form-select">
                        <option value="fixed_cart"><?php esc_html_e( 'Desconto fixo', 'joinotify' ) ?></option>
                        <option value="percent"><?php esc_html_e( 'Percentual', 'joinotify' ) ?></option>
                    </select>

                    <input type="text" id="joinotify_set_discount_value" class="form-control" placeholder="<?php esc_attr_e( 'Valor do desconto', 'joinotify' ) ?>"/>
                </div>
            </div>

            <div class="coupon-action-item">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Permitir frete grátis', 'joinotify' ) ?></span>
                <input type="checkbox" id="joinotify_coupon_allow_free_shipping" class="toggle-switch">
            </div>

            <div class="coupon-action-item">
                <span class="fs-md text-muted mb-2 ms-2 d-block"><?php esc_html_e( 'Data de expiração', 'joinotify' ) ?></span>
                <input type="text" id="joinotify_coupon_expires_date" class="form-control dateselect">
            </div>
        </div>

        <?php return ob_get_clean();
    }
}