<?php

namespace MeuMouse\Joinotify\Integrations;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WooCommerce Subscriptions plugin
 * 
 * @since 1.0.0
 * @version 1.2.0
 * @package MeuMouse.com
 */
class Woo_Subscriptions extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        // check if WooCommerce Subscriptions is active
        if ( class_exists('WC_Subscriptions') ) {
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_subscription_triggers' ), 10, 1 );
        }
    }


    /**
     * Add subscription triggers on WooCommerce array
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param array $triggers | Current triggers array
     * @return array
     */
    public function add_subscription_triggers( $triggers ) {
        $new_triggers = array(
            array(
                'data_trigger' => 'woocommerce_checkout_subscription_created',
                'title' => esc_html__( 'Nova assinatura criada', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando uma nova assinatura é criada no WooCommerce.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_status_active',
                'title' => esc_html__( 'Assinatura é ativada', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando uma assinatura tem seu status alterado para ativo.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_payment_complete',
                'title' => esc_html__( 'Pagamento da renovação recebido', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando uma assinatura recorrente tem o pagamento recebido.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_payment_failed',
                'title' => esc_html__( 'Pagamento da renovação falhou', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando uma assinatura recorrente tem seu pagamento falhado ou recusado.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_status_expired',
                'title' => esc_html__( 'Renovação da assinatura expirou', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando o prazo de um assinatura expirou.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_status_cancelled',
                'title' => esc_html__( 'Assinatura tem status alterado para cancelado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando a assinatura tem seu status alterado para cancelado.', 'joinotify' ),
                'require_settings' => false,
            ),
        );

        $triggers['woocommerce'] = array_merge( $triggers['woocommerce'], $new_triggers );

        return $triggers;
    }
}