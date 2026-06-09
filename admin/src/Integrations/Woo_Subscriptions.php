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
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Woo_Subscriptions extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        // check if WooCommerce Subscriptions is active
        if ( class_exists('WC_Subscriptions') ) {
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_subscription_triggers' ), 20, 1 );

            // fire hooks if WooCommerce is active
            if ( Admin::get_setting('enable_woocommerce_integration') === 'yes' ) {
                // fire when a subscription is created
                add_action( 'woocommerce_checkout_subscription_created', array( $this, 'process_workflow_subscription_created' ), 10, 3 );

                // fire when a subscription status is active
                add_action( 'woocommerce_subscription_status_active', array( $this, 'process_workflow_subscription_status_active' ), 10, 1 );

                // fire when a subscription payment is complete
                add_action( 'woocommerce_subscription_payment_complete', array( $this, 'process_workflow_subscription_payment_complete' ), 10, 1 );

                // fire when a subscription payment is failed
                add_action( 'woocommerce_subscription_payment_failed', array( $this, 'process_workflow_subscription_payment_failed' ), 10, 2 );

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
     * @version 1.4.7
     * @param array $triggers | Current triggers array
     * @return array
     */
    public function add_subscription_triggers( $triggers ) {
        $new_triggers = array(
            array(
                'data_trigger' => 'woocommerce_checkout_subscription_created',
                'title' => __( 'New subscription created', 'joinotify' ),
                'description' => __( 'This trigger is fired when a new subscription is created in WooCommerce.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_status_active',
                'title' => __( 'Subscription is activated', 'joinotify' ),
                'description' => __( 'This trigger is fired when a subscription status changes to active.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_payment_complete',
                'title' => __( 'Renewal payment received', 'joinotify' ),
                'description' => __( 'This trigger is fired when a recurring subscription payment is received.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_payment_failed',
                'title' => __( 'Renewal payment failed', 'joinotify' ),
                'description' => __( 'This trigger is fired when a recurring subscription payment fails or is declined.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_status_expired',
                'title' => __( 'Subscription renewal expired', 'joinotify' ),
                'description' => __( 'This trigger is fired when a subscription term has expired.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_subscription_status_cancelled',
                'title' => __( 'Subscription canceled', 'joinotify' ),
                'description' => __( 'This trigger is fired when the subscription status changes to canceled.', 'joinotify' ),
                'require_settings' => false,
            ),
        );

        $triggers['woocommerce'] = array_merge( $triggers['woocommerce'], $new_triggers );

        return $triggers;
    }


    /**
     * Build the trigger payload, apply its filter and dispatch the workflows.
     *
     * Shared by every subscription hook callback below; only the hook name,
     * filter hook and hook-specific payload keys vary.
     *
     * @since 2.0.0
     * @param string $hook   The WooCommerce Subscriptions hook that fired.
     * @param string $filter The Joinotify payload filter to apply.
     * @param array  $extra  Hook-specific payload keys (subscription_id, etc.).
     * @return void
     */
    protected function dispatch_subscription_workflow( $hook, $filter, array $extra = array() ) {
        /**
         * Filter the payload before processing workflows
         *
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( $filter, array_merge( array(
            'type' => 'trigger',
            'hook' => $hook,
            'integration' => 'woocommerce',
        ), $extra ) );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription is created
     * 
     * @since 1.2.0
     * @version 1.4.7
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param object|WC_Order $order | A WC_Order instance representing the order for which subscriptions have been created
     * @param object|WC_Cart $recurring_cart | A WC_Cart instance representing the cart which stores the data used for creating this subscription
     */
    public function process_workflow_subscription_created( $subscription, $order, $recurring_cart ) {
        $this->dispatch_subscription_workflow(
            'woocommerce_checkout_subscription_created',
            'Joinotify/Process_Workflows/Woocommerce/Checkout_Subscription_Created',
            array(
                'subscription_id' => $subscription->get_id(),
                'order_id' => $order->get_id(),
                'recurring_cart' => $recurring_cart,
            )
        );
    }


    /**
     * Process workflow when subscription is activated
     * 
     * @since 1.2.0
     * @version 1.4.7
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_status_active( $subscription ) {
        $this->dispatch_subscription_workflow(
            'woocommerce_subscription_status_active',
            'Joinotify/Process_Workflows/Woocommerce/Subscription_Status_Active',
            array( 'subscription_id' => $subscription->get_id() )
        );
    }


    /**
     * Process workflow when subscription payment is complete
     * 
     * @since 1.2.0
     * @version 1.4.7
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_payment_complete( $subscription ) {
        $this->dispatch_subscription_workflow(
            'woocommerce_subscription_payment_complete',
            'Joinotify/Process_Workflows/Woocommerce/Subscription_Payment_Complete',
            array( 'subscription_id' => $subscription->get_id() )
        );
    }


    /**
     * Process workflow when subscription payment is failed
     * 
     * @since 1.2.0
     * @version 1.4.7
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param string $new_status | The new status of the subscription
     * @return void
     */
    public function process_workflow_subscription_payment_failed( $subscription, $new_status ) {
        $this->dispatch_subscription_workflow(
            'woocommerce_subscription_payment_failed',
            'Joinotify/Process_Workflows/Woocommerce/Subscription_Payment_Failed',
            array(
                'subscription_id' => $subscription->get_id(),
                'new_status' => $new_status,
            )
        );
    }


    /**
     * Process workflow when subscription status is expired
     * 
     * @since 1.2.0
     * @version 1.4.7
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_status_expired( $subscription ) {
        $this->dispatch_subscription_workflow(
            'woocommerce_subscription_status_expired',
            'Joinotify/Process_Workflows/Woocommerce/Subscription_Status_Expired',
            array( 'subscription_id' => $subscription->get_id() )
        );
    }


    /**
     * Process workflow when subscription status is cancelled
     * 
     * @since 1.2.0
     * @version 1.4.7
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_status_cancelled( $subscription ) {
        $this->dispatch_subscription_workflow(
            'woocommerce_subscription_status_cancelled',
            'Joinotify/Process_Workflows/Woocommerce/Subscription_Status_Cancelled',
            array( 'subscription_id' => $subscription->get_id() )
        );
    }
}