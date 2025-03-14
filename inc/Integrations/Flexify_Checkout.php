<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Flexify Checkout for WooCommerce plugin
 * 
 * @since 1.0.0
 * @version 1.2.2
 * @package MeuMouse.com
 */
class Flexify_Checkout extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.2.2
     * @return void
     */
    public function __construct() {
        // check if Flexify Checkout is active
        if ( class_exists('\MeuMouse\Flexify_Checkout\Flexify_Checkout') ) {
            // add triggers
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );

            // add trigger tab
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 30 );

            // add trigger content
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );

            // add placeholders
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 10, 2 );

            // add conditions
            add_filter( 'Joinotify/Validations/Get_Action_Conditions', array( $this, 'add_conditions' ), 10, 1 );

            // check if Flexify Checkout extension addon is active
            if ( class_exists('Flexify_Checkout_Recovery_Carts') ) {
                // when a order is abandoned
                add_action( 'Flexify_Checkout/Recovery_Carts/Order_Abandoned', array( $this, 'process_workflow_order_abandoned' ), 10, 2 );

                // when a cart is abandoned
                add_action( 'Flexify_Checkout/Recovery_Carts/Cart_Abandoned', array( $this, 'process_workflow_cart_abandoned' ), 10, 1 );

                // when a cart is recovered
                add_action( 'Flexify_Checkout/Recovery_Carts/Cart_Recovered', array( $this, 'process_workflow_cart_recovered' ), 10, 2 );

                // when a cart is lost
                add_action( 'Flexify_Checkout/Recovery_Carts/Cart_Lost', array( $this, 'process_workflow_cart_lost' ), 10, 1 );
            }
        }
    }


    /**
     * Add Flexify Checkout triggers
     * 
     * @since 1.1.0
     * @version 1.2.0
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['flexify_checkout'] = array(
            array(
                'data_trigger' => 'Flexify_Checkout/Recovery_Carts/Cart_Abandoned',
                'title' => esc_html__( 'Abandono do carrinho', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando o usuário abandona o carrinho.', 'joinotify' ),
                'require_settings' => false,
                'require_plugins' => true,
                'plugins' => array(
                    array(
                        'name' => esc_html__( 'Flexify Checkout - Recuperação de carrinhos abandonados', 'joinotify' ),
                        'slug' => 'flexify-checkout-recovery-carts-addon/flexify-checkout-recovery-carts-addon.php',
                        'download_url' => 'https://github.com/meumouse/flexify-checkout-recovery-carts-addon/raw/refs/heads/main/dist/flexify-checkout-recovery-carts-addon.zip',
                    ),
                ),
            ),
            array(
                'data_trigger' => 'Flexify_Checkout/Recovery_Carts/Cart_Recovered',
                'title' => esc_html__( 'Carrinho abandonado é recuperado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um carrinho abandonado é recuperado.', 'joinotify' ),
                'require_settings' => false,
                'require_plugins' => true,
                'plugins' => array(
                    array(
                        'name' => esc_html__( 'Flexify Checkout - Recuperação de carrinhos abandonados', 'joinotify' ),
                        'slug' => 'flexify-checkout-recovery-carts-addon/flexify-checkout-recovery-carts-addon.php',
                        'download_url' => 'https://github.com/meumouse/flexify-checkout-recovery-carts-addon/raw/refs/heads/main/dist/flexify-checkout-recovery-carts-addon.zip',
                    ),
                ),
            ),
            array(
                'data_trigger' => 'Flexify_Checkout/Recovery_Carts/Order_Abandoned',
                'title' => esc_html__( 'Abandono do pedido', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um pedido é abandonado.', 'joinotify' ),
                'require_settings' => false,
                'require_plugins' => true,
                'plugins' => array(
                    array(
                        'name' => esc_html__( 'Flexify Checkout - Recuperação de carrinhos abandonados', 'joinotify' ),
                        'slug' => 'flexify-checkout-recovery-carts-addon/flexify-checkout-recovery-carts-addon.php',
                        'download_url' => 'https://github.com/meumouse/flexify-checkout-recovery-carts-addon/raw/refs/heads/main/dist/flexify-checkout-recovery-carts-addon.zip',
                    ),
                ),
            ),
            array(
                'data_trigger' => 'Flexify_Checkout/Recovery_Carts/Cart_Lost',
                'title' => esc_html__( 'Carrinho perdido', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um carrinho é considerado perdido.', 'joinotify' ),
                'require_settings' => false,
                'require_plugins' => true,
                'plugins' => array(
                    array(
                        'name' => esc_html__( 'Flexify Checkout - Recuperação de carrinhos abandonados', 'joinotify' ),
                        'slug' => 'flexify-checkout-recovery-carts-addon/flexify-checkout-recovery-carts-addon.php',
                        'download_url' => 'https://github.com/meumouse/flexify-checkout-recovery-carts-addon/raw/refs/heads/main/dist/flexify-checkout-recovery-carts-addon.zip',
                    ),
                ),
            ),
            array(
                'data_trigger' => 'flexify_checkout_entry_step_1',
                'title' => esc_html__( 'Ao entrar na etapa 1', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 1 da finalização de compras.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'flexify_checkout_entry_step_2',
                'title' => esc_html__( 'Ao entrar na etapa 2', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 2 da finalização de compras.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'flexify_checkout_entry_step_3',
                'title' => esc_html__( 'Ao entrar na etapa 3', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 3 da finalização de compras.', 'joinotify' ),
                'require_settings' => false,
            ),
        );

        return $triggers;
    }


    /**
     * Add Flexify Checkout triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'flexify_checkout';
        $integration_name = esc_html__( 'Flexify Checkout', 'joinotify' );
        $icon_svg = '<svg class="joinotify-tab-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 945.76 891.08"><path d="M514,116.38c-234.22,0-424.08,189.87-424.08,424.07S279.74,964.53,514,964.53,938,774.67,938,540.45,748.17,116.38,514,116.38Zm171.38,426.1c-141.76.37-257.11,117.69-257.4,259.45H339.72c0-191.79,153.83-347.42,345.62-347.42Zm0-176.64c-141.76.19-266.84,69.9-346,176.13V410.6C431,328.12,551.92,277.5,685.34,277.5Z" transform="translate(-89.88 -73.45)"/><circle cx="779.75" cy="166.01" r="166.01" style="fill:#fff"/><path d="M785.1,285.69c-9.31-37.24-14-55.85-4.19-68.37s29-12.52,67.35-12.52h50.25c38.38,0,57.57,0,67.34,12.52s5.12,31.13-4.18,68.37c-5.93,23.68-8.89,35.52-17.72,42.42s-21,6.89-45.44,6.89H848.26c-24.41,0-36.62,0-45.45-6.89S791,309.37,785.1,285.69Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M954.76,210.22,947.05,182c-3-10.9-4.45-16.35-7.5-20.45a27.08,27.08,0,0,0-11.91-9.09c-4.76-1.86-10.41-1.86-21.7-1.86M792,210.22l7.7-28.27c3-10.9,4.46-16.35,7.51-20.45a27.11,27.11,0,0,1,11.9-9.09c4.77-1.86,10.42-1.86,21.71-1.86" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M840.83,150.55a10.85,10.85,0,0,1,10.85-10.85h43.41a10.85,10.85,0,1,1,0,21.7H851.68A10.85,10.85,0,0,1,840.83,150.55Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M830,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M916.79,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M873.38,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/></svg>';

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
        $this->render_integration_trigger_content('flexify_checkout');
    }


    /**
     * Add Flexify Checkout placeholders on workflow builder
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param array $placeholders | Current placeholders
     * @param array $payload | Payload data
     * @return array
     */
    public function add_placeholders( $placeholders, $payload ) {
        $order = isset( $payload['order_id'] ) ? wc_get_order( $payload['order_id'] ) : null;
        $trigger_names = Triggers::get_trigger_names('woocommerce');

        // if Inter bank addon is active
        if ( class_exists('Module_Inter_Bank') ) {
            $inter_placeholders = array(
                '{{ fc_inter_pix_copia_cola }}' => array(
                    'triggers' => $trigger_names,
                    'description' => esc_html__( 'Para recuperar o código Pix Copia e Cola do pedido. Através da integração Flexify Checkout - Inter addon', 'joinotify' ),
                    'replacement' => array(
                        'production' => isset( $order ) ? $order->get_meta('inter_pix_payload') : '',
                        'sandbox' => '00020126330014BR.GOV.BCB.PIX0114+5581999999999520400005303986540540.005802BR5925_MEUMOUSE.COM_6008BRASIL62070503***6304ABCD',
                    ),
                ),
                '{{ fc_inter_pix_expiration_time }}' => array(
                    'triggers' => $trigger_names,
                    'description' => esc_html__( 'Para recuperar o tempo de expiração do Pix Copia e Cola. Através da integração Flexify Checkout - Inter addon', 'joinotify' ),
                    'replacement' => array(
                        'production' => isset( $order ) ? sprintf( esc_html__( '%s minutos', 'joinotify' ), $order->get_meta('inter_pix_expires_in') ) : '',
                        'sandbox' => esc_html__( '30 minutos', 'joinotify' ),
                    ),
                ),
                '{{ fc_inter_bank_slip_url }}' => array(
                    'triggers' => $trigger_names,
                    'description' => esc_html__( 'Para recuperar o tempo de expiração do Pix Copia e Cola. Através da integração Flexify Checkout - Inter addon', 'joinotify' ),
                    'replacement' => array(
                        'production' => isset( $order ) ? $order->get_meta('inter_boleto_url') : '',
                        'sandbox' => esc_html__( '30 minutos', 'joinotify' ),
                    ),
                ),
            );
            
            // add inter placeholders to woocommerce
            foreach ( $inter_placeholders as $placeholder_key => $placeholder_data ) {
                $placeholders['woocommerce'][$placeholder_key] = array(
                    'triggers' => $trigger_names,
                    'description' => $placeholder_data['description'],
                    'replacement' => array(
                        'production' => $placeholder_data['replacement']['production'],
                        'sandbox' => $placeholder_data['replacement']['sandbox'],
                    ),
                );
            }
        }

        // if Recovery Carts addon is active
        if ( class_exists('Flexify_Checkout_Recovery_Carts') ) {
            $placeholders['flexify_checkout'] = array(
                '{{ fcrc_recovery_link }}' => array(
                    'triggers' => array(
                        'Flexify_Checkout/Recovery_Carts/Order_Abandoned',
                        'Flexify_Checkout/Recovery_Carts/Cart_Abandoned',
                        'Flexify_Checkout/Recovery_Carts/Cart_Recovered',
                        'Flexify_Checkout/Recovery_Carts/Cart_Lost',
                    ),
                    'description' => esc_html__( 'Link de recuperação do carrinho abandonado. Através da integração Flexify Checkout - Recuperação de carrinhos abandonados.', 'joinotify' ),
                    'replacement' => array(
                        'production' => class_exists('\MeuMouse\Flexify_Checkout\Recovery_Carts\Core') ? \MeuMouse\Flexify_Checkout\Recovery_Carts\Core\Helpers::generate_recovery_cart_link( $payload['cart_id'] ) : '',
                        'sandbox' => wc_get_checkout_url() . '?recovery_cart=10905',
                    ),
                ),
            );
        }

        return $placeholders;
    }


    /**
     * Add conditions for Flexify Checkout triggers
     * 
     * @since 1.2.0
     * @param array $conditions | Current conditions
     * @return array
     */
    public function add_conditions( $conditions ) {
        $fc_conditions = array(
            'Flexify_Checkout/Recovery_Carts/Cart_Abandoned' => array(
                'cart_total' => array(
                    'title' => __( 'Valor total do carrinho', 'joinotify' ),
                    'description' => __( 'Permite verificar o valor total do carrinho abandonado.', 'joinotify' ),
                ),
                'items_in_cart' => array(
                    'title' => __( 'Itens no carrinho', 'joinotify' ),
                    'description' => __( 'Permite verificar os itens presentes no carrinho abandonado.', 'joinotify' ),
                ),
                'user_meta' => array(
                    'title' => __( 'Meta dados do usuário', 'joinotify' ),
                    'description' => __( 'Permite verificar metadados específicos do usuário que solicitou a redefinição de senha.', 'joinotify' ),
                ),
                'cart_recovered' => array(
                    'title' => __( 'Carrinho recuperado', 'joinotify' ),
                    'description' => __( 'Permite verificar se o carrinho abandonado foi recuperado.', 'joinotify' ),
                ),
            ),
        );

        return array_merge( $conditions, $fc_conditions );
    }


    /**
     * Process workflow when a order is abandoned
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param int $order_id | The abandoned order ID
     * @param int $cart_id | The abandoned cart ID
     * @return void
     */
    public function process_workflow_order_abandoned( $order_id, $cart_id ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'Flexify_Checkout/Recovery_Carts/Order_Abandoned',
            'integration' => 'flexify_checkout',
            'order_id' => $order_id,
            'cart_id' => $cart_id,
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when a cart is abandoned
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param int $cart_id | The abandoned cart ID
     * @return void
     */
    public function process_workflow_cart_abandoned( $cart_id ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'Flexify_Checkout/Recovery_Carts/Cart_Abandoned',
            'integration' => 'flexify_checkout',
            'cart_id' => $cart_id,
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when a cart is recovered
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param int $cart_id | The recovered cart ID
     * @param int $order_id | The order ID
     * @return void
     */
    public function process_workflow_cart_recovered( $cart_id, $order_id ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'Flexify_Checkout/Recovery_Carts/Cart_Recovered',
            'integration' => 'flexify_checkout',
            'cart_id' => $cart_id,
            'order_id' => $order_id,
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when a cart is lost
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param int $cart_id | The recovered cart ID
     * @return void
     */
    public function process_workflow_cart_lost( $cart_id ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'Flexify_Checkout/Recovery_Carts/Cart_Lost',
            'integration' => 'flexify_checkout',
            'cart_id' => $cart_id,
        );

        Workflow_Processor::process_workflows( $payload );
    }
}