<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WooCommerce Subscriptions plugin
 * 
 * @since 1.0.0
 * @version 1.2.2
 * @package MeuMouse.com
 */
class Woo_Subscriptions extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.2.2
     * @return void
     */
    public function __construct() {
        // check if WooCommerce Subscriptions is active
        if ( class_exists('WC_Subscriptions') ) {
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_subscription_triggers' ), 10, 1 );

            // fire hooks if WooCommerce is active
            if ( Admin::get_setting('enable_woocommerce_integration') === 'yes' ) {
                // fire when a subscription is created
                add_action( 'woocommerce_checkout_subscription_created', array( $this, 'process_workflow_subscription_created' ), 10, 3 );

                // fire when a subscription status is active
                add_action( 'woocommerce_subscription_status_active', array( $this, 'process_workflow_subscription_status_active' ), 10, 3 );

                // fire when a subscription payment is complete
                add_action( 'woocommerce_subscription_payment_complete', array( $this, 'process_workflow_subscription_payment_complete', 10, 1 ) );

                // fire when a subscription payment is failed
                add_action( 'woocommerce_subscription_payment_failed', array( $this, 'process_workflow_subscription_payment_failed', 10, 2 ) );

                // fire when a subscription status is expired
                add_action( 'woocommerce_subscription_status_expired', array(  $this, 'process_workflow_subscription_status_expired' ), 10, 1 );

                // fire when a subscription status is cancelled
                add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'process_workflow_subscription_status_cancelled' ), 10, 1 );
            }
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


    /**
     * Process workflow when subscription is created
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param object|WC_Order $order | A WC_Order instance representing the order for which subscriptions have been created
     * @param object|WC_Cart $recurring_cart | A WC_Cart instance representing the cart which stores the data used for creating this subscription
     */
    public function process_workflow_subscription_created( $subscription, $order, $recurring_cart ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_checkout_subscription_created',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
            'order' => $order,
            'order_id' => $order->get_id(),
            'recurring_cart' => $recurring_cart,
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription is activated
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param string $new_status | The new status of the subscription
     * @param string $old_status | The old status of the subscription
     * @return void
     */
    public function process_workflow_subscription_status_active( $subscription, $new_status, $old_status ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_status_active',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
            'new_status' => $new_status,
            'old_status' => $old_status,
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription payment is complete
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_payment_complete( $subscription ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_payment_complete',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription payment is failed
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param string $new_status | The new status of the subscription
     * @return void
     */
    public function process_workflow_subscription_payment_failed( $subscription, $new_status ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_payment_failed',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
            'new_status' => $new_status,
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription status is expired
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_status_expired( $subscription ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_status_expired',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription status is cancelled
     * 
     * @since 1.2.0
     * @version 1.2.2
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_status_cancelled( $subscription ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_status_cancelled',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
        );

        Workflow_Processor::process_workflows( $payload );
    }
}